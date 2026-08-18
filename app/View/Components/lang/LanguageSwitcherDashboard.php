<?php

namespace App\View\Components\lang;

use App\Models\Language;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Dashboard language switcher component (admin panel header).
 *
 * Usage in Blade:
 *   <x-lang.language-switcher-dashboard />
 *
 * Renders as a Bootstrap dropdown styled for the admin panel header.
 * Uses ?change-locale=xx query-param approach (no redirect param needed).
 */
class LanguageSwitcherDashboard extends Component
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
        return view('components.lang.language-switcher-dashboard');
    }
}
