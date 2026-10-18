@extends('layouts.storefront')

@section('title', 'استعادة كلمة المرور | حرفة')

@section('meta_description', 'استعادة كلمة مرور حسابك في حرفة - سوق الصنعة الفلسطينية الأصيلة')

@section('content')
    @include('storefront.login.forgot-password')
@endsection

@push('scripts')
    @vite('resources/js/storefront-login.js')
@endpush
