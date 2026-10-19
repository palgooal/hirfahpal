<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * CONTACT-ADMIN-DELETE-02: 2026_10_19_000000_add_deleted_at_to_contact_messages_table. The nullable deleted_at column,
 * no existing row changed, and a rollback refused while any message is soft-deleted (it would bring them back into
 * the admin list).
 */
class ContactMessageSoftDeleteMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_19_000000_add_deleted_at_to_contact_messages_table.php';

    public function test_deleted_at_is_a_nullable_timestamp(): void
    {
        $column = collect(Schema::getColumns('contact_messages'))->firstWhere('name', 'deleted_at');

        $this->assertNotNull($column);
        $this->assertTrue($column['nullable']);
        $this->assertNull($column['default']);
    }

    public function test_rollback_and_reapply_keep_every_message_when_none_is_deleted(): void
    {
        $id = $this->message()->id;
        $before = DB::table('contact_messages')->where('id', $id)->first(['id', 'name', 'email', 'subject', 'message', 'read_at']);

        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('contact_messages', 'deleted_at'));
        $this->assertEquals($before, DB::table('contact_messages')->where('id', $id)->first(['id', 'name', 'email', 'subject', 'message', 'read_at']));

        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('contact_messages', 'deleted_at'));
        $this->assertNull(DB::table('contact_messages')->where('id', $id)->value('deleted_at'), 'No backfill.');
    }

    public function test_rollback_is_refused_while_a_message_is_soft_deleted(): void
    {
        $message = $this->message();
        $message->delete();

        try {
            $this->migration()->down();
            $this->fail('The rollback must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('1 contact message(s) are soft-deleted', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('contact_messages', 'deleted_at'));
        $this->assertSoftDeleted('contact_messages', ['id' => $message->id]);
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function message(): ContactMessage
    {
        return ContactMessage::query()->create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    }
}
