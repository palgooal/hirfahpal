<?php

declare(strict_types=1);

/*
 * HIRFAH - Vite package installer (deployment phase 2.2.4).
 *
 * Installs ONE Vite package, validated by deploy/vite-package-validate.php, into
 *     <app>/public/build   and   <web>/build
 * Standalone PHP CLI: no Laravel, no vendor/, no .env, no database, no Composer, no npm/Node, no network.
 * Everything temporary lives under <packages-root> (outside the application and the web root):
 *     incoming/  packages (read only)        work/      validator work directories
 *     stage/     staged trees per transaction  previous/  replaced builds (the last 2 kept after a success)
 *     state/     deploy.lock (flock) and install.journal
 *
 * --mode=install: lock; recover first if an earlier run was interrupted (and then FAIL); run the validator in install
 *   mode with --stage-dir, so the very tree it verified is staged (nothing is extracted again); make the two target
 *   copies, set modes (directories 0755, files 0644) and verify them against PACKAGE.json; write the journal; swap
 *   each target with two renames (web root first, then the application); verify the installed result completely;
 *   commit; only then remove old previous builds. Any failure before the commit rolls back what was swapped.
 * --mode=recover: lock; if a journal exists, recover it (roll back, or finish the cleanup of a committed run) and
 *   FAIL so that the interruption is noticed; otherwise PASS with ACTION=none.
 *
 * The two targets are NOT swapped atomically as one unit. The journal records the transaction before the first
 * rename; recovery inspects the actual state of every path (content digests) and only performs the renames that
 * lead back to the recorded old state. Any state it cannot explain is left untouched (no deletion) and reported as
 * manual-recovery-required. Links are never followed; current builds are never deleted, only moved to previous/.
 *
 * Usage:
 *   php vite-package-install.php --mode=install --packages-root <dir> --package-dir <incoming/<id>> --app <dir>
 *       --web <dir> [--tar /bin/tar] [--git git]
 *   php vite-package-install.php --mode=recover --packages-root <dir> --app <dir> --web <dir>
 * Output: fixed key=value lines (validator output goes to stderr, prefixed). Exit codes: 0 PASS, 1 FAIL, 2 usage or
 * internal error.
 */

const TXN_PATTERN = '/\A[0-9]{8}T[0-9]{6}Z-[0-9a-f]{8}\z/';
const PACKAGE_ID_PATTERN = '/\Ahirfah-vite-[0-9a-f]{12}-[0-9]{8}T[0-9]{6}Z\z/';
const KEEP_PREVIOUS = 2;
const LIMIT_PACKAGE_JSON_BYTES = 1024 * 1024;
const VITE_INPUT_PATTERN = '/[\'"](resources\/[^\'"]+\.(?:js|css))[\'"]/';
const OUTPUT_KEYS = ['MODE', 'ACTION', 'PACKAGE_ID', 'SOURCE_COMMIT', 'PREVIOUS', 'RESULT', 'REASON'];

final class InstallFailure extends RuntimeException {}
final class UsageError extends RuntimeException {}
final class AmbiguousState extends RuntimeException {}

final class Installer
{
    /** @var array<string, string> */
    public array $out = [];

    private string $root = '';

    private string $app = '';

    private string $web = '';

    /** @var array<string, string> target name => path, in install order */
    private array $targets = [];

    /** @var resource|null */
    private $lock = null;

    /** @param array<string, string> $opt */
    public function __construct(private array $opt)
    {
        $this->out = array_fill_keys(OUTPUT_KEYS, '');
        $this->out['MODE'] = $opt['mode'];
        $this->out['ACTION'] = 'none';
        $this->out['RESULT'] = 'FAIL';
    }

    public function run(): void
    {
        $this->checkPaths();
        $this->acquireLock();
        if (file_exists($this->journalPath())) {
            $this->recoverJournal();   // always ends in a failure: the interruption must be noticed
        }
        @unlink($this->journalPath().'.tmp');   // a journal write that never completed: no swap had started
        if ($this->opt['mode'] === 'recover') {
            $this->out['RESULT'] = 'PASS';
            $this->out['REASON'] = 'no interrupted installation';

            return;
        }
        $this->removeLeftovers();
        $this->install();
    }

    // ----------------------------------------------------------------------------------------- paths and lock

    private function checkPaths(): void
    {
        if (PHP_VERSION_ID < 80200) {
            throw new UsageError('PHP 8.2 or newer is required');
        }
        foreach (['flock', 'fsync', 'proc_open', 'hash_file'] as $fn) {
            if (! function_exists($fn)) {
                throw new UsageError("required PHP function not available: {$fn}");
            }
        }
        $this->app = $this->realDir($this->opt['app'], '--app');
        $this->web = $this->realDir($this->opt['web'], '--web');
        if (is_link(rtrim($this->opt['packages-root'], '/\\'))) {
            throw new UsageError('--packages-root must not be a symbolic link');
        }
        $this->root = $this->realDir($this->opt['packages-root'], '--packages-root');
        foreach (['application' => $this->app, 'web root' => $this->web] as $label => $protected) {
            if ($this->within($this->root, $protected) || $this->within($protected, $this->root)) {
                throw new UsageError("--packages-root must be outside the {$label} (and must not contain it)");
            }
        }
        foreach (['state', 'stage', 'previous'] as $sub) {
            $path = $this->root.'/'.$sub;
            if (@lstat($path) === false && ! @mkdir($path, 0700)) {
                throw new UsageError("cannot create {$path}");
            }
            if (is_link($path) || ! is_dir($path)) {
                throw new UsageError("{$path} must be a real directory");
            }
        }
        $devices = [];
        foreach ([$this->root, $this->web, $this->app.'/public'] as $path) {
            $st = @stat($path);
            if ($st === false) {
                throw new UsageError("cannot stat {$path}");
            }
            $devices[$st['dev']] = true;
        }
        if (count($devices) !== 1) {
            throw new UsageError('packages root, web root and application public directory must be on one file system');
        }
        $this->targets = ['web' => $this->web.'/build', 'app' => $this->app.'/public/build'];
    }

    private function acquireLock(): void
    {
        $handle = @fopen($this->root.'/state/deploy.lock', 'c');
        if ($handle === false) {
            throw new UsageError('cannot open the deployment lock');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            $this->out['ACTION'] = 'locked';
            throw new InstallFailure('another deployment is running (lock held)');
        }
        $this->lock = $handle;   // released when this process ends, also if it is killed
    }

    public function releaseLock(): void
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    // --------------------------------------------------------------------------------------------- install

    private function install(): void
    {
        foreach ($this->targets as $name => $path) {
            if (is_link($path) || ! is_dir($path)) {
                throw new InstallFailure("current {$name} build is missing or not a real directory: {$path}");
            }
        }
        $packageDir = $this->checkPackageDir($this->opt['package-dir']);
        $packageId = basename($packageDir);
        $this->out['PACKAGE_ID'] = $packageId;

        $old = [];
        foreach ($this->targets as $name => $path) {
            $old[$name] = $this->digest($path);
            if ($old[$name] === 'invalid') {
                throw new InstallFailure("current {$name} build contains links or special files: {$path}");
            }
        }

        $txn = gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
        $stage = $this->root.'/stage/'.$txn;
        if (! @mkdir($stage, 0700)) {
            throw new InstallFailure('cannot create the stage directory');
        }
        $journal = null;
        try {
            $package = $this->runValidator($packageDir, $packageId, $stage.'/v');
            $new = $this->expectedDigest($package);
            if ($this->digest($stage.'/v/build') !== $new) {
                throw new InstallFailure('staged tree does not match PACKAGE.json');
            }
            if ($old['web'] === $new && $old['app'] === $new) {
                $this->out['ACTION'] = 'already-installed';
                $this->out['RESULT'] = 'PASS';
                $this->out['REASON'] = 'the installed build already equals this package; nothing changed';

                return;
            }

            // two target copies with fixed modes, verified before anything is swapped
            $this->renameStrict($stage.'/v/build', $stage.'/app');
            $this->copyTree($stage.'/app', $stage.'/web');
            foreach (['web', 'app'] as $name) {
                $this->setModes($stage.'/'.$name);
                if ($this->digest($stage.'/'.$name) !== $new) {
                    throw new InstallFailure("staged {$name} copy does not match PACKAGE.json");
                }
                $this->checkModes($stage.'/'.$name);
            }

            $previous = $this->root.'/previous/'.$txn;
            if (! @mkdir($previous, 0700)) {
                throw new InstallFailure('cannot create the previous-build directory');
            }
            $journal = [
                'format' => 'hirfah-vite-install-journal', 'version' => 1, 'txn' => $txn, 'phase' => 'swapping',
                'package_id' => $packageId, 'commit' => $this->out['SOURCE_COMMIT'], 'new_digest' => $new,
                'targets' => [],
            ];
            foreach ($this->targets as $name => $path) {
                $journal['targets'][] = ['name' => $name, 'path' => $path, 'previous' => $previous.'/'.$name,
                    'staged' => $stage.'/'.$name, 'old_digest' => $old[$name], 'skip' => $old[$name] === $new];
            }
            $this->writeJournal($journal);

            try {
                foreach ($journal['targets'] as $t) {
                    if ($t['skip']) {
                        continue;
                    }
                    $this->renameStrict($t['path'], $t['previous']);
                    $this->renameStrict($t['staged'], $t['path']);
                }
                $this->verifyInstalled($new);
                $journal['phase'] = 'committed';
                $this->writeJournal($journal);   // atomic: until this rename lands, recovery rolls back
            } catch (Throwable $e) {
                $journal['phase'] = 'swapping';
                $this->rollBack($journal, $e);   // always throws
            }
            $this->finishCommitted($journal);    // previous builds are pruned only after the commit
            $this->out['ACTION'] = 'installed';
            $this->out['PREVIOUS'] = $previous;
            $this->out['RESULT'] = 'PASS';
            $this->out['REASON'] = sprintf('installed %s into both build directories; previous builds in %s', $packageId, $previous);
        } finally {
            if ($journal === null || ! file_exists($this->journalPath())) {
                $this->removeOwn($stage, $this->root.'/stage');   // never while a journal still needs it
            }
        }
    }

    private function checkPackageDir(string $input): string
    {
        $dir = $this->realDir($input, '--package-dir');
        if (dirname($dir) !== $this->root.'/incoming' || ! preg_match(PACKAGE_ID_PATTERN, basename($dir))) {
            throw new UsageError('--package-dir must be <packages-root>/incoming/<package_id>');
        }

        return $dir;
    }

    /** @return array<string, mixed> the staged PACKAGE.json */
    private function runValidator(string $packageDir, string $packageId, string $stageDir): array
    {
        $cmd = [PHP_BINARY, __DIR__.'/vite-package-validate.php', '--mode=install', '--package-dir', $packageDir,
            '--app', $this->app, '--web', $this->web, '--work-root', $this->root.'/work',
            '--tar', $this->opt['tar'], '--git', $this->opt['git'], '--stage-dir', $stageDir];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            throw new UsageError('cannot run the validator');
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $v = [];
        foreach (preg_split('/\r?\n/', trim($stdout)) as $line) {
            fwrite(STDERR, 'validator: '.$line."\n");
            if (preg_match('/\A([A-Z_]+)=(.*)\z/', $line, $m) && ! isset($v[$m[1]])) {
                $v[$m[1]] = $m[2];
            }
        }
        if (trim($stderr) !== '') {
            fwrite(STDERR, 'validator stderr: '.$this->oneLine($stderr)."\n");
        }
        $this->out['SOURCE_COMMIT'] = $v['SOURCE_COMMIT'] ?? '';
        $approved = $code === 0 && ($v['RESULT'] ?? '') === 'PASS' && ($v['INSTALL_APPROVED'] ?? '') === 'true'
            && ($v['SOURCE_STATUS'] ?? '') === 'verified-head' && ($v['PACKAGE_ID'] ?? '') === $packageId
            && $this->norm($v['STAGE_DIR'] ?? '') === $stageDir && preg_match('/\A[0-9a-f]{40}\z/', $this->out['SOURCE_COMMIT']);
        if (! $approved) {
            throw new InstallFailure('package not approved for installation (validator exit '.$code.'): '.($v['REASON'] ?? 'no reason'));
        }
        $file = $stageDir.'/PACKAGE.json';
        if (is_link($file) || ! is_file($file) || filesize($file) > LIMIT_PACKAGE_JSON_BYTES) {
            throw new InstallFailure('staged PACKAGE.json missing or invalid');
        }
        $package = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($package) || ($package['package_id'] ?? null) !== $packageId || ! is_array($package['files'] ?? null)
            || ($package['source']['commit'] ?? null) !== $this->out['SOURCE_COMMIT']) {
            throw new InstallFailure('staged PACKAGE.json does not match the validated package');
        }

        return $package;
    }

    // ------------------------------------------------------------------------------- verify after the swap

    private function verifyInstalled(string $new): void
    {
        foreach ($this->targets as $name => $path) {
            if ($this->digest($path) !== $new) {
                throw new InstallFailure("installed {$name} build does not match the package");
            }
            $this->checkModes($path);
        }
        $manifest = json_decode((string) @file_get_contents($this->targets['app'].'/manifest.json'), true);
        if (! is_array($manifest) || $manifest === []) {
            throw new InstallFailure('installed manifest is missing or invalid');
        }
        preg_match_all(VITE_INPUT_PATTERN, (string) @file_get_contents($this->app.'/vite.config.js'), $m);
        $missing = array_diff(array_unique($m[1]), array_keys($manifest));
        if ($m[1] === [] || $missing !== []) {
            throw new InstallFailure('installed manifest lacks vite.config.js inputs: '.implode(', ', $missing));
        }
        foreach ($manifest as $entry) {
            $refs = is_array($entry) ? array_merge([$entry['file'] ?? ''], (array) ($entry['css'] ?? []), (array) ($entry['assets'] ?? [])) : [''];
            foreach ($refs as $ref) {
                foreach ($this->targets as $name => $path) {
                    if (! is_string($ref) || $ref === '' || str_contains($ref, '..') || ! is_file($path.'/'.$ref)) {
                        throw new InstallFailure("installed {$name} build lacks a manifest file: ".(is_string($ref) ? $ref : '?'));
                    }
                }
            }
        }
        foreach ([$this->app.'/public/hot', $this->web.'/hot'] as $hot) {
            if (@lstat($hot) !== false) {
                throw new InstallFailure('Vite dev-server marker present: '.$hot);
            }
        }
    }

    // ------------------------------------------------------------------------------ rollback and recovery

    /** @param array<string, mixed> $journal */
    private function rollBack(array $journal, Throwable $cause): never
    {
        try {
            $this->restore($journal);
        } catch (AmbiguousState $e) {
            $this->out['ACTION'] = 'manual-recovery-required';
            throw new InstallFailure('installation failed ('.$cause->getMessage().') and could not be rolled back safely: '.$e->getMessage());
        }
        $this->out['ACTION'] = 'rolled-back';
        throw new InstallFailure('installation failed and was rolled back; previous builds restored: '.$cause->getMessage());
    }

    private function recoverJournal(): never
    {
        try {
            $journal = $this->readJournal();
            if ($journal['phase'] === 'swapping') {
                $this->restore($journal);
                $what = 'rolled back to the previous builds';
            } else {
                foreach ($journal['targets'] as $t) {
                    if ($this->digest($t['path']) !== ($t['skip'] ? $t['old_digest'] : $journal['new_digest'])) {
                        throw new AmbiguousState("committed {$t['name']} build does not match the journal");
                    }
                }
                $this->finishCommitted($journal);
                $what = 'its installation had completed; cleanup finished';
            }
        } catch (AmbiguousState $e) {
            $this->out['ACTION'] = 'manual-recovery-required';
            throw new InstallFailure('an interrupted installation needs manual recovery (nothing was deleted): '.$e->getMessage());
        }
        $this->out['ACTION'] = 'recovered';
        $this->out['PACKAGE_ID'] = $journal['package_id'];
        $this->out['SOURCE_COMMIT'] = $journal['commit'];
        throw new InstallFailure("an interrupted installation ({$journal['txn']}) was found and {$what}; run the deployment again");
    }

    /**
     * Brings every target back to its recorded old build, using the actual state of each path. Only renames;
     * never deletes a build. Throws AmbiguousState (leaving everything as found) for any unexplained state.
     *
     * @param  array<string, mixed>  $journal
     */
    private function restore(array $journal): void
    {
        $new = $journal['new_digest'];
        foreach (array_reverse($journal['targets']) as $t) {
            $current = $this->digest($t['path']);
            if ($t['skip']) {
                if ($current !== $t['old_digest']) {
                    throw new AmbiguousState("{$t['name']} build changed although it was not replaced");
                }

                continue;
            }
            $previous = $this->digest($t['previous']);
            $staged = @lstat($t['staged']) !== false;
            if ($current === $t['old_digest'] && $previous === null) {
                continue;                                                    // not swapped yet
            }
            if ($current === null && $previous === $t['old_digest']) {
                $this->renameStrict($t['previous'], $t['path']);             // interrupted between the two renames

                continue;
            }
            if ($current === $new && $previous === $t['old_digest'] && ! $staged) {
                $this->renameStrict($t['path'], $t['staged']);               // fully swapped: move the new build back
                $this->renameStrict($t['previous'], $t['path']);

                continue;
            }
            throw new AmbiguousState(sprintf('%s: unexpected state (build %s, previous %s, staged copy %s)', $t['name'],
                $this->label($current, $t['old_digest'], $new), $this->label($previous, $t['old_digest'], $new), $staged ? 'present' : 'absent'));
        }
        foreach ($journal['targets'] as $t) {
            if ($this->digest($t['path']) !== $t['old_digest']) {
                throw new AmbiguousState("{$t['name']} build is not the recorded old build after the rollback");
            }
        }
        $this->removeOwn($this->root.'/stage/'.$journal['txn'], $this->root.'/stage');
        @rmdir($this->root.'/previous/'.$journal['txn']);   // only if empty: every old build is back in place
        $this->deleteJournal();
    }

    /** @param array<string, mixed> $journal */
    private function finishCommitted(array $journal): void
    {
        $this->removeOwn($this->root.'/stage/'.$journal['txn'], $this->root.'/stage');
        $this->prunePrevious();
        $this->deleteJournal();
    }

    private function prunePrevious(): void
    {
        $dir = $this->root.'/previous';
        $kept = [];
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            if (preg_match(TXN_PATTERN, $name) && ! is_link($dir.'/'.$name) && is_dir($dir.'/'.$name) && ! @rmdir($dir.'/'.$name)) {
                $kept[] = $name;   // rmdir succeeds only for an empty (useless) directory
            }
        }
        rsort($kept, SORT_STRING);   // names start with the UTC time: newest first
        foreach (array_slice($kept, KEEP_PREVIOUS) as $name) {
            $this->removeOwn($dir.'/'.$name, $dir);
        }
    }

    private function removeLeftovers(): void
    {
        // no journal: nothing in stage/ belongs to an unfinished swap (a crash before the journal was written)
        foreach (array_diff(scandir($this->root.'/stage') ?: [], ['.', '..']) as $name) {
            if (preg_match(TXN_PATTERN, $name)) {
                $this->removeOwn($this->root.'/stage/'.$name, $this->root.'/stage');
            }
        }
    }

    // ------------------------------------------------------------------------------------------ journal

    private function journalPath(): string
    {
        return $this->root.'/state/install.journal';
    }

    /** @param array<string, mixed> $journal */
    private function writeJournal(array $journal): void
    {
        $tmp = $this->journalPath().'.tmp';
        $fh = @fopen($tmp, 'w');
        if ($fh === false) {
            throw new InstallFailure('cannot write the install journal');
        }
        try {
            $data = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            if (fwrite($fh, $data) !== strlen($data) || ! fflush($fh) || ! fsync($fh)) {
                throw new InstallFailure('cannot write the install journal');
            }
        } finally {
            fclose($fh);
        }
        if (! @rename($tmp, $this->journalPath())) {
            throw new InstallFailure('cannot write the install journal');
        }
    }

    private function deleteJournal(): void
    {
        if (! @unlink($this->journalPath())) {
            throw new AmbiguousState('cannot remove the install journal');
        }
    }

    /** @return array<string, mixed> a journal whose paths are exactly the ones this run would use */
    private function readJournal(): array
    {
        $data = @file_get_contents($this->journalPath(), false, null, 0, 65536);
        $j = is_string($data) ? json_decode($data, true) : null;
        $ok = is_array($j) && array_keys($j) === ['format', 'version', 'txn', 'phase', 'package_id', 'commit', 'new_digest', 'targets']
            && $j['format'] === 'hirfah-vite-install-journal' && $j['version'] === 1
            && is_string($j['txn']) && preg_match(TXN_PATTERN, $j['txn']) && in_array($j['phase'], ['swapping', 'committed'], true)
            && is_string($j['package_id']) && preg_match(PACKAGE_ID_PATTERN, $j['package_id'])
            && is_string($j['commit']) && preg_match('/\A[0-9a-f]{40}\z/', $j['commit'])
            && is_string($j['new_digest']) && preg_match('/\A[0-9a-f]{64}\z/', $j['new_digest'])
            && is_array($j['targets']) && count($j['targets']) === 2;
        if ($ok) {
            foreach (array_values($j['targets']) as $i => $t) {
                $name = ['web', 'app'][$i];
                $ok = $ok && is_array($t) && array_keys($t) === ['name', 'path', 'previous', 'staged', 'old_digest', 'skip']
                    && $t['name'] === $name && $t['path'] === $this->targets[$name]
                    && $t['previous'] === $this->root.'/previous/'.$j['txn'].'/'.$name
                    && $t['staged'] === $this->root.'/stage/'.$j['txn'].'/'.$name
                    && is_string($t['old_digest']) && preg_match('/\A[0-9a-f]{64}\z/', $t['old_digest']) && is_bool($t['skip']);
            }
        }
        if (! $ok) {
            throw new AmbiguousState('the install journal is not valid for these paths: '.$this->journalPath());
        }

        return $j;
    }

    // ------------------------------------------------------------------------------------- tree helpers

    /**
     * Content digest of a build tree: null if the path does not exist, 'invalid' if it is not a real directory or
     * contains a link or special file (never followed), else SHA-256 over the sorted directory and file entries.
     */
    private function digest(string $path): ?string
    {
        $st = @lstat($path);
        if ($st === false) {
            return null;
        }
        if (is_link($path) || ($st['mode'] & 0170000) !== 0040000) {
            return 'invalid';
        }
        $lines = [];

        return $this->collect($path, '', $lines) ? $this->hashLines($lines) : 'invalid';
    }

    /** @param list<string> $lines */
    private function collect(string $abs, string $rel, array &$lines): bool
    {
        foreach (array_diff(scandir($abs) ?: [], ['.', '..']) as $name) {
            $path = $abs.'/'.$name;
            $r = $rel === '' ? $name : $rel.'/'.$name;
            $st = @lstat($path);
            if ($st === false || is_link($path)) {
                return false;
            }
            $kind = $st['mode'] & 0170000;
            if ($kind === 0040000) {
                $lines[] = "d\0".$r;
                if (! $this->collect($path, $r, $lines)) {
                    return false;
                }
            } elseif ($kind === 0100000) {
                $lines[] = "f\0".$r."\0".$st['size']."\0".hash_file('sha256', $path);
            } else {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $package */
    private function expectedDigest(array $package): string
    {
        $lines = [];
        $dirs = [];
        foreach ($package['files'] as $f) {
            if (! is_array($f) || ! is_string($f['path'] ?? null) || ! str_starts_with($f['path'], 'build/')) {
                throw new InstallFailure('staged PACKAGE.json has an invalid file entry');
            }
            $rel = substr($f['path'], 6);
            $lines[] = "f\0".$rel."\0".$f['size']."\0".$f['sha256'];
            for ($d = dirname($rel); $d !== '.'; $d = dirname($d)) {
                $dirs[$d] = true;
            }
        }
        foreach (array_keys($dirs) as $d) {
            $lines[] = "d\0".$d;
        }

        return $this->hashLines($lines);
    }

    /** @param list<string> $lines */
    private function hashLines(array $lines): string
    {
        sort($lines, SORT_STRING);

        return hash('sha256', implode("\n", $lines));
    }

    private function copyTree(string $from, string $to): void
    {
        if (! @mkdir($to, 0700)) {
            throw new InstallFailure('cannot create '.$to);
        }
        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
            $src = $from.'/'.$name;
            if (is_link($src)) {
                throw new InstallFailure('link in the staged tree');
            }
            if (is_dir($src)) {
                $this->copyTree($src, $to.'/'.$name);
            } elseif (! @copy($src, $to.'/'.$name)) {
                throw new InstallFailure('cannot copy '.$name);
            }
        }
    }

    private function setModes(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir.'/'.$name;
            if (is_dir($path) && ! is_link($path)) {
                $this->setModes($path);
            } elseif (! @chmod($path, 0644)) {
                throw new InstallFailure('cannot set the mode of '.$name);
            }
        }
        if (! @chmod($dir, 0755)) {
            throw new InstallFailure('cannot set the mode of a staged directory');
        }
    }

    private function checkModes(string $dir): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;   // POSIX modes are not meaningful here
        }
        clearstatcache();
        if ((fileperms($dir) & 0777) !== 0755) {
            throw new InstallFailure('directory mode is not 0755: '.$dir);
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir.'/'.$name;
            if (is_dir($path)) {
                $this->checkModes($path);
            } elseif ((fileperms($path) & 0777) !== 0644) {
                throw new InstallFailure('file mode is not 0644: '.$path);
            }
        }
    }

    /** rename() that refuses to land on any existing path (rename could replace an empty directory). */
    private function renameStrict(string $from, string $to): void
    {
        if (@lstat($to) !== false) {
            throw new AmbiguousState('rename target already exists: '.$to);
        }
        if (! @rename($from, $to)) {
            throw new InstallFailure('cannot rename '.$from.' to '.$to);
        }
    }

    /** Removes a transaction directory of this installer (name pattern checked) without following links. */
    private function removeOwn(string $path, string $parent): void
    {
        if (dirname($path) !== $parent || ! preg_match(TXN_PATTERN, basename($path))) {
            throw new UsageError('refusing to remove an unexpected path: '.$path);
        }
        $this->remove($path);
    }

    private function remove(string $path): void
    {
        $st = @lstat($path);
        if ($st === false) {
            return;
        }
        $kind = $st['mode'] & 0170000;
        if (! is_link($path) && $kind === 0040000) {
            @chmod($path, 0700);
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
                $this->remove($path.'/'.$name);
            }
            @rmdir($path);

            return;
        }
        if (! is_link($path) && $kind === 0100000) {
            @chmod($path, 0600);
        }
        if (! @unlink($path)) {
            @rmdir($path);   // a link or Windows junction is removed as one entry, never followed
        }
    }

    private function label(?string $digest, string $old, string $new): string
    {
        return match ($digest) {
            null => 'missing', 'invalid' => 'invalid', $old => 'old', $new => 'new', default => 'unknown',
        };
    }

    private function realDir(string $path, string $label): string
    {
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new UsageError("{$label} is not an existing directory: {$path}");
        }

        return $this->norm($real);
    }

    private function norm(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function within(string $child, string $parent): bool
    {
        $c = $this->norm($child).'/';
        $p = $this->norm($parent).'/';
        if (PHP_OS_FAMILY === 'Windows') {
            $c = strtolower($c);
            $p = strtolower($p);
        }

        return str_starts_with($c, $p);
    }

    private function oneLine(string $text): string
    {
        return substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 300);
    }
}

// ------------------------------------------------------------------------------------------------------- main

/** @return array<string, string> */
function parse_options(array $argv): array
{
    $opt = ['tar' => '/bin/tar', 'git' => 'git'];
    $args = array_slice($argv, 1);
    while ($args !== []) {
        $arg = array_shift($args);
        if (! preg_match('/\A--(mode|packages-root|package-dir|app|web|tar|git)(?:=(.*))?\z/s', $arg, $m)) {
            throw new UsageError('unknown argument: '.$arg);
        }
        $value = $m[2] ?? array_shift($args);
        if ($value === null || $value === '') {
            throw new UsageError("--{$m[1]} needs a value");
        }
        $opt[$m[1]] = $value;
    }
    if (! in_array($opt['mode'] ?? '', ['install', 'recover'], true)) {
        throw new UsageError('--mode must be install or recover');
    }
    foreach (array_merge(['packages-root', 'app', 'web'], $opt['mode'] === 'install' ? ['package-dir'] : []) as $required) {
        if (! isset($opt[$required])) {
            throw new UsageError("--{$required} is required");
        }
    }

    return $opt;
}

function emit(array $out): void
{
    foreach (OUTPUT_KEYS as $key) {
        fwrite(STDOUT, $key.'='.str_replace(["\r", "\n"], ' ', (string) ($out[$key] ?? ''))."\n");
    }
}

if (PHP_SAPI !== 'cli') {
    exit(2);
}
set_error_handler(static function (int $severity, string $message): bool {
    if (! (error_reporting() & $severity)) {
        return false;   // suppressed with @: the caller checks the return value
    }
    throw new ErrorException($message, 0, $severity);
});
$exit = 2;
$installer = null;
try {
    $installer = new Installer(parse_options($argv));
    $installer->run();
    $exit = 0;
} catch (InstallFailure $e) {
    $exit = 1;
    $reason = $e->getMessage();
} catch (AmbiguousState $e) {
    $exit = 1;
    $reason = 'manual recovery required (nothing was deleted): '.$e->getMessage();
    if ($installer !== null) {
        $installer->out['ACTION'] = 'manual-recovery-required';
    }
} catch (Throwable $e) {
    $exit = 2;
    $reason = ($e instanceof UsageError ? '' : 'internal error: ').$e->getMessage();
} finally {
    $installer?->releaseLock();
}
$out = $installer?->out ?? array_fill_keys(OUTPUT_KEYS, '');
if ($exit !== 0) {
    $out['RESULT'] = 'FAIL';
    $out['REASON'] = $reason ?? 'error';
}
emit($out);
exit($exit);
