@extends('layouts.storefront')

@section('title', 'سلة المشتريات | حرفة')

@section('meta_description', 'سلة المشتريات في حرفة - مراجعة المنتجات مجمعة حسب التاجر قبل متابعة الدفع')

@section('content')
    @include('storefront.cart.index')
@endsection
