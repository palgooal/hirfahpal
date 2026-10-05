<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'categories.view');

        $categories = Category::query()
            ->with(['parent'])
            ->withCount('products')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.categories.index', compact('categories'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'categories.create');

        return view('dashboard.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.create');

        Category::create($this->validatedData($request));

        return redirect()->route('dashboard.categories.index')->with('success', t('dashboard.Category_saved', 'Category saved successfully.'));
    }

    public function edit(Request $request, Category $category): View
    {
        $this->authorizeAbility($request, 'categories.edit');

        return view('dashboard.categories.form', [
            'category' => $category,
            'parents' => Category::query()->whereKeyNot($category->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.edit');

        $category->update($this->validatedData($request, $category));

        return redirect()->route('dashboard.categories.index')->with('success', t('dashboard.Category_saved', 'Category saved successfully.'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.delete');

        abort_if($category->products()->exists() || $category->children()->exists(), 422);
        $category->delete();

        return back()->with('success', t('dashboard.Category_deleted', 'Category deleted successfully.'));
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $category);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }

    private function uniqueSlug(string $value, ?Category $category = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (Category::query()
            ->when($category?->exists, fn ($query) => $query->whereKeyNot($category->id))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
