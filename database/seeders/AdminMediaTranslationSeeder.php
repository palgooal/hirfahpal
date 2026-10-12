<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English messages of the media library protections: uploads refused by
 * App\Http\Middleware\EnsureSafeMediaUpload (AD-UI-CAT-M2-A) and safe deletion by
 * App\Http\Controllers\Dashboard\MediaLibraryController (AD-UI-CAT-M2-C). Both read them without
 * auto-creating rows, so until this seeder runs the English defaults are shown and nothing is written.
 * Also the strings of the admin library page (AD-UI-CAT-M3), read through t() like the rest of the admin;
 * the sidebar label (dashboard.Nav_Media_Library) is with the other sidebar strings in
 * AdminDashboardTranslationSeeder.
 * Only missing (key, locale) rows are inserted; edited values are kept. Not called from DatabaseSeeder.
 */
class AdminMediaTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        'dashboard.Media_in_use' => [
            'ar' => 'لا يمكن حذف هذا الوسيط لأنه مستخدم. أزله من أماكن استخدامه أولًا.',
            'en' => 'This media is used and cannot be deleted. Remove it from where it is used first.',
        ],
        'dashboard.Media_deleted' => [
            'ar' => 'تم حذف الوسيط.',
            'en' => 'The media item was deleted.',
        ],
        'dashboard.Media_bulk_deleted' => [
            'ar' => 'تم حذف الوسائط المحددة.',
            'en' => 'The selected media items were deleted.',
        ],

        // Upload protection (AD-UI-CAT-M2-A).
        'dashboard.Media_invalid_image' => [
            'ar' => 'يجب أن يكون الملف صورة JPG أو PNG أو GIF أو WebP، وأن يطابق امتداده محتواه.',
            'en' => 'The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.',
        ],
        'dashboard.Media_image_too_large' => [
            'ar' => 'أبعاد الصورة كبيرة جدًا.',
            'en' => 'The image dimensions are too large.',
        ],

        // Ability names of the media.* group (data/abilities.php), shown in the admins form (AD-UI-CAT-M3-FIX).
        'dashboard.Media_Library' => [
            'ar' => 'مكتبة الوسائط',
            'en' => 'Media Library',
        ],
        'dashboard.View_Media' => [
            'ar' => 'عرض مكتبة الوسائط',
            'en' => 'View Media Library',
        ],
        'dashboard.Upload_Media' => [
            'ar' => 'رفع الوسائط',
            'en' => 'Upload Media',
        ],
        'dashboard.Edit_Media' => [
            'ar' => 'تعديل بيانات الوسائط',
            'en' => 'Edit Media Details',
        ],
        'dashboard.Delete_Media' => [
            'ar' => 'حذف الوسائط',
            'en' => 'Delete Media',
        ],

        // Admin library page (AD-UI-CAT-M3): resources/views/vendor/media-library/media.blade.php and the
        // messages it hands to resources/js/admin-media-library.js. Placeholders (:count, :name, :names, :size)
        // are kept in every language.
        'dashboard.Media_Intro' => [
            'ar' => 'ارفع الصور المستخدمة في لوحة الإدارة والمتجر وأدِرها.',
            'en' => 'Upload and manage the images used in the admin and the store.',
        ],
        'dashboard.Media_Upload' => [
            'ar' => 'رفع صور',
            'en' => 'Upload images',
        ],
        'dashboard.Media_Dropzone' => [
            'ar' => 'أفلت الصور هنا لرفعها',
            'en' => 'Drop images here to upload them',
        ],
        'dashboard.Media_Upload_Hint' => [
            'ar' => 'صور JPG أو PNG أو GIF أو WebP، بحد أقصى :size ميغابايت لكل صورة.',
            'en' => 'JPG, PNG, GIF or WebP, up to :size MB each.',
        ],
        'dashboard.Media_Search_Label' => [
            'ar' => 'البحث في الصور',
            'en' => 'Search images',
        ],
        'dashboard.Media_Search_Placeholder' => [
            'ar' => 'ابحث بالاسم أو العنوان أو التسمية التوضيحية',
            'en' => 'Search by name, title or caption',
        ],
        'dashboard.Media_Search_Clear' => [
            'ar' => 'مسح البحث',
            'en' => 'Clear search',
        ],
        'dashboard.Media_Count' => [
            'ar' => 'عدد الصور: :count',
            'en' => 'Images: :count',
        ],
        'dashboard.Media_Grid_Label' => [
            'ar' => 'الصور',
            'en' => 'Images',
        ],
        'dashboard.Media_Loading' => [
            'ar' => 'جارٍ تحميل الصور…',
            'en' => 'Loading images…',
        ],
        'dashboard.Media_Empty' => [
            'ar' => 'لا توجد صور بعد.',
            'en' => 'No images yet.',
        ],
        'dashboard.Media_Empty_Search' => [
            'ar' => 'لا توجد صور تطابق البحث.',
            'en' => 'No images match your search.',
        ],
        'dashboard.Media_Load_Error' => [
            'ar' => 'تعذّر تحميل الصور.',
            'en' => 'The images could not be loaded.',
        ],
        'dashboard.Media_Retry' => [
            'ar' => 'إعادة المحاولة',
            'en' => 'Try again',
        ],
        'dashboard.Media_Load_More' => [
            'ar' => 'تحميل المزيد',
            'en' => 'Load more',
        ],
        'dashboard.Media_Select_Item' => [
            'ar' => 'تحديد :name',
            'en' => 'Select :name',
        ],
        'dashboard.Media_Selected_Count' => [
            'ar' => 'المحدد: :count',
            'en' => 'Selected: :count',
        ],
        'dashboard.Media_Clear_Selection' => [
            'ar' => 'إلغاء التحديد',
            'en' => 'Clear selection',
        ],
        'dashboard.Media_Delete_Selected' => [
            'ar' => 'حذف المحدد',
            'en' => 'Delete selected',
        ],
        'dashboard.Media_Open_Details' => [
            'ar' => 'تفاصيل :name',
            'en' => 'Details of :name',
        ],
        'dashboard.Media_In_Use_Badge' => [
            'ar' => 'مستخدمة',
            'en' => 'In use',
        ],
        'dashboard.Media_Unknown_Uploader' => [
            'ar' => 'غير معروف',
            'en' => 'Unknown',
        ],
        'dashboard.Media_Details_Title' => [
            'ar' => 'تفاصيل الصورة',
            'en' => 'Image details',
        ],
        'dashboard.Media_Close' => [
            'ar' => 'إغلاق',
            'en' => 'Close',
        ],
        'dashboard.Media_Field_Type' => [
            'ar' => 'النوع',
            'en' => 'Type',
        ],
        'dashboard.Media_Field_Size' => [
            'ar' => 'الحجم',
            'en' => 'Size',
        ],
        'dashboard.Media_Field_Dimensions' => [
            'ar' => 'الأبعاد',
            'en' => 'Dimensions',
        ],
        'dashboard.Media_Field_Uploader' => [
            'ar' => 'رفعها',
            'en' => 'Uploaded by',
        ],
        'dashboard.Media_Field_Uploaded_At' => [
            'ar' => 'تاريخ الرفع',
            'en' => 'Uploaded on',
        ],
        'dashboard.Media_Field_Original_Name' => [
            'ar' => 'اسم الملف',
            'en' => 'File name',
        ],
        'dashboard.Media_Field_Title' => [
            'ar' => 'العنوان',
            'en' => 'Title',
        ],
        'dashboard.Media_Field_Alt' => [
            'ar' => 'النص البديل',
            'en' => 'Alternative text',
        ],
        'dashboard.Media_Field_Alt_Hint' => [
            'ar' => 'يصف الصورة لقارئات الشاشة.',
            'en' => 'Describes the image for screen readers.',
        ],
        'dashboard.Media_Field_Caption' => [
            'ar' => 'التسمية التوضيحية',
            'en' => 'Caption',
        ],
        'dashboard.Media_Field_Description' => [
            'ar' => 'الوصف',
            'en' => 'Description',
        ],
        'dashboard.Media_Copy_Link' => [
            'ar' => 'نسخ الرابط',
            'en' => 'Copy link',
        ],
        'dashboard.Media_Link_Copied' => [
            'ar' => 'تم نسخ الرابط.',
            'en' => 'Link copied.',
        ],
        'dashboard.Media_Copy_Failed' => [
            'ar' => 'تعذّر نسخ الرابط.',
            'en' => 'The link could not be copied.',
        ],
        'dashboard.Media_Save' => [
            'ar' => 'حفظ التغييرات',
            'en' => 'Save changes',
        ],
        'dashboard.Media_Saving' => [
            'ar' => 'جارٍ الحفظ…',
            'en' => 'Saving…',
        ],
        'dashboard.Media_Saved' => [
            'ar' => 'تم حفظ تفاصيل الصورة.',
            'en' => 'The image details were saved.',
        ],
        'dashboard.Media_Save_Failed' => [
            'ar' => 'تعذّر حفظ التغييرات.',
            'en' => 'The changes could not be saved.',
        ],
        'dashboard.Media_Delete' => [
            'ar' => 'حذف',
            'en' => 'Delete',
        ],
        'dashboard.Media_Confirm_Delete' => [
            'ar' => 'هل تريد حذف هذه الصورة؟ لا يمكن التراجع عن ذلك.',
            'en' => 'Delete this image? This cannot be undone.',
        ],
        'dashboard.Media_Confirm_Bulk_Delete' => [
            'ar' => 'هل تريد حذف الصور المحددة (:count)؟ لا يمكن التراجع عن ذلك.',
            'en' => 'Delete the selected images (:count)? This cannot be undone.',
        ],
        'dashboard.Media_Deleted_Count' => [
            'ar' => 'عدد الصور المحذوفة: :count.',
            'en' => 'Images deleted: :count.',
        ],
        'dashboard.Media_Delete_Failed' => [
            'ar' => 'تعذّر حذف الصورة.',
            'en' => 'The image could not be deleted.',
        ],
        'dashboard.Media_In_Use_Items' => [
            'ar' => 'الصور المستخدمة: :names',
            'en' => 'Used: :names',
        ],
        'dashboard.Media_Already_Deleted' => [
            'ar' => 'هذه الصورة محذوفة مسبقًا.',
            'en' => 'This image was already deleted.',
        ],
        'dashboard.Media_Cleanup_Warning' => [
            'ar' => 'حُذفت السجلات، لكن تعذّرت إزالة بعض الملفات من التخزين، وسُجّلت لتنظيفها لاحقًا.',
            'en' => 'The records were deleted, but some files could not be removed from storage. They were logged for cleanup.',
        ],
        'dashboard.Media_Uploading' => [
            'ar' => 'جارٍ الرفع…',
            'en' => 'Uploading…',
        ],
        'dashboard.Media_Uploaded_Count' => [
            'ar' => 'عدد الصور المرفوعة: :count.',
            'en' => 'Images uploaded: :count.',
        ],
        'dashboard.Media_Upload_Failed' => [
            'ar' => 'تعذّر رفع الصور.',
            'en' => 'The images could not be uploaded.',
        ],
        'dashboard.Media_Forbidden' => [
            'ar' => 'لا تملك صلاحية تنفيذ هذا الإجراء.',
            'en' => 'You do not have permission to do this.',
        ],
        'dashboard.Media_Dismiss' => [
            'ar' => 'إغلاق التنبيه',
            'en' => 'Dismiss',
        ],

        // Media picker (AD-UI-CAT-M4-B): resources/views/components/admin/media-picker.blade.php.
        'dashboard.Media_Picker_Choose' => [
            'ar' => 'اختيار صورة',
            'en' => 'Choose image',
        ],
        'dashboard.Media_Picker_Replace' => [
            'ar' => 'استبدال الصورة',
            'en' => 'Replace image',
        ],
        'dashboard.Media_Picker_Remove' => [
            'ar' => 'إزالة الصورة',
            'en' => 'Remove image',
        ],
        'dashboard.Media_Picker_Undo_Remove' => [
            'ar' => 'إبقاء الصورة',
            'en' => 'Keep image',
        ],
        'dashboard.Media_Picker_Removed_Note' => [
            'ar' => 'ستُزال الصورة من هذا التصنيف عند الحفظ، ويبقى الملف في مكتبة الوسائط.',
            'en' => 'The image will be removed from this category when you save. The file stays in the media library.',
        ],
        'dashboard.Media_Picker_None' => [
            'ar' => 'لم تُختر صورة.',
            'en' => 'No image selected.',
        ],
        'dashboard.Media_Picker_Hint' => [
            'ar' => 'اختياري. صورة JPG أو PNG أو GIF أو WebP من مكتبة الوسائط.',
            'en' => 'Optional. A JPG, PNG, GIF or WebP image from the media library.',
        ],
        'dashboard.Media_Picker_No_Access' => [
            'ar' => 'تحتاج صلاحية عرض مكتبة الوسائط لاختيار الصورة أو استبدالها.',
            'en' => 'You need permission to view the media library to choose or replace the image.',
        ],
        'dashboard.Media_Picker_Title' => [
            'ar' => 'اختيار صورة',
            'en' => 'Choose an image',
        ],
        'dashboard.Media_Picker_Selected' => [
            'ar' => 'الصورة المختارة: :name',
            'en' => 'Selected image: :name',
        ],
        'dashboard.Media_Picker_Use' => [
            'ar' => 'استخدام هذه الصورة',
            'en' => 'Use this image',
        ],
        'dashboard.Media_Picker_Cancel' => [
            'ar' => 'إلغاء',
            'en' => 'Cancel',
        ],
        'dashboard.Media_Picker_Network' => [
            'ar' => 'تعذّر الاتصال. تحقّق من الشبكة وحاول مجددًا.',
            'en' => 'The connection failed. Check the network and try again.',
        ],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::TRANSLATIONS as $key => $values) {
            foreach ($values as $locale => $value) {
                // insertOrIgnore relies on unique(key, locale): an existing row is never overwritten.
                $inserted = TranslationValue::query()->insertOrIgnore([
                    'key' => $key,
                    'locale' => $locale,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($inserted > 0) {
                    cache()->forget("translation.{$locale}.{$key}");
                }
            }
        }
    }
}
