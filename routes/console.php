<?php

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
 * Super admin status comes only from admins.super_admin. This command never
 * creates admins or ability rows, and never picks an account on its own.
 */
Artisan::command('admin:make-super
    {identifier? : Email or phone of an existing admin}
    {--check : Report super admin status without changing anything}', function () {
    if ($this->option('check')) {
        $superAdmins = Admin::query()->where('super_admin', true)->orderBy('id')->get();
        $activeCount = $superAdmins->where('status', 'active')->count();

        $this->table(
            ['Admins', 'Super admins', 'Active super admins'],
            [[Admin::query()->count(), $superAdmins->count(), $activeCount]]
        );

        if ($superAdmins->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'Email', 'Phone', 'Status'],
                $superAdmins->map(fn (Admin $admin) => [$admin->id, $admin->name, $admin->email, $admin->phone, $admin->status])->all()
            );
        }

        if ($activeCount === 0) {
            $this->warn('No active super admin exists. Promote an existing active admin with: php artisan admin:make-super <email|phone>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    $identifier = trim((string) $this->argument('identifier'));

    if ($identifier === '') {
        $this->error('Provide the email or phone of an existing admin, or use --check.');

        return Command::FAILURE;
    }

    $matches = Admin::query()
        ->where('email', $identifier)
        ->orWhere('phone', $identifier)
        ->get();

    if ($matches->isEmpty()) {
        $this->error('No admin found with that email or phone.');

        return Command::FAILURE;
    }

    if ($matches->count() > 1) {
        $this->error('The identifier matches more than one admin; nothing was changed.');

        return Command::FAILURE;
    }

    $admin = $matches->sole();

    $this->table(
        ['ID', 'Name', 'Email', 'Phone', 'Status', 'Super admin'],
        [[$admin->id, $admin->name, $admin->email, $admin->phone, $admin->status, $admin->isSuperAdmin() ? 'yes' : 'no']]
    );

    if ($admin->status !== 'active') {
        $this->error("Admin #{$admin->id} is not active ({$admin->status}); nothing was changed.");

        return Command::FAILURE;
    }

    if ($admin->isSuperAdmin()) {
        $this->info("Admin #{$admin->id} is already a super admin; nothing was changed.");

        return Command::SUCCESS;
    }

    if (! $this->confirm('Promote this admin to super admin?')) {
        $this->warn('Promotion cancelled; nothing was changed.');

        return Command::FAILURE;
    }

    $admin->forceFill(['super_admin' => true])->save();

    $this->info("Admin #{$admin->id} is now a super admin.");

    return Command::SUCCESS;
})->purpose('Promote an existing active admin to super admin, or check super admin status');
