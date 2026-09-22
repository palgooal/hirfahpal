@php
  $webAuthenticated = auth('web')->check();
  $customerAuthenticated = auth('customer')->check();
  $vendorAuthenticated = auth('vendor')->check();
  $deliveryDriverAuthenticated = auth('delivery_driver')->check();
  $adminAuthenticated = auth('admin')->check();
  $authenticated = $webAuthenticated || $customerAuthenticated || $vendorAuthenticated || $deliveryDriverAuthenticated || $adminAuthenticated;
@endphp

@props(['active' => ''])

