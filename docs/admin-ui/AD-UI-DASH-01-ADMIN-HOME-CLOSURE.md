# AD-UI-DASH-01 — إغلاق الصفحة الرئيسية للوحة الإدارة

| البند | القيمة |
|---|---|
| الحالة | **AD-UI-DASH-01 = PASS / CLOSED** (قرار مالك المشروع) |
| تاريخ الاعتماد | 2026-10-13 |
| الصفحة | `/dashboard` (`dashboard.home`): `AdminDashboardController::index` و`resources/views/dashboard/admin/index.blade.php` |
| النطاق | DASH-1 (الصلاحيات ورسائل الجلسة)، DASH-2 (الترجمات)، DASH-3 (تباين الأزرار)، DASH-4 (تهيئة `Admin_Control_Center`) |
| الجولات | AD-UI-DASH-01-AUDIT ← DASH-01-FIX-01؛ AD-UI-DASH-02-AUDIT ← DASH-02-IMPLEMENT ← DASH-02-SEED؛ AD-UI-DASH-03-04-AUDIT ← DASH-03-04-FIX-01 |
| ملاحظة | لا تعدّل هذه الوثيقة الـ SRS ولا وثيقة AD-UI-01 ولا وثيقة قرارات التصنيفات. نشأت الملاحظة DASH-1 في `AD-UI-CAT-01-CATEGORIES-DECISIONS.md` (القسم 6.4) دون أن تعيد فتح CAT-01F |

---

## 1. DASH-1 — المحتوى حسب الصلاحيات ورسائل الجلسة

### 1.1 المشكلة (AD-UI-DASH-01-AUDIT)

- الصفحة لا تفحص أي صلاحية (المسار يتطلب تسجيل دخول المشرف فقط)، فتعرض لكل مشرف 19 نوعًا من الروابط، ويقود كل رابط إلى 403 لمن لا يملك صلاحية وجهته (DASH-1a).
- تعرض لكل مشرف 12 عددًا وأسماء البائعين المعلّقين ومدنهم وأرقام طلبات البائعين، وتستعلم عنها جميعًا (DASH-1b، كشف بيانات داخلي).
- لا تعرض رسائل الجلسة (DASH-1c)؛ والـ Admin Layout المشترك يعرض `$errors` فقط.

### 1.2 الإصلاح (DASH-01-FIX-01)

- **فحص الصلاحية قبل الاستعلام** في `AdminDashboardController`: بنفس شرط الشريط الجانبي (Super Admin أو `hasAbility`)؛ البيانات المحجوبة قيمتها `null` (لا صفر) ولا يُستعلم عنها.
- **العرض حسب الصلاحية** في الصفحة: كل بطاقة وإجراء سريع وجدول ورابط يظهر فقط لمن يملك صلاحية وجهته؛ وتُخفى حاوية الإجراءات السريعة إذا خلت. الصفر الحقيقي يُعرض لمن يملك الصلاحية.
- **رسائل الجلسة:** `success` بـ `role="status"` و`danger` بـ `role="alert"`، بتنسيق `admin-media-alert` الموجود، والنص مهرّب؛ دون تعديل الـ Layout ودون JavaScript.
- لا تغيير في المسارات أو الـ Middleware أو نظام الصلاحيات، ولا تُمنح أي صلاحية تلقائيًا.

| البيانات والروابط | الصلاحية |
|---|---|
| البائعون المعلّقون والنشطون، وقائمة البائعين المعلّقين وروابط «عرض الكل» و«مراجعة» | `vendors.view` |
| طلبات البائعين المعلّقة، «تحتاج متابعة»، تنبيهات مهلة الرد وروابطها، وعدد التعيينات المفتوحة (غير معروض) | `vendor-orders.view` |
| المنتجات النشطة، المخزون المنخفض، رابط المنتجات | `products.view` |
| المندوبون بانتظار الموافقة، رابط مندوبي التوصيل | `delivery-drivers.view` |
| النزاعات المفتوحة | `disputes.view` |
| التقييمات بانتظار المراجعة مع رابطها | `reviews.view` |
| طلبات الاسترجاع المفتوحة مع رابطها | `return-requests.view` |
| عدد الطلبات (غير معروض) ورابط الطلبات | `orders.view` |
| روابط التصنيفات والعملاء | `categories.view`، `customers.view` |

**الاستعلامات على جداول الوحدات عند عرض الصفحة:** Super Admin ‏15؛ مشرف محدود (`products.view` و`orders.view`) ‏3؛ مشرف يملك `categories.delete` فقط ‏0؛ مشرف بلا صلاحيات وحدات ‏0. كل رابط ظاهر يُفتح دون 403 (18/18 لـ Super Admin، 4/4 للمحدود).

---

## 2. DASH-2 — ترجمات الصفحة الرئيسية

### 2.1 المشكلة (AD-UI-DASH-02-AUDIT)

`t()` (`app/helpers_locale.php`، `auto_create` مفعّل) ينشئ المفتاح الغائب في لغة العرض بالنص الافتراضي المكتوب في الكود. لذلك من 34 مفتاحًا للصفحة: 31 صفًا عربيًا بنص إنجليزي، والصف الإنجليزي لـ `Admin_Dashboard` بنص عربي، و`Review` غائب باللغتين. لم يكن أي منها مخصصًا يدويًا.

### 2.2 الإصلاح (DASH-02-IMPLEMENT، DASH-02-SEED)

- **`Database\Seeders\AdminHomeTranslationSeeder`** (جديد، غير مستدعى من `DatabaseSeeder`): 33 مفتاحًا باللغتين بالقيم المعتمدة في تقرير التدقيق، ومصطلحات الشريط الجانبي («الطلبات الفرعية»، «مندوبو التوصيل»...). إدراج الناقص فقط (`insertOrIgnore`)، وتصحيحات محروسة بمطابقة **حرفية** في PHP مع تحديث مشروط بالقيمة القديمة، ومسح ذاكرة الترجمة للصفوف المتغيرة. لا يشمل `Admin_Control_Center`.
- **المفاتيح المشتركة الاثنا عشر** (بموافقة مالك المشروع): `Categories`، `Products`، `Customers`، `Delivery_Drivers`، `Orders`، `Store`، `Owner`، `City`، `Actions`، `Order`، `Vendor`، `Needs_attention`؛ فصُحّحت نفس العناوين في صفحات الإدارة الأخرى التي تستخدمها.
- النص الافتراضي لـ `Admin_Dashboard` في الصفحة صار إنجليزيًا («Admin panel»).
- **التشغيل على التطوير** (مرة واحدة، 2026-10-12، بعد Dry Run مطابق): إدراج 2 (`Review`)، وتصحيح 32 (31 عربيًا و`Admin_Dashboard` بالإنجليزية)، ولا تغيير خارج التصحيحات المعتمدة؛ `translation_values` من 1136 إلى **1138**؛ Dry Run لاحق: 0 و0.

---

## 3. DASH-3 — تباين أزرار الصفحة الرئيسية

- **المشكلة:** تنسيقات Able Pro (`public/assets-dashboard/css/style.css`): `btn-light-secondary` بتباين 4.33:1، و`btn-light-warning` («فتح») بتباين 1.99:1 (hover ‏2.64، focus ‏2.83). إصلاح D3 السابق محصور في `.admin-media`.
- **الإصلاح (DASH-03-04-FIX-01):** نطاق `.admin-home` على بطاقات الأزرار الثلاث في الصفحة فقط؛ `btn-light-secondary` يعيد استخدام قواعد D3 نفسها (إضافة محددات، دون ألوان جديدة)؛ و`btn-light-warning` بنص `#8a4520` على `--color-hf-surface` وحدّ `--color-hf-copper`، وخلفية `--color-hf-canvas` في hover وfocus وfocus-visible وactive. حلقة التركيز العامة باقية. لا تعديل على Able Pro أو الـ Layout أو صفحات أخرى.

| الزر | قبل (normal / hover / focus-visible) | بعد (normal / hover / focus / focus-visible / active) |
|---|---|---|
| `btn-light-secondary` (9 أزرار) | 4.33 / 5.49 / 7.52 | 13.4 / 12.52 / 13.4 / 12.52 / 12.52 |
| `btn-light-warning` («فتح») | 1.99 / 2.64 / 2.83 | 6.7 / 6.26 / 6.26 / 6.26 / 6.26 |

قيست فعليًا في Edge بالعربية والإنجليزية. صفحات المنتجات والبائعين وقائمة التصنيفات: صفر اختلافات في الحالة العادية قبل الإصلاح وبعده، والفئة `admin-home` لا توجد إلا في الصفحة الرئيسية.

---

## 4. DASH-4 — تهيئة `Admin_Control_Center`

- **المشكلة:** `AdminHeaderTranslationSeeder` كان يُدرج الصف الإنجليزي فقط (عبر التصحيح)؛ فعلى قاعدة جديدة يظهر العنوان «Admin panel» على الصفحة العربية في أول عرض، ثم ينشئ `t()` الصف العربي.
- **الإصلاح:** إضافة `dashboard.Admin_Control_Center` (ar «لوحة الإدارة» / en «Admin panel») إلى `TRANSLATIONS` في `AdminHeaderTranslationSeeder`، مع إبقاء التصحيح الإنجليزي المحروس كما هو؛ والنص الافتراضي في الصفحة صار «Admin panel». لا تداخل مع `AdminHomeTranslationSeeder`.
- **التطوير:** الصفان موجودان وصحيحان؛ Dry Run: إدراج 0 وتصحيح 0. لم يُشغَّل الـ Seeder على التطوير (لا حاجة).

---

## 5. نتائج الاختبارات

| المجموعة | النتيجة |
|---|---|
| الحزمة الكاملة (PHPUnit، SQLite) | **905 PASS**، و**30 SKIPPED** (اختبارات MySQL، تُشغَّل على قاعدة معزولة)، و**7 إخفاقات قديمة خارج النطاق** |
| MySQL معزول (`hirfah_fk_test_*`، تُحذف بعد التحقق من الاسم) | ناجحة في كل الجولات (21/21 لاختبارات Seeders الترجمة في الجولة الأخيرة) |
| اختبارات الصفحة الرئيسية | `AdminDashboardTest`، `AdminDashboardHomePermissionsTest`، `AdminHomeTranslationSeederTest`، `AdminHeaderTranslationSeederTest` |
| اختبارات الطفرات | كل طفرة (إلغاء فحص الصلاحية، عرض كل البطاقات، إلغاء الرسائل، تجاهل المطابقة الحرفية، حذف إدراج `Admin_Control_Center`) أفشلت اختبارًا واحدًا على الأقل |

**الإخفاقات السبعة القديمة** (خارج الصفحة الرئيسية): `ArabicEncodingTest` (مجلد `ask/` غير موجود)، و`HomeHeroTest` ×3 (مسارات `home-hero` ونموذج `HomeHero` غير موجودة)، و`SearchTest` ×3 (نموذج `Area` ومسارات البحث غير موجودة).

## 6. Edge وإمكانية الوصول

- مراجعة الصفحة الرئيسية بالعربية والإنجليزية (RTL/LTR) على 1440 و820 و390 وتكبير 200%، لأدوار Super Admin والمحدود ومشرف الحذف فقط وصفحة الرسالة: **40/40**، دون تمرير أفقي، ودون أخطاء Console.
- **axe:** صفر مخالفات `color-contrast` في محتوى الصفحة بعد DASH-3 (كانت 10 عقد)؛ المخالفة العامة N1 (`meta-viewport`) في الـ Layout مستثناة.
- لا تراجع في مراجعات قسم التصنيفات (القائمة 68/68، النموذج 72/72، تأكيد الحذف 34/34، FIX-02 ‏24/24، FIX-03 ‏20/20).

## 7. حالة قاعدة التطوير

- `translation_values` = **1138**، دون صفوف مكررة؛ `categories` = 0؛ لا Migrations معلّقة.
- الكتابة الوحيدة على التطوير في هذا النطاق: تشغيل `AdminHomeTranslationSeeder` المعتمد (DASH-02-SEED).
- لُوحظ تحديث `last_login_at` و`updated_at` للمشرف #1 عند تسجيل دخول على خادم التطوير (2026-10-12، 22:53 بالتوقيت المحلي)؛ لم يتغير غيرهما.

## 8. حالة المستودع

لا Commit ولا Push حتى الآن. الملفات المعدّلة أو الجديدة في هذا النطاق:

- `app/Http/Controllers/Dashboard/AdminDashboardController.php`
- `resources/views/dashboard/admin/index.blade.php`
- `resources/css/admin-dashboard.css`
- `database/seeders/AdminHomeTranslationSeeder.php` (جديد)
- `database/seeders/AdminHeaderTranslationSeeder.php`
- `tests/Feature/AdminDashboardHomePermissionsTest.php` (جديد)
- `tests/Feature/AdminHomeTranslationSeederTest.php` (جديد)
- `tests/Feature/AdminHeaderTranslationSeederTest.php`
- `tests/Feature/AdminDashboardTest.php`

---

## 9. ملاحظات مؤجلة (لا تُنفذ ضمن هذا الإغلاق)

| # | الملاحظة | الحالة |
|---|---|---|
| D-1 | **تباين الأزرار في بقية صفحات الإدارة:** `btn-light-secondary` بتباين 4.33:1 خارج `.admin-media` و`.admin-home` (مستخدم في 22 ملفًا، منها المنتجات والبائعون والطلبات والعملاء واللغات والمشرفون)، و`btn-light-danger` في صفحة المنتجات بتباين 2.99:1. الإصلاح العام يغيّر شكل تلك الصفحات ويحتاج جولة واعتمادًا مستقلين | مؤجل |
| D-2 | **N1:** مخالفة `meta-viewport` (axe) في الـ Admin Layout المشترك؛ تُعالج في جولة الـ Layout | مؤجل |
| D-3 | **الإخفاقات السبعة القديمة** (القسم 5): خارج نطاق الصفحة الرئيسية | مؤجل |

## 10. سجل التغييرات

| التاريخ | التغيير |
|---|---|
| 2026-10-13 | إنشاء الوثيقة: قرار مالك المشروع **AD-UI-DASH-01 = PASS / CLOSED**؛ توثيق DASH-1 إلى DASH-4 ونتائجها، والملاحظات المؤجلة D-1 إلى D-3 |
