<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminContactMessageTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CONTACT-ADMIN-IMPLEMENTATION-01: the admin contact messages (/dashboard/contact-messages). Access through
 * contact-messages.view (super admins always), 403 without it and no read_at change, newest first with the shared
 * pagination, the empty state, read and unread badges, escaped content with its line breaks, the first authorized view
 * marking the message read and later views keeping that date, the guest or linked customer shown only with
 * customers.view, the sidebar item, and the texts read without creating translation rows.
 */
class AdminContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- access

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $message = $this->message();

        $this->get(route('dashboard.contact-messages.index'))->assertRedirect(route('admin.login'));
        $this->get(route('dashboard.contact-messages.show', $message->id))->assertRedirect(route('admin.login'));
        $this->assertNull($message->fresh()->read_at);
    }

    public function test_customers_vendors_and_delivery_drivers_cannot_open_the_module(): void
    {
        $message = $this->message();

        foreach ([[Customer::factory()->create(), 'customer'], [Vendor::factory()->create(), 'vendor'], [DeliveryDriver::factory()->create(), 'delivery_driver']] as [$user, $guard]) {
            $this->actingAs($user, $guard)->get(route('dashboard.contact-messages.index'))->assertRedirect(route('admin.login'));
            $this->actingAs($user, $guard)->get(route('dashboard.contact-messages.show', $message->id))->assertRedirect(route('admin.login'));
        }

        $this->assertNull($message->fresh()->read_at);
    }

    public function test_a_super_admin_and_an_admin_with_the_ability_can_open_both_pages(): void
    {
        foreach ([$this->admin(superAdmin: true), $this->admin(['contact-messages.view'])] as $admin) {
            $message = $this->message(['name' => 'Sender '.$admin->id]);

            $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.index'))->assertOk()->assertSee('Sender '.$admin->id);
            $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.show', $message->id))->assertOk()->assertSee('Sender '.$admin->id);
        }
    }

    public function test_an_admin_without_the_ability_gets_403_and_the_message_stays_unread(): void
    {
        $message = $this->message();
        $admin = $this->admin(['customers.view', 'reviews.view']);

        $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.show', $message->id))->assertForbidden();
        // The ability is checked before the message is read: an unknown id is 403 too, not 404.
        $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.show', 999999))->assertForbidden();

        $this->assertNull($message->fresh()->read_at);
    }

    public function test_an_unknown_message_is_404_for_an_authorized_admin(): void
    {
        $this->actingAs($this->admin(['contact-messages.view']), 'admin')
            ->get(route('dashboard.contact-messages.show', 999999))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- list

    public function test_the_list_is_newest_first_15_per_page_with_the_shared_pagination(): void
    {
        foreach (range(1, 17) as $number) {
            $this->message(['name' => sprintf('Sender %02d', $number)]);
        }
        $admin = $this->admin(['contact-messages.view']);

        $first = $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.index'))->assertOk();
        $this->assertSame(15, substr_count($first->getContent(), 'data-contact-message-id="'));
        $first->assertSeeInOrder(['Sender 17', 'Sender 16', 'Sender 03']);
        $first->assertDontSee('Sender 02');
        $first->assertSee('page=2', false);

        $second = $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.index', ['page' => 2]))->assertOk();
        $this->assertSame(2, substr_count($second->getContent(), 'data-contact-message-id="'));
        $second->assertSeeInOrder(['Sender 02', 'Sender 01']);
    }

    public function test_the_list_shows_sender_email_subject_date_status_and_a_view_link(): void
    {
        Carbon::setTestNow('2026-10-19 14:05:00');
        $unread = $this->message(['name' => 'Huda', 'email' => 'huda@example.com', 'subject' => 'Shipping question']);
        $read = $this->message(['name' => 'Sami', 'subject' => null, 'read_at' => now()]);
        Carbon::setTestNow();

        $response = $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index', locale: 'en');

        $response->assertSee('Huda')->assertSee('huda@example.com')->assertSee('Shipping question')->assertSee('2026-10-19 14:05');
        $response->assertSee('href="'.route('dashboard.contact-messages.show', $unread->id).'"', false);
        $this->assertMatchesRegularExpression('#data-contact-message-id="'.$unread->id.'" data-read="false".*?admin-products-badge is-low">New<#s', $response->getContent());
        $this->assertMatchesRegularExpression('#data-contact-message-id="'.$read->id.'" data-read="true".*?No subject.*?admin-products-badge is-draft">Read<#s', $response->getContent());
        // The list never shows the message text itself.
        $response->assertDontSee('A test message body');
    }

    public function test_the_empty_list_shows_the_empty_state(): void
    {
        $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index', locale: 'en')
            ->assertSee('No messages yet')
            ->assertSee('Messages sent from the store contact page appear here.')
            ->assertDontSee('<table', false);
    }

    // ---------------------------------------------------------------- details and read status

    public function test_the_details_show_the_full_message_with_its_line_breaks_escaped(): void
    {
        $message = $this->message([
            'name' => '<b>Mallory</b>',
            'email' => 'mallory@example.com',
            'subject' => '<script>alert("s")</script>',
            'message' => "First line <img src=x onerror=alert(1)>\nSecond line",
        ]);

        $html = $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.show', $message->id, 'en')->getContent();

        $this->assertStringContainsString('&lt;b&gt;Mallory&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;s&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<script>alert("s")', $html);
        // The line break is kept as text inside the pre-line block (admin-product-text), not turned into HTML.
        $this->assertMatchesRegularExpression('#class="admin-product-text" dir="auto" data-contact-message-body>First line &lt;img src=x onerror=alert\(1\)&gt;\nSecond line</dd>#', $html);
        $this->assertStringContainsString('mallory@example.com', $html);
    }

    public function test_the_list_escapes_untrusted_content(): void
    {
        $this->message(['name' => '<i>Eve</i>', 'subject' => '<svg onload=alert(1)>']);

        $html = $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index', locale: 'en')->getContent();

        $this->assertStringContainsString('&lt;i&gt;Eve&lt;/i&gt;', $html);
        $this->assertStringContainsString('&lt;svg onload=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<svg onload', $html);
    }

    public function test_the_first_authorized_view_marks_the_message_read_and_later_views_keep_that_date(): void
    {
        $message = $this->message();
        $updatedAt = $message->updated_at->toDateTimeString();
        $admin = $this->admin(['contact-messages.view']);

        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->page($admin, 'dashboard.contact-messages.show', $message->id, 'en')->assertSee('2026-10-19 10:00');
        $this->assertSame('2026-10-19 10:00:00', $message->fresh()->read_at->toDateTimeString());

        Carbon::setTestNow('2026-10-20 18:30:00');
        $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.show', $message->id, 'en')->assertSee('2026-10-19 10:00');
        $this->assertSame('2026-10-19 10:00:00', $message->fresh()->read_at->toDateTimeString(), 'A later view keeps the first date.');
        $this->assertSame($updatedAt, $message->fresh()->updated_at->toDateTimeString(), 'Marking read does not touch updated_at.');
        Carbon::setTestNow();

        $this->page($admin, 'dashboard.contact-messages.index', locale: 'en')->assertSee('data-read="true"', false);
    }

    public function test_opening_the_list_does_not_mark_messages_read(): void
    {
        $message = $this->message();

        $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index');

        $this->assertNull($message->fresh()->read_at);
    }

    public function test_the_linked_customer_is_shown_only_with_customers_view(): void
    {
        $customer = Customer::factory()->create(['name' => 'Registered Rana']);
        $linked = $this->message(['customer_id' => $customer->id]);
        $guest = $this->message();

        $withCustomers = $this->admin(['contact-messages.view', 'customers.view']);
        $this->page($withCustomers, 'dashboard.contact-messages.show', $linked->id, 'en')
            ->assertSee('Customer account')
            ->assertSee('href="'.route('dashboard.customers.show', $customer).'"', false)
            ->assertSee('Registered Rana');
        $this->page($withCustomers, 'dashboard.contact-messages.show', $guest->id, 'en')
            ->assertSee('Guest (no account)')
            ->assertDontSee('Registered Rana');

        $withoutCustomers = $this->admin(['contact-messages.view']);
        foreach ([$linked, $guest] as $message) {
            $this->page($withoutCustomers, 'dashboard.contact-messages.show', $message->id, 'en')
                ->assertDontSee('data-contact-message-account', false)
                ->assertDontSee('Customer account')
                ->assertDontSee('Registered Rana')
                ->assertDontSee(route('dashboard.customers.show', $customer), false);
        }
    }

    // ---------------------------------------------------------------- sidebar and translations

    public function test_the_sidebar_item_is_in_the_quality_group_only_for_admins_with_the_ability(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $url = route('dashboard.contact-messages.index');

        foreach ([$this->admin(superAdmin: true), $this->admin(['contact-messages.view'])] as $admin) {
            $nav = $this->sidebar($admin);
            $this->assertMatchesRegularExpression('#>الجودة والمتابعة</span>.*?<a href="'.preg_quote($url, '#').'" class="pc-link"\s*>.*?<span class="pc-mtext">رسائل التواصل</span>#s', $nav);
        }

        $this->assertStringNotContainsString($url, $this->sidebar($this->admin(['reviews.view'])));
        $this->assertStringNotContainsString($url, $this->sidebar($this->admin()));
    }

    public function test_the_item_is_current_on_the_details_page_and_titles_both_pages(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $message = $this->message();
        $admin = $this->admin(['contact-messages.view']);

        foreach ([['dashboard.contact-messages.index', null], ['dashboard.contact-messages.show', $message->id]] as [$route, $id]) {
            $html = $this->page($admin, $route, $id)->getContent();
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('dashboard.contact-messages.index'), '#').'" class="pc-link"\s+aria-current="page"#', $html, $route);
            $this->assertStringContainsString('<h1 class="admin-header-title">رسائل التواصل</h1>', $html, $route);
        }
    }

    public function test_the_seeded_arabic_texts_are_shown(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminContactMessageTranslationSeeder::class);
        $customer = Customer::factory()->create();
        $message = $this->message(['subject' => null, 'customer_id' => $customer->id]);
        $admin = $this->admin(['contact-messages.view', 'customers.view']);

        $this->page($admin, 'dashboard.contact-messages.index')
            ->assertSee('المرسل')->assertSee('تاريخ الاستلام')->assertSee('بدون موضوع')->assertSee('جديدة');
        $this->page($admin, 'dashboard.contact-messages.show', $message->id)
            ->assertSee('العودة إلى رسائل التواصل')->assertSee('بيانات المرسل')->assertSee('حساب العميل')->assertSee('تاريخ القراءة')->assertSee('مقروءة');
    }

    public function test_rendering_creates_no_rows_for_the_contact_message_keys(): void
    {
        $message = $this->message(['customer_id' => Customer::factory()->create()->id]);
        $admin = $this->admin(superAdmin: true);

        foreach (['ar', 'en'] as $locale) {
            $this->page($admin, 'dashboard.contact-messages.index', locale: $locale)->assertSee('Contact messages');
            $this->page($admin, 'dashboard.contact-messages.show', $message->id, $locale)->assertSee('Sender details');
            $this->page($admin, 'dashboard.contact-messages.index', ['page' => 9], $locale)->assertSee('No messages yet');
        }

        $this->assertSame(0, TranslationValue::query()
            ->where(fn ($query) => $query->where('key', 'like', 'dashboard.Contact_Messages%')->orWhere('key', 'dashboard.Nav_Contact_Messages'))
            ->count());
    }

    // ---------------------------------------------------------------- soft delete (CONTACT-ADMIN-DELETE-02)

    public function test_a_super_admin_soft_deletes_a_confirmed_message_and_it_leaves_the_list(): void
    {
        $message = $this->message(['name' => 'Deleted Dana']);
        $kept = $this->message(['name' => 'Kept Karim']);
        $admin = $this->admin(superAdmin: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])
            ->assertRedirect(route('dashboard.contact-messages.index'))
            ->assertSessionHas('success', 'The message was deleted.');

        $this->assertSoftDeleted('contact_messages', ['id' => $message->id]);
        $this->assertNotNull(ContactMessage::withTrashed()->find($message->id), 'The row stays in the database.');
        $this->assertNull(ContactMessage::query()->find($message->id));
        $this->assertNull($message->fresh()->read_at, 'Deleting does not mark the message read.');

        $list = $this->page($admin, 'dashboard.contact-messages.index', locale: 'en');
        $list->assertDontSee('Deleted Dana')->assertSee('Kept Karim');
        $this->assertSame(1, substr_count($list->getContent(), 'data-contact-message-id="'));

        $this->actingAs($admin, 'admin')->get(route('dashboard.contact-messages.show', $message->id))->assertNotFound();
        $this->assertNull($kept->fresh()->deleted_at);
    }

    public function test_the_success_message_is_shown_on_the_list_after_the_redirect(): void
    {
        $message = $this->message();

        $this->actingAs($this->admin(superAdmin: true), 'admin')->withSession(['locale' => 'en'])->followingRedirects()
            ->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('The message was deleted.');
    }

    public function test_without_the_confirmation_nothing_is_deleted(): void
    {
        $message = $this->message();
        $admin = $this->admin(superAdmin: true);

        foreach ([[], ['confirm' => '0'], ['confirm' => '']] as $payload) {
            $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
                ->delete(route('dashboard.contact-messages.destroy', $message->id), $payload)
                ->assertRedirect(route('dashboard.contact-messages.show', $message->id))
                ->assertSessionHas('danger', 'The deletion was not confirmed.');
        }

        $this->assertNull($message->fresh()->deleted_at);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->followingRedirects()
            ->delete(route('dashboard.contact-messages.destroy', $message->id))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('The deletion was not confirmed.');
    }

    public function test_only_a_super_admin_may_delete_and_sees_the_delete_panel(): void
    {
        $message = $this->message();

        foreach ([$this->admin(['contact-messages.view']), $this->admin(['contact-messages.view', 'customers.view', 'reviews.delete'])] as $admin) {
            $this->actingAs($admin, 'admin')->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertForbidden();
            // The super admin check comes before the message is read: an unknown id is 403 too.
            $this->actingAs($admin, 'admin')->delete(route('dashboard.contact-messages.destroy', 999999), ['confirm' => '1'])->assertForbidden();
            $this->page($admin, 'dashboard.contact-messages.show', $message->id, 'en')
                ->assertDontSee('data-contact-message-delete', false)
                ->assertDontSee(route('dashboard.contact-messages.destroy', $message->id).'"', false);
        }
        $this->assertNull($message->fresh()->deleted_at);

        $html = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.show', $message->id, 'en')->getContent();
        $this->assertMatchesRegularExpression('#<form method="POST" action="'.preg_quote(route('dashboard.contact-messages.destroy', $message->id), '#').'" class="admin-product-moderation is-suspend"[^>]*data-contact-message-delete>#', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertMatchesRegularExpression('#<input class="form-check-input" type="checkbox" name="confirm" value="1" id="contact-message-delete-confirm" required#', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="contact-message-delete-confirm">I confirm deleting this message</label>', $html);
        $this->assertStringContainsString('stays in the database', $html);
    }

    public function test_guests_and_other_account_types_cannot_delete(): void
    {
        $message = $this->message();

        $this->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertRedirect(route('admin.login'));
        foreach ([[Customer::factory()->create(), 'customer'], [Vendor::factory()->create(), 'vendor'], [DeliveryDriver::factory()->create(), 'delivery_driver']] as [$user, $guard]) {
            $this->actingAs($user, $guard)->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertRedirect(route('admin.login'));
        }

        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_an_unknown_or_already_deleted_message_is_404_for_a_super_admin(): void
    {
        $admin = $this->admin(superAdmin: true);
        $message = $this->message();
        $message->delete();
        $deletedAt = ContactMessage::withTrashed()->find($message->id)->deleted_at->toDateTimeString();

        $this->travel(1)->hours();
        $this->actingAs($admin, 'admin')->delete(route('dashboard.contact-messages.destroy', 999999), ['confirm' => '1'])->assertNotFound();
        $this->actingAs($admin, 'admin')->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertNotFound();

        $this->assertSame($deletedAt, ContactMessage::withTrashed()->find($message->id)->deleted_at->toDateTimeString(), 'The first deletion date is kept.');
    }

    public function test_deleting_keeps_the_read_date_of_a_read_message(): void
    {
        $admin = $this->admin(superAdmin: true);
        $message = $this->message();

        Carbon::setTestNow('2026-10-19 09:00:00');
        $this->page($admin, 'dashboard.contact-messages.show', $message->id);
        Carbon::setTestNow('2026-10-19 11:00:00');
        $this->actingAs($admin, 'admin')->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertRedirect();
        Carbon::setTestNow();

        $trashed = ContactMessage::withTrashed()->find($message->id);
        $this->assertSame('2026-10-19 09:00:00', $trashed->read_at->toDateTimeString());
        $this->assertSame('2026-10-19 11:00:00', $trashed->deleted_at->toDateTimeString());
    }

    public function test_pagination_counts_only_messages_that_are_not_deleted(): void
    {
        $messages = collect(range(1, 16))->map(fn ($number) => $this->message(['name' => sprintf('Sender %02d', $number)]));
        $messages->first()->delete();

        $page = $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index', locale: 'en');

        $this->assertSame(15, substr_count($page->getContent(), 'data-contact-message-id="'));
        $page->assertDontSee('page=2', false)->assertDontSee('Sender 01');
    }

    public function test_the_seeded_arabic_delete_texts_are_shown(): void
    {
        $this->seed(AdminContactMessageTranslationSeeder::class);
        $message = $this->message();
        $admin = $this->admin(superAdmin: true);

        $this->page($admin, 'dashboard.contact-messages.show', $message->id)
            ->assertSee('حذف الرسالة')->assertSee('أؤكد حذف هذه الرسالة')->assertSee('تبقى محفوظة في قاعدة البيانات');

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->followingRedirects()
            ->delete(route('dashboard.contact-messages.destroy', $message->id))->assertSee('لم يتم تأكيد الحذف.');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->followingRedirects()
            ->delete(route('dashboard.contact-messages.destroy', $message->id), ['confirm' => '1'])->assertSee('تم حذف الرسالة.');
    }

    public function test_deleting_creates_no_translation_rows(): void
    {
        $admin = $this->admin(superAdmin: true);
        $first = $this->message();
        $second = $this->message();

        $this->actingAs($admin, 'admin')->followingRedirects()->delete(route('dashboard.contact-messages.destroy', $first->id))->assertOk();
        $this->actingAs($admin, 'admin')->followingRedirects()->delete(route('dashboard.contact-messages.destroy', $second->id), ['confirm' => '1'])->assertOk();

        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Contact_Messages%')->count());
    }

    // ---------------------------------------------------------------- helpers

    private function admin(array $abilities = [], bool $superAdmin = false): Admin
    {
        $number = Admin::query()->count() + 1;

        $admin = Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::query()->create($attributes + [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Hello',
            'message' => 'A test message body',
        ]);
    }

    private function page(Admin $admin, string $route, mixed $parameters = [], string $locale = 'ar'): TestResponse
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route($route, $parameters ?? []))->assertOk();
    }

    private function sidebar(Admin $admin): string
    {
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<nav class="pc-sidebar" id="admin-sidebar".*?</nav>#s', $html, $match));

        return $match[0];
    }
}
