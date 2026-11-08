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

    /**
     * The text and external links rendered in the storefront footer.
     * Keeping these defaults here lets existing installations gain editable
     * footer content without changing what customers see before the first save.
     */
    public static function footerDefaults(): array
    {
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
     * @return array<string, string>
     */
    public static function footerFor(?self $setting): array
    {
        $stored = is_array($setting?->footer_content) ? $setting->footer_content : [];
        $footer = [];

        foreach (static::footerDefaults() as $key => $default) {
            $value = is_string($stored[$key] ?? null) ? trim($stored[$key]) : '';

            $footer[$key] = in_array($key, self::FOOTER_URL_FIELDS, true)
                ? (preg_match('#^https?://#i', $value) === 1 ? $value : '')
                : ($value !== '' ? $value : $default);
        }

        return $footer;
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

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['singleton_key' => 1],
            static::defaults()
        );
    }
}
