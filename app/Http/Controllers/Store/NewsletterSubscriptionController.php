<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        NewsletterSubscription::query()->firstOrCreate($data);

        $message = 'تم تسجيل اشتراكك في النشرة البريدية بنجاح.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->to(route('home').'#newsletter')->with('newsletter_status', $message);
    }
}
