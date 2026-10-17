# CUS-CLOSURE-01 — إغلاق مرحلة إدارة العملاء (MVP)

| البند | القيمة |
|---|---|
| Version | 1.0 |
| Status | CLOSED WITH DEFERRALS — Pending Owner Review |
| Project | HIRFAH |
| Scope | Admin Customers (`/dashboard/customers`) |
| Date | 2026-10-17 |
| References | CUS-ACCEPT-01 (تقرير الجلسة، غير محفوظ كملف) / [CUS-DESIGN-01.md](CUS-DESIGN-01.md) v0.2 (DEFERRED) / HIRFAH SRS v0.5 (Draft) / HIRFAH Architecture v0.1 (Draft) / AD-UI-01 v0.1 |

> وثيقة إغلاق فقط. لم يُعدَّل أي كود أو اختبار أو Seeder أو Migration في هذه الجولة، ولم يُكتب شيء على قاعدة البيانات، ولم يُنفَّذ Commit أو Push أو نشر.

---

## 1. قرارات المالك

| # | القرار | الأثر |
|---|---|---|
| 1 | تأجيل نافذة تأكيد الحظر | DEF-1 |
| 2 | تأجيل سجل تدقيق تغييرات الحالة | DEF-2 |
| 3 | تأجيل معالجة تعارض تعديلات المشرفين | DEF-3 |
| 4 | الإبقاء على `pending` بسلوكها الحالي، وتأجيل آلية التفعيل | DEF-4 |
| 5 | اعتماد الوظائف الحالية لإدارة العملاء ضمن نطاق MVP، استنادًا إلى CUS-ACCEPT-01 | هذه الوثيقة |

الميزات المؤجلة **ليست أخطاء مانعة** لإغلاق المرحلة، **ولا تُعد ملغاة**. تصميمها محفوظ في CUS-DESIGN-01 v0.2 بالحالة `DEFERRED — Future Enhancement`.

---

## 2. النطاق المنفذ

المتطلب المرجعي: **FR-ADM-005** (SRS v0.5، Medium): «الإشراف على حسابات العملاء المسجلين»، ضمن UC-ADM-004 وAC-ADM-004.

| الوظيفة | التنفيذ | الملفات |
|---|---|---|
| قائمة العملاء | جدول على الشاشات العريضة، وبطاقة لكل عميل تحت 1024px؛ الاسم والبريد والهاتف وعدد الطلبات والتقييمات وشارة الحالة؛ الأحدث أولًا | `resources/views/dashboard/customers/index.blade.php` |
| البحث | بالاسم أو البريد أو الهاتف؛ مجمّع مع فلتر الحالة؛ رموز `%` و`_` و`!` تُطابَق حرفيًا | `CustomerManagementController::index` |
| فلترة الحالة | `active` و`pending` و`blocked`؛ القيم غير المعروفة والمصفوفات تُتجاهل دون خطأ؛ «مسح الفلاتر» يظهر فقط لفلتر مطبَّق فعلًا | الـ Controller + `index.blade.php` |
| Pagination | 15 لكل صفحة، مع حفظ البحث والفلتر (`withQueryString`)، وعدد النتائج، وحالة صفحة بعد الأخيرة | `dashboard.partials.pagination` (مشترك) |
| تفاصيل العميل | بيانات التواصل، وآخر 10 طلبات (رابط الطلب لمن يملك `orders.view`)، والعناوين، وآخر 10 تقييمات | `resources/views/dashboard/customers/show.blade.php` |
| تغيير الحالة | `PATCH dashboard.customers.update-status`، حقل `status`، النموذج يظهر لمن يملك `customers.edit` فقط | الـ Controller + `show.blade.php` |
| الصلاحيات | العرض `customers.view`، والتعديل `customers.edit`؛ الخادم يتحقق دائمًا؛ الزائر يُوجَّه إلى الدخول، وJSON يُرجع 401، ومن لا يملك الصلاحية 403 | `authorizeAbility()` |
| Validation | `required` + القيم الثلاث؛ الخطأ أسفل الحقل مع `aria-invalid` و`aria-describedby`؛ اسم الحقل بلغة المشرف؛ الاحتفاظ بالاختيار بعد الفشل؛ 422 لـ JSON | الـ Controller + `show.blade.php` |
| Redirect | النجاح والفشل يعودان دائمًا إلى صفحة العميل عبر مسار مسمى، لا عبر الـ Referer | `updateStatus()` |
| الترجمة | 40 مفتاحًا خاصًا بالعملاء بالعربية والإنجليزية، تُقرأ دون إنشاء صفوف تلقائيًا؛ لا مفاتيح مشتركة للحالات | `AdminCustomerTranslationSeeder` |
| RTL/LTR | `lang` و`dir` من الخادم؛ قيم المستخدمين في `<bdi>` أو `dir` | الـ Layout + الـ Views |
| XSS / CSRF | كل القيم تُطبع escaped؛ نموذج التعديل يحمل `@csrf` و`@method('PATCH')`؛ المسار ضمن مجموعة `web` | الـ Views + `routes/dashboard.php` |
| حماية الحسابات المحظورة | `blocked` و`pending` لا يدخلان؛ الحساب يُخرج في طلبه التالي (سلوك قائم، لم يتغير) | `GuardLoginService`، `EnsureAccountIsActive` |
| إخفاء شريط الصفحة الفارغ | للصفحتين فقط، مع إبقاء أخطاء الـ Layout ظاهرة في صفحة التفاصيل | `resources/css/admin-dashboard.css` |

**غير ضمن النطاق:** حذف العملاء، والتعديل على بياناتهم، والإجراءات الجماعية، والتصدير، وسجل التدقيق، ونافذة التأكيد، وآلية تفعيل `pending`.

---

## 3. نتائج الاختبارات

| التشغيل | التاريخ | النتيجة |
|---|---|---|
| `AdminCustomersIndexTest` | 2026-10-17 (جولة الإغلاق) | **54 passed** (563 assertions) |
| `AdminCustomerShowTest` | 2026-10-17 (جولة الإغلاق) | **57 passed** (632 assertions) |
| `AdminCustomerTranslationSeederTest` | 2026-10-17 (جولة الإغلاق) | **11 passed** (259 assertions) |
| الملفات الثلاثة + `AccountStatusEnforcementTest` + `AuthRateLimitingTest` + `AdminDashboardSidebarTest` | 2026-10-17 (CUS-ACCEPT-01) | **193 passed** (2157 assertions)، 0 failed |
| المجموعة الكاملة `php artisan test` | 2026-10-17 (بعد CUS-UI-CLEANUP-01، آخر تعديل للكود) | **1260 passed، 30 skipped، 0 failed** (13087 assertions) |
| `LIKE … escape '!'` على MySQL 8.4.3 (تعابير ثابتة، دون جداول) | 2026-10-17 (CUS-ACCEPT-01) | **12/12** مطابقة لنتائج SQLite |

- **البيئة:** SQLite في الذاكرة (`phpunit.xml`)، لا قاعدة dev.
- **لم يتغير أي ملف كود أو اختبار** منذ تشغيل المجموعة الكاملة: إحصاء الملفات المتتبعة ما زال `7 files changed, 554 insertions(+), 57 deletions(-)`، والتغييرات اللاحقة وثائق فقط.
- **الاختبارات الـ30 المتخطاة** كلها تحتاج قاعدة MySQL معزولة (`CATEGORY_FK_MYSQL_DATABASE`): CategoryAuditFixes (4)، CategoryForeignKeyRestrict (5)، CategoryHierarchyConcurrency (4)، MediaDeletionConcurrency (13)، TranslationCorrectionCase (4). **لا يتعلق أي منها بإدارة العملاء.**

---

## 4. الإصلاحات المكتملة

| البند | المشكلة | الإصلاح | الاختبارات |
|---|---|---|---|
| CUS-BE-001 | البحث يتجاوز فلتر الحالة؛ رموز LIKE تعمل كأنماط | تجميع بدائل البحث؛ `LIKE … escape '!'` | 11 اختبارًا `cus_be_001_*` |
| CUS-BE-002 | مصفوفة أو حالة غير معروفة في Query String تسبب خطأ أو فلترة غير متوقعة | القراءة كنص فقط، والحالة من قائمة ثابتة | مصفوفات HTML وJSON، وقيم فارغة، وحالات غير معروفة |
| CUS-BE-003 | الـ Redirect بعد تغيير الحالة يتبع الـ Referer أو الجلسة | Redirect إلى مسار مسمى، و`redirectTo` عند فشل التحقق | Referer خارجي، و`_previous`، و422 للـ JSON |
| CUS-BE-004 | تسميات الحالة من مفاتيح مشتركة بمعانٍ أخرى، مع إنشاء صفوف تلقائيًا | مفاتيح خاصة بالعملاء دون إنشاء تلقائي | لا صفوف مكتوبة، والقيم المخصصة محفوظة |
| CUS-BE-006 | اسم الحقل في رسالة التحقق داخلي أو بالإنجليزية | اسم الحقل المترجم من `Customers_Status_Label` | HTML وJSON باللغتين |
| CUS-UI-05 / CUS-UI-06 / CUS-UI-06B | إعادة بناء القائمة والتفاصيل في إطار الإدارة | الـ Views والـ CSS؛ تصحيح فحص صفوف الترجمة (ومثله في `MediaLibraryAdminPageTest`) | ملفا اختبار الواجهتين |
| CUS-UI-CLEANUP-01 | «مسح الفلاتر» يظهر لـ `status=invalid`؛ تعليق قديم | `$filtered` يطابق قيم الـ Backend؛ تصحيح التعليق | `test_clear_filters_follows_the_filters_the_controller_applies` |

---

## 5. الميزات المؤجلة

| # | الميزة | المرجع | الحالة |
|---|---|---|---|
| DEF-1 | نافذة تأكيد قبل الحظر (ومعها منع الإرسال المكرر لنموذج الحالة، DEC-AD-UI-13) | CUS-DESIGN-01 §8، AD-UI-01 DEC-14 / 5.11 | Deferred — Future Enhancement |
| DEF-2 | سجل تدقيق تغييرات الحالة (`customer_status_changes`) مع Transaction وقفل الصف | CUS-DESIGN-01 §3–§7، Architecture 1.6.9 | Deferred — Future Enhancement |
| DEF-3 | معالجة تعارض تعديلات المشرفين (آخر كاتب يفوز حاليًا) | CUS-DESIGN-01 §5.3، OD-4 | Deferred — Future Enhancement |
| DEF-4 | آلية تفعيل `pending` (السلوك الحالي: رفض الدخول والإخراج من الجلسة) | CUS-DESIGN-01 §9 | Deferred — Future Enhancement |

**السلوك الحالي الموثق للمؤجل:**
- تغيير الحالة يتم دون Transaction أو قفل، ولا يحفظ أثرًا لمن غيّرها.
- حفظ حالة لم تتغير يُظهر رسالة النجاح.
- عند التنافس بين مشرفين يفوز آخر طلب.
- الحظر لا يطلب تأكيدًا.

هذا مقبول ضمن MVP بقرار المالك.

---

## 6. الملاحظات غير المانعة

| # | الملاحظة | الأولوية |
|---|---|---|
| N-1 | تعليق قديم في `AdminCustomerTranslationSeeder.php:80` يقول إن `updateStatus()` يقرأ الرسالة مع الإنشاء التلقائي، والكود الآن دون إنشاء تلقائي | منخفضة |
| N-2 | `withCount('addresses')` في `index()` غير مستخدم في الصفحة (استعلام فرعي زائد) | منخفضة |
| N-3 | خريطة الحالات مكررة في الـ Controller والـ Viewين؛ `$statuses` يُمرَّر إلى `index` ولا يُستخدم | تحسين |
| N-4 | مسار MySQL للبحث غير مغطى باختبار آلي (تحقق يدوي بتعابير ثابتة فقط) | تحسين |
| N-5 | DEC-AD-UI-13 (الحفظ المحمي) وDEC-AD-UI-16 (WCAG) جزئيان في نموذج الحالة | تحسين |
| S-1 | تباين `btn-light-secondary` (4.33:1) مستخدم في الصفحتين؛ مؤجل أصلًا (AD-UI-DASH-01 D-1) | مشترك، خارج النطاق |
| S-2 | تسميات `data/abilities.php` تُقرأ بـ `t()` مع الإنشاء التلقائي؛ تعالجه TR-INIT-01 | مشترك، خارج النطاق |
| S-3 | خلل في SRS v0.5: في مصفوفة التتبع 9.4 حلّ نص BR-015 محل صف FR-ADM-017 | وثيقة، خارج النطاق |

---

## 7. متطلبات القبول غير المستكملة

لم تُنفَّذ هذه التحققات، فلا يُدّعى أنها اجتازت. حالتها **NOT VERIFIED** (لا FAILED):

| # | المتطلب | المصدر | المطلوب |
|---|---|---|---|
| V-1 | لقطات عربية RTL وإنجليزية LTR للصفحتين: كمبيوتر، وتابلت (~820px)، وجوال (~390px) | AD-UI-01 §7.2، `AGENTS.md` (لقطات للـ PR) | لقطات موثقة |
| V-2 | التجاوب: البطاقات تحت 1024px، ولا تمرير أفقي للصفحة | DEC-AD-UI-15، NFR-017 (TBC) | فحص بصري (القاعدة موجودة في الـ CSS، والاختبارات تفحص البنية فقط) |
| V-3 | لوحة المفاتيح: ترتيب التركيز، والتركيز الظاهر، والنموذج والفلاتر | DEC-AD-UI-16 | فحص يدوي |
| V-4 | قارئ الشاشة: التسميات، و`caption`، والإعلان عن الأخطاء والرسائل | DEC-AD-UI-16 | فحص يدوي |
| V-5 | التباين، والتكبير حتى 200%، و`prefers-reduced-motion` | DEC-AD-UI-16 | فحص يدوي (S-1 معروف مسبقًا) |
| V-6 | رفض CSRF فعليًا (PHPUnit يعطّل الـ Middleware؛ الاختبارات تفحص وجود `_token` فقط) | NFR-007 | طلب PATCH يدوي دون رمز على بيئة محلية يجب أن يُرفض بـ 419 |
| V-7 | البحث عبر طلب كامل على MySQL (بيانات حقيقية) | CUS-BE-001 | فحص يدوي أو N-4 |

هذه التحققات **لا تمنع الـ Commit**، لكنها مطلوبة قبل اعتماد النشر (القسم 11).

---

## 8. `AdminCustomerTranslationSeeder` — الحالة وطريقة التشغيل

### 8.1 الحالة

| البند | القيمة |
|---|---|
| المفاتيح | 40 مفتاحًا × لغتان = 80 صفًا (`dashboard.Customers_*`، `dashboard.Orders_Status_*`، `dashboard.Customer_status_updated`) |
| الإدراج | `insertOrIgnore` على `unique(key, locale)`: يُدرج الناقص فقط، ولا يستبدل قيمة معدلة |
| التصحيح | صف واحد محروس: `Customer_status_updated` بالعربية، فقط إن كان لا يزال يحمل النص الإنجليزي القديم حرفيًا (مقارنة في PHP، لا عبر Collation) |
| الذاكرة المؤقتة | يمسح `translation.{locale}.{key}` لكل صف أُدرج أو صُحّح |
| الاستدعاء | **غير مستدعى من `DatabaseSeeder`**، و`DatabaseSeeder` فارغ أصلًا؛ مختبر في `test_not_called_from_database_seeder` |
| التكرار | تشغيله مرتين لا يغيّر شيئًا (`test_running_twice_changes_nothing`) |
| الاختبارات | 11 passed (القسم 3) |
| حالة dev (قراءة فقط، 2026-10-17) | 80/80 صفًا موجودة، والرسالة العربية صحيحة؛ لم يُشغَّل في هذه الجولة |
| الملف | غير متتبع في git حتى الآن (`??`) |

### 8.2 مفاتيح معاد استخدامها تعتمد عليها الصفحتان

| المفتاح | الـ Seeder الذي يوفّره | حالة dev (قراءة فقط) |
|---|---|---|
| `Nav_Customers` | `AdminDashboardTranslationSeeder` | ar وen موجودان |
| `Orders`، `Actions`، `City` | `AdminHomeTranslationSeeder` | موجودة |
| `Pagination_Label/Next/Page/Previous/Summary` | `AdminCategoryTranslationSeeder` | موجودة |
| `Reviews` | `AdminAbilityTranslationSeeder`، **يُدرجه على قاعدة جديدة فقط مع تغيير TR-INIT-01** (قبله كان يصحّحه فقط) | موجود |

**تبعية نشر:** على بيئة جديدة، تسمية «التقييمات» في صفحتي العملاء تعتمد على نشر TR-INIT-01 وتشغيل `AdminAbilityTranslationSeeder`. دونها تظهر «Reviews» بالإنجليزية في الواجهة العربية، لأن الصفحتين تقرآن دون إنشاء تلقائي. هذه تبعية نشر وليست تبعية Commit: اختبارات العملاء تنشئ الصف بنفسها.

### 8.3 طريقة التشغيل في بيئات النشر (لا تُنفَّذ الآن)

بعد نشر الكود، وبعد نسخة احتياطية من `translation_values`، وبموافقة المالك:

```bash
php artisan db:seed --class=AdminDashboardTranslationSeeder --force
php artisan db:seed --class=AdminHomeTranslationSeeder --force
php artisan db:seed --class=AdminCategoryTranslationSeeder --force
php artisan db:seed --class=AdminAbilityTranslationSeeder --force
php artisan db:seed --class=AdminCustomerTranslationSeeder --force
```

- `--force` مطلوب في بيئة `production`، لأن Laravel يطلب تأكيدًا تفاعليًا دونه.
- كل الـ Seeders المذكورة تُدرج الناقص فقط، فتشغيلها على بيئة فيها الصفوف آمن ولا يغيّر شيئًا.
- **لا تُستخدم** `php artisan db:seed` دون `--class`، لأن `DatabaseSeeder` فارغ ولا يشغّل شيئًا. **ولا تُستخدم** `migrate:fresh --seed` على أي قاعدة غير محلية.
- لا Migration مطلوبة لهذه المرحلة: إدارة العملاء لم تُضف أو تغيّر أي جدول.

---

## 9. الملفات

### 9.1 المعدّلة (متتبعة)

| الملف | المجموعة |
|---|---|
| `app/Http/Controllers/Dashboard/CustomerManagementController.php` | A — العملاء |
| `resources/views/dashboard/customers/index.blade.php` | A |
| `resources/views/dashboard/customers/show.blade.php` | A |
| `resources/css/admin-dashboard.css` | A |
| `tests/Feature/MediaLibraryAdminPageTest.php` | A (CUS-UI-06B: نفس تصحيح فحص صفوف الترجمة) |
| `database/seeders/AdminAbilityTranslationSeeder.php` | **B — TR-INIT-01** |
| `tests/Feature/AdminAbilityTranslationSeederTest.php` | **B — TR-INIT-01** |

### 9.2 غير المتتبعة

| الملف | المجموعة |
|---|---|
| `database/seeders/AdminCustomerTranslationSeeder.php` | A |
| `tests/Feature/AdminCustomersIndexTest.php` | A |
| `tests/Feature/AdminCustomerShowTest.php` | A |
| `tests/Feature/AdminCustomerTranslationSeederTest.php` | A |
| `docs/reviews/CUS-DESIGN-01.md` | C — الوثائق |
| `docs/reviews/CUS-CLOSURE-01.md` | C |

لا ملفات أخرى معدلة أو غير متتبعة. لا Migrations ولا Routes ولا `.env` ضمن التغييرات.

---

## 10. خطة Commits المقترحة

على `main` مباشرة، أو على فرع مستقل (`feature/admin-customers`) ثم PR، بحسب قرار المالك. قبل كل Commit: `./vendor/bin/pint --test` على ملفات PHP المعنية، و`git diff --check`، والاختبارات المذكورة.

| # | المحتوى | الملفات | العنوان المقترح | الاختبارات قبل الـ Commit |
|---|---|---|---|---|
| 1 | TR-INIT-01 | المجموعة B (ملفان) | `Initialize ability label translations on new databases` | `AdminAbilityTranslationSeederTest` |
| 2 | إدارة العملاء MVP | المجموعة A (9 ملفات) | `Rebuild admin customers list and details pages` | الملفات الثلاثة للعملاء + `MediaLibraryAdminPageTest` + `AccountStatusEnforcementTest` |
| 3 | الوثائق | المجموعة C (ملفان) | `Document admin customers closure and deferred design` | — |

- **فصل TR-INIT-01:** Commit 1 لا يحتوي أي ملف عملاء، وCommit 2 لا يحتوي ملفات TR-INIT-01. الترتيب 1 ثم 2 يوافق تبعية النشر في 8.2، وأي ترتيب آخر لا يكسر الاختبارات.
- **بعد Commit 2** تُشغَّل المجموعة الكاملة `php artisan test` للتأكد من النتيجة نفسها (1260 / 30 / 0).
- **وصف الـ PR** (إن وُجد) يذكر: الغرض، وأوامر الاختبار، و**لا Migrations**، و**Seeders جديدة غير مستدعاة من `DatabaseSeeder`** (القسم 8.3)، واللقطات (V-1)، والمؤجل (القسم 5).
- **إن رغب المالك في Commit واحد** بدل 2 و3: يبقى TR-INIT-01 منفصلًا على الأقل.

---

## 11. الحكم النهائي

| الجانب | الحكم | السبب |
|---|---|---|
| **إغلاق المرحلة (MVP)** | **CLOSED WITH DEFERRALS** | FR-ADM-005 منفذ؛ الإصلاحات الست مغلقة؛ لا Release Blockers؛ المؤجل بقرار المالك (القسم 5) |
| **جاهزية الكود للـ Commit** | **READY** | الاختبارات ناجحة؛ `git diff --check` نظيف؛ النطاق محدد؛ بشرط فصل TR-INIT-01 (القسم 10) |
| **جاهزية النشر** | **NOT YET READY** | يتطلب: (1) V-1..V-6 على الأقل، ولقطات للـ PR؛ (2) نسخة احتياطية ثم الـ Seeders الخمسة في البيئة المستهدفة (القسم 8.3)؛ (3) `npm run build` لأن `admin-dashboard.css` تغيّر؛ (4) موافقة المالك على النشر |

الملاحظات N-1..N-5 لا تمنع الـ Commit ولا النشر.
