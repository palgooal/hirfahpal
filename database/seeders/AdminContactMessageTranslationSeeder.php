<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * CONTACT-ADMIN-IMPLEMENTATION-01: Arabic and English texts of the admin contact-messages list and details page
 * (dashboard.Contact_Messages_*). The pages read them without auto-creating rows. The sidebar label and page title
 * (dashboard.Nav_Contact_Messages) belong to AdminDashboardTranslationSeeder, the ability labels to
 * AdminAbilityTranslationSeeder; the approved keys reused as they are (Actions, Pagination_*) are not seeded here.
 *
 * Only missing (key, locale) rows are inserted; edited values are kept; nothing is deleted. Not called from
 * DatabaseSeeder.
 */
class AdminContactMessageTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Messages list.
        'dashboard.Contact_Messages_Intro' => [
            'ar' => 'الرسائل المرسلة من صفحة «تواصل واستفسارات» في المتجر.',
            'en' => 'Messages sent from the store contact page.',
        ],
        'dashboard.Contact_Messages_Column_Sender' => ['ar' => 'المرسل', 'en' => 'Sender'],
        'dashboard.Contact_Messages_Column_Subject' => ['ar' => 'الموضوع', 'en' => 'Subject'],
        'dashboard.Contact_Messages_Column_Received' => ['ar' => 'تاريخ الاستلام', 'en' => 'Received'],
        'dashboard.Contact_Messages_Column_Status' => ['ar' => 'الحالة', 'en' => 'Status'],
        'dashboard.Contact_Messages_No_Subject' => ['ar' => 'بدون موضوع', 'en' => 'No subject'],
        'dashboard.Contact_Messages_Status_Unread' => ['ar' => 'جديدة', 'en' => 'New'],
        'dashboard.Contact_Messages_Status_Read' => ['ar' => 'مقروءة', 'en' => 'Read'],
        'dashboard.Contact_Messages_View' => ['ar' => 'عرض', 'en' => 'View'],
        'dashboard.Contact_Messages_Empty_Title' => ['ar' => 'لا توجد رسائل بعد', 'en' => 'No messages yet'],
        'dashboard.Contact_Messages_Empty_Text' => [
            'ar' => 'تظهر هنا الرسائل المرسلة من صفحة التواصل في المتجر.',
            'en' => 'Messages sent from the store contact page appear here.',
        ],

        // Message details.
        'dashboard.Contact_Messages_Show_Back' => ['ar' => 'العودة إلى رسائل التواصل', 'en' => 'Back to contact messages'],
        'dashboard.Contact_Messages_Show_Sender' => ['ar' => 'بيانات المرسل', 'en' => 'Sender details'],
        'dashboard.Contact_Messages_Show_Name' => ['ar' => 'الاسم', 'en' => 'Name'],
        'dashboard.Contact_Messages_Show_Email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
        'dashboard.Contact_Messages_Show_Account' => ['ar' => 'حساب العميل', 'en' => 'Customer account'],
        'dashboard.Contact_Messages_Show_Guest' => ['ar' => 'زائر (بدون حساب)', 'en' => 'Guest (no account)'],
        'dashboard.Contact_Messages_Show_Received' => ['ar' => 'تاريخ الاستلام', 'en' => 'Received'],
        'dashboard.Contact_Messages_Show_Read_At' => ['ar' => 'تاريخ القراءة', 'en' => 'Read on'],
        'dashboard.Contact_Messages_Show_Message' => ['ar' => 'الرسالة', 'en' => 'Message'],

        // CONTACT-ADMIN-DELETE-02: soft delete (details page panel, and the two controller messages).
        'dashboard.Contact_Messages_Delete_Title' => ['ar' => 'حذف الرسالة', 'en' => 'Delete this message'],
        'dashboard.Contact_Messages_Delete_Hint' => [
            'ar' => 'تختفي الرسالة من قائمة رسائل التواصل لكنها تبقى محفوظة في قاعدة البيانات، ولا يمكن استعادتها من لوحة التحكم.',
            'en' => 'The message disappears from the contact messages list but stays in the database. It cannot be restored from the dashboard.',
        ],
        'dashboard.Contact_Messages_Delete_Confirm' => ['ar' => 'أؤكد حذف هذه الرسالة', 'en' => 'I confirm deleting this message'],
        'dashboard.Contact_Messages_Delete_Button' => ['ar' => 'حذف الرسالة', 'en' => 'Delete message'],
        'dashboard.Contact_Messages_Delete_Not_Confirmed' => ['ar' => 'لم يتم تأكيد الحذف.', 'en' => 'The deletion was not confirmed.'],
        'dashboard.Contact_Messages_Deleted' => ['ar' => 'تم حذف الرسالة.', 'en' => 'The message was deleted.'],
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
