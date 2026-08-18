<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\TranslationValue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TranslationValueController
 *
 * Manages the translation_values dictionary (the DB-driven translation system).
 * Supports CRUD, Export/Import via CSV, and key-based filtering.
 *
 * Routes (register under an admin/auth middleware group):
 *
 *   Route::resource('translation-values', TranslationValueController::class)->except(['show','edit','update','destroy']);
 *   Route::get('translation-values/{key}/edit',     [TranslationValueController::class, 'edit'])->name('translation-values.edit');
 *   Route::post('translation-values/{key}/update',  [TranslationValueController::class, 'update'])->name('translation-values.update');
 *   Route::delete('translation-values/{key}/delete',[TranslationValueController::class, 'destroy'])->name('translation-values.destroy');
 *   Route::get('translation-values/export',         [TranslationValueController::class, 'export'])->name('translation-values.export');
 *   Route::post('translation-values/import',        [TranslationValueController::class, 'import'])->name('translation-values.import');
 *
 * Key naming convention:
 *   frontend.*   → front-end UI strings
 *   dashboard.*  → admin dashboard strings
 *   (no prefix)  → shared/general strings
 */
class TranslationValueController extends Controller
{
    public function index(Request $request)
    {
        $localeFilter = $request->get('locale');
        $search       = $request->get('search');
        $typeFilter   = $request->get('type');

        $translations = TranslationValue::when($localeFilter, fn ($q) => $q->where('locale', $localeFilter))
            ->when($search,       fn ($q) => $q->where('key', 'like', "%$search%"))
            ->when($typeFilter, function ($q) use ($typeFilter) {
                match ($typeFilter) {
                    'dashboard' => $q->where('key', 'like', 'dashboard.%'),
                    'frontend'  => $q->where('key', 'like', 'frontend.%'),
                    'general'   => $q->where(fn ($sq) => $sq
                        ->where('key', 'not like', 'dashboard.%')
                        ->where('key', 'not like', 'frontend.%')),
                    default => null,
                };
            })
            ->get()
            ->groupBy('key');

        $languages = available_locales();

        return view('dashboard.lang.translation-values.index', compact(
            'translations',
            'languages',
            'localeFilter',
            'search',
            'typeFilter'
        ));
    }

    public function create()
    {
        $languages = Language::where('is_active', true)->get();
        return view('dashboard.lang.translation-values.create', compact('languages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'key'    => 'required|string|max:150',
            'values' => 'required|array',
        ]);

        foreach ($request->values as $locale => $value) {
            TranslationValue::updateOrCreate(
                ['key' => $request->key, 'locale' => $locale],
                ['value' => $value]
            );
        }

        return redirect()->route('dashboard.translation-values.index')->with('success', 'تمت الإضافة بنجاح');
    }

    public function edit($key)
    {
        $languages    = Language::where('is_active', true)->get();
        $translations = TranslationValue::where('key', $key)->get()->keyBy('locale');

        return view('dashboard.lang.translation-values.edit', compact('key', 'languages', 'translations'));
    }

    public function update(Request $request, $key)
    {
        $request->validate(['values' => 'required|array']);

        foreach ($request->values as $locale => $value) {
            TranslationValue::updateOrCreate(
                ['key' => $key, 'locale' => $locale],
                ['value' => $value]
            );
            // Invalidate cache immediately
            cache()->forget("translation.{$locale}.{$key}");
        }

        return redirect()->route('dashboard.translation-values.index')->with('success', 'تم التحديث بنجاح');
    }

    public function destroy($key)
    {
        $translations = TranslationValue::where('key', $key)->get();

        foreach ($translations as $translation) {
            cache()->forget("translation.{$translation->locale}.{$translation->key}");
            $translation->delete();
        }

        return redirect()->route('dashboard.translation-values.index')->with('success', 'تم الحذف بنجاح');
    }

    /** Export all translations to a downloadable CSV file. */
    public function export()
    {
        $translations = TranslationValue::all();

        $filename = 'translations_export_' . now()->format('Y_m_d_His') . '.csv';
        $handle   = fopen('php://output', 'w');

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename={$filename}");

        fputcsv($handle, ['key', 'locale', 'value']);

        foreach ($translations as $t) {
            fputcsv($handle, [$t->key, $t->locale, $t->value]);
        }

        fclose($handle);
        exit;
    }

    /** Import translations from a CSV file (key, locale, value). */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        fgetcsv($handle); // skip header row

        while (($row = fgetcsv($handle)) !== false) {
            TranslationValue::updateOrCreate(
                ['key' => $row[0], 'locale' => $row[1]],
                ['value' => $row[2]]
            );
            cache()->forget("translation.{$row[1]}.{$row[0]}");
        }

        fclose($handle);

        return redirect()->back()->with('success', 'تم استيراد الترجمات بنجاح');
    }
}
