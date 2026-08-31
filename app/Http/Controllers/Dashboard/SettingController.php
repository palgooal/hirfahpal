<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.view');

        $setting = Setting::query()->firstOrCreate([], [
            'timezone' => 'UTC',
            'default_locale' => config('app.locale', 'en'),
            'default_currency' => 'USD',
        ]);

        return view('dashboard.settings.index', compact('setting'));
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $setting = Setting::query()->firstOrCreate([]);
        $data = $request->safe()->except(['logo', 'favicon']);

        foreach (['logo', 'favicon'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            if ($setting->{$field}) {
                Storage::disk('public')->delete($setting->{$field});
            }

            $data[$field] = $request->file($field)->store('settings', 'public');
        }

        $setting->update($data);

        return back()->with('success', t('dashboard.Settings_Updated', 'Settings updated successfully.'));
    }
}
