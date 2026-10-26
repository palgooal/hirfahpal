{{--
    SITE-AVAILABILITY Phase 3B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7): the site state card on the
    settings page, included for a Super Admin only ($card from SiteAvailabilitySettings::card()). Static warnings, no
    JavaScript. Texts through t() without auto-creating keys (AdminSiteAvailabilityTranslationSeeder).
--}}
@php
    $saText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $stateLabels = [
        'open' => $saText('Site_Availability_State_Open', 'Open'),
        'coming_soon' => $saText('Site_Availability_State_Coming_Soon', 'Coming soon'),
        'maintenance' => $saText('Site_Availability_State_Maintenance', 'Maintenance'),
    ];
    $stateHints = [
        'open' => $saText('Site_Availability_Hint_Open', 'The store is available to everyone as usual.'),
        'coming_soon' => $saText('Site_Availability_Hint_Coming_Soon', 'Visitors, customers and delivery drivers see the Coming Soon page, while approved vendors can access their dashboards and the admin dashboard remains available.'),
        'maintenance' => $saText('Site_Availability_Hint_Maintenance', 'Visitors, customers, vendors and delivery drivers see the maintenance page (HTTP 503), while the admin dashboard remains accessible to authorized administrators.'),
    ];
    // admin-products-badge modifiers: olive outline, copper outline, solid red.
    $tones = ['open' => 'active', 'coming_soon' => 'low', 'maintenance' => 'suspended'];
    $sources = [
        'environment' => $saText('Site_Availability_Source_Environment', 'Forced by the server environment'),
        'database' => $saText('Site_Availability_Source_Database', 'Set in the dashboard'),
        'default' => $saText('Site_Availability_Source_Default', 'Default (no state saved yet)'),
    ];

    $effective = $card['effective']->value;
    $stored = $card['stored']?->value;
    // SA-FIX-2: after a rejected save the admin's choice stays selected, but only a valid state value is accepted.
    $previous = old('state');
    $selected = (is_string($previous) ? App\Support\SiteAvailability\SiteAvailabilityState::tryFrom($previous)?->value : null) ?? $stored ?? $effective;
    $ready = $card['schema_ready'];
    $flash = session(App\Http\Controllers\Dashboard\SiteAvailabilityController::FLASH);
    // SA-FIX-3: only known levels style the message (anything else is info); errors and warnings are announced
    // assertively (alert), confirmations politely (status).
    $flashLevel = in_array($flash['level'] ?? null, ['success', 'info', 'warning', 'danger'], true) ? $flash['level'] : 'info';
    $flashRole = in_array($flashLevel, ['danger', 'warning'], true) ? 'alert' : 'status';
    // SA-FIX-1: the change time is stored in the app timezone (UTC) and shown in Palestine time, for display only.
    $changedAtLocal = $card['changed_at']?->copy()->setTimezone('Asia/Hebron');
    // SA-FIX-4B (D4), display only: the migration gives every row `open` with no change date, and only
    // SiteAvailabilitySettings::store() writes the state, always with its date. So `open` without a date was never
    // saved by anyone; any other state without a date (a manual database edit) keeps the "saved" label.
    $sourceKey = $card['source'] === 'database' && $card['changed_at'] === null && $stored === 'open' ? 'default' : $card['source'];
@endphp

<div class="col-span-12" id="site-availability">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-1">{{ $saText('Site_Availability_Title', 'Site availability') }}</h4>
            <p class="text-muted mb-0">{{ $saText('Site_Availability_Intro', 'Control whether the storefront is open, coming soon or under maintenance.') }}</p>
        </div>

        <div class="card-body">
            @if (is_array($flash) && isset($flash['message']))
                <div class="alert alert-{{ $flashLevel }}" role="{{ $flashRole }}" data-site-availability-flash>
                    {{ $flash['message'] }}
                </div>
            @endif

            @if ($card['override'])
                <div class="alert alert-warning" data-site-availability-override>
                    {{ str_replace(':value', $card['override']->value, $saText('Site_Availability_Override_Warning', 'The site state is forced by the server settings (HIRFAH_SITE_AVAILABILITY=:value) and takes precedence over the state saved here. Changes are saved but do not apply until the value is set back to auto.')) }}
                </div>
            @endif

            @unless ($ready)
                <div class="alert alert-danger" data-site-availability-migration-pending>
                    {{ $saText('Site_Availability_Migration_Pending', 'The site availability database migration has not been applied yet, so the state cannot be saved. Ask the technical administrator to run the migration.') }}
                </div>
            @endunless

            <dl class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <dt class="text-muted fw-normal mb-1">{{ $saText('Site_Availability_Effective', 'Current state') }}</dt>
                    <dd class="mb-0">
                        {{-- U1: the dashboard's existing badge, styled only inside its .admin-products scope (no rule on the wrapper itself). --}}
                        <span class="admin-products"><span class="admin-products-badge is-{{ $tones[$effective] }}" data-site-availability-effective="{{ $effective }}">{{ $stateLabels[$effective] }}</span></span>
                        <span class="d-block small text-muted mt-1" data-site-availability-source="{{ $card['source'] }}">{{ $sources[$sourceKey] ?? '' }}</span>
                    </dd>
                </div>

                <div class="col-12 col-md-4">
                    <dt class="text-muted fw-normal mb-1">{{ $saText('Site_Availability_Stored', 'Saved state') }}</dt>
                    <dd class="mb-0" data-site-availability-stored="{{ $stored ?? '' }}">{{ $stored ? $stateLabels[$stored] : '—' }}</dd>
                </div>

                <div class="col-12 col-md-4">
                    <dt class="text-muted fw-normal mb-1">{{ $saText('Site_Availability_Last_Change', 'Last change') }}</dt>
                    <dd class="mb-0" data-site-availability-last-change>
                        @if ($changedAtLocal)
                            <bdi dir="ltr" data-site-availability-changed-at>{{ $changedAtLocal->format('Y-m-d H:i') }}</bdi>
                            <span class="small text-muted">({{ $saText('Site_Availability_Palestine_Time', 'Palestine time') }})</span>
                            —
                            <bdi>{{ $card['changed_by'] ?? ($card['changed_by_deleted'] ? $saText('Site_Availability_Deleted_Admin', 'Deleted admin') : '—') }}</bdi>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('dashboard.setting.site-availability.update') }}">
                @csrf
                @method('PATCH')

                <fieldset @disabled(! $ready)>
                    <legend class="h6 mb-3">{{ $saText('Site_Availability_Choose', 'Choose the site state') }}</legend>

                    {{-- U3: each control and its label in one row that follows the page direction (no .form-check, whose
                         Bootstrap float puts the control on the far side in RTL). --}}
                    @foreach (App\Support\SiteAvailability\SiteAvailabilityState::cases() as $option)
                        @php($value = $option->value)
                        <div class="d-flex align-items-start gap-2 mb-3" data-site-availability-option="{{ $value }}">
                            <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="state" id="site-availability-{{ $value }}" value="{{ $value }}" @checked($selected === $value)>
                            <div>
                                <label class="form-check-label fw-semibold" for="site-availability-{{ $value }}">{{ $stateLabels[$value] }}</label>
                                <p class="small text-muted mb-0">{{ $stateHints[$value] }}</p>

                                @if ($value === 'maintenance')
                                    <p class="small text-danger mb-0 mt-1" data-site-availability-warning="maintenance">{{ $saText('Site_Availability_Warning_Maintenance', 'Visitors, customers, vendors and delivery drivers will see that the site is under maintenance (503) and cannot sign in or complete anything until you reopen the site.') }}</p>
                                @elseif ($value === 'coming_soon' && $stored !== 'coming_soon')
                                    {{-- SA-FIX-4B (D2): closing to customers, in the launch warning's dark amber. --}}
                                    <p class="small mb-0 mt-1" style="color: #8a4520" data-site-availability-warning="coming-soon">{{ $saText('Site_Availability_Warning_Coming_Soon', 'Saving Coming Soon mode will block customers from accessing the store, signing in or placing orders until it is reopened. Delivery drivers will also be unable to access their dashboards.') }}</p>
                                @elseif ($value === 'open' && in_array($stored, ['coming_soon', 'maintenance'], true))
                                    {{-- U4: the dashboard's dark amber instead of Bootstrap's low-contrast warning yellow. --}}
                                    <p class="small mb-0 mt-1" style="color: #8a4520" data-site-availability-warning="launch">{{ $saText('Site_Availability_Warning_Launch', 'The store will be open to everyone as soon as you save.') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex align-items-start gap-2 mt-4" data-site-availability-confirm-row>
                        <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="confirm" id="site-availability-confirm" value="1" required>
                        <label class="form-check-label" for="site-availability-confirm">{{ $saText('Site_Availability_Confirm', 'I confirm changing the site state') }}</label>
                    </div>

                    {{-- U2: no Save button while the migration is pending (the notice above explains why). --}}
                    @if ($ready)
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary" data-site-availability-save>{{ $saText('Site_Availability_Save', 'Save site state') }}</button>
                        </div>
                    @endif
                </fieldset>
            </form>
        </div>
    </div>
</div>
