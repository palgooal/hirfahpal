@extends('layouts.storefront')

@section('title', 'كل المتاجر | '.($siteSetting?->site_name ?: 'حِرفة'))

@section('meta_description', 'كل المتاجر في حِرفة - دليل الحرفيين والمشاغل الفلسطينية المسجلة')

@section('content')
    @include('storefront.vendors.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-vendors.js')
@endpush
