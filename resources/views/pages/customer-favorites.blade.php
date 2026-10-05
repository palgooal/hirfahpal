@extends('layouts.storefront')

@section('title', 'المفضلة | حرفة')

@section('meta_description', 'مفضلة حسابي في حرفة - القطع المحفوظة من الحرفيين والمشاغل')

@section('content')
    @include('storefront.account.favorites')
@endsection

@push('scripts')
    @vite('resources/js/storefront-favorites.js')
@endpush
