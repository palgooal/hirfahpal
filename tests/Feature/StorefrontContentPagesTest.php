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
}
