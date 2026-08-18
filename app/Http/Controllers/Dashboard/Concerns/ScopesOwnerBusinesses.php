<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ScopesOwnerBusinesses
{
    private function accessibleBusinesses(): Builder
    {
        return Business::query()
            ->when(
                request()->routeIs('owner.*'),
                fn (Builder $query) => $query->where('owner_id', Auth::guard('owner')->id())
            );
    }

    private function ensureBusinessIsAccessible(int $businessId): void
    {
        abort_unless($this->accessibleBusinesses()->whereKey($businessId)->exists(), 404);
    }

    private function scopeToAccessibleBusiness(Builder $query): Builder
    {
        return $query->when(
            request()->routeIs('owner.*'),
            fn (Builder $query) => $query->whereHas(
                'business',
                fn (Builder $business) => $business->where('owner_id', Auth::guard('owner')->id())
            )
        );
    }
}
