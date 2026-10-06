@extends('layouts.storefront')

@section('title', 'إتمام الطلب | حرفة')

@section('meta_description', 'إتمام الطلب في حرفة - اختيار عنوان التوصيل وطريقة الدفع ومراجعة الطلبات الفرعية حسب كل تاجر قبل التأكيد النهائي')

@section('content')
    @include('storefront.checkout.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-checkout.js')
@endpush
