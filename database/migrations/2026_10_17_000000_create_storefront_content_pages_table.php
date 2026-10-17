<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('storefront_content_pages')->insert([
            ['slug' => 'about', 'title' => 'قصة المنصة ورؤيتها', 'excerpt' => 'نتعرف من خلالها على رسالة حِرفة.', 'body' => "حِرفة سوق رقمي يربط الحرفيين والتعاونيات الفلسطينية بالعملاء مباشرة.\n\nنؤمن بأن كل قطعة يدوية تحمل قصة صانعها ومكانها، لذلك نعمل على تقديمها بوضوح وثقة.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'authenticity', 'title' => 'ميثاق الأصالة والجودة', 'excerpt' => 'التزامنا تجاه القطع والحرفيين والعملاء.', 'body' => "نعرض منتجات الحرفيين والمشاغل المعتمدة فقط.\n\nنراجع معلومات المتجر والمنتج قبل النشر، ونوفر مساحة للتقييمات بعد استلام الطلبات للمحافظة على جودة التجربة.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'shipping', 'title' => 'الشحن والتوصيل المنظم', 'excerpt' => 'معلومات توصيل الطلبات متعددة المتاجر.', 'body' => "قد يتكون طلبك من أكثر من طلب فرعي عندما تشتري من متاجر مختلفة.\n\nتظهر رسوم التوصيل والمجموع النهائي بوضوح قبل تأكيد الطلب، ويمكن متابعة حالة كل طلب فرعي من حسابك.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'returns', 'title' => 'سياسة الاستبدال والإرجاع', 'excerpt' => 'إرشادات طلب المساعدة بعد الاستلام.', 'body' => "إذا واجهت مشكلة في الطلب، احتفظ بتفاصيله وتواصل معنا من صفحة التواصل.\n\nسنراجع الحالة مع المتجر المعني وفق حالة الطلب والمنتج.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'faq', 'title' => 'الأسئلة الأكثر شيوعاً', 'excerpt' => 'إجابات مختصرة حول الطلبات والحساب.', 'body' => "كيف أتابع طلبي؟\nيمكنك فتح صفحة طلباتي من حسابك لمراجعة حالة كل طلب.\n\nكيف أغير عنواني؟\nيمكنك إضافة العناوين وتعديلها من قسم العناوين في حسابك.\n\nهل يمكن الشراء من أكثر من متجر؟\nنعم، ويُنشأ طلب فرعي مستقل لكل متجر لضمان متابعة واضحة.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'terms', 'title' => 'شروط الاستخدام', 'excerpt' => 'القواعد الأساسية لاستخدام منصة حِرفة.', 'body' => "باستخدامك المنصة، توافق على تقديم معلومات صحيحة عند إنشاء الحساب وإتمام الطلب.\n\nتخضع الطلبات لتوفر المنتجات وموافقة المتجر، وتبقى الأسعار النهائية هي المعروضة عند تأكيد الطلب.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'privacy', 'title' => 'سياسة الخصوصية', 'excerpt' => 'كيف نستخدم بيانات الحساب والطلب.', 'body' => "نستخدم بياناتك لإدارة الحساب والطلب والتوصيل وخدمة العملاء.\n\nلا تظهر بياناتك الشخصية للمتاجر إلا بالقدر اللازم لتنفيذ الطلب، ويمكنك تحديث بياناتك من حسابك.", 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_content_pages');
    }
};
