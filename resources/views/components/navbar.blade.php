@php
  $webAuthenticated = auth('web')->check();
  $ownerAuthenticated = auth('owner')->check();
  $adminAuthenticated = auth('admin')->check();
  $authenticated = $webAuthenticated || $ownerAuthenticated || $adminAuthenticated;
@endphp

@props(['active' => ''])


