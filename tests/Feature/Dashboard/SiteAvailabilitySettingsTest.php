<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\Dashboard\SiteAvailabilityController;
use App\Models\Admin;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Support\SiteAvailability\SiteAvailabilitySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 3B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7): the Super Admin site state card
 * on the settings page and PATCH dashboard/settings/site-availability. Visibility and 403 for everyone else, strict
 * state values, required confirmation, migration-pending behaviour, locked transactional write with a no-op for the
 * same state, the environment override shown apart from the saved state, the general settings form unchanged.
 */
class SiteAvailabilitySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_20_000000_add_site_availability_to_settings_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['site-availability.override' => 'auto']);
    }

    // ---------------------------------------------------------------- visibility

    public function test_only_a_super_admin_sees_the_card(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $this->settingsPage($super)
            ->assertSee('id="site-availability"', false)
            ->assertSee('name="state"', false)
            ->assertSee('value="coming_soon"', false)
            ->assertSee('name="confirm"', false)
            ->assertSee(route('dashboard.setting.site-availability.update'), false);

        foreach ([['settings.view'], ['settings.view', 'settings.edit'], ['settings.view', 'maintenance.bypass']] as $abilities) {
            $this->settingsPage($this->adminWithAbilities($abilities))
                ->assertDontSee('id="site-availability"', false)
                ->assertDontSee(route('dashboard.setting.site-availability.update'), false);
        }
    }

    public function test_the_card_is_not_read_for_other_admins(): void
    {
        $admin = $this->adminWithAbilities(['settings.view', 'settings.edit']);
        $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'))->assertOk();
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        // Phase 3C: the header badge reads the effective state for every admin (one memoized read of the mode). The card's
        // own data — the saved row with its date and author, the migration record, the schema — is never read for them.
        $this->assertStringNotContainsString('site_availability_changed', $queries);
        $this->assertStringNotContainsString('"migrations"', $queries);
        $this->assertStringNotContainsString('pragma_table_xinfo', $queries, 'No schema check for them either.');
        $this->assertSame(1, substr_count($queries, 'site_availability_mode'), 'Only the header state read.');
    }

    public function test_the_card_shows_the_effective_and_saved_state_and_the_last_change(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $other = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('coming_soon', $other, '2026-10-20 14:05:00');

        $this->settingsPage($super)
            ->assertSee('data-site-availability-effective="coming_soon"', false)
            ->assertSee('data-site-availability-source="database"', false)
            ->assertSee('data-site-availability-stored="coming_soon"', false)
            ->assertSee('2026-10-20 14:05')
            ->assertSee($other->name)
            ->assertSee('id="site-availability-coming_soon" value="coming_soon" checked', false)
            ->assertSee('data-site-availability-warning="maintenance"', false)
            ->assertSee('data-site-availability-warning="launch"', false)
            ->assertDontSee('data-site-availability-override', false)
            ->assertDontSee('data-site-availability-migration-pending', false);
    }

    public function test_a_never_changed_state_and_a_deleted_admin(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $this->settingsPage($super)
            ->assertSee('data-site-availability-effective="open"', false)
            ->assertSee('data-site-availability-stored="open"', false)
            ->assertDontSee('data-site-availability-warning="launch"', false);

        $gone = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('maintenance', $gone, '2026-10-19 09:00:00');
        $gone->delete();

        $this->settingsPage($super)
            ->assertSee('2026-10-19 09:00')
            ->assertSee('Deleted admin');
    }

    // ---------------------------------------------------------------- authorization

    public function test_non_super_admins_get_403_and_nothing_is_written(): void
    {
        Setting::singleton();
        $before = $this->settingsRow();

        foreach ([['settings.view', 'settings.edit'], ['maintenance.bypass'], ['admins.edit', 'settings.edit', 'maintenance.bypass'], []] as $abilities) {
            $this->actingAs($this->adminWithAbilities($abilities), 'admin')
                ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance', 'confirm' => '1'])
                ->assertForbidden();
        }

        $this->assertSame($before, $this->settingsRow());
    }

    public function test_the_super_admin_check_comes_before_any_settings_or_schema_read(): void
    {
        $admin = $this->adminWithAbilities(['settings.edit']);
        $this->actingAs($admin, 'admin')->get(route('dashboard.home'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance', 'confirm' => '1'])
            ->assertForbidden();
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('"settings"', $queries);
        $this->assertStringNotContainsString('pragma_table_xinfo', $queries);
    }

    public function test_a_blocked_super_admin_and_a_guest_cannot_save(): void
    {
        Setting::singleton();
        $before = $this->settingsRow();

        $this->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance', 'confirm' => '1'])
            ->assertRedirect(route('admin.login'));

        $this->createAdmin(superAdmin: true);
        $blocked = $this->createAdmin(superAdmin: true);
        $blocked->update(['status' => 'blocked']);
        // EnsureAccountIsActive signs a blocked account out before the controller runs.
        $this->actingAs($blocked, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance', 'confirm' => '1'])
            ->assertSessionMissing(SiteAvailabilityController::FLASH);
        $this->assertGuest('admin');

        $this->assertSame($before, $this->settingsRow());
    }

    // ---------------------------------------------------------------- validation and confirmation

    public function test_the_state_is_validated_strictly(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        $before = $this->settingsRow();

        foreach (['OPEN', 'Maintenance', 'closed', '', 'coming-soon', '1', ['maintenance'], null] as $state) {
            $payload = ['confirm' => '1'];
            if ($state !== null) {
                $payload['state'] = $state;
            }

            $this->assertFlash($this->actingAs($super, 'admin')->patch(route('dashboard.setting.site-availability.update'), $payload), 'danger', 'Choose one of the three site states.');
        }

        $this->assertSame($before, $this->settingsRow());
    }

    public function test_the_confirmation_is_required(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        $before = $this->settingsRow();

        foreach ([[], ['confirm' => '0'], ['confirm' => ''], ['confirm' => 'no']] as $confirm) {
            $this->assertFlash(
                $this->actingAs($super, 'admin')->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance'] + $confirm),
                'danger',
                'The change was not confirmed. Tick the confirmation box to save.'
            );
        }

        $this->assertSame($before, $this->settingsRow());
    }

    // ---------------------------------------------------------------- saving

    public function test_a_super_admin_saves_each_state_with_date_and_author(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->travelTo('2026-10-20 10:00:00');

        foreach (['coming_soon', 'maintenance', 'open'] as $state) {
            $this->assertFlash($this->save($super, $state), 'success', 'The site state was saved.');

            $row = $this->settingsRow();
            $this->assertSame($state, $row['site_availability_mode']);
            $this->assertSame((int) $super->id, (int) $row['site_availability_changed_by']);
            $this->assertStringStartsWith('2026-10-20 10:00', (string) $row['site_availability_changed_at']);
        }

        $this->assertSame(1, DB::table('settings')->count(), 'Still one settings row.');
    }

    public function test_the_saved_state_is_what_the_gate_applies(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->save($super, 'maintenance');

        // As a visitor (the signed-in Super Admin would preview the closed store).
        auth('admin')->logout();
        $this->get('/')->assertStatus(503)->assertSee('data-site-availability="maintenance"', false);
    }

    public function test_the_same_state_is_a_no_op(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->travelTo('2026-10-20 10:00:00');
        $this->save($super, 'coming_soon');
        $before = $this->settingsRow();

        $this->travelTo('2026-10-21 12:00:00');
        $other = $this->createAdmin(superAdmin: true);
        $this->assertFlash($this->save($other, 'coming_soon'), 'info', 'This is already the saved state. Nothing was changed.');

        $this->assertSame($before, $this->settingsRow(), 'Neither the date nor the author changes.');
    }

    public function test_the_write_locks_the_settings_row_inside_a_transaction(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        $levels = [];
        DB::listen(function ($query) use (&$levels) {
            if (str_contains($query->sql, 'site_availability_mode')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->save($super, 'maintenance');

        // RefreshDatabase wraps the test in one transaction: the write adds its own level on top.
        $this->assertNotEmpty($levels);
        $this->assertGreaterThanOrEqual(2, min($levels), 'The read and the write run inside the save transaction.');
    }

    public function test_a_database_error_during_the_write_is_not_reported_as_saved(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        DB::statement("CREATE TRIGGER fail_site_availability BEFORE UPDATE OF site_availability_mode ON settings BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        $before = $this->settingsRow();

        $response = $this->save($super, 'maintenance');

        $response->assertStatus(500);
        $response->assertSessionMissing(SiteAvailabilityController::FLASH);
        $this->assertSame($before, $this->settingsRow());
    }

    public function test_the_general_settings_form_cannot_write_the_availability_columns(): void
    {
        $admin = $this->adminWithAbilities(['settings.edit']);
        $super = $this->createAdmin(superAdmin: true);

        foreach ([$admin, $super] as $actor) {
            $this->actingAs($actor, 'admin')
                ->put(route('dashboard.setting.update'), $this->validPayload([
                    'site_availability_mode' => 'maintenance',
                    'site_availability_changed_by' => $actor->id,
                    'site_availability_changed_at' => '2020-01-01 00:00:00',
                ]))
                ->assertRedirect(route('dashboard.setting.index'));
        }

        $row = $this->settingsRow();
        $this->assertSame('open', $row['site_availability_mode']);
        $this->assertNull($row['site_availability_changed_by']);
        $this->assertNull($row['site_availability_changed_at']);
        $this->assertSame('Site', $row['site_name'], 'The general form still saves its own fields.');
    }

    // ---------------------------------------------------------------- environment override

    public function test_a_forced_override_is_shown_apart_from_the_saved_state_and_the_form_stays_usable(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('coming_soon', $super, '2026-10-20 08:00:00');
        config(['site-availability.override' => 'maintenance']);

        $this->settingsPage($super)
            ->assertSee('data-site-availability-override', false)
            ->assertSee('HIRFAH_SITE_AVAILABILITY=maintenance')
            ->assertSee('data-site-availability-effective="maintenance"', false)
            ->assertSee('data-site-availability-source="environment"', false)
            ->assertSee('data-site-availability-stored="coming_soon"', false)
            ->assertDontSee('<fieldset disabled', false);

        $this->assertFlash($this->save($super, 'open'), 'warning', 'The state was saved, but the server override still decides the site state until it is set back to auto.');
        $this->assertSame('open', $this->settingsRow()['site_availability_mode'], 'Saved.');
        auth('admin')->logout();
        $this->get('/')->assertStatus(503)->assertSee('data-site-availability="maintenance"', false);

        config(['site-availability.override' => 'auto']);
        $this->get('/')->assertOk()->assertDontSee('data-site-availability', false);
    }

    // ---------------------------------------------------------------- migration pending

    public function test_without_the_migration_the_card_warns_and_the_form_is_disabled(): void
    {
        // Migration record present, columns missing.
        $super = $this->createAdmin(superAdmin: true);
        $this->rollBackSiteAvailabilityMigration();
        $this->assertTrue($this->migrationRecorded());

        $this->assertNotReady($super);
        $this->settingsPage($super)
            ->assertSee('data-site-availability-effective="open"', false)
            ->assertSee('data-site-availability-stored=""', false);
    }

    public function test_columns_without_the_migration_record_are_not_ready(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        DB::table('migrations')->where('migration', SiteAvailabilitySettings::MIGRATION)->delete();
        $this->assertTrue(Schema::hasColumn('settings', 'site_availability_mode'), 'The three columns are still there.');

        $this->assertNotReady($super);
        $this->assertRefusedAsPending($super);
    }

    public function test_the_record_without_the_columns_refuses_a_direct_save(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->rollBackSiteAvailabilityMigration();

        $this->assertRefusedAsPending($super);
    }

    public function test_a_missing_migrations_table_is_not_ready(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Schema::drop('migrations');

        $this->assertNotReady($super);
        $this->assertRefusedAsPending($super);
    }

    public function test_a_similar_migration_name_does_not_count(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        DB::table('migrations')->where('migration', SiteAvailabilitySettings::MIGRATION)->update(['migration' => SiteAvailabilitySettings::MIGRATION.'_old']);

        $this->assertNotReady($super);
        $this->assertRefusedAsPending($super);
    }

    public function test_with_the_record_and_the_columns_the_form_is_ready_and_saves(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->assertTrue($this->migrationRecorded());

        $this->settingsPage($super)
            ->assertDontSee('data-site-availability-migration-pending', false)
            ->assertDontSee('<fieldset disabled', false)
            ->assertSee('data-site-availability-save', false);

        $this->assertFlash($this->save($super, 'maintenance'), 'success', 'The site state was saved.');
        $this->assertSame('maintenance', $this->settingsRow()['site_availability_mode']);
    }

    public function test_readiness_costs_a_bounded_number_of_queries_per_request(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->settingsPage($super);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->save($super, 'coming_soon');
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $migrationReads = array_filter($queries, fn (string $sql): bool => str_contains($sql, '"migrations"'));
        $this->assertCount(1, $migrationReads, 'The migration record is read once per save.');
    }

    // ---------------------------------------------------------------- UI corrections (U1–U4)

    public function test_the_effective_state_uses_the_dashboard_badges(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        foreach (['open' => 'is-active', 'coming_soon' => 'is-low', 'maintenance' => 'is-suspended'] as $state => $modifier) {
            $this->storeDirectly($state, $super, '2026-10-20 08:00:00');

            $card = $this->card($this->settingsPage($super));
            $this->assertStringContainsString('<span class="admin-products"><span class="admin-products-badge '.$modifier.'" data-site-availability-effective="'.$state.'">', $card, $state);
            $this->assertStringNotContainsString('bg-light-', $card);
        }
    }

    public function test_controls_sit_beside_their_labels_in_one_row(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $card = $this->card($this->settingsPage($super));

        foreach (['open', 'coming_soon', 'maintenance'] as $state) {
            $this->assertMatchesRegularExpression(
                '#<div class="d-flex align-items-start gap-2 mb-3" data-site-availability-option="'.$state.'">\s*<input class="form-check-input flex-shrink-0 mt-1" type="radio" name="state" id="site-availability-'.$state.'"[^>]*>\s*<div>\s*<label class="form-check-label fw-semibold" for="site-availability-'.$state.'">#',
                $card,
                $state
            );
        }
        $this->assertMatchesRegularExpression('#<div class="d-flex align-items-start gap-2 mt-4" data-site-availability-confirm-row>\s*<input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="confirm"[^>]*>\s*<label class="form-check-label" for="site-availability-confirm">#', $card);
        $this->assertStringNotContainsString('class="form-check ', $card, 'No floated .form-check wrapper in the card.');
        $this->assertStringNotContainsString('class="form-check"', $card);
    }

    public function test_the_launch_warning_uses_the_dark_amber(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('coming_soon', $super, '2026-10-20 08:00:00');
        $card = $this->card($this->settingsPage($super));

        $this->assertStringContainsString('<p class="small mb-0 mt-1" style="color: #8a4520" data-site-availability-warning="launch">The store will be open to everyone as soon as you save.</p>', $card);
        $this->assertStringNotContainsString('text-warning', $card);
    }

    public function test_the_general_settings_form_keeps_its_markup(): void
    {
        $editor = $this->adminWithAbilities(['settings.view', 'settings.edit']);
        $html = $this->settingsPage($editor)->getContent();

        $this->assertStringContainsString('name="site_name"', $html);
        $this->assertStringContainsString('action="'.route('dashboard.setting.update').'"', $html);
        $this->assertStringContainsString('>Save</button>', $html);
        $this->assertStringNotContainsString('id="site-availability"', $html, 'No card for them (the header badge is Phase 3C).');
    }

    public function test_without_the_migration_a_direct_save_is_rejected_and_the_schema_is_untouched(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->rollBackSiteAvailabilityMigration();
        $columns = Schema::getColumnListing('settings');
        Setting::singleton();
        $before = DB::table('settings')->get()->toArray();

        $this->assertFlash($this->save($super, 'maintenance'), 'danger', 'The site availability migration has not been applied yet. Nothing was saved.');

        $this->assertSame($columns, Schema::getColumnListing('settings'), 'No column was added.');
        $this->assertEquals($before, DB::table('settings')->get()->toArray(), 'Nothing written.');
        $this->get('/')->assertOk();
    }

    // ---------------------------------------------------------------- existing settings unchanged

    public function test_the_general_settings_page_and_form_work_as_before(): void
    {
        $viewer = $this->adminWithAbilities(['settings.view']);
        $this->settingsPage($viewer)->assertSee('name="site_name"', false)->assertDontSee('>Save</button>', false);

        $editor = $this->adminWithAbilities(['settings.edit']);
        $this->actingAs($editor, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload(['site_name' => 'Kept working']))
            ->assertRedirect(route('dashboard.setting.index'))
            ->assertSessionHas('success');
        $this->assertSame('Kept working', Setting::singleton()->site_name);

        $super = $this->createAdmin(superAdmin: true);
        $this->settingsPage($super)->assertSee('name="site_name"', false)->assertSee('id="site-availability"', false);
    }

    // ---------------------------------------------------------------- helpers

    private function settingsPage(Admin $admin): TestResponse
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.setting.index'))->assertOk();
    }

    private function save(Admin $admin, string $state): TestResponse
    {
        return $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => $state, 'confirm' => '1']);
    }

    private function assertFlash(TestResponse $response, string $level, string $message): void
    {
        $response->assertRedirect(route('dashboard.setting.index').'#site-availability');
        $this->assertSame(['level' => $level, 'message' => $message], session(SiteAvailabilityController::FLASH));
    }

    private function storeDirectly(string $state, Admin $by, string $at): void
    {
        Setting::singleton();
        DB::table('settings')->where('singleton_key', 1)->update([
            'site_availability_mode' => $state,
            'site_availability_changed_by' => $by->id,
            'site_availability_changed_at' => $at,
        ]);
    }

    /** @return array<string, mixed> */
    private function settingsRow(): array
    {
        return (array) DB::table('settings')->where('singleton_key', 1)->first();
    }

    private function assertNotReady(Admin $super): void
    {
        $this->settingsPage($super)
            ->assertSee('data-site-availability-migration-pending', false)
            ->assertSee('<fieldset disabled', false)
            ->assertDontSee('data-site-availability-save', false)
            ->assertDontSee('Save site state');
    }

    private function assertRefusedAsPending(Admin $super): void
    {
        $before = Schema::hasColumn('settings', 'site_availability_mode') ? $this->settingsRow() : null;

        $this->assertFlash($this->save($super, 'maintenance'), 'danger', 'The site availability migration has not been applied yet. Nothing was saved.');

        if ($before !== null) {
            $this->assertSame($before, $this->settingsRow(), 'Nothing written.');
        }
    }

    private function migrationRecorded(): bool
    {
        return DB::table('migrations')->where('migration', SiteAvailabilitySettings::MIGRATION)->exists();
    }

    /** The card's HTML only (up to the general settings card). */
    private function card(TestResponse $response): string
    {
        $html = $response->getContent();
        $start = strpos($html, 'id="site-availability"');
        $this->assertNotFalse($start, 'The card is rendered.');
        $end = strpos($html, 'action="'.route('dashboard.setting.update').'"', $start);

        return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
    }

    private function rollBackSiteAvailabilityMigration(): void
    {
        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasColumn('settings', 'site_availability_mode'));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'Site',
            'site_description' => 'Description',
            'email' => 'site@example.com',
            'phone' => '0599000000',
            'address' => 'Address',
            'timezone' => 'UTC',
            'default_locale' => 'en',
            'default_currency' => 'USD',
        ], $overrides);
    }

    private function adminWithAbilities(array $abilities): Admin
    {
        $admin = $this->createAdmin();

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function createAdmin(bool $superAdmin = false): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);
    }
}
