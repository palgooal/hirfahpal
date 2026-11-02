<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminVendorOrderTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VEN-BE-020 (slice 2A): deploying AdminVendorOrderTranslationSeeder whether or not the admin vendor order page was opened
 * first. Opened in Arabic before the seeder, t() (auto-create on) inserts an Arabic row holding the English default; the
 * seeder repairs exactly that placeholder (the English default, never updated since it was created) and keeps every
 * other row: correct, customized or edited ones.
 */
class AdminVendorOrderTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'dashboard.Vendor_Order_Assignment_Not_Ready';

    private Admin $admin;

    private VendorOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $order = Order::query()->create(['number' => 'HF-T-1', 'customer_id' => Customer::factory()->create()->id, 'status' => 'processing', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);
        $this->order = VendorOrder::query()->create(['number' => 'VO-T-1', 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'accepted', 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10, 'accepted_at' => now()]);
    }

    public function test_the_root_cause_an_arabic_visit_before_the_seeder_stores_the_english_default_as_arabic(): void
    {
        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Vendor_Order_Assignment_%')->count(), 'Empty: no row yet.');

        $this->assertStringContainsString(e($this->text('en')), $this->visit('ar'), 'The first Arabic visit shows the English default.');
        $this->assertStringContainsString(e($this->text('en')), $this->visit('ar'), 'And so does every later one.');

        $row = $this->row('ar');
        $this->assertSame($this->text('en'), $row->value, 'An Arabic row holding the English default.');
        $this->assertTrue($row->created_at->equalTo($row->updated_at));
    }

    public function test_seeder_before_the_first_visit(): void
    {
        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertStringContainsString(e($this->text('ar')), $this->visit('ar'));
        $this->assertStringContainsString(e($this->text('en')), $this->visit('en'));
        $this->assertSame([$this->text('ar'), $this->text('en')], [$this->row('ar')->value, $this->row('en')->value]);
    }

    public function test_arabic_visit_before_the_seeder_is_repaired_and_shown_in_arabic(): void
    {
        $this->visit('ar');
        $this->visit('ar'); // the English placeholder is now cached as well

        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame($this->text('ar'), $this->row('ar')->value);
        $this->assertSame($this->text('en'), $this->row('en')->value, 'The English row (auto-created with the right text) is kept.');
        $this->assertStringContainsString(e($this->text('ar')), $this->visit('ar'), 'The cached placeholder was cleared.');
        $this->assertStringContainsString(e($this->text('en')), $this->visit('en'));

        foreach (AdminVendorOrderTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], TranslationValue::where('key', $key)->where('locale', 'ar')->value('value'), "{$key}: both keys repaired.");
        }
    }

    public function test_existing_correct_arabic_translations_are_left_untouched(): void
    {
        $this->seed(AdminVendorOrderTranslationSeeder::class);
        $before = $this->snapshot();
        $this->travel(5)->minutes();

        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_customized_translations_are_kept(): void
    {
        TranslationValue::query()->create(['key' => self::KEY, 'locale' => 'ar', 'value' => 'نص عربي مخصّص من الإدارة']);
        TranslationValue::query()->create(['key' => self::KEY, 'locale' => 'en', 'value' => 'Customized by the admin.']);
        $before = $this->snapshot();

        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame($before[self::KEY], $this->snapshot()[self::KEY]);
        $this->assertStringContainsString('نص عربي مخصّص من الإدارة', $this->visit('ar'));
    }

    public function test_an_english_value_kept_on_purpose_after_an_edit_is_not_replaced(): void
    {
        // Auto-created, then edited through the translations page (updated_at moves), ending with the English text again.
        $this->visit('ar');
        $this->travel(1)->minutes();
        TranslationValue::updateOrCreate(['key' => self::KEY, 'locale' => 'ar'], ['value' => 'مسودة']);
        $this->travel(1)->minutes();
        TranslationValue::updateOrCreate(['key' => self::KEY, 'locale' => 'ar'], ['value' => $this->text('en')]);
        $before = $this->row('ar')->only(['value', 'updated_at']);

        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertEquals($before, $this->row('ar')->only(['value', 'updated_at']), 'Edited since it was created: kept as it is.');
    }

    public function test_an_arabic_placeholder_differing_only_in_case_is_not_replaced(): void
    {
        TranslationValue::query()->create(['key' => self::KEY, 'locale' => 'ar', 'value' => mb_strtoupper($this->text('en'))]);

        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame(mb_strtoupper($this->text('en')), $this->row('ar')->value);
    }

    public function test_repeated_seeder_runs_change_nothing_after_the_first(): void
    {
        $this->visit('ar');
        $this->seed(AdminVendorOrderTranslationSeeder::class);
        $before = $this->snapshot();
        $this->travel(5)->minutes();

        $this->seed(AdminVendorOrderTranslationSeeder::class);
        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(4, TranslationValue::where('key', 'like', 'dashboard.Vendor_Order_Assignment_%')->count());
    }

    private function visit(string $locale): string
    {
        return $this->actingAs($this->admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.vendor-orders.show', $this->order))->assertOk()->getContent();
    }

    private function text(string $locale): string
    {
        return AdminVendorOrderTranslationSeeder::TRANSLATIONS[self::KEY][$locale];
    }

    private function row(string $locale): TranslationValue
    {
        return TranslationValue::where('key', self::KEY)->where('locale', $locale)->sole();
    }

    /** @return array<string, array<string, array{0: string, 1: string}>> key => locale => [value, updated_at] */
    private function snapshot(): array
    {
        return TranslationValue::where('key', 'like', 'dashboard.Vendor_Order_Assignment_%')->orderBy('key')->orderBy('locale')->get()
            ->groupBy('key')->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->locale => [$r->value, $r->updated_at->toJSON()]])->all())->all();
    }
}
