<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateHomeContentRequest;
use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Setting;
use App\Support\Media\VendorMediaPath;
use App\Support\SiteAvailability\SiteAvailabilitySettings;
use App\Support\Storefront\SocialPlatforms;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Palgoal\MediaLibrary\Models\Media;

class SettingController extends Controller
{
    use AuthorizesRequests;

    /** SETTINGS-MEDIA-01: form name => legacy path column; the media column is {name}_media_id. */
    private const BRAND_IMAGES = ['logo' => 'logo', 'favicon' => 'favicon'];

    /** The image types the media picker offers (as CategoryManagementController). */
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

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

        // SETTINGS-MEDIA-01: the logo and favicon come from the media library; their fields are applied explicitly,
        // never through mass assignment. No file is ever deleted here.
        $brand = $this->brandImageChanges($request, $setting);
        foreach (array_keys(self::BRAND_IMAGES) as $name) {
            unset($data[$name.'_media_id'], $data['remove_'.$name], $data['remove_legacy_'.$name]);
        }

        try {
            $setting->fill($data)->forceFill($brand)->save();
        } catch (QueryException $exception) {
            return $this->refusedBrandImage($exception, $brand);
        }

        return redirect()
            ->route('dashboard.setting.index')
            ->with('success', t('dashboard.Settings_updated_successfully', 'Settings updated successfully.'));
    }

    /**
     * SETTINGS-MEDIA-01: the logo and favicon changes, as column => value, the same rules as a category image
     * (CategoryManagementController::imageChange()). For each of them, with the form fields {name}_media_id,
     * remove_{name} and remove_legacy_{name}:
     *
     *  - remove_{name}=1 with an id: 422 (choose or remove, not both);
     *  - remove_{name}=1: unlinks the media and clears the legacy path, so the site shows its fallback image again;
     *  - remove_legacy_{name}=1: clears the legacy path only (an image uploaded before the media library);
     *  - no id, or the id already linked: no media change, and no media ability is needed;
     *  - another id: needs media.view (403 otherwise); the item must exist, be a JPG, PNG, GIF or WebP image and not
     *    belong to a vendor (422 otherwise).
     *
     * Unlinking or clearing a path never deletes a file: the media item stays in the library, the old upload on disk.
     *
     * @return array<string, int|null>
     */
    private function brandImageChanges(Request $request, Setting $setting): array
    {
        $changes = [];
        $errors = [];

        foreach (self::BRAND_IMAGES as $name => $legacy) {
            $field = $name.'_media_id';
            $remove = $request->boolean('remove_'.$name);
            $id = $request->filled($field) ? (int) $request->input($field) : null;

            if ($remove && $id !== null) {
                $errors[$field] = $this->untranslatedDefault('dashboard.Category_image_conflict', 'Choose a new image or remove the current one, not both.');

                continue;
            }

            if ($remove) {
                $changes[$field] = null;
                $changes[$legacy] = null;

                continue;
            }

            if ($request->boolean('remove_legacy_'.$name)) {
                $changes[$legacy] = null;
            }

            if ($id === null || (int) $setting->{$field} === $id) {
                continue;
            }

            abort_unless($request->user('admin')?->can('viewAny', Media::class), 403);

            $media = Media::query()->find($id);

            if (! $media) {
                $errors[$field] = $this->untranslatedDefault('dashboard.Category_image_missing', 'The selected image no longer exists. Choose another image.');
            } elseif ($media->file_type !== 'image' || ! in_array($media->mime_type, self::IMAGE_MIME_TYPES, true)) {
                $errors[$field] = $this->untranslatedDefault('dashboard.Category_image_invalid', 'The selected file is not a supported image (JPG, PNG, GIF or WebP).');
            } elseif (VendorMediaPath::isVendorOwned($media->file_path)) {
                // ADMIN-MEDIA-ORGANIZATION-01: site images come from the admin media library only.
                $errors[$field] = $this->untranslatedDefault('dashboard.Category_image_vendor_owned', 'This image belongs to a vendor. Choose an image from the admin media library.');
            } else {
                $changes[$field] = (int) $media->getKey();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $changes;
    }

    /**
     * The database refused the save. A chosen image deleted after it was checked (its foreign key) is reported on its
     * own field; any other database error is re-thrown.
     *
     * @param  array<string, int|null>  $brand
     */
    private function refusedBrandImage(QueryException $exception, array $brand): RedirectResponse
    {
        $errors = [];

        foreach (array_keys(self::BRAND_IMAGES) as $name) {
            $id = $brand[$name.'_media_id'] ?? null;

            if ($id !== null && ! Media::query()->whereKey($id)->exists()) {
                $errors[$name.'_media_id'] = $this->untranslatedDefault('dashboard.Category_image_missing', 'The selected image no longer exists. Choose another image.');
            }
        }

        if ($errors === []) {
            throw $exception;
        }

        return redirect()->route('dashboard.setting.index')->withInput()->withErrors($errors);
    }

    private function untranslatedDefault(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
