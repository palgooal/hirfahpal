<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\UpdateProfileRequest;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'vendor' => $request->user('vendor')->load(['profile.governorate', 'profile.city']),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $vendor = $request->user('vendor');
        $data = $request->validated();

        $vendor->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'avatar' => $data['avatar'] ?? $vendor->avatar,
        ]);

        $profile = $vendor->profile;
        $profileData = [
            'governorate_id' => $data['governorate_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'store_name' => $data['store_name'],
            'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['store_name'], $profile?->id),
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'address_line' => $data['address_line'] ?? null,
            'logo' => $data['logo'] ?? $profile?->logo,
            'cover_image' => $data['cover_image'] ?? $profile?->cover_image,
        ];

        if ($profile) {
            $profile->update($profileData);
        } else {
            $vendor->profile()->create($profileData);
        }

        return response()->json([
            'message' => 'Vendor profile updated successfully.',
            'vendor' => $vendor->fresh(['profile.governorate', 'profile.city']),
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'vendor';
        $slug = $base;
        $counter = 2;

        while (VendorProfile::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
