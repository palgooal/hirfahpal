<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Creates a brand-new active super admin. It never modifies an existing
 * account; promoting one is the job of admin:make-super.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super
        {--name= : Full name of the new super admin}
        {--email= : Email address of the new super admin}
        {--phone= : Phone number of the new super admin}
        {--password-stdin : Read the password from standard input instead of prompting}
        {--additional : Allow creating another super admin while an active one already exists}
        {--yes : Skip the confirmation prompt; validation and safety checks still apply}';

    protected $description = 'Create a new active super admin (never modifies existing admins)';

    public function handle(): int
    {
        $identity = $this->collectIdentity();

        if ($identity === null) {
            return self::FAILURE;
        }

        if (! $this->passesValidation($identity, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ])) {
            return self::FAILURE;
        }

        if ($this->refuseExistingAdmin($identity)) {
            return self::FAILURE;
        }

        if (! $this->option('additional') && $this->activeSuperAdminExists()) {
            $this->error('An active super admin already exists; nothing was created. Pass --additional to create another one.');

            return self::FAILURE;
        }

        $password = $this->collectPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        if (! $this->confirmCreation($identity)) {
            return self::FAILURE;
        }

        try {
            $admin = DB::transaction(function () use ($identity, $password) {
                if (! $this->option('additional') && $this->activeSuperAdminExists(lock: true)) {
                    return null;
                }

                return Admin::create([
                    'name' => $identity['name'],
                    'email' => $identity['email'],
                    'phone' => $identity['phone'],
                    'password' => $password,
                    'status' => 'active',
                    'super_admin' => true,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('An admin with this email or phone already exists; nothing was created.');

            return self::FAILURE;
        }

        if ($admin === null) {
            $this->error('An active super admin was created meanwhile; nothing was created. Pass --additional to create another one.');

            return self::FAILURE;
        }

        $this->info("Super admin #{$admin->id} created ({$admin->email}).");

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, email: string, phone: string}|null
     */
    private function collectIdentity(): ?array
    {
        $identity = [];
        $missing = [];

        foreach (['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label) {
            $value = trim((string) $this->option($field));

            if ($value === '' && $this->input->isInteractive()) {
                $value = trim((string) $this->ask($label));
            }

            if ($value === '') {
                $missing[] = '--'.$field;
            }

            $identity[$field] = $value;
        }

        if ($missing !== [] && ! $this->input->isInteractive()) {
            $this->error('Missing required option(s) in non-interactive mode: '.implode(', ', $missing).'. Nothing was created.');

            return null;
        }

        return $identity;
    }

    private function refuseExistingAdmin(array $identity): bool
    {
        $existing = Admin::query()
            ->where('email', $identity['email'])
            ->orWhere('phone', $identity['phone'])
            ->get();

        if ($existing->isEmpty()) {
            return false;
        }

        $this->error('An admin with this email or phone already exists; nothing was changed.');
        $this->table(
            ['ID', 'Name', 'Email', 'Phone', 'Status', 'Super admin'],
            $existing->map(fn (Admin $admin) => [
                $admin->id, $admin->name, $admin->email, $admin->phone, $admin->status, $admin->isSuperAdmin() ? 'yes' : 'no',
            ])->all()
        );
        $this->line('To promote an existing active admin instead, run: php artisan admin:make-super <email|phone>');

        return true;
    }

    private function collectPassword(): ?string
    {
        if ($this->option('password-stdin')) {
            $password = $this->readPasswordFromStdin();

            return $this->passesValidation(
                ['password' => $password],
                ['password' => ['required', 'string', Password::default()]]
            ) ? $password : null;
        }

        if (! $this->input->isInteractive()) {
            $this->error('A password is required. In non-interactive mode pipe it in with --password-stdin. Nothing was created.');

            return null;
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        return $this->passesValidation(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'string', Password::default(), 'confirmed']]
        ) ? $password : null;
    }

    private function readPasswordFromStdin(): string
    {
        $stream = $this->input instanceof StreamableInputInterface && $this->input->getStream()
            ? $this->input->getStream()
            : STDIN;

        $line = fgets($stream);

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    private function confirmCreation(array $identity): bool
    {
        $this->table(
            ['Name', 'Email', 'Phone', 'Status', 'Super admin'],
            [[$identity['name'], $identity['email'], $identity['phone'], 'active', 'yes']]
        );

        if ($this->option('yes')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Non-interactive creation requires --yes to confirm. Nothing was created.');

            return false;
        }

        $question = $this->option('additional')
            ? 'Create this ADDITIONAL super admin while another active super admin exists?'
            : 'Create this super admin?';

        if (! $this->confirm($question)) {
            $this->warn('Creation cancelled; nothing was created.');

            return false;
        }

        return true;
    }

    /**
     * Validation messages never contain the submitted values, so a rejected
     * password is not echoed back.
     */
    private function passesValidation(array $data, array $rules): bool
    {
        $validator = Validator::make($data, $rules);

        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }

        return $validator->passes();
    }

    private function activeSuperAdminExists(bool $lock = false): bool
    {
        return Admin::query()
            ->where('super_admin', true)
            ->where('status', 'active')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }
}
