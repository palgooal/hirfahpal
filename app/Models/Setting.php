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
            'default_currency' => 'USD',
        ];
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

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['singleton_key' => 1],
            static::defaults()
        );
    }
}
