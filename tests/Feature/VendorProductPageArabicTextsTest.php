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
 * VPM-UX-00: the 42 interface texts of the vendor products page (list and editor) whose Arabic rows were missing from a
 * local database. Each has its approved Arabic and English values in VendorDashboardTranslationSeeder, and once seeded
 * the Arabic page renders the Arabic value (as text, in an attribute or in the page's JSON texts), never the English
 * default; the English page renders the English value.
 */
class VendorProductPageArabicTextsTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'vendor.Product_Archived_Refused', 'vendor.Product_Badge_Archived', 'vendor.Product_Badge_Suspended',
        'vendor.Product_Care_Instructions', 'vendor.Product_Dimensions', 'vendor.Product_Gallery_Current',
        'vendor.Product_Gallery_Duplicate', 'vendor.Product_Gallery_Full', 'vendor.Product_Gallery_Legacy',
        'vendor.Product_Gallery_Missing', 'vendor.Product_Gallery_Move_Down', 'vendor.Product_Gallery_Move_Up',
        'vendor.Product_Gallery_Moved', 'vendor.Product_Gallery_Over_Max', 'vendor.Product_Height', 'vendor.Product_Length',
        'vendor.Product_List_Actions', 'vendor.Product_List_Delete', 'vendor.Product_List_Delete_Named', 'vendor.Product_List_Edit',
        'vendor.Product_List_Edit_Named', 'vendor.Product_List_Pagination', 'vendor.Product_List_Price', 'vendor.Product_List_Product',
        'vendor.Product_List_Status', 'vendor.Product_Material', 'vendor.Product_Notice_Archived', 'vendor.Product_Notice_Suspended',
        'vendor.Product_Place_Of_Manufacture', 'vendor.Product_Preparation_Days', 'vendor.Product_Section_Craft',
        'vendor.Product_Shipping_Details', 'vendor.Product_Status', 'vendor.Product_Status_Active', 'vendor.Product_Status_Draft',
        'vendor.Product_Status_Hint', 'vendor.Product_Status_Inactive', 'vendor.Product_Status_Keep_Out_Of_Stock',
        'vendor.Product_Status_Out_Of_Stock', 'vendor.Product_Weight_Unit', 'vendor.Product_Weight_Unit_Select', 'vendor.Product_Width',
    ];

    private string $hotFile = '';

    private Vendor $vendor;

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
        $this->vendor = Vendor::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_every_key_has_its_approved_arabic_and_english_values_in_the_seeder(): void
    {
        $this->assertCount(42, array_unique(self::KEYS));

        foreach (self::KEYS as $key) {
            $values = VendorDashboardTranslationSeeder::TRANSLATIONS[$key] ?? [];
            $this->assertNotSame('', trim($values['ar'] ?? ''), "{$key}: Arabic");
            $this->assertNotSame('', trim($values['en'] ?? ''), "{$key}: English");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key}: the Arabic value is Arabic");
        }
    }

    public function test_the_arabic_page_renders_the_arabic_values_and_the_english_page_the_english_ones(): void
    {
        $arabic = $this->page('ar');
        $english = $this->page('en');

        foreach (self::KEYS as $key) {
            [$ar, $en] = [VendorDashboardTranslationSeeder::TRANSLATIONS[$key]['ar'], VendorDashboardTranslationSeeder::TRANSLATIONS[$key]['en']];
            $this->assertStringContainsString($ar, $arabic, "{$key}: Arabic value rendered");
            $this->assertStringContainsString($en, $english, "{$key}: English value rendered");
        }
        // No English default of these keys is left in the Arabic page (texts that are also a word of another Arabic
        // value, like "Width" or "Status", cannot occur: the Arabic page has no other English interface text).
        foreach (self::KEYS as $key) {
            $this->assertStringNotContainsString('"'.VendorDashboardTranslationSeeder::TRANSLATIONS[$key]['en'].'"', $arabic, "{$key}: no English default in a JSON text");
            $this->assertStringNotContainsString('>'.VendorDashboardTranslationSeeder::TRANSLATIONS[$key]['en'].'<', $arabic, "{$key}: no English default as text");
        }
    }

    public function test_rendering_the_page_creates_no_translation_rows(): void
    {
        $rows = TranslationValue::count();

        $this->page('ar');
        $this->page('en');

        $this->assertSame($rows, TranslationValue::count());
    }

    /** The page's HTML, with entities and the JSON blocks' \u escapes decoded, so every rendered text is searchable. */
    private function page(string $locale): string
    {
        $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
            ->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        return preg_replace_callback('/(?:\\\\u[0-9a-fA-F]{4})+/', fn ($m) => json_decode('"'.$m[0].'"'), $html);
    }
}
