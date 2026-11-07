@extends('layouts.storefront')

@section('title', $product->name.' | '.($siteSetting?->site_name ?: 'حِرفة'))

@section('meta_description', $product->short_description ?: $product->name)

@if ($preview ?? false)
@section('robots', 'noindex, nofollow')
@endif

@section('content')
    @include('storefront.product.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-product.js')
@endpush
