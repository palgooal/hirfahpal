<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'products.view');

        $products = Product::query()
            ->with(['vendor.profile', 'category'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'products.create');

        return view('dashboard.products.form', $this->formData(new Product(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.create');

        Product::create($this->validatedData($request));

        return redirect()->route('dashboard.products.index')->with('success', t('dashboard.Product_saved', 'Product saved successfully.'));
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeAbility($request, 'products.edit');

        return view('dashboard.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.edit');

        $product->update($this->validatedData($request, $product));

        return redirect()->route('dashboard.products.index')->with('success', t('dashboard.Product_saved', 'Product saved successfully.'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.delete');

        $product->delete();

        return back()->with('success', t('dashboard.Product_deleted', 'Product deleted successfully.'));
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'vendors' => Vendor::query()->with('profile')->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ];
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $product);
        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 1;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['published_at'] = $data['status'] === 'active' ? ($product?->published_at ?? now()) : null;

        return $data;
    }

    private function statuses(): array
    {
        return [
            'draft' => t('dashboard.Draft', 'Draft'),
            'active' => t('dashboard.Active', 'Active'),
            'inactive' => t('dashboard.Inactive', 'Inactive'),
            'out_of_stock' => t('dashboard.Out_of_stock', 'Out of stock'),
        ];
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }

    private function uniqueSlug(string $value, ?Product $product = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $counter = 2;

        while (Product::query()
            ->when($product?->exists, fn ($query) => $query->whereKeyNot($product->id))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
