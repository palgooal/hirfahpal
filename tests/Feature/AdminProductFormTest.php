<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminProductTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AD-UI-PROD-M7-FIX-01: the admin product create / edit form. F-2 (an inactive current category is offered, marked,
 * selected and kept by a save), the four sections, linked labels, field errors with aria-invalid / aria-describedby
 * and translated field names, old values after a failed save, the M0-FIX stock basis, the read-only suspension and
 * archive notes, Cancel / Back, LTR fields, both languages, and no translation row written by the form.
 */
class AdminProductFormTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $active;

    private Category $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create();
        $this->active = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->inactive = Category::create(['name' => 'Old crafts', 'slug' => 'old-crafts', 'is_active' => false]);
    }

    // ---------------------------------------------------------------- create and edit

    public function test_a_product_is_created_from_the_form(): void
    {
        $admin = $this->admin(super: true);
        $values = $this->formValues($this->page($admin, route('dashboard.products.create')));

        $this->assertSame('draft', $values['status'], 'A new product starts as a draft.');
        $this->assertSame('', $values['category_id']);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.store'), [
            'name' => 'Clay jug', 'price' => '12.50', 'stock_quantity' => '4', 'category_id' => $this->active->id, 'weight' => '1500', 'weight_unit' => 'g',
        ] + $values)->assertRedirect(route('dashboard.products.index'))->assertSessionHas('success');

        $product = Product::sole();
        $this->assertSame(['Clay jug', 'clay-jug', '12.50', 4, $this->active->id, 'draft', 1, false], [
            $product->name, $product->slug, $product->price, $product->stock_quantity, $product->category_id, $product->status, $product->low_stock_threshold, $product->is_featured,
        ]);
        $this->assertSame('g', $product->weight_unit);
    }

    public function test_the_edit_form_submits_the_product_unchanged_with_its_stock_basis(): void
    {
        $product = $this->product(['category_id' => $this->active->id, 'sku' => 'JUG-1', 'compare_at_price' => 15, 'weight' => 1.25, 'is_featured' => true, 'short_description' => 'Short', 'description' => "Line 1\nLine 2"]);
        $admin = $this->admin(super: true);
        $html = $this->page($admin, route('dashboard.products.edit', $product));

        $this->assertMatchesRegularExpression('/name="stock_quantity_original" value="10"/', $html);
        $this->assertMatchesRegularExpression('/name="stock_version" value="'.preg_quote(ProductManagementController::stockVersion($product), '/').'"/', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);

        $values = $this->formValues($html);
        $this->assertSame(['Clay jug', 'clay-jug', (string) $this->vendor->id, (string) $this->active->id, 'JUG-1', '10.00', '15.00', '10', '3', '1.25', '', 'Short', "Line 1\nLine 2", 'active', '1'], [
            $values['name'], $values['slug'], $values['vendor_id'], $values['category_id'], $values['sku'], $values['price'], $values['compare_at_price'],
            $values['stock_quantity'], $values['low_stock_threshold'], $values['weight'], $values['weight_unit'], $values['short_description'], $values['description'], $values['status'], $values['is_featured'],
        ]);

        $before = $product->fresh()->only(['name', 'slug', 'vendor_id', 'category_id', 'sku', 'price', 'compare_at_price', 'stock_quantity', 'low_stock_threshold', 'weight', 'weight_unit', 'short_description', 'description', 'status', 'is_featured', 'published_at']);
        $this->actingAs($admin, 'admin')->put(route('dashboard.products.update', $product), $values)->assertSessionHasNoErrors()->assertRedirect(route('dashboard.products.index'));
        $this->assertEquals($before, $product->fresh()->only(array_keys($before)));
    }

    // ---------------------------------------------------------------- F-2: inactive category

    public function test_an_inactive_current_category_is_offered_selected_and_marked_and_no_other_inactive_one(): void
    {
        $other = Category::create(['name' => 'Retired', 'slug' => 'retired', 'is_active' => false]);
        $legacy = $this->product(['category_id' => $this->inactive->id]);
        $admin = $this->admin(super: true);

        $html = $this->page($admin, route('dashboard.products.edit', $legacy));
        $this->assertMatchesRegularExpression('#<option value="'.$this->inactive->id.'"\s+selected[^>]*>Old crafts \(inactive\)</option>#', $html);
        $this->assertStringContainsString('id="product-category-hint">The current category is inactive. It is kept when you save; choose another one to move the product.</p>', $html);
        $this->assertMatchesRegularExpression('#id="product-category"[^>]*aria-describedby="product-category-hint"#', $html);
        $this->assertStringNotContainsString('<option value="'.$other->id.'"', $html, 'No other inactive category.');
        $this->assertStringContainsString('<option value="'.$this->active->id.'"', $html);

        foreach ([route('dashboard.products.create'), route('dashboard.products.edit', $this->product(['name' => 'Plain', 'slug' => 'plain', 'category_id' => $this->active->id]))] as $url) {
            $page = $this->page($admin, $url);
            $this->assertStringNotContainsString('<option value="'.$this->inactive->id.'"', $page);
            $this->assertStringNotContainsString('product-category-hint', $page);
        }
    }

    /** @return array<string, array{array<string, string>}> */
    public static function otherFieldChanges(): array
    {
        return ['the price' => [['price' => '14.75']], 'the description' => [['description' => 'A new description.']], 'the name' => [['name' => 'Renamed jug']]];
    }

    /** @param array<string, string> $change */
    #[DataProvider('otherFieldChanges')]
    public function test_saving_another_field_keeps_the_inactive_category(array $change): void
    {
        $legacy = $this->product(['category_id' => $this->inactive->id]);
        $admin = $this->admin(super: true);
        $values = $this->formValues($this->page($admin, route('dashboard.products.edit', $legacy)));

        $this->actingAs($admin, 'admin')->put(route('dashboard.products.update', $legacy), $change + $values)->assertSessionHasNoErrors();

        $after = $legacy->fresh();
        $this->assertSame($this->inactive->id, $after->category_id, 'The inactive category is kept.');
        $this->assertSame(array_values($change)[0], (string) $after->{array_key_first($change)});
    }

    public function test_the_inactive_category_stays_selected_after_a_failed_save(): void
    {
        $legacy = $this->product(['category_id' => $this->inactive->id]);
        $admin = $this->admin(super: true);
        $values = $this->formValues($this->page($admin, route('dashboard.products.edit', $legacy)));

        $this->actingAs($admin, 'admin')->put(route('dashboard.products.update', $legacy), ['price' => '-1'] + $values)->assertSessionHasErrors('price');

        $html = $this->page($admin, route('dashboard.products.edit', $legacy));
        $this->assertSame((string) $this->inactive->id, $this->formValues($html)['category_id']);
        $this->assertSame($this->inactive->id, $legacy->fresh()->category_id);
    }

    // ---------------------------------------------------------------- labels, errors and accessibility

    public function test_every_field_has_a_linked_label_and_the_required_ones_are_marked(): void
    {
        $html = $this->page($this->admin(super: true), route('dashboard.products.edit', $this->product()));
        $xpath = $this->xpath($html);

        $fields = $xpath->query('//form[@novalidate]//*[self::input[not(@type="hidden")] or self::select or self::textarea]');
        $this->assertSame(16, $fields->length);
        foreach ($fields as $field) {
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id, $field->getAttribute('name'));
            $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length, "{$id} has one label.");
        }

        foreach (['product-name', 'product-vendor', 'product-price', 'product-stock', 'product-status'] as $id) {
            $this->assertSame('true', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('aria-required'), $id);
            $this->assertStringContainsString('(Required)', $xpath->query('//label[@for="'.$id.'"]')->item(0)->textContent);
        }
        $this->assertSame(4, $xpath->query('//form//fieldset/legend')->length);
        $this->assertSame(['Basic details', 'Price and stock', 'Description and details', 'Status and settings'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//form//fieldset/legend'))));
    }

    public function test_errors_are_shown_at_their_fields_with_translated_names_and_the_typed_values_are_kept(): void
    {
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->post(route('dashboard.products.store'), [
            'vendor_id' => $this->vendor->id, 'name' => '', 'slug' => 'a b', 'price' => '-5', 'compare_at_price' => '1', 'stock_quantity' => 'x', 'status' => 'active', 'short_description' => 'Kept text',
        ])->assertRedirect(route('dashboard.products.create'));
        $html = $this->page($admin, route('dashboard.products.create'));

        foreach (['name' => 'product-name', 'slug' => 'product-slug', 'price' => 'product-price', 'stock_quantity' => 'product-stock'] as $field => $id) {
            $this->assertMatchesRegularExpression('#id="'.$id.'"[^>]*aria-invalid="true"#', $html, $field);
            $this->assertMatchesRegularExpression('#id="'.$id.'"[^>]*aria-describedby="[^"]*'.$id.'-error"#', $html, $field);
            $this->assertStringContainsString('<p class="admin-cat-form-error" id="'.$id.'-error">', $html, $field);
            $this->assertStringContainsString('<a href="#'.$id.'">', $html, "{$field} in the summary");
        }
        $this->assertStringContainsString('The product name field is required.', $html);
        $this->assertStringContainsString('The stock quantity field must be an integer.', $html);
        // The page's own content (the layout's raw list stays in the markup, hidden by the page styles).
        $content = $this->xpath($html)->query('//*[@id="admin-product-form"]')->item(0)->textContent;
        $this->assertStringContainsString('Product name: The product name field is required.', preg_replace('/\s+/', ' ', $content));
        $this->assertStringNotContainsString('stock_quantity', $content);
        $this->assertStringNotContainsString('compare_at_price', $content);

        $values = $this->formValues($html);
        $this->assertSame(['a b', '-5', '1', 'x', 'Kept text', 'active'], [$values['slug'], $values['price'], $values['compare_at_price'], $values['stock_quantity'], $values['short_description'], $values['status']]);
        $this->assertSame(0, Product::count());
    }

    public function test_the_layout_breadcrumb_and_raw_error_list_are_hidden_by_the_page_styles(): void
    {
        $css = file_get_contents(resource_path('css/admin-dashboard.css'));

        $this->assertStringContainsString("html.admin-shell .pc-content:has(#admin-product-form) > .page-header,\nhtml.admin-shell .pc-content:has(#admin-product-form) > .row > .alert-danger {\n  display: none;", $css);
        $this->assertStringContainsString('id="admin-product-form"', $this->page($this->admin(super: true), route('dashboard.products.create')));
    }

    public function test_codes_and_numbers_are_left_to_right(): void
    {
        $html = $this->page($this->admin(super: true), route('dashboard.products.edit', $this->product()), 'ar');

        foreach (['product-slug', 'product-sku', 'product-price', 'product-compare-price', 'product-stock', 'product-low-stock', 'product-weight'] as $id) {
            $this->assertMatchesRegularExpression('#id="'.$id.'"[^>]*dir="ltr"#', $html, $id);
        }
        $this->assertMatchesRegularExpression('#id="product-name"[^>]*dir="auto"#', $html);
    }

    // ---------------------------------------------------------------- moderation notes, Cancel and Back

    public function test_suspension_and_archive_are_shown_read_only_and_a_save_keeps_them(): void
    {
        $admin = $this->admin(super: true);
        $plain = $this->product();
        $both = $this->product(['name' => 'Both', 'slug' => 'both']);
        $both->forceFill(['suspended_at' => now()->subDay(), 'archived_at' => now()->subHour()])->save();

        $this->assertStringNotContainsString('admin-product-form-note', $this->page($admin, route('dashboard.products.edit', $plain)));
        $this->assertStringNotContainsString('admin-product-form-note', $this->page($admin, route('dashboard.products.create')));

        $html = $this->page($admin, route('dashboard.products.edit', $both));
        $this->assertStringContainsString('This product is suspended by the administration and cannot be sold. Saving this form does not lift the suspension.', $html);
        $this->assertStringContainsString('This product is archived and cannot be sold. Saving this form does not restore it.', $html);
        $this->assertStringContainsString('href="'.route('dashboard.products.show', $both).'#product-panel-moderation"', $html);
        $this->assertStringNotContainsString('/suspend"', $html, 'No moderation form here.');

        $suspendedAt = $both->fresh()->suspended_at;
        $archivedAt = $both->fresh()->archived_at;
        $this->actingAs($admin, 'admin')->put(route('dashboard.products.update', $both), ['price' => '20'] + $this->formValues($html))->assertSessionHasNoErrors();
        $this->assertEquals([$suspendedAt, $archivedAt], [$both->fresh()->suspended_at, $both->fresh()->archived_at]);
    }

    public function test_cancel_and_back_lead_to_the_product_or_the_list(): void
    {
        $product = $this->product();

        $edit = $this->page($this->admin(super: true), route('dashboard.products.edit', $product));
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('dashboard.products.show', $product), '#').'" class="btn btn-light-secondary">Cancel</a>#', $edit);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('dashboard.products.show', $product), '#').'" class="admin-product-back">#', $edit);

        $create = $this->page($this->admin(super: true), route('dashboard.products.create'));
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('dashboard.products.index'), '#').'" class="btn btn-light-secondary">Cancel</a>#', $create);

        // An admin who may edit but not view products is not sent to a page that refuses them.
        $editor = $this->page($this->admin(['products.edit']), route('dashboard.products.edit', $product));
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('dashboard.home'), '#').'" class="btn btn-light-secondary">Cancel</a>#', $editor);
        $this->assertStringNotContainsString('class="admin-product-back"', $editor);
        $this->assertStringNotContainsString('#product-panel-moderation', $editor);
    }

    public function test_the_product_name_is_escaped(): void
    {
        $product = $this->product(['name' => '<script>alert(1)</script>']);
        $html = $this->page($this->admin(super: true), route('dashboard.products.edit', $product));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('<bdi>&lt;script&gt;alert(1)&lt;/script&gt;</bdi>', $html);
    }

    // ---------------------------------------------------------------- languages and translation rows

    public function test_the_form_writes_no_translation_row(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product(['category_id' => $this->inactive->id]);
        $product->forceFill(['suspended_at' => now()])->save();

        foreach (['ar', 'en'] as $locale) {
            $this->page($admin, route('dashboard.products.create'), $locale);
            $this->page($admin, route('dashboard.products.edit', $product), $locale);
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->post(route('dashboard.products.store'), ['name' => '', 'stock_quantity' => 'x', 'status' => 'nope']);
            $this->page($admin, route('dashboard.products.create'), $locale);
        }

        $formKeys = ['Products_Form_%', 'Products_Status_%', 'Product_Attribute_%'];
        foreach ($formKeys as $pattern) {
            $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.'.$pattern)->count(), $pattern);
        }
        // The shared keys the old form used are not read any more.
        $old = ['Name', 'Slug', 'Vendor', 'Category', 'None', 'Price', 'Compare_Price', 'SKU', 'Status', 'Stock', 'Low_Stock_Threshold', 'Weight', 'Featured', 'Short_Description', 'Description', 'Save', 'Cancel', 'Draft', 'Active', 'Inactive', 'Out_of_stock', 'Create_Product', 'Edit_Product', 'Create', 'Edit'];
        $this->assertSame([], TranslationValue::whereIn('key', array_map(fn ($key) => 'dashboard.'.$key, $old))->pluck('key')->unique()->values()->all());
    }

    public function test_arabic_and_english(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
        $admin = $this->admin(super: true);
        $product = $this->product(['category_id' => $this->inactive->id]);
        $product->forceFill(['archived_at' => now()])->save();

        $ar = $this->page($admin, route('dashboard.products.edit', $product), 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['تعديل المنتج', 'البيانات الأساسية', 'الأسعار والمخزون', 'الوصف والتفاصيل', 'الحالة والإعدادات', 'اسم المنتج', 'كمية المخزون', 'سعر المقارنة', '(إلزامي)', '(اختياري)', 'Old crafts (غير نشط)', 'هذا المنتج مؤرشف ولا يمكن بيعه', 'حفظ المنتج', 'إلغاء', 'العودة إلى المنتج', 'مسودة', 'نشط'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }
        foreach (['>Name<', 'Low Stock Threshold', 'Compare Price', '>Save<', 'Product name'] as $english) {
            $this->assertStringNotContainsString($english, $ar, $english);
        }

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.products.store'), ['vendor_id' => $this->vendor->id, 'name' => '', 'price' => '1', 'stock_quantity' => 'x', 'status' => 'draft']);
        $errors = $this->page($admin, route('dashboard.products.create'), 'ar');
        $this->assertStringContainsString('حقل اسم المنتج مطلوب.', $errors);
        $this->assertStringContainsString('يجب أن يكون حقل كمية المخزون عددًا صحيحًا.', $errors);
        $this->assertStringContainsString('يرجى تصحيح ما يلي:', $errors);

        $en = $this->page($admin, route('dashboard.products.create'), 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        foreach (['Add product', 'Basic details', 'Product name', 'Stock quantity', 'Save product', 'Back to products'] as $text) {
            $this->assertStringContainsString($text, $en, $text);
        }
    }

    public function test_the_weight_unit_selector_offers_and_saves_kg_g_and_ton_in_both_languages(): void
    {
        $admin = $this->admin(super: true);
        $options = fn (string $html): array => array_map(
            fn ($option) => [$option->getAttribute('value'), trim($option->textContent)],
            iterator_to_array($this->xpath($html)->query('//select[@id="product-weight-unit"]/option'))
        );

        $this->assertSame([['', 'Unit'], ['kg', 'kg'], ['g', 'g'], ['t', 'ton']], $options($this->page($admin, route('dashboard.products.create'))));

        $this->seed(AdminProductTranslationSeeder::class);
        $ar = $this->page($admin, route('dashboard.products.create'), 'ar');
        $this->assertSame([['', 'الوحدة'], ['kg', 'كغ'], ['g', 'غ'], ['t', 'طن']], $options($ar));
        $this->assertStringContainsString('<label class="visually-hidden" for="product-weight-unit">وحدة الوزن</label>', $ar);

        // Each unit is submitted and stored as before, and shown selected on the edit form.
        foreach (['kg', 'g', 't'] as $unit) {
            $values = $this->formValues($this->page($admin, route('dashboard.products.create')));
            $this->actingAs($admin, 'admin')->post(route('dashboard.products.store'), [
                'name' => 'Clay jug '.$unit, 'price' => '12.50', 'stock_quantity' => '4', 'weight' => '2', 'weight_unit' => $unit,
            ] + $values)->assertRedirect(route('dashboard.products.index'));

            $product = Product::query()->where('name', 'Clay jug '.$unit)->sole();
            $this->assertSame($unit, $product->weight_unit);
            $this->assertSame($unit, $this->formValues($this->page($admin, route('dashboard.products.edit', $product)))['weight_unit']);
        }
    }

    public function test_an_invalid_image_shows_the_translated_error(): void
    {
        $this->seed(AdminProductTranslationSeeder::class);
        $admin = $this->admin(super: true);
        $values = $this->formValues($this->page($admin, route('dashboard.products.create')));
        $fake = UploadedFile::fake()->createWithContent('jug.png', 'not an image');

        foreach (['en' => 'Each image must be a valid JPG, PNG, GIF or WebP file, with an extension matching its content.',
            'ar' => 'يجب أن تكون كل صورة ملف JPG أو PNG أو GIF أو WebP صالحًا، وامتداده مطابق لمحتواه.'] as $locale => $message) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->post(route('dashboard.products.store'), [
                'name' => 'Clay jug', 'price' => '12.50', 'stock_quantity' => '4', 'images' => [$fake],
            ] + $values)->assertSessionHasErrors(['images' => $message]);
        }
        $this->assertSame(0, Product::count());
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'price' => 10,
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => "Editor {$sequence}", 'email' => "form{$sequence}@example.com", 'phone' => '05990006'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, string $url, string $locale = 'en'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * What the product form would submit as rendered (a browser's successful controls; an unchecked checkbox is not
     * sent, so its hidden companion is).
     *
     * @return array<string, string>
     */
    private function formValues(string $html): array
    {
        $xpath = $this->xpath($html);
        $values = [];

        foreach ($xpath->query('//form[@novalidate]//input') as $input) {
            $type = $input->getAttribute('type');
            if ($type === 'checkbox' && ! $input->hasAttribute('checked')) {
                continue;
            }
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        foreach ($xpath->query('//form[@novalidate]//select') as $select) {
            $selected = $xpath->query('.//option[@selected]', $select)->item(0) ?? $xpath->query('.//option', $select)->item(0);
            $values[$select->getAttribute('name')] = $selected?->getAttribute('value') ?? '';
        }
        foreach ($xpath->query('//form[@novalidate]//textarea') as $textarea) {
            $values[$textarea->getAttribute('name')] = $textarea->textContent;
        }

        return $values;
    }
}
