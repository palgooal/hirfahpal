<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Markup contract of the vendor approval-status page. Access rules are
 * covered by VendorApprovalEnforcementTest.
 */
class VendorApprovalStatusPageTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // UI strings come from seeded translation_values; the default locale is Arabic (RTL).
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);

        // A hot file makes @vite print the entry names without needing a build.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_pending_browser_request_returns_the_status_view(): void
    {
        $this->as('pending')
            ->page()
            ->assertOk()
            ->assertViewIs('auth.vendor.approval-status')
            ->assertViewHas('state', 'pending');
    }

    public function test_pending_page_shows_localized_pending_content(): void
    {
        $this->as('pending')
            ->page()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('حالة الطلب')
            ->assertSee('طلبك قيد المراجعة')
            ->assertSee('تم استلام طلب انضمامك إلى حرفة كبائع، وتتم مراجعته حاليًا من فريق الإدارة.')
            ->assertSee('بانتظار المراجعة')
            ->assertSee('data-status-indicator="pending"', false)
            ->assertSee('اسم المتجر')
            ->assertSee('Craft Corner')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة')
            ->assertDontSee('لم تتم الموافقة على طلبك')
            ->assertDontSee('id="rejection-reason"', false);
    }

    public function test_rejected_page_shows_the_rejected_state_and_reason(): void
    {
        $this->as('rejected', 'بيانات المتجر تحتاج إلى استكمال')
            ->page()
            ->assertOk()
            ->assertViewHas('state', 'rejected')
            ->assertSee('لم تتم الموافقة على طلبك')
            ->assertSee('نأسف لإبلاغك بأنه لم تتم الموافقة على طلب انضمامك إلى حرفة كبائع.')
            ->assertSee('data-status-indicator="rejected"', false)
            ->assertSee('id="rejection-reason"', false)
            ->assertSee('سبب عدم الموافقة')
            ->assertSee('بيانات المتجر تحتاج إلى استكمال')
            ->assertDontSee('طلبك قيد المراجعة');
    }

    public function test_rejection_reason_is_escaped(): void
    {
        $this->as('rejected', '<script>alert(1)</script>')
            ->page()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_null_or_blank_rejection_reason_renders_no_reason_component(): void
    {
        foreach ([null, '   '] as $index => $reason) {
            $this->as('rejected', $reason, "rejected-$index@example.com")
                ->page()
                ->assertOk()
                ->assertSee('لم تتم الموافقة على طلبك')
                ->assertDontSee('id="rejection-reason"', false)
                ->assertDontSee('سبب عدم الموافقة');
        }
    }

    public function test_approved_browser_request_redirects_to_the_dashboard(): void
    {
        $this->as('approved')->page()->assertRedirect(route('vendor.dashboard'));
    }

    public function test_json_requests_keep_the_contract_for_every_state(): void
    {
        $this->as('pending', email: 'p@example.com')->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertExactJson(['approval_status' => 'pending', 'rejection_reason' => null, 'store_name' => 'Craft Corner', 'dashboard_url' => null]);

        $this->as('rejected', 'Reason.', 'r@example.com')->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertExactJson(['approval_status' => 'rejected', 'rejection_reason' => 'Reason.', 'store_name' => 'Craft Corner', 'dashboard_url' => null]);

        $this->as('approved', email: 'a@example.com')->getJson(route('vendor.approval-status'))
            ->assertOk()
            ->assertExactJson(['approval_status' => 'approved', 'rejection_reason' => null, 'store_name' => 'Craft Corner', 'dashboard_url' => route('vendor.dashboard')]);
    }

    public function test_switching_to_english_renders_ltr(): void
    {
        $this->as('rejected', 'Store details are incomplete.');

        $this->get(route('vendor.approval-status').'?change-locale=en')
            ->assertRedirect(route('vendor.approval-status'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Application status')
            ->assertSee('Your application was not approved')
            ->assertSee('Store details are incomplete.')
            ->assertSee('Sign out')
            ->assertSee('Manage your store and start your journey with Hirfah');
    }

    public function test_language_switcher_is_present(): void
    {
        $this->as('pending')
            ->page()
            ->assertSee('aria-label="اختيار اللغة"', false)
            ->assertSee(route('vendor.approval-status').'?change-locale=en', false);
    }

    public function test_logout_is_present_and_is_the_only_form(): void
    {
        $response = $this->as('pending')
            ->page()
            ->assertSee('method="POST" action="'.route('vendor.logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('تسجيل الخروج');

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_no_operational_or_admin_links_for_pending_or_rejected(): void
    {
        foreach (['pending' => null, 'rejected' => 'Reason.'] as $state => $reason) {
            $this->as($state, $reason, "$state@example.com")
                ->page()
                ->assertDontSee('/vendor/dashboard', false)
                ->assertDontSee(route('vendor.dashboard'), false)
                ->assertDontSee(route('admin.login'), false)
                ->assertDontSee('/admin/', false)
                ->assertDontSee('name="store_name"', false)
                ->assertDontSee('register', false);
        }
    }

    public function test_only_vendor_auth_css_is_loaded(): void
    {
        $this->as('pending')
            ->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('assets-dashboard', false);
    }

    public function test_visiting_the_page_never_auto_creates_translation_rows(): void
    {
        TranslationValue::where('key', 'like', 'vendor.%')->delete();
        $before = TranslationValue::count();

        $this->as('pending')->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    private function as(string $approval, ?string $reason = null, string $email = 'vendor@example.com'): static
    {
        $vendor = Vendor::factory()->create(['email' => $email]);
        $vendor->profile()->create([
            'store_name' => 'Craft Corner',
            'slug' => 'craft-corner-'.$vendor->id,
            'approval_status' => $approval,
            'rejection_reason' => $reason,
        ]);

        return $this->actingAs($vendor, 'vendor');
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.approval-status'));
    }
}
