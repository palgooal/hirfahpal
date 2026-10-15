@extends('layouts.storefront')

@section('title', $product->name.' | '.($siteSetting?->site_name ?: 'حِرفة'))

@section('meta_description', $product->short_description ?: $product->name)

@section('content')
    @include('storefront.product.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-product.js')
@endpush
