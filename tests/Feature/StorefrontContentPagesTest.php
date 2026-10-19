<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\StorefrontContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontContentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_content_pages_are_rendered_from_the_database(): void
    {
        StorefrontContentPage::query()->where('slug', 'about')->update([
            'title' => 'Our dynamic story',
            'body' => 'This content comes from the database.',
        ]);

        $this->get(route('content.show', 'about'))
            ->assertOk()
            ->assertSee('Our dynamic story')
            ->assertSee('This content comes from the database.');

        StorefrontContentPage::query()->where('slug', 'about')->update(['is_published' => false]);

        $this->get(route('content.show', 'about'))->assertNotFound();
    }

    public function test_contact_messages_are_validated_and_saved_for_guests_and_customers(): void
    {
        $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'invalid',
            'message' => '',
        ])->assertSessionHasErrors(['name', 'email', 'message']);

        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post(route('contact.store'), [
            'name' => 'Customer name',
            'email' => 'customer@example.com',
            'subject' => 'Order question',
            'message' => 'I need help with my order.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'customer_id' => $customer->id,
            'email' => 'customer@example.com',
            'subject' => 'Order question',
        ]);
        $this->assertSame(1, ContactMessage::query()->count());
    }

    public function test_contact_success_is_shown_once_inside_the_form(): void
    {
        $html = $this->from(route('contact.create'))->followingRedirects()->post(route('contact.store'), [
            'name' => 'Guest name',
            'email' => 'guest@example.com',
            'message' => 'A question about shipping.',
        ])->assertOk()->getContent();

        $success = 'تم إرسال رسالتك بنجاح. سنتواصل معك في أقرب وقت.';
        $this->assertSame(1, substr_count($html, $success), 'The success message is shown once, not again by the layout.');
        $this->assertMatchesRegularExpression('#<form[^>]*>.*role="status" data-contact-success.*'.preg_quote($success, '#').'.*</form>#s', $html);
        $this->assertStringNotContainsString('data-contact-errors', $html);
        $this->assertNull(ContactMessage::query()->sole()->customer_id);
    }

    public function test_contact_validation_errors_are_shown_in_the_form_with_arabic_field_names_and_old_input(): void
    {
        $html = $this->from(route('contact.create'))->followingRedirects()->post(route('contact.store'), [
            'name' => 'Kept name',
            'email' => 'not-an-email',
            'subject' => str_repeat('s', 256),
            'message' => '   ',
        ])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<form[^>]*>.*role="alert" data-contact-errors.*</form>#s', $html);
        foreach (['email', 'subject', 'message'] as $field) {
            $this->assertStringContainsString('aria-describedby="contact-'.$field.'-error"', $html);
            $this->assertStringContainsString('id="contact-'.$field.'-error"', $html);
        }
        $this->assertStringNotContainsString('id="contact-name-error"', $html);
        $this->assertStringContainsString('حقل الرسالة مطلوب.', $html);
        $this->assertStringContainsString('يجب ألا يزيد طول حقل الموضوع عن 255 حرفًا.', $html);
        $this->assertStringNotContainsString('حقل message', $html);
        $this->assertStringNotContainsString('حقل subject', $html);
        $this->assertStringContainsString('value="Kept name"', $html);
        $this->assertStringContainsString('value="not-an-email"', $html);
        // The layout's page-level message line is not rendered on the contact page.
        $this->assertStringNotContainsString('px-4 py-3 text-sm text-copper" role="status"', $html);
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_other_storefront_pages_keep_the_layout_feedback_line(): void
    {
        $this->withSession(['success' => 'Saved elsewhere'])->get(route('categories'))
            ->assertOk()
            ->assertSee('px-4 py-3 text-sm text-copper" role="status"', false)
            ->assertSee('Saved elsewhere');
    }
}
