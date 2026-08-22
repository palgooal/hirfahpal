<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Support\Str;

class ModelPolicy
{
    public function before(Admin $admin, string $ability): ?bool
    {
        return $admin->super_admin ? true : null;
    }

    public function __call(string $name, array $arguments): bool
    {
        $resource = str_replace('Policy', '', class_basename($this));
        $resource = Str::plural(Str::kebab($resource));

        if ($name === 'viewAny') {
            $name = 'view';
        }

        $admin = $arguments[0] ?? null;

        if (! $admin instanceof Admin) {
            return false;
        }

        return $admin->roles()
            ->where('role_name', $resource . '.' . Str::kebab($name))
            ->where('ability', 'allow')
            ->exists();
    }
}
