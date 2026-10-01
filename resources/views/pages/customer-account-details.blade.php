@extends('layouts.storefront')

@section('title', 'تفاصيل الحساب | حرفة')

@section('meta_description', 'تفاصيل حسابي في حرفة - المعلومات الشخصية وكلمة المرور')

@section('content')
    @include('storefront.account.details', ['customer' => request()->user('customer')])
@endsection
