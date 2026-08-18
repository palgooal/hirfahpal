<?php

namespace App\View\Components\lang;

use App\Models\Language;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Frontend language switcher component.
 *
 * Usage in Blade:
 *   <x-lang.language-switcher />
 *   <x-lang.language-switcher variant="topbar" />
 *   <x-lang.language-switcher variant="builder" />
 *
 * Props (all optional, handled inside the view):
 *   variant         → 'front' (default) | 'builder' | 'topbar'
 *   buttonClass     → override button CSS classes
 *   menuClass       → override dropdown menu CSS classes
 *   itemClass       → override item CSS classes
 *   activeItemClass → override active item CSS classes
 *   label           → override the button label text
 *   showLocaleCode  → bool, show code alongside native name (topbar variant)
 */
class LanguageSwitcher extends Component
{
    public $languages;
    public $currentLocale;
    public $currentLanguage;

    public function __construct()
    {
        $this->languages       = Language::where('is_active', true)->get();
        $this->currentLocale   = app()->getLocale();
        $this->currentLanguage = Language::where('code', $this->currentLocale)->first();
    }

    public function render(): View|Closure|string
    {
        return view('components.lang.language-switcher');
    }
}
