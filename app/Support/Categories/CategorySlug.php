<?php

namespace App\Support\Categories;

use Illuminate\Support\Str;
use Normalizer;

/**
 * AUD-02 (AD-UI-CAT-01F-FIX-01): category slugs in Arabic and/or English (owner decision). A slug is one or
 * more segments of Arabic letters, lowercase a-z and digits 0-9, joined by single hyphens: no other symbol,
 * space, control or invisible character, no leading, trailing or doubled hyphen. Arabic letters are the base
 * letters U+0621–U+063A and U+0641–U+064A (no tatweel, no diacritics, no Arabic punctuation).
 *
 * The same canonical form is produced for typed slugs (normalize()) and names (fromName()): Unicode NFC,
 * A-Z lowercased (ASCII only; Arabic has no case), Arabic-Indic digits written 0-9.
 * The column is varchar(255): MySQL counts characters there, and 255 Arabic letters are 510 bytes, within
 * utf8mb4's limits for the column and its unique index, so the limit is 255 characters.
 */
final class CategorySlug
{
    public const MAX_LENGTH = 255;

    private const LETTERS = '\x{0621}-\x{063A}\x{0641}-\x{064A}a-z0-9';

    public const PATTERN = '/^['.self::LETTERS.']+(?:-['.self::LETTERS.']+)*$/u';

    /** The canonical form of a typed slug; the result still has to match PATTERN. */
    public static function normalize(string $value): string
    {
        $value = trim(self::nfc($value));

        return self::westernDigits(strtolower($value));
    }

    public static function isValid(string $slug): bool
    {
        return mb_strlen($slug) <= self::MAX_LENGTH && preg_match(self::PATTERN, $slug) === 1;
    }

    /**
     * A slug from a category name, keeping Arabic letters: Latin letters with accents become plain a-z,
     * diacritics and tatweel are dropped, anything else separates segments. '' when nothing usable is left
     * (the caller then asks for a slug instead of inventing one).
     */
    public static function fromName(string $name, int $maxLength = self::MAX_LENGTH): string
    {
        $value = self::westernDigits(self::nfc($name));
        // Arabic diacritics (harakat, superscript alef) and tatweel carry no letter.
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $value) ?? '';
        // Latin letters only are transliterated (é -> e); Arabic stays as it is.
        $value = preg_replace_callback('/\p{Latin}+/u', fn (array $match) => Str::ascii($match[0]), $value) ?? '';
        $value = strtolower($value);
        $value = preg_replace('/[^'.self::LETTERS.']+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return self::truncate($value, $maxLength);
    }

    /** Cut to $maxLength characters without leaving a trailing hyphen. */
    public static function truncate(string $slug, int $maxLength): string
    {
        return rtrim(mb_substr($slug, 0, max(0, $maxLength)), '-');
    }

    private static function nfc(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        return class_exists(Normalizer::class) ? (Normalizer::normalize($value, Normalizer::FORM_C) ?: $value) : $value;
    }

    /** Arabic-Indic (٠-٩) and Extended Arabic-Indic (۰-۹) digits written 0-9: one form per slug. */
    private static function westernDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
