<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-CAT-2 (AD-UI-CAT-01E-3C): validation messages name the category fields in the admin's language
 * (t(), without auto-creating keys), under the fields and in the form's error summary. The rules and
 * lang/{locale}/validation.php are unchanged.
 */
class CategoryValidationFieldNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_arabic_messages_use_the_arabic_field_names(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        Category::create(['name' => 'Taken', 'slug' => 'taken']);
        $admin = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => '', 'slug' => 'taken', 'parent_id' => 999, 'sort_order' => '-1', 'description' => ['x'], 'image_media_id' => 'abc', 'remove_image' => 'maybe'])
            ->assertSessionHasErrors([
                'name' => 'حقل اسم التصنيف مطلوب.',
                'slug' => 'قيمة الرابط المختصر مستخدمة من قبل.',
                'parent_id' => 'قيمة التصنيف الأب المختارة غير صالحة.',
                'sort_order' => 'يجب أن تكون قيمة حقل ترتيب العرض 0 على الأقل.',
                'description' => 'يجب أن يكون حقل الوصف نصًا.',
            ]);

        // The image fields are validated after the category fields; on their own:
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Ok', 'image_media_id' => 'abc', 'remove_image' => 'maybe'])
            ->assertSessionHasErrors([
                'image_media_id' => 'يجب أن يكون حقل الصورة عددًا صحيحًا.',
                'remove_image' => 'يجب أن تكون قيمة حقل إزالة الصورة صحيحة أو خاطئة.',
            ]);

        // Shown with those names under the field and in the summary.
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => '', 'slug' => 'taken']);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.create'))->getContent();
        $this->assertStringContainsString('<p class="admin-cat-form-error" id="category-name-error">حقل اسم التصنيف مطلوب.</p>', $html);
        $this->assertStringContainsString('<p class="admin-cat-form-error" id="category-slug-error">قيمة الرابط المختصر مستخدمة من قبل.</p>', $html);
        $this->assertStringContainsString('<a href="#category-name">الاسم</a>: حقل اسم التصنيف مطلوب.', $html);
        $this->assertStringNotContainsString('sort order', $html);
        $this->assertStringNotContainsString('قيمة slug', $html);
    }

    public function test_english_messages_use_the_english_field_names(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        Category::create(['name' => 'Taken', 'slug' => 'taken']);
        $admin = Admin::factory()->create(['super_admin' => true]);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.categories.edit', $category))
            ->put(route('dashboard.categories.update', $category), ['name' => str_repeat('x', 256), 'slug' => 'taken', 'sort_order' => 'two'])
            ->assertSessionHasErrors([
                'name' => 'The category name field must not be greater than 255 characters.',
                'slug' => 'The slug has already been taken.',
                'sort_order' => 'The display order field must be an integer.',
            ]);
    }

    public function test_the_names_need_no_seeded_rows_and_create_none(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true]);

        // Not seeded: the English names, in either locale, and no translation row written.
        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->from(route('dashboard.categories.create'))
                ->post(route('dashboard.categories.store'), ['name' => ''])
                ->assertSessionHasErrors('name');
            $this->assertStringContainsString('category name', session('errors')->first('name'), $locale);
        }

        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Category_Attribute_%')->count());
    }
}
