<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('view', Setting::class);

        return view('dashboard.settings.index', [
            'setting' => Setting::singleton(),
        ]);
    }

    public function update(UpdateSettingRequest $request)
    {
        $setting = Setting::singleton();
        $data = $request->validated();
        $oldFiles = [];

        foreach (['logo', 'favicon'] as $field) {
            if ($request->hasFile($field)) {
                $oldFiles[] = $setting->{$field};
                $data[$field] = $request->file($field)->store('settings', 'public');
            } else {
                unset($data[$field]);
            }
        }

        $setting->update($data);

        foreach (array_filter($oldFiles) as $oldFile) {
            Storage::disk('public')->delete($oldFile);
        }

        return redirect()
            ->route('dashboard.setting.index')
            ->with('success', t('dashboard.Settings_updated_successfully', 'Settings updated successfully.'));
    }
}
