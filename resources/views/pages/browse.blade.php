@extends('layouts.storefront')

@section('title', 'نتائج البحث | حرفة')

@section('meta_description', 'نتائج البحث والتصفية في حرفة - تصفح منتجات الحرفيين والمشاغل الفلسطينية حسب الفئة والتاجر والمدينة والسعر')

@section('content')
    @include('storefront.browse.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-browse.js')
@endpush
