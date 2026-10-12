<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Support\Categories\CategoryDeleteConfirmation;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * N-CAT-1 (AD-UI-CAT-01E-3C): a category is deleted only with a confirmation the server issued for that
 * category and that admin, live and unused; the confirmation page works without JavaScript; DEC-CAT-Q3, the
 * abilities and CSRF still apply; a category deleted meanwhile is reported, never a 500.
 */
class CategoryDeleteConfirmationTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private const INVALID = 'The deletion was not confirmed or the confirmation expired. Confirm it again.';

    private const GONE = 'This category no longer exists. It may have been deleted already.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- refused without a valid confirmation

    public function test_a_delete_without_a_confirmation_is_refused_and_leads_to_the_confirmation_page(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        foreach ([[], ['confirmation' => ''], ['confirmation' => ['x']]] as $payload) {
            $this->actingAs($admin, 'admin')->from(route('dashboard.categories.index'))
                ->delete(route('dashboard.categories.destroy', $category), $payload)
                ->assertRedirect(route('dashboard.categories.delete', $category))
                ->assertSessionHas('danger', self::INVALID)
                ->assertSessionMissing('success');
        }

        $this->assertModelExists($category);
    }

    public function test_a_value_the_server_did_not_issue_is_refused(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        foreach (['1', 'true', 'confirmed', 'yes', str_repeat('a', 48)] as $forged) {
            $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $forged])
                ->assertSessionHas('danger', self::INVALID);
        }

        $this->assertModelExists($category);
    }

    public function test_a_confirmation_for_another_category_is_refused_and_used_up(): void
    {
        $pottery = $this->category('Pottery');
        $glass = $this->category('Glass');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $pottery);

        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $glass), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.categories.delete', $glass))
            ->assertSessionHas('danger', self::INVALID);
        $this->assertModelExists($glass);

        // Consumed by that attempt: it cannot be replayed on its own category either.
        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $pottery), ['confirmation' => $token])
            ->assertSessionHas('danger', self::INVALID);
        $this->assertModelExists($pottery);
    }

    public function test_a_confirmation_issued_to_another_admin_is_refused(): void
    {
        $category = $this->category('Pottery');
        $first = $this->admin(super: true);
        $second = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($first, $category);

        $this->actingAs($second, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertSessionHas('danger', self::INVALID);

        $this->assertModelExists($category);
    }

    public function test_an_expired_confirmation_is_refused(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        $this->travel(CategoryDeleteConfirmation::TTL + 1)->seconds();

        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.categories.delete', $category))
            ->assertSessionHas('danger', self::INVALID);
        $this->assertModelExists($category);
    }

    public function test_a_confirmation_cannot_be_used_twice(): void
    {
        $vendor = Vendor::factory()->create();
        $category = $this->category('Pottery');
        $product = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10]);
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        // First use: accepted as a confirmation, refused by DEC-CAT-Q3 (it has a product).
        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.index'))->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertSessionHas('danger', 'This category cannot be deleted because it has associated products.');

        // Even once the category is empty, the same confirmation does not delete it.
        DB::table('products')->where('id', $product)->delete();
        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertSessionHas('danger', self::INVALID);
        $this->assertModelExists($category);
    }

    // ---------------------------------------------------------------- accepted

    public function test_a_valid_confirmation_deletes_and_returns_to_the_list_with_its_filters(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $list = route('dashboard.categories.index', ['search' => 'Pot', 'page' => 1]);

        $this->confirmedDelete($admin, $category, $list)
            ->assertRedirect($list)
            ->assertSessionHas('success', 'Category deleted successfully.');

        $this->assertModelMissing($category);
        $this->assertSame([], session(CategoryDeleteConfirmation::SESSION_KEY));
    }

    public function test_the_list_issues_one_confirmation_per_deletable_row_for_its_admin(): void
    {
        $empty = $this->category('Empty');
        $parent = $this->category('Parent');
        $this->category('Child', $parent);
        $admin = $this->admin(super: true);

        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.categories.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#data-destroy="'.preg_quote(route('dashboard.categories.destroy', $empty), '#').'" data-confirmation="([A-Za-z0-9]{48})"#', $html, $match));
        $this->assertStringNotContainsString('data-destroy="'.route('dashboard.categories.destroy', $parent).'"', $html);

        // The dialog's DELETE (what the script sends) deletes with it.
        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.index'))
            ->delete(route('dashboard.categories.destroy', $empty), ['confirmation' => $match[1]])
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('success');
        $this->assertModelMissing($empty);

        // A viewer without categories.delete is issued nothing.
        $viewer = $this->admin(['categories.view']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.index'))->assertOk();
        $this->assertCount(0, array_filter(session(CategoryDeleteConfirmation::SESSION_KEY, []), fn ($entry) => $entry['admin'] === $viewer->id));
    }

    // ---------------------------------------------------------------- the page without JavaScript

    public function test_the_confirmation_page_deletes_only_after_confirm(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);
        $category = $this->category('فخار <b>');
        $admin = $this->admin(super: true);

        // The row's form is a GET to this page.
        $list = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.index'))->getContent();
        $this->assertStringContainsString('<form method="GET" action="'.route('dashboard.categories.delete', $category).'" data-category-delete', $list);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.categories.delete', $category))->assertOk()->getContent();
        $this->assertModelExists($category);

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="admin-header-title">حذف هذا التصنيف؟</h1>', $html);
        $this->assertStringContainsString('<bdi>فخار &lt;b&gt;</bdi>', $html);
        $this->assertStringContainsString('لا يمكن التراجع عن ذلك', $html);
        $this->assertStringContainsString('<span>تأكيد الحذف</span>', $html);
        $this->assertStringContainsString('<a href="'.route('dashboard.categories.index').'" class="btn btn-light-secondary">إلغاء</a>', $html);
        $this->assertSame(1, preg_match('#<form method="POST" action="'.preg_quote(route('dashboard.categories.destroy', $category), '#').'" data-category-delete-page>\s*<input type="hidden" name="_token" value="[^"]+"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<input type="hidden" name="confirmation" value="([A-Za-z0-9]{48})">#', $html, $match));

        // Confirm: back to the list (not to this page, which no longer applies).
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.delete', $category))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $match[1]])
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('success', 'تم حذف التصنيف بنجاح.');
        $this->assertModelMissing($category);
    }

    public function test_cancel_and_get_requests_never_delete(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        // Viewing the page twice and leaving through Cancel (a link to the list).
        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.index'))->assertOk();

        // No GET route deletes.
        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.destroy', $category))->assertStatus(405);
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('dashboard.categories.delete')->methods());
        $this->assertModelExists($category);
    }

    public function test_the_page_explains_a_category_that_cannot_be_deleted_and_offers_no_confirm(): void
    {
        $parent = $this->category('Parent');
        $this->category('Child', $parent);
        $admin = $this->admin(super: true);

        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $parent))->assertOk()->getContent();

        $this->assertStringContainsString('This category has subcategories and cannot be deleted. Move or delete them first.', $html);
        $this->assertStringNotContainsString('data-category-delete-page', $html);
        $this->assertStringNotContainsString('name="confirmation"', $html);
        $this->assertStringContainsString('class="btn btn-light-secondary">Cancel</a>', $html);
    }

    public function test_a_refused_confirmation_shows_its_message_on_the_page(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category))->assertRedirect(route('dashboard.categories.delete', $category));
        $html = $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category))->getContent();

        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-error" role="alert">.*?'.preg_quote(self::INVALID, '#').'#s', $html);
        $this->assertStringContainsString('data-category-delete-page', $html, 'A fresh confirmation is offered.');
    }

    // ---------------------------------------------------------------- abilities, CSRF

    public function test_the_page_and_the_delete_need_the_delete_ability(): void
    {
        $category = $this->category('Pottery');
        $editor = $this->admin(['categories.view', 'categories.edit']);

        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.delete', $category))->assertForbidden();
        // Even holding a confirmation (issued by hand here), the ability is checked first.
        $token = $this->issueDeleteConfirmation($editor, $category);
        $this->actingAs($editor, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])->assertForbidden();
        $this->assertModelExists($category);

        $deleter = $this->admin(['categories.view', 'categories.delete']);
        $this->confirmedDelete($deleter, $category)->assertSessionHas('success');
        $this->assertModelMissing($category);

        auth('admin')->logout();
        $other = $this->category('Glass');
        $this->get(route('dashboard.categories.delete', $other))->assertRedirect(route('admin.login'));
    }

    public function test_the_delete_needs_the_csrf_token(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        // CSRF verification is skipped while running unit tests; run it as outside tests.
        $this->app['env'] = 'local';

        try {
            $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertModelExists($category);
        $this->assertContains('web', Route::getRoutes()->getByName('dashboard.categories.destroy')->gatherMiddleware());
    }

    // ---------------------------------------------------------------- concurrency

    public function test_a_category_deleted_after_the_confirmation_was_shown_is_reported_not_a_500(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        DB::table('categories')->where('id', $category->id)->delete(); // Another admin, meanwhile.

        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.delete', $category))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('danger', self::GONE);

        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category->id))
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('danger', self::GONE);
        $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', 'abc'))->assertRedirect(route('dashboard.categories.index'));
    }

    public function test_a_category_deleted_during_the_request_is_reported_not_a_500(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        // Another request deletes it right after this one checked it exists, before the row lock.
        $done = false;
        DB::listen(function ($query) use ($category, &$done) {
            if (! $done && str_starts_with($query->sql, 'select exists(select * from "categories"')) {
                $done = true;
                DB::table('categories')->where('id', $category->id)->delete();
            }
        });

        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.index'))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('danger', self::GONE);
        $this->assertTrue($done);
    }

    public function test_a_product_added_after_the_confirmation_still_blocks_the_delete(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);
        $token = $this->issueDeleteConfirmation($admin, $category);

        DB::table('products')->insert(['vendor_id' => Vendor::factory()->create()->id, 'category_id' => $category->id, 'name' => 'Late', 'slug' => 'late', 'price' => 1]);

        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.index'))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertSessionHas('danger', 'This category cannot be deleted because it has associated products.');
        $this->assertModelExists($category);
    }

    public function test_the_session_keeps_a_bounded_number_of_confirmations(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        $first = $this->issueDeleteConfirmation($admin, $category);
        foreach (range(1, 129) as $ignored) {
            $last = $this->issueDeleteConfirmation($admin, $category);
        }

        $pending = session(CategoryDeleteConfirmation::SESSION_KEY);
        $this->assertCount(100, $pending);
        $this->assertArrayNotHasKey($first, $pending, 'The oldest are dropped.');
        $this->assertArrayHasKey($last, $pending);

        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $first])->assertSessionHas('danger', self::INVALID);
        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $last])->assertSessionHas('success');
    }

    public function test_the_pages_create_no_translation_rows(): void
    {
        $category = $this->category('Pottery');
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.categories.delete', $category))->assertOk();
            $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->delete(route('dashboard.categories.destroy', $category))->assertRedirect();
        }

        $this->assertSame(0, TranslationValue::whereIn('key', array_keys(AdminCategoryTranslationSeeder::TRANSLATIONS))->count());
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::create(['name' => $name, 'slug' => 'c-'.Category::query()->count().'-'.md5($name), 'parent_id' => $parent?->id]);
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
