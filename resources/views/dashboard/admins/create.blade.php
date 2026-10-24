<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.admins.index') }}">{{ t('dashboard.Admins', 'Admins') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_Admin', 'Add Admin') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12 admin-admin-create">
        <div class="card admin-admin-create-card">
            <div class="card-header admin-admin-create-header">
                <div class="admin-admin-create-icon" aria-hidden="true"><i class="ph-duotone ph-user-plus"></i></div>
                <div>
                    <h5 class="mb-1">{{ t('dashboard.Add_Admin', 'Add Admin') }}</h5>
                    <p class="mb-0 text-muted">{{ t('dashboard.Add_Admin_hint', 'Create an administrator account and choose its access permissions.') }}</p>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.admins.store') }}" method="POST" class="admin-admin-form" data-admin-create-form>
                    @csrf
                    @include('dashboard.admins._form', [
                        'buttonLabel' => t('dashboard.Save', 'Save'),
                    ])
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
