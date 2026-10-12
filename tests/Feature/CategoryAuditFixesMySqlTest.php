<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AD-UI-CAT-01F-FIX-01 on MySQL: AUD-01 (sort_order is INT UNSIGNED: SQLite accepts any integer and cannot show
 * the overflow) and AUD-02 (Arabic slugs in the utf8mb4 column and its unique index, the case-insensitive
 * collation, a duplicate slug saved concurrently: error 1062).
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the variable of the other MySQL tests). Skipped otherwise.
 */
class CategoryAuditFixesMySqlTest extends TestCase
{
    private const CONNECTION = 'category_audit_mysql';

    private ?string $previousConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['database' => $database])]);
        DB::purge(self::CONNECTION);
        $this->assertSame($database, DB::connection(self::CONNECTION)->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS d')->d);
        $this->clear();
        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->previousConnection !== null) {
            $this->clear();
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_sort_order_beyond_int_unsigned_is_a_validation_error_not_a_500(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Max', 'sort_order' => '4294967295'])
            ->assertRedirect(route('dashboard.categories.index'));
        $this->assertSame(4294967295, (int) Category::query()->value('sort_order'));

        foreach (['4294967296', '18446744073709551616', '-1'] as $value) {
            $this->actingAs($admin, 'admin')->from(route('dashboard.categories.create'))
                ->post(route('dashboard.categories.store'), ['name' => 'Over '.$value, 'sort_order' => $value])
                ->assertRedirect(route('dashboard.categories.create'))
                ->assertSessionHasErrors('sort_order');
        }

        $this->actingAs($admin, 'admin')->postJson(route('dashboard.categories.store'), ['name' => 'Json', 'sort_order' => 4294967296])->assertStatus(422);

        $category = Category::query()->firstOrFail();
        $this->actingAs($admin, 'admin')->from(route('dashboard.categories.edit', $category))
            ->put(route('dashboard.categories.update', $category), ['name' => 'Max', 'slug' => $category->slug, 'sort_order' => '4294967296'])
            ->assertRedirect(route('dashboard.categories.edit', $category))
            ->assertSessionHasErrors('sort_order');
        $this->assertSame(1, Category::count());
    }

    public function test_arabic_slugs_are_stored_whole_and_unique_on_mysql(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'فخار يدوي'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'فخار يدوي'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Mixed', 'slug' => 'handmade-فخار-2026'])->assertSessionHasNoErrors();
        $this->assertSame(['فخار-يدوي', 'فخار-يدوي-2', 'handmade-فخار-2026'], Category::query()->orderBy('id')->pluck('slug')->all());

        // 255 Arabic letters (510 bytes) fit the column and its unique index; a duplicate is cut to stay within 255.
        $long = str_repeat('ب', 255);
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $long])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => $long])->assertSessionHasNoErrors();
        $this->assertSame([$long, str_repeat('ب', 253).'-2'], Category::query()->orderByDesc('id')->limit(2)->pluck('slug')->reverse()->values()->all());
        $this->assertSame(510, (int) DB::selectOne('SELECT MAX(OCTET_LENGTH(slug)) AS b FROM categories')->b);

        // The store filter finds the Arabic slug.
        $this->getJson('/shop/products?category='.rawurlencode('فخار-يدوي'))->assertOk();
    }

    public function test_a_slug_equal_under_the_collation_is_refused_cleanly(): void
    {
        $admin = $this->admin();
        // Stored before the format (e.g. by hand): upper case; the collation treats it as equal to its lower case.
        Category::create(['name' => 'Legacy', 'slug' => 'HANDMADE']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'New', 'slug' => 'handmade'])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
        $this->assertSame(1, Category::count());
    }

    public function test_a_duplicate_slug_saved_meanwhile_is_a_field_error_not_a_500(): void
    {
        $admin = $this->admin();

        // Another session saves the same slug right after this request checked it was free (error 1062 on save).
        $done = false;
        DB::listen(function ($query) use (&$done) {
            if (! $done && str_contains($query->sql, 'select count(*) as aggregate from `categories` where `slug`')) {
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
    }

    private function admin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    private function clear(): void
    {
        foreach (['products', 'categories', 'role_user', 'admins', 'languages'] as $table) {
            DB::connection(self::CONNECTION)->table($table)->delete();
        }
    }
}
