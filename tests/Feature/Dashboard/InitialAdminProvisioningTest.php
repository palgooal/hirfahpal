<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class InitialAdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Provisioned-pass-2026';

    private const IDENTITY = [
        '--name' => 'Platform Owner',
        '--email' => 'owner@example.com',
        '--phone' => '0591234567',
    ];

    public function test_seeder_creates_no_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Admin::count());
    }

    public function test_seeder_does_not_modify_existing_admins(): void
    {
        $blocked = $this->createAdmin('blocked@example.com', '0590000001', status: 'blocked');
        $pending = $this->createAdmin('pending@example.com', '0590000002', status: 'pending');
        $superAdmin = $this->createAdmin('super@example.com', '0590000003', superAdmin: true);
        $snapshots = collect([$blocked, $pending, $superAdmin])->mapWithKeys(fn (Admin $admin) => [$admin->id => $admin->fresh()->getAttributes()]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Admin::count());
        foreach ($snapshots as $id => $attributes) {
            $this->assertSame($attributes, Admin::find($id)->getAttributes());
        }
    }

    public function test_seeder_does_not_reset_password_reactivate_or_grant_super(): void
    {
        $admin = $this->createAdmin('blocked@example.com', '0590000001', status: 'blocked');

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('original-password', $admin->password));
        $this->assertSame('blocked', $admin->status);
        $this->assertFalse($admin->isSuperAdmin());
    }

    public function test_tracked_seeder_contains_no_admin_provisioning(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['App\\Models\\Admin', 'Admin::', 'Hash::', 'bcrypt(', 'updateOrCreate', 'super_admin', "'password'", "'email'", "'phone'", "table('admins')"] as $needle) {
            $this->assertStringNotContainsString($needle, $source);
        }

        $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $source);
    }

    public function test_command_signature_accepts_no_password_argument_or_option(): void
    {
        $definition = $this->command()->getDefinition();

        $this->assertSame([], $definition->getArguments());
        $this->assertFalse($definition->hasOption('password'));
        $this->assertTrue($definition->hasOption('password-stdin'));
        $this->assertFalse($definition->getOption('password-stdin')->acceptValue());
    }

    public function test_interactive_flow_creates_an_active_flagged_super_admin(): void
    {
        $this->artisan('admin:create-super')
            ->expectsQuestion('Name', 'Platform Owner')
            ->expectsQuestion('Email', 'owner@example.com')
            ->expectsQuestion('Phone', '0591234567')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsTable(['Name', 'Email', 'Phone', 'Status', 'Super admin'], [['Platform Owner', 'owner@example.com', '0591234567', 'active', 'yes']])
            ->expectsConfirmation('Create this super admin?', 'yes')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertSuccessful();

        $admin = Admin::sole();
        $this->assertSame('Platform Owner', $admin->name);
        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame('0591234567', $admin->phone);
        $this->assertTrue($admin->isActiveSuperAdmin());
    }

    public function test_created_super_admin_has_no_abilities_and_no_email_verification(): void
    {
        [$code] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $this->assertSame(0, $code);
        $admin = Admin::sole();
        $this->assertSame([], $admin->abilityNames());
        $this->assertSame(0, RoleUser::count());
        $this->assertNull($admin->email_verified_at);
    }

    public function test_password_is_hashed_and_plaintext_is_not_persisted(): void
    {
        $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $admin = Admin::sole();
        $this->assertNotSame(self::PASSWORD, $admin->password);
        $this->assertTrue(Hash::isHashed($admin->password));
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));

        foreach ($admin->getAttributes() as $column => $value) {
            $this->assertStringNotContainsString(self::PASSWORD, (string) $value, $column);
        }
    }

    public function test_password_is_never_printed(): void
    {
        [$code, $output] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('created', $output);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
    }

    public function test_interactive_password_must_satisfy_the_default_policy(): void
    {
        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsQuestion('Password', 'short7')
            ->expectsQuestion('Confirm password', 'short7')
            ->doesntExpectOutputToContain('short7')
            ->assertFailed();

        $this->assertSame(0, Admin::count());
    }

    public function test_mismatched_password_confirmation_is_refused(): void
    {
        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD.'-different')
            ->assertFailed();

        $this->assertSame(0, Admin::count());
    }

    public function test_duplicate_email_is_refused_without_mutation(): void
    {
        $existing = $this->createAdmin('owner@example.com', '0590000001');
        $before = $existing->fresh()->getAttributes();

        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsOutputToContain('already exists; nothing was changed')
            ->expectsOutputToContain('admin:make-super')
            ->assertFailed();

        $this->assertSame(1, Admin::count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
    }

    public function test_duplicate_phone_is_refused_without_mutation(): void
    {
        $existing = $this->createAdmin('someone@example.com', '0591234567');
        $before = $existing->fresh()->getAttributes();

        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsOutputToContain('already exists; nothing was changed')
            ->assertFailed();

        $this->assertSame(1, Admin::count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
    }

    public function test_existing_ordinary_admin_is_not_silently_promoted(): void
    {
        $existing = $this->createAdmin('owner@example.com', '0591234567');
        RoleUser::create(['user_id' => $existing->id, 'role_name' => 'settings.view', 'ability' => 'allow']);

        [$code] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true, '--additional' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $existing->refresh();
        $this->assertFalse($existing->isSuperAdmin());
        $this->assertTrue(Hash::check('original-password', $existing->password));
        $this->assertSame(['settings.view'], $existing->abilityNames());
    }

    public function test_existing_blocked_admin_is_not_mutated(): void
    {
        $this->assertExistingAdminUntouched('blocked');
    }

    public function test_existing_pending_admin_is_not_mutated(): void
    {
        $this->assertExistingAdminUntouched('pending');
    }

    public function test_rerunning_with_the_same_identity_is_refused(): void
    {
        [$first] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD);
        [$second, $output] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true, '--additional' => true], 'Another-pass-2026');

        $this->assertSame(0, $first);
        $this->assertSame(1, $second);
        $this->assertStringContainsString('already exists', $output);
        $this->assertSame(1, Admin::count());
        $this->assertTrue(Hash::check(self::PASSWORD, Admin::sole()->password));
    }

    public function test_first_super_admin_is_created_without_additional(): void
    {
        $this->createAdmin('ordinary@example.com', '0590000001');
        $this->createAdmin('inactive-super@example.com', '0590000002', superAdmin: true, status: 'blocked');

        [$code] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $this->assertSame(0, $code);
        $this->assertTrue(Admin::where('email', 'owner@example.com')->sole()->isActiveSuperAdmin());
    }

    public function test_second_super_admin_is_refused_without_additional(): void
    {
        $this->createAdmin('existing-super@example.com', '0590000001', superAdmin: true);

        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsOutputToContain('An active super admin already exists')
            ->assertFailed();

        $this->assertSame(1, Admin::count());
    }

    public function test_second_super_admin_is_created_with_explicit_additional(): void
    {
        $this->createAdmin('existing-super@example.com', '0590000001', superAdmin: true);

        $this->artisan('admin:create-super', self::IDENTITY + ['--additional' => true])
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsConfirmation('Create this ADDITIONAL super admin while another active super admin exists?', 'yes')
            ->assertSuccessful();

        $this->assertSame(2, Admin::where('super_admin', true)->where('status', 'active')->count());
    }

    public function test_additional_does_not_bypass_duplicate_or_password_validation(): void
    {
        $this->createAdmin('existing-super@example.com', '0591234567', superAdmin: true);

        [$duplicate] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true, '--additional' => true], self::PASSWORD);
        [$weak] = $this->runWithStdin(['--name' => 'Other', '--email' => 'other@example.com', '--phone' => '0597777777', '--password-stdin' => true, '--yes' => true, '--additional' => true], 'short7');

        $this->assertSame(1, $duplicate);
        $this->assertSame(1, $weak);
        $this->assertSame(1, Admin::count());
    }

    public function test_declining_confirmation_creates_nothing(): void
    {
        $this->artisan('admin:create-super', self::IDENTITY)
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsConfirmation('Create this super admin?', 'no')
            ->expectsOutputToContain('nothing was created')
            ->assertFailed();

        $this->assertSame(0, Admin::count());
    }

    public function test_non_interactive_without_password_source_fails_safely(): void
    {
        [$code, $output] = $this->runWithStdin(self::IDENTITY + ['--yes' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--password-stdin', $output);
        $this->assertSame(0, Admin::count());
    }

    public function test_non_interactive_without_yes_creates_nothing(): void
    {
        [$code, $output] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--yes', $output);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
        $this->assertSame(0, Admin::count());
    }

    public function test_password_stdin_creates_the_super_admin(): void
    {
        [$code, $output] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], self::PASSWORD.PHP_EOL);

        $this->assertSame(0, $code);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
        $this->assertTrue(Hash::check(self::PASSWORD, Admin::sole()->password));
    }

    public function test_invalid_stdin_password_is_refused(): void
    {
        foreach (['', 'short7'] as $password) {
            [$code, $output] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true], $password);

            $this->assertSame(1, $code);
            if ($password !== '') {
                $this->assertStringNotContainsString($password, $output);
            }
        }

        $this->assertSame(0, Admin::count());
    }

    public function test_missing_identity_in_non_interactive_mode_fails_safely(): void
    {
        [$code, $output] = $this->runWithStdin(['--email' => 'owner@example.com', '--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--name', $output);
        $this->assertStringContainsString('--phone', $output);
        $this->assertSame(0, Admin::count());
    }

    public function test_invalid_email_is_refused(): void
    {
        [$code] = $this->runWithStdin(['--name' => 'Owner', '--email' => 'not-an-email', '--phone' => '0591234567', '--password-stdin' => true, '--yes' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $this->assertSame(0, Admin::count());
    }

    public function test_make_super_command_remains_operational(): void
    {
        $admin = $this->createAdmin('ordinary@example.com', '0590000001');

        $this->artisan('admin:make-super', ['--check' => true])
            ->expectsOutputToContain('No active super admin exists.')
            ->assertFailed();

        $this->artisan('admin:make-super', ['identifier' => 'ordinary@example.com'])
            ->expectsConfirmation('Promote this admin to super admin?', 'yes')
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->isActiveSuperAdmin());
    }

    private function assertExistingAdminUntouched(string $status): void
    {
        $existing = $this->createAdmin('owner@example.com', '0591234567', status: $status);
        $before = $existing->fresh()->getAttributes();

        [$code] = $this->runWithStdin(self::IDENTITY + ['--password-stdin' => true, '--yes' => true, '--additional' => true], self::PASSWORD);

        $this->assertSame(1, $code);
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertSame(0, RoleUser::count());
    }

    /**
     * Runs the command non-interactively with $stdin as its input stream,
     * the same way a deployment pipe would feed it.
     *
     * @return array{0: int, 1: string}
     */
    private function runWithStdin(array $options, string $stdin): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $stdin);
        rewind($stream);

        $input = new ArrayInput($options);
        $input->setStream($stream);
        $input->setInteractive(false);

        $output = new BufferedOutput;
        $code = $this->command()->run($input, $output);

        return [$code, $output->fetch()];
    }

    private function command()
    {
        return $this->app->make(Kernel::class)->all()['admin:create-super'];
    }

    private function createAdmin(string $email, string $phone, bool $superAdmin = false, string $status = 'active'): Admin
    {
        return Admin::create([
            'name' => 'Existing '.$email,
            'email' => $email,
            'phone' => $phone,
            'password' => 'original-password',
            'status' => $status,
            'super_admin' => $superAdmin,
        ]);
    }
}
