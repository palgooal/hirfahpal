<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StorefrontContentPage;
use Illuminate\View\View;

class StorefrontContentPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = StorefrontContentPage::query()->published()->where('slug', $slug)->firstOrFail();

        return view('storefront.content.show', compact('page'));
    }
}
