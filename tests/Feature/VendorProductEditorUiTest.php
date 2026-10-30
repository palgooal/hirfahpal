<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * VPM-01A: the vendor product editor's image section in the admin product gallery's look, under vendor-scoped classes
 * (the vendor shell has no Bootstrap), and every editor text translated. The image actions, the six-image limit and
 * the requests are unchanged; they stay covered by VendorProductEditorGalleryTest and the VendorProductImage* tests.
 */
class VendorProductEditorUiTest extends TestCase
{
    use RefreshDatabase;

    private const VIEW = 'views/vendor-dashboard/resources.blade.php';

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    /** Bootstrap utilities the vendor shell does not define (no bootstrap.min.css there). */
    private const BOOTSTRAP_ONLY = ['d-flex', 'd-inline-flex', 'justify-content-', 'align-items-', 'flex-column', 'object-fit-cover', 'visually-hidden', 'form-text', 'text-danger'];

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- markup

    public function test_the_image_section_has_the_admin_gallery_structure_and_keeps_the_script_hooks(): void
    {
        $section = $this->imageSection($this->page('en'));

        $this->assertMatchesRegularExpression('/<fieldset class="admin-cat-form-section vendor-product-gallery" data-product-gallery aria-busy="false">/', $section);
        $this->assertMatchesRegularExpression('/class="vendor-product-gallery-bar">\s*<p class="vendor-product-gallery-count" data-product-gallery-count><\/p>/', $section);
        $this->assertSame(2, substr_count($section, 'class="vendor-product-gallery-group"'));
        $this->assertMatchesRegularExpression('/<h3 class="vendor-product-gallery-label" id="vendor-product-main-label">Main product image<\/h3>.*data-product-primary-open.*<div class="vendor-product-gallery-box" data-product-primary-image><\/div>/s', $section);
        $this->assertMatchesRegularExpression('/<h3 class="vendor-product-gallery-label" id="vendor-product-gallery-label">Product gallery<\/h3>.*data-product-gallery-open data-limited="true".*<div class="vendor-product-gallery-box" data-product-gallery-images><\/div>/s', $section);
        $this->assertMatchesRegularExpression('/role="status" aria-live="polite" data-product-gallery-status/', $section);
        $this->assertMatchesRegularExpression('/class="vendor-product-error" role="alert" data-product-gallery-error hidden/', $section);

        // VPM-01B: the chooser is no longer inline; it is the picker dialog after the form (VendorProductPickerTest).
        $this->assertStringNotContainsString('data-product-media-', $section);
    }

    public function test_the_image_section_and_its_script_use_no_bootstrap_only_utilities(): void
    {
        $section = $this->imageSection(file_get_contents(resource_path(self::VIEW)));
        $script = file_get_contents(base_path(self::SCRIPT));

        foreach (self::BOOTSTRAP_ONLY as $utility) {
            $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b'.preg_quote($utility, '/').'/', $section, "view: {$utility}");
            $this->assertDoesNotMatchRegularExpression("/className = '[^']*\\b".preg_quote($utility, '/').'/', $script, "script: {$utility}");
        }
        $this->assertStringNotContainsString("'border rounded", $script);
    }

    public function test_the_page_is_rtl_in_arabic_and_ltr_in_english(): void
    {
        $this->assertMatchesRegularExpression('/<html lang="ar" dir="rtl"/', $this->page('ar'));
        $this->assertMatchesRegularExpression('/<html lang="en" dir="ltr"/', $this->page('en'));
        $this->assertStringContainsString('name="sku" maxlength="255" dir="ltr"', $this->page('ar'), 'An SKU reads left to right in Arabic too.');
    }

    // ---------------------------------------------------------------- translations

    public function test_the_editor_reads_in_arabic(): void
    {
        $html = $this->page('ar');
        $section = $this->imageSection($html);

        foreach (['إضافة منتج', 'تفاصيل المنتج', 'السعر والمخزون والشحن', 'الاسم', 'التصنيف', 'بدون تصنيف', 'رمز المنتج (SKU)', 'المخزون', 'وصف مختصر', 'السعر الأساسي', 'سعر التخفيض (اختياري)', 'يجب أن يكون أقل من السعر الأساسي.', 'حد انخفاض المخزون', 'الوزن', 'حفظ', 'إلغاء'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringContainsString('<legend class="admin-cat-form-legend">صور المنتج</legend>', $section);
        foreach (['اختر حتى 6 صور من مكتبة الوسائط الخاصة بك. تظهر الصورة الرئيسية أولًا في المتجر.', 'الصورة الرئيسية للمنتج', 'تظهر أولًا في المتجر.', 'اختر من الوسائط', 'معرض المنتج', 'اختر صورًا إضافية من مكتبة الوسائط الخاصة بك.', 'أضف صورة إلى المعرض'] as $text) {
            $this->assertStringContainsString($text, $section, $text);
        }
        // VPM-01B: the chooser's texts are in the picker dialog, after the form.
        foreach (['اختر صورة من الوسائط', 'ارفع صورة جديدة', 'إغلاق'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        foreach (['Product details', 'Price, stock and shipping', 'Product images', 'Main product image', 'Product gallery', 'Choose from media', 'Add gallery image', 'Upload new image', 'Must be lower than the regular price.'] as $english) {
            $this->assertStringNotContainsString($english, $html, $english);
        }

        $editor = $this->editorMessages($html);
        $this->assertSame('تعديل المنتج', $editor['edit']);
        $this->assertSame('هل تريد حذف هذا المنتج؟ لا يمكن التراجع عن ذلك.', $editor['confirmDelete']);
        $this->assertSame('تعذّر حفظ المنتج. راجع هذه التفاصيل:', $editor['saveInvalid']);
        $gallery = $this->galleryMessages($html);
        $this->assertSame('الصورة :position', $gallery['position']);
        $this->assertSame('الصورة الرئيسية', $gallery['mainBadge']);
    }

    public function test_the_editor_reads_in_english(): void
    {
        $html = $this->page('en');

        foreach (['Add product', 'Product details', 'Price, stock and shipping', 'SKU', 'Regular price', 'Must be lower than the regular price.', 'Product images', 'Main product image', 'Choose from media', 'Product gallery', 'Add gallery image', 'Choose an image from media', 'Upload new image', 'Save', 'Cancel'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertSame([
            'add' => 'Add product',
            'edit' => 'Edit product',
            'noCategory' => 'No category',
            'confirmDelete' => 'Delete this product? This cannot be undone.',
            'deleteFailed' => 'The product could not be deleted.',
            'loadFailed' => 'The product could not be loaded.',
            'saveFailed' => 'The product could not be saved.',
            'saveInvalid' => 'The product could not be saved. Check these details:',
        ], $this->editorMessages($html));
    }

    public function test_every_editor_text_is_seeded_in_both_languages_with_the_view_default(): void
    {
        $editor = $this->editorBlock(file_get_contents(resource_path(self::VIEW)));
        preg_match_all("/\\bt\\('(vendor\\.[A-Za-z_]+)', '((?:[^'\\\\]|\\\\.)*)'\\)/", $editor, $calls, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(20, count($calls));

        foreach ($calls as [, $key, $default]) {
            $values = VendorDashboardTranslationSeeder::TRANSLATIONS[$key] ?? null;
            $this->assertNotNull($values, $key);
            $this->assertSame(stripslashes($default), $values['en'], "{$key}: the English value is the default in the view.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
        }
    }

    public function test_the_script_has_no_hard_coded_wording_and_every_editor_text_it_reads_is_given(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
        foreach (["'Add product'", "'Edit product'", "'No category'", 'Delete this product', 'could not be deleted', 'could not be loaded', 'could not be saved'] as $english) {
            $this->assertStringNotContainsString($english, $script, $english);
        }

        $given = $this->editorMessages($this->page('en'));
        preg_match_all('/texts\.([a-zA-Z]+)/', $script, $used);
        foreach (array_unique($used[1]) as $name) {
            $this->assertArrayHasKey($name, $given, "texts.{$name}");
        }
    }

    public function test_rendering_the_editor_never_creates_translation_rows(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $before = TranslationValue::count();

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.products.page'))->assertOk();
        }

        $this->assertSame($before, TranslationValue::count());
    }

    // ---------------------------------------------------------------- styles

    public function test_the_gallery_styles_are_vendor_scoped_logical_and_responsive(): void
    {
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));
        $start = strpos($css, '/* VPM-01A');
        $this->assertNotFalse($start);
        $block = substr($css, $start, strpos($css, "\nhtml.vendor-shell body,", $start) - $start);

        preg_match_all('/(?:^|\})\s*([^{}@\/][^{}]*?)\s*\{/', preg_replace('#/\*.*?\*/#s', '', $block), $rules);
        $this->assertGreaterThan(30, count($rules[1]));
        foreach ($rules[1] as $selectors) {
            if (str_starts_with(trim($selectors), '@')) {
                continue;
            }
            foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                $this->assertStringStartsWith('html.vendor-shell .vendor-product-', $selector);
            }
        }
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w])(left|right|margin-left|margin-right|padding-left|padding-right)\s*:/', $block, 'Logical properties only, so RTL and LTR mirror on their own.');
        $this->assertStringContainsString('@media (max-width: 575.98px)', $block);
        $this->assertStringContainsString('grid-template-columns: 5.5rem minmax(0, 1fr)', $block, 'The admin gallery card.');
        $this->assertStringContainsString('object-fit: cover', $block);
        $this->assertStringNotContainsString(':root', $block);
        $this->assertStringNotContainsString('@theme', $block);
    }

    // ---------------------------------------------------------------- helpers

    private function page(string $locale): string
    {
        return $this->actingAs(Vendor::factory()->approved()->create(), 'vendor')->withSession(['locale' => $locale])
            ->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
    }

    private function imageSection(string $html): string
    {
        $this->assertSame(1, preg_match('#<fieldset class="admin-cat-form-section vendor-product-gallery".*?</fieldset>#s', $html, $match));

        return $match[0];
    }

    private function editorBlock(string $view): string
    {
        $this->assertSame(1, preg_match("#@if \(\\\$resource === 'products'\)\s*<div class=\"col-span-12 admin-media.*?@endif#s", $view, $match));

        return $match[0];
    }

    /** @return array<string, string> */
    private function editorMessages(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-product-editor-messages>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function galleryMessages(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-product-gallery-messages>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
