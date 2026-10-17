<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminMediaTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * AD-UI-CAT-M3: the media library page inside the admin shell: layout, direction, translations, the actions each
 * media.* ability shows (the server still checks them), the sidebar entry and the uploader's name in the list.
 */
class MediaLibraryAdminPageTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Vite in dev-server mode: tags are rendered without a build, so the page's own script is visible.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://vite.test');
        Vite::useHotFile($this->hotFile);

        Storage::fake('public');
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- shell

    public function test_the_page_renders_inside_the_admin_shell(): void
    {
        $this->seedTranslations();
        $html = $this->page($this->admin(superAdmin: true));

        $this->assertStringContainsString('class="preset-1 admin-shell"', $html);
        $this->assertStringContainsString('id="admin-sidebar"', $html);
        $this->assertMatchesRegularExpression('#<header class="pc-header admin-header" id="header">#', $html);
        $this->assertMatchesRegularExpression('#<h1 class="admin-header-title"[^>]*>\s*مكتبة الوسائط\s*</h1>#u', $html, 'The page title is in the admin header.');
        $this->assertMatchesRegularExpression('#<title>مكتبة الوسائط \| #u', $html);
        $this->assertStringContainsString('id="admin-media-library"', $html);

        // The admin assets, the page's own script, and nothing of the package's standalone page.
        $this->assertStringContainsString('http://vite.test/resources/css/admin-dashboard.css', $html);
        $this->assertStringContainsString('http://vite.test/resources/js/admin-media-library.js', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('vendor/media-library/js/media-library.js', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);
        $this->assertStringNotContainsString('aria-label="Breadcrumb"', $html, 'No permanent breadcrumb (DEC-AD-UI-03).');
        $this->assertStringNotContainsString('لوحة التحكم', $html);
    }

    public function test_the_published_view_is_the_admin_override(): void
    {
        $view = file_get_contents(resource_path('views/vendor/media-library/media.blade.php'));

        $this->assertSame('dashboard-layout', config('media-library.layout'));
        $this->assertNull(config('media-library.breadcrumb'));
        $this->assertStringContainsString("@vite('resources/js/admin-media-library.js')", $view, 'Replaced by vendor:publish --force? Restore the admin page.');
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $view);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $view, 'No fixed Arabic text: everything goes through t().');
    }

    public function test_direction_and_language_follow_the_active_language(): void
    {
        $this->seedTranslations();
        $admin = $this->admin(superAdmin: true);

        $ar = $this->page($admin, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['رفع صور', 'أفلت الصور هنا لرفعها', 'تفاصيل الصورة', 'النص البديل', 'حفظ التغييرات', 'حذف المحدد', 'جارٍ تحميل الصور…', 'تعذّر تحميل الصور.', 'إعادة المحاولة'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }
        $this->assertStringContainsString('بحد أقصى 10 ميغابايت', $ar, 'The size limit comes from the configuration.');

        $en = $this->page($admin, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        foreach (['Upload images', 'Drop images here to upload them', 'Image details', 'Alternative text', 'Save changes', 'Delete selected', 'Media Library'] as $text) {
            $this->assertStringContainsString($text, $en, $text);
        }
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $this->between($en, 'id="admin-media-library"', '</dialog>'), 'No Arabic left in the English page body.');
    }

    public function test_server_messages_follow_the_admin_language(): void
    {
        $this->seedTranslations();
        $media = $this->storedMedia();
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $category->forceFill(['image_media_id' => $media->id])->save();
        $admin = $this->admin(superAdmin: true);

        foreach (['ar' => 'لا يمكن حذف هذا الوسيط لأنه مستخدم. أزله من أماكن استخدامه أولًا.', 'en' => 'This media is used and cannot be deleted. Remove it from where it is used first.'] as $locale => $message) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->deleteJson(route('media-library.media.destroy', $media->id))
                ->assertStatus(409)
                ->assertJsonPath('message', $message);
        }

        $refused = UploadedFile::fake()->createWithContent('evil.html', "GIF89a\x01\x00\x01\x00<script>alert(1)</script>");
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])
            ->postJson(route('media-library.media.store'), ['image' => $refused])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'يجب أن يكون الملف صورة JPG أو PNG أو GIF أو WebP، وأن يطابق امتداده محتواه.');
    }

    public function test_the_page_config_carries_the_translated_messages_and_the_routes(): void
    {
        $this->seedTranslations();
        $config = $this->config($this->page($this->admin(superAdmin: true), 'ar'));

        $this->assertSame(route('media-library.media.index'), $config['urls']['index']);
        $this->assertSame(route('media-library.media.store'), $config['urls']['store']);
        $this->assertSame(route('media-library.media.bulk-destroy'), $config['urls']['bulk']);
        $this->assertSame(route('media-library.media.update', ['id' => '__ID__']), $config['urls']['item']);
        $this->assertSame('ar', $config['locale']);
        $this->assertSame('لا يمكن حذف هذا الوسيط لأنه مستخدم. أزله من أماكن استخدامه أولًا.', $config['messages']['inUse']);
        $this->assertSame('عدد الصور المحذوفة: :count.', $config['messages']['deletedCount']);
        $this->assertSame('حُذفت السجلات، لكن تعذّرت إزالة بعض الملفات من التخزين، وسُجّلت لتنظيفها لاحقًا.', $config['messages']['cleanupWarning']);
        $this->assertNotEmpty($config['csrf']);
    }

    public function test_an_unseeded_language_shows_the_english_defaults_and_creates_no_rows(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);

        $html = $this->page($this->admin(superAdmin: true), 'ar');

        $this->assertStringContainsString('Upload images', $html);
        $this->assertSame([], $this->mediaKeyRows(), 'The page never auto-creates its keys.');
    }

    public function test_the_translation_row_check_detects_a_written_media_key(): void
    {
        // CUS-UI-06B: matched by prefix in PHP; on SQLite 'dashboard.Media\_%' (LIKE without an escape character)
        // matches nothing, so the assertion above could never fail.
        TranslationValue::create(['key' => 'dashboard.Media_Upload', 'locale' => 'ar', 'value' => 'Upload images']);
        TranslationValue::create(['key' => 'dashboard.Media', 'locale' => 'ar', 'value' => 'الوسائط']);

        $this->assertSame(['dashboard.Media_Upload'], $this->mediaKeyRows());
        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Media\_%')->count(), 'The LIKE form misses the row it should find.');
    }

    /** @return list<string> the rows of the media page keys (dashboard.Media_*), matched by prefix in PHP */
    private function mediaKeyRows(): array
    {
        return TranslationValue::query()->orderBy('key')->pluck('key')
            ->filter(fn (string $key) => str_starts_with($key, 'dashboard.Media_'))
            ->values()->all();
    }

    // ---------------------------------------------------------------- abilities

    public function test_actions_follow_the_media_abilities(): void
    {
        $this->seedTranslations();

        $cases = [
            'view only' => [['media.view'], ['create' => false, 'edit' => false, 'delete' => false]],
            'view + create' => [['media.view', 'media.create'], ['create' => true, 'edit' => false, 'delete' => false]],
            'view + edit' => [['media.view', 'media.edit'], ['create' => false, 'edit' => true, 'delete' => false]],
            'view + delete' => [['media.view', 'media.delete'], ['create' => false, 'edit' => false, 'delete' => true]],
            'all' => [['media.view', 'media.create', 'media.edit', 'media.delete'], ['create' => true, 'edit' => true, 'delete' => true]],
        ];

        foreach ($cases as $label => [$abilities, $expected]) {
            $html = $this->page($this->admin(abilities: $abilities));

            $this->assertSame($expected, $this->config($html)['can'], $label);
            $this->assertSame($expected['create'], str_contains($html, 'id="media-upload-button"'), "{$label}: upload button");
            $this->assertSame($expected['create'], str_contains($html, 'id="media-dropzone"'), "{$label}: drop zone");
            $this->assertSame($expected['edit'], str_contains($html, 'id="media-save"'), "{$label}: save");
            $this->assertSame(! $expected['edit'], (bool) preg_match('#id="media-field-alt"[^>]*readonly#', $html), "{$label}: read-only fields");
            $this->assertSame($expected['delete'], str_contains($html, 'id="media-delete"'), "{$label}: delete");
            $this->assertSame($expected['delete'], str_contains($html, 'id="media-bulk-delete"'), "{$label}: bulk delete");
        }

        $super = $this->config($this->page($this->admin(superAdmin: true)))['can'];
        $this->assertSame(['create' => true, 'edit' => true, 'delete' => true], $super);
    }

    public function test_hidden_actions_are_still_refused_by_the_server(): void
    {
        $media = $this->storedMedia();
        $viewer = $this->admin(abilities: ['media.view']);
        $this->actingAs($viewer, 'admin');

        $this->postJson(route('media-library.media.store'), ['files' => [UploadedFile::fake()->image('a.png')]])->assertForbidden();
        $this->putJson(route('media-library.media.update', $media->id), ['alt' => 'x'])->assertForbidden();
        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertForbidden();
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]])->assertForbidden();

        $this->assertModelExists($media);
        $this->assertNull($media->fresh()->alt);
    }

    public function test_the_page_is_refused_without_media_view(): void
    {
        foreach ([[], ['media.create', 'media.edit', 'media.delete'], ['categories.view', 'settings.view']] as $abilities) {
            $this->actingAs($this->admin(abilities: $abilities), 'admin')->get(route('media-library.page'))->assertForbidden();
        }

        $this->get(route('media-library.page'))->assertForbidden();
    }

    // ---------------------------------------------------------------- sidebar

    public function test_the_sidebar_lists_the_library_in_the_system_group_for_media_view(): void
    {
        $this->seedTranslations();

        foreach ([$this->admin(superAdmin: true), $this->admin(abilities: ['media.view'])] as $admin) {
            $system = $this->systemGroup($this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.home'))->assertOk()->getContent());

            $this->assertStringContainsString('href="'.route('media-library.page').'"', $system);
            $this->assertStringContainsString('<i class="ph-duotone ph-images" aria-hidden="true"></i>', $system);
            $this->assertStringContainsString('>مكتبة الوسائط</span>', $system);
        }

        foreach ([[], ['media.create', 'media.edit', 'media.delete'], ['settings.view']] as $abilities) {
            $html = $this->actingAs($this->admin(abilities: $abilities), 'admin')->get(route('dashboard.home'))->assertOk()->getContent();

            $this->assertStringNotContainsString('href="'.route('media-library.page').'"', $html);
        }

        $en = $this->actingAs($this->admin(superAdmin: true), 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.home'))->getContent();
        $this->assertStringContainsString('>Media Library</span>', $this->systemGroup($en));
    }

    public function test_the_sidebar_entry_is_current_on_the_library_page(): void
    {
        $this->seedTranslations();
        $html = $this->page($this->admin(superAdmin: true));

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('media-library.page'), '#').'" class="pc-link"\s+aria-current="page"#', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    // ---------------------------------------------------------------- list data

    public function test_the_list_carries_the_uploader_name_only(): void
    {
        $uploader = $this->admin(abilities: ['media.create']);
        $mine = $this->storedMedia(['uploader_id' => $uploader->id]);
        $orphan = $this->storedMedia(['uploader_id' => 999999]);
        $none = $this->storedMedia();

        $response = $this->actingAs($this->admin(abilities: ['media.view']), 'admin')
            ->getJson(route('media-library.media.index'))
            ->assertOk()
            ->assertJsonPath('total', 3);

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame($uploader->name, $byId[$mine->id]['uploader_name']);
        $this->assertNull($byId[$orphan->id]['uploader_name']);
        $this->assertNull($byId[$none->id]['uploader_name']);
        $this->assertStringNotContainsString($uploader->email, $response->getContent());
        $this->assertSame(['current_page', 'data', 'first_page_url', 'from', 'last_page', 'last_page_url', 'links', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total'], array_keys($response->json()));
    }

    public function test_the_page_lists_images_only(): void
    {
        $image = $this->storedMedia();
        $this->storedMedia(['file_type' => 'document', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf']);

        $this->actingAs($this->admin(abilities: ['media.view']), 'admin')
            ->getJson(route('media-library.media.index', ['type' => 'image', 'per_page' => 24]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $image->id);

        $this->assertStringContainsString("type: 'image'", file_get_contents(resource_path('js/admin-media-library.js')));
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/gif,image/webp"', file_get_contents(resource_path('views/vendor/media-library/media.blade.php')));
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminMediaTranslationSeeder::class);
    }

    private function page(Admin $admin, string $locale = 'ar'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('media-library.page'))->assertOk()->getContent();
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="media-library-config">(.*?)</script>#s', $html, $match), 'Page config not rendered.');

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function systemGroup(string $html): string
    {
        $this->assertSame(1, preg_match('#<span class="admin-nav-group-title" id="admin-nav-group-\d+">(?:النظام|System)</span>.*?</ul>#su', $html, $match), 'System group not rendered.');

        return $match[0];
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }

    private function admin(bool $superAdmin = false, array $abilities = []): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => $superAdmin]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function storedMedia(array $attributes = []): Media
    {
        $name = uniqid('', true).'.png';
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, 'file');

        return Media::create($attributes + [
            'file_name' => $name,
            'file_original_name' => $name,
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }
}
