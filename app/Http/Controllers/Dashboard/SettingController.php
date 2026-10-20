<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateHomeContentRequest;
use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Setting;
use App\Support\SiteAvailability\SiteAvailabilitySettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    use AuthorizesRequests;

    public function homeContent()
    {
        $this->authorize('view', Setting::class);

        return response()->json([
            'home_content' => array_replace(config('homepage'), Setting::query()->first()?->home_content ?? []),
        ]);
    }

    public function updateHomeContent(UpdateHomeContentRequest $request)
    {
        $setting = Setting::singleton();
        $setting->update([
            'home_content' => array_replace($setting->home_content ?? [], $request->validated('home_content')),
        ]);

        return response()->json(['message' => 'تم تحديث محتوى الصفحة الرئيسية.']);
    }

    public function index(Request $request)
    {
        $this->authorize('view', Setting::class);

        $setting = Setting::singleton();

        return view('dashboard.settings.index', [
            'setting' => $setting,
            // SITE-AVAILABILITY Phase 3B: the card's data is read for a Super Admin only.
            'siteAvailability' => $request->user('admin')?->isSuperAdmin() ? SiteAvailabilitySettings::card($setting) : null,
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
