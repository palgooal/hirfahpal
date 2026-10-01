@extends('layouts.storefront')

@section('title', 'العنوان | حرفة')

@section('meta_description', 'عناوين التوصيل المحفوظة في حسابي على حرفة')

@section('content')
    @include('storefront.account.addresses')
@endsection

@push('scripts')
    @vite('resources/js/storefront-addresses.js')
@endpush
