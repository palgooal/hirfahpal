<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.admins.index') }}">{{ t('dashboard.Admins', 'Admins') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_Admin', 'Add Admin') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Add_Admin', 'Add Admin') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.admins.store') }}" method="POST">
                    @csrf
                    @include('dashboard.admins._form', [
                        'buttonLabel' => t('dashboard.Save', 'Save'),
                    ])
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
