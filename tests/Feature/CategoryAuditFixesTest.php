<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Support\Categories\CategorySlug;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * AD-UI-CAT-01F-FIX-01: the final audit findings AUD-01 (sort_order range), AUD-02 (Arabic/English slugs),
 * AUD-03 (a page past the last one), AUD-05 (redirects only to this module's pages on this origin) and
 * AUD-06 (status filter values). The MySQL side is in CategoryAuditFixesMySqlTest.
 */
class CategoryAuditFixesTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private const SLUG_INVALID = 'Use Arabic or English letters and numbers only, with single hyphens between words.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- AUD-01

    public function test_sort_order_accepts_the_column_maximum_and_refuses_more(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Max', 'sort_order' => '4294967295'])->assertRedirect(route('dashboard.categories.index'));
        $this->assertSame(4294967295, (int) Category::query()->where('name', 'Max')->value('sort_order'));

        foreach (['4294967296', '99999999999999999999', '-1', '1.5', 'abc', '1e3'] as $value) {
            $this->actingAs($admin, 'admin')->from(route('dashboard.categories.create'))
                ->post(route('dashboard.categories.store'), ['name' => 'Bad '.$value, 'sort_order' => $value])
                ->assertRedirect(route('dashboard.categories.create'))
                ->assertSessionHasErrors('sort_order');
        }

        $this->actingAs($admin, 'admin')->postJson(route('dashboard.categories.store'), ['name' => 'Json', 'sort_order' => 4294967296])
            ->assertStatus(422)->assertJsonValidationErrors('sort_order');

        $category = Category::query()->where('name', 'Max')->first();
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->putJson(route('dashboard.categories.update', $category), ['name' => 'Max', 'slug' => $category->slug, 'sort_order' => 4294967296])
            ->assertStatus(422)->assertJsonValidationErrors('sort_order');
        $this->assertSame(4294967295, (int) $category->fresh()->sort_order);
        $this->assertSame(1, Category::count());
    }

    // ---------------------------------------------------------------- AUD-02: typed slugs

    public function test_arabic_english_and_mixed_slugs_are_accepted(): void
    {
        $admin = $this->admin();

        foreach (['فخار-يدوي', 'handmade-pottery', 'فخار-2026', 'handmade-فخار', 'a', 'ب', '2026'] as $i => $slug) {
            $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => "Name {$i}", 'slug' => $slug])->assertSessionHasNoErrors();
            $this->assertSame($slug, Category::query()->where('name', "Name {$i}")->value('slug'), $slug);
        }
    }

    public function test_unsafe_slugs_are_refused(): void
    {
        $admin = $this->admin();
        $refused = [
            'Bad Slug/../x?y#z', 'فخار/يدوي', 'فخار?نوع=1', '-فخار', 'فخار-', 'فخار--يدوي', 'فخار يدوي', 'فخار_يدوي',
            'فخار.يدوي', "فخار\u{200B}يدوي", "فخار\u{200F}يدوي", "فخار\u{202E}يدوي", "a\tb", "a\nb", 'فخـار', 'فَخار',
            'فخار؟', 'فخار،يدوي', 'café', 'ÀB', '<script>', '%2F', '..', '-', 'پنیر',
        ];

        foreach ($refused as $slug) {
            $this->actingAs($admin, 'admin')->from(route('dashboard.categories.create'))
                ->post(route('dashboard.categories.store'), ['name' => 'Refused', 'slug' => $slug])
                ->assertSessionHasErrors(['slug' => self::SLUG_INVALID]);
        }

        $this->assertSame(0, Category::count());
    }

    public function test_typed_slugs_are_normalised_before_the_checks(): void
    {
        $admin = $this->admin();

        // A-Z lowercased, Arabic-Indic digits written 0-9, surrounding spaces trimmed.
        foreach (['Handmade-POTTERY' => 'handmade-pottery', '  فخار-٢٠٢٦  ' => 'فخار-2026', 'Rugs-۱۲' => 'rugs-12'] as $typed => $stored) {
            $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $typed, 'slug' => $typed])->assertSessionHasNoErrors();
            // (The request trims the name too.)
            $this->assertSame($stored, Category::query()->where('name', trim($typed))->value('slug'));
        }

        // Unique after normalisation: HANDMADE-POTTERY is the same slug.
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Again', 'slug' => 'HANDMADE-POTTERY'])
            ->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
    }

    public function test_slug_length_is_counted_in_characters_up_to_255(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Long', 'slug' => str_repeat('ب', 255)])->assertSessionHasNoErrors();
        $this->assertSame(255, mb_strlen(Category::query()->where('name', 'Long')->value('slug')));

        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Too long', 'slug' => str_repeat('ب', 256)])
            ->assertSessionHasErrors('slug');
    }

    // ---------------------------------------------------------------- AUD-02: generated slugs

    public function test_a_slug_is_made_from_the_name_keeping_arabic(): void
    {
        $admin = $this->admin();
        $cases = [
            'فخار يدوي' => 'فخار-يدوي',
            'Handmade Pottery' => 'handmade-pottery',
            'Handmade فخار 2026' => 'handmade-فخار-2026',
            'فَخّارٌ يدويّ' => 'فخار-يدوي',
            'فخـــار' => 'فخار',
            'سجاد ٢٠٢٦' => 'سجاد-2026',
            'Café & Crème' => 'cafe-creme',
            '  --فخار؟ (يدوي)!--  ' => 'فخار-يدوي',
        ];

        foreach ($cases as $name => $slug) {
            $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $name])->assertSessionHasNoErrors();
            $this->assertSame($slug, Category::query()->where('name', trim($name))->value('slug'), $name);
            Category::query()->delete(); // Several names give the same slug; each case starts free.
        }
    }

    public function test_a_generated_slug_is_made_unique_within_255_characters(): void
    {
        $admin = $this->admin();

        foreach ([1, 2, 3] as $ignored) {
            $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'فخار']);
        }
        $this->assertSame(['فخار', 'فخار-2', 'فخار-3'], Category::query()->orderBy('id')->pluck('slug')->all());

        $long = str_repeat('ب', 255);
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $long])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $long])->assertSessionHasNoErrors();
        $second = Category::query()->orderByDesc('id')->value('slug');
        $this->assertSame(255, mb_strlen($second));
        $this->assertSame(str_repeat('ب', 253).'-2', $second);
    }

    public function test_a_name_without_a_usable_slug_asks_for_one_and_invents_nothing(): void
    {
        $admin = $this->admin();

        foreach (['!!!', '؟؟', '---', '🙂', 'ـــ'] as $name) {
            $this->actingAs($admin, 'admin')->from(route('dashboard.categories.create'))
                ->post(route('dashboard.categories.store'), ['name' => $name])
                ->assertRedirect(route('dashboard.categories.create'))
                ->assertSessionHasErrors(['slug' => 'A slug cannot be made from this name. Enter a slug.']);
        }

        $this->assertSame(0, Category::count());
        $this->assertSame('', CategorySlug::fromName('!!!'));

        // With a slug typed, the same name is fine.
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => '!!!', 'slug' => 'special'])->assertSessionHasNoErrors();
    }

    public function test_an_existing_slug_is_kept_unchanged_on_edit(): void
    {
        $admin = $this->admin();
        // Created before the format existed (stored directly): kept, never rewritten.
        $old = Category::create(['name' => 'Old', 'slug' => 'Old_Slug.v1']);

        $this->actingAs($admin, 'admin')->put(route('dashboard.categories.update', $old), ['name' => 'Old renamed', 'slug' => 'Old_Slug.v1'])->assertSessionHasNoErrors();
        $this->assertSame(['Old renamed', 'Old_Slug.v1'], [$old->fresh()->name, $old->fresh()->slug]);

        // A different slug follows the format.
        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.edit', $old))
            ->put(route('dashboard.categories.update', $old), ['name' => 'Old', 'slug' => 'Old_Slug.v2'])
            ->assertSessionHasErrors(['slug' => self::SLUG_INVALID]);
        $this->assertSame('Old_Slug.v1', $old->fresh()->slug);

        // An Arabic slug edited to another valid one.
        $this->actingAs($admin, 'admin')->put(route('dashboard.categories.update', $old), ['name' => 'Old', 'slug' => 'قديم-1'])->assertSessionHasNoErrors();
        $this->assertSame('قديم-1', $old->fresh()->slug);
    }

    public function test_a_slug_taken_by_a_concurrent_save_is_a_field_error_not_a_500(): void
    {
        $admin = $this->admin();

        // Another request saves the same slug right after this one checked it was free.
        $done = false;
        DB::listen(function ($query) use (&$done) {
            if (! $done && str_contains($query->sql, 'select count(*) as aggregate from "categories" where "slug"')) {
                $done = true;
                DB::table('categories')->insert(['name' => 'Other', 'slug' => 'فخار-يدوي', 'sort_order' => 0, 'is_active' => 1]);
            }
        });

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Mine', 'slug' => 'فخار-يدوي'])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);

        $this->assertTrue($done);
        $this->assertSame(['Other'], Category::query()->pluck('name')->all());
        $this->assertSame('Mine', session('_old_input.name'));
    }

    public function test_the_store_filter_finds_an_arabic_slug(): void
    {
        Category::create(['name' => 'فخار يدوي', 'slug' => 'فخار-يدوي']);

        $this->getJson('/shop/products?category='.rawurlencode('فخار-يدوي'))->assertOk();
    }

    public function test_the_slug_messages_are_seeded_in_arabic(): void
    {
        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'X', 'slug' => 'فخار/يدوي'])
            ->assertSessionHasErrors(['slug' => 'استخدم حروفًا عربية أو إنجليزية وأرقامًا فقط، مع شرطة واحدة بين الكلمات.']);
        $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => '!!!'])
            ->assertSessionHasErrors(['slug' => 'لا يمكن إنشاء رابط مختصر من هذا الاسم. أدخل الرابط المختصر.']);
    }

    // ---------------------------------------------------------------- AUD-03

    public function test_a_page_past_the_last_is_not_shown_as_an_empty_list(): void
    {
        $admin = $this->admin();
        foreach (range(1, 16) as $i) {
            Category::create(['name' => sprintf('Pottery %02d', $i), 'slug' => "pottery-{$i}"]);
        }

        $html = $this->page($admin, ['page' => 9]);
        $this->assertStringContainsString('There are no categories on this page.', $html);
        $this->assertStringContainsString('href="'.e(route('dashboard.categories.index', ['page' => 2])).'"', $html);
        $this->assertStringNotContainsString('No categories yet.', $html);

        // With a search: the link keeps it.
        $html = $this->page($admin, ['search' => 'Pottery', 'page' => 5]);
        $this->assertStringContainsString('There are no categories on this page.', $html);
        $this->assertStringContainsString('href="'.e(route('dashboard.categories.index', ['search' => 'Pottery', 'page' => 2])).'"', $html);

        // A search with no results and a truly empty list keep their own messages.
        $this->assertStringContainsString('No categories match these filters.', $this->page($admin, ['search' => 'zzz']));
        $this->assertStringContainsString('No categories match these filters.', $this->page($admin, ['search' => 'zzz', 'page' => 3]));
        Category::query()->delete();
        $this->assertStringContainsString('No categories yet.', $this->page($admin, ['page' => 4]));

        // Pagination links keep the filters.
        foreach (range(1, 16) as $i) {
            Category::create(['name' => sprintf('Glass %02d', $i), 'slug' => "glass-{$i}"]);
        }
        $this->assertStringContainsString(e(route('dashboard.categories.index', ['search' => 'Glass', 'level' => 'root', 'is_active' => '1', 'page' => 2])), $this->page($admin, ['search' => 'Glass', 'level' => 'root', 'is_active' => '1']));
    }

    // ---------------------------------------------------------------- AUD-05

    public function test_after_a_delete_the_admin_returns_only_to_this_module_on_this_origin(): void
    {
        $admin = $this->admin();
        $list = route('dashboard.categories.index');
        $root = rtrim(url('/'), '/');
        $parts = parse_url($root);
        $port = $parts['port'] ?? 80;
        $cases = [
            'internal list with filters' => [$list.'?search=pot&is_active=1&page=2', $list.'?search=pot&is_active=1&page=2'],
            'external host' => ['https://evil.example/dashboard/categories', $list],
            'look-alike host' => ["http://{$parts['host']}.evil.example:{$port}/dashboard/categories", $list],
            'credentials before the host' => ["http://evil.example@{$parts['host']}:{$port}/dashboard/categories", $list],
            'protocol-relative' => ['//evil.example/dashboard/categories', $list],
            'other scheme' => ["https://{$parts['host']}:{$port}/dashboard/categories", $list],
            'other port' => ["http://{$parts['host']}:".($port + 1).'/dashboard/categories', $list],
            'javascript' => ['javascript:alert(1)//dashboard/categories', $list],
            'dot segments' => [$root.'/dashboard/categories/../admins', $list],
            'encoded slash' => [$root.'/dashboard/categories%2F..%2Fadmins', $list],
            'backslash' => [$root.'/dashboard/categories\\evil', $list],
            'another page of the app' => [$root.'/dashboard/admins', $list],
            'not a url' => ['%%%', $list],
        ];

        foreach ($cases as $label => [$referer, $expected]) {
            $category = Category::create(['name' => $label, 'slug' => 'c-'.md5($label)]);
            $token = $this->issueDeleteConfirmation($admin, $category);
            $this->actingAs($admin, 'admin')->withHeader('referer', $referer)
                ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
                ->assertRedirect($expected);
            $this->assertModelMissing($category);
        }

        // No Referer at all: the list.
        $category = Category::create(['name' => 'No referer', 'slug' => 'no-referer']);
        $token = $this->issueDeleteConfirmation($admin, $category);
        $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])->assertRedirect($list);
    }

    public function test_a_refused_delete_and_a_failed_save_go_back_only_to_this_module(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
        $confirm = route('dashboard.categories.delete', $parent);

        // Refused delete (it has a subcategory): back to the confirmation page, never outside.
        $token = $this->issueDeleteConfirmation($admin, $parent);
        $this->actingAs($admin, 'admin')->withHeader('referer', $confirm)->delete(route('dashboard.categories.destroy', $parent), ['confirmation' => $token])->assertRedirect($confirm);
        $token = $this->issueDeleteConfirmation($admin, $parent);
        $this->actingAs($admin, 'admin')->withHeader('referer', 'https://evil.example/dashboard/categories/1/delete')->delete(route('dashboard.categories.destroy', $parent), ['confirmation' => $token])->assertRedirect(route('dashboard.categories.index'));

        // Validation errors: back to the form the admin used, or to the form itself.
        $create = route('dashboard.categories.create');
        $this->actingAs($admin, 'admin')->withHeader('referer', $create)->post(route('dashboard.categories.store'), ['name' => ''])->assertRedirect($create);
        $this->actingAs($admin, 'admin')->withHeader('referer', 'https://evil.example/dashboard/categories/create')->post(route('dashboard.categories.store'), ['name' => ''])->assertRedirect($create);
        $edit = route('dashboard.categories.edit', $parent);
        $this->actingAs($admin, 'admin')->withHeader('referer', '//evil.example/x')->put(route('dashboard.categories.update', $parent), ['name' => ''])->assertRedirect($edit);
        // A hierarchy refusal (thrown inside the transaction) too.
        $this->actingAs($admin, 'admin')->withHeader('referer', 'https://evil.example/')->put(route('dashboard.categories.update', $parent), ['name' => 'Parent', 'slug' => 'parent', 'parent_id' => $parent->id])->assertRedirect($edit)->assertSessionHasErrors('parent_id');
    }

    // ---------------------------------------------------------------- AUD-06

    public function test_the_status_filter_accepts_only_its_two_values(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Shown on', 'slug' => 'on', 'is_active' => true]);
        Category::create(['name' => 'Shown off', 'slug' => 'off', 'is_active' => false]);

        // (' 1' is trimmed to '1' by the request, so it is a valid value.)
        foreach (['abc', '2', 'true', 'yes', '01', '-1'] as $value) {
            $html = $this->page($admin, ['is_active' => $value]);
            $this->assertStringContainsString('<bdi>Shown on</bdi>', $html, $value);
            $this->assertStringContainsString('<bdi>Shown off</bdi>', $html, $value);
            $this->assertStringNotContainsString('>Reset</a>', $html, "{$value}: not a filter");
        }

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.categories.index').'?is_active[]=1')->assertOk()->getContent();
        $this->assertStringContainsString('<bdi>Shown off</bdi>', $html);

        $this->assertStringNotContainsString('<bdi>Shown off</bdi>', $this->page($admin, ['is_active' => '1']));
        $this->assertStringNotContainsString('<bdi>Shown on</bdi>', $this->page($admin, ['is_active' => '0']));
        $this->assertStringContainsString('>Reset</a>', $this->page($admin, ['is_active' => '0']));
        $this->assertStringNotContainsString('>Reset</a>', $this->page($admin, ['level' => 'zzz']));
    }

    public function test_no_translation_row_is_created(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Only', 'slug' => 'only']);
        $this->page($admin, ['page' => 7]);
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => '!!!']);
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'X', 'slug' => 'a/b']);

        $this->assertSame(0, TranslationValue::whereIn('key', array_keys(AdminCategoryTranslationSeeder::TRANSLATIONS))->count());
    }

    private function page(Admin $admin, array $query): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.categories.index', $query))->assertOk()->getContent();
    }

    private function admin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }
}
