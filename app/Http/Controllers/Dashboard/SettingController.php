<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateHomeContentRequest;
use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Setting;
use App\Support\SiteAvailability\SiteAvailabilitySettings;
use App\Support\Storefront\SocialPlatforms;
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

        // SETTINGS-FIX-01 (SET-03): reading the page never creates the settings row.
        $setting = Setting::forDisplay();

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

        // FOOTER-I18N-01C: the footer texts per language (texts.ar / texts.en, flat Arabic copy kept); a language or a
        // field not sent keeps what is saved, an empty one goes back to its language's default.
        if (array_key_exists('footer', $data)) {
            $data['footer_content'] = Setting::mergeFooterTexts($setting->footer_content, $data['footer'] ?? []);
        }

        // FOOTER-SOCIAL-02B: the social list only when the form sent it; an omitted list keeps the saved one (and
        // mergeFooter() keeps it through text-only saves). An empty list is saved as such, so removed links never come
        // back through the legacy fields, which follow the list for code that still reads them.
        if (array_key_exists('footer_social', $data)) {
            $links = SocialPlatforms::fromForm($data['footer_social'] ?? []);
            $data['footer_content'] = array_replace(
                $data['footer_content'] ?? Setting::mergeFooter($setting->footer_content, []),
                SocialPlatforms::legacyFieldsFor($links),
                ['social_links' => $links],
            );
        }

        unset($data['footer'], $data['footer_social']);

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
