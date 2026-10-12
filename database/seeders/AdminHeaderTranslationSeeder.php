<?php

namespace Database\Seeders;

use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

/**
 * Arabic and English strings of the admin header (AD-UI-01D), plus one targeted
 * data correction. The header reuses existing keys where they are correct
 * (dashboard.Choose_Language, dashboard.Nav_Account_Menu, dashboard.Nav_Sign_Out,
 * dashboard.Nav_Brand_Subtitle as the fallback page title); dashboard.Super_Admin
 * is not reused because its Arabic row holds English text.
 * Only missing (key, locale) rows are inserted; edited values are kept.
 * Not called from DatabaseSeeder.
 */
class AdminHeaderTranslationSeeder extends Seeder
{
    /**
     * key => [locale => value]
     *
     * @var array<string, array<string, string>>
     */
    public const TRANSLATIONS = [
        // Role shown under the admin's name in the header account menu.
        'dashboard.Header_Role_Super_Admin' => [
            'ar' => 'مشرف عام',
            'en' => 'Super admin',
        ],
        'dashboard.Header_Role_Admin' => [
            'ar' => 'مشرف',
            'en' => 'Admin',
        ],
    ];

    /**
     * Rows auto-created with the wrong language. Each is replaced only while it still holds
     * the known wrong value; any other value is an edit and is kept.
     *
     * @var array<int, array{key: string, locale: string, from: string, to: string}>
     */
    public const CORRECTIONS = [
        // The dashboard home heading was auto-created with its Arabic default in English too.
        ['key' => 'dashboard.Admin_Control_Center', 'locale' => 'en', 'from' => 'لوحة الإدارة', 'to' => 'Admin panel'],
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

        foreach (self::CORRECTIONS as $correction) {
            $this->correct($correction['key'], $correction['locale'], $correction['from'], $correction['to'], $now);
        }
    }

    private function correct(string $key, string $locale, string $from, string $to, $now): void
    {
        $row = TranslationValue::query()->where('key', $key)->where('locale', $locale)->first();

        if ($row === null) {
            // Missing row: add the correct value so t() never auto-creates it with the wrong default.
            $changed = TranslationValue::query()->insertOrIgnore([
                'key' => $key,
                'locale' => $locale,
                'value' => $to,
                'created_at' => $now,
                'updated_at' => $now,
            ]) > 0;
        } elseif ($row->value === $from) {
            // Guarded update: only this row, and only while it still holds the known wrong value.
            $changed = TranslationValue::query()
                ->whereKey($row->getKey())
                ->where('value', $from)
                ->update(['value' => $to, 'updated_at' => $now]) > 0;
        } else {
            if ($row->value !== $to) {
                $this->command?->warn("{$key} [{$locale}] was edited (\"{$row->value}\"); left unchanged.");
            }

            $changed = false;
        }

        if ($changed) {
            cache()->forget("translation.{$locale}.{$key}");
        }
    }
}
