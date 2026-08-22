<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.admins.index') }}">{{ t('dashboard.Admins', 'Admins') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Edit_Admin', 'Edit Admin') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Edit_Admin', 'Edit Admin') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.admins.update', $admin) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('dashboard.admins._form', [
                        'buttonLabel' => t('dashboard.Update', 'Update'),
                    ])
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
