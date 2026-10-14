{{--
    AD-UI-PROD-M4 (DEC-PROD-12): the read-only product details in the admin shell. Five groups, each a section with
    its own heading. resources/js/admin-product-show.js turns them into ARIA tabs; without JavaScript the tab list
    stays hidden and the five groups are shown one after the other. The moderation tab carries the suspension form of
    AD-UI-PROD-M5 (suspend or lift) and the archive form of AD-UI-PROD-M6 (archive or restore), each by its ability.
    Product data is always escaped ({{ }}, never {!! !!}; line breaks kept by CSS); images come only from
    ProductManagementController::safeImageUrl().
    Texts through t() without auto-creating keys (AdminProductTranslationSeeder).
--}}
@php
    $prodAdmin = auth('admin')->user();
    $canEdit = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.edit'));
    $canSuspend = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.suspend'));
    $canUnsuspend = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.unsuspend'));
    $canArchive = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.archive'));
    $canRestore = (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility('products.restore'));

    $prodText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $statusLabel = fn (?string $status): string => isset($statusKeys[$status]) ? $prodText(...$statusKeys[$status]) : (string) $status;
    $actionLabels = [
        'suspended' => ['Products_Action_Suspended', 'Suspended'],
        'unsuspended' => ['Products_Action_Unsuspended', 'Suspension lifted'],
        'archived' => ['Products_Action_Archived', 'Archived'],
        'restored' => ['Products_Action_Restored', 'Restored'],
    ];
    $actionLabel = fn (string $action): string => isset($actionLabels[$action]) ? $prodText(...$actionLabels[$action]) : $action;
    $when = fn ($date): string => $date ? $date->format('Y-m-d H:i') : '—';
    $amount = fn ($value): string => $value === null ? '—' : number_format((float) $value, 2);

    $reserved = (int) $product->reserved_quantity;
    $available = max(0, (int) $product->stock_quantity - $reserved);
    $isLow = $available <= (int) $product->low_stock_threshold;
    $category = $product->category;
    $dimensions = is_array($product->dimensions) ? array_filter($product->dimensions, fn ($value) => $value !== null && $value !== '') : [];

    $tabs = [
        'info' => $prodText('Products_Show_Tab_Info', 'Product information'),
        'images' => $prodText('Products_Show_Tab_Images', 'Images'),
        'stock' => $prodText('Products_Show_Tab_Stock', 'Price and stock'),
        'moderation' => $prodText('Products_Show_Tab_Moderation', 'Moderation'),
        'log' => $prodText('Products_Show_Tab_Log', 'Action log'),
    ];
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products admin-product-show" id="admin-product-show">

        {{-- Content header (5.1): the page title is in the admin header; the product is named here. --}}
        <div class="admin-media-head admin-product-head">
            <div class="admin-product-identity">
                <a href="{{ route('dashboard.products.index') }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $prodText('Products_Show_Back', 'Back to products') }}</span>
                </a>
                <h2 class="admin-product-title"><bdi>{{ $product->name }}</bdi></h2>
                <p class="admin-products-code" dir="ltr">{{ $product->sku ?? $product->slug }}</p>
                <p class="admin-products-badges">
                    <span class="admin-products-badge is-{{ $product->status }}">{{ $statusLabel($product->status) }}</span>
                    @if ($product->isSuspended())
                        <span class="admin-products-badge is-suspended">{{ $prodText('Products_Suspended', 'Suspended') }}</span>
                    @endif
                    @if ($product->isArchived())
                        <span class="admin-products-badge is-archived">{{ $prodText('Products_Archived', 'Archived') }}</span>
                    @endif
                    @if ($isLow)
                        <span class="admin-products-badge is-low">{{ $prodText('Products_Filter_Stock_Low', 'Low stock') }}</span>
                    @endif
                </p>
            </div>
            @if ($canEdit)
                <a href="{{ route('dashboard.products.edit', $product) }}" class="btn btn-light-secondary">
                    <i class="ph-duotone ph-pencil-simple" aria-hidden="true"></i>
                    <span>{{ $prodText('Products_Edit', 'Edit') }}</span>
                </a>
            @endif
        </div>

        {{-- Result messages (success, danger), inline until the admin toast exists. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <div class="admin-product-tabs" data-product-tabs>
            {{-- Revealed by admin-product-show.js; hidden without JavaScript, when every group is shown in turn. --}}
            <div class="admin-product-tablist" role="tablist" aria-label="{{ $prodText('Products_Show_Tabs', 'Product details') }}" data-product-tablist hidden>
                @foreach ($tabs as $key => $label)
                    <button type="button" class="admin-product-tab" role="tab" id="product-tab-{{ $key }}" aria-controls="product-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-product-tab="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>

            {{-- 1. Product information --}}
            <section class="card admin-media-panel admin-product-panel" id="product-panel-info" aria-labelledby="product-heading-info" data-product-panel="info">
                <h2 class="admin-product-panel-title" id="product-heading-info">{{ $tabs['info'] }}</h2>
                <dl class="admin-product-facts">
                    <div><dt>{{ $prodText('Products_Show_Name', 'Name') }}</dt><dd><bdi>{{ $product->name }}</bdi></dd></div>
                    <div><dt>{{ $prodText('Products_Show_Sku', 'SKU') }}</dt><dd><span dir="ltr">{{ $product->sku ?? '—' }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Show_Slug', 'Slug') }}</dt><dd><span dir="ltr">{{ $product->slug }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Column_Status', 'Status') }}</dt><dd>{{ $statusLabel($product->status) }}</dd></div>
                    <div><dt>{{ $prodText('Products_Column_Vendor', 'Vendor') }}</dt><dd><bdi>{{ $product->vendor?->profile?->store_name ?? $product->vendor?->name ?? '—' }}</bdi></dd></div>
                    <div>
                        <dt>{{ $prodText('Products_Column_Category', 'Category') }}</dt>
                        <dd>
                            @if ($category)
                                <bdi>{{ $category->parent ? $category->parent->name.' › '.$category->name : $category->name }}</bdi>
                            @else
                                <span class="admin-products-muted">{{ $prodText('Products_No_Category', 'No category') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>{{ $prodText('Products_Show_Weight', 'Weight') }}</dt><dd><span dir="ltr">{{ $product->weight !== null ? $amount($product->weight) : '—' }}</span></dd></div>
                    <div>
                        <dt>{{ $prodText('Products_Show_Dimensions', 'Dimensions') }}</dt>
                        <dd><span dir="ltr">{{ $dimensions ? implode(' × ', array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $dimensions)) : '—' }}</span></dd>
                    </div>
                    <div><dt>{{ $prodText('Products_Show_Published', 'Published') }}</dt><dd><span dir="ltr">{{ $when($product->published_at) }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Show_Created', 'Created') }}</dt><dd><span dir="ltr">{{ $when($product->created_at) }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Show_Updated', 'Last updated') }}</dt><dd><span dir="ltr">{{ $when($product->updated_at) }}</span></dd></div>
                    <div class="is-wide">
                        <dt>{{ $prodText('Products_Show_Short_Description', 'Short description') }}</dt>
                        <dd class="admin-product-text">{{ filled($product->short_description) ? $product->short_description : '—' }}</dd>
                    </div>
                    <div class="is-wide">
                        <dt>{{ $prodText('Products_Show_Description', 'Description') }}</dt>
                        <dd class="admin-product-text">{{ filled($product->description) ? $product->description : '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- 2. Images --}}
            <section class="card admin-media-panel admin-product-panel" id="product-panel-images" aria-labelledby="product-heading-images" data-product-panel="images">
                <h2 class="admin-product-panel-title" id="product-heading-images">{{ $tabs['images'] }}</h2>
                @if ($images->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-image" aria-hidden="true"></i>
                        <span>{{ $prodText('Products_Show_No_Images', 'This product has no images.') }}</span>
                    </div>
                @else
                    <ul class="admin-product-images">
                        @foreach ($images as $index => $entry)
                            {{-- Block form only: an inline @php(...) before a later @php block would be paired with its @endphp. --}}
                            @php
                                $image = $entry['image'];
                            @endphp
                            <li class="admin-product-image">
                                <div class="admin-product-image-frame">
                                    @if ($entry['url'])
                                        <img src="{{ $entry['url'] }}" alt="{{ filled($image->alt_text) ? $image->alt_text : str_replace(':number', (string) ($index + 1), $prodText('Products_Show_Image_Alt', 'Product image :number')) }}" loading="lazy">
                                    @else
                                        <i class="ph-duotone ph-image" aria-hidden="true"></i>
                                    @endif
                                </div>
                                <div class="admin-product-image-meta">
                                    @if ($image->is_primary)
                                        <span class="admin-products-badge is-active">{{ $prodText('Products_Show_Primary_Image', 'Main image') }}</span>
                                    @endif
                                    @unless ($entry['url'])
                                        <span class="admin-product-image-refused">{{ $prodText('Products_Show_Image_Not_Shown', 'Not shown: not a stored image file') }}</span>
                                        <code class="admin-product-image-path" dir="ltr">{{ $image->path }}</code>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- 3. Price and stock --}}
            <section class="card admin-media-panel admin-product-panel" id="product-panel-stock" aria-labelledby="product-heading-stock" data-product-panel="stock">
                <h2 class="admin-product-panel-title" id="product-heading-stock">{{ $tabs['stock'] }}</h2>
                <dl class="admin-product-facts">
                    <div><dt>{{ $prodText('Products_Column_Price', 'Price') }}</dt><dd><span dir="ltr">{{ $amount($product->price) }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Show_Compare_Price', 'Compare-at price') }}</dt><dd><span dir="ltr">{{ $amount($product->compare_at_price) }}</span></dd></div>
                    <div><dt>{{ $prodText('Products_Stock_On_Hand', 'On hand') }}</dt><dd>{{ (int) $product->stock_quantity }}</dd></div>
                    <div><dt>{{ $prodText('Products_Stock_Reserved', 'Reserved') }}</dt><dd>{{ $reserved }}</dd></div>
                    <div><dt>{{ $prodText('Products_Stock_Available', 'Available') }}</dt><dd>{{ $available }}</dd></div>
                    <div>
                        <dt>{{ $prodText('Products_Show_Low_Threshold', 'Low stock threshold') }}</dt>
                        <dd>
                            {{ (int) $product->low_stock_threshold }}
                            @if ($isLow)
                                <span class="admin-products-badge is-low">{{ $prodText('Products_Filter_Stock_Low', 'Low stock') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- 4. Moderation: the state, and the suspension action of M5 for an admin who may take it. --}}
            <section class="card admin-media-panel admin-product-panel" id="product-panel-moderation" aria-labelledby="product-heading-moderation" data-product-panel="moderation">
                <h2 class="admin-product-panel-title" id="product-heading-moderation">{{ $tabs['moderation'] }}</h2>
                <dl class="admin-product-facts">
                    <div>
                        <dt>{{ $prodText('Products_Filter_Suspension', 'Suspension') }}</dt>
                        <dd>
                            @if ($product->isSuspended())
                                <span class="admin-products-badge is-suspended">{{ $prodText('Products_Suspended', 'Suspended') }}</span>
                                <span class="admin-product-since" dir="ltr">{{ $when($product->suspended_at) }}</span>
                            @else
                                {{ $prodText('Products_Not_Suspended', 'Not suspended') }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ $prodText('Products_Filter_Archive', 'Archive') }}</dt>
                        <dd>
                            @if ($product->isArchived())
                                <span class="admin-products-badge is-archived">{{ $prodText('Products_Archived', 'Archived') }}</span>
                                <span class="admin-product-since" dir="ltr">{{ $when($product->archived_at) }}</span>
                            @else
                                {{ $prodText('Products_Show_Not_Archived', 'Not archived') }}
                            @endif
                        </dd>
                    </div>
                    @if ($product->isSuspended())
                        <div class="is-wide">
                            <dt>{{ $prodText('Products_Show_Suspension_Reason', 'Reason for the suspension') }}</dt>
                            <dd class="admin-product-text">{{ filled($suspensionReason) ? $suspensionReason : $prodText('Products_Show_No_Reason', 'No reason recorded.') }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- AD-UI-PROD-M5: the one action this state allows, for an admin holding its ability. Writing the reason
                     is the confirmation (no dialog); the server checks the ability, the reason and the state again. --}}
                @php
                    $suspended = $product->isSuspended();
                    $canModerate = $suspended ? $canUnsuspend : $canSuspend;
                    $moderation = $suspended
                        ? ['route' => 'dashboard.products.unsuspend', 'title' => ['Products_Unsuspend_Title', 'Lift the suspension'], 'hint' => ['Products_Unsuspend_Hint', 'The product can be sold again if its other conditions allow it. Its status and archive are not changed.'], 'label' => ['Products_Unsuspend_Reason', 'Why is the suspension lifted?'], 'submit' => ['Products_Unsuspend_Submit', 'Lift the suspension'], 'tone' => 'is-lift']
                        : ['route' => 'dashboard.products.suspend', 'title' => ['Products_Suspend_Title', 'Suspend this product'], 'hint' => ['Products_Suspend_Hint', 'A suspended product cannot be sold until the suspension is lifted. Its status and archive are not changed.'], 'label' => ['Products_Suspend_Reason', 'Why is the product suspended?'], 'submit' => ['Products_Suspend_Submit', 'Suspend the product'], 'tone' => 'is-suspend'];
                @endphp
                @if ($canModerate)
                    <form method="POST" action="{{ route($moderation['route'], $product->id) }}" class="admin-product-moderation {{ $moderation['tone'] }}" aria-labelledby="product-moderation-title">
                        @csrf
                        <h3 class="admin-product-moderation-title" id="product-moderation-title">{{ $prodText(...$moderation['title']) }}</h3>
                        <p class="admin-product-moderation-hint" id="product-moderation-hint">{{ $prodText(...$moderation['hint']) }}</p>
                        <label class="form-label" for="product-moderation-reason">{{ $prodText(...$moderation['label']) }}</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror" id="product-moderation-reason" name="reason" rows="3" required minlength="3" maxlength="1000"
                            aria-describedby="product-moderation-hint product-moderation-limits{{ $errors->has('reason') ? ' product-moderation-error' : '' }}"
                            @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
                        <p class="admin-product-moderation-limits" id="product-moderation-limits">{{ $prodText('Products_Moderation_Reason_Limits', 'Between 3 and 1000 characters. It is kept in the action log.') }}</p>
                        @error('reason')
                            <p class="admin-product-moderation-error" id="product-moderation-error" role="alert">{{ $message }}</p>
                        @enderror
                        <div class="admin-product-moderation-actions">
                            <button type="submit" class="btn {{ $suspended ? 'btn-primary' : 'btn-danger' }}">
                                <i class="ph-duotone {{ $suspended ? 'ph-lock-simple-open' : 'ph-lock-simple' }}" aria-hidden="true"></i>
                                <span>{{ $prodText(...$moderation['submit']) }}</span>
                            </button>
                        </div>
                    </form>
                @endif

                {{-- AD-UI-PROD-M6: archive or restore, separate from the suspension form: its own field (archive_reason),
                     ids, error and old input, so the two forms never mix. Restoring always makes the product a draft
                     and keeps any suspension; the notice says so before the admin confirms. --}}
                @php
                    $archived = $product->isArchived();
                    $canArchiveAction = $archived ? $canRestore : $canArchive;
                    $archiving = $archived
                        ? ['route' => 'dashboard.products.restore', 'title' => ['Products_Restore_Title', 'Restore this product'], 'hint' => ['Products_Restore_Hint', 'The product comes back as a draft, whatever its status was, and is not on sale until it is published again. An administrative suspension, if any, stays.'], 'label' => ['Products_Restore_Reason', 'Why is the product restored?'], 'submit' => ['Products_Restore_Submit', 'Restore as a draft'], 'tone' => 'is-restore', 'icon' => 'ph-arrow-counter-clockwise', 'button' => 'btn-primary']
                        : ['route' => 'dashboard.products.archive', 'title' => ['Products_Archive_Title', 'Archive this product'], 'hint' => ['Products_Archive_Hint', 'An archived product cannot be sold and leaves the default list. Nothing is deleted: its orders, reservations, reviews and log are kept. Its status and suspension are not changed.'], 'label' => ['Products_Archive_Reason', 'Why is the product archived?'], 'submit' => ['Products_Archive_Submit', 'Archive the product'], 'tone' => 'is-archive', 'icon' => 'ph-archive', 'button' => 'btn-danger'];
                @endphp
                @if ($canArchiveAction)
                    <form method="POST" action="{{ route($archiving['route'], $product->id) }}" class="admin-product-moderation {{ $archiving['tone'] }}" aria-labelledby="product-archive-title">
                        @csrf
                        <h3 class="admin-product-moderation-title" id="product-archive-title">{{ $prodText(...$archiving['title']) }}</h3>
                        <p class="admin-product-moderation-hint" id="product-archive-hint">{{ $prodText(...$archiving['hint']) }}</p>
                        <label class="form-label" for="product-archive-reason">{{ $prodText(...$archiving['label']) }}</label>
                        <textarea class="form-control @error('archive_reason') is-invalid @enderror" id="product-archive-reason" name="archive_reason" rows="3" required minlength="3" maxlength="1000"
                            aria-describedby="product-archive-hint product-archive-limits{{ $errors->has('archive_reason') ? ' product-archive-error' : '' }}"
                            @error('archive_reason') aria-invalid="true" @enderror>{{ old('archive_reason') }}</textarea>
                        <p class="admin-product-moderation-limits" id="product-archive-limits">{{ $prodText('Products_Moderation_Reason_Limits', 'Between 3 and 1000 characters. It is kept in the action log.') }}</p>
                        @error('archive_reason')
                            <p class="admin-product-moderation-error" id="product-archive-error" role="alert">{{ $message }}</p>
                        @enderror
                        <div class="admin-product-moderation-actions">
                            <button type="submit" class="btn {{ $archiving['button'] }}">
                                <i class="ph-duotone {{ $archiving['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $prodText(...$archiving['submit']) }}</span>
                            </button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- 5. Action log: queried only with products.audit (DEC-PROD-10). --}}
            <section class="card admin-media-panel admin-product-panel" id="product-panel-log" aria-labelledby="product-heading-log" data-product-panel="log">
                <h2 class="admin-product-panel-title" id="product-heading-log">{{ $tabs['log'] }}</h2>
                @if (! $canAudit)
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>
                        <span>{{ $prodText('Products_Show_Log_Forbidden', 'Viewing the action log needs the "View product action log" permission.') }}</span>
                    </div>
                @elseif ($actions->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-clock-counter-clockwise" aria-hidden="true"></i>
                        <span>{{ $prodText('Products_Show_Log_Empty', 'No administrative action on this product yet.') }}</span>
                    </div>
                @else
                    <ol class="admin-product-log">
                        @foreach ($actions as $entry)
                            <li class="admin-product-log-entry" data-action-id="{{ $entry->id }}">
                                <p class="admin-product-log-head">
                                    <span class="admin-product-log-action is-{{ $entry->action }}">{{ $actionLabel($entry->action) }}</span>
                                    <span class="admin-product-log-who"><bdi>{{ $entry->admin_name }}</bdi></span>
                                    <time class="admin-product-log-when" dir="ltr" datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $when($entry->created_at) }}</time>
                                </p>
                                <p class="admin-product-log-reason admin-product-text">{{ $entry->reason }}</p>
                                @if (is_array($entry->changes) && $entry->changes !== [])
                                    <ul class="admin-product-log-changes">
                                        @foreach ($entry->changes as $field => $change)
                                            @if (is_array($change) && count($change) === 2)
                                                <li dir="ltr">
                                                    {{ $field === 'status' ? $prodText('Products_Column_Status', 'Status') : $field }}:
                                                    {{ $field === 'status' ? $statusLabel(is_scalar($change[0]) ? (string) $change[0] : null) : json_encode($change[0]) }}
                                                    →
                                                    {{ $field === 'status' ? $statusLabel(is_scalar($change[1]) ? (string) $change[1] : null) : json_encode($change[1]) }}
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    {{ $actions->links('dashboard.partials.pagination') }}
                @endif
            </section>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/admin-product-show.js')
    @endpush
</x-dashboard-layout>
