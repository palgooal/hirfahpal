@extends('layouts.storefront')

@section('title', 'تعيين كلمة مرور جديدة | حرفة')

@section('meta_description', 'تعيين كلمة مرور جديدة لحسابك في حرفة - سوق الصنعة الفلسطينية الأصيلة')

@section('content')
    @include('storefront.login.reset-password')
@endsection

@push('scripts')
    @vite('resources/js/storefront-login.js')
@endpush
