<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\TranslationValue;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * LanguageController
 *
 * Full CRUD for the languages table + AJAX toggle endpoints.
 *
 * Routes (register under an admin/auth middleware group):
 *
 *   Route::resource('languages', LanguageController::class)->except(['show'])->names('languages');
 *   Route::post('languages/{language}/toggle-rtl',    [LanguageController::class, 'toggleRtl'])->name('languages.toggle-rtl');
 *   Route::post('languages/{language}/toggle-status', [LanguageController::class, 'toggleStatus'])->name('languages.toggle-status');
 *   Route::delete('languages/{language}/delete',      [LanguageController::class, 'destroy'])->name('languages.destroy-ajax');
 */
class LanguageController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('view', Language::class);

        $langs = Language::paginate(10);
        return view('dashboard.lang.index', compact('langs'));
    }

    public function create()
    {
        $this->authorize('create', Language::class);

        return view('dashboard.lang.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Language::class);

        $request->validate([
            'name'   => 'required|string|max:255',
            'native' => 'required|string|max:255',
            'code'   => 'required|string|max:10|unique:languages,code',
            'flag'   => 'nullable|string|max:255',
        ]);

        Language::create([
            'name'      => $request->name,
            'native'    => $request->native,
            'code'      => strtolower($request->code),
            'flag'      => $request->flag,
            'is_rtl'    => $request->has('is_rtl'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('dashboard.languages.index')->with('success', 'Language added successfully!');
    }

    public function edit(Request $request, string $id)
    {
        $this->authorize('edit', Language::class);

        return view('dashboard.lang.edit')->with('language', Language::findOrFail($id));
    }

    public function update(Request $request, Language $language)
    {
        $this->authorize('edit', Language::class);

        $request->validate([
            'name'   => 'required|string|max:255',
            'native' => 'required|string|max:255',
            'code'   => 'required|string|max:10|unique:languages,code,' . $language->id,
            'flag'   => 'nullable|string|max:255',
        ]);

        $language->update([
            'name'      => $request->name,
            'native'    => $request->native,
            'code'      => strtolower($request->code),
            'flag'      => $request->flag,
            'is_rtl'    => $request->has('is_rtl'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('dashboard.languages.index')->with('success', 'Language updated successfully!');
    }

    /** AJAX — toggle RTL direction */
    public function toggleRtl(Language $language, Request $request)
    {
        $this->authorize('edit', Language::class);

        $language->is_rtl = $request->boolean('is_rtl');
        $language->save();

        return response()->json(['success' => true]);
    }

    /** AJAX — toggle active/inactive status */
    public function toggleStatus(Language $language, Request $request)
    {
        $this->authorize('edit', Language::class);

        $language->is_active = $request->boolean('is_active');
        $language->save();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a language AND all its translation_values rows.
     * Also clears the cache for every deleted translation key.
     */
    public function destroy(Language $language)
    {
        $this->authorize('delete', Language::class);

        try {
            $translations = TranslationValue::where('locale', $language->code)->get();

            foreach ($translations as $translation) {
                cache()->forget("translation.{$translation->locale}.{$translation->key}");
                $translation->delete();
            }

            $language->delete();

            return response()->json([
                'success' => true,
                'message' => 'Language and its translations deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to delete: ' . $e->getMessage(),
            ], 500);
        }
    }
}
