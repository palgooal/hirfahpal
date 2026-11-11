<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Palgoal\MediaLibrary\Models\Media;
use RuntimeException;
use Tests\TestCase;

/**
 * SETTINGS-MEDIA-01: SQLite path of 2026_11_11_000000_add_brand_media_to_settings_table. DatabaseMigrations (not
 * RefreshDatabase) so up()/down() run outside a transaction, as they do under the migrator. On SQLite the columns are
 * added natively (no table rebuild), so the named CHECK of settings.site_availability_mode survives both ways.
 */
class SettingBrandMediaMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_11_11_000000_add_brand_media_to_settings_table.php';

    public function test_the_columns_are_nullable_indexed_restrict_references_to_media(): void
    {
        $columns = collect(Schema::getColumns('settings'))->keyBy('name');
        $keys = collect(Schema::getForeignKeys('settings'));

        foreach (['logo_media_id' => 'settings_logo_media_id_index', 'favicon_media_id' => 'settings_favicon_media_id_index'] as $column => $index) {
            $this->assertTrue($columns[$column]['nullable'], $column);
            $this->assertSame('integer', $columns[$column]['type_name'], $column);
            $this->assertNull($columns[$column]['default'], $column);
            $this->assertContains($index, collect(Schema::getIndexes('settings'))->pluck('name')->all());
            $matching = $keys->filter(fn (array $fk) => $fk['columns'] === [$column])->values();
            $this->assertCount(1, $matching, $column);
            $this->assertSame(['media', ['id'], 'restrict'], [$matching[0]['foreign_table'], $matching[0]['foreign_columns'], $matching[0]['on_delete']], $column);
        }

        // The legacy path columns, the site availability CHECK and its foreign key are untouched.
        $this->assertTrue(Schema::hasColumns('settings', ['logo', 'favicon']));
        $this->assertStringContainsString('settings_site_availability_mode_check', $this->tableSql());
        $this->assertSame('set null', $keys->firstWhere('columns', ['site_availability_changed_by'])['on_delete']);
    }

    public function test_rollback_and_reapply_keep_every_row_value_the_check_and_the_other_keys(): void
    {
        $media = $this->media();
        $admin = Admin::factory()->create();
        Setting::singleton()->forceFill([
            'site_name' => 'Hirfah', 'logo' => 'settings/old-logo.png', 'favicon' => 'settings/old.png', 'logo_media_id' => $media->id,
            'site_availability_mode' => 'coming_soon', 'site_availability_changed_by' => $admin->id, 'footer_content' => ['tagline' => 'x'],
        ])->save();
        $before = (array) DB::table('settings')->first();
        $migration = $this->migration();

        $migration->down();

        $this->assertFalse(Schema::hasColumn('settings', 'logo_media_id'));
        $this->assertFalse(Schema::hasColumn('settings', 'favicon_media_id'));
        $expected = $before;
        unset($expected['logo_media_id'], $expected['favicon_media_id']);
        $this->assertSame($expected, (array) DB::table('settings')->first(), 'down() keeps every other value.');
        $this->assertStringContainsString('settings_site_availability_mode_check', $this->tableSql());
        $this->assertSame([['site_availability_changed_by']], collect(Schema::getForeignKeys('settings'))->pluck('columns')->all());
        $this->assertSame(['settings_singleton_key_unique'], array_values(array_diff(
            collect(Schema::getIndexes('settings'))->pluck('name')->all(), ['primary']
        )), 'The other indexes are recreated, the two own indexes are gone.');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // The CHECK still refuses an unknown state after the rebuild.
        $this->assertFalse(rescue(fn () => DB::table('settings')->update(['site_availability_mode' => 'closed']) >= 0, false, false));

        $migration->up();
        $migration->up(); // Already migrated: checked and skipped.

        $after = (array) DB::table('settings')->first();
        $this->assertNull($after['logo_media_id']);
        $this->assertNull($after['favicon_media_id']);
        unset($after['logo_media_id'], $after['favicon_media_id']);
        $this->assertSame($expected, $after, 'up() copies nothing from the legacy paths.');
        $this->assertStringContainsString('settings_site_availability_mode_check', $this->tableSql());
        $this->assertCount(3, Schema::getForeignKeys('settings'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_a_partial_state_is_refused_without_changes(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::statement('alter table "settings" add column "logo_media_id" integer null');
        $sql = $this->tableSql();

        try {
            $migration->up();
            $this->fail('A partly migrated table must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('partly migrated', $exception->getMessage());
        }

        $this->assertSame($sql, $this->tableSql());

        // Restore the expected state so the suite's rollback can run.
        DB::statement('alter table "settings" drop column "logo_media_id"');
        $migration->up();
    }

    public function test_down_refuses_to_rebuild_inside_a_transaction(): void
    {
        DB::beginTransaction();

        try {
            $this->migration()->down();
            $this->fail('down() must refuse to rebuild inside a transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('inside a transaction', $exception->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertTrue(Schema::hasColumn('settings', 'logo_media_id'));
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function media(): Media
    {
        return Media::create([
            'file_name' => 'logo.png', 'file_original_name' => 'logo.png', 'file_path' => 'media/2026/11/logo.png',
            'file_extension' => 'png', 'mime_type' => 'image/png', 'size' => 4, 'file_type' => 'image', 'disk' => 'public',
        ]);
    }

    private function tableSql(): string
    {
        return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'settings')->value('sql');
    }
}
