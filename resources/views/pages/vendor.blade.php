@extends('layouts.storefront')

@section('title', 'دار الكرمة للخزف | حِرفة')

@section('meta_description', 'متجر دار الكرمة للخزف في حِرفة - خزف وفخار فلسطيني من الخليل القديمة')

@section('content')
    @include('storefront.vendor.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-vendor.js')
@endpush
