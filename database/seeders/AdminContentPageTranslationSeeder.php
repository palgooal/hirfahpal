<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * PAGES-03A: the Arabic and English texts of the content pages in the dashboard (list, edit form, validation, the
 * preview bar). Read without auto-creating rows. The ability labels (content-pages.*) are in
 * AdminAbilityTranslationSeeder. Only missing (key, locale) rows are inserted; edited values are kept; nothing is
 * updated or deleted. Not called from DatabaseSeeder.
 */
class AdminContentPageTranslationSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    public const TRANSLATIONS = [
        'dashboard.Nav_Content_Pages' => ['ar' => 'صفحات المحتوى', 'en' => 'Content pages'],
        'dashboard.Content_Pages_List' => ['ar' => 'قائمة الصفحات', 'en' => 'Pages list'],
        'dashboard.Content_Pages_Status_Published' => ['ar' => 'منشورة', 'en' => 'Published'],
        'dashboard.Content_Pages_Status_Draft' => ['ar' => 'مسودة', 'en' => 'Draft'],
        'dashboard.Content_Pages_System' => ['ar' => 'صفحة نظام', 'en' => 'System page'],
        'dashboard.Content_Pages_Updated' => ['ar' => 'آخر تحديث: :date', 'en' => 'Last updated: :date'],
        'dashboard.Content_Pages_Edit' => ['ar' => 'تعديل', 'en' => 'Edit'],
        'dashboard.Content_Pages_View' => ['ar' => 'عرض', 'en' => 'View'],
        'dashboard.Content_Pages_Edit_Page' => ['ar' => 'تعديل :title', 'en' => 'Edit :title'],
        'dashboard.Content_Pages_View_Page' => ['ar' => 'عرض :title', 'en' => 'View :title'],
        'dashboard.Content_Pages_Opens_New_Tab' => ['ar' => 'يُفتح في نافذة جديدة', 'en' => 'opens in a new tab'],
        'dashboard.Content_Pages_Back' => ['ar' => 'العودة إلى الصفحات', 'en' => 'Back to pages'],
        'dashboard.Content_Pages_Section_Page' => ['ar' => 'الصفحة', 'en' => 'Page'],
        'dashboard.Content_Pages_Field_Slug' => ['ar' => 'الرابط', 'en' => 'Address'],
        'dashboard.Content_Pages_Slug_Locked' => ['ar' => 'رابط صفحة النظام ثابت ولا يتغير.', 'en' => 'A system page keeps its address.'],
        'dashboard.Content_Pages_Field_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Content_Pages_System_Status_Note' => ['ar' => 'صفحات النظام تبقى منشورة، فالروابط في المتجر تؤدي إليها.', 'en' => 'System pages stay published: links in the store lead to them.'],
        'dashboard.Content_Pages_Field_Title' => ['ar' => 'عنوان الصفحة', 'en' => 'Page title'],
        'dashboard.Content_Pages_Field_Excerpt' => ['ar' => 'الملخص', 'en' => 'Summary'],
        'dashboard.Content_Pages_Field_Meta_Title' => ['ar' => 'عنوان محركات البحث', 'en' => 'SEO title'],
        'dashboard.Content_Pages_Field_Meta_Description' => ['ar' => 'وصف محركات البحث', 'en' => 'SEO description'],
        'dashboard.Content_Pages_Meta_Hint' => ['ar' => 'يظهر في نتائج البحث. إن تُرك فارغًا يُستخدم العنوان والملخص. يُفضّل نحو 60 حرفًا للعنوان و160 للوصف.', 'en' => 'Shown in search results. When empty, the title and summary are used. About 60 characters for the title and 160 for the description work best.'],
        'dashboard.Content_Pages_Field_Body' => ['ar' => 'محتوى الصفحة', 'en' => 'Page content'],
        'dashboard.Content_Pages_Body_Readonly_View' => ['ar' => 'يمكنك عرض هذا المحتوى دون تغييره.', 'en' => 'You can view this content but not change it.'],
        'dashboard.Content_Pages_Body_Empty' => ['ar' => 'لا يوجد محتوى بهذه اللغة بعد.', 'en' => 'No content in this language yet.'],
        'dashboard.Content_Pages_Save' => ['ar' => 'حفظ الصفحة', 'en' => 'Save page'],
        'dashboard.Content_Pages_Saved' => ['ar' => 'حُفظت الصفحة.', 'en' => 'The page was saved.'],
        'dashboard.Content_Pages_Form_Errors' => ['ar' => 'يرجى تصحيح ما يلي:', 'en' => 'Please correct the following:'],
        'dashboard.Content_Pages_System_Published' => ['ar' => 'صفحة النظام تبقى منشورة.', 'en' => 'A system page stays published.'],
        // PAGES-05B: the source language and every other language by name (the registry), instead of Arabic / English.
        'dashboard.Content_Pages_Source_Body_Required' => ['ar' => 'تحتاج النسخة (:language) إلى محتوى.', 'en' => 'The :language page needs some content.'],
        'dashboard.Content_Pages_Language_Title_Required' => ['ar' => 'للنسخة (:language) محتوى، فهي تحتاج إلى عنوان.', 'en' => 'The :language page has content, so it needs a title.'],
        'dashboard.Content_Pages_Language_Unsupported' => ['ar' => 'أُرسل محتوى للغة غير مفعّلة.', 'en' => 'Content was sent for a language that is not enabled.'],
        // PAGES-03B: the visual editor (toolbar, dialogs, states) and the language tabs.
        'dashboard.Content_Editor_Toolbar' => ['ar' => 'أدوات التنسيق', 'en' => 'Formatting'],
        'dashboard.Content_Editor_Table_Toolbar' => ['ar' => 'الجدول', 'en' => 'Table'],
        'dashboard.Content_Editor_Paragraph' => ['ar' => 'فقرة', 'en' => 'Paragraph'],
        'dashboard.Content_Editor_Heading_2' => ['ar' => 'عنوان', 'en' => 'Heading'],
        'dashboard.Content_Editor_Heading_3' => ['ar' => 'عنوان فرعي', 'en' => 'Subheading'],
        'dashboard.Content_Editor_Heading_4' => ['ar' => 'عنوان صغير', 'en' => 'Small heading'],
        'dashboard.Content_Editor_Bold' => ['ar' => 'غامق', 'en' => 'Bold'],
        'dashboard.Content_Editor_Italic' => ['ar' => 'مائل', 'en' => 'Italic'],
        'dashboard.Content_Editor_Underline' => ['ar' => 'تسطير', 'en' => 'Underline'],
        'dashboard.Content_Editor_Strike' => ['ar' => 'يتوسطه خط', 'en' => 'Strikethrough'],
        'dashboard.Content_Editor_Bullet_List' => ['ar' => 'قائمة نقطية', 'en' => 'Bulleted list'],
        'dashboard.Content_Editor_Numbered_List' => ['ar' => 'قائمة مرقمة', 'en' => 'Numbered list'],
        'dashboard.Content_Editor_Quote' => ['ar' => 'اقتباس', 'en' => 'Quote'],
        'dashboard.Content_Editor_Divider' => ['ar' => 'خط فاصل', 'en' => 'Divider'],
        'dashboard.Content_Editor_Link' => ['ar' => 'رابط', 'en' => 'Link'],
        'dashboard.Content_Editor_Unlink' => ['ar' => 'إزالة الرابط', 'en' => 'Remove link'],
        'dashboard.Content_Editor_Table' => ['ar' => 'إدراج جدول', 'en' => 'Insert table'],
        'dashboard.Content_Editor_Add_Row' => ['ar' => 'إضافة صف', 'en' => 'Add row'],
        'dashboard.Content_Editor_Delete_Row' => ['ar' => 'حذف الصف', 'en' => 'Delete row'],
        'dashboard.Content_Editor_Add_Column' => ['ar' => 'إضافة عمود', 'en' => 'Add column'],
        'dashboard.Content_Editor_Delete_Column' => ['ar' => 'حذف العمود', 'en' => 'Delete column'],
        'dashboard.Content_Editor_Delete_Table' => ['ar' => 'حذف الجدول', 'en' => 'Delete table'],
        'dashboard.Content_Editor_Image' => ['ar' => 'صورة', 'en' => 'Image'],
        'dashboard.Content_Editor_Undo' => ['ar' => 'تراجع', 'en' => 'Undo'],
        'dashboard.Content_Editor_Redo' => ['ar' => 'إعادة', 'en' => 'Redo'],
        'dashboard.Content_Editor_Ready' => ['ar' => 'المحرر جاهز.', 'en' => 'The editor is ready.'],
        'dashboard.Content_Editor_Failed' => ['ar' => 'تعذّر تشغيل المحرر. يُعرض المحتوى المحفوظ وسيبقى كما هو عند الحفظ.', 'en' => 'The editor could not start. The saved content is shown and will be kept when you save.'],
        'dashboard.Content_Editor_Image_Refused' => ['ar' => 'يمكن إدراج صور مكتبة الوسائط فقط.', 'en' => 'Only images from the media library can be inserted.'],
        'dashboard.Content_Editor_Languages' => ['ar' => 'اللغات', 'en' => 'Languages'],
        'dashboard.Content_Editor_Has_Errors' => ['ar' => 'يحتوي على أخطاء', 'en' => 'Contains errors'],
        'dashboard.Content_Editor_Loading' => ['ar' => 'جارٍ تحميل المحرر…', 'en' => 'Loading the editor…'],
        'dashboard.Content_Editor_Hint' => ['ar' => 'مفتاحا Alt+F10 ينقلانك إلى أدوات التنسيق، ومفتاح Escape يعيدك إلى النص.', 'en' => 'Alt+F10 moves to the formatting tools; Escape returns to the text.'],
        'dashboard.Content_Editor_Link_Title' => ['ar' => 'رابط', 'en' => 'Link'],
        'dashboard.Content_Editor_Link_Url' => ['ar' => 'العنوان', 'en' => 'Address'],
        'dashboard.Content_Editor_Link_Hint' => ['ar' => 'يبدأ بـ https:// أو mailto: أو tel:، أو صفحة من الموقع تبدأ بـ /.', 'en' => 'https://…, mailto:…, tel:…, or a page of this site starting with /.'],
        'dashboard.Content_Editor_Link_Invalid' => ['ar' => 'أدخل عنوانًا كاملًا يبدأ بـ https:// أو mailto: أو tel:، أو صفحة من الموقع تبدأ بـ /.', 'en' => 'Enter a full address starting with https://, mailto: or tel:, or a page of this site starting with /.'],
        'dashboard.Content_Editor_Link_Apply' => ['ar' => 'تطبيق', 'en' => 'Apply'],
        'dashboard.Content_Editor_Cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],
        'dashboard.Content_Editor_Image_Title' => ['ar' => 'وصف الصورة', 'en' => 'Image description'],
        'dashboard.Content_Editor_Image_Alt' => ['ar' => 'النص البديل', 'en' => 'Alternative text'],
        'dashboard.Content_Editor_Image_Hint' => ['ar' => 'صف ما تُظهره الصورة لمن لا يستطيع رؤيتها.', 'en' => 'Describe what the image shows, for visitors who cannot see it.'],
        'dashboard.Content_Editor_Image_Alt_Required' => ['ar' => 'صف الصورة قبل إدراجها.', 'en' => 'Describe the image before inserting it.'],
        'dashboard.Content_Editor_Image_Insert' => ['ar' => 'إدراج الصورة', 'en' => 'Insert image'],
        'dashboard.Content_Editor_Image_Update' => ['ar' => 'حفظ الوصف', 'en' => 'Save description'],
        'dashboard.Content_Pages_Preview_Title' => ['ar' => 'معاينة الصفحة', 'en' => 'Page preview'],
        'dashboard.Content_Pages_Preview_Published' => ['ar' => 'الصفحة كما حُفظت، كما يراها الزوار.', 'en' => 'The saved page, as visitors see it.'],
        'dashboard.Content_Pages_Preview_Draft' => ['ar' => 'الصفحة كما حُفظت. هي مسودة لا يراها الزوار.', 'en' => 'The saved page. It is a draft: visitors cannot see it.'],
        'dashboard.Content_Pages_Preview_Back' => ['ar' => 'العودة إلى التعديل', 'en' => 'Back to editing'],
        // PAGES-04B: creating a custom page.
        'dashboard.Content_Pages_Create' => ['ar' => 'صفحة جديدة', 'en' => 'New page'],
        'dashboard.Content_Pages_Created' => ['ar' => 'أُنشئت الصفحة.', 'en' => 'The page was created.'],
        'dashboard.Content_Pages_Slug_Hint' => ['ar' => 'ستكون الصفحة على /pages/ يليه هذا الرابط. حروف إنجليزية صغيرة وأرقام وشرطات مفردة، مثل our-story. لا يمكن تغييره بعد نشر الصفحة.', 'en' => 'The page will be at /pages/ followed by this address. Lowercase English letters, digits and single hyphens, for example our-story. It can no longer be changed once the page is published.'],
        'dashboard.Content_Pages_Slug_Format' => ['ar' => 'استخدم حروفاً إنجليزية صغيرة وأرقاماً وشرطات مفردة، مثل our-story.', 'en' => 'Use lowercase English letters, digits and single hyphens, for example our-story.'],
        'dashboard.Content_Pages_Slug_Reserved' => ['ar' => 'هذا الرابط محجوز. اختر رابطاً آخر.', 'en' => 'This address is reserved. Choose another one.'],
        'dashboard.Content_Pages_Slug_Taken' => ['ar' => 'هذا الرابط مستخدم لصفحة أخرى.', 'en' => 'Another page already uses this address.'],
        // PAGES-04C: the slug lock, page kinds, copying the link and deleting a draft.
        'dashboard.Content_Pages_Slug_Locked_Error' => ['ar' => 'لم يعد ممكناً تغيير هذا الرابط: الصفحة صفحة نظام أو سبق نشرها.', 'en' => 'This address can no longer be changed: the page is a system page or has been published.'],
        'dashboard.Content_Pages_Slug_Locked_Published' => ['ar' => 'الرابط مقفل: سبق نشر الصفحة، فيجب أن تبقى الروابط إليها صالحة.', 'en' => 'The address is locked: the page has been published, so links to it must keep working.'],
        'dashboard.Content_Pages_Custom' => ['ar' => 'صفحة مخصصة', 'en' => 'Custom page'],
        'dashboard.Content_Pages_Copy_Link' => ['ar' => 'نسخ الرابط', 'en' => 'Copy link'],
        'dashboard.Content_Pages_Link_Copied' => ['ar' => 'نُسخ الرابط.', 'en' => 'The link was copied.'],
        'dashboard.Content_Pages_Link_Copy_Failed' => ['ar' => 'تعذّر نسخ الرابط. العنوان هو :url', 'en' => 'The link could not be copied. The address is :url'],
        'dashboard.Content_Pages_Delete' => ['ar' => 'حذف', 'en' => 'Delete'],
        'dashboard.Content_Pages_Delete_Title' => ['ar' => 'حذف هذه الصفحة؟', 'en' => 'Delete this page?'],
        'dashboard.Content_Pages_Deleted' => ['ar' => 'حُذفت الصفحة. لم تعد الروابط إلى عنوانها تعمل.', 'en' => 'The page was deleted. Links to its address no longer work.'],
        // PAGES-04E: the list's single preview, in the dashboard's language.
        'dashboard.Content_Pages_Preview' => ['ar' => 'معاينة', 'en' => 'Preview'],
        'dashboard.Content_Pages_Delete_Not_Draft' => ['ar' => 'لا تُحذف إلا الصفحة المسودة. أعد الصفحة إلى مسودة أولاً.', 'en' => 'Only a draft page can be deleted. Return the page to draft first.'],
        // PAGES-05C: every language of the registry, named by the registry, instead of Arabic / English.
        'dashboard.Content_Pages_Intro_Languages' => ['ar' => 'صفحات المعلومات في المتجر بكل لغة مفعّلة: عناوينها وملخصاتها ومحتواها ونصوص محركات البحث.', 'en' => 'The information pages of the store, in each enabled language: their titles, summaries, content and search engine texts.'],
        'dashboard.Content_Pages_Translations' => ['ar' => 'الترجمات', 'en' => 'Translations'],
        'dashboard.Content_Pages_Translation_Complete' => ['ar' => 'مكتملة', 'en' => 'Complete'],
        'dashboard.Content_Pages_Translation_Incomplete' => ['ar' => 'غير مكتملة', 'en' => 'Incomplete'],
        'dashboard.Content_Pages_Translation_Missing' => ['ar' => 'غير موجودة', 'en' => 'Missing'],
        'dashboard.Content_Pages_Source_Hint' => ['ar' => 'مطلوبة: عنوان ومحتوى. يرى الزوار هذه اللغة حين لا تكون لغتهم مكتملة.', 'en' => 'Required: a title and content. Visitors see this language when theirs is not complete.'],
        'dashboard.Content_Pages_Translation_Hint' => ['ar' => 'اختيارية. إلى أن يُملأ عنوانها ومحتواها معاً، يرى الزوار صفحة :source.', 'en' => 'Optional. Until its title and content are both filled in, visitors see the :source page.'],
        'dashboard.Content_Pages_Preview_In' => ['ar' => 'معاينة بلغة :language', 'en' => 'Preview in :language'],
        'dashboard.Content_Pages_Preview_Fallback_Missing' => ['ar' => 'لا توجد نسخة :language بعد، فتُعرض نسخة :source.', 'en' => 'There is no :language version yet, so the :source one is shown.'],
        'dashboard.Content_Pages_Preview_Fallback_Incomplete' => ['ar' => 'نسخة :language غير مكتملة بعد (تحتاج إلى عنوان ومحتوى)، فتُعرض نسخة :source.', 'en' => 'The :language version is not complete yet (it needs a title and content), so the :source one is shown.'],
        'dashboard.Content_Pages_Delete_Confirm_All' => ['ar' => 'ستُحذف «:title» مع كل ترجماتها. ستتوقف الروابط إلى :address عن العمل. لا يمكن التراجع عن ذلك.', 'en' => '“:title” will be deleted with all its translations. Links to :address will stop working. This cannot be undone.'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::TRANSLATIONS as $key => $values) {
            foreach ($values as $locale => $value) {
                // insertOrIgnore relies on unique(key, locale): an existing row is never overwritten.
                $inserted = TranslationValue::query()->insertOrIgnore([
                    'key' => $key, 'locale' => $locale, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
                ]);

                if ($inserted > 0) {
                    cache()->forget("translation.{$locale}.{$key}");
                }
            }
        }
    }
}
