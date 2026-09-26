<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy extends ModelPolicy
{
    /**
     * Without a target this is the class-level "may edit admins" check used by
     * the UI; with a target, non-super admins may never touch a super admin.
     */
    public function edit(Admin $actor, ?Admin $target = null): bool
    {
        return $actor->hasAbility('admins.edit')
            && ! $target?->isSuperAdmin();
    }

    public function delete(Admin $actor, ?Admin $target = null): bool
    {
        return $actor->hasAbility('admins.delete')
            && ! $target?->isSuperAdmin();
    }

    /**
     * Super admin status is the stored flag only; a legacy "admins.super"
     * role_user row must not grant it through the generic ability lookup.
     */
    public function super(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
