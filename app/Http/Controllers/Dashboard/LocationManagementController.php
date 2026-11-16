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
use Illuminate\Validation\Rule;

class LocationManagementController extends Controller
{
    // The base controller has no authorize(): as SettingController, this one uses the trait itself.
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view', Setting::class);

        return view('dashboard.settings.locations', [
            // Governorate → City: the governorates with their cities, and the cities linked to no governorate (legacy
            // rows, until the H4 data step), listed read-only.
            'governorates' => Governorate::query()->with(['cities' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'unassignedCities' => City::query()->whereNull('governorate_id')->orderBy('name')->get(),
        ]);
    }

    /** A governorate is a parent: one name, no city (governorates.city_id is never written). */
    public function storeGovernorate(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        // A form saved before Governorate → City sent governorates[]: a single filled row is its name.
        if (! $request->has('name') && is_array($request->input('governorates'))) {
            $rows = array_values(array_filter($request->input('governorates'), fn ($name) => $name !== null && (! is_string($name) || trim($name) !== '')));
            if (count($rows) === 1) {
                $request->merge(['name' => $rows[0]]);
            }
        }

        $data = $request->validate(
            ['name' => ['required', 'string', 'max:255', 'unique:governorates,name']],
            [],
            ['name' => $this->text('Location_Governorate', 'Governorate')],
        );
        Governorate::query()->create(['name' => $data['name'], 'slug' => $this->uniqueSlug(Governorate::class, $data['name']), 'status' => 'active']);

        return back()->with('success', $this->text('Location_Governorate_Added', 'Governorate added successfully.'));
    }

    /** Cities belong to one active governorate (cities.governorate_id); several can be added at once. */
    public function storeCity(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        // Blank rows are allowed in the form so an admin can fill as many cities as needed; the single `name` payload
        // of the older form still works.
        $names = $request->input('cities');
        if (! is_array($names)) {
            $names = $request->has('name') ? [$request->input('name')] : [];
        }
        $names = array_values(array_filter($names, fn ($name) => $name !== null && (! is_string($name) || trim($name) !== '')));
        $request->merge(['cities' => $names]);

        $citiesLabel = $this->text('Location_Cities', 'Cities');
        $data = $request->validate([
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')->where('status', 'active')],
            'cities' => ['required', 'array', 'min:1'],
            'cities.*' => ['required', 'string', 'max:255', 'distinct', 'unique:cities,name'],
        ], [], [
            'governorate_id' => $this->text('Location_Governorate', 'Governorate'),
            'cities' => $citiesLabel,
            'cities.*' => $citiesLabel,
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['cities'] as $name) {
                City::query()->create([
                    'governorate_id' => $data['governorate_id'],
                    'name' => $name,
                    'slug' => $this->uniqueSlug(City::class, $name),
                    'status' => 'active',
                ]);
            }
        });

        $message = count($data['cities']) === 1
            ? $this->text('Location_City_Added', 'City added successfully.')
            : $this->text('Location_Cities_Added', 'Cities added successfully.');

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
