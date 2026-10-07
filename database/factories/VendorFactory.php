<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('056#######'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A vendor whose store profile has been approved.
     */
    public function approved(): static
    {
        return $this->withProfile('approved');
    }

    /**
     * A vendor with a store profile in the given approval state.
     */
    public function withProfile(string $approvalStatus, ?string $rejectionReason = null): static
    {
        return $this->afterCreating(function (Vendor $vendor) use ($approvalStatus, $rejectionReason): void {
            $vendor->profile()->create([
                'store_name' => $vendor->name.' Store',
                'slug' => Str::slug($vendor->name).'-'.$vendor->id,
                'approval_status' => $approvalStatus,
                'approved_at' => $approvalStatus === 'approved' ? now() : null,
                'rejection_reason' => $rejectionReason,
            ]);
        });
    }
}
