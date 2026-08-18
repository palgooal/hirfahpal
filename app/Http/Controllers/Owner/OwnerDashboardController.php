<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function index(): View
    {
        $ownerId = Auth::guard('owner')->id();

        // $businesses = Business::with(['category', 'area'])
        //     ->where('owner_id', $ownerId)
        //     ->latest()
        //     ->limit(5)
        //     ->get();

        // $stats = [
        //     'total' => Business::where('owner_id', $ownerId)->count(),
        //     'pending' => Business::where('owner_id', $ownerId)->where('status', 'pending')->count(),
        //     'active' => Business::where('owner_id', $ownerId)->where('status', 'active')->count(),
        //     'rejected' => Business::where('owner_id', $ownerId)->where('status', 'rejected')->count(),
        // ];

        return view('owner.dashboard.index');
    }
}
