{{--
    N-CAT-1 (AD-UI-CAT-01E-3C): the server-side delete confirmation, used when JavaScript is off (and after a
    refused or expired confirmation). Shows the category and a warning; Confirm sends DELETE with CSRF and the
    confirmation issued for this category and this admin (CategoryDeleteConfirmation). A category that cannot
    be deleted (DEC-CAT-Q3) shows why, without a confirm button. Texts through t() without auto-creating keys.
--}}
@php
    $catText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    // AUD-04b (independent abilities): Cancel leads to the list only for admins who may open it; otherwise to
    // the dashboard home, which any signed-in admin may open (routes/web.php, dashboard.home).
    $catAdmin = auth('admin')->user();
    $cancelUrl = ($catAdmin?->isSuperAdmin() || $catAdmin?->hasAbility('categories.view')) ? route('dashboard.categories.index') : route('dashboard.home');

    [$before, $after] =array_pad(explode(':name', $catText('Category_Delete_Confirm_Text', ':name will be deleted. This cannot be undone.'), 2), 2, '');
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $catText('Category_Delete_Confirm_Title', 'Delete this category?') }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-cat-form admin-cat-delete" id="admin-category-delete">
        @if (session('danger'))
            <div class="admin-media-alert is-error" role="alert">
                <i class="ph-duotone ph-info" aria-hidden="true"></i>
                <div class="admin-media-alert-content"><p>{{ session('danger') }}</p></div>
            </div>
        @endif

        <section class="card admin-media-panel admin-cat-delete-card" aria-labelledby="category-delete-name">
            <div class="admin-cat-delete-body">
                <i class="ph-duotone ph-warning admin-cat-delete-icon" aria-hidden="true"></i>
                <div>
                    <h2 class="admin-cat-delete-name" id="category-delete-name"><bdi>{{ $category->name }}</bdi></h2>
                    @if ($blockedMessage)
                        <p class="admin-cat-delete-text">{{ $blockedMessage }}</p>
                    @else
                        <p class="admin-cat-delete-text">{{ $before }}<bdi>{{ $category->name }}</bdi>{{ $after }}</p>
                    @endif
                </div>
            </div>

            <div class="admin-cat-form-actions">
                @if ($confirmation)
                    <form method="POST" action="{{ route('dashboard.categories.destroy', $category) }}" data-category-delete-page>
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="confirmation" value="{{ $confirmation }}">
                        <button type="submit" class="btn btn-danger">
                            <i class="ph-duotone ph-trash" aria-hidden="true"></i>
                            <span>{{ $catText('Category_Delete_Confirm_Action', 'Confirm deletion') }}</span>
                        </button>
                    </form>
                @endif
                <a href="{{ $cancelUrl }}" class="btn btn-light-secondary">{{ $catText('Category_Delete_Cancel', 'Cancel') }}</a>
            </div>
        </section>
    </div>
</x-dashboard-layout>
