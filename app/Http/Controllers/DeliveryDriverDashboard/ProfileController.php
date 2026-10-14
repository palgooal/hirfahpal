<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryDriverDashboard\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'delivery_driver' => $request->user('delivery_driver')->load(['profile.governorate', 'profile.city']),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $driver = $request->user('delivery_driver');
        $data = $request->validated();

        DB::transaction(function () use ($driver, $data): void {
            $driver->update(array_filter([
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($value, $key) => array_key_exists($key, $data), ARRAY_FILTER_USE_BOTH));

            $profileData = [];
            foreach (['vehicle_type', 'vehicle_plate', 'governorate_id', 'city_id', 'is_available'] as $key) {
                if (array_key_exists($key, $data)) {
                    $profileData[$key] = $data[$key];
                }
            }

            if ($profileData !== []) {
                $driver->profile()->updateOrCreate(['delivery_driver_id' => $driver->id], $profileData);
            }
        });

        return response()->json([
            'message' => 'Delivery driver profile updated successfully.',
            'delivery_driver' => $driver->fresh(['profile.governorate', 'profile.city']),
        ]);
    }
}
