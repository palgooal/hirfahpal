<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('edit', Setting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'timezone' => ['required', 'timezone'],
            'default_locale' => ['required', 'string', 'max:10'],
            'default_currency' => ['required', 'string', 'size:3'],
            'footer' => ['nullable', 'array'],
            'footer.tagline' => ['nullable', 'string', 'max:255'],
            'footer.description' => ['nullable', 'string', 'max:2000'],
            'footer.instagram_url' => ['nullable', 'url', 'max:255'],
            'footer.facebook_url' => ['nullable', 'url', 'max:255'],
            'footer.x_url' => ['nullable', 'url', 'max:255'],
            'footer.about_title' => ['nullable', 'string', 'max:100'],
            'footer.about_story_label' => ['nullable', 'string', 'max:255'],
            'footer.about_authenticity_label' => ['nullable', 'string', 'max:255'],
            'footer.about_contact_label' => ['nullable', 'string', 'max:255'],
            'footer.links_title' => ['nullable', 'string', 'max:100'],
            'footer.orders_label' => ['nullable', 'string', 'max:255'],
            'footer.cart_label' => ['nullable', 'string', 'max:255'],
            'footer.login_label' => ['nullable', 'string', 'max:255'],
            'footer.account_label' => ['nullable', 'string', 'max:255'],
            'footer.customer_service_title' => ['nullable', 'string', 'max:100'],
            'footer.shipping_label' => ['nullable', 'string', 'max:255'],
            'footer.returns_label' => ['nullable', 'string', 'max:255'],
            'footer.faq_label' => ['nullable', 'string', 'max:255'],
            'footer.copyright' => ['nullable', 'string', 'max:500'],
            'footer.payment_methods_title' => ['nullable', 'string', 'max:255'],
            'footer.card_payment_label' => ['nullable', 'string', 'max:100'],
            'footer.apple_pay_label' => ['nullable', 'string', 'max:100'],
            'footer.cash_on_delivery_label' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_currency')) {
            $this->merge([
                'default_currency' => strtoupper((string) $this->input('default_currency')),
            ]);
        }
    }
}
