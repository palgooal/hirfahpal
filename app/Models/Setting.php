<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'site_description',
        'logo',
        'favicon',
        'email',
        'phone',
        'address',
        'timezone',
        'default_locale',
        'default_currency',
        'home_content',
        'footer_content',
    ];

    protected $hidden = ['singleton_key'];

    public static function current(): ?self
    {
        return Schema::hasTable('settings') ? static::query()->first() : null;
    }

    protected function casts(): array
    {
        // site_availability_* (SITE-AVAILABILITY) are deliberately not fillable: only SiteAvailabilitySettings::store()
        // writes them.
        return [
            'home_content' => 'array',
            'footer_content' => 'array',
            'site_availability_changed_at' => 'datetime',
        ];
    }

    public static function defaults(): array
    {
        return [
            'timezone' => config('app.timezone', 'UTC'),
            'default_locale' => config('app.locale', 'en'),
            // SETTINGS-FIX-01 (SET-03): ILS, the platform currency (the storefront's fallback too). Only a new row gets
            // it: a saved currency is never replaced.
            'default_currency' => 'ILS',
        ];
    }

    /**
     * SETTINGS-FIX-01 (SET-03): the settings row for reading, or, while there is none, an unsaved one holding the
     * defaults. Never writes: the row is created only by an authorized save (singleton()).
     */
    public static function forDisplay(): self
    {
        return static::query()->where('singleton_key', 1)->first() ?? new static(static::defaults());
    }

    /** FOOTER-I18N-01B: the languages the footer texts exist in; any other language reads the English texts. */
    public const FOOTER_LOCALES = ['ar', 'en'];

    /** FOOTER-I18N-01B: the site name when none is saved, per language (code, so reading never writes a row). */
    public const SITE_NAME_FALLBACKS = ['ar' => 'حِرفة', 'en' => 'Hirfah'];

    /**
     * The text and external links rendered in the storefront footer.
     * Keeping these defaults here lets existing installations gain editable
     * footer content without changing what customers see before the first save.
     *
     * FOOTER-I18N-01B: per language (the approved English copy of FOOTER-I18N-01A-COPY); Arabic when none is given, as
     * before; any language other than Arabic or English gets the English texts. Same keys, same order in both.
     *
     * @return array<string, string>
     */
    public static function footerDefaults(string $locale = 'ar'): array
    {
        if ($locale !== 'ar') {
            return [
                'tagline' => 'Authentic Palestinian crafts',
                'description' => 'A digital marketplace that celebrates Palestinian heritage and connects independent artisans and women\'s cooperatives with customers around the world through authentic, quality craftsmanship.',
                'instagram_url' => '',
                'facebook_url' => '',
                'x_url' => '',
                'about_title' => 'About Hirfah',
                'about_story_label' => 'Our story and vision',
                'about_authenticity_label' => 'Authenticity and quality charter',
                'about_contact_label' => 'Contact us',
                'links_title' => 'Useful links',
                'orders_label' => 'My orders',
                'cart_label' => 'Shopping cart',
                'login_label' => 'Sign in',
                'account_label' => 'My account',
                'customer_service_title' => 'Customer service',
                'shipping_label' => 'Shipping and delivery',
                'returns_label' => 'Returns and exchanges',
                'faq_label' => 'Frequently asked questions',
                'copyright' => 'All rights reserved.',
                'payment_methods_title' => 'Secure payment methods:',
                'card_payment_label' => 'Credit card',
                'apple_pay_label' => 'Apple Pay',
                'cash_on_delivery_label' => 'Cash on delivery',
            ];
        }

        return [
            'tagline' => 'سوق الصنعة الفلسطينية الأصيلة',
            'description' => 'سوق رقمي مباشر يوثق التراث ويربط الحرفيين المستقلين والتعاونيات النسوية بالمستهلك الواعي حول العالم بجودة وأصالة استثنائية.',
            'instagram_url' => '',
            'facebook_url' => '',
            'x_url' => '',
            'about_title' => 'عن حِرفة',
            'about_story_label' => 'قصة المنصة ورؤيتها',
            'about_authenticity_label' => 'ميثاق الأصالة والجودة',
            'about_contact_label' => 'تواصل واستفسارات',
            'links_title' => 'روابط مفيدة',
            'orders_label' => 'طلباتي',
            'cart_label' => 'سلة المشتريات',
            'login_label' => 'تسجيل الدخول',
            'account_label' => 'حسابي',
            'customer_service_title' => 'خدمة العملاء',
            'shipping_label' => 'الشحن والتوصيل المنظم',
            'returns_label' => 'سياسة الاستبدال والإرجاع',
            'faq_label' => 'الأسئلة الأكثر شيوعاً',
            'copyright' => 'جميع الحقوق محفوظة لرواد الحِرف الأصيلة في فلسطين.',
            'payment_methods_title' => 'طرق الدفع الآمنة:',
            'card_payment_label' => 'بطاقة ائتمان',
            'apple_pay_label' => 'Apple Pay',
            'cash_on_delivery_label' => 'الدفع عند الاستلام',
        ];
    }

    /** SETTINGS-FIX-01 (SET-01, SET-02): the footer's link fields: optional, and empty means no link. */
    public const FOOTER_URL_FIELDS = ['instagram_url', 'facebook_url', 'x_url'];

    /**
     * SETTINGS-FIX-01 (SET-02, R-02): the footer as shown (storefront and settings form). Each text field is its saved
     * text, or its default when the saved value is missing, empty or not text, so no heading or link label is ever
     * empty. Each link field is its saved http(s) address, or empty (no link). Saved content of any other shape (a
     * string, a number, null) counts as nothing saved. Read only: what is stored is never rewritten here.
     *
     * FOOTER-I18N-01B: in the given language (Arabic when none is given: the settings form). The texts come from
     * footer_content.texts.<locale> when it is a list of texts; without texts.ar, the flat fields are the Arabic texts
     * (all data saved so far). English never shows the Arabic texts: what English lacks is its approved default. Any
     * other language reads English. The links stay language-neutral: always the flat fields.
     *
     * @return array<string, string>
     */
    public static function footerFor(?self $setting, string $locale = 'ar'): array
    {
        $content = is_array($setting?->footer_content) ? $setting->footer_content : [];
        $locale = in_array($locale, self::FOOTER_LOCALES, true) ? $locale : 'en';
        $texts = is_array($content['texts'] ?? null) ? $content['texts'] : [];
        $localized = is_array($texts[$locale] ?? null) ? $texts[$locale] : ($locale === 'ar' ? $content : []);
        $footer = [];

        foreach (static::footerDefaults($locale) as $key => $default) {
            $stored = in_array($key, self::FOOTER_URL_FIELDS, true) ? $content : $localized;
            $value = is_string($stored[$key] ?? null) ? trim($stored[$key]) : '';

            $footer[$key] = in_array($key, self::FOOTER_URL_FIELDS, true)
                ? (preg_match('#^https?://#i', $value) === 1 ? $value : '')
                : ($value !== '' ? $value : $default);
        }

        return $footer;
    }

    /** FOOTER-I18N-01B: the saved site name, or the language's fallback when none (or only spaces) is saved. */
    public static function siteNameFor(?self $setting, string $locale): string
    {
        $name = is_string($setting?->site_name) ? trim($setting->site_name) : '';

        return $name !== '' ? $name : (self::SITE_NAME_FALLBACKS[$locale] ?? self::SITE_NAME_FALLBACKS['en']);
    }

    /**
     * SETTINGS-FIX-01 (SET-02, R-02): the footer content to save: the saved content (the defaults the first time; saved
     * content that is not an object counts as none) with the submitted fields over it. A field left out keeps its saved
     * value; a submitted empty (or whitespace-only) text field goes back to its default; an empty link field is saved
     * empty (no link). Fields the footer does not have are ignored.
     *
     * @return array<string, mixed>
     */
    public static function mergeFooter(mixed $stored, array $submitted): array
    {
        $defaults = static::footerDefaults();
        $footer = array_replace($defaults, is_array($stored) ? $stored : []);

        foreach (array_intersect_key($submitted, $defaults) as $key => $value) {
            $value = is_string($value) ? trim($value) : '';
            $footer[$key] = $value !== '' || in_array($key, self::FOOTER_URL_FIELDS, true) ? $value : $defaults[$key];
        }

        return $footer;
    }

    /** FOOTER-I18N-01C: the footer's text fields, everything but the links (20). */
    public static function footerTextFields(): array
    {
        return array_values(array_diff(array_keys(static::footerDefaults()), self::FOOTER_URL_FIELDS));
    }

    /**
     * FOOTER-I18N-01C: the texts saved for a language, without defaults: texts.<locale> when it is a list of texts;
     * for Arabic without texts.ar, the flat fields (all data saved before). Only non-empty texts count. English never
     * receives the Arabic texts.
     *
     * @return array<string, string>
     */
    public static function savedFooterTexts(?self $setting, string $locale): array
    {
        return static::textsFrom(is_array($setting?->footer_content) ? $setting->footer_content : [], $locale);
    }

    /**
     * FOOTER-I18N-01C: the footer content to save from the bilingual form (footer[ar|en][field]). Only the languages
     * and fields sent change: a language left out, or a field left out, keeps what is saved. A field sent empty, or
     * with its language's default, is removed, so the language's default shows (it follows the default if that ever
     * changes). Arabic starts from the flat fields while texts.ar does not exist yet. The flat fields then hold the
     * resolved Arabic texts again, for code that still reads only them (a rollback). The links, social_links and any
     * other key are kept as they are. Saving the same form twice gives the same content.
     *
     * @return array<string, mixed>
     */
    public static function mergeFooterTexts(mixed $stored, array $submitted): array
    {
        $content = array_replace(static::footerDefaults(), is_array($stored) ? $stored : []);
        $texts = is_array($content['texts'] ?? null) ? $content['texts'] : null;
        $fields = array_flip(static::footerTextFields());

        foreach (self::FOOTER_LOCALES as $locale) {
            if (! is_array($submitted[$locale] ?? null)) {
                continue;
            }

            $defaults = static::footerDefaults($locale);
            $current = array_filter(static::textsFrom($content, $locale), fn (string $value, string $key) => $value !== $defaults[$key], ARRAY_FILTER_USE_BOTH);

            foreach (array_intersect_key($submitted[$locale], $fields) as $key => $value) {
                $value = is_string($value) ? trim($value) : '';

                if ($value === '' || $value === $defaults[$key]) {
                    unset($current[$key]);
                } else {
                    $current[$key] = $value;
                }
            }

            $texts ??= [];
            $texts[$locale] = $current;
        }

        if ($texts === null) {
            return $content;
        }

        $content['texts'] = $texts;

        if (is_array($texts['ar'] ?? null)) {
            foreach (array_keys($fields) as $key) {
                $content[$key] = is_string($texts['ar'][$key] ?? null) ? $texts['ar'][$key] : static::footerDefaults('ar')[$key];
            }
        }

        return $content;
    }

    /** @return array<string, string> */
    private static function textsFrom(array $content, string $locale): array
    {
        $texts = is_array($content['texts'] ?? null) ? $content['texts'] : [];
        $source = is_array($texts[$locale] ?? null) ? $texts[$locale] : ($locale === 'ar' ? $content : []);
        $saved = [];

        foreach (static::footerTextFields() as $key) {
            $value = is_string($source[$key] ?? null) ? trim($source[$key]) : '';

            if ($value !== '') {
                $saved[$key] = $value;
            }
        }

        return $saved;
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['singleton_key' => 1],
            static::defaults()
        );
    }
}
