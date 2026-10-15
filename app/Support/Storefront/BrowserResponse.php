<?php

namespace App\Support\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrowserResponse
{
    public static function saved(Request $request, mixed $data, int $status = 200, ?string $route = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($data, $status);
        }

        return ($route ? redirect()->route($route) : back())->with('success', 'تم حفظ التغييرات بنجاح.');
    }
}
