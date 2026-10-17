<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function create(Request $request): View
    {
        return view('storefront.content.contact', ['customer' => $request->user('customer')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::query()->create($data + ['customer_id' => $request->user('customer')?->id]);

        return back()->with('success', 'تم إرسال رسالتك بنجاح. سنتواصل معك في أقرب وقت.');
    }
}
