@extends('layouts.storefront')

@section('title', 'إبريق فخار مقدسي مزخرف | حِرفة')

@section('meta_description', 'إبريق فخار مقدسي مزخرف يدوياً من حِرفة')

@section('content')
    @include('storefront.product.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-product.js')
@endpush
