<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationManagementController extends Controller
{
    // The base controller has no authorize(): as SettingController, this one uses the trait itself.
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view', Setting::class);

        return view('dashboard.settings.locations', [
            'cities' => City::query()->with(['governorates' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            // Governorates linked to no city (legacy rows, or their city deleted: nullOnDelete), listed on their own.
            'unassignedGovernorates' => Governorate::query()->whereNull('city_id')->orderBy('name')->get(),
        ]);
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:cities,name']]);
        City::query()->create(['name' => $data['name'], 'slug' => $this->uniqueSlug(City::class, $data['name'])]);

        return back()->with('success', $this->text('Location_City_Added', 'City added successfully.'));
    }

    public function storeGovernorate(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        // Blank rows are allowed in the form so an admin can fill as many governorates as needed.
        // Keep the old single `name` payload working for forms saved before the multi-row form.
        $names = $request->input('governorates');
        if (! is_array($names)) {
            $names = $request->has('name') ? [$request->input('name')] : [];
        }
        $names = array_values(array_filter($names, fn ($name) => $name !== null && (! is_string($name) || trim($name) !== '')));
        $request->merge(['governorates' => $names]);

        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'governorates' => ['required', 'array', 'min:1'],
            'governorates.*' => ['required', 'string', 'max:255', 'distinct', 'unique:governorates,name'],
        ], [], [
            'governorates' => $this->text('Location_Governorate', 'Governorate'),
            'governorates.*' => $this->text('Location_Governorate', 'Governorate'),
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['governorates'] as $name) {
                Governorate::query()->create([
                    'city_id' => $data['city_id'],
                    'name' => $name,
                    'slug' => $this->uniqueSlug(Governorate::class, $name),
                ]);
            }
        });

        $message = count($data['governorates']) === 1
            ? $this->text('Location_Governorate_Added', 'Governorate added successfully.')
            : $this->text('Location_Governorates_Added', 'Governorates added successfully.');

        return back()->with('success', $message);
    }

    /** A dashboard text in the page language (AdminSettingsTranslationSeeder), read without creating translation rows. */
    private function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'location';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
