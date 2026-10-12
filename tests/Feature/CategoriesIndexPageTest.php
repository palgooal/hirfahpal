<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Palgoal\MediaLibrary\Models\Media;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * AD-UI-CAT-01E-3A: the categories list in the admin shell: hierarchy, counts, thumbnails, filters, paging,
 * actions per ability, delete confirmation, messages, RTL/LTR, no activation control (DEC-CAT-Q16).
 */
class CategoriesIndexPageTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

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

    // ---------------------------------------------------------------- shell, header

    public function test_the_list_renders_in_the_admin_shell_with_one_title(): void
    {
        $this->seedTranslations();
        $html = $this->page($this->admin(super: true));

        $this->assertStringContainsString('id="admin-categories"', $html);
        $this->assertMatchesRegularExpression('#<h1 class="admin-header-title"[^>]*>\s*التصنيفات\s*</h1>#u', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'One page title, in the header.');
        $this->assertStringNotContainsString('<h4', $this->list($html), 'No second title in the content.');
        $this->assertStringContainsString('نظّم التصنيفات التي يتصفحها المتسوقون', $html);
        $this->assertStringContainsString('http://vite.test/resources/js/admin-categories.js', $html);
        $this->assertStringNotContainsString('class="breadcrumb-item"', $html, 'No permanent breadcrumb (DEC-AD-UI-03).');
    }

    public function test_main_and_subcategories_are_grouped_and_told_apart(): void
    {
        $pottery = $this->category('Pottery', sort: 2);
        $glass = $this->category('Glass', sort: 1);
        $cups = $this->category('Cups', parent: $pottery, sort: 1);
        $bowls = $this->category('Bowls', parent: $pottery, sort: 0);
        $vases = $this->category('Vases', parent: $glass);

        $html = $this->page($this->admin(super: true), 'en');

        // Glass (order 1) and its child, then Pottery (order 2) and its children by their own order.
        $this->assertSame([$glass->id, $vases->id, $pottery->id, $bowls->id, $cups->id], $this->rowIds($html));
        $this->assertMatchesRegularExpression('#data-category-id="'.$pottery->id.'"[^>]*>|<li class="admin-cats-row is-root" data-category-id="'.$pottery->id.'"#', $html);
        $this->assertStringContainsString('<li class="admin-cats-row is-child" data-category-id="'.$cups->id.'">', $html);
        $this->assertMatchesRegularExpression('#data-category-id="'.$cups->id.'">.*?Subcategory</span>\s*<span class="admin-cats-parent">Under <bdi>Pottery</bdi>#s', $html);
        $this->assertMatchesRegularExpression('#data-category-id="'.$pottery->id.'">.*?Main category</span>#s', $html);
    }

    public function test_direct_products_and_subcategories_are_counted(): void
    {
        $root = $this->category('Pottery');
        $child = $this->category('Cups', parent: $root);
        $this->products($child, 3);
        $this->products($root, 1);

        $html = $this->page($this->admin(super: true), 'en');

        $this->assertSame(['Direct products' => '1', 'Subcategories' => '1', 'Order' => '0'], $this->stats($html, $root));
        $this->assertSame(['Direct products' => '3', 'Subcategories' => '0', 'Order' => '0'], $this->stats($html, $child));
    }

    // ---------------------------------------------------------------- thumbnails

    public function test_a_thumbnail_is_shown_only_for_a_valid_stored_image(): void
    {
        $with = $this->category('With image', image: $this->media());
        $missingFile = $this->category('Missing file', image: $this->media(store: false));
        $notImage = $this->category('Not an image', image: $this->media(['file_type' => 'document', 'mime_type' => 'application/pdf']));
        $without = $this->category('No image');
        DB::table('categories')->where('id', $without->id)->update(['image' => 'legacy/never-shown.png']);

        $html = $this->page($this->admin(super: true), 'en');

        $this->assertMatchesRegularExpression('#data-category-id="'.$with->id.'">\s*<div class="admin-cats-thumb">\s*<img src="'.preg_quote($with->imageMedia->url, '#').'" alt="" loading="lazy" data-category-thumb>#', $html);

        foreach ([$missingFile, $notImage, $without] as $category) {
            $this->assertMatchesRegularExpression('#data-category-id="'.$category->id.'">\s*<div class="admin-cats-thumb">\s*<i class="ph-duotone ph-image" aria-hidden="true"></i>#', $html, $category->name);
        }

        $this->assertStringNotContainsString('legacy/never-shown.png', $html, 'The legacy image column is never used.');
        $this->assertStringNotContainsString('data-picker', $html, 'No media picker on the list.');
    }

    // ---------------------------------------------------------------- filters, paging

    public function test_search_is_literal_and_filters_combine(): void
    {
        $pottery = $this->category('Pottery');
        $cups = $this->category('Cups', parent: $pottery);
        $wool = $this->category('100% Wool');
        $this->category('Under_score');
        $dormant = $this->category('Dormant', active: false);
        $admin = $this->admin(super: true);

        $this->assertSame([$cups->id], $this->rowIds($this->page($admin, 'en', ['search' => 'cup'])));
        // Typed wildcards match themselves, not everything.
        $this->assertSame([$wool->id], $this->rowIds($this->page($admin, 'en', ['search' => '%'])));
        $this->assertSame([Category::where('name', 'Under_score')->value('id')], $this->rowIds($this->page($admin, 'en', ['search' => '_'])));
        $this->assertSame([$cups->id], $this->rowIds($this->page($admin, 'en', ['level' => 'child'])));
        $this->assertNotContains($cups->id, $this->rowIds($this->page($admin, 'en', ['level' => 'root'])));
        $this->assertSame([$dormant->id], $this->rowIds($this->page($admin, 'en', ['is_active' => '0'])));

        $filtered = $this->page($admin, 'en', ['search' => 'nothing-like-this']);
        $this->assertStringContainsString('No categories match these filters.', $filtered);
        $this->assertStringContainsString('>Reset</a>', $filtered);
        $this->assertStringContainsString('value="nothing-like-this"', $filtered, 'The search is kept in the field.');
    }

    public function test_filter_fields_have_linked_labels(): void
    {
        $html = $this->page($this->admin(super: true), 'en');

        foreach (['categories-search' => 'Search categories', 'categories-level' => 'Type', 'categories-status' => 'Status'] as $id => $label) {
            $this->assertMatchesRegularExpression('#<label class="form-label" for="'.$id.'">'.$label.'</label>#', $html, $id);
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }

    public function test_pagination_is_translated_and_keeps_the_filters(): void
    {
        $this->seedTranslations();
        foreach (range(1, 17) as $i) {
            $this->category(sprintf('Category %02d', $i));
        }
        $admin = $this->admin(super: true);

        $first = $this->page($admin, 'ar', ['search' => 'Category']);
        $this->assertStringContainsString('عرض 1 إلى 15 من 17', $first);
        $this->assertStringContainsString('aria-label="التنقل بين الصفحات"', $first);
        $this->assertMatchesRegularExpression('#<span class="admin-page is-current" aria-current="page">1</span>#', $first);
        $this->assertMatchesRegularExpression('#href="[^"]*search=Category[^"]*page=2" rel="next">#', html_entity_decode($first));
        $this->assertStringContainsString('<span>التالي</span>', $first);

        $second = $this->page($admin, 'ar', ['search' => 'Category', 'page' => 2]);
        $this->assertStringContainsString('عرض 16 إلى 17 من 17', $second);
        $this->assertCount(2, $this->rowIds($second));
    }

    public function test_an_empty_store_offers_the_first_category(): void
    {
        $html = $this->page($this->admin(super: true), 'en');
        $this->assertStringContainsString('No categories yet.', $html);
        $this->assertStringContainsString('Add the first category', $html);

        $viewer = $this->page($this->admin(['categories.view']), 'en');
        $this->assertStringNotContainsString('Add the first category', $viewer);
    }

    // ---------------------------------------------------------------- actions per ability

    public function test_actions_follow_the_category_abilities(): void
    {
        $category = $this->category('Pottery');
        $cases = [
            'view only' => [['categories.view'], ['create' => false, 'edit' => false, 'delete' => false]],
            'view + create' => [['categories.view', 'categories.create'], ['create' => true, 'edit' => false, 'delete' => false]],
            'view + edit' => [['categories.view', 'categories.edit'], ['create' => false, 'edit' => true, 'delete' => false]],
            'view + delete' => [['categories.view', 'categories.delete'], ['create' => false, 'edit' => false, 'delete' => true]],
        ];

        foreach ($cases as $label => [$abilities, $expected]) {
            $html = $this->page($this->admin($abilities), 'en');

            $this->assertSame($expected['create'], str_contains($html, 'href="'.route('dashboard.categories.create').'"'), "{$label}: add");
            $this->assertSame($expected['edit'], str_contains($html, 'href="'.route('dashboard.categories.edit', $category).'"'), "{$label}: edit");
            $this->assertSame($expected['delete'], str_contains($html, 'action="'.route('dashboard.categories.delete', $category).'"'), "{$label}: delete");
            $this->assertSame($expected['delete'], str_contains($html, 'data-destroy="'.route('dashboard.categories.destroy', $category).'"'), "{$label}: delete target");
            $this->assertSame($expected['delete'], str_contains($html, 'id="category-delete-dialog"'), "{$label}: dialog");
        }

        $this->actingAs($this->admin(['categories.create', 'categories.edit', 'categories.delete']), 'admin')->get(route('dashboard.categories.index'))->assertForbidden();

        auth('admin')->logout();
        $this->get(route('dashboard.categories.index'))->assertRedirect(route('admin.login'));
    }

    public function test_actions_have_names_that_say_which_category(): void
    {
        $category = $this->category('Pottery');
        $html = $this->page($this->admin(super: true), 'en');

        $this->assertStringContainsString('aria-label="Edit Pottery"', $html);
        $this->assertStringContainsString('aria-label="Delete Pottery"', $html);
        // N-CAT-1: the row's form opens the confirmation page (GET); the dialog's DELETE form carries CSRF.
        $this->assertMatchesRegularExpression('#<form method="GET" action="'.preg_quote(route('dashboard.categories.delete', $category), '#').'" data-category-delete data-name="Pottery" data-destroy="'.preg_quote(route('dashboard.categories.destroy', $category), '#').'" data-confirmation="[A-Za-z0-9]{48}">#', $html);
        $this->assertMatchesRegularExpression('#<form method="POST" action="" class="admin-media-dialog-footer" data-category-delete-form>\s*<input type="hidden" name="_token" value="[^"]+"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<input type="hidden" name="confirmation" value="" data-category-delete-confirmation>#', $html);
    }

    public function test_delete_is_not_offered_for_a_category_with_products_or_subcategories_and_the_server_still_refuses(): void
    {
        $withProducts = $this->category('Has products');
        $this->products($withProducts, 1);
        $parent = $this->category('Has children');
        $child = $this->category('Child', parent: $parent);
        $admin = $this->admin(super: true);

        $html = $this->page($admin, 'en');
        $this->assertStringNotContainsString('data-destroy="'.route('dashboard.categories.destroy', $withProducts).'"', $html);
        $this->assertStringNotContainsString('data-destroy="'.route('dashboard.categories.destroy', $parent).'"', $html);
        $this->assertStringContainsString('data-destroy="'.route('dashboard.categories.destroy', $child).'"', $html);
        $this->assertStringContainsString('Cannot be deleted: it has products.', $html);
        $this->assertStringContainsString('Cannot be deleted: it has subcategories.', $html);

        // Hiding the button is not the protection: even with a valid confirmation the server refuses.
        $this->confirmedDelete($admin, $withProducts)->assertSessionHas('danger');
        $this->confirmedDelete($admin, $parent)->assertSessionHas('danger');
        $this->assertModelExists($withProducts);
        $this->assertModelExists($parent);
    }

    public function test_the_confirm_dialog_defaults_to_cancel(): void
    {
        $this->seedTranslations();
        $this->category('Pottery');
        $html = $this->page($this->admin(super: true));

        $this->assertMatchesRegularExpression('#<dialog class="admin-media-dialog admin-cats-confirm" id="category-delete-dialog" aria-labelledby="category-delete-title" aria-describedby="category-delete-text">#', $html);
        $this->assertMatchesRegularExpression('#<button type="button" class="btn btn-light-secondary" data-category-delete-cancel autofocus>إلغاء</button>#u', $html);
        $this->assertStringContainsString('data-template="سيُحذف التصنيف :name. لا يمكن التراجع عن ذلك."', $html);
        $this->assertStringContainsString('<button type="submit" class="btn btn-danger" data-category-delete-confirm>حذف التصنيف</button>', $html);
    }

    // ---------------------------------------------------------------- messages

    public function test_success_and_error_messages_show_on_the_page(): void
    {
        $this->seedTranslations();
        $admin = $this->admin(super: true);
        $withProducts = $this->category('Has products');
        $this->products($withProducts, 1);
        $empty = $this->category('Empty');

        $this->withSession(['locale' => 'ar'])->confirmedDelete($admin, $empty);
        $done = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.index'))->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-success" role="status" data-cat-alert>.*?تم حذف التصنيف بنجاح\.#su', $done);

        $this->withSession(['locale' => 'ar'])->confirmedDelete($admin, $withProducts);
        $refused = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.index'))->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-error" role="alert" data-cat-alert>.*?لا يمكن حذف هذا التصنيف لأنه مرتبط بمنتجات\.#su', $refused);
        $this->assertStringContainsString('aria-label="إغلاق التنبيه"', $refused);
    }

    // ---------------------------------------------------------------- language, decisions

    public function test_direction_and_language(): void
    {
        $this->seedTranslations();
        $this->category('فخار');
        $admin = $this->admin(super: true);

        $ar = $this->page($admin, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['إضافة تصنيف', 'البحث في التصنيفات', 'تصنيف رئيسي', 'منتجات مباشرة', 'تصنيفات فرعية', 'الترتيب', 'تعديل', 'حذف'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }

        $en = $this->page($admin, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', preg_replace('#<bdi>[^<]*</bdi>|data-name="[^"]*"|aria-label="(Edit|Delete) [^"]*"#u', '', $this->list($en)), 'Only user data is Arabic in the English list.');
    }

    public function test_the_page_creates_no_translation_rows(): void
    {
        $this->category('Pottery');
        $this->page($this->admin(super: true), 'ar');

        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Categor%')->orWhere('key', 'like', 'dashboard.Pagination%')->count());
    }

    public function test_no_activation_control_is_offered(): void
    {
        $this->category('Active');
        $this->category('Dormant', active: false);
        $html = $this->page($this->admin(super: true), 'en');

        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertDoesNotMatchRegularExpression('#<form[^>]*method="POST"[^>]*>(?:(?!</form>).)*name="is_active"#is', $html);
        $this->assertStringContainsString('<span class="admin-cats-badge is-inactive">Inactive</span>', $html, 'The state is shown, not changed.');
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
    }

    private function page(Admin $admin, string $locale = 'ar', array $query = []): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.categories.index', $query))->assertOk()->getContent();
    }

    private function list(string $html): string
    {
        $this->assertSame(1, preg_match('#id="admin-categories".*?</section>#s', $html, $match));

        return $match[0];
    }

    /** @return list<int> */
    private function rowIds(string $html): array
    {
        preg_match_all('#<li class="admin-cats-row is-(?:root|child)" data-category-id="(\d+)">#', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    /** @return array<string, string> */
    private function stats(string $html, Category $category): array
    {
        $this->assertSame(1, preg_match('#data-category-id="'.$category->id.'">.*?<dl class="admin-cats-stats">(.*?)</dl>#s', $html, $match));
        preg_match_all('#<dt>([^<]+)</dt>\s*<dd>([^<]+)</dd>#', $match[1], $pairs);

        return array_combine($pairs[1], array_map('trim', $pairs[2]));
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function category(string $name, ?Category $parent = null, int $sort = 0, bool $active = true, ?Media $image = null): Category
    {
        $category = Category::create(['name' => $name, 'slug' => str($name)->slug()->value() ?: 'c-'.uniqid(), 'parent_id' => $parent?->id, 'sort_order' => $sort, 'is_active' => $active]);
        $image && $category->forceFill(['image_media_id' => $image->id])->save();

        return $category->fresh();
    }

    private function products(Category $category, int $count): void
    {
        $vendor = Vendor::factory()->create();

        foreach (range(1, $count) as $i) {
            DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => "P{$category->id}-{$i}", 'slug' => "p-{$category->id}-{$i}", 'price' => 10]);
        }
    }

    private function media(array $attributes = [], bool $store = true): Media
    {
        $name = uniqid('', true).'.png';
        $path = 'media/2026/10/'.$name;
        $store && Storage::disk('public')->put($path, 'file');

        return Media::create($attributes + [
            'file_name' => $name, 'file_original_name' => $name, 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 4, 'file_type' => 'image', 'disk' => 'public',
        ]);
    }
}
