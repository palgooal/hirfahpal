<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-CAT-01E-3B: the category create / edit form in the admin shell. Linked labels, hints and errors,
 * old input, parent options (two levels, DEC-CAT-Q13), no activation control (DEC-CAT-Q16), the M4 media picker,
 * the double-submit hook, both directions, and no translation row created while rendering.
 */
class CategoryFormPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_the_create_form_in_arabic(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $pottery = Category::create(['name' => 'فخار', 'slug' => 'pottery']);
        $jugs = Category::create(['name' => 'أباريق', 'slug' => 'jugs', 'parent_id' => $pottery->id]);

        $html = $this->page($this->admin(super: true), route('dashboard.categories.create'), 'ar');
        $form = $this->form($html);

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'One h1: the header title.');
        $this->assertStringContainsString('<h1 class="admin-header-title">إضافة تصنيف</h1>', $html);
        $this->assertStringContainsString('<title>إضافة تصنيف | ', $html);
        $this->assertStringContainsString('التصنيفات الجديدة تكون مفعّلة', $html);
        $this->assertStringContainsString('action="'.route('dashboard.categories.store').'"', $form);
        $this->assertStringNotContainsString('name="_method"', $form);
        $this->assertStringContainsString('aria-label="إضافة تصنيف"', $form);

        // Labels linked to their fields, with required / optional marks.
        foreach (['category-name' => 'الاسم', 'category-slug' => 'الرابط المختصر (Slug)', 'category-description' => 'الوصف', 'category-parent' => 'التصنيف الأب', 'category-order' => 'ترتيب العرض'] as $id => $label) {
            $this->assertMatchesRegularExpression('#<label class="form-label" for="'.$id.'">\s*'.preg_quote($label, '#').' <span class="admin-cat-form-mark">\((مطلوب|اختياري)\)</span>#u', $form, $id);
            $this->assertSame(1, preg_match_all('# id="'.$id.'"#', $form), "{$id}: one field");
        }
        $this->assertMatchesRegularExpression('#for="category-name">\s*الاسم <span class="admin-cat-form-mark">\(مطلوب\)#u', $form);
        $this->assertMatchesRegularExpression('#id="category-name" name="name" value="" maxlength="255" required aria-required="true"#', $form);
        $this->assertStringContainsString('aria-describedby="category-slug-hint"', $form);
        $this->assertStringContainsString('aria-describedby="category-parent-hint"', $form);
        $this->assertStringContainsString('aria-describedby="category-order-hint"', $form);
        $this->assertStringContainsString('value="0" min="0"', $form, 'Order starts at 0.');

        // Parent: a clear "main category" choice, then main categories only.
        $this->assertStringContainsString('<option value="">بلا أب (تصنيف رئيسي)</option>', $form);
        $this->assertStringContainsString('<option value="'.$pottery->id.'"', $form);
        $this->assertStringNotContainsString('<option value="'.$jugs->id.'"', $form, 'A subcategory is never offered as a parent.');
        $this->assertStringContainsString('التصنيفات الرئيسية فقط يمكن أن تكون أبًا', $form);

        // No activation control (DEC-CAT-Q16); the M4 picker is there; no file upload into categories.image.
        $this->assertStringNotContainsString('name="is_active"', $form);
        $this->assertStringNotContainsString('type="checkbox"', $form);
        $this->assertStringNotContainsString('name="image"', $form);
        $this->assertStringContainsString('data-admin-media-picker', $form);
        $this->assertStringContainsString('name="image_media_id"', $form);
        $this->assertStringNotContainsString('data-picker-remove-button', $form, 'Nothing to remove on create.');

        // Save with its saving text, Cancel back to the list.
        $this->assertMatchesRegularExpression('#<button type="submit" class="btn btn-primary" data-category-submit data-saving="جارٍ الحفظ…">.*?<span>حفظ التصنيف</span>#su', $form);
        $this->assertStringContainsString('<a href="'.route('dashboard.categories.index').'" class="btn btn-light-secondary">إلغاء</a>', $form);
        $this->assertStringContainsString('data-category-form', $form);
        $this->assertStringContainsString('id="admin-category-form"', $html);
    }

    public function test_the_edit_form_shows_the_current_values_in_english(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $pottery = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $glass = Category::create(['name' => 'Glass', 'slug' => 'glass']);
        $jugs = Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $pottery->id, 'description' => 'Clay & water', 'sort_order' => 4]);

        $html = $this->page($this->admin(super: true), route('dashboard.categories.edit', $jugs), 'en');
        $form = $this->form($html);

        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="admin-header-title">Edit Category</h1>', $html);
        $this->assertStringContainsString('Its active state stays as it is.', $html);
        $this->assertStringContainsString('action="'.route('dashboard.categories.update', $jugs).'"', $form);
        $this->assertStringContainsString('name="_method" value="PUT"', $form);
        $this->assertStringContainsString('name="name" value="Jugs"', $form);
        $this->assertStringContainsString('name="slug" value="jugs"', $form);
        $this->assertStringContainsString('name="sort_order" value="4"', $form);
        $this->assertStringContainsString('>Clay &amp; water</textarea>', $form);
        $this->assertStringContainsString('<option value="'.$pottery->id.'" selected>Pottery</option>', $form);
        $this->assertStringContainsString('<option value="'.$glass->id.'" >Glass</option>', $form);
        $this->assertStringNotContainsString('<option value="'.$jugs->id.'"', $form, 'Never its own parent.');
        $this->assertStringNotContainsString('admin-cat-form-note', $html, 'Active: no inactive note.');
        $this->assertStringContainsString('<span>Save category</span>', $form);
    }

    public function test_a_main_category_with_subcategories_is_offered_no_parent_and_says_why(): void
    {
        $pottery = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        Category::create(['name' => 'Glass', 'slug' => 'glass']);
        Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $pottery->id]);

        $form = $this->form($this->page($this->admin(super: true), route('dashboard.categories.edit', $pottery), 'en'));

        $this->assertSame(1, substr_count($form, '<option '), 'Only the main-category option.');
        $this->assertStringContainsString('<option value="">None (main category)</option>', $form);
        $this->assertStringContainsString('This category has subcategories, so it stays a main category.', $form);
    }

    public function test_an_inactive_category_says_saving_keeps_it_inactive(): void
    {
        $category = Category::create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);

        $html = $this->page($this->admin(super: true), route('dashboard.categories.edit', $category), 'en');

        $this->assertStringContainsString('This category is inactive. Saving does not change that.', $html);
        $this->assertStringNotContainsString('name="is_active"', $this->form($html));
    }

    public function test_a_failed_save_keeps_the_input_and_shows_each_error_under_its_field(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $admin = $this->admin(super: true);
        $pottery = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        Category::create(['name' => 'Taken', 'slug' => 'taken']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => '', 'slug' => 'taken', 'parent_id' => $pottery->id, 'sort_order' => 'x', 'description' => 'Kept <b>text</b>'])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasErrors(['name', 'slug', 'sort_order']);
        $this->assertSame(2, Category::count(), 'Nothing saved.');

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.categories.create'))->assertOk()->getContent();
        $form = $this->form($html);

        // Old input kept (and escaped).
        $this->assertStringContainsString('name="slug" value="taken"', $form);
        $this->assertStringContainsString('name="sort_order" value="x"', $form);
        $this->assertStringContainsString('>Kept &lt;b&gt;text&lt;/b&gt;</textarea>', $form);
        $this->assertStringContainsString('<option value="'.$pottery->id.'" selected>Pottery</option>', $form);

        // Each error under its field, announced with the field.
        foreach (['name' => 'category-name', 'slug' => 'category-slug', 'sort_order' => 'category-order'] as $field => $id) {
            $this->assertMatchesRegularExpression('#id="'.$id.'"[^>]*aria-invalid="true"#s', $form, $field);
            $this->assertMatchesRegularExpression('#<p class="admin-cat-form-error" id="'.$id.'-error">[^<]+</p>#', $form, $field);
            $this->assertMatchesRegularExpression('#id="'.$id.'"[^>]*aria-describedby="[^"]*'.$id.'-error"#s', $form, $field);
        }
        $this->assertMatchesRegularExpression('#class="form-control is-invalid" id="category-name"#', $form);
        $this->assertStringContainsString('aria-describedby="category-slug-hint category-slug-error"', $form);
        $this->assertStringNotContainsString('category-description-error', $form);

        // A translated summary linking to the fields, before the form.
        $this->assertMatchesRegularExpression('#id="category-form-errors".*Please correct the following:.*<a href="\#category-name">Name</a>:.*<a href="\#category-slug">Slug</a>:.*<a href="\#category-order">Display order</a>:#s', $html);
        $this->assertLessThan(strpos($html, '<form method="POST"'), strpos($html, 'id="category-form-errors"'));
    }

    public function test_a_refused_parent_is_shown_under_the_parent_field(): void
    {
        $admin = $this->admin(super: true);
        $pottery = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $jugs = Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $pottery->id]);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Small jugs', 'parent_id' => $jugs->id])
            ->assertSessionHasErrors('parent_id');

        $form = $this->form($this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.categories.create'))->getContent());

        $this->assertStringContainsString('aria-describedby="category-parent-hint category-parent-error"', $form);
        $this->assertStringContainsString('<p class="admin-cat-form-error" id="category-parent-error">Choose a main category as the parent.', $form);
        $this->assertStringContainsString('name="name" value="Small jugs"', $form);
    }

    public function test_the_form_follows_the_permissions(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);

        $viewer = $this->admin(['categories.view']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.edit', $category))->assertForbidden();

        $editor = $this->admin(['categories.edit']);
        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.create'))->assertForbidden();
        $html = $this->actingAs($editor, 'admin')->get(route('dashboard.categories.edit', $category))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-picker-open', $html, 'Choosing an image still needs media.view.');

        $creator = $this->admin(['categories.create', 'media.view', 'media.create']);
        $html = $this->actingAs($creator, 'admin')->get(route('dashboard.categories.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-picker-open', $html);
        $this->assertStringContainsString('data-picker-upload', $html);
    }

    public function test_saving_from_the_form_keeps_the_category_rules(): void
    {
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Pottery', 'is_active' => 0])->assertRedirect();
        $created = Category::where('name', 'Pottery')->firstOrFail();
        $this->assertTrue((bool) $created->is_active, 'New categories are active.');

        $inactive = Category::create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);
        $this->actingAs($admin, 'admin')->put(route('dashboard.categories.update', $inactive), ['name' => 'Older', 'slug' => 'old', 'is_active' => 1])->assertRedirect();
        $this->assertFalse((bool) $inactive->fresh()->is_active, 'Editing keeps the state.');
        $this->assertSame('Older', $inactive->fresh()->name);
    }

    public function test_rendering_creates_no_translation_rows(): void
    {
        $admin = $this->admin(super: true);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $keys = array_keys(AdminCategoryTranslationSeeder::TRANSLATIONS);

        foreach (['ar', 'en'] as $locale) {
            $html = $this->page($admin, route('dashboard.categories.create'), $locale);
            $this->assertStringContainsString('<span>Save category</span>', $html, 'English defaults until the seeder runs.');
            $this->page($admin, route('dashboard.categories.edit', $category), $locale);
        }

        $this->assertSame(0, TranslationValue::whereIn('key', $keys)->count());
    }

    private function page(Admin $admin, string $url, string $locale): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();
    }

    private function form(string $html): string
    {
        $this->assertSame(1, preg_match('#<form method="POST"[^>]*data-category-form[^>]*>.*?</form>#s', $html, $match), 'The category form.');

        return $match[0];
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
