<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\SiteAvailability\SiteAvailability;
use App\Support\SiteAvailability\SiteAvailabilitySettings;
use App\Support\SiteAvailability\SiteAvailabilityState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SITE-AVAILABILITY Phase 3B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7.2): saving the stored state from
 * the settings card. Super Admin only, checked before anything is read. Without the migration, with an unknown state
 * or without the ticked confirmation nothing is written; the same state is a no-op. Outcomes are flashed for the card
 * (not the validation bag, so the page's general error list stays for the general form). The environment override is
 * never changed here: a saved state applies once HIRFAH_SITE_AVAILABILITY is back to auto.
 */
class SiteAvailabilityController extends Controller
{
    public const FLASH = 'site_availability';

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless((bool) $admin?->isSuperAdmin(), 403);

        if (! SiteAvailabilitySettings::schemaReady()) {
            return $this->back('danger', 'Site_Availability_Migration_Pending_Save', 'The site availability migration has not been applied yet. Nothing was saved.');
        }

        $input = $request->input('state');
        $state = is_string($input) ? SiteAvailabilityState::tryFrom($input) : null;

        if ($state === null) {
            return $this->back('danger', 'Site_Availability_Invalid_State', 'Choose one of the three site states.');
        }

        if (! $request->boolean('confirm')) {
            // SA-FIX-2: the (already valid) choice is kept for the redirect; the confirmation never is.
            return $this->back('danger', 'Site_Availability_Not_Confirmed', 'The change was not confirmed. Tick the confirmation box to save.')
                ->withInput(['state' => $state->value]);
        }

        if (! SiteAvailabilitySettings::store($state, $admin)) {
            return $this->back('info', 'Site_Availability_Unchanged', 'This is already the saved state. Nothing was changed.');
        }

        app(SiteAvailability::class)->forget();

        return app(SiteAvailability::class)->override() !== null
            ? $this->back('warning', 'Site_Availability_Saved_Overridden', 'The state was saved, but the server override still decides the site state until it is set back to auto.')
            : $this->back('success', 'Site_Availability_Saved', 'The site state was saved.');
    }

    private function back(string $level, string $key, string $default): RedirectResponse
    {
        return redirect()
            ->to(route('dashboard.setting.index').'#site-availability')
            ->with(self::FLASH, ['level' => $level, 'message' => $this->text($key, $default)]);
    }

    /** Texts without auto-creating translation rows (AdminSiteAvailabilityTranslationSeeder). */
    private function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
