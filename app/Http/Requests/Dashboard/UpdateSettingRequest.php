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
            // SETTINGS-FIX-01 (SET-01): absolute http(s) addresses only (no data:, file:, ftp: or other schemes).
            'footer.instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'footer.facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'footer.x_url' => ['nullable', 'url:http,https', 'max:255'],
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

    /**
     * SETTINGS-I18N-01: validation messages name each field by its label on the page (dashboard.*: the general labels
     * and dashboard.Settings_Footer_*), never by its technical key (footer.about_title). Read without auto-creating
     * rows, so a failed save never stores an English default as the Arabic value.
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (self::GENERAL_LABELS as $field => [$key, $default]) {
            $attributes[$field] = $this->label($key, $default);
        }

        foreach (self::FOOTER_LABELS as $field => $default) {
            // footer.x_url → dashboard.Settings_Footer_X_Url, as the settings page builds it.
            $key = 'Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $field)));
            $attributes['footer.'.$field] = $this->label($key, $default);
        }

        return $attributes;
    }

    /** field => [dashboard key, English default], as the settings page reads them. */
    public const GENERAL_LABELS = [
        'site_name' => ['Site_Name', 'Site Name'],
        'site_description' => ['Site_Description', 'Site Description'],
        'logo' => ['Logo', 'Logo'],
        'favicon' => ['Favicon', 'Favicon'],
        'email' => ['Email', 'Email'],
        'phone' => ['Phone', 'Phone'],
        'address' => ['Address', 'Address'],
        'timezone' => ['Timezone', 'Timezone'],
        'default_locale' => ['Default_Locale', 'Default Locale'],
        'default_currency' => ['Default_Currency', 'Default Currency'],
    ];

    /** footer field => English default of its label, as the settings page reads it. */
    public const FOOTER_LABELS = [
        'tagline' => 'Tagline',
        'description' => 'Footer description',
        'instagram_url' => 'Instagram link',
        'facebook_url' => 'Facebook link',
        'x_url' => 'X link',
        'about_title' => 'About section title',
        'about_story_label' => 'Story and vision link text',
        'about_authenticity_label' => 'Authenticity and quality link text',
        'about_contact_label' => 'Contact link text',
        'links_title' => 'Useful links title',
        'orders_label' => 'Orders link text',
        'cart_label' => 'Cart link text',
        'login_label' => 'Sign-in link text',
        'account_label' => 'Account link text',
        'customer_service_title' => 'Customer service title',
        'shipping_label' => 'Shipping link text',
        'returns_label' => 'Exchanges and returns link text',
        'faq_label' => 'FAQ link text',
        'copyright' => 'Copyright text',
        'payment_methods_title' => 'Payment methods title',
        'card_payment_label' => 'Credit card label',
        'apple_pay_label' => 'Apple Pay label',
        'cash_on_delivery_label' => 'Cash on delivery label',
    ];

    private function label(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
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
