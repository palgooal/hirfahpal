@extends('layouts.storefront')

@section('title', 'نتائج البحث | حرفة')

@section('content')
    @include('storefront.browse.index')
@endsection

@push('scripts')
    @vite('resources/js/storefront-browse.js')
@endpush
