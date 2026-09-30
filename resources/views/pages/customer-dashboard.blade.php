@extends('layouts.storefront')

@section('title', 'لوحة التحكم | حرفة')

@section('meta_description', 'لوحة تحكم حسابي في حرفة - ملخص الطلبات والمفضلة وإعدادات الحساب')

@section('content')
    @include('storefront.dashboard.index', ['customer' => $user])
@endsection
