<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function index(): View
    {
        return view('owner.dashboard.index');
    }
}
