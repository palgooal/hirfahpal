<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\Dashboard\SiteAvailabilityController;
use App\Models\Admin;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Support\SiteAvailability\SiteAvailability;
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
            ->assertSee('2026-10-20 17:05') // 14:05 UTC in Palestine time (UTC+3 before the autumn change).
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
            ->assertSee('2026-10-19 12:00') // 09:00 UTC in Palestine time.
            ->assertSee('Deleted admin');
    }

    // ---------------------------------------------------------------- last change time (SA-FIX-1)

    public function test_the_last_change_is_shown_in_palestine_time_in_summer_and_winter(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        // Summer: UTC+3.
        $this->storeDirectly('coming_soon', $super, '2026-07-15 10:00:00');
        $this->settingsPage($super)
            ->assertSee('<bdi dir="ltr" data-site-availability-changed-at>2026-07-15 13:00</bdi>', false)
            ->assertSee('(Palestine time)')
            ->assertDontSee('2026-07-15 10:00');

        // Winter: UTC+2.
        $this->storeDirectly('maintenance', $super, '2026-01-15 10:00:00');
        $this->settingsPage($super)
            ->assertSee('<bdi dir="ltr" data-site-availability-changed-at>2026-01-15 12:00</bdi>', false)
            ->assertSee('(Palestine time)')
            ->assertDontSee('2026-01-15 10:00');

        // Across midnight: 22:30 UTC in summer is the next day in Palestine.
        $this->storeDirectly('open', $super, '2026-07-15 22:30:00');
        $this->settingsPage($super)
            ->assertSee('<bdi dir="ltr" data-site-availability-changed-at>2026-07-16 01:30</bdi>', false);
    }

    public function test_a_saved_change_keeps_its_utc_time_in_the_database(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->travelTo('2026-07-15 10:00:00');

        $this->actingAs($super, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon', 'confirm' => '1']);

        $this->settingsPage($super)
            ->assertSee('<bdi dir="ltr" data-site-availability-changed-at>2026-07-15 13:00</bdi>', false);
        $this->assertStringStartsWith('2026-07-15 10:00:00', (string) $this->settingsRow()['site_availability_changed_at']);
    }

    public function test_a_never_changed_state_shows_a_dash_without_a_time(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $html = $this->settingsPage($super)
            ->assertDontSee('data-site-availability-changed-at', false)
            ->assertDontSee('(Palestine time)')
            ->getContent();

        $this->assertMatchesRegularExpression('/<dd class="mb-0" data-site-availability-last-change>\s*—\s*<\/dd>/u', $html);
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

    // ---------------------------------------------------------------- the choice after a rejected save (SA-FIX-2)

    public function test_an_unconfirmed_save_keeps_the_chosen_state_selected_without_saving_it(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('open', $super, '2026-10-20 10:00:00');
        $before = $this->settingsRow();

        $response = $this->actingAs($super, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon']);
        $this->assertFlash($response, 'danger', 'The change was not confirmed. Tick the confirmation box to save.');
        // Only the validated state is kept for the next request: never the confirmation, never anything else.
        $response->assertSessionHasInput('state', 'coming_soon');
        $this->assertSame(['state' => 'coming_soon'], session()->getOldInput());

        // The next request (the redirect target) shows the choice, while the saved and effective state stay open.
        $html = $this->settingsPage($super)
            ->assertSee('id="site-availability-coming_soon" value="coming_soon" checked', false)
            ->assertDontSee('id="site-availability-open" value="open" checked', false)
            ->assertSee('data-site-availability-stored="open"', false)
            ->assertSee('data-site-availability-effective="open"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<input [^>]*name="confirm" id="site-availability-confirm" value="1" required>/', $html, 'The confirmation is not ticked again.');
        $this->assertSame($before, $this->settingsRow());
        $this->assertSame('open', app(SiteAvailability::class)->current()->value);

        // The kept choice lasts for that one request only.
        $this->settingsPage($super)->assertSee('id="site-availability-open" value="open" checked', false);
    }

    public function test_an_invalid_state_never_selects_an_unknown_option(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('coming_soon', $super, '2026-10-20 10:00:00');

        foreach (['closed', 'OPEN', '"><script>alert(1)</script>', ''] as $state) {
            $this->actingAs($super, 'admin')
                ->patch(route('dashboard.setting.site-availability.update'), ['state' => $state])
                ->assertSessionMissing('_old_input');

            $this->settingsPage($super)->assertSee('id="site-availability-coming_soon" value="coming_soon" checked', false);
        }

        // An old input that did not come from this controller (a tampered or foreign value) is ignored as well.
        $html = $this->actingAs($super, 'admin')
            ->withSession(['locale' => 'en', '_old_input' => ['state' => '"><script>alert(1)</script>']])
            ->get(route('dashboard.setting.index'))
            ->assertOk()
            ->assertSee('id="site-availability-coming_soon" value="coming_soon" checked', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'name="state" id="site-availability-coming_soon" value="coming_soon" checked'));
        $this->assertSame(1, preg_match_all('/name="state"[^>]*\bchecked\b/', $html));
    }

    public function test_a_successful_save_shows_the_new_state_and_drops_the_earlier_choice(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('open', $super, '2026-10-20 10:00:00');

        $this->actingAs($super, 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon']);
        $this->assertFlash($this->save($super, 'maintenance'), 'success', 'The site state was saved.');
        $this->assertSessionMissingOldInput();

        $this->settingsPage($super)
            ->assertSee('id="site-availability-maintenance" value="maintenance" checked', false)
            ->assertDontSee('id="site-availability-coming_soon" value="coming_soon" checked', false)
            ->assertSee('data-site-availability-stored="maintenance"', false);
    }

    public function test_a_first_visit_keeps_the_saved_state_selected(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('maintenance', $super, '2026-10-20 10:00:00');

        $this->settingsPage($super)->assertSee('id="site-availability-maintenance" value="maintenance" checked', false);
    }

    public function test_a_forbidden_save_keeps_no_choice(): void
    {
        $this->actingAs($this->adminWithAbilities(['settings.view', 'settings.edit']), 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon'])
            ->assertForbidden()
            ->assertSessionMissing('_old_input');
    }

    // ---------------------------------------------------------------- outcome message roles (SA-FIX-3)

    public function test_errors_and_warnings_are_alerts_and_confirmations_are_status_messages(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('open', $super, '2026-10-20 10:00:00');

        // danger: an unconfirmed save.
        $this->actingAs($super, 'admin')->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon']);
        $this->assertFlashElement($this->settingsPage($super)->getContent(), 'danger', 'alert', 'The change was not confirmed. Tick the confirmation box to save.');

        // success: a saved change.
        $this->save($super, 'coming_soon');
        $this->assertFlashElement($this->settingsPage($super)->getContent(), 'success', 'status', 'The site state was saved.');

        // info: the same state again.
        $this->save($super, 'coming_soon');
        $this->assertFlashElement($this->settingsPage($super)->getContent(), 'info', 'status', 'This is already the saved state. Nothing was changed.');

        // warning: saved while the server override decides.
        config(['site-availability.override' => 'maintenance']);
        $this->save($super, 'open');
        $this->assertFlashElement($this->settingsPage($super)->getContent(), 'warning', 'alert', 'The state was saved, but the server override still decides the site state until it is set back to auto.');
    }

    public function test_an_unknown_flash_level_is_a_plain_status_message(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        foreach (['"><script>alert(1)</script>', 'primary', '', null] as $level) {
            $html = $this->actingAs($super, 'admin')
                ->withSession(['locale' => 'en', SiteAvailabilityController::FLASH => ['level' => $level, 'message' => 'A message.']])
                ->get(route('dashboard.setting.index'))
                ->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->getContent();

            $this->assertFlashElement($html, 'info', 'status', 'A message.');
        }
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

    // ---------------------------------------------------------------- wording and warnings (SA-FIX-4B)

    public function test_the_option_hints_use_the_approved_wording(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $card = $this->card($this->settingsPage($super));

        $this->assertStringContainsString('<p class="small text-muted mb-0">Visitors, customers and delivery drivers see the Coming Soon page, while approved vendors can access their dashboards and the admin dashboard remains available.</p>', $card);
        $this->assertStringContainsString('<p class="small text-muted mb-0">Visitors, customers, vendors and delivery drivers see the maintenance page (HTTP 503), while the admin dashboard remains accessible to authorized administrators.</p>', $card);
        // The approved maintenance warning is unchanged.
        $this->assertStringContainsString('<p class="small text-danger mb-0 mt-1" data-site-availability-warning="maintenance">Visitors, customers, vendors and delivery drivers will see that the site is under maintenance (503) and cannot sign in or complete anything until you reopen the site.</p>', $card);
    }

    public function test_the_coming_soon_warning_shows_unless_coming_soon_is_already_saved(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $warning = '<p class="small mb-0 mt-1" style="color: #8a4520" data-site-availability-warning="coming-soon">Saving Coming Soon mode will block customers from accessing the store, signing in or placing orders until it is reopened. Delivery drivers will also be unable to access their dashboards.</p>';

        foreach (['open' => true, 'maintenance' => true, 'coming_soon' => false] as $stored => $shown) {
            $this->storeDirectly($stored, $super, '2026-10-20 08:00:00');
            $card = $this->card($this->settingsPage($super));

            $this->assertSame($shown ? 1 : 0, substr_count($card, $warning), "saved {$stored}");
            if ($shown) {
                // Beneath the Coming Soon option, after its hint.
                $this->assertMatchesRegularExpression('#data-site-availability-option="coming_soon">.*?Coming Soon page.*?data-site-availability-warning="coming-soon".*?</div>\s*</div>#s', $card);
            }
        }
    }

    public function test_the_launch_warning_shows_when_reopening_from_coming_soon_or_maintenance(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        foreach (['coming_soon' => 1, 'maintenance' => 1, 'open' => 0] as $stored => $count) {
            $this->storeDirectly($stored, $super, '2026-10-20 08:00:00');
            $card = $this->card($this->settingsPage($super));

            $this->assertSame($count, substr_count($card, 'data-site-availability-warning="launch"'), "saved {$stored}");
            if ($count) {
                $this->assertMatchesRegularExpression('#data-site-availability-option="open">.*?data-site-availability-warning="launch".*?data-site-availability-option="coming_soon"#s', $card);
            }
        }
    }

    public function test_with_an_override_the_warnings_follow_the_saved_state_and_save_changes_nothing_effective(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $this->storeDirectly('maintenance', $super, '2026-10-20 08:00:00');
        config(['site-availability.override' => 'open']);

        $card = $this->card($this->settingsPage($super));
        // The effective state is open (forced), but saving acts on the saved maintenance state, so both warnings apply.
        $this->assertStringContainsString('data-site-availability-effective="open"', $card);
        $this->assertStringContainsString('data-site-availability-warning="launch"', $card);
        $this->assertStringContainsString('data-site-availability-warning="coming-soon"', $card);

        $this->assertFlash($this->save($super, 'coming_soon'), 'warning', 'The state was saved, but the server override still decides the site state until it is set back to auto.');
        $this->assertSame('coming_soon', $this->settingsRow()['site_availability_mode']);
        $this->assertSame('open', app(SiteAvailability::class)->current()->value);
    }

    // ---------------------------------------------------------------- source label (SA-FIX-4B, D4)

    public function test_a_never_saved_default_state_shows_the_default_source_label(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        $before = $this->settingsRow();
        $this->assertSame('open', $before['site_availability_mode']);
        $this->assertNull($before['site_availability_changed_at']);

        $this->settingsPage($super)
            ->assertSee('<span class="d-block small text-muted mt-1" data-site-availability-source="database">Default (no state saved yet)</span>', false)
            ->assertDontSee('Set in the dashboard')
            ->assertSee('data-site-availability-effective="open"', false)
            ->assertSee('data-site-availability-stored="open"', false);

        $this->assertSame($before, $this->settingsRow(), 'Rendering the card writes nothing.');
        $this->assertSame('database', app(SiteAvailability::class)->source(), 'The resolution source is unchanged.');
    }

    public function test_an_explicitly_saved_state_shows_the_dashboard_source_label(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();

        $this->save($super, 'coming_soon');
        $this->save($super, 'open');

        $this->settingsPage($super)
            ->assertSee('<span class="d-block small text-muted mt-1" data-site-availability-source="database">Set in the dashboard</span>', false)
            ->assertDontSee('Default (no state saved yet)');
    }

    public function test_a_closed_state_without_a_change_date_keeps_the_saved_label(): void
    {
        // A manual database edit (not through the card): never shown as the default.
        $super = $this->createAdmin(superAdmin: true);
        Setting::singleton();
        DB::table('settings')->where('singleton_key', 1)->update(['site_availability_mode' => 'maintenance']);

        $this->settingsPage($super)
            ->assertSee('<span class="d-block small text-muted mt-1" data-site-availability-source="database">Set in the dashboard</span>', false)
            ->assertSee('data-site-availability-effective="maintenance"', false);
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

    /**
     * The rendered outcome message: exactly one, with the level's existing classes, exactly one role (the expected one)
     * and the message text unchanged.
     */
    private function assertFlashElement(string $html, string $level, string $role, string $message): void
    {
        $this->assertSame(1, preg_match_all('/<div [^>]*data-site-availability-flash[^>]*>(.*?)<\/div>/s', $html, $matches), 'One outcome message.');
        $this->assertSame('<div class="alert alert-'.$level.'" role="'.$role.'" data-site-availability-flash>', strstr($matches[0][0], '>', true).'>');
        $this->assertSame(1, substr_count($matches[0][0], 'role='), 'One role only.');
        $this->assertSame($message, trim($matches[1][0]));
    }

    private function assertSessionMissingOldInput(): void
    {
        $this->assertSame([], session()->getOldInput());
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
