<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Setting;
use App\Support\Storefront\SocialPlatforms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('edit', Setting::class) ?? false;
    }

    public function rules(): array
    {
        // FOOTER-I18N-01C: the footer texts per language, footer[ar|en][field]: only the supported languages and the
        // 20 text fields, each with its own limit. The links are the social manager's (footer_social).
        $footer = ['footer' => ['nullable', 'array:'.implode(',', Setting::FOOTER_LOCALES)]];
        foreach (Setting::FOOTER_LOCALES as $locale) {
            $footer["footer.{$locale}"] = ['nullable', 'array:'.implode(',', array_keys(self::FOOTER_TEXT_LIMITS))];
            foreach (self::FOOTER_TEXT_LIMITS as $field => $max) {
                $footer["footer.{$locale}.{$field}"] = ['nullable', 'string', 'max:'.$max];
            }
        }

        return $footer + [
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
            // FOOTER-SOCIAL-02B: the social links, keyed by platform (only the approved ones). Optional: a request
            // without them leaves the saved list as it is. A platform without an address is not saved.
            'footer_social' => ['sometimes', 'array:'.implode(',', array_keys(SocialPlatforms::PLATFORMS))],
            'footer_social.*' => ['array:url,enabled,position'],
            'footer_social.*.url' => ['nullable', 'string', 'max:255'],
            'footer_social.*.enabled' => ['nullable', 'boolean'],
            'footer_social.*.position' => ['nullable', 'integer', 'between:1,'.count(SocialPlatforms::PLATFORMS)],
        ];
    }

    /**
     * FOOTER-SOCIAL-02B: an address must belong to its platform (SocialPlatforms::isAllowedUrl: http(s), exact host,
     * no lookalike), and the platforms with an address must each have a different position. Errors are set on the
     * field itself (footer_social.<platform>.url / .position) so they show under it.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = $this->input('footer_social');

            if (! is_array($rows) || $validator->errors()->has('footer_social')) {
                return;
            }

            $positions = [];

            foreach ($rows as $platform => $row) {
                $url = is_array($row) && is_string($row['url'] ?? null) ? trim($row['url']) : '';

                if (! isset(SocialPlatforms::PLATFORMS[$platform]) || $url === '') {
                    continue;
                }

                if (! $validator->errors()->has("footer_social.{$platform}.url") && ! SocialPlatforms::isAllowedUrl($platform, $url)) {
                    $validator->errors()->add("footer_social.{$platform}.url", str_replace(':platform', $this->platformName($platform),
                        $this->label('Settings_Social_Url_Invalid', 'Enter a valid :platform address starting with https://.')));
                }

                if (is_numeric($row['position'] ?? null)) {
                    $positions[(int) $row['position']][] = $platform;
                }
            }

            foreach (array_filter($positions, fn (array $platforms) => count($platforms) > 1) as $platforms) {
                foreach ($platforms as $platform) {
                    $validator->errors()->add("footer_social.{$platform}.position",
                        $this->label('Settings_Social_Position_Distinct', 'Choose a different order for each platform.'));
                }
            }
        }];
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

        // FOOTER-I18N-01C: a footer text by its label and language, e.g. "Tagline (English)".
        $languages = [
            'ar' => $this->label('Settings_Language_Ar', 'Arabic'),
            'en' => $this->label('Settings_Language_En', 'English'),
        ];
        foreach ($languages as $locale => $language) {
            foreach (array_keys(self::FOOTER_TEXT_LIMITS) as $field) {
                $key = 'Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $field)));
                $attributes["footer.{$locale}.{$field}"] = $this->label($key, self::FOOTER_LABELS[$field]).' ('.$language.')';
            }
            $attributes["footer.{$locale}"] = $this->label('Settings_Footer_Title', 'Footer settings').' ('.$language.')';
        }

        // FOOTER-SOCIAL-02B: each social field by its platform's name.
        $attributes['footer_social'] = $this->label('Settings_Group_Footer_Social', 'Social links');
        foreach (array_keys(SocialPlatforms::PLATFORMS) as $platform) {
            $name = $this->platformName($platform);
            $attributes["footer_social.{$platform}.url"] = str_replace(':platform', $name, $this->label('Settings_Social_Url', ':platform link'));
            $attributes["footer_social.{$platform}.position"] = str_replace(':platform', $name, $this->label('Settings_Social_Order_Of', ':platform order'));
            $attributes["footer_social.{$platform}.enabled"] = $name;
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

    /** FOOTER-I18N-01C: each footer text field's length limit (the same in both languages). */
    public const FOOTER_TEXT_LIMITS = [
        'tagline' => 255, 'description' => 2000,
        'about_title' => 100, 'about_story_label' => 255, 'about_authenticity_label' => 255, 'about_contact_label' => 255,
        'links_title' => 100, 'orders_label' => 255, 'cart_label' => 255, 'login_label' => 255, 'account_label' => 255,
        'customer_service_title' => 100, 'shipping_label' => 255, 'returns_label' => 255, 'faq_label' => 255,
        'copyright' => 500, 'payment_methods_title' => 255, 'card_payment_label' => 100, 'apple_pay_label' => 100,
        'cash_on_delivery_label' => 100,
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

    private function label(string $key, string $default, string $group = 'dashboard'): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($group.'.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }

    /** A platform's name in the page language (the storefront footer's names). */
    private function platformName(string $platform): string
    {
        [$key, $default] = SocialPlatforms::PLATFORMS[$platform];

        return $this->label('Footer_Social_'.$key, $default, 'storefront');
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
