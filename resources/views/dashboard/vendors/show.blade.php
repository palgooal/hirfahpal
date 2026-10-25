{{--
    AD-UI-VENDORS-01: the vendor details page in the admin shell (AD-UI-01), on the pieces of the customer and product
    details pages (admin-product-show: head, panels, facts, action forms, log list) and the products table, without
    tabs, new CSS or JavaScript. Data from VendorManagementController::show, unchanged: the vendor with its profile
    (city, governorate), the last 10 products, the last 10 vendor orders and, only for admins with vendors.view and
    reviews.view (or super admin), the last 10 store reviews ($recentReviews, null otherwise; FR-ADM-015).
    The approve, reject and commission forms are shown only to admins with vendors.edit (or super admin) and post to
    the same routes as before; both decisions stay available whatever the current state (no transition rules).
    Texts through t() without auto-creating keys (AdminVendorTranslationSeeder); the reused approved keys are
    Products_Status_* (AdminProductTranslationSeeder) and the commission and review keys of AdminVendorTranslationSeeder.
--}}
@php
    $vendText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $vendAdmin = auth('admin')->user();
    $canDecide = (bool) ($vendAdmin?->isSuperAdmin() || $vendAdmin?->hasAbility('vendors.edit'));
    $profile = $vendor->profile;

    // Approval (vendor_profiles.approval_status; a vendor without a profile is shown as pending, as before) and account
    // status (vendors.status, the customer tones): key, default label and badge tone of the shared badge styles.
    $approval = $profile?->approval_status ?? 'pending';
    $approvalKeys = [
        'approved' => ['Vendors_Approval_Approved', 'Approved', 'is-active'],
        'pending' => ['Vendors_Approval_Pending', 'Pending review', 'is-low'],
        'rejected' => ['Vendors_Approval_Rejected', 'Rejected', 'is-suspended'],
    ];
    $accountKeys = [
        'active' => ['Vendors_Account_Active', 'Active', 'is-active'],
        'pending' => ['Vendors_Account_Pending', 'Pending activation', 'is-low'],
        'blocked' => ['Vendors_Account_Blocked', 'Blocked', 'is-suspended'],
    ];
    $label = fn (array $keys, ?string $value): string => isset($keys[$value]) ? $vendText($keys[$value][0], $keys[$value][1]) : (string) $value;

    // The values of products.status, vendor_orders.status and reviews.status.
    $productStatusKeys = [
        'draft' => ['Products_Status_Draft', 'Draft'],
        'active' => ['Products_Status_Active', 'Active'],
        'inactive' => ['Products_Status_Inactive', 'Inactive'],
        'out_of_stock' => ['Products_Status_Out_Of_Stock', 'Out of stock'],
    ];
    $orderStatusKeys = [
        'pending' => ['VendorOrders_Status_Pending', 'Pending'],
        'accepted' => ['VendorOrders_Status_Accepted', 'Accepted'],
        'preparing' => ['VendorOrders_Status_Preparing', 'Preparing'],
        'ready_for_delivery' => ['VendorOrders_Status_Ready_For_Delivery', 'Ready for delivery'],
        'assigned' => ['VendorOrders_Status_Assigned', 'Assigned'],
        'out_for_delivery' => ['VendorOrders_Status_Out_For_Delivery', 'Out for delivery'],
        'delivered' => ['VendorOrders_Status_Delivered', 'Delivered'],
        'completed' => ['VendorOrders_Status_Completed', 'Completed'],
        'rejected' => ['VendorOrders_Status_Rejected', 'Rejected'],
        'cancelled' => ['VendorOrders_Status_Cancelled', 'Cancelled'],
    ];
    $reviewStatusKeys = [
        'pending' => ['Vendors_Review_Status_Pending', 'Pending', 'is-low'],
        'published' => ['Vendors_Review_Status_Published', 'Published', 'is-active'],
        'hidden' => ['Vendors_Review_Status_Hidden', 'Hidden', 'is-inactive'],
    ];

    $amount = fn ($value): string => number_format((float) $value, 2);
    $when = fn ($date): string => $date ? $date->format('Y-m-d H:i') : '—';
    $storeName = $profile?->store_name ?? $vendor->name;
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products admin-product-show admin-vendor-show" id="admin-vendor-show">

        {{-- Content header (5.1): the page title is in the admin header; the store is named here. --}}
        <div class="admin-media-head admin-product-head">
            <div class="admin-product-identity">
                <a href="{{ route('dashboard.vendors.index') }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $vendText('Vendors_Show_Back', 'Back to vendors') }}</span>
                </a>
                <h2 class="admin-product-title"><bdi>{{ $storeName }}</bdi></h2>
                @if (is_string($vendor->avatar) && str_starts_with($vendor->avatar, 'vendors/avatars/') && \Illuminate\Support\Facades\Storage::disk('public')->exists($vendor->avatar))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($vendor->avatar) }}" alt="" width="64" height="64" class="rounded-circle object-fit-cover">
                @endif
                <p class="admin-products-badges">
                    <span class="admin-products-badge {{ $approvalKeys[$approval][2] ?? '' }}" data-approval-status="{{ $approval }}">
                        <span class="visually-hidden">{{ $vendText('Vendors_Approval_Status', 'Approval status') }}:</span>
                        {{ $label($approvalKeys, $approval) }}
                    </span>
                    <span class="admin-products-badge {{ $accountKeys[$vendor->status][2] ?? '' }}" data-account-status="{{ $vendor->status }}">
                        <span class="visually-hidden">{{ $vendText('Vendors_Account_Status', 'Account status') }}:</span>
                        {{ $label($accountKeys, $vendor->status) }}
                    </span>
                </p>
            </div>
        </div>

        {{-- Result messages (success, danger), inline as on the other admin pages. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        {{-- Approved layout (AD-UI-VENDORS-01): pairs of panels side by side from xl, one column below, in the same order
             (RTL: the first panel of a pair is on the right). Only existing grid utilities. --}}
        {{-- Row 1: store and owner + location --}}
        <div class="grid grid-cols-12 gap-4" data-vendor-row="store">
            {{-- 1. Store and owner --}}
            <section class="card admin-media-panel admin-product-panel col-span-12 xl:col-span-6 min-w-0" aria-labelledby="vendor-heading-store">
                <h2 class="admin-product-panel-title" id="vendor-heading-store">{{ $vendText('Vendors_Show_Store', 'Store and owner') }}</h2>
                <dl class="admin-product-facts">
                    <div><dt>{{ $vendText('Vendors_Show_Store_Name', 'Store name') }}</dt><dd><bdi>{{ $storeName }}</bdi></dd></div>
                    <div><dt>{{ $vendText('Vendors_Show_Owner', 'Owner') }}</dt><dd><bdi>{{ $vendor->name }}</bdi></dd></div>
                    <div><dt>{{ $vendText('Vendors_Show_Email', 'Email') }}</dt><dd>@if (filled($vendor->email))<span dir="ltr">{{ $vendor->email }}</span>@else — @endif</dd></div>
                    <div><dt>{{ $vendText('Vendors_Show_Phone', 'Phone') }}</dt><dd>@if (filled($vendor->phone))<span dir="ltr">{{ $vendor->phone }}</span>@else — @endif</dd></div>
                    <div><dt>{{ $vendText('Vendors_Approval_Status', 'Approval status') }}</dt><dd>{{ $label($approvalKeys, $approval) }}</dd></div>
                    <div><dt>{{ $vendText('Vendors_Account_Status', 'Account status') }}</dt><dd>{{ $label($accountKeys, $vendor->status) }}</dd></div>
                    <div class="is-wide">
                        <dt>{{ $vendText('Vendors_Show_Short_Description', 'Short description') }}</dt>
                        <dd>@if (filled($profile?->short_description))<span class="admin-product-text" dir="auto">{{ $profile->short_description }}</span>@else {{ $vendText('Vendors_Show_No_Description', 'No description available.') }} @endif</dd>
                    </div>
                    @if ($profile?->description)
                        <div class="is-wide">
                            <dt>{{ $vendText('Vendors_Show_Description', 'Description') }}</dt>
                            <dd><span class="admin-product-text" dir="auto">{{ $profile->description }}</span></dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- 2. Location --}}
            <section class="card admin-media-panel admin-product-panel col-span-12 xl:col-span-6 min-w-0" aria-labelledby="vendor-heading-location">
                <h2 class="admin-product-panel-title" id="vendor-heading-location">{{ $vendText('Vendors_Show_Location', 'Location') }}</h2>
                <dl class="admin-product-facts">
                    <div><dt>{{ $vendText('Vendors_Show_Governorate', 'Governorate') }}</dt><dd>@if ($profile?->governorate)<bdi>{{ $profile->governorate->name }}</bdi>@else — @endif</dd></div>
                    <div><dt>{{ $vendText('Vendors_Show_City', 'City') }}</dt><dd>@if ($profile?->city)<bdi>{{ $profile->city->name }}</bdi>@else — @endif</dd></div>
                    <div class="is-wide"><dt>{{ $vendText('Vendors_Show_Address', 'Address') }}</dt><dd>@if (filled($profile?->address_line))<bdi>{{ $profile->address_line }}</bdi>@else — @endif</dd></div>
                </dl>
            </section>
        </div>

        {{-- Row 2: admin decision + commission rate (the decision takes the full row when there is no store profile).
             align-items-start (Bootstrap, already loaded): each panel keeps its own height, both aligned at the top. --}}
        <div class="grid grid-cols-12 gap-4 align-items-start" data-vendor-row="decision">
            {{-- 3. Admin decision: both forms stay available as before (no transition rules, no extra confirmation). --}}
            <section class="card admin-media-panel admin-product-panel {{ $profile ? 'col-span-12 xl:col-span-6' : 'col-span-12' }} min-w-0" aria-labelledby="vendor-heading-decision">
                <h2 class="admin-product-panel-title" id="vendor-heading-decision">{{ $vendText('Vendors_Decision_Title', 'Admin decision') }}</h2>
                @if ($canDecide)
                    <form method="POST" action="{{ route('dashboard.vendors.approve', $vendor) }}" class="admin-product-moderation is-lift" aria-labelledby="vendor-approve-title">
                        @csrf
                        @method('PATCH')
                        <h3 class="admin-product-moderation-title" id="vendor-approve-title">{{ $vendText('Vendors_Approve_Title', 'Approve the store') }}</h3>
                        <p class="admin-product-moderation-hint">{{ $vendText('Vendors_Approve_Hint', 'An approved store can sell while its account is active. The account status is not changed.') }}</p>
                        <div class="admin-product-moderation-actions">
                            <button type="submit" class="btn btn-primary" @disabled($approval === 'approved') aria-describedby="vendor-approve-title">
                                <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                                <span>{{ $approval === 'approved' ? $vendText('Vendors_Approval_Approved', 'Approved') : $vendText('Vendors_Approve_Submit', 'Approve vendor') }}</span>
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('dashboard.vendors.reject', $vendor) }}" class="admin-product-moderation is-suspend" aria-labelledby="vendor-reject-title">
                        @csrf
                        @method('PATCH')
                        <h3 class="admin-product-moderation-title" id="vendor-reject-title">{{ $vendText('Vendors_Reject_Title', 'Reject the store') }}</h3>
                        <p class="admin-product-moderation-hint" id="vendor-reject-hint">{{ $vendText('Vendors_Reject_Hint', 'The vendor sees the reason. The account status is not changed.') }}</p>
                        <label class="form-label" for="vendor-rejection-reason">{{ $vendText('Vendors_Rejection_Reason', 'Rejection reason') }}</label>
                        <textarea class="form-control @error('rejection_reason') is-invalid @enderror" id="vendor-rejection-reason" name="rejection_reason" rows="4" required maxlength="1000" dir="auto"
                            aria-describedby="vendor-reject-hint{{ $errors->has('rejection_reason') ? ' vendor-rejection-reason-error' : '' }}"
                            @error('rejection_reason') aria-invalid="true" @enderror>{{ old('rejection_reason', $profile?->rejection_reason) }}</textarea>
                        @error('rejection_reason')
                            <p class="admin-product-moderation-error" id="vendor-rejection-reason-error" role="alert">{{ $message }}</p>
                        @enderror
                        <div class="admin-product-moderation-actions">
                            <button type="submit" class="btn btn-danger">
                                <i class="ph-duotone ph-x-circle" aria-hidden="true"></i>
                                <span>{{ $vendText('Vendors_Reject_Submit', 'Reject vendor') }}</span>
                            </button>
                        </div>
                    </form>
                @else
                    <p class="admin-products-muted">{{ $vendText('Vendors_No_Decision_Permission', 'You do not have permission to make this decision.') }}</p>
                @endif
            </section>

            {{-- 4. Commission rate (FR-ADM-009), only for a vendor with a store profile. --}}
            @if ($profile)
                <section class="card admin-media-panel admin-product-panel col-span-12 xl:col-span-6 min-w-0" aria-labelledby="vendor-heading-commission">
                    <h2 class="admin-product-panel-title" id="vendor-heading-commission">{{ $vendText('Vendors_Commission_Title', 'Commission rate') }}</h2>
                    <dl class="admin-product-facts">
                        <div>
                            <dt>{{ $vendText('Current_commission_rate', 'Current rate') }}</dt>
                            <dd data-commission-rate>@if ($profile->commission_rate !== null)<span dir="ltr">{{ number_format((float) $profile->commission_rate, 2) }}%</span>@else {{ $vendText('Not_set', 'Not set') }} @endif</dd>
                        </div>
                    </dl>
                    @if ($canDecide)
                        <form method="POST" action="{{ route('dashboard.vendors.commission-rate', $vendor) }}" class="admin-product-moderation" aria-labelledby="vendor-commission-title">
                            @csrf
                            @method('PATCH')
                            <h3 class="admin-product-moderation-title" id="vendor-commission-title">{{ $vendText('Update_commission_rate', 'Update Commission Rate') }}</h3>
                            <p class="admin-product-moderation-hint" id="vendor-commission-hint">{{ $vendText('Vendors_Commission_Hint', 'Between 0 and 100, with up to two decimal places. Leave it empty to clear the rate.') }}</p>
                            <label class="form-label" for="commission_rate">{{ $vendText('Vendors_Commission_Title', 'Commission rate') }} (%)</label>
                            <input type="number" class="form-control @error('commission_rate') is-invalid @enderror" id="commission_rate" name="commission_rate" value="{{ old('commission_rate', $profile->commission_rate) }}" min="0" max="100" step="0.01" dir="ltr"
                                aria-describedby="vendor-commission-hint{{ $errors->has('commission_rate') ? ' vendor-commission-rate-error' : '' }}"
                                @error('commission_rate') aria-invalid="true" @enderror>
                            @error('commission_rate')
                                <p class="admin-product-moderation-error" id="vendor-commission-rate-error" role="alert">{{ $message }}</p>
                            @enderror
                            <div class="admin-product-moderation-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                                    <span>{{ $vendText('Update_commission_rate', 'Update Commission Rate') }}</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        {{-- Row 3: recent products + recent vendor orders --}}
        <div class="grid grid-cols-12 gap-4" data-vendor-row="activity">
            {{-- 5. Recent products (the last 10 the controller loads) --}}
            <section class="card admin-media-panel admin-product-panel col-span-12 xl:col-span-6 min-w-0" aria-labelledby="vendor-heading-products">
                <h2 class="admin-product-panel-title" id="vendor-heading-products">{{ $vendText('Vendors_Recent_Products', 'Recent products') }}</h2>
                @if ($vendor->products->isEmpty())
                    <p class="admin-products-muted">{{ $vendText('Vendors_No_Products', 'This vendor has no products.') }}</p>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $vendText('Vendors_Recent_Products', 'Recent products') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $vendText('Vendors_Column_Product', 'Product') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Column_Status', 'Status') }}</th>
                                    <th scope="col" class="is-number">{{ $vendText('Vendors_Column_Price', 'Price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vendor->products as $product)
                                    <tr data-product-id="{{ $product->id }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name"><bdi>{{ $product->name }}</bdi></p>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Column_Status', 'Status') }}</span>
                                            {{ $label($productStatusKeys, $product->status) }}
                                        </td>
                                        <td class="is-number">
                                            <span class="admin-products-label">{{ $vendText('Vendors_Column_Price', 'Price') }}</span>
                                            <span dir="ltr">{{ $amount($product->price) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- 6. Recent vendor orders (the last 10 the controller loads) --}}
            <section class="card admin-media-panel admin-product-panel col-span-12 xl:col-span-6 min-w-0" aria-labelledby="vendor-heading-orders">
                <h2 class="admin-product-panel-title" id="vendor-heading-orders">{{ $vendText('Vendors_Recent_Orders', 'Recent vendor orders') }}</h2>
                @if ($vendor->vendorOrders->isEmpty())
                    <p class="admin-products-muted">{{ $vendText('Vendors_No_Orders', 'This vendor has no orders.') }}</p>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $vendText('Vendors_Recent_Orders', 'Recent vendor orders') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $vendText('Vendors_Column_Number', 'Order number') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Column_Status', 'Status') }}</th>
                                    <th scope="col" class="is-number">{{ $vendText('Vendors_Column_Total', 'Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vendor->vendorOrders as $vendorOrder)
                                    <tr data-vendor-order-id="{{ $vendorOrder->id }}">
                                        <td class="admin-products-cell-product">
                                            <span class="admin-products-label">{{ $vendText('Vendors_Column_Number', 'Order number') }}</span>
                                            <a href="{{ route('dashboard.vendor-orders.show', $vendorOrder) }}" class="admin-products-name-link" dir="ltr">{{ $vendorOrder->number }}</a>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Column_Status', 'Status') }}</span>
                                            {{ $label($orderStatusKeys, $vendorOrder->status) }}
                                        </td>
                                        <td class="is-number">
                                            <span class="admin-products-label">{{ $vendText('Vendors_Column_Total', 'Total') }}</span>
                                            <span dir="ltr">{{ $amount($vendorOrder->total) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- 7. Recent store reviews (full width) (FR-ADM-015): read only, loaded by the controller only for admins with
             vendors.view and reviews.view (or super admin). --}}
        @if ($recentReviews !== null)
            <section class="card admin-media-panel admin-product-panel" aria-labelledby="vendor-heading-reviews" data-vendor-reviews>
                <h2 class="admin-product-panel-title" id="vendor-heading-reviews">{{ $vendText('Recent_Reviews', 'Recent Reviews') }}</h2>
                @if ($recentReviews->isEmpty())
                    <p class="admin-products-muted">{{ $vendText('No_reviews_found', 'No reviews found') }}</p>
                @else
                    <ol class="admin-product-log">
                        @foreach ($recentReviews as $review)
                            <li class="admin-product-log-entry" data-review-id="{{ $review->id }}">
                                <div class="admin-product-log-head">
                                    <span class="admin-product-log-action">{{ str_replace(':rating', (string) (int) $review->rating, $vendText('Vendors_Review_Rating', 'Rating: :rating of 5')) }}</span>
                                    <span class="admin-products-badge {{ $reviewStatusKeys[$review->status][2] ?? '' }}" data-review-status="{{ $review->status }}">{{ $label($reviewStatusKeys, $review->status) }}</span>
                                    <span class="admin-product-log-who"><bdi>{{ $review->customer?->name ?? '—' }}</bdi></span>
                                    <span class="admin-product-log-when" dir="ltr">{{ $when($review->created_at) }}</span>
                                </div>
                                @if (filled($review->comment))
                                    <p class="admin-product-log-reason admin-product-text" dir="auto">{{ $review->comment }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
    </div>
</x-dashboard-layout>
