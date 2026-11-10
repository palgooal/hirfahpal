<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    /** PAGES-05B / 05D: the request attribute holding the active languages, read once per request. */
    private const ACTIVE = 'languages.active';

    protected $fillable = ['name', 'native', 'code', 'flag', 'is_rtl', 'is_active'];

    protected static function booted(): void
    {
        // A language added, enabled, disabled or removed is seen by the next read.
        static::saved(fn () => request()->attributes->remove(self::ACTIVE));
        static::deleted(fn () => request()->attributes->remove(self::ACTIVE));
    }

    public function translationValues()
    {
        return $this->hasMany(TranslationValue::class, 'language_id', 'id');
    }

    /**
     * PAGES-05B: the enabled languages (is_active), keyed by their lower-cased code, in the order they were added. The
     * registry of the languages the site offers; one query per request.
     *
     * @return Collection<string, self>
     */
    public static function active(): Collection
    {
        // Kept on the current request (not the container), so every request reads the registry once, also when one
        // application serves several requests (tests).
        $attributes = request()->attributes;

        if (! $attributes->has(self::ACTIVE)) {
            $attributes->set(self::ACTIVE, static::query()->where('is_active', true)->orderBy('id')->get()
                ->keyBy(fn (self $language) => strtolower($language->code)));
        }

        return $attributes->get(self::ACTIVE);
    }

    /** @return list<string> */
    public static function activeCodes(): array
    {
        return static::active()->keys()->all();
    }

    public static function isActiveCode(string $code): bool
    {
        return static::active()->has(strtolower($code));
    }

    /**
     * PAGES-05B: a language's direction from its is_rtl, also for a disabled one; null when the code is not in the
     * registry at all (the caller decides).
     */
    public static function directionOf(string $code): ?string
    {
        $code = strtolower($code);
        $language = static::active()->get($code) ?? static::query()->whereRaw('lower(code) = ?', [$code])->first();

        return $language === null ? null : ($language->is_rtl ? 'rtl' : 'ltr');
    }
}
