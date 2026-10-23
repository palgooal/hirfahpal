<?php

declare(strict_types=1);

/*
 * HIRFAH - Vite package validator (deployment phase 2.2.2).
 *
 * Validates ONE package directory produced by deploy/make-vite-package.sh:
 *     <package-dir> = .../<package_id>/
 *         <package_id>.tar.gz
 *         <package_id>.tar.gz.sha256
 * before anything may be installed. It installs nothing: no copy to public_html or public/build, no change to the
 * application, the web root, .env, the database or caches; no npm, Composer, migrations or network. Standalone PHP
 * CLI: no Laravel, no vendor/, no .env.
 *
 * Layers (no single layer is trusted alone):
 *   1. Private copy: the archive is read once from the package directory into a fresh 0700 work directory while
 *      its SHA-256 is computed on the very bytes written; gzip is decoded in PHP with a hard size cap, one member
 *      only, no trailing data. The incoming directory is never read again.
 *   2. Limited tar header scanner (authoritative): fixed ustar header fields only; regular files and directories
 *      only; every other type and every tar extension (pax, GNU long names, sparse, ...) is rejected; strict paths,
 *      no duplicates, limits enforced before any extraction.
 *   3. GNU tar, run without a shell and with a clean environment (no TAR_OPTIONS): --list --verbose must agree with
 *      the scanner exactly; then a hardened --extract of the explicit, already validated file list.
 *   4. Post-extraction check with lstat (no links, no special files, exact file set and sizes) and SHA-256 of every
 *      file; then PACKAGE.json and the Vite manifest are verified against the computed values (never trusted).
 * Source check (read-only git): SOURCE_VERIFIED only if the commit exists locally, its tree and its vite.config.js
 * inputs match the package, and - in install mode - the commit is HEAD.
 *
 * Usage:
 *   php vite-package-validate.php --package-dir <dir> --mode install|prepare --app <app root> --web <web root>
 *       --work-root <dir> [--tar /bin/tar] [--git git] [--stage-dir <new dir>]
 * Output: fixed key=value lines. Exit codes: 0 PASS, 1 FAIL (validation), 2 usage, environment or internal error.
 * install: PASS only if PACKAGE_VALID=true and SOURCE_VERIFIED=true (then INSTALL_APPROVED=true).
 * prepare: PASS if PACKAGE_VALID=true; SOURCE_VERIFIED may be false; INSTALL_APPROVED is always false.
 * --stage-dir (install mode only): a directory that must not exist yet, outside the application and the web root,
 * on the same file system as the work root. Only on PASS, the validator creates it (0700) and moves the very tree
 * it verified into it (<stage-dir>/build and <stage-dir>/PACKAGE.json, by rename; nothing is extracted again) and
 * reports it as STAGE_DIR. On failure it is removed again and STAGE_DIR is empty.
 * The work directory is always removed, after success and failure.
 */

const LIMIT_ARCHIVE_BYTES = 20 * 1024 * 1024;   // compressed archive
const LIMIT_TOTAL_BYTES = 50 * 1024 * 1024;     // sum of extracted file sizes
const LIMIT_FILE_BYTES = 10 * 1024 * 1024;      // any single file
const LIMIT_ELEMENTS = 5000;                    // tar members (files + directories)
const LIMIT_PACKAGE_JSON_BYTES = 1024 * 1024;
const LIMIT_TAR_BYTES = 60 * 1024 * 1024;       // decompressed tar stream (contents + headers + padding)
const LIMIT_SHA_FILE_BYTES = 256;
const PACKAGE_ID_PATTERN = '/^hirfah-vite-([0-9a-f]{12})-[0-9]{8}T[0-9]{6}Z$/';
const SAFE_SEGMENT = '[A-Za-z0-9._@-]+';
const ALLOWED_EXTENSIONS = '/\.(js|mjs|css|json|map|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico)$/i';
const VITE_INPUT_PATTERN = '/[\'"](resources\/[^\'"]+\.(?:js|css))[\'"]/';
const OUTPUT_KEYS = ['MODE', 'PACKAGE_ID', 'SOURCE_COMMIT', 'PACKAGE_VALID', 'SOURCE_VERIFIED', 'SOURCE_STATUS', 'INSTALL_APPROVED', 'RESULT', 'REASON', 'STAGE_DIR'];

final class ValidationFailure extends RuntimeException {}
final class UsageError extends RuntimeException {}

final class Validator
{
    /** @var array<string, string> */
    public array $out = [];

    private ?string $work = null;

    private string $workRoot = '';

    private string $tarSha = '';

    private ?string $stage = null;      // checked --stage-dir path (install mode only)

    private bool $stageCreated = false;

    private bool $stageDone = false;

    /** @param array<string, string> $opt */
    public function __construct(private array $opt)
    {
        foreach (OUTPUT_KEYS as $key) {
            $this->out[$key] = '';
        }
        $this->out['MODE'] = $opt['mode'];
        $this->out['PACKAGE_VALID'] = 'false';
        $this->out['SOURCE_VERIFIED'] = 'false';
        $this->out['SOURCE_STATUS'] = 'not-checked';
        $this->out['INSTALL_APPROVED'] = 'false';
        $this->out['RESULT'] = 'FAIL';
    }

    public function run(): void
    {
        [$app, $web, $workRoot, $packageDir] = $this->checkEnvironmentAndPaths();
        $this->workRoot = $workRoot;
        [$id, $archiveName, $expectedSha] = $this->checkPackageDirectory($packageDir);
        $this->out['PACKAGE_ID'] = $id;

        $this->createWorkDirectory($id);
        $this->copyArchive($packageDir.'/'.$archiveName, $expectedSha);
        $this->decompress();
        $members = $this->scanTarHeaders();
        $this->crossCheckWithTar($members);
        $this->extract($members);
        $hashes = $this->checkExtractedTree($members);
        $package = $this->checkPackageJson($id, $hashes);
        $manifest = $this->checkManifest($hashes, $package);
        $this->out['PACKAGE_VALID'] = 'true';
        $this->out['SOURCE_COMMIT'] = $package['source']['commit'];

        $this->verifySource($app, $package, $manifest);

        if ($this->opt['mode'] === 'install') {
            if ($this->out['SOURCE_VERIFIED'] !== 'true') {
                throw new ValidationFailure('install mode requires a verified source ('.$this->out['SOURCE_STATUS'].')');
            }
            if ($this->stage !== null) {
                $this->stageVerifiedTree();
            }
            $this->out['INSTALL_APPROVED'] = 'true';
            $this->out['REASON'] = sprintf('package valid and source verified at HEAD; %d files, %d bytes', count($hashes), array_sum(array_column($hashes, 'size')));
        } else {
            $this->out['REASON'] = sprintf('package valid; source %s; not an install approval; %d files, %d bytes',
                $this->out['SOURCE_VERIFIED'] === 'true' ? 'verified' : 'not verifiable ('.$this->out['SOURCE_STATUS'].')',
                count($hashes), array_sum(array_column($hashes, 'size')));
        }
        $this->out['RESULT'] = 'PASS';
    }

    // ---------------------------------------------------------------------------------------------------- paths

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function checkEnvironmentAndPaths(): array
    {
        if (PHP_VERSION_ID < 80200) {
            throw new UsageError('PHP 8.2 or newer is required');
        }
        foreach (['inflate_init', 'inflate_add', 'inflate_get_status', 'inflate_get_read_len', 'proc_open', 'hash_init'] as $fn) {
            if (! function_exists($fn)) {
                throw new UsageError("required PHP function not available: {$fn}");
            }
        }
        if (! is_file($this->opt['tar']) || ! is_executable($this->opt['tar'])) {
            throw new UsageError('GNU tar not found or not executable: '.$this->opt['tar']);
        }
        $app = $this->realDir($this->opt['app'], '--app');
        $web = $this->realDir($this->opt['web'], '--web');
        $workRoot = $this->realDir($this->opt['work-root'], '--work-root');
        if (is_link(rtrim($this->opt['work-root'], '/\\'))) {
            throw new UsageError('--work-root must not be a symbolic link');
        }
        if (! is_writable($workRoot)) {
            throw new UsageError('--work-root is not writable');
        }
        if (is_link(rtrim($this->opt['package-dir'], '/\\'))) {
            throw new ValidationFailure('package directory is a symbolic link');
        }
        $packageDir = $this->realDir($this->opt['package-dir'], '--package-dir');
        foreach (['--work-root' => $workRoot, '--package-dir' => $packageDir] as $label => $path) {
            foreach (['application' => $app, 'web root' => $web] as $name => $protected) {
                if ($this->within($path, $protected) || $this->within($protected, $path)) {
                    throw new UsageError("{$label} must be outside the {$name} (and must not contain it)");
                }
            }
        }
        if (isset($this->opt['stage-dir'])) {
            $this->stage = $this->checkStageDir($this->opt['stage-dir'], $app, $web, $workRoot);
        }

        return [$app, $web, $workRoot, $packageDir];
    }

    private function checkStageDir(string $stageDir, string $app, string $web, string $workRoot): string
    {
        $stageDir = rtrim($stageDir, '/\\');
        $name = basename(str_replace('\\', '/', $stageDir));
        if (! preg_match('/\A'.SAFE_SEGMENT.'\z/', $name) || $name === '.' || $name === '..') {
            throw new UsageError('--stage-dir has an unsafe name');
        }
        if (@lstat($stageDir) !== false) {
            throw new UsageError('--stage-dir must not exist yet');
        }
        $parentInput = dirname($stageDir);
        if (is_link(rtrim($parentInput, '/\\'))) {
            throw new UsageError('--stage-dir parent must not be a symbolic link');
        }
        $parent = $this->realDir($parentInput, '--stage-dir parent');
        if (! is_writable($parent)) {
            throw new UsageError('--stage-dir parent is not writable');
        }
        foreach (['application' => $app, 'web root' => $web] as $label => $protected) {
            if ($this->within($parent, $protected) || $this->within($protected, $parent)) {
                throw new UsageError("--stage-dir must be outside the {$label} (and must not contain it)");
            }
        }
        if (@stat($parent)['dev'] !== @stat($workRoot)['dev']) {
            throw new UsageError('--stage-dir must be on the same file system as --work-root');
        }

        return $parent.'/'.$name;
    }

    /** Moves the verified tree (extract/build, extract/PACKAGE.json) into the new stage directory by rename. */
    private function stageVerifiedTree(): void
    {
        if (! @mkdir($this->stage, 0700)) {
            throw new ValidationFailure('cannot create the stage directory (it must not exist)');
        }
        $this->stageCreated = true;
        @chmod($this->stage, 0700);
        foreach (['build', 'PACKAGE.json'] as $entry) {
            if (! @rename($this->work.'/extract/'.$entry, $this->stage.'/'.$entry)) {
                throw new ValidationFailure('cannot move the verified '.$entry.' into the stage directory');
            }
        }
        $this->stageDone = true;
        $this->out['STAGE_DIR'] = $this->stage;
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

    // --------------------------------------------------------------------------------------------- package dir

    /** @return array{0: string, 1: string, 2: string} */
    private function checkPackageDirectory(string $dir): array
    {
        $id = basename($dir);
        if (! preg_match(PACKAGE_ID_PATTERN, $id)) {
            throw new ValidationFailure('package directory name is not a package id: '.$id);
        }
        $archive = $id.'.tar.gz';
        $shaName = $archive.'.sha256';
        $entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        sort($entries);
        if ($entries !== [$archive, $shaName]) {
            throw new ValidationFailure('package directory must hold exactly '.$archive.' and '.$shaName.' (found: '.implode(', ', $entries).')');
        }
        foreach ([$archive => LIMIT_ARCHIVE_BYTES, $shaName => LIMIT_SHA_FILE_BYTES] as $name => $limit) {
            $path = $dir.'/'.$name;
            $st = @lstat($path);
            if ($st === false || is_link($path) || ! is_file($path)) {
                throw new ValidationFailure("not a regular file: {$name}");
            }
            if ($st['nlink'] !== 1) {
                throw new ValidationFailure("{$name} has {$st['nlink']} hard links (expected 1)");
            }
            if ($st['size'] > $limit) {
                throw new ValidationFailure("{$name} is larger than {$limit} bytes");
            }
            if ($st['size'] === 0) {
                throw new ValidationFailure("{$name} is empty");
            }
        }
        $line = (string) file_get_contents($dir.'/'.$shaName, false, null, 0, LIMIT_SHA_FILE_BYTES + 1);
        if (! preg_match('/\A([0-9a-f]{64}) [ *]([^\r\n\/\\\\]+)\n?\z/', $line, $m) || $m[2] !== $archive) {
            throw new ValidationFailure('checksum file must be exactly one line "<sha256>  '.$archive.'"');
        }

        return [$id, $archive, $m[1]];
    }

    // ------------------------------------------------------------------------------------ private copy + gunzip

    private function createWorkDirectory(string $id): void
    {
        $path = $this->workRoot.'/'.$id.'.'.bin2hex(random_bytes(8));
        if (! @mkdir($path, 0700)) {
            throw new UsageError('cannot create the work directory');
        }
        $this->work = $path;
        @chmod($path, 0700);
    }

    private function copyArchive(string $source, string $expectedSha): void
    {
        $in = @fopen($source, 'rb');
        $out = @fopen($this->work.'/archive.tar.gz', 'xb');
        if ($in === false || $out === false) {
            throw new ValidationFailure('cannot open the archive for the private copy');
        }
        $ctx = hash_init('sha256');
        $total = 0;
        try {
            while (! feof($in)) {
                $chunk = fread($in, 1 << 20);
                if ($chunk === false) {
                    throw new ValidationFailure('cannot read the archive');
                }
                $total += strlen($chunk);
                if ($total > LIMIT_ARCHIVE_BYTES) {
                    throw new ValidationFailure('archive is larger than '.LIMIT_ARCHIVE_BYTES.' bytes');
                }
                hash_update($ctx, $chunk);
                if (fwrite($out, $chunk) !== strlen($chunk)) {
                    throw new ValidationFailure('cannot write the private copy');
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }
        if (! hash_equals($expectedSha, hash_final($ctx))) {
            throw new ValidationFailure('archive SHA-256 does not match the checksum file');
        }
        @chmod($this->work.'/archive.tar.gz', 0400);
    }

    private function decompress(): void
    {
        $in = fopen($this->work.'/archive.tar.gz', 'rb');
        $out = fopen($this->work.'/archive.tar', 'xb');
        $ctx = inflate_init(ZLIB_ENCODING_GZIP);
        if ($in === false || $out === false || $ctx === false) {
            throw new ValidationFailure('cannot start gzip decoding');
        }
        $sha = hash_init('sha256');
        $fed = 0;
        $produced = 0;
        $ended = false;
        try {
            $this->inflateInto($in, $out, $ctx, $sha, $fed, $produced, $ended);
        } finally {
            fclose($in);
            fclose($out);
        }
        if (! $ended) {
            throw new ValidationFailure('truncated gzip stream');
        }
        $this->tarSha = hash_final($sha);
        @chmod($this->work.'/archive.tar', 0400);
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function inflateInto($in, $out, InflateContext $ctx, HashContext $sha, int &$fed, int &$produced, bool &$ended): void
    {
        while (! feof($in)) {
            $chunk = (string) fread($in, 1 << 16);
            if ($chunk === '') {
                continue;
            }
            if ($ended) {
                throw new ValidationFailure('data after the end of the gzip stream (second member or trailing garbage)');
            }
            $fed += strlen($chunk);
            $data = @inflate_add($ctx, $chunk, ZLIB_SYNC_FLUSH);
            if ($data === false) {
                throw new ValidationFailure('corrupt gzip data');
            }
            $produced += strlen($data);
            if ($produced > LIMIT_TAR_BYTES) {
                throw new ValidationFailure('decompressed archive is larger than '.LIMIT_TAR_BYTES.' bytes');
            }
            hash_update($sha, $data);
            fwrite($out, $data);
            if (inflate_get_status($ctx) === ZLIB_STREAM_END) {
                $ended = true;
                if (inflate_get_read_len($ctx) !== $fed) {
                    throw new ValidationFailure('data after the end of the gzip stream (second member or trailing garbage)');
                }
            }
        }
    }

    // ------------------------------------------------------------------------------------------- tar scanner

    /** @return list<array{name: string, type: string, size: int}> */
    private function scanTarHeaders(): array
    {
        $path = $this->work.'/archive.tar';
        $fh = fopen($path, 'rb');
        try {
            $members = $this->scanOpenTar($fh, (int) filesize($path));
        } finally {
            fclose($fh);
        }

        $files = array_values(array_filter($members, fn ($m) => $m['type'] === 'file'));
        $fileNames = array_column($files, 'name');
        if (! in_array('PACKAGE.json', $fileNames, true) || ! in_array('build/manifest.json', $fileNames, true)) {
            throw new ValidationFailure('archive must contain PACKAGE.json and build/manifest.json');
        }
        foreach ($members as $m) {
            if ($m['type'] === 'dir' && ! array_filter($fileNames, fn ($f) => str_starts_with($f, $m['name'].'/'))) {
                throw new ValidationFailure('tar directory without files: '.$m['name']);
            }
        }

        return $members;
    }

    /**
     * @param  resource  $fh
     * @return list<array{name: string, type: string, size: int}>
     */
    private function scanOpenTar($fh, int $length): array
    {
        $members = [];
        $seen = [];
        $seenFolded = [];
        $total = 0;
        $offset = 0;
        $zero = str_repeat("\0", 512);
        while (true) {
            if ($offset + 512 > $length) {
                throw new ValidationFailure('truncated tar archive (no end-of-archive marker)');
            }
            fseek($fh, $offset);
            $h = (string) fread($fh, 512);
            if ($h === $zero) {
                if ($offset + 1024 > $length || (string) fread($fh, 512) !== $zero) {
                    throw new ValidationFailure('invalid tar end-of-archive marker');
                }
                while (! feof($fh)) {
                    $rest = (string) fread($fh, 1 << 16);
                    if (trim($rest, "\0") !== '') {
                        throw new ValidationFailure('data after the tar end-of-archive marker');
                    }
                }
                break;
            }
            $this->checkHeaderChecksum($h, $offset);
            $magic = substr($h, 257, 6);
            $version = substr($h, 263, 2);
            $posix = $magic === "ustar\0" && $version === '00';
            $gnu = $magic === 'ustar ' && $version === " \0";
            if (! $posix && ! $gnu) {
                throw new ValidationFailure("tar header at offset {$offset} is not ustar (pre-POSIX or foreign format)");
            }
            $type = $h[156];
            if (! in_array($type, ['0', "\0", '5'], true)) {
                $names = ['1' => 'hard link', '2' => 'symbolic link', '3' => 'character device', '4' => 'block device',
                    '6' => 'FIFO', '7' => 'contiguous file', 'x' => 'pax extended header', 'g' => 'pax global header',
                    'L' => 'GNU long name', 'K' => 'GNU long link name', 'S' => 'GNU sparse file', 'V' => 'GNU volume header',
                    'M' => 'GNU multi-volume', 'N' => 'GNU old long name', 'D' => 'GNU directory dump'];
                throw new ValidationFailure('tar member type not allowed: '.($names[$type] ?? 'type 0x'.bin2hex($type)).' at offset '.$offset);
            }
            if (trim(substr($h, 157, 100), "\0") !== '') {
                throw new ValidationFailure("tar member at offset {$offset} has a link name");
            }
            $name = $this->cString(substr($h, 0, 100));
            if ($posix) {
                $prefix = $this->cString(substr($h, 345, 155));
                if ($prefix !== '') {
                    $name = $prefix.'/'.$name;
                }
            }
            $size = $this->octal(substr($h, 124, 12), 'size', $offset);
            $isDir = $type === '5';
            if ($isDir !== str_ends_with($name, '/')) {
                throw new ValidationFailure("tar member type and name disagree: {$name}");
            }
            if ($isDir && $size !== 0) {
                throw new ValidationFailure("tar directory with a non-zero size: {$name}");
            }
            $name = rtrim($name, '/');
            $this->checkMemberPath($name, $isDir);
            $folded = strtolower($name);
            if (isset($seen[$name]) || isset($seenFolded[$folded])) {
                throw new ValidationFailure("duplicate tar member: {$name}");
            }
            $seen[$name] = true;
            $seenFolded[$folded] = true;
            if (count($members) + 1 > LIMIT_ELEMENTS) {
                throw new ValidationFailure('more than '.LIMIT_ELEMENTS.' tar members');
            }
            if (! $isDir) {
                if ($size > LIMIT_FILE_BYTES) {
                    throw new ValidationFailure('file larger than '.LIMIT_FILE_BYTES." bytes: {$name}");
                }
                if ($name === 'PACKAGE.json' && $size > LIMIT_PACKAGE_JSON_BYTES) {
                    throw new ValidationFailure('PACKAGE.json is larger than '.LIMIT_PACKAGE_JSON_BYTES.' bytes');
                }
                $total += $size;
                if ($total > LIMIT_TOTAL_BYTES) {
                    throw new ValidationFailure('extracted size would exceed '.LIMIT_TOTAL_BYTES.' bytes');
                }
            }
            $members[] = ['name' => $name, 'type' => $isDir ? 'dir' : 'file', 'size' => $size];
            $offset += 512 + (int) ceil($size / 512) * 512;
            if ($offset > $length) {
                throw new ValidationFailure("truncated tar member data: {$name}");
            }
        }

        return $members;
    }

    private function checkHeaderChecksum(string $h, int $offset): void
    {
        $stored = $this->octal(substr($h, 148, 8), 'checksum', $offset);
        $sum = 0;
        for ($i = 0; $i < 512; $i++) {
            $sum += ($i >= 148 && $i < 156) ? 32 : ord($h[$i]);
        }
        if ($sum !== $stored) {
            throw new ValidationFailure("bad tar header checksum at offset {$offset}");
        }
    }

    private function octal(string $field, string $what, int $offset): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80)) {
            throw new ValidationFailure("tar {$what} uses base-256 encoding at offset {$offset}");
        }
        if (! preg_match('/\A *([0-7]{1,12}) *[\0 ]*\z/', $field, $m)) {
            throw new ValidationFailure("invalid tar {$what} field at offset {$offset}");
        }

        return (int) octdec($m[1]);
    }

    private function cString(string $field): string
    {
        $nul = strpos($field, "\0");
        $value = $nul === false ? $field : substr($field, 0, $nul);
        if ($nul !== false && trim(substr($field, $nul), "\0") !== '') {
            throw new ValidationFailure('tar name field has data after its terminator');
        }

        return $value;
    }

    private function checkMemberPath(string $name, bool $isDir): void
    {
        if (! preg_match('#\A'.SAFE_SEGMENT.'(/'.SAFE_SEGMENT.')*\z#', $name)) {
            throw new ValidationFailure('unsafe tar member path: '.addcslashes($name, "\0..\37\\"));
        }
        foreach (explode('/', $name) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new ValidationFailure('unsafe tar member path: '.$name);
            }
        }
        $top = explode('/', $name)[0];
        if (! (($name === 'PACKAGE.json' && ! $isDir) || $top === 'build')) {
            throw new ValidationFailure('tar member outside PACKAGE.json and build/: '.$name);
        }
        if ($name === 'build' && ! $isDir) {
            throw new ValidationFailure('build must be a directory');
        }
    }

    // ---------------------------------------------------------------------------------------------- GNU tar

    /** @param list<array{name: string, type: string, size: int}> $members */
    private function crossCheckWithTar(array $members): void
    {
        [$code, $stdout, $stderr] = $this->runTar(['--list', '--verbose', '--numeric-owner', '--quoting-style=escape', '--file=archive.tar']);
        if ($code !== 0 || trim($stderr) !== '') {
            throw new ValidationFailure('tar --list failed: '.$this->oneLine($stderr));
        }
        $listed = [];
        foreach (preg_split('/\r?\n/', rtrim($stdout, "\r\n")) as $line) {
            if (! preg_match('#\A(\S)\S{9}\s+\S+\s+(\d+)\s+\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?\s(.+)\z#', $line, $m)) {
                throw new ValidationFailure('unexpected tar --list line: '.$this->oneLine($line));
            }
            $type = match ($m[1]) {
                '-' => 'file',
                'd' => 'dir',
                default => 'other:'.$m[1],
            };
            $listed[] = ['name' => rtrim($m[3], '/'), 'type' => $type, 'size' => (int) $m[2]];
        }
        if ($listed !== $members) {
            throw new ValidationFailure('archive parsers disagree (header scanner vs GNU tar listing)');
        }
    }

    /** @param list<array{name: string, type: string, size: int}> $members */
    private function extract(array $members): void
    {
        if (! hash_equals($this->tarSha, (string) hash_file('sha256', $this->work.'/archive.tar'))) {
            throw new ValidationFailure('archive changed between scan and extraction');
        }
        $files = array_column(array_filter($members, fn ($m) => $m['type'] === 'file'), 'name');
        file_put_contents($this->work.'/members.lst', implode("\0", $files)."\0");
        if (! @mkdir($this->work.'/extract', 0700)) {
            throw new ValidationFailure('cannot create the extraction directory');
        }
        [$code, , $stderr] = $this->runTar(['--extract', '--file=archive.tar', '--directory=extract',
            '--no-same-owner', '--no-same-permissions', '--keep-old-files',
            '--no-acls', '--no-selinux', '--no-xattrs',
            '--no-wildcards', '--verbatim-files-from', '--null', '--files-from=members.lst']);
        if ($code !== 0 || trim($stderr) !== '') {
            throw new ValidationFailure('tar --extract failed: '.$this->oneLine($stderr));
        }
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function runTar(array $args): array
    {
        $env = ['PATH' => PHP_OS_FAMILY === 'Windows' ? dirname($this->opt['tar']) : '/bin:/usr/bin', 'LC_ALL' => 'C'];
        if (PHP_OS_FAMILY === 'Windows' && getenv('SYSTEMROOT')) {
            $env['SYSTEMROOT'] = (string) getenv('SYSTEMROOT');
        }

        return $this->exec(array_merge([$this->opt['tar']], $args), $this->work, $env);   // no TAR_OPTIONS
    }

    // ------------------------------------------------------------------------------------ extracted contents

    /**
     * @param  list<array{name: string, type: string, size: int}>  $members
     * @return array<string, array{size: int, sha256: string}> file path => size and hash (PACKAGE.json included)
     */
    private function checkExtractedTree(array $members): array
    {
        $root = $this->work.'/extract';
        $found = [];
        $dirs = [];
        $this->walk($root, '', $found, $dirs);
        $expectedFiles = [];
        $expectedDirs = [];
        foreach ($members as $m) {
            if ($m['type'] === 'file') {
                $expectedFiles[$m['name']] = $m['size'];
                for ($p = dirname($m['name']); $p !== '.'; $p = dirname($p)) {
                    $expectedDirs[$p] = true;
                }
            }
        }
        ksort($expectedFiles);
        $actualSizes = array_map(fn ($f) => $f['size'], $found);
        ksort($actualSizes);
        ksort($dirs);
        ksort($expectedDirs);
        if ($actualSizes !== $expectedFiles || array_keys($dirs) !== array_keys($expectedDirs)) {
            throw new ValidationFailure('extracted tree does not match the scanned archive');
        }

        return $found;
    }

    /**
     * @param  array<string, array{size: int, sha256: string}>  $found
     * @param  array<string, true>  $dirs
     */
    private function walk(string $abs, string $rel, array &$found, array &$dirs): void
    {
        foreach (array_diff(scandir($abs) ?: [], ['.', '..']) as $name) {
            $path = $abs.'/'.$name;
            $r = $rel === '' ? $name : $rel.'/'.$name;
            if (is_link($path)) {
                throw new ValidationFailure('symbolic link in the extracted tree: '.$r);
            }
            $st = lstat($path);
            $kind = $st['mode'] & 0170000;
            if ($kind === 0040000) {
                $dirs[$r] = true;
                $this->walk($path, $r, $found, $dirs);
            } elseif ($kind === 0100000) {
                if ($st['nlink'] !== 1) {
                    throw new ValidationFailure('hard link in the extracted tree: '.$r);
                }
                $found[$r] = ['size' => $st['size'], 'sha256' => (string) hash_file('sha256', $path)];
            } else {
                throw new ValidationFailure('special file in the extracted tree: '.$r);
            }
        }
    }

    // --------------------------------------------------------------------------------------- PACKAGE.json

    /**
     * @param  array<string, array{size: int, sha256: string}>  $hashes
     * @return array<string, mixed>
     */
    private function checkPackageJson(string $id, array $hashes): array
    {
        $raw = (string) file_get_contents($this->work.'/extract/PACKAGE.json');
        try {
            $p = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationFailure('PACKAGE.json is not valid JSON');
        }
        $this->exactKeys($p, ['format', 'format_version', 'package_id', 'source', 'builder', 'build', 'vite_inputs', 'files'], 'PACKAGE.json');
        if ($p['format'] !== 'hirfah-vite-package' || $p['format_version'] !== 1) {
            throw new ValidationFailure('unsupported PACKAGE.json format');
        }
        if ($p['package_id'] !== $id) {
            throw new ValidationFailure('PACKAGE.json package_id does not match the package directory');
        }
        $this->exactKeys($p['source'], ['repository', 'branch_ref', 'commit', 'tree', 'commit_time'], 'PACKAGE.json source');
        $this->exactKeys($p['builder'], ['node', 'npm', 'vite', 'platform', 'created_at'], 'PACKAGE.json builder');
        $this->exactKeys($p['build'], ['root', 'manifest', 'manifest_sha256', 'manifest_entries', 'file_count', 'total_bytes'], 'PACKAGE.json build');
        $s = $p['source'];
        foreach (['commit', 'tree'] as $k) {
            if (! is_string($s[$k]) || ! preg_match('/\A[0-9a-f]{40}\z/', $s[$k])) {
                throw new ValidationFailure("PACKAGE.json source.{$k} is not a 40-hex id");
            }
        }
        preg_match(PACKAGE_ID_PATTERN, $id, $m);
        if (! str_starts_with($s['commit'], $m[1])) {
            throw new ValidationFailure('PACKAGE.json source.commit does not match the package id');
        }
        foreach (['branch_ref', 'commit_time'] as $k) {
            if (! is_string($s[$k])) {
                throw new ValidationFailure("PACKAGE.json source.{$k} must be a string");
            }
        }
        if ($s['repository'] !== null && ! is_string($s['repository'])) {
            throw new ValidationFailure('PACKAGE.json source.repository must be a string or null');
        }
        foreach ($p['builder'] as $k => $v) {
            if (! is_string($v)) {
                throw new ValidationFailure("PACKAGE.json builder.{$k} must be a string");
            }
        }
        $b = $p['build'];
        if ($b['root'] !== 'build' || $b['manifest'] !== 'build/manifest.json') {
            throw new ValidationFailure('PACKAGE.json build root/manifest must be build and build/manifest.json');
        }
        if (! is_array($p['vite_inputs']) || ! array_is_list($p['vite_inputs']) || $p['vite_inputs'] === []) {
            throw new ValidationFailure('PACKAGE.json vite_inputs must be a non-empty list');
        }
        foreach ($p['vite_inputs'] as $input) {
            if (! is_string($input) || ! preg_match('#\Aresources/'.SAFE_SEGMENT.'(/'.SAFE_SEGMENT.')*\.(js|css)\z#', $input)) {
                throw new ValidationFailure('PACKAGE.json vite_inputs holds an invalid entry');
            }
        }
        if (count(array_unique($p['vite_inputs'])) !== count($p['vite_inputs'])) {
            throw new ValidationFailure('PACKAGE.json vite_inputs has duplicates');
        }
        if (! is_array($p['files']) || ! array_is_list($p['files'])) {
            throw new ValidationFailure('PACKAGE.json files must be a list');
        }
        $buildFiles = array_filter($hashes, fn ($k) => $k !== 'PACKAGE.json', ARRAY_FILTER_USE_KEY);
        $listed = [];
        foreach ($p['files'] as $f) {
            $this->exactKeys($f, ['path', 'sha256', 'size'], 'PACKAGE.json files[]');
            if (! is_string($f['path']) || ! is_string($f['sha256']) || ! is_int($f['size']) || isset($listed[$f['path']])) {
                throw new ValidationFailure('PACKAGE.json files[] has an invalid or duplicate entry');
            }
            $listed[$f['path']] = true;
            $actual = $buildFiles[$f['path']] ?? null;
            if ($actual === null) {
                throw new ValidationFailure('PACKAGE.json lists a file that is not in the archive: '.$f['path']);
            }
            if ($actual['size'] !== $f['size'] || ! hash_equals($actual['sha256'], $f['sha256'])) {
                throw new ValidationFailure('PACKAGE.json size or SHA-256 does not match: '.$f['path']);
            }
        }
        if (count($listed) !== count($buildFiles)) {
            $missing = array_diff(array_keys($buildFiles), array_keys($listed));
            throw new ValidationFailure('archive file not listed in PACKAGE.json: '.reset($missing));
        }
        $total = array_sum(array_column($buildFiles, 'size'));
        if ($b['file_count'] !== count($buildFiles) || $b['total_bytes'] !== $total
            || ! is_string($b['manifest_sha256']) || ! hash_equals($buildFiles['build/manifest.json']['sha256'], $b['manifest_sha256'])) {
            throw new ValidationFailure('PACKAGE.json build totals or manifest_sha256 do not match the archive');
        }

        return $p;
    }

    /** @param mixed $value */
    private function exactKeys($value, array $keys, string $label): void
    {
        if (! is_array($value) || array_is_list($value) && $value !== []) {
            throw new ValidationFailure("{$label} must be an object");
        }
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new ValidationFailure("{$label} has missing or unknown keys");
        }
    }

    // ------------------------------------------------------------------------------------------- manifest

    /**
     * @param  array<string, array{size: int, sha256: string}>  $hashes
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function checkManifest(array $hashes, array $package): array
    {
        try {
            $manifest = json_decode((string) file_get_contents($this->work.'/extract/build/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationFailure('build/manifest.json is not valid JSON');
        }
        if (! is_array($manifest) || $manifest === [] || array_is_list($manifest)) {
            throw new ValidationFailure('build/manifest.json must be a non-empty object');
        }
        if ($package['build']['manifest_entries'] !== count($manifest)) {
            throw new ValidationFailure('PACKAGE.json manifest_entries does not match the manifest');
        }
        $referenced = ['manifest.json' => true];
        foreach ($manifest as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
                throw new ValidationFailure("manifest entry without a file: {$key}");
            }
            $refs = [$entry['file']];
            foreach (['css', 'assets'] as $list) {
                if (isset($entry[$list])) {
                    if (! is_array($entry[$list]) || ! array_is_list($entry[$list])) {
                        throw new ValidationFailure("manifest {$list} of {$key} must be a list");
                    }
                    array_push($refs, ...$entry[$list]);
                }
            }
            foreach ($refs as $ref) {
                if (! is_string($ref) || ! preg_match('#\A'.SAFE_SEGMENT.'(/'.SAFE_SEGMENT.')*\z#', $ref) || in_array('..', explode('/', $ref), true)) {
                    throw new ValidationFailure("unsafe manifest path in {$key}");
                }
                if (! isset($hashes['build/'.$ref])) {
                    throw new ValidationFailure('file referenced by the manifest is missing: '.$ref);
                }
                if (preg_match('/\.(js|mjs|css)\z/i', $ref) && $hashes['build/'.$ref]['size'] === 0) {
                    throw new ValidationFailure('empty JavaScript or CSS file: '.$ref);
                }
                $referenced[$ref] = true;
            }
            foreach (['imports', 'dynamicImports'] as $list) {
                foreach ((array) ($entry[$list] ?? []) as $import) {
                    if (! is_string($import) || ! array_key_exists($import, $manifest)) {
                        throw new ValidationFailure("manifest {$list} of {$key} names a missing entry");
                    }
                }
            }
        }
        foreach (array_keys($hashes) as $path) {
            if ($path === 'PACKAGE.json') {
                continue;
            }
            $rel = substr($path, strlen('build/'));
            if (! preg_match(ALLOWED_EXTENSIONS, $rel)) {
                throw new ValidationFailure('file type not allowed: '.$rel);
            }
            if (! isset($referenced[$rel])) {
                throw new ValidationFailure('file not referenced by the manifest: '.$rel);
            }
        }
        foreach ($package['vite_inputs'] as $input) {
            if (! array_key_exists($input, $manifest)) {
                throw new ValidationFailure('Vite input missing from the manifest: '.$input);
            }
        }

        return $manifest;
    }

    // ---------------------------------------------------------------------------------------------- source

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $manifest
     */
    private function verifySource(string $app, array $package, array $manifest): void
    {
        $commit = $package['source']['commit'];
        [$code] = $this->git($app, ['rev-parse', '--is-inside-work-tree']);
        if ($code !== 0) {
            throw new UsageError('--app is not a git work tree');
        }
        [$code] = $this->git($app, ['cat-file', '-e', $commit.'^{commit}']);
        if ($code !== 0) {
            $this->out['SOURCE_STATUS'] = 'commit-not-available-locally';

            return;
        }
        [$code, $tree] = $this->git($app, ['rev-parse', $commit.'^{tree}']);
        if ($code !== 0 || trim($tree) !== $package['source']['tree']) {
            $this->out['SOURCE_STATUS'] = 'tree-mismatch';
            throw new ValidationFailure('source tree does not match the package (source.tree)');
        }
        [$code, $config] = $this->git($app, ['show', $commit.':vite.config.js']);
        if ($code !== 0) {
            $this->out['SOURCE_STATUS'] = 'vite-config-missing';
            throw new ValidationFailure('vite.config.js not found at the source commit');
        }
        preg_match_all(VITE_INPUT_PATTERN, $config, $m);
        $fromRepo = array_values(array_unique($m[1]));
        $fromPackage = $package['vite_inputs'];
        sort($fromRepo);
        sort($fromPackage);
        if ($fromRepo === [] || $fromRepo !== $fromPackage) {
            $this->out['SOURCE_STATUS'] = 'vite-inputs-mismatch';
            throw new ValidationFailure('Vite inputs at the source commit differ from the package');
        }
        foreach ($fromRepo as $input) {
            if (! array_key_exists($input, $manifest)) {
                $this->out['SOURCE_STATUS'] = 'vite-inputs-mismatch';
                throw new ValidationFailure('Vite input of the source commit missing from the manifest: '.$input);
            }
        }
        if ($this->opt['mode'] === 'install') {
            [$code, $head] = $this->git($app, ['rev-parse', 'HEAD']);
            if ($code !== 0 || trim($head) !== $commit) {
                $this->out['SOURCE_STATUS'] = 'not-head';

                return;
            }
            $this->out['SOURCE_STATUS'] = 'verified-head';
        } else {
            $this->out['SOURCE_STATUS'] = 'verified';
        }
        $this->out['SOURCE_VERIFIED'] = 'true';
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function git(string $app, array $args): array
    {
        $env = ['PATH' => (string) getenv('PATH'), 'LC_ALL' => 'C', 'GIT_OPTIONAL_LOCKS' => '0', 'GIT_TERMINAL_PROMPT' => '0',
            'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir())];
        foreach (['SYSTEMROOT', 'USERPROFILE', 'TEMP', 'TMP'] as $k) {
            if (getenv($k) !== false) {
                $env[$k] = (string) getenv($k);
            }
        }

        return $this->exec(array_merge([$this->opt['git'], '--no-optional-locks', '-C', $app], $args), $app, $env);
    }

    // ------------------------------------------------------------------------------------------- helpers

    /**
     * Runs a program without a shell (argument array) in $cwd with exactly the given environment.
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function exec(array $cmd, string $cwd, array $env): array
    {
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (! is_resource($proc)) {
            throw new UsageError('cannot run '.basename((string) $cmd[0]));
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), $stdout, $stderr];
    }

    private function oneLine(string $text): string
    {
        return substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 300);
    }

    /** Removes this run's work directory (and a stage directory it created but did not complete), without following links. */
    public function cleanup(): void
    {
        if ($this->stageCreated && ! $this->stageDone && $this->stage !== null) {
            $this->stageCreated = false;
            $this->remove($this->stage);
            if (file_exists($this->stage)) {
                fwrite(STDERR, "WARN: incomplete stage directory could not be fully removed\n");
            }
        }
        if ($this->work === null) {
            return;
        }
        $work = $this->work;
        $this->work = null;
        if (! $this->within($work, $this->workRoot) || ! preg_match('#/hirfah-vite-[0-9a-f]{12}-[0-9]{8}T[0-9]{6}Z\.[0-9a-f]{16}\z#', $work)) {
            fwrite(STDERR, "WARN: refusing to remove an unexpected work path\n");

            return;
        }
        $this->remove($work);
        if (file_exists($work)) {
            fwrite(STDERR, "WARN: work directory could not be fully removed\n");
        }
    }

    private function remove(string $path): void
    {
        $st = @lstat($path);
        if ($st === false) {
            return;
        }
        $kind = $st['mode'] & 0170000;
        if (! is_link($path) && $kind === 0040000) {
            // only a real directory (by lstat) is entered
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
        // links, Windows junctions and anything else are removed as one entry, never followed
        if (! @unlink($path)) {
            @rmdir($path);
        }
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
        if (! preg_match('/\A--(package-dir|mode|app|web|work-root|tar|git|stage-dir)(?:=(.*))?\z/s', $arg, $m)) {
            throw new UsageError('unknown argument: '.$arg);
        }
        $value = $m[2] ?? array_shift($args);
        if ($value === null || $value === '') {
            throw new UsageError("--{$m[1]} needs a value");
        }
        $opt[$m[1]] = $value;
    }
    foreach (['package-dir', 'mode', 'app', 'web', 'work-root'] as $required) {
        if (! isset($opt[$required])) {
            throw new UsageError("--{$required} is required");
        }
    }
    if (! in_array($opt['mode'], ['install', 'prepare'], true)) {
        throw new UsageError('--mode must be install or prepare');
    }
    if (isset($opt['stage-dir']) && $opt['mode'] !== 'install') {
        throw new UsageError('--stage-dir is only allowed with --mode=install');
    }

    return $opt;
}

function emit(array $out): void
{
    foreach (OUTPUT_KEYS as $key) {
        $value = str_replace(["\r", "\n"], ' ', (string) ($out[$key] ?? ''));
        fwrite(STDOUT, $key.'='.$value."\n");
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
$validator = null;
try {
    $validator = new Validator(parse_options($argv));
    $validator->run();
    $exit = 0;
} catch (ValidationFailure $e) {
    if ($validator !== null) {
        $validator->out['REASON'] = $e->getMessage();
    }
    $exit = 1;
} catch (UsageError $e) {
    $exit = 2;
    $reason = $e->getMessage();
} catch (Throwable $e) {
    $exit = 2;
    $reason = 'internal error: '.$e->getMessage();
} finally {
    $validator?->cleanup();
}
if ($validator === null) {
    $out = array_fill_keys(OUTPUT_KEYS, '');
    $out['PACKAGE_VALID'] = 'false';
    $out['SOURCE_VERIFIED'] = 'false';
    $out['SOURCE_STATUS'] = 'not-checked';
    $out['INSTALL_APPROVED'] = 'false';
    $out['RESULT'] = 'FAIL';
    $out['REASON'] = $reason ?? 'usage error';
    emit($out);
} else {
    if ($exit !== 0) {
        $validator->out['RESULT'] = 'FAIL';
        $validator->out['INSTALL_APPROVED'] = 'false';
        $validator->out['STAGE_DIR'] = '';
        if ($exit === 2) {
            $validator->out['REASON'] = $reason ?? 'error';
        }
    }
    emit($validator->out);
}
exit($exit);
