@extends('layouts.storefront')

@section('content')
<main>
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
