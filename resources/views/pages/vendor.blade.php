@extends('layouts.storefront')

@section('title', $vendor->profile->store_name.' | '.($siteSetting?->site_name ?: 'حِرفة'))

@section('meta_description', $vendor->profile->short_description ?: $vendor->profile->store_name)

@section('content')
    @include('storefront.vendor.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-vendor.js')
@endpush
