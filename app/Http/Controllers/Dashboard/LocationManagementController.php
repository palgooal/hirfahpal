<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view', Setting::class);

        return view('dashboard.settings.locations', [
            'cities' => City::query()->with(['governorates' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:cities,name']]);
        City::query()->create(['name' => $data['name'], 'slug' => $this->uniqueSlug(City::class, $data['name'])]);

        return back()->with('success', 'City added successfully.');
    }

    public function storeGovernorate(Request $request): RedirectResponse
    {
        $this->authorize('edit', Setting::class);

        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255', 'unique:governorates,name'],
        ]);
        Governorate::query()->create([
            'city_id' => $data['city_id'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Governorate::class, $data['name']),
        ]);

        return back()->with('success', 'Governorate added successfully.');
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
