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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CONTACT-ADMIN-TRASH-02: the contact-message Trash (/dashboard/contact-messages/trash), restore and permanent deletion.
 * Super admins only, checked before any message is read (403 otherwise, unknown ids included); Trash lookups only find
 * soft-deleted messages (404 otherwise). The Trash list and details, read_at never changed by viewing, restore keeping
 * every business field, the typed localized phrase for permanent deletion, repeated actions, CSRF, and the texts read
 * without creating translation rows.
 */
class AdminContactMessagesTrashTest extends TestCase
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

    public function test_a_super_admin_can_use_all_four_routes(): void
    {
        $admin = $this->admin(superAdmin: true);
        $restored = $this->trashed();
        $purged = $this->trashed();

        $this->as($admin)->get(route('dashboard.contact-messages.trash'))->assertOk();
        $this->as($admin)->get(route('dashboard.contact-messages.trash.show', $restored->id))->assertOk();
        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $restored->id))->assertRedirect(route('dashboard.contact-messages.trash'));
        $this->as($admin)->delete(route('dashboard.contact-messages.purge', $purged->id), ['confirmation_phrase' => 'delete permanently'])
            ->assertRedirect(route('dashboard.contact-messages.trash'));
    }

    public function test_non_super_admins_get_403_on_every_route_even_for_unknown_ids(): void
    {
        $message = $this->trashed();

        foreach ([$this->admin(['contact-messages.view']), $this->admin(['contact-messages.view', 'customers.view', 'reviews.delete'])] as $admin) {
            foreach ([$message->id, 999999] as $id) {
                $this->as($admin)->get(route('dashboard.contact-messages.trash.show', $id))->assertForbidden();
                $this->as($admin)->patch(route('dashboard.contact-messages.restore', $id))->assertForbidden();
                $this->as($admin)->delete(route('dashboard.contact-messages.purge', $id), ['confirmation_phrase' => 'delete permanently'])->assertForbidden();
            }
            $this->as($admin)->get(route('dashboard.contact-messages.trash'))->assertForbidden();
        }

        $this->assertNotNull(ContactMessage::withTrashed()->find($message->id)?->deleted_at, 'Still in the Trash.');
    }

    public function test_guests_and_other_account_types_are_sent_to_the_admin_login(): void
    {
        $message = $this->trashed();
        $requests = fn () => [
            $this->get(route('dashboard.contact-messages.trash')),
            $this->get(route('dashboard.contact-messages.trash.show', $message->id)),
            $this->patch(route('dashboard.contact-messages.restore', $message->id)),
            $this->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => 'delete permanently']),
        ];

        foreach ($requests() as $response) {
            $response->assertRedirect(route('admin.login'));
        }
        foreach ([[Customer::factory()->create(), 'customer'], [Vendor::factory()->create(), 'vendor'], [DeliveryDriver::factory()->create(), 'delivery_driver']] as [$user, $guard]) {
            $this->actingAs($user, $guard);
            foreach ($requests() as $response) {
                $response->assertRedirect(route('admin.login'));
            }
        }

        $this->assertNotNull(ContactMessage::withTrashed()->find($message->id)?->deleted_at);
    }

    // ---------------------------------------------------------------- Trash list

    public function test_the_trash_lists_only_deleted_messages_most_recently_deleted_first(): void
    {
        $active = $this->message(['name' => 'Active Amal']);
        Carbon::setTestNow('2026-10-19 08:00:00');
        $older = $this->trashed(['name' => 'Older Omar']);
        Carbon::setTestNow('2026-10-19 09:30:00');
        $newer = $this->trashed(['name' => 'Newer Nour', 'email' => 'nour@example.com', 'subject' => 'Refund']);
        Carbon::setTestNow();

        $page = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.trash');

        $page->assertSeeInOrder(['Newer Nour', 'Older Omar'])->assertDontSee('Active Amal');
        $page->assertSee('nour@example.com')->assertSee('Refund')->assertSee('2026-10-19 09:30');
        $page->assertSee('href="'.route('dashboard.contact-messages.trash.show', $newer->id).'"', false);
        $this->assertSame(2, substr_count($page->getContent(), 'data-trashed-message-id="'));
        $this->assertNull($active->fresh()->deleted_at);
        $this->assertNotNull($older->id);
    }

    public function test_the_trash_is_paginated_15_per_page_with_a_deterministic_order(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        foreach (range(1, 17) as $number) {
            $this->trashed(['name' => sprintf('Trashed %02d', $number)]); // Same deleted_at: newest id first.
        }
        Carbon::setTestNow();
        $admin = $this->admin(superAdmin: true);

        $first = $this->page($admin, 'dashboard.contact-messages.trash');
        $this->assertSame(15, substr_count($first->getContent(), 'data-trashed-message-id="'));
        $first->assertSeeInOrder(['Trashed 17', 'Trashed 16', 'Trashed 03'])->assertDontSee('Trashed 02')->assertSee('page=2', false);

        $second = $this->page($admin, 'dashboard.contact-messages.trash', ['page' => 2]);
        $this->assertSame(2, substr_count($second->getContent(), 'data-trashed-message-id="'));
        $second->assertSeeInOrder(['Trashed 02', 'Trashed 01']);
    }

    public function test_the_empty_trash_shows_its_empty_state(): void
    {
        $this->message();

        $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.trash')
            ->assertSee('The trash is empty')
            ->assertSee('Messages appear here after they are deleted from the inbox.')
            ->assertDontSee('<table', false);
    }

    public function test_the_switcher_shows_the_trash_count_to_super_admins_only_and_the_inbox_hides_deleted_messages(): void
    {
        $this->message(['name' => 'Inbox Iman']);
        $this->trashed(['name' => 'Gone Ghada']);
        $this->trashed();

        $super = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.index');
        $super->assertSee('data-contact-messages-switcher', false)->assertSee('(2)')->assertSee('Inbox Iman')->assertDontSee('Gone Ghada');
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('dashboard.contact-messages.index'), '#').'" class="btn btn-sm btn-primary"\s+aria-current="page"#', $super->getContent());

        $trash = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.trash');
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('dashboard.contact-messages.trash'), '#').'" class="btn btn-sm btn-primary"\s+aria-current="page"#', $trash->getContent());

        $viewer = $this->page($this->admin(['contact-messages.view']), 'dashboard.contact-messages.index');
        $viewer->assertSee('Inbox Iman')->assertDontSee('Gone Ghada')
            ->assertDontSee('data-contact-messages-switcher', false)
            ->assertDontSee(route('dashboard.contact-messages.trash'), false);
    }

    // ---------------------------------------------------------------- Trash details

    public function test_the_trash_details_show_the_deleted_state_without_changing_read_at(): void
    {
        Carbon::setTestNow('2026-10-19 07:15:00');
        $unread = $this->trashed(['customer_id' => Customer::factory()->create(['name' => 'Linked Lina'])->id]);
        Carbon::setTestNow();
        $read = $this->trashed(['read_at' => Carbon::parse('2026-10-18 12:00:00')]);
        $admin = $this->admin(superAdmin: true);

        $html = $this->page($admin, 'dashboard.contact-messages.trash.show', $unread->id)->getContent();
        $this->assertNull(ContactMessage::withTrashed()->find($unread->id)->read_at, 'Viewing in the Trash never marks read.');
        $this->assertStringContainsString('data-contact-message-deleted>Deleted<', $html);
        $this->assertStringContainsString('data-contact-message-deleted-at>2026-10-19 07:15<', $html);
        $this->assertStringContainsString('href="'.route('dashboard.contact-messages.trash').'" class="admin-product-back"', $html);
        $this->assertStringContainsString('Back to the trash', $html);
        $this->assertStringContainsString('Linked Lina', $html);
        // The ordinary soft-delete form is hidden; restore and permanent deletion are shown.
        $this->assertStringNotContainsString('data-contact-message-delete>', $html);
        $this->assertStringContainsString('action="'.route('dashboard.contact-messages.restore', $unread->id).'"', $html);
        $this->assertStringContainsString('action="'.route('dashboard.contact-messages.purge', $unread->id).'"', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('Type “delete permanently” to confirm', $html);
        $this->assertMatchesRegularExpression('#<input class="form-control" type="text" id="contact-message-purge-phrase" name="confirmation_phrase" required autocomplete="off"#', $html);

        $this->page($admin, 'dashboard.contact-messages.trash.show', $read->id);
        $this->assertSame('2026-10-18 12:00:00', ContactMessage::withTrashed()->find($read->id)->read_at->toDateTimeString());
    }

    public function test_the_trash_details_escape_customer_content(): void
    {
        $message = $this->trashed([
            'name' => '<b>Mallory</b>',
            'subject' => '<script>alert(1)</script>',
            'message' => "Line <img src=x onerror=alert(1)>\nNext",
        ]);

        $html = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.trash.show', $message->id)->getContent();
        $list = $this->page($this->admin(superAdmin: true), 'dashboard.contact-messages.trash')->getContent();

        foreach ([$html, $list] as $content) {
            $this->assertStringContainsString('&lt;b&gt;Mallory&lt;/b&gt;', $content);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        }
        $this->assertStringContainsString("data-contact-message-body>Line &lt;img src=x onerror=alert(1)&gt;\nNext</dd>", $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_active_unknown_and_purged_messages_are_404_in_the_trash_details(): void
    {
        $admin = $this->admin(superAdmin: true);
        $active = $this->message();
        $purged = $this->trashed();
        $purged->forceDelete();

        foreach ([$active->id, 999999, $purged->id] as $id) {
            $this->as($admin)->get(route('dashboard.contact-messages.trash.show', $id))->assertNotFound();
        }
        $this->assertNull($active->fresh()->read_at);
    }

    // ---------------------------------------------------------------- restore

    public function test_restore_clears_deleted_at_and_keeps_every_business_field(): void
    {
        $customer = Customer::factory()->create();
        $message = $this->trashed([
            'customer_id' => $customer->id, 'name' => 'Rami', 'email' => 'rami@example.com', 'subject' => 'Order 12',
            'message' => "Two\nlines", 'read_at' => Carbon::parse('2026-10-18 08:00:00'),
        ]);
        $before = ContactMessage::withTrashed()->find($message->id)->only(['customer_id', 'name', 'email', 'subject', 'message', 'created_at']);
        $admin = $this->admin(superAdmin: true);

        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $message->id))
            ->assertRedirect(route('dashboard.contact-messages.trash'))
            ->assertSessionHas('success', 'The message was restored.');

        $restored = ContactMessage::query()->find($message->id);
        $this->assertNotNull($restored, 'Back among the active messages.');
        $this->assertNull($restored->deleted_at);
        $this->assertSame('2026-10-18 08:00:00', $restored->read_at->toDateTimeString());
        $this->assertEquals($before, $restored->only(['customer_id', 'name', 'email', 'subject', 'message', 'created_at']));

        $this->page($admin, 'dashboard.contact-messages.index')->assertSee('Rami');
        $this->page($admin, 'dashboard.contact-messages.trash')->assertDontSee('Rami');
    }

    public function test_restoring_an_unread_message_keeps_it_unread(): void
    {
        $message = $this->trashed();

        $this->as($this->admin(superAdmin: true))->patch(route('dashboard.contact-messages.restore', $message->id))->assertRedirect();

        $this->assertNull($message->fresh()->read_at);
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_repeated_restore_and_restoring_an_active_or_unknown_message_are_404(): void
    {
        $admin = $this->admin(superAdmin: true);
        $message = $this->trashed();
        $active = $this->message();

        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $message->id))->assertRedirect();
        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $message->id))->assertNotFound();
        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $active->id))->assertNotFound();
        $this->as($admin)->patch(route('dashboard.contact-messages.restore', 999999))->assertNotFound();
    }

    // ---------------------------------------------------------------- permanent deletion

    public function test_a_super_admin_permanently_deletes_with_the_english_phrase_case_insensitively(): void
    {
        $admin = $this->admin(superAdmin: true);

        foreach (['delete permanently', '  DELETE   Permanently ', "delete\u{00A0}permanently\u{200F}"] as $phrase) {
            $message = $this->trashed();

            $this->as($admin)->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => $phrase])
                ->assertRedirect(route('dashboard.contact-messages.trash'))
                ->assertSessionHas('success', 'The message was permanently deleted.');

            $this->assertNull(ContactMessage::withTrashed()->find($message->id), json_encode($phrase).' deletes the row.');
        }
    }

    public function test_the_arabic_phrase_is_required_in_arabic(): void
    {
        $admin = $this->admin(superAdmin: true);
        $kept = $this->trashed();
        $purged = $this->trashed();

        $this->as($admin, 'ar')->delete(route('dashboard.contact-messages.purge', $kept->id), ['confirmation_phrase' => 'delete permanently'])
            ->assertRedirect(route('dashboard.contact-messages.trash.show', $kept->id));
        $this->assertNotNull(ContactMessage::withTrashed()->find($kept->id), 'The English phrase is refused in Arabic.');

        $this->as($admin, 'ar')->delete(route('dashboard.contact-messages.purge', $purged->id), ['confirmation_phrase' => ' حذف  نهائي '])
            ->assertRedirect(route('dashboard.contact-messages.trash'));
        $this->assertNull(ContactMessage::withTrashed()->find($purged->id));

        // The phrase shown is the Arabic one (label text from its English default until the seeder runs, then Arabic).
        $this->page($admin, 'dashboard.contact-messages.trash.show', $kept->id, 'ar')->assertSee('Type “حذف نهائي” to confirm');
        $this->seed(AdminContactMessageTranslationSeeder::class);
        $this->page($admin, 'dashboard.contact-messages.trash.show', $kept->id, 'ar')->assertSee('اكتب «حذف نهائي» للتأكيد');
    }

    public function test_missing_wrong_or_wrong_language_confirmation_keeps_the_message(): void
    {
        $admin = $this->admin(superAdmin: true);
        $message = $this->trashed();

        foreach ([[], ['confirmation_phrase' => ''], ['confirmation_phrase' => '   '], ['confirmation_phrase' => 'delete'], ['confirmation_phrase' => 'delete permanently!'], ['confirmation_phrase' => 'حذف نهائي'], ['confirmation_phrase' => ['delete permanently']]] as $payload) {
            $this->as($admin)->delete(route('dashboard.contact-messages.purge', $message->id), $payload)
                ->assertRedirect(route('dashboard.contact-messages.trash.show', $message->id))
                ->assertSessionHas('danger', 'The permanent deletion was not confirmed. Type the phrase exactly as shown.');
        }

        $this->assertNotNull(ContactMessage::withTrashed()->find($message->id)?->deleted_at, 'Still in the Trash.');

        $this->as($admin)->followingRedirects()->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => 'nope'])
            ->assertOk()->assertSee('role="alert"', false)->assertSee('Type the phrase exactly as shown.');
    }

    public function test_an_active_message_cannot_be_purged_and_repeated_purge_is_404(): void
    {
        $admin = $this->admin(superAdmin: true);
        $active = $this->message();
        $message = $this->trashed();

        $this->as($admin)->delete(route('dashboard.contact-messages.purge', $active->id), ['confirmation_phrase' => 'delete permanently'])->assertNotFound();
        $this->assertNotNull(ContactMessage::query()->find($active->id), 'Never deleted from the inbox.');

        $this->as($admin)->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => 'delete permanently'])->assertRedirect();
        $this->as($admin)->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => 'delete permanently'])->assertNotFound();
        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $message->id))->assertNotFound();
        $this->as($admin)->delete(route('dashboard.contact-messages.purge', 999999), ['confirmation_phrase' => 'delete permanently'])->assertNotFound();
    }

    public function test_the_inbox_and_its_details_never_offer_permanent_deletion(): void
    {
        $message = $this->message();
        $admin = $this->admin(superAdmin: true);

        $this->page($admin, 'dashboard.contact-messages.show', $message->id)
            ->assertDontSee('data-contact-message-purge', false)
            ->assertDontSee('data-contact-message-restore', false)
            ->assertDontSee('confirmation_phrase', false);
        $this->page($admin, 'dashboard.contact-messages.index')->assertDontSee('confirmation_phrase', false);
    }

    // ---------------------------------------------------------------- CSRF, translations

    public function test_restore_and_purge_require_the_csrf_token(): void
    {
        $message = $this->trashed();
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $admin = $this->admin(superAdmin: true);

        $this->as($admin)->patch(route('dashboard.contact-messages.restore', $message->id))->assertStatus(419);
        $this->as($admin)->delete(route('dashboard.contact-messages.purge', $message->id), ['confirmation_phrase' => 'delete permanently'])->assertStatus(419);

        $this->assertNotNull(ContactMessage::withTrashed()->find($message->id)?->deleted_at, 'Neither restored nor deleted.');
    }

    public function test_the_seeded_arabic_trash_texts_are_shown(): void
    {
        $this->seed(AdminContactMessageTranslationSeeder::class);
        $message = $this->trashed();
        $admin = $this->admin(superAdmin: true);

        $this->page($admin, 'dashboard.contact-messages.trash', locale: 'ar')
            ->assertSee('سلة المحذوفات')->assertSee('تاريخ الحذف')->assertSee('الرسائل');
        $this->page($admin, 'dashboard.contact-messages.trash.show', $message->id, 'ar')
            ->assertSee('محذوفة')->assertSee('العودة إلى سلة المحذوفات')->assertSee('استعادة الرسالة')->assertSee('حذف نهائي');
        $this->as($admin, 'ar')->followingRedirects()->patch(route('dashboard.contact-messages.restore', $message->id))->assertSee('تمت استعادة الرسالة.');
    }

    public function test_the_trash_pages_and_actions_create_no_translation_rows(): void
    {
        $admin = $this->admin(superAdmin: true);
        $first = $this->trashed();
        $second = $this->trashed();

        foreach (['ar', 'en'] as $locale) {
            $this->page($admin, 'dashboard.contact-messages.index', locale: $locale);
            $this->page($admin, 'dashboard.contact-messages.trash', locale: $locale);
            $this->page($admin, 'dashboard.contact-messages.trash.show', $first->id, $locale);
            $this->page($admin, 'dashboard.contact-messages.trash', ['page' => 9], $locale)->assertSee('The trash is empty');
        }
        $this->as($admin)->followingRedirects()->delete(route('dashboard.contact-messages.purge', $first->id), ['confirmation_phrase' => 'x'])->assertOk();
        $this->as($admin)->followingRedirects()->delete(route('dashboard.contact-messages.purge', $first->id), ['confirmation_phrase' => 'delete permanently'])->assertOk();
        $this->as($admin)->followingRedirects()->patch(route('dashboard.contact-messages.restore', $second->id))->assertOk();

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

    private function trashed(array $attributes = []): ContactMessage
    {
        $message = $this->message($attributes);
        $message->delete();

        return $message;
    }

    /** Acting as the admin in a locale (English by default, so the untranslated defaults are asserted). */
    private function as(Admin $admin, string $locale = 'en'): static
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale]);
    }

    private function page(Admin $admin, string $route, mixed $parameters = [], string $locale = 'en'): TestResponse
    {
        return $this->as($admin, $locale)->get(route($route, $parameters ?? []))->assertOk();
    }
}
