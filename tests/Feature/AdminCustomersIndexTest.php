<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminCustomerTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminHomeTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CUS-UI-05: the admin customers list (/dashboard/customers). Access, both languages, the approved t() keys read
 * without auto-creating rows, the customers with their data and status badges, search and status filter as shown by
 * the page, the shared pagination, the empty states, escaping, labelled fields, no status-change control on the list,
 * and the page band hidden by its own CSS rule. CUS-BE-001 (fixed): the search is grouped with the status filter and
 * matches the LIKE wildcards literally; its regression tests cover name, email, phone, the filter, pagination and access.
 */
class AdminCustomersIndexTest extends TestCase
{
    use RefreshDatabase;

    /** The shared keys the controller read before CUS-BE-004. */
    private const SHARED_STATUS_KEYS = ['dashboard.Active', 'dashboard.Pending', 'dashboard.Blocked'];

    /** The keys the controller reads now (without auto-create). */
    private const CUSTOMER_STATUS_KEYS = ['dashboard.Customers_Status_Active', 'dashboard.Customers_Status_Pending', 'dashboard.Customers_Status_Blocked', 'dashboard.Customer_status_updated'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- access

    public function test_an_admin_with_customers_view_sees_the_list(): void
    {
        $this->customer(['name' => 'Omar']);

        $this->page($this->admin(['customers.view']))
            ->assertOk()
            ->assertSee('id="admin-customers"', false)
            ->assertSee('Omar');
    }

    public function test_guests_and_admins_without_customers_view_are_refused(): void
    {
        $this->get(route('dashboard.customers.index'))->assertRedirect();

        foreach ([[], ['customers.edit'], ['products.view', 'orders.view']] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')->get(route('dashboard.customers.index'))->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- languages and translations

    public function test_arabic_is_right_to_left_with_the_approved_keys_and_no_unintended_english(): void
    {
        $this->seedTranslations();
        $this->customer(['name' => 'علي', 'email' => 'ali@example.test', 'phone' => '0599000001', 'status' => 'blocked']);
        $this->customer(['name' => 'منى', 'email' => 'mona@example.test', 'phone' => '0599000002', 'status' => 'pending']);
        $this->customer(['name' => 'عمر', 'email' => 'omar@example.test', 'phone' => '0599000003', 'status' => 'active']);

        $html = $this->page($this->admin(super: true), 'ar')->getContent();
        $text = $this->contentText($html);

        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $html);
        foreach (['تابع حسابات العملاء في المتجر', 'البحث في العملاء', 'حالة الحساب', 'كل الحالات', 'تطبيق', 'العميل', 'رقم الهاتف', 'الطلبات', 'التقييمات', 'الحالة', 'عرض', 'نشط', 'بانتظار التفعيل', 'محظور', 'عرض 1 إلى 3 من 3'] as $arabic) {
            $this->assertStringContainsString($arabic, $text, $arabic);
        }
        foreach (['Customer', 'Phone', 'Status', 'View', 'Filter', 'Reset', 'All statuses', 'Search customers', 'No records found', 'Active', 'Pending', 'Blocked', 'Apply', 'Orders', 'Reviews', 'Actions', 'Showing'] as $english) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($english, '/').'\b/u', $text, "Unintended English: {$english}");
        }
    }

    public function test_english_is_left_to_right(): void
    {
        $this->seedTranslations();
        $this->customer(['status' => 'pending']);

        $html = $this->page($this->admin(super: true), 'en')->getContent();

        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $html);
        foreach (['Follow customer accounts in the store', 'Search customers', 'Account status', 'Pending activation', 'Showing 1 to 1 of 1'] as $english) {
            $this->assertStringContainsString($english, $html, $english);
        }
    }

    public function test_the_page_texts_come_from_the_approved_keys(): void
    {
        $blade = file_get_contents(resource_path('views/dashboard/customers/index.blade.php'));
        preg_match_all("/\\\$custText\('([A-Za-z_]+)'/", $blade, $matches);

        $approved = array_map(fn ($key) => substr($key, strlen('dashboard.')), array_keys(AdminCustomerTranslationSeeder::TRANSLATIONS));
        $reused = ['Nav_Customers', 'Orders', 'Reviews', 'Actions', 'Pagination_Page'];
        foreach (array_unique($matches[1]) as $key) {
            $this->assertContains($key, [...$approved, ...$reused], "{$key} is not an approved key.");
        }
        $this->assertStringNotContainsString('{{ t(', $blade, 'Texts go through $custText (no auto-create).');
        $this->assertStringNotContainsString('__(', $blade);
    }

    public function test_rendering_writes_no_translation_row_for_the_page_texts(): void
    {
        $this->customer();
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            $this->page($admin, $locale);
            $this->page($admin, $locale, ['search' => 'zzz']);
        }

        $this->assertSame([], $this->pageKeyRows(), 'The page never auto-creates its keys.');
        // The keys the previous page read with t() (auto-create on), no longer read by the view.
        $old = ['Customer', 'Phone', 'Status', 'View', 'Filter', 'Reset', 'All', 'Search_customers', 'No_records_found', 'Home'];
        $this->assertSame([], TranslationValue::whereIn('key', array_map(fn ($key) => 'dashboard.'.$key, $old))->pluck('key')->unique()->values()->all());
    }

    public function test_the_translation_row_check_detects_a_written_page_key(): void
    {
        // CUS-UI-06B: the check filters the keys in PHP, because on SQLite LIKE has no escape character by default,
        // so 'dashboard.Customers\_%' matches nothing and an assertion built on it could never fail.
        TranslationValue::create(['key' => 'dashboard.Customers_Intro', 'locale' => 'ar', 'value' => 'Follow customer accounts']);
        TranslationValue::create(['key' => 'dashboard.Orders_Status_Pending', 'locale' => 'ar', 'value' => 'Pending']);
        TranslationValue::create(['key' => 'dashboard.Customers', 'locale' => 'ar', 'value' => 'العملاء']);

        $this->assertSame(['dashboard.Customers_Intro', 'dashboard.Orders_Status_Pending'], $this->pageKeyRows());
        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Customers\_%')->count(), 'The LIKE form misses the row it should find.');
    }

    // ---------------------------------------------------------------- data

    public function test_customers_show_with_their_data_counts_and_status_badges(): void
    {
        $ali = $this->customer(['name' => 'Ali', 'email' => 'ali@example.test', 'phone' => '0599000001', 'status' => 'blocked']);
        $this->customer(['name' => 'Mona', 'email' => 'mona@example.test', 'phone' => '0599000002', 'status' => 'pending']);
        $this->customer(['name' => 'Omar', 'email' => 'omar@example.test', 'phone' => '0599000003', 'status' => 'active']);
        foreach ([1, 2] as $n) {
            DB::table('orders')->insert(['number' => "O-{$n}", 'customer_id' => $ali->id, 'payment_method' => 'cod', 'created_at' => now(), 'updated_at' => now()]);
        }

        $html = $this->page($this->admin(super: true))->getContent();

        $this->assertStringContainsString('<a href="'.route('dashboard.customers.show', $ali).'" class="admin-products-name-link"><bdi>Ali</bdi></a>', $html);
        $this->assertStringContainsString('<p class="admin-products-code" dir="ltr">ali@example.test</p>', $html);
        $this->assertStringContainsString('<span dir="ltr">0599000001</span>', $html);
        $this->assertMatchesRegularExpression('#data-customer-id="'.$ali->id.'".*?<span class="admin-products-label">Orders</span>\s*2\s*</td>#s', $html);
        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">Blocked</span>', $html);
        $this->assertStringContainsString('<span class="admin-products-badge is-low">Pending activation</span>', $html);
        $this->assertStringContainsString('<span class="admin-products-badge is-active">Active</span>', $html);
        $this->assertSame(3, substr_count($html, 'data-customer-id='));
    }

    public function test_names_are_escaped(): void
    {
        $this->customer(['name' => '<script>alert(1)</script><img src=x onerror=alert(2)>']);

        $html = $this->page($this->admin(super: true))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('<bdi>&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(2)&gt;</bdi>', $html);
    }

    // ---------------------------------------------------------------- search, filter, pagination

    public function test_search_and_status_filter_as_shown_by_the_page(): void
    {
        $this->customer(['name' => 'Ali', 'email' => 'ali@example.test', 'status' => 'blocked']);
        $this->customer(['name' => 'Omar', 'email' => 'omar@example.test', 'status' => 'active']);
        $admin = $this->admin(super: true);

        $html = $this->page($admin, 'en', ['search' => 'Omar'])->getContent();
        $this->assertSame(1, substr_count($html, 'data-customer-id='));
        $this->assertStringContainsString('value="Omar"', $html);
        $this->assertStringContainsString('>Clear filters</a>', $html);

        $html = $this->page($admin, 'en', ['status' => 'blocked'])->getContent();
        $this->assertStringContainsString('<bdi>Ali</bdi>', $html);
        $this->assertStringNotContainsString('<bdi>Omar</bdi>', $html);
        $this->assertStringContainsString('<option value="blocked" selected>', $html);

        $this->assertStringNotContainsString('>Clear filters</a>', $this->page($admin)->getContent(), 'No clear link without a filter.');
    }

    // CUS-BE-001: the search alternatives (name, email, phone) are grouped, so they never bypass the status filter,
    // and the LIKE wildcards typed by the admin are matched literally.

    public function test_cus_be_001_search_combined_with_the_status_filter(): void
    {
        $this->customer(['name' => 'Ali', 'email' => 'ali@example.test', 'status' => 'blocked']);
        $alia = $this->customer(['name' => 'Alia', 'email' => 'alia@example.test', 'status' => 'active']);

        $html = $this->page($this->admin(super: true), 'en', ['search' => 'Ali', 'status' => 'active'])->getContent();

        $this->assertSame([$alia->id], $this->rowIds($html));
        $this->assertStringNotContainsString('<bdi>Ali</bdi>', $html);
    }

    public function test_cus_be_001_search_by_name_with_the_status_filter(): void
    {
        $this->customer(['name' => 'Sami Haddad', 'status' => 'active']);
        $pending = $this->customer(['name' => 'Sami Khalil', 'status' => 'pending']);
        $this->customer(['name' => 'Sami Nasser', 'status' => 'blocked']);

        $this->assertSame([$pending->id], $this->rowIds($this->page($this->admin(super: true), 'en', ['search' => 'Sami', 'status' => 'pending'])->getContent()));
    }

    public function test_cus_be_001_search_by_email_with_the_status_filter(): void
    {
        $active = $this->customer(['name' => 'Rami', 'email' => 'rami@shop.test', 'status' => 'active']);
        $this->customer(['name' => 'Huda', 'email' => 'huda@shop.test', 'status' => 'blocked']);
        $this->customer(['name' => 'Nour', 'email' => 'nour@other.test', 'status' => 'active']);

        $this->assertSame([$active->id], $this->rowIds($this->page($this->admin(super: true), 'en', ['search' => 'shop.test', 'status' => 'active'])->getContent()));
    }

    public function test_cus_be_001_search_by_phone_with_the_status_filter(): void
    {
        $this->customer(['name' => 'Rami', 'phone' => '0598111001', 'status' => 'active']);
        $pending = $this->customer(['name' => 'Huda', 'phone' => '0598111002', 'status' => 'pending']);
        $this->customer(['name' => 'Nour', 'phone' => '0597222003', 'status' => 'pending']);

        $this->assertSame([$pending->id], $this->rowIds($this->page($this->admin(super: true), 'en', ['search' => '0598111', 'status' => 'pending'])->getContent()));
    }

    public function test_cus_be_001_a_blocked_customer_never_shows_under_active(): void
    {
        // Matching by name, by email and by phone: each alternative alone used to bypass the filter.
        $this->customer(['name' => 'Zaid Blocked', 'email' => 'zaid.blocked@example.test', 'phone' => '0597333001', 'status' => 'blocked']);
        $this->customer(['name' => 'Other Blocked', 'email' => 'zaid.other@example.test', 'phone' => '0597333002', 'status' => 'blocked']);
        $active = $this->customer(['name' => 'Zaid Active', 'email' => 'zaid.active@example.test', 'phone' => '0597333003', 'status' => 'active']);
        $admin = $this->admin(super: true);

        foreach (['Zaid', 'zaid.', '0597333'] as $search) {
            $html = $this->page($admin, 'en', ['search' => $search, 'status' => 'active'])->getContent();

            $this->assertSame([$active->id], $this->rowIds($html), $search);
            $this->assertStringNotContainsString('Blocked</bdi>', $html, $search);
            $this->assertStringNotContainsString('is-suspended', $html, $search);
        }
    }

    public function test_cus_be_001_a_percent_sign_is_searched_literally(): void
    {
        $percent = $this->customer(['name' => 'Shop 100% Natural']);
        $this->customer(['name' => 'Shop 1000 Natural']);
        $this->customer(['name' => 'Shop Natural']);

        $admin = $this->admin(super: true);
        $this->assertSame([$percent->id], $this->rowIds($this->page($admin, 'en', ['search' => '100%'])->getContent()));
        $this->assertSame([$percent->id], $this->rowIds($this->page($admin, 'en', ['search' => '%'])->getContent()), 'A lone % matches only the name that contains it.');
    }

    public function test_cus_be_001_an_underscore_is_searched_literally(): void
    {
        $underscore = $this->customer(['name' => 'Underscore', 'email' => 'ali_shop@example.test']);
        $this->customer(['name' => 'Any Character', 'email' => 'alixshop@example.test']);

        $admin = $this->admin(super: true);
        $this->assertSame([$underscore->id], $this->rowIds($this->page($admin, 'en', ['search' => 'ali_shop'])->getContent()));
        $this->assertSame([$underscore->id], $this->rowIds($this->page($admin, 'en', ['search' => '_'])->getContent()));
    }

    public function test_cus_be_001_the_escape_character_and_a_backslash_are_searched_literally(): void
    {
        $bang = $this->customer(['name' => 'Wow! Store']);
        $slash = $this->customer(['name' => 'Back\\Slash']);
        $this->customer(['name' => 'Wow Store']);
        $this->customer(['name' => 'BackSlash']);
        $admin = $this->admin(super: true);

        $this->assertSame([$bang->id], $this->rowIds($this->page($admin, 'en', ['search' => 'Wow!'])->getContent()));
        $this->assertSame([$bang->id], $this->rowIds($this->page($admin, 'en', ['search' => '!'])->getContent()));
        $this->assertSame([$slash->id], $this->rowIds($this->page($admin, 'en', ['search' => 'Back\\Slash'])->getContent()));
    }

    public function test_cus_be_001_search_without_a_filter_matches_every_status(): void
    {
        $blocked = $this->customer(['name' => 'Layla One', 'status' => 'blocked']);
        $pending = $this->customer(['name' => 'Other', 'email' => 'layla.two@example.test', 'status' => 'pending']);
        $active = $this->customer(['name' => 'Third', 'phone' => '0596444001', 'status' => 'active']);
        $this->customer(['name' => 'Nobody', 'status' => 'active']);
        $admin = $this->admin(super: true);

        $this->assertEqualsCanonicalizing([$blocked->id, $pending->id], $this->rowIds($this->page($admin, 'en', ['search' => 'layla'])->getContent()));
        $this->assertSame([$active->id], $this->rowIds($this->page($admin, 'en', ['search' => '0596444'])->getContent()));
    }

    public function test_cus_be_001_the_filter_without_a_search(): void
    {
        $ids = [];
        foreach (['active', 'pending', 'blocked', 'active', 'blocked'] as $status) {
            $ids[$status][] = $this->customer(['status' => $status])->id;
        }
        $admin = $this->admin(super: true);

        foreach ($ids as $status => $expected) {
            $this->assertEqualsCanonicalizing($expected, $this->rowIds($this->page($admin, 'en', ['status' => $status])->getContent()), $status);
        }
        $this->assertCount(5, $this->rowIds($this->page($admin, 'en', ['search' => '', 'status' => ''])->getContent()), 'Empty values filter nothing.');
    }

    public function test_cus_be_001_pagination_and_order_with_the_search_and_the_filter(): void
    {
        $expected = [];
        for ($i = 1; $i <= 20; $i++) {
            // Newest first: Match 1 is the most recent.
            $expected[] = $this->customer(['name' => "Match {$i}", 'status' => 'active', 'created_at' => now()->subMinutes($i)])->id;
        }
        for ($i = 1; $i <= 4; $i++) {
            $this->customer(['name' => "Match blocked {$i}", 'status' => 'blocked', 'created_at' => now()]);
            $this->customer(['name' => "Other {$i}", 'status' => 'active', 'created_at' => now()]);
        }
        $admin = $this->admin(super: true);
        $query = ['search' => 'Match', 'status' => 'active'];

        $first = $this->page($admin, 'en', $query)->getContent();
        $this->assertSame(array_slice($expected, 0, 15), $this->rowIds($first));
        $this->assertStringContainsString('Showing 1 to 15 of 20', $first);
        $this->assertMatchesRegularExpression('#href="[^"]*\?search=Match&amp;status=active&amp;page=2"#', $first, 'The page links keep the search and the filter.');
        $this->assertStringContainsString('value="Match"', $first);
        $this->assertStringContainsString('<option value="active" selected>', $first);

        $second = $this->page($admin, 'en', $query + ['page' => 2])->getContent();
        $this->assertSame(array_slice($expected, 15), $this->rowIds($second));
        $this->assertStringContainsString('Showing 16 to 20 of 20', $second);
    }

    public function test_cus_be_001_access_is_unchanged_with_a_search_and_a_filter(): void
    {
        $this->customer(['name' => 'Ali', 'status' => 'active']);
        $query = ['search' => 'Ali', 'status' => 'active'];

        $this->get(route('dashboard.customers.index', $query))->assertRedirect();
        foreach ([[], ['customers.edit'], ['products.view', 'orders.view']] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')->get(route('dashboard.customers.index', $query))->assertForbidden();
        }
        $this->assertCount(1, $this->rowIds($this->page($this->admin(['customers.view']), 'en', $query)->getContent()));
        $this->assertCount(1, $this->rowIds($this->page($this->admin(super: true), 'en', $query)->getContent()));
    }

    // CUS-BE-002: search and status are read only as single strings, as in the products list (PRD-19) and the
    // categories list (AUD-06): an array or an unknown status is ignored (the list is shown unfiltered), never a 500.
    // The route only renders HTML, so a JSON request gets the same page; nothing becomes a 422 or a redirect.

    /** @return array<string, array{string}> */
    public static function arrayQueries(): array
    {
        return [
            'search[]' => ['search[]=x'],
            'search[key]' => ['search[foo]=bar'],
            'search[][]' => ['search[][]=Ali'],
            'status[]' => ['status[]=active'],
            'status[key]' => ['status[foo]=active'],
            'status[][]' => ['status[][]=blocked'],
            'both' => ['search[]=Ali&status[]=active'],
        ];
    }

    /** @dataProvider arrayQueries */
    public function test_cus_be_002_an_array_is_ignored_for_an_html_request(string $query): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();

        $response = $this->actingAs($this->admin(super: true), 'admin')->withSession(['locale' => 'en'])
            ->get(route('dashboard.customers.index').'?'.$query)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html = $response->getContent();

        $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($html), 'Unfiltered list.');
        $this->assertSame([], $this->selectedStatuses($html), 'The status filter shows "All statuses".');
        $this->assertStringContainsString('id="customers-search" name="search" value=""', $html);
        $this->assertStringNotContainsString('Array', $this->contentText($html));
    }

    /** @dataProvider arrayQueries */
    public function test_cus_be_002_an_array_is_ignored_for_a_json_request(string $query): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();

        $response = $this->actingAs($this->admin(super: true), 'admin')->withSession(['locale' => 'en'])
            ->getJson(route('dashboard.customers.index').'?'.$query)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($response->getContent()));
    }

    public function test_cus_be_002_an_array_never_reaches_the_query(): void
    {
        $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);

        DB::enableQueryLog();
        foreach (['search[]=Ali', 'search[foo]=Ali', 'status[]=active', 'status[foo]=blocked'] as $query) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.customers.index').'?'.$query)->assertOk();
        }

        $customerQueries = array_filter(DB::getQueryLog(), fn (array $entry) => str_contains($entry['query'], 'from "customers"'));
        $this->assertNotEmpty($customerQueries);
        foreach ($customerQueries as $entry) {
            $this->assertStringNotContainsString(' like ', $entry['query']);
            $this->assertDoesNotMatchRegularExpression('/"status" = \?|"customers"\."status" = \?/', $entry['query']);
            $this->assertNotContains('Ali', $entry['bindings']);
            $this->assertNotContains('%Ali%', $entry['bindings']);
        }
    }

    public function test_cus_be_002_empty_values_filter_nothing(): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);

        foreach (['search=', 'status=', 'search=&status=', 'search=%20%20&status=', 'search=%20&status=%20'] as $query) {
            $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.customers.index').'?'.$query)->assertOk()->getContent();
            $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($html), $query);
            $this->assertStringNotContainsString('>Clear filters</a>', $html, $query);
        }
    }

    public function test_cus_be_002_an_unknown_status_is_ignored(): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);

        foreach (['invalid', 'Active', 'ACTIVE', 'deleted', '1', '0', 'active,blocked', "active'--"] as $status) {
            $html = $this->page($admin, 'en', ['status' => $status])->getContent();
            $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($html), $status);
            $this->assertSame([], $this->selectedStatuses($html), $status);
        }

        $this->actingAs($admin, 'admin')->getJson(route('dashboard.customers.index', ['status' => 'invalid']))->assertOk();
        // An unknown status alongside a search keeps the search.
        $this->assertSame([$ali->id], $this->rowIds($this->page($admin, 'en', ['search' => 'Ali', 'status' => 'invalid'])->getContent()));
    }

    public function test_cus_be_002_valid_search_and_filter_still_work(): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();
        $pending = $this->customer(['name' => 'Alia', 'status' => 'pending']);
        $admin = $this->admin(super: true);

        $this->assertEqualsCanonicalizing([$ali->id, $pending->id], $this->rowIds($this->page($admin, 'en', ['search' => 'Ali'])->getContent()));
        $this->assertSame([$omar->id], $this->rowIds($this->page($admin, 'en', ['status' => 'active'])->getContent()));
        $this->assertSame([$ali->id], $this->rowIds($this->page($admin, 'en', ['status' => 'blocked'])->getContent()));
        $this->assertSame([$pending->id], $this->rowIds($this->page($admin, 'en', ['search' => 'Ali', 'status' => 'pending'])->getContent()));
        $this->assertSame([$ali->id], $this->rowIds($this->page($admin, 'en', ['search' => ' Ali ', 'status' => 'blocked'])->getContent()), 'Trimmed.');
        $this->assertSame([], $this->rowIds($this->page($admin, 'en', ['search' => 'Omar', 'status' => 'blocked'])->getContent()));
        // A valid search with an array status: the search applies, the status is ignored.
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.customers.index').'?search=Omar&status[]=blocked')->assertOk()->getContent();
        $this->assertSame([$omar->id], $this->rowIds($html));
    }

    // CUS-UI-CLEANUP-01: Clear filters appears only for a filter the controller applies.

    public function test_clear_filters_follows_the_filters_the_controller_applies(): void
    {
        [$ali, $omar] = $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);
        $get = fn (string $query): string => $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->get(route('dashboard.customers.index').'?'.$query)->assertOk()->getContent();

        $html = $get('status=invalid');
        $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($html));
        $this->assertStringNotContainsString('>Clear filters</a>', $html, 'status=invalid');

        $html = $get('status=active');
        $this->assertSame([$omar->id], $this->rowIds($html));
        $this->assertStringContainsString('>Clear filters</a>', $html, 'status=active');

        $html = $get('search=Ali');
        $this->assertSame([$ali->id], $this->rowIds($html));
        $this->assertStringContainsString('>Clear filters</a>', $html, 'search=Ali');

        $html = $get('search=Ali&status=invalid');
        $this->assertSame([$ali->id], $this->rowIds($html));
        $this->assertSame([], $this->selectedStatuses($html));
        $this->assertStringContainsString('>Clear filters</a>', $html, 'search=Ali&status=invalid (the search only)');

        $html = $get('search[]=x&status[]=active');
        $this->assertEqualsCanonicalizing([$ali->id, $omar->id], $this->rowIds($html));
        $this->assertStringNotContainsString('>Clear filters</a>', $html, 'search[]=x&status[]=active');
    }

    public function test_cus_be_002_invalid_values_never_bypass_access(): void
    {
        $this->blockedAliAndActiveOmar();
        $url = route('dashboard.customers.index').'?search[]=x&status[]=active';

        $this->get($url)->assertRedirect(route('admin.login'));
        $this->getJson($url)->assertUnauthorized();
        foreach ([[], ['customers.edit'], ['products.view', 'orders.view']] as $abilities) {
            $admin = $this->admin($abilities);
            $this->actingAs($admin, 'admin')->get($url)->assertForbidden();
            $this->actingAs($admin, 'admin')->getJson($url)->assertForbidden();
            $this->actingAs($admin, 'admin')->get(route('dashboard.customers.index').'?status=invalid')->assertForbidden();
        }
        $this->actingAs($this->admin(['customers.view']), 'admin')->get($url)->assertOk();
    }

    // CUS-BE-004: the status labels come from the customer-specific keys, read without auto-creating rows; the filter
    // uses the fixed list of values. The shared dashboard.Active / Pending / Blocked keys are never read or written.
    // On an empty translations table the admin shell (layout, partials, language switcher) still auto-creates its
    // own keys on any dashboard page; those rows are told apart by their source and are out of scope here.

    public function test_cus_be_004_the_list_writes_no_translation_row_of_its_own(): void
    {
        $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);

        foreach (['ar', 'en'] as $locale) {
            foreach ([[], ['status' => 'pending'], ['status' => 'invalid'], ['search' => 'Ali', 'status' => 'blocked']] as $query) {
                $written = $this->writtenKeys(fn () => $this->page($admin, $locale, $query));
                $this->assertShellKeysOnly($written, $locale.' '.json_encode($query));
            }
        }
        $this->assertSame([], TranslationValue::whereIn('key', self::CUSTOMER_STATUS_KEYS)->pluck('key')->all());
    }

    public function test_cus_be_004_the_shared_status_keys_are_not_created_after_the_admin_seeders(): void
    {
        $this->seedTranslations();
        $this->blockedAliAndActiveOmar();
        $admin = $this->admin(super: true);

        // (A few keys of the admin shell are not in these seeders, so the shell may still write them.)
        foreach (['ar', 'en'] as $locale) {
            $this->assertShellKeysOnly($this->writtenKeys(fn () => $this->page($admin, $locale)), $locale);
            $this->assertShellKeysOnly($this->writtenKeys(fn () => $this->page($admin, $locale, ['status' => 'active'])), $locale);
        }

        $this->assertSame([], TranslationValue::whereIn('key', self::SHARED_STATUS_KEYS)->pluck('key')->all());
    }

    public function test_cus_be_004_custom_translations_are_kept_and_shown(): void
    {
        $this->seedTranslations();
        $old = now()->subDays(3)->startOfSecond();
        $custom = [
            ['dashboard.Customers_Status_Active', 'ar', 'فعّال'],
            ['dashboard.Customers_Status_Pending', 'en', 'Awaiting activation'],
            ['dashboard.Active', 'ar', 'مُفعّلة'],
            ['dashboard.Pending', 'ar', 'قيد الانتظار'],
            ['dashboard.Blocked', 'en', 'Banned'],
        ];
        foreach ($custom as [$key, $locale, $value]) {
            TranslationValue::updateOrCreate(['key' => $key, 'locale' => $locale], ['value' => $value]);
            TranslationValue::where('key', $key)->where('locale', $locale)->update(['created_at' => $old, 'updated_at' => $old]);
        }
        $this->blockedAliAndActiveOmar();
        $this->customer(['status' => 'pending']);
        $admin = $this->admin(super: true);

        $ar = $this->page($admin, 'ar');
        $en = $this->page($admin, 'en', ['status' => 'pending']);

        $this->assertSame('فعّال', $ar->viewData('statuses')['active']);
        $this->assertStringContainsString('<span class="admin-products-badge is-active">فعّال</span>', $ar->getContent());
        $this->assertSame('Awaiting activation', $en->viewData('statuses')['pending']);
        $this->assertStringContainsString('<span class="admin-products-badge is-low">Awaiting activation</span>', $en->getContent());
        foreach ($custom as [$key, $locale, $value]) {
            $row = TranslationValue::where('key', $key)->where('locale', $locale)->first();
            $this->assertSame($value, $row->value, $key);
            $this->assertEquals($old, $row->updated_at, "{$key}: updated_at");
        }
        $this->assertSame(0, TranslationValue::whereIn('key', self::SHARED_STATUS_KEYS)->where('updated_at', '>', $old)->count());
    }

    public function test_cus_be_004_the_status_labels_in_arabic_and_english(): void
    {
        $this->seedTranslations();
        $admin = $this->admin(super: true);

        $this->assertSame(['active' => 'نشط', 'pending' => 'بانتظار التفعيل', 'blocked' => 'محظور'], $this->page($admin, 'ar')->viewData('statuses'));
        $this->assertSame(['active' => 'Active', 'pending' => 'Pending activation', 'blocked' => 'Blocked'], $this->page($admin, 'en')->viewData('statuses'));
    }

    public function test_cus_be_004_the_default_labels_without_translations(): void
    {
        $admin = $this->admin(super: true);
        $defaults = ['active' => 'Active', 'pending' => 'Pending activation', 'blocked' => 'Blocked'];

        foreach (['ar', 'en'] as $locale) {
            $this->assertSame($defaults, $this->page($admin, $locale)->viewData('statuses'), $locale);
        }
        $this->assertSame([], TranslationValue::whereIn('key', self::CUSTOMER_STATUS_KEYS)->pluck('key')->all());
    }

    public function test_cus_be_004_the_filter_works_without_translations_and_in_order(): void
    {
        $ids = [];
        foreach (['active', 'pending', 'blocked'] as $status) {
            $ids[$status] = $this->customer(['status' => $status])->id;
        }
        $admin = $this->admin(super: true);

        foreach ($ids as $status => $id) {
            $this->assertSame([$id], $this->rowIds($this->page($admin, 'ar', ['status' => $status])->getContent()), $status);
        }
        $this->assertCount(3, $this->rowIds($this->page($admin, 'ar', ['status' => 'invalid'])->getContent()), 'An unknown status is ignored.');
        $this->assertSame(['active', 'pending', 'blocked'], array_keys($this->page($admin, 'en')->viewData('statuses')));

        $source = file_get_contents(app_path('Http/Controllers/Dashboard/CustomerManagementController.php'));
        foreach (self::SHARED_STATUS_KEYS as $key) {
            $this->assertDoesNotMatchRegularExpression('/[\'"]'.preg_quote($key, '/').'[\'"]/', $source, "{$key} is not read by the controller.");
        }
    }

    public function test_the_shared_pagination(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->customer(['name' => "Customer {$i}"]);
        }
        $admin = $this->admin(super: true);

        $first = $this->page($admin)->getContent();
        $this->assertSame(15, substr_count($first, 'data-customer-id='));
        $this->assertStringContainsString('class="admin-pagination"', $first);
        $this->assertStringContainsString('Showing 1 to 15 of 20', $first);

        $second = $this->page($admin, 'en', ['page' => 2])->getContent();
        $this->assertSame(5, substr_count($second, 'data-customer-id='));
        $this->assertStringContainsString('Showing 16 to 20 of 20', $second);
    }

    // ---------------------------------------------------------------- empty states

    public function test_no_customers_yet(): void
    {
        $html = $this->page($this->admin(super: true))->getContent();

        $this->assertStringContainsString('No customers yet', $html);
        $this->assertStringContainsString('Customers appear here once they sign up in the store.', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('Clear filters', $html);
    }

    public function test_no_matching_customers_and_a_page_past_the_last(): void
    {
        $this->customer(['name' => 'Omar']);
        $admin = $this->admin(super: true);

        $none = $this->page($admin, 'en', ['search' => 'zzz'])->getContent();
        $this->assertStringContainsString('No matching customers', $none);
        $this->assertStringContainsString('Try another search or clear the filters.', $none);
        $this->assertStringContainsString('href="'.route('dashboard.customers.index').'" class="btn btn-light-secondary">Clear filters</a>', $none);
        $this->assertStringNotContainsString('<table', $none);

        $past = $this->page($admin, 'en', ['page' => 5])->getContent();
        $this->assertStringContainsString('No matching customers', $past);
        $this->assertStringContainsString('>Page 1</a>', $past);
    }

    // ---------------------------------------------------------------- accessibility and scope

    public function test_fields_are_labelled_and_the_table_is_described(): void
    {
        $this->customer();
        $html = $this->page($this->admin(super: true))->getContent();
        $xpath = $this->xpath($html);

        foreach ($xpath->query('//*[@id="admin-customers"]//*[self::input or self::select]') as $field) {
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id, $field->getAttribute('name'));
            $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length, "{$id} has a label.");
        }
        $this->assertSame(1, $xpath->query('//form[@role="search"][@method="GET"]')->length);
        $this->assertSame('Customers', trim($xpath->query('//*[@id="admin-customers"]//table/caption')->item(0)->textContent));
        $this->assertSame(6, $xpath->query('//*[@id="admin-customers"]//thead//th[@scope="col"]')->length);
        $this->assertMatchesRegularExpression('#<span>View</span>\s*<span class="visually-hidden"><bdi>#', $html, 'The View link names the customer.');
    }

    public function test_the_list_has_no_status_change_control(): void
    {
        $customer = $this->customer();
        $html = $this->page($this->admin(super: true))->getContent();
        $xpath = $this->xpath($html);

        $this->assertStringNotContainsString(route('dashboard.customers.update-status', $customer), $html);
        $this->assertSame(0, $xpath->query('//*[@id="admin-customers"]//form[not(@method="GET")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="admin-customers"]//select[@name="status"]')->length, 'Only the status filter.');
        $this->assertStringNotContainsString('name="_method"', $html);
    }

    public function test_the_page_band_is_hidden_for_this_page_only(): void
    {
        $css = file_get_contents(resource_path('css/admin-dashboard.css'));

        $this->assertStringContainsString("html.admin-shell .pc-content:has(#admin-customers) > .page-header {\n  display: none;\n}", $css);
        $this->assertStringNotContainsString('breadcrumb-item', $this->page($this->admin(super: true))->getContent());
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminHomeTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminCustomerTranslationSeeder::class);
        // dashboard.Reviews is reused as approved; no seeder inserts it (AdminAbilityTranslationSeeder only corrects
        // it), so it is created here as it exists on the development server.
        TranslationValue::insertOrIgnore([
            ['key' => 'dashboard.Reviews', 'locale' => 'ar', 'value' => 'التقييمات'],
            ['key' => 'dashboard.Reviews', 'locale' => 'en', 'value' => 'Reviews'],
        ]);
    }

    /**
     * The rows of the page's own keys (Customers_*, Orders_Status_*), matched by prefix in PHP.
     *
     * @return list<string>
     */
    private function pageKeyRows(): array
    {
        return TranslationValue::query()->orderBy('key')->pluck('key')
            ->filter(fn (string $key) => str_starts_with($key, 'dashboard.Customers_') || str_starts_with($key, 'dashboard.Orders_Status_'))
            ->values()->all();
    }

    private function customer(array $attributes = []): Customer
    {
        static $sequence = 0;
        $sequence++;

        return Customer::factory()->create($attributes + [
            'name' => "Customer {$sequence}",
            'email' => "customer{$sequence}@example.test",
            'phone' => '05992000'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'status' => 'active',
        ]);
    }

    /**
     * The customer ids of the list rows, in the order shown.
     *
     * @return list<int>
     */
    private function rowIds(string $html): array
    {
        preg_match_all('/data-customer-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * The distinct keys of the translation rows written while $request runs.
     *
     * @return list<string>
     */
    private function writtenKeys(callable $request): array
    {
        $lastId = (int) TranslationValue::max('id');
        $request();

        return TranslationValue::where('id', '>', $lastId)->distinct()->orderBy('key')->pluck('key')->all();
    }

    /** Every written key belongs to the admin shell (literal in its files), none to the customers controller or views. */
    private function assertShellKeysOnly(array $written, string $message = ''): void
    {
        $shell = implode("\n", array_map('file_get_contents', array_merge(
            [resource_path('views/layouts/dashboard-layout.blade.php'), resource_path('views/components/lang/language-switcher-dashboard.blade.php')],
            glob(resource_path('views/layouts/partials/dashboard/*.blade.php'))
        )));

        foreach ($written as $key) {
            $this->assertNotContains($key, [...self::SHARED_STATUS_KEYS, ...self::CUSTOMER_STATUS_KEYS], "{$message}: {$key}");
            $this->assertFalse(str_starts_with($key, 'dashboard.Customers_') || str_starts_with($key, 'dashboard.Orders_Status_'), "{$message}: {$key}");
            $this->assertStringContainsString("'{$key}'", $shell, "{$message}: {$key} is not a key of the admin shell.");
        }
    }

    /** @return array{Customer, Customer} */
    private function blockedAliAndActiveOmar(): array
    {
        return [
            $this->customer(['name' => 'Ali', 'email' => 'ali@example.test', 'status' => 'blocked']),
            $this->customer(['name' => 'Omar', 'email' => 'omar@example.test', 'status' => 'active']),
        ];
    }

    /**
     * The values of the selected options of the status filter.
     *
     * @return list<string>
     */
    private function selectedStatuses(string $html): array
    {
        $values = [];
        foreach ($this->xpath($html)->query('//select[@name="status"]/option[@selected]') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => "Admin {$sequence}", 'email' => "cus-admin{$sequence}@example.com", 'phone' => '05990007'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, string $locale = 'en', array $query = []): TestResponse
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.customers.index', $query))->assertOk();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /** The page's own content (without the shared header and sidebar), whitespace collapsed. */
    private function contentText(string $html): string
    {
        return preg_replace('/\s+/u', ' ', $this->xpath($html)->query('//*[@id="admin-customers"]')->item(0)->textContent);
    }
}
