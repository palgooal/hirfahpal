<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY §6.1: 2026_10_20_000000_add_site_availability_to_settings_table. The three columns, the safe
 * default 'open' for an existing row, the CHECK constraint, the admin reference cleared on delete, and down()/up().
 */
class SiteAvailabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_20_000000_add_site_availability_to_settings_table.php';

    public function test_the_columns_exist_with_the_expected_nullability_and_default(): void
    {
        $columns = collect(Schema::getColumns('settings'))->keyBy('name');

        $this->assertFalse($columns['site_availability_mode']['nullable']);
        $this->assertStringContainsString('open', (string) $columns['site_availability_mode']['default']);
        $this->assertTrue($columns['site_availability_changed_at']['nullable']);
        $this->assertTrue($columns['site_availability_changed_by']['nullable']);

        $key = collect(Schema::getForeignKeys('settings'))->first(fn (array $fk) => $fk['columns'] === ['site_availability_changed_by']);
        $this->assertSame('admins', $key['foreign_table']);
        $this->assertSame('set null', $key['on_delete']);
    }

    public function test_an_existing_settings_row_defaults_to_open(): void
    {
        $setting = Setting::singleton();

        $this->assertSame('open', DB::table('settings')->where('id', $setting->id)->value('site_availability_mode'));
        $this->assertNull(DB::table('settings')->where('id', $setting->id)->value('site_availability_changed_at'));
    }

    public function test_only_the_three_states_are_accepted(): void
    {
        Setting::singleton();

        foreach (['open', 'coming_soon', 'maintenance'] as $state) {
            DB::table('settings')->update(['site_availability_mode' => $state]);
            $this->assertSame($state, DB::table('settings')->value('site_availability_mode'));
        }

        $this->expectException(QueryException::class);
        DB::table('settings')->update(['site_availability_mode' => 'closed']);
    }

    public function test_deleting_the_admin_who_changed_the_state_clears_the_reference(): void
    {
        Setting::singleton();
        $admin = Admin::create(['name' => 'Admin', 'email' => 'a@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        DB::table('settings')->update(['site_availability_mode' => 'coming_soon', 'site_availability_changed_by' => $admin->id, 'site_availability_changed_at' => now()]);

        $admin->delete();

        $this->assertNull(DB::table('settings')->value('site_availability_changed_by'));
        $this->assertSame('coming_soon', DB::table('settings')->value('site_availability_mode'));
    }

    public function test_only_the_exact_values_are_accepted_case_and_spaces_included(): void
    {
        Setting::singleton();

        foreach (['OPEN', 'Open', 'maintenance ', ' maintenance', 'coming soon', 'coming-soon', '', 'closed'] as $invalid) {
            try {
                DB::table('settings')->update(['site_availability_mode' => $invalid]);
                $this->fail(json_encode($invalid).' was accepted.');
            } catch (QueryException) {
                $this->assertSame('open', DB::table('settings')->value('site_availability_mode'), json_encode($invalid).' left the stored value unchanged.');
            }
        }
    }

    public function test_up_on_the_fully_applied_schema_changes_nothing(): void
    {
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => 'maintenance']);
        $before = collect(Schema::getColumns('settings'))->pluck('name')->all();

        $this->migration()->up();

        $this->assertSame($before, collect(Schema::getColumns('settings'))->pluck('name')->all());
        $this->assertSame('maintenance', DB::table('settings')->value('site_availability_mode'));
    }

    public function test_up_refuses_every_partial_state_without_changing_anything(): void
    {
        Setting::singleton()->update(['site_name' => 'Hirfah']);
        $migration = $this->migration();
        $partials = [
            'changed_at only' => fn () => Schema::table('settings', fn ($table) => $table->timestamp('site_availability_changed_at')->nullable()),
            'changed_by and its key' => fn () => Schema::table('settings', fn ($table) => $table->foreignId('site_availability_changed_by')->nullable()->constrained('admins')->nullOnDelete()),
            'mode with its check' => fn () => DB::statement("ALTER TABLE settings ADD COLUMN site_availability_mode VARCHAR(20) NOT NULL DEFAULT 'open' CONSTRAINT settings_site_availability_mode_check CHECK (site_availability_mode IN ('open', 'coming_soon', 'maintenance'))"),
        ];

        foreach ($partials as $label => $create) {
            $migration->down();
            $create();
            $columns = collect(Schema::getColumns('settings'))->pluck('name')->all();

            try {
                $migration->up();
                $this->fail("up() must refuse the partial state: {$label}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('partly migrated for site availability', $e->getMessage(), $label);
            }

            $this->assertSame($columns, collect(Schema::getColumns('settings'))->pluck('name')->all(), "{$label}: nothing was changed.");
            $this->assertSame('Hirfah', DB::table('settings')->value('site_name'));
        }

        // down() clears the last partial state; up() then applies cleanly.
        $migration->down();
        $migration->up();
        $this->assertSame('open', DB::table('settings')->value('site_availability_mode'));
    }

    public function test_down_removes_only_owned_objects_that_exist_and_is_safe_to_repeat(): void
    {
        Setting::singleton()->update(['site_name' => 'Hirfah']);
        $migration = $this->migration();
        $migration->down();
        $baseline = collect(Schema::getColumns('settings'))->pluck('name')->all();

        // Partial: changed_by with its key only.
        Schema::table('settings', fn ($table) => $table->foreignId('site_availability_changed_by')->nullable()->constrained('admins')->nullOnDelete());
        $migration->down();
        $this->assertSame($baseline, collect(Schema::getColumns('settings'))->pluck('name')->all());
        $this->assertSame([], Schema::getForeignKeys('settings'));

        // Nothing to remove: a no-op, twice.
        $migration->down();
        $migration->down();
        $this->assertSame($baseline, collect(Schema::getColumns('settings'))->pluck('name')->all());
        $this->assertSame('Hirfah', DB::table('settings')->value('site_name'));

        $migration->up();
    }

    public function test_down_removes_the_columns_and_up_restores_them_without_touching_other_settings(): void
    {
        Setting::singleton()->update(['site_name' => 'Hirfah']);
        $migration = require database_path(self::MIGRATION);

        $migration->down();
        foreach (['site_availability_mode', 'site_availability_changed_at', 'site_availability_changed_by'] as $column) {
            $this->assertFalse(Schema::hasColumn('settings', $column), $column);
        }
        $this->assertSame('Hirfah', DB::table('settings')->value('site_name'));

        $migration->up();
        $this->assertSame('open', DB::table('settings')->value('site_availability_mode'));
        $this->assertSame('Hirfah', DB::table('settings')->value('site_name'));

        $this->expectException(QueryException::class);
        DB::table('settings')->update(['site_availability_mode' => 'closed']);
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }
}
