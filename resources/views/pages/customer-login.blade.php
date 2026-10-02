@extends('layouts.storefront')

@section('title', 'تسجيل الدخول | حرفة')

@section('meta_description', 'تسجيل الدخول أو إنشاء حساب في حرفة - سوق الصنعة الفلسطينية الأصيلة')

@section('content')
    @include('storefront.login.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-login.js')
@endpush
