<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminMediaTranslationSeeder;
use Database\Seeders\AdminVendorTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P4: the read-only Vendor media page (dashboard.vendor-media.index) and the Admin /
 * Vendor media switcher. The page needs media.view and vendors.view, renders inside the admin shell in both
 * directions, reads the P3 endpoints, offers no upload, edit, delete or selection, and never creates translation
 * rows. The library page gains the switcher only for admins who may open both sections; nothing else changes.
 */
class AdminVendorMediaPageTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/admin-vendor-media.js';

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

    // ---------------------------------------------------------------- access

    public function test_an_admin_with_both_abilities_and_a_super_admin_open_the_page(): void
    {
        foreach ([$this->admin(abilities: ['media.view', 'vendors.view']), $this->admin(superAdmin: true)] as $admin) {
            $html = $this->page($admin);

            $this->assertStringContainsString('id="admin-vendor-media"', $html);
            $this->assertStringContainsString('class="pc-sidebar', $html, 'Inside the admin shell.');
        }
    }

    public function test_both_abilities_are_required(): void
    {
        foreach ([[], ['media.view'], ['vendors.view'], ['media.view', 'media.edit', 'media.delete', 'vendors.edit'], ['vendors.view', 'vendors.edit']] as $abilities) {
            $this->actingAs($this->admin(abilities: $abilities), 'admin')
                ->get(route('dashboard.vendor-media.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_and_vendor_sessions_cannot_open_the_page(): void
    {
        $this->get(route('dashboard.vendor-media.index'))->assertRedirect();

        $vendor = Vendor::factory()->approved()->create();
        $response = $this->actingAs($vendor, 'vendor')->get(route('dashboard.vendor-media.index'));
        $response->assertRedirect();
        $this->assertStringNotContainsString('admin-vendor-media', (string) $response->getContent());
    }

    public function test_the_route_is_a_single_read_only_page_in_the_admin_group(): void
    {
        $route = app('router')->getRoutes()->getByName('dashboard.vendor-media.index');

        $this->assertSame('dashboard/vendor-media', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth:admin', $route->gatherMiddleware());

        $super = $this->admin(superAdmin: true);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->actingAs($super, 'admin')->{$method}(route('dashboard.vendor-media.index'))->assertMethodNotAllowed();
        }
    }

    // ---------------------------------------------------------------- shell, direction, title

    public function test_direction_language_and_title_follow_the_active_language(): void
    {
        $this->seedTranslations();
        $admin = $this->admin(superAdmin: true);

        $ar = $this->page($admin, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar"[^>]* dir="rtl"#', $ar);
        $this->assertStringContainsString('<h1 class="admin-header-title">وسائط البائعين</h1>', $ar);
        $this->assertStringContainsString('<title>وسائط البائعين | ', $ar);
        $this->assertStringContainsString('تصفّح الصور التي رفعها كل بائع. هذا العرض للاطلاع فقط.', $ar);
        $this->assertStringContainsString('للاطلاع فقط', $ar);

        $en = $this->page($admin, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en"[^>]* dir="ltr"#', $en);
        $this->assertStringContainsString('<h1 class="admin-header-title">Vendor media</h1>', $en);
        $this->assertStringContainsString('Browse the images each vendor uploaded. This view is read-only.', $en);
    }

    public function test_the_page_loads_its_own_script_only(): void
    {
        $html = $this->page($this->admin(superAdmin: true));

        $this->assertStringContainsString('resources/js/admin-vendor-media.js', $html);
        $this->assertStringNotContainsString('resources/js/admin-media-library.js', $html);
        $this->assertStringNotContainsString('admin-media-picker.js', $html);
    }

    public function test_an_unseeded_language_shows_the_english_defaults_and_creates_no_rows(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $admin = $this->admin(superAdmin: true);

        $html = $this->page($admin, 'ar');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('media-library.page'))->assertOk();

        $this->assertStringContainsString('Vendor folders', $html);
        $this->assertSame([], $this->rowsStartingWith(['dashboard.Media_', 'dashboard.Vendors_']), 'Neither page auto-creates its keys.');
    }

    // ---------------------------------------------------------------- config

    public function test_the_config_carries_the_p3_endpoints_and_the_translated_messages(): void
    {
        $this->seedTranslations();
        $config = $this->config($this->page($this->admin(superAdmin: true), 'ar'));

        $this->assertSame(['folders', 'media'], array_keys($config['urls']));
        $this->assertSame(route('dashboard.vendor-media.folders'), $config['urls']['folders']);
        $this->assertSame(route('dashboard.vendor-media.media', ['vendor' => '__ID__']), $config['urls']['media']);
        $this->assertSame(['folders' => 24, 'media' => 40], $config['perPage']);
        $this->assertSame('ar', $config['locale']);
        $this->assertArrayNotHasKey('csrf', $config, 'A read-only page needs no token.');

        $this->assertSame('محظور', $config['statuses']['account']['blocked']['label']);
        $this->assertSame('is-suspended', $config['statuses']['account']['blocked']['tone']);
        $this->assertSame('معتمد', $config['statuses']['approval']['approved']['label']);
        $this->assertSame('بلا ملف متجر', $config['statuses']['noProfile']);
        $this->assertSame('عدد البائعين: :count', $config['messages']['vendorsCount']);
        $this->assertSame('تعذّر العثور على مجلد هذا البائع.', $config['messages']['notFound']);

        $json = json_encode($config);
        $this->assertStringNotContainsString('vendors/media', $json, 'No storage path.');
        $this->assertStringNotContainsString('media-library', $json, 'No admin library endpoint.');
    }

    // ---------------------------------------------------------------- read-only

    public function test_the_page_has_no_mutation_controls(): void
    {
        $html = $this->page($this->admin(superAdmin: true));
        $page = $this->between($html, 'id="admin-vendor-media"', 'id="vendor-media-config"');

        foreach (['media-upload-button', 'media-upload-input', 'media-dropzone', 'media-delete', 'media-bulk-delete', 'media-save', 'media-selection', 'media-copy-link', 'media-details-form'] as $id) {
            $this->assertStringNotContainsString('id="'.$id.'"', $page, $id);
        }

        $this->assertStringNotContainsString('type="file"', $page);
        $this->assertStringNotContainsString('<textarea', $page);
        $this->assertDoesNotMatchRegularExpression('#<form[^>]*method=#i', $page);
        $this->assertSame(1, substr_count($page, 'type="checkbox"'), 'Only the "vendors with images" filter.');
        $this->assertStringContainsString('id="vendor-media-has-media"', $page);
        $this->assertStringNotContainsString('data-media-picker', $page);
        $this->assertSame(2, substr_count($page, 'role="search"'), 'The two forms only search.');
    }

    public function test_the_details_dialog_is_read_only_with_the_p3_fields(): void
    {
        $html = $this->page($this->admin(superAdmin: true));
        $dialog = $this->between($html, '<dialog', '</dialog>');

        $this->assertStringContainsString('id="vendor-media-details-facts"', $dialog);
        $this->assertStringContainsString('id="vendor-media-open-original"', $dialog);
        $this->assertStringContainsString('rel="noopener noreferrer"', $dialog);
        $this->assertDoesNotMatchRegularExpression('#<input|<textarea|<select|type="submit"#', $dialog);
    }

    // ---------------------------------------------------------------- switcher and sidebar

    public function test_the_library_page_shows_the_switcher_only_with_both_abilities(): void
    {
        $this->seedTranslations();

        foreach ([$this->admin(abilities: ['media.view', 'vendors.view']), $this->admin(superAdmin: true)] as $admin) {
            $html = $this->library($admin);
            $nav = $this->between($html, '<nav class="admin-vendor-media-sections"', '</nav>');

            $this->assertStringContainsString('aria-label="أقسام مكتبة الوسائط"', $nav);
            $this->assertMatchesRegularExpression('#href="'.preg_quote(route('media-library.page'), '#').'" class="btn btn-sm btn-primary"\s+aria-current="page"#', $nav);
            $this->assertMatchesRegularExpression('#href="'.preg_quote(route('dashboard.vendor-media.index'), '#').'" class="btn btn-sm btn-light-secondary"\s*>#', $nav);
            $this->assertStringContainsString('وسائط الإدارة', $nav);
            $this->assertStringContainsString('وسائط البائعين', $nav);
        }

        $viewer = $this->library($this->admin(abilities: ['media.view']));
        $this->assertStringNotContainsString('data-media-sections', $viewer);
        $this->assertStringNotContainsString(route('dashboard.vendor-media.index'), $viewer, 'No way into a section the admin cannot open.');
    }

    public function test_the_vendor_page_marks_its_own_section_and_the_sidebar_entry(): void
    {
        $this->seedTranslations();
        $html = $this->page($this->admin(abilities: ['media.view', 'vendors.view']));
        $nav = $this->between($html, '<nav class="admin-vendor-media-sections"', '</nav>');

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('dashboard.vendor-media.index'), '#').'" class="btn btn-sm btn-primary"\s+aria-current="page"#', $nav);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('media-library.page'), '#').'" class="btn btn-sm btn-light-secondary"\s*>#', $nav);

        // The sidebar keeps one Media Library entry, current on this section too.
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('media-library.page'), '#').'" class="pc-link"\s+aria-current="page"#', $html);
        $this->assertSame(2, substr_count($html, 'aria-current="page"'), 'The sidebar entry and the switcher link.');
        $this->assertSame(1, substr_count($html, 'class="pc-link"  aria-current="page"') + substr_count($html, 'class="pc-link" aria-current="page"'));
    }

    // ---------------------------------------------------------------- the library is unchanged

    public function test_the_library_page_and_its_endpoints_still_work(): void
    {
        $admin = $this->admin(superAdmin: true);
        $html = $this->library($admin);

        foreach (['media-upload-button', 'media-dropzone', 'media-bulk-delete', 'media-save', 'media-delete', 'media-library-config'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, $id);
        }

        $this->assertStringContainsString('resources/js/admin-media-library.js', $html);

        $own = $this->storedMedia('media/2026/10/'.uniqid().'.png');
        $vendorMedia = $this->storedMedia('vendors/media/7/'.uniqid().'.png');
        $ids = array_column($this->actingAs($admin, 'admin')->getJson(route('media-library.media.index'))->assertOk()->json('data'), 'id');

        $this->assertSame([$own->id], $ids, 'Admin media only, as before.');
        $this->assertNotContains($vendorMedia->id, $ids);
    }

    // ---------------------------------------------------------------- the script

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-vendor-media.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output, 'The JS tests must actually run.');
    }

    public function test_the_script_holds_no_wording_no_html_injection_and_no_writes(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Every text comes from the translated page config.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $source, 'DOM APIs only.');
        $this->assertDoesNotMatchRegularExpression("/method:\\s*'(?:POST|PUT|PATCH|DELETE)'/i", $source, 'The page only reads.');
        $this->assertStringNotContainsString('body.message', $source, 'A server message is never shown as is.');
        $this->assertStringContainsString("Accept: 'application/json'", $source);

        // Each message it shows is a key of the config the page provides.
        preg_match_all('/messages\.([a-zA-Z]+)/', $source, $matches);
        $view = file_get_contents(resource_path('views/dashboard/vendor-media/index.blade.php'));

        $this->assertGreaterThan(15, count(array_unique($matches[1])));

        foreach (array_unique($matches[1]) as $key) {
            $this->assertMatchesRegularExpression("/'{$key}' => \\\$vmText\\(/", $view, "messages.{$key} must be provided by the page.");
        }
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminMediaTranslationSeeder::class);
        $this->seed(AdminVendorTranslationSeeder::class);
    }

    private function page(Admin $admin, string $locale = 'ar'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.vendor-media.index'))->assertOk()->getContent();
    }

    private function library(Admin $admin, string $locale = 'ar'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('media-library.page'))->assertOk()->getContent();
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="vendor-media-config">(.*?)</script>#s', $html, $match), 'Page config not rendered.');

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }

    /** @return list<string> translation rows whose key starts with one of the prefixes (matched in PHP) */
    private function rowsStartingWith(array $prefixes): array
    {
        return TranslationValue::query()->orderBy('key')->pluck('key')
            ->filter(fn (string $key) => collect($prefixes)->contains(fn (string $prefix) => str_starts_with($key, $prefix)))
            ->values()->all();
    }

    private function admin(bool $superAdmin = false, array $abilities = []): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => $superAdmin]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function storedMedia(string $path): Media
    {
        Storage::disk('public')->put($path, 'file');

        return Media::create([
            'file_name' => basename($path),
            'file_original_name' => basename($path),
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }

    private function node(): string
    {
        foreach (['node', 'node.exe'] as $candidate) {
            $found = trim((string) shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ').$candidate.' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));

            if ($found !== '') {
                return strtok($found, "\r\n");
            }
        }

        $this->markTestSkipped('Node.js is not available.');
    }
}
