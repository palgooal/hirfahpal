# CUS-DESIGN-01 — تأكيد حظر العميل وسجل تغييرات الحالة

| البند | القيمة |
|---|---|
| Version | 0.2 |
| Status | DEFERRED — Future Enhancement |
| Project | HIRFAH |
| Scope | Admin Customers |
| Date | 2026-10-17 |
| References | CUS-ACCEPT-01 (تقرير الجلسة، غير محفوظ كملف) / HIRFAH SRS v0.5 (Draft) / HIRFAH Architecture v0.1 (Draft) / AD-UI-01 v0.1 / CUS-DESIGN-02 (مراجعة تقنية لهذه الوثيقة) |

> **DEFERRED — Future Enhancement (CUS-CLOSURE-01، 2026-10-17).** قرر مالك المشروع عدم توسيع إدارة العملاء في المرحلة الحالية، وتأجيل نافذة تأكيد الحظر (D-1) وسجل تدقيق تغييرات الحالة (D-2) ومعالجة تعارض تعديلات المشرفين (OD-4)، والإبقاء على `pending` بسلوكها الحالي (D-3). هذا التصميم v0.2 **محفوظ مرجعًا للتطوير المستقبلي، وليس تصميمًا معتمدًا للتنفيذ الآن**. المؤجل ليس ملغى، وليس خطأ مانعًا لإغلاق المرحلة. قبل أي تنفيذ لاحق: يُعاد التحقق من الكود الفعلي حينها، وتُحسم قرارات القسم 13، ويُعتمد التصميم من جديد. إغلاق المرحلة الحالية: [CUS-CLOSURE-01.md](CUS-CLOSURE-01.md).
>
> وثيقة تصميم فقط. لا تعتمد تنفيذًا، ولا تغيّر كودًا أو قاعدة بيانات. ما يحمل الحالة **Proposed** توصية تحتاج اعتمادًا؛ لا يتحول إلى قرار نهائي إلا بموافقة مالك المشروع (القسم 13).

### سجل الإصدارات

| الإصدار | التاريخ | التغيير |
|---|---|---|
| 0.1 | 2026-10-17 | المسودة الأولى (CUS-DESIGN-01): تصميم D-1 وD-2 وD-3، وعشرة أسئلة مفتوحة |
| 0.2 | 2026-10-17 | مراجعة تقنية (CUS-DESIGN-02): Decision Register؛ تثبيت التوجهات المعتمدة مبدئيًا (أغلقت Q-2 وQ-3 وQ-9 وQ-10)؛ تحليل دقيق لما يُعاد ويُستبعد من `ProductAdminAction`؛ عقد Transaction وConcurrency؛ استراتيجية Migration وRollback (منها حدود `migrate:fresh`)؛ سياسة احتفاظ أقل بالبيانات الشخصية (إلغاء لقطة اسم العميل المقترحة)؛ سيناريو الجلسة الأخرى؛ عقد الترجمة بصياغات مراجعة؛ قرار Controller مقابل Service؛ مراحل التنفيذ؛ قرارات المالك المتبقية |
| 0.2 (حالة) | 2026-10-17 | CUS-CLOSURE-01: الحالة صارت **DEFERRED — Future Enhancement**؛ المحتوى التقني دون تغيير. D-1 وD-2 مؤجلان، وقرارات OD-1..OD-9 لم تُحسم ولم تعد مطلوبة للمرحلة الحالية |

---

## 0. المصادر

### 0.1 ما قُرئ فعلًا

| المصدر | المسار | ما استُخدم منه |
|---|---|---|
| SRS v0.5 (Draft) | `C:\Users\lenovo\Desktop\HIRFAH-SRS-v0.5-Client-Review-FINAL-QA.docx` | FR-ADM-005، AC-ADM-004، NFR-006..009، BR-024 |
| Architecture v0.1 (Draft) | `C:\Users\lenovo\Desktop\hirfah-architecture-reviewed-final.docx` | 1.6.5 Least Privilege، 1.6.9 Auditability، 2.3 (Audit domain)، 2.9 (Data Protection، Idempotency، Consistency) |
| AD-UI-01 | `docs/admin-ui/AD-UI-01-ADMIN-LAYOUT-SPEC.md`، `docs/admin-ui/HIRFAH-AD-UI-01-Specification-v0.1.docx` | DEC-AD-UI-01، 12، 13، 14، 16؛ المكوّن 5.11 Confirm Dialog |
| تقرير CUS-ACCEPT-01 | محادثة الجلسة (غير محفوظ في المستودع) | D-1، D-2، D-3 |
| الكود | `CustomerManagementController.php`، `customers/show.blade.php`، `Customer.php`، `Concerns/HasAccountProfile.php`، `AdminController.php`، `routes/dashboard.php`، `data/abilities.php`، `EnsureAccountIsActive.php`، `GuardLoginService.php`، `config/database.php` | الوضع الحالي (القسم 2) |
| الـ Migrations | `create_owners_table`، `rename_owners_table_to_customers`، `create_admins_table`، `create_product_admin_actions_table`، `add_moderation_columns_to_products`، `restrict_category_foreign_keys`، `add_image_media_id_to_categories`، `create_order_status_histories_table` | المخطط ونمط Rollback |
| أنماط قائمة | `ProductAdminAction.php`، `ProductManagementController::moderate()` و`show()`، `categories/index.blade.php` (dialog)، `admin-categories.js`، `tests/js/admin-categories.test.mjs`، `AdminCategoriesScriptTest.php`، `CategoryHierarchyConcurrencyMySqlTest.php`، `tests/Support/category-hierarchy-worker.php`، `ProductModerationSchemaTest.php` | إعادة الاستخدام |
| الترجمات المعتمدة | `AdminProductTranslationSeeder.php` (`Product_moderation_failed`)، `AdminCategoryTranslationSeeder.php` (`Category_Delete_*`)، `AdminCustomerTranslationSeeder.php` (`Customers_Status_Hint`) | توحيد الصياغات (القسم 10) |
| الاختبارات | `AdminCustomerShowTest.php`، `AdminCustomersIndexTest.php`، `AdminCustomerTranslationSeederTest.php` | أثر التغيير (القسم 11) |

### 0.2 ما لم يُعثر عليه

- **CUS-AUDIT-01** و**CUS-DEC-001..006**: غير موجودة في المستودع أو Desktop أو Downloads أو git log. لم تُراجع.
- لا توجد حزمة Audit عامة في `composer.json`.

---

## 1. Decision Register

**المفتاح:** Approved = معتمد من المالك. Approved (orientation) = توجه تقني معتمد مبدئيًا في CUS-DESIGN-02. Proposed = توصية هذه الوثيقة، تحتاج اعتمادًا. Owner = قرار ينتظر المالك (القسم 13).

### 1.1 قرارات الأعمال

| ID | القرار | الحالة | المصدر |
|---|---|---|---|
| D-1 | نافذة تأكيد قبل حظر العميل | Approved ← **Deferred** (CUS-CLOSURE-01) | المالك، بعد CUS-ACCEPT-01 |
| D-2 | سجل تغييرات حالة العميل: المنفذ، الوقت، الحالة السابقة، الجديدة | Approved ← **Deferred** (CUS-CLOSURE-01) | المالك |
| D-3 | الإبقاء على `pending` وتأجيل آلية التفعيل | Approved (سارٍ: `pending` بسلوكها الحالي) | المالك |
| D-X1 | لا سبب إلزاميًا للحظر | Approved | المالك (CUS-DESIGN-01/02) |

### 1.2 التوجهات التقنية المعتمدة مبدئيًا (CUS-DESIGN-02)

| ID | التوجه | أغلق في v0.1 |
|---|---|---|
| T-1 | إعادة استخدام نموذج PATCH الحالي (المسار والحقل والصلاحية والـ Redirect كما هي) | — |
| T-2 | لا واجهة Audit الآن (لا قسم في صفحة العميل، ولا شاشة مستقلة) | Q-2 |
| T-3 | Transaction وقفل صف العميل | Q-3 (جزئيًا) |
| T-4 | لا سجل إذا لم تتغير الحالة | Q-3 |
| T-5 | لا سجلات بأثر رجعي | Q-10 |
| T-6 | نطاق السجل: تغييرات المشرف عبر إدارة العملاء فقط (لا Observer) | Q-9 |
| T-7 | لا تغيير في سلوك `pending` | — |
| T-8 | لا تغيير في Routes أو صلاحيات العملاء حاليًا (لا `customers.audit` الآن) | Q-2 |

### 1.3 قرارات تقنية مقترحة (Proposed)

| ID | المقترح | القسم |
|---|---|---|
| P-1 | جدول جديد `customer_status_changes` على نمط `product_admin_actions`، لا جدول مشترك ولا حزمة | 3 |
| P-2 | منطق السجل داخل `CustomerManagementController::updateStatus()`، لا Service مستقلة | 4 |
| P-3 | `down()` يرفض ما دام في السجل بيانات؛ الإزالة المقصودة إجراء صريح خارج الـ Migration | 7 |
| P-4 | `customer_id` مرجع فقط، **دون لقطة اسم العميل** (تعديل عن v0.1) | 6 |
| P-5 | `admin_id` مرجع + لقطة `admin_name` (لأن المشرف قابل للحذف وD-2 يشترط المنفذ) — Owner OD-1 | 6 |
| P-6 | `from_status` و`to_status` بنوع `string(20)` مع فرض القيم في الـ Model | 3 |
| P-7 | حفظ حالة لم تتغير يُرجع رسالة النجاح الحالية (لا رسالة جديدة) | 5 |
| P-8 | فشل حفظ السجل يُلغي تحديث الحالة كاملًا | 5 |
| P-9 | التنافس بين جلستين: آخر كاتب يفوز، ويظهر التسلسل في السجل — Owner OD-4 | 5 |
| P-10 | دون JavaScript: يُرسل النموذج بلا تأكيد (تحسين تدريجي) — Owner OD-5 | 8 |
| P-11 | حماية Append-only على مستوى Eloquent كما في المنتجات | 3 |
| P-12 | خمسة مفاتيح ترجمة جديدة بالصياغات في القسم 10 — Owner OD-6 | 10 |

---

## 2. الوضع الحالي (حقائق من الكود)

### 2.1 تغيير الحالة اليوم

`CustomerManagementController::updateStatus()`:

1. `authorizeAbility('customers.edit')`.
2. `validate(['status' => required, in:active,pending,blocked])`؛ الفشل يعود إلى صفحة العميل (CUS-BE-003)، و422 للـ JSON.
3. `$customer->update($data)` — **بلا Transaction، وبلا قفل، وبلا قراءة للحالة السابقة**. الـ `$customer` محمّل بـ Route Model Binding قبل الطلب.
4. Redirect إلى `dashboard.customers.show` مع `success` = `dashboard.Customer_status_updated`، **حتى إن لم تتغير الحالة**.

**العقد (يبقى كما هو، T-1):** `PATCH customers/{customer}/status` باسم `dashboard.customers.update-status`، حقل `status`، CSRF، `customers.edit`، Redirect إلى صفحة التفاصيل، 422 للـ JSON.

### 2.2 الواجهة اليوم

`show.blade.php` يعرض نموذج الحالة فقط لمن يملك `customers.edit`: `POST` + `@csrf` + `@method('PATCH')`، `<select id="customer-status" name="status">`، زر «حفظ الحالة»، والتلميح المعتمد `Customers_Status_Hint`. لا JavaScript، ولا تأكيد، ولا منع للنقر المكرر.

### 2.3 الحماية بعد الحظر

`GuardLoginService` يرفض دخول `blocked` و`pending`؛ `EnsureAccountIsActive` (مجموعة `web`) يُخرج الحساب غير النشط **في الطلب التالي**. مختبر في `AccountStatusEnforcementTest` و`AuthRateLimitingTest`. **لا يتغير.**

### 2.4 الحذف

- **العملاء:** لا مسار حذف في الإدارة أو المتجر. الجداول المرتبطة (`orders`، `customer_addresses`، `reviews`، `disputes`، `return_requests`، `carts`، `customer_favorites`) بـ `cascadeOnDelete`، و`contact_messages` بـ `nullOnDelete`.
- **المشرفون:** قابلون للحذف (`AdminController::destroy`، يمنع حذف المشرف الحالي فقط).
- لا `SoftDeletes` في أي Model.

### 2.5 نمط Rollback في المشروع

- أربع Migrations تحمي بيانات ترفض `down()` ولا تغيّر شيئًا: `restrict_category_foreign_keys`، `add_image_media_id_to_categories`، `add_moderation_columns_to_products` (ترفض ما دام منتج معلّقًا أو مؤرشفًا)، `create_product_admin_actions_table` (ترفض ما دام في السجل بيانات).
- بقية الـ Migrations (43) تحذف دون حراسة، وهي جداول المخطط الأساسي.
- **النمط المعتمد للبيانات الحساسة إذن: الرفض الصريح، لا الحذف الصامت.**

---

## 3. إعادة الاستخدام من `ProductAdminAction` (P-1، P-6، P-11)

### 3.1 ما يُعاد كما هو

| العنصر | في المنتجات | في العملاء |
|---|---|---|
| جدول Append-only مستقل | `product_admin_actions` | `customer_status_changes` |
| `created_at` فقط بـ `useCurrent()`؛ `UPDATED_AT = null` | نعم | نعم |
| FK nullable بـ `nullOnDelete` نحو `admins` | نعم | نعم |
| فهرس (المرجع، `created_at`) | `product_id, created_at` | `customer_id, created_at` |
| `updating`/`deleting` يرميان `LogicException` | نعم | نعم |
| حارس `creating` يتحقق من القيم | الإجراء والسبب | الحالتان، واختلافهما |
| توثيق حدود الحماية في الـ Model (الاستعلام الخام، وON DELETE في قاعدة البيانات) | نعم | نعم، بنفس الصياغة |
| `up()`: جدول موجود يُفحص بـ `assertTargetState()` بدل إعادة إنشائه | نعم | نعم |
| `down()`: رفض ما دام في السجل بيانات | نعم | نعم (القسم 7) |
| Transaction + `lockForUpdate()` + `attempts: 3` + `QueryException` → `report()` + رسالة `danger` | `moderate()` | `updateStatus()` (القسم 5) |
| علاقة في الـ Model الأب مرتبة الأحدث أولًا | `Product::adminActions()` | `Customer::statusChanges()` |

### 3.2 ما لا يُنسخ

| العنصر | السبب |
|---|---|
| `reason` (`text`، إلزامي) وحارس السبب | السبب غير معتمد (D-X1) |
| `action` (`enum` خاص بالمنتجات) | السجل يخص نوعًا واحدًا (تغيير الحالة)؛ `from_status`/`to_status` يكفيان |
| `changes` (`json` قبل/بعد) | الحالتان عمودان صريحان؛ JSON يصعّب الاستعلام والفهرسة |
| لقطات الكيان (`product_name`، `product_sku`) | المنتج قابل للحذف من البائع (C-15)، أما العميل فلا مسار لحذفه؛ تقليل البيانات الشخصية (القسم 6) |
| `admin_name` إلزامي دون نقاش | يبقى مقترحًا لكنه قرار مالك (OD-1) |
| قراءة السجل بقدرة `products.audit` | لا واجهة الآن (T-2، T-8) |
| المنطق عبر جدول `moderationTransitions()` | انتقال واحد عام؛ لا حاجة لجدول انتقالات |

### 3.3 المخطط المقترح (لا يُنشأ الآن)

| العمود | النوع | القيود |
|---|---|---|
| `id` | `bigIncrements` | |
| `customer_id` | `foreignId` nullable | `constrained('customers')->nullOnDelete()` |
| `admin_id` | `foreignId` nullable | `constrained('admins')->nullOnDelete()` |
| `admin_name` | `string` | لقطة اسم المنفذ (P-5، OD-1). إن رُفض OD-1 يُحذف العمود |
| `from_status` | `string(20)` | القيم يفرضها الـ Model (P-6) |
| `to_status` | `string(20)` | كذلك |
| `created_at` | `timestamp` | `useCurrent()` |
| فهرس | `customer_status_changes_customer_created_index` | `(customer_id, created_at)` |

**بديل P-6:** `enum('active','pending','blocked')` مطابق لـ `customers.status`. يمنع القيم الخاطئة حتى في الاستعلام الخام، لكنه يحتاج ALTER عند إضافة حالة لاحقًا (D-3).

### 3.4 Model والعلاقات

```php
// app/Models/CustomerStatusChange.php — على نمط ProductAdminAction
class CustomerStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'admin_id', 'admin_name', 'from_status', 'to_status'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerStatusChange $entry): void {
            if (! in_array($entry->from_status, Customer::STATUSES, true) || ! in_array($entry->to_status, Customer::STATUSES, true)) {
                throw new LogicException('Unknown customer status.');
            }
            if ($entry->from_status === $entry->to_status) {
                throw new LogicException('A customer status change needs two different statuses.');
            }
        });

        static::updating(fn () => throw new LogicException('Customer status changes are append-only and cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Customer status changes are append-only and cannot be deleted.'));
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function admin() { return $this->belongsTo(Admin::class); }
}

// app/Models/Customer.php — إضافتان
public const STATUSES = ['active', 'pending', 'blocked'];

public function statusChanges()
{
    return $this->hasMany(CustomerStatusChange::class)->latest('created_at')->latest('id');
}
```

`CustomerManagementController::STATUSES` يبقى للتسميات، ويُثبت اختبار أن مفاتيحه تساوي `Customer::STATUSES`. لا تغيير على enum العمود `customers.status`.

---

## 4. Controller أم Service مستقلة (P-2)

**التوصية: داخل `CustomerManagementController::updateStatus()`.** لا Service ولا Action class.

| المعيار | الواقع الحالي |
|---|---|
| عدد نقاط الكتابة | واحدة (T-6: الإدارة فقط؛ لا Observer، ولا API، ولا Job) |
| حجم المنطق | نحو 25 سطرًا: قفل، مقارنة، تحديث، إنشاء سجل |
| نمط المشروع | `ProductManagementController::moderate()` ينفذ نفس المنطق داخل الـ Controller (private method). `app/Support` يحمل خدمات مشتركة بين عدة نقاط (مثل `GuardLoginService`) |
| الثوابت | يحميها الـ Model (`creating`/`updating`/`deleting`)، لا الـ Controller، فلا تضيع بنقل المنطق لاحقًا |
| الاختبار | اختبارات Feature عبر المسار الحقيقي، وعمّال MySQL يستدعون الـ Controller نفسه |

**معيار الاستخراج لاحقًا:** تُستخرج خدمة (مثل `App\Support\Customers\CustomerStatusChanger`) فقط إذا ظهرت نقطة كتابة ثانية (تفعيل `pending` مستقبلًا، Bulk، API). هذا ليس قرارًا الآن.

---

## 5. Transaction & Concurrency Contract

### 5.1 التسلسل

```php
public function updateStatus(Request $request, Customer $customer): RedirectResponse
{
    $this->authorizeAbility($request, 'customers.edit');               // C-1
    $page = route('dashboard.customers.show', $customer);

    $data = /* التحقق الحالي كما هو (CUS-BE-003، CUS-BE-006) */;       // C-1
    $admin = $request->user('admin');

    try {
        DB::transaction(function () use ($customer, $data, $admin): void {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();   // C-2
            $from = $locked->status;

            if ($from === $data['status']) {
                return;                                                          // C-3
            }

            $locked->forceFill(['status' => $data['status']])->save();          // C-4
            CustomerStatusChange::create([                                       // C-4
                'customer_id' => $locked->id,
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'from_status' => $from,
                'to_status' => $data['status'],
            ]);
        }, attempts: 3);                                                         // C-6
    } catch (QueryException $exception) {                                        // C-5
        report($exception);

        return redirect()->to($page)->withInput($request->only('status'))->with('danger', $this->untranslatedDefault(
            'dashboard.Customer_status_failed',
            'The status could not be saved, so nothing was changed. Please try again.'
        ));
    }

    return redirect()->to($page)->with('success', $this->untranslatedDefault(
        'dashboard.Customer_status_updated', 'Customer status updated successfully.'
    ));                                                                          // C-3، P-7
}
```

### 5.2 البنود

| # | البند |
|---|---|
| C-1 | الصلاحية ثم التحقق **قبل** فتح الـ Transaction، كما اليوم. الرفض (403، 422، redirect) لا يقفل شيئًا ولا يكتب شيئًا |
| C-2 | **الحالة السابقة تُقرأ من الصف المقفل فقط.** `SELECT … FOR UPDATE` على صف واحد بالمعرف. في InnoDB القراءة المقفلة ترى آخر نسخة مثبتة (committed) بصرف النظر عن لقطة REPEATABLE READ. الـ `$customer` المحمّل بالـ Binding لا يُستخدم إلا للمعرف ورابط الـ Redirect |
| C-3 | إن تساوت الحالة المقفلة مع المطلوبة: لا `UPDATE`، ولا سجل، ولا تغيير `updated_at`؛ تُثبت الـ Transaction فارغة، وتُرجع رسالة النجاح الحالية (P-7) |
| C-4 | `UPDATE customers` ثم `INSERT customer_status_changes` في نفس الـ Transaction وعلى نفس الاتصال |
| C-5 | **أي استثناء داخل الـ Transaction يُرجع الاثنين معًا** (P-8): لا حالة بلا سجل، ولا سجل بلا حالة. `QueryException`: `report()` ثم `danger` مع حفظ المدخل. استثناء آخر (مثل `LogicException` من حارس الـ Model، وهو غير قابل للوصول من هذا المسار لأن C-3 يسبقه) يُرجع الاثنين ويظهر 500؛ لا يُكتم |
| C-6 | `attempts: 3`: يعيد Laravel المحاولة عند أخطاء التزامن (Deadlock، Lock wait timeout) **فقط في المستوى الأول من الـ Transaction**. أسوأ انتظار نظري ≈ 3 × `innodb_lock_wait_timeout` (افتراضيًا 50 ثانية)، والقفل يدوم جملتين قصيرتين فقط |
| C-7 | نطاق القفل: صف العميل وحده. صف المشرف لا يُقفل؛ `admin_name` هو الاسم كما حُمّل في بداية الطلب |
| C-8 | `firstOrFail()`: إن حُذف العميل بين الـ Binding والقفل → 404 ولا كتابة (لا مسار حذف اليوم) |
| C-9 | الجلسات: لا يتغير شيء داخل الـ Transaction. `EnsureAccountIsActive` يُخرج العميل المحظور في طلبه التالي. **حد قائم لا يغيّره التصميم:** طلب للعميل تجاوز الـ Middleware قبل الحظر (مثل checkout جارٍ) يكتمل |
| C-10 | الاختبارات: `RefreshDatabase` يغلّف كل اختبار بـ Transaction، فـ `DB::transaction` يصبح Savepoint ولا تُعاد المحاولة؛ وعلى SQLite يُتجاهل `FOR UPDATE` وتُسلسل الكتابة بقفل القاعدة كلها. **لذا لا يثبت التزامن الحقيقي إلا على MySQL** (القسم 5.4) |

### 5.3 سلوك الطلبات المتزامنة (P-9)

| السيناريو (العميل `active`) | الترتيب | النتيجة | السجل |
|---|---|---|---|
| مشرفان يطلبان `blocked` | A يقفل، B ينتظر | A يكتب؛ B يقرأ `blocked` = المطلوب → C-3 | سجل واحد `active→blocked`؛ الطلبان «نجاح» |
| A `blocked`، B `pending` | A يقفل، B ينتظر | A يكتب `blocked`؛ B يقرأ `blocked` ويكتب `pending` | `active→blocked` ثم `blocked→pending`؛ الحالة النهائية `pending` |
| نقر مزدوج من نفس المشرف | الطلب الثاني ينتظر | مثل الصف الأول | سجل واحد |
| إعادة إرسال بعد ساعة | — | إن لم يغيّرها أحد: C-3؛ وإن غيّرها: تغيير جديد مشروع | 0 أو 1 |

**آخر كاتب يفوز (P-9):** في الصف الثاني، B بنى قراره على صفحة قديمة (`active`) فألغى حظر A دون أن يعلم. السجل يُظهر التسلسل بدقة (`from_status` في الثاني = `to_status` في الأول)، فلا يضيع شيء، لكن **الحظر رُفع**. البديل: حقل مخفي `expected_status` يرفض الطلب إن اختلفت الحالة المقفلة (Optimistic Concurrency). هو إضافة اختيارية للعقد، لكنها تغيّر سلوك الـ Backend، فتحتاج قرارًا (OD-4).

### 5.4 اختبارات MySQL المعزولة

على نمط `CategoryHierarchyConcurrencyMySqlTest`:
- عامل لكل طلب: `tests/Support/customer-status-worker.php`، باتصال MySQL مستقل، عبر الـ Controller الحقيقي.
- الـ holder يتوقف بعد القفل وقبل الكتابة عبر `Customer::updating($hold)`، حتى يُنشأ ملف `release`.
- الاختبار ينتظر انتظار القفل الحي في `performance_schema.data_lock_waits` قبل تحرير الـ holder: تنسيق لا توقيت.
- يعمل فقط على قاعدة `hirfah_fk_test_*` عبر `CATEGORY_FK_MYSQL_DATABASE`، ويُتخطى دونها.

| # | الاختبار |
|---|---|
| M-1 | نفس الحالة المستهدفة بالتوازي → سجل واحد، حالة نهائية صحيحة، الطلبان ناجحان، والـ contender كان في LOCK WAIT فعلًا |
| M-2 | حالتان مختلفتان بالتوازي → سجلان متسلسلان، `from` الثاني = `to` الأول |
| M-3 | فشل الـ INSERT (Trigger بـ `SIGNAL SQLSTATE '45000'` في القاعدة المعزولة) → الحالة لم تتغير، ولا سجل، و`danger` |
| M-4 | المخطط على MySQL: الأنواع، وأسماء الـ FK، و`ON DELETE SET NULL`، والفهرس |
| M-5 | `down()` يرفض مع وجود سجلات ويقبل مع جدول فارغ |
| M-6 | حذف المشرف ثم حذف العميل (بـ SQL مباشر في القاعدة المعزولة) → السجل باقٍ، والمرجعان NULL، و`admin_name` باقٍ |

**إنشاء قاعدة `hirfah_fk_test_*` على خادم MySQL المحلي يحتاج موافقة مستقلة (OD-8)، لأن الاختبارات الـ30 المتخطاة كلها تنتظره كذلك.**

---

## 6. Data Retention Considerations

### 6.1 الأسئلة

| السؤال | التحليل |
|---|---|
| هل لقطة **اسم العميل** ضرورية الآن؟ | **لا.** لا مسار لحذف العميل، فالمرجع `customer_id` كافٍ للربط والعرض. اللقطة نسخة ثانية من بيانات شخصية بلا حاجة حالية. **تعديل عن v0.1: أُلغيت** (P-4) |
| هل لقطة **اسم المشرف** ضرورية؟ | **نعم على الأرجح.** المشرفون قابلون للحذف اليوم؛ بلا لقطة يصبح `admin_id` NULL فيضيع «المنفذ» الذي يشترطه D-2. البيانات هنا بيانات موظف لا عميل (P-5، OD-1) |
| أثر حذف المشرف | `nullOnDelete` → `admin_id` NULL؛ مع اللقطة يبقى الاسم؛ بدونها يصبح السجل «مشرف محذوف» |
| أثر حذف العميل (مستقبلًا) | `nullOnDelete` → السجل يبقى **مجهول الهوية** (لا اسم ولا مرجع)، فيبقى أثر أن حسابًا ما غُيّر دون بيانات شخصية. بقية بيانات العميل تُحذف بالـ cascade كما اليوم |
| هل يمكن الاكتفاء بالمفاتيح المرجعية؟ | للعميل: نعم. للمشرف: فقط إن قُبل ضياع المنفذ عند حذفه، أو مُنع حذف المشرف صاحب السجلات |

### 6.2 البدائل لربط المشرف

| البديل | البيانات المحفوظة | الأثر |
|---|---|---|
| **A (مقترح)** — FK `nullOnDelete` + `admin_name` | المعرف والاسم | المنفذ مقروء بعد الحذف؛ نفس نمط المنتجات |
| B — FK `nullOnDelete` دون لقطة | المعرف فقط | الأقل احتفاظًا؛ المنفذ يضيع عند الحذف |
| C — FK `restrictOnDelete` دون لقطة | المعرف فقط | المنفذ لا يضيع، لكن **يمنع حذف أي مشرف له سجلات**؛ `AdminController::destroy` اليوم لا يعالج هذا الرفض (سيظهر خطأ قاعدة بيانات)، فيلزم تعديل خارج نطاق العملاء |
| D — `admin_id` دون FK | معرف معلق | الرقم يبقى بلا معنى بعد الحذف؛ لا يُنصح |

### 6.3 بدائل ربط العميل عند الحذف المستقبلي

| البديل | الأثر |
|---|---|
| **`nullOnDelete` (مقترح)** | السجل يبقى مجهولًا؛ عدّ التغييرات ممكن؛ لا بيانات شخصية |
| `cascadeOnDelete` | السجل يُحذف مع العميل؛ أقل احتفاظًا، لكنه يمحو أثر التدقيق (يخالف روح Auditability) |

### 6.4 ما لا يُحفظ

البريد، والهاتف، وعنوان IP، ووكيل المتصفح، والسبب، ونص حر. مدة احتفاظ محددة: **غير محددة** (لا سياسة في SRS؛ OD-7).

---

## 7. Migration & Rollback Strategy (P-3)

### 7.1 `up()`

- اسم الملف: `database/migrations/2026_10_XX_000000_create_customer_status_changes_table.php` (التاريخ عند التنفيذ).
- **جدول جديد فقط**؛ لا يكتب أي صف قائم، ولا يغيّر `customers` أو `admins`.
- إن وُجد الجدول: `assertTargetState()` يفحص الأعمدة والمفتاحين (`SET NULL`) والفهرس، ويرفض أي شكل آخر دون تغيير (إعادة تشغيل بعد تشغيل جزئي).
- على MySQL 8 الـ `CREATE TABLE` ذري؛ لا ALTER على جداول قائمة.

### 7.2 `down()` — التوصية

| الحالة | السلوك |
|---|---|
| الجدول غير موجود | لا شيء |
| الجدول فارغ | `Schema::drop()` |
| الجدول فيه سجلات | **`RuntimeException` ولا تغيير** ("customer_status_changes holds N entries; rolling back would erase the audit trail. Nothing was changed.") |

**لماذا الرفض:** يطابق النمط الذي اتبعته الـ Migrations الأربع الحامية للبيانات (القسم 2.5). لا خيار `--force` أو متغير بيئة يتجاوز الرفض داخل الـ Migration، لأن ذلك يجعل الحذف ممكنًا بأمر روتيني.

**الإزالة المقصودة (إجراء صريح، خارج الـ Migration، بموافقة المالك):**
1. تصدير الجدول (`mysqldump <db> customer_status_changes`) وحفظ النسخة.
2. تفريغه يدويًا بأمر SQL مقصود يُوثق في سجل التغيير.
3. ثم `migrate:rollback`.

### 7.3 حدود الحماية — مهم

| الأمر | هل يمر بـ `down()`؟ | النتيجة |
|---|---|---|
| `migrate:rollback` / `migrate:reset` / `migrate:refresh` | نعم | **مرفوض** مع وجود سجلات |
| **`migrate:fresh`** | **لا** (يحذف كل الجداول مباشرة) | **يمحو السجل دون رفض** |
| `db:wipe` | لا | يمحو السجل |
| `RefreshDatabase` في الاختبارات | لا | مقبول (قاعدة الذاكرة) |

الحماية في `down()` **لا تمنع** `migrate:fresh` أو `db:wipe`. الضمان الوحيد ضدهما: النسخ الاحتياطي (NFR-019..021) ومنع هذين الأمرين على قواعد غير محلية (إجراء تشغيلي، خارج الكود). يُذكر ذلك في ملاحظة النشر.

### 7.4 التطور اللاحق

Forward-only: أي تغيير على الجدول بعد نشره يكون Migration جديدة، لا تعديلًا للقديمة.

---

## 8. D-1 — نافذة تأكيد الحظر (مراجعة السلوك)

### 8.1 متى تظهر

```js
// resources/js/admin-customer-show.js
export function needsBlockConfirmation(current, target) {
    return target === 'blocked' && current !== 'blocked';
}
```

| الحالية المحفوظة | المستهدفة | نافذة؟ |
|---|---|---|
| `active` / `pending` | `blocked` | نعم |
| `blocked` | `blocked` | لا |
| أي حالة | `active` / `pending` | لا |

الحالية تُقرأ من `data-current-status="{{ $customer->status }}"` على النموذج (قيمة الخادم عند العرض)، لا من القائمة التي قد تحمل `old('status')`.

### 8.2 البنية

النموذج الحالي يبقى **الوحيد** الذي يرسل PATCH (T-1). النافذة بلا `<form>` وبلا CSRF خاص، وداخل `@if ($canEdit)` (يحافظ على اختبار `AdminCustomerShowTest` السطر ~93: لا `form`/`select` لمن لا يملك التعديل). الاسم من الخادم داخل `<bdi>` بـ `{{ }}`؛ JavaScript لا يكتب نصًا ولا HTML.

```blade
<dialog class="admin-media-dialog" id="customer-block-dialog"
        aria-labelledby="customer-block-title" aria-describedby="customer-block-text">
    <div class="admin-media-dialog-header">
        <h2 class="admin-media-dialog-title" id="customer-block-title">{{ $custText('Customers_Block_Confirm_Title', 'Block this customer?') }}</h2>
    </div>
    <div class="admin-media-dialog-body">
        <p id="customer-block-text">{{ $custText('Customers_Block_Confirm_Text', 'The customer will not be able to sign in and will be signed out of their current session.') }}</p>
        <dl>
            <div><dt>{{ $custText('Customers_Column_Customer', 'Customer') }}</dt><dd><bdi>{{ $customer->name }}</bdi></dd></div>
            <div><dt>{{ $custText('Customers_Status_Label', 'New status') }}</dt><dd>{{ $statusLabel('blocked') }}</dd></div>
        </dl>
    </div>
    <div class="admin-media-dialog-footer">
        <button type="button" class="btn btn-light-secondary" data-customer-block-cancel autofocus>{{ $custText('Customers_Block_Cancel', 'Cancel') }}</button>
        <button type="button" class="btn btn-danger" data-customer-block-confirm>{{ $custText('Customers_Block_Confirm', 'Block customer') }}</button>
    </div>
</dialog>
```

### 8.3 السلوك المراجَع

| البند | السلوك | ملاحظة المراجعة |
|---|---|---|
| فتح | `submit` على النموذج → إن `needsBlockConfirmation` ولم يُؤكَّد: `preventDefault()` ثم `showModal()` | الفتح بفعل المستخدم، فيعمل Escape دون قيود المتصفح على النوافذ المفتوحة آليًا |
| الإلغاء | زر `type="button"` → `dialog.close()` | **لا PATCH**؛ القائمة تبقى على اختيار المشرف ليعدّل أو يعيد المحاولة |
| Escape | حدث `cancel` الأصلي → `close` | لا PATCH؛ لا يُمنع الحدث |
| النقر على الخلفية | `event.target === dialog` → `close()` | نفس نمط التصنيفات |
| التركيز عند الفتح | «إلغاء» بـ `autofocus` | الإلغاء آمن افتراضيًا (5.11)؛ `showModal()` يجعل ما خلف النافذة خاملًا |
| التركيز عند الإغلاق | يعود إلى زر «حفظ الحالة» **فقط عند الإلغاء** | عند التأكيد تنتقل الصفحة؛ لا يُعاد التركيز إلى زر سيتعطل |
| التأكيد | علامة `confirmed = true`، `close()`، `form.requestSubmit()` | `requestSubmit` يمر بالتحقق الأصلي (`required`) وبحارس الإرسال، بعكس `form.submit()` |
| منع الإرسال المكرر | أول `submit` مقبول يضع `data-sending="true"` و`aria-disabled="true"` على زري الحفظ والتأكيد؛ كل `submit` لاحق `preventDefault()` | لا `disabled` قبل الإرسال (قد يُسقط الزر من بيانات النموذج ومن ترتيب التركيز). الخادم يبقى الضمان الحقيقي (C-3) |
| الرجوع من الـ bfcache | `pageshow` مع `event.persisted` يعيد ضبط `confirmed` و`data-sending` و`aria-disabled` | بدونها تبقى الأزرار معطلة بعد زر «رجوع» |
| JavaScript غير متاح | النموذج يُرسل مباشرة بلا تأكيد؛ `<dialog>` المغلق غير مرئي | P-10 / OD-5 |

### 8.4 الجلسة الأخرى وسلامة البيانات

| السيناريو | ما تعرضه الصفحة | ما يحدث |
|---|---|---|
| مشرف آخر حظر العميل بعد فتح الصفحة؛ المشرف يختار `blocked` | `active` | تظهر نافذة غير ضرورية؛ الخادم يجد `blocked` → C-3، رسالة نجاح، لا سجل. **سليم** |
| مشرف آخر حظره؛ المشرف يختار `active` | `active` | لا نافذة؛ الخادم يرفع الحظر ويسجل `blocked→active`. **آخر كاتب يفوز** (P-9، OD-4) |
| مشرف آخر رفع الحظر؛ المشرف يختار `blocked` | `blocked` | **لا نافذة** (الصفحة تظن أنه محظور)؛ الخادم يحظر ويسجل. الحظر دون تأكيد ممكن في هذه الحالة النادرة |

النافذة قرار واجهة على بيانات وقت العرض؛ الحقيقة يحسمها الخادم داخل القفل. السجل يجعل كل سيناريو قابلًا للتتبع. الحالتان الأخيرتان تُعالجان كليًا فقط بـ OD-4 (`expected_status`).

### 8.5 اللغة والاتجاه وإمكانية الوصول

النصوص من `$custText()` بلا auto-create؛ الاتجاه من `<html dir>`؛ CSS `.admin-media-dialog` القائم بخصائص منطقية؛ الاسم في `<bdi>`؛ `aria-labelledby` و`aria-describedby`؛ أهداف `.btn` ≥ 24×24px؛ لا لون وحده. لا يُدّعى WCAG 2.2 AA قبل اختبار موثق.

### 8.6 التحميل

`@push('scripts') @vite('resources/js/admin-customer-show.js') @endpush` داخل `$canEdit`، وإضافة المدخل إلى `vite.config.js`.

---

## 9. D-3 — حالة `pending`

- تبقى `pending` في `CustomerManagementController::STATUSES` و`Customer::STATUSES` وenum `customers.status`.
- السلوك الحالي دون تعديل (T-7): رفض الدخول بـ `auth.pending`، والإخراج في الطلب التالي، والتسجيل العام ينشئ `active`.
- الانتقال منها أو إليها: لا نافذة، **ويُسجَّل**.
- **آلية التفعيل مؤجلة**: لا OTP، ولا موافقة تسجيل، ولا Workflow، ولا مسار تفعيل.

---

## 10. Translation Contract

### 10.1 القواعد

| # | القاعدة |
|---|---|
| TC-1 | مفاتيح خاصة بالعملاء فقط (`dashboard.Customers_*` و`dashboard.Customer_status_*`)، لا مفاتيح مشتركة (CUS-BE-004) |
| TC-2 | القراءة بلا auto-create: `$custText()` في الـ View و`untranslatedDefault()` في الـ Controller |
| TC-3 | الإضافة عبر `AdminCustomerTranslationSeeder` فقط (إدراج الناقص، لا يستبدل قيمة معدلة)، **عند التنفيذ لا الآن** |
| TC-4 | النص الإنجليزي في الـ Seeder = النص الافتراضي في الكود حرفيًا |
| TC-5 | JavaScript بلا نصوص |
| TC-6 | الصياغة متسقة مع النصوص المعتمدة القائمة: `Customers_Status_Hint`، `Category_Delete_*`، `Product_moderation_failed` |

### 10.2 المفاتيح الخمسة (مراجعة، لا تُضاف الآن)

| المفتاح | ar | en | مرجع الاتساق |
|---|---|---|---|
| `dashboard.Customers_Block_Confirm_Title` | حظر هذا العميل؟ | Block this customer? | `Category_Delete_Confirm_Title` («حذف هذا التصنيف؟») |
| `dashboard.Customers_Block_Confirm_Text` | لن يتمكن العميل من تسجيل الدخول، وسيُسجَّل خروجه من جلسته الحالية. | The customer will not be able to sign in and will be signed out of their current session. | `Customers_Status_Hint` (نفس الأثر بصيغة المستقبل) |
| `dashboard.Customers_Block_Confirm` | حظر العميل | Block customer | `Category_Delete_Confirm` («حذف التصنيف») |
| `dashboard.Customers_Block_Cancel` | إلغاء | Cancel | `Category_Delete_Cancel` («إلغاء») |
| `dashboard.Customer_status_failed` | تعذّر حفظ الحالة، فلم يتغير شيء. حاول مجددًا. | The status could not be saved, so nothing was changed. Please try again. | `Product_moderation_failed` («تعذّر حفظ التغيير، فلم يتغير شيء. حاول مجددًا.») |

**تعديلات عن v0.1:** `Confirm_Text` يسمّي «العميل» صراحة ويتبع صياغة التلميح المعتمد بدل «They will…»؛ `Customer_status_failed` بالعربية «حاول مجددًا» بدل «حاول مرة أخرى» اتساقًا مع المنتجات.

**مفاتيح معاد استخدامها (موجودة):** `Customers_Column_Customer`، `Customers_Status_Label`، `Customers_Status_Blocked`.

**الأثر:** عدد مفاتيح الـ Seeder 40 → 45، وتحديث `AdminCustomerTranslationSeederTest`. اعتماد الصياغات: OD-6.

---

## 11. Technical Risks

| # | الخطر | الاحتمال / الأثر | المعالجة |
|---|---|---|---|
| R-1 | `migrate:fresh` / `db:wipe` يمحوان السجل دون المرور بـ `down()` | منخفض / عالٍ | النسخ الاحتياطي، ومنع الأمرين على القواعد غير المحلية (تشغيلي)؛ ملاحظة نشر |
| R-2 | آخر كاتب يفوز: رفع حظر بناءً على صفحة قديمة | منخفض / متوسط | السجل يُظهره؛ الحل الكامل `expected_status` (OD-4) |
| R-3 | حظر دون نافذة حين تعرض الصفحة حالة قديمة `blocked` | منخفض جدًا / منخفض | السجل؛ OD-4 |
| R-4 | دون JavaScript لا تأكيد | منخفض / متوسط | ليس ضابط أمان؛ OD-5 |
| R-5 | Append-only على مستوى Eloquent فقط | منخفض / متوسط | موثق؛ لا مسار واجهة؛ مراجعة الكود |
| R-6 | التزامن غير مثبت على SQLite | مؤكد / متوسط | اختبارات MySQL المعزولة (M-1..M-6)، تحتاج OD-8 |
| R-7 | `attempts: 3` قد يطيل الانتظار حتى ~150 ثانية في أسوأ حالة | منخفض جدًا / منخفض | القفل جملتان؛ لا تغيير في `innodb_lock_wait_timeout` |
| R-8 | طلب عميل جارٍ قبل الحظر يكتمل | منخفض / منخفض | حد قائم (C-9)، لا يغيّره التصميم |
| R-9 | `string(20)` يقبل قيمة خاطئة باستعلام خام | منخفض / منخفض | حارس `creating`؛ بديل `enum` (القسم 3.3) |
| R-10 | Migration جديدة على dev/production | — | جدول جديد فقط؛ تنفيذ بموافقة |
| R-11 | `admin_name` لقطة قد تختلف عن اسم المشرف الحالي بعد تغييره | مؤكد / منخفض | مقصود: الاسم وقت التنفيذ |
| R-12 | حذف عميل مستقبلًا يحذف بياناته ويُبقي سجلًا مجهولًا | — | مقصود (القسم 6.3)؛ قرار يعاد عند إضافة حذف العملاء |

---

## 12. Proposed Implementation Phases

لا مرحلة تبدأ دون اعتماد هذه الوثيقة وقرارات القسم 13 الخاصة بها. كل مرحلة: تعديلات صغيرة، ثم الاختبارات المعنية، ثم المجموعة الكاملة، ثم تقرير، ثم توقف للاعتماد. لا Commit أو Push دون إذن.

| المرحلة | المحتوى | الملفات | الاختبارات | يعتمد على |
|---|---|---|---|---|
| **P0 — الاعتماد** | اعتماد v0.2 وOD-1..OD-7 | الوثيقة فقط | — | — |
| **P1 — السجل (D-2)** | Migration، Model، `Customer::STATUSES` و`statusChanges()`، `updateStatus()` بالعقد في القسم 5، مفتاح `Customer_status_failed` | `database/migrations/…create_customer_status_changes_table.php`، `app/Models/CustomerStatusChange.php`، `app/Models/Customer.php`، `CustomerManagementController.php`، `AdminCustomerTranslationSeeder.php` | `CustomerStatusChangeSchemaTest` (جديد)، `AdminCustomerStatusAuditTest` (جديد: الانتقالات، لا تغيير، التكرار، الفشل بـ Trigger في SQLite، الحذف، الصلاحيات، لا مسار تعديل)، تحديث `AdminCustomerTranslationSeederTest`، `AdminCustomerShowTest` كما هو | P0 |
| **P2 — النافذة (D-1)** | `data-*`، `<dialog>`، السكربت، `vite.config.js`، المفاتيح الأربعة | `show.blade.php`، `resources/js/admin-customer-show.js`، `vite.config.js`، `AdminCustomerTranslationSeeder.php` | `tests/js/admin-customer-show.test.mjs` (جديد)، `AdminCustomerShowScriptTest` (جديد)، إضافات `AdminCustomerShowTest`، `AdminCustomerTranslationSeederTest` | P0؛ مستقلة عن P1 تقنيًا |
| **P3 — MySQL المعزول** | M-1..M-6 | `tests/Support/customer-status-worker.php`، `tests/Feature/CustomerStatusConcurrencyMySqlTest.php` | تُتخطى دون القاعدة المعزولة | P1، OD-8 |
| **P4 — التحقق والنشر** | المجموعة الكاملة، تحقق يدوي (لوحة مفاتيح، قارئ شاشة، RTL/LTR، جوال، بلا JavaScript)، لقطات للـ PR | — | — | P1، P2 |

**خطوات النشر (بعد P4 وبموافقة):** `php artisan migrate` (جدول جديد فقط) ← `php artisan db:seed --class=AdminCustomerTranslationSeeder` (الناقص فقط) ← `npm run build`. لا `migrate:fresh` على أي قاعدة غير محلية (R-1).

**ترتيب P1 قبل P2:** صحة البيانات أولًا؛ النافذة تحسين واجهة لا تعتمد على السجل. يمكن عكسهما دون أثر تقني.

### 12.1 أثر التغييرات على الاختبارات الحالية

| الاختبار | الأثر |
|---|---|
| `AdminCustomerTranslationSeederTest::test_it_covers_exactly_the_40_approved_keys…` | يتغير: العدد والقائمة |
| `AdminCustomerShowTest` السطر ~93 | يبقى ناجحًا (النافذة داخل `$canEdit` وبلا `form`) |
| `AdminCustomerShowTest` السطور ~384 و~426 (إعادة إرسال نفس الحالة تتوقع النجاح) | تبقى ناجحة مع P-7 |
| اختبارات CUS-BE-003/004/006 | لا تغيير متوقع |
| `AccountStatusEnforcementTest`، `AuthRateLimitingTest`، `AdminCustomersIndexTest`، `ProductModerationSchemaTest` | لا أثر |

---

## 13. Remaining Owner Decisions

| # | القرار | الخيارات | التوصية |
|---|---|---|---|
| OD-1 | لقطة اسم المشرف المنفذ | (A) FK `nullOnDelete` + `admin_name` (B) مرجع فقط، والمنفذ يضيع عند الحذف (C) `restrictOnDelete`، يمنع حذف المشرف صاحب السجلات ويحتاج تعديل `AdminController` | **A** |
| OD-2 | لقطة اسم العميل | (A) لا لقطة، مرجع فقط (B) لقطة الاسم كما في v0.1 | **A** |
| OD-3 | مصير السجل إن أُضيف حذف العملاء مستقبلًا | (A) `nullOnDelete`، سجل مجهول (B) `cascadeOnDelete` | **A**؛ يُعاد النظر عند إضافة الحذف |
| OD-4 | التنافس بين جلستين | (A) آخر كاتب يفوز + السجل (B) حقل `expected_status` يرفض عند الاختلاف (تغيير سلوك Backend، ومفتاح رسالة جديد) | **A** للمرحلة الحالية |
| OD-5 | الحظر دون JavaScript | (A) بلا تأكيد، تحسين تدريجي (B) تأكيد من الخادم (يغيّر العقد) | **A** |
| OD-6 | صياغات المفاتيح الخمسة (القسم 10.2) | اعتماد / تعديل | اعتماد |
| OD-7 | مدة الاحتفاظ بالسجل | (A) غير محددة الآن (B) مدة محددة مع إجراء حذف موثق | **A** حتى تصدر سياسة احتفاظ |
| OD-8 | إنشاء قاعدة `hirfah_fk_test_*` على MySQL المحلي لتشغيل P3 (والاختبارات الـ30 المتخطاة) | موافقة / رفض | موافقة، قاعدة معزولة فقط |
| OD-9 | نوع أعمدة الحالة | (A) `string(20)` + الـ Model (B) `enum` | **A** |

**أُغلقت من v0.1 بالتوجهات المعتمدة:** Q-2 (T-2، T-8)، Q-3 (T-3، T-4)، Q-9 (T-6)، Q-10 (T-5). **أُعيد صياغتها هنا:** Q-1 → OD-5، Q-4 → P-7، Q-5 → OD-9، Q-6 → OD-1..OD-3 وOD-7، Q-7 → P-11، Q-8 → OD-6.

---

## 14. خارج النطاق

- سبب الحظر (D-X1).
- آلية تفعيل `pending` (D-3).
- واجهة قراءة السجل وقدرة `customers.audit` (T-2، T-8).
- إشعار العميل بالحظر (BR-024 يخص الطلبات والتوصيل والاعتمادات).
- منع النقر المكرر في بقية صفحات الإدارة، وتباين `btn-light-secondary` (S-1 في CUS-ACCEPT-01).
- تعديل `order_status_histories` أو `product_admin_actions` أو `AdminController`.
