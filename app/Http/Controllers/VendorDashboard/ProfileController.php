<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\UpdateProfileRequest;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($vendor, $data): void {
            $vendor->update([
                'name' => $data['name'],
                'email' => array_key_exists('email', $data) ? $data['email'] : $vendor->email,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $vendor->phone,
                'avatar' => array_key_exists('avatar', $data) ? $data['avatar'] : $vendor->avatar,
            ]);

            $profile = $vendor->profile;
            $profileData = [
                'governorate_id' => array_key_exists('governorate_id', $data) ? $data['governorate_id'] : $profile?->governorate_id,
                'city_id' => array_key_exists('city_id', $data) ? $data['city_id'] : $profile?->city_id,
                'store_name' => $data['store_name'],
                'slug' => ($data['slug'] ?? null) ?: ($profile?->slug ?: $this->uniqueSlug($data['store_name'], $profile?->id)),
                'short_description' => array_key_exists('short_description', $data) ? $data['short_description'] : $profile?->short_description,
                'description' => array_key_exists('description', $data) ? $data['description'] : $profile?->description,
                'address_line' => array_key_exists('address_line', $data) ? $data['address_line'] : $profile?->address_line,
                'logo' => array_key_exists('logo', $data) ? $data['logo'] : $profile?->logo,
                'cover_image' => array_key_exists('cover_image', $data) ? $data['cover_image'] : $profile?->cover_image,
            ];

            if ($profile) {
                $profile->update($profileData);

                return;
            }

            $vendor->profile()->create($profileData);
        });

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
