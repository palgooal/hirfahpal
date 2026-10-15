@extends('layouts.storefront')

@section('title', $siteSetting?->site_name ?: 'حِرفة | سوق الصنعة الفلسطينية الأصيلة')
@section('meta_description', $siteSetting?->site_description ?: 'حِرفة - سوق الصنعة الفلسطينية الأصيلة')

@section('content')
<main data-homepage>
    @include('storefront.home.hero')
    @include('storefront.home.popular-products')
    @include('storefront.home.categories')
    @include('storefront.home.new-arrivals')
    @include('storefront.home.season')
    @include('storefront.home.story')
    @include('storefront.home.artisans')
    @include('storefront.home.trust')
    @include('storefront.home.newsletter')
</main>
@endsection

@push('scripts')
    @include('storefront.home-actions')
@endpush
