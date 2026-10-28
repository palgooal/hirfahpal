<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAdminAction;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Support\Media\VendorMediaPath;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Palgoal\MediaLibrary\Models\Media;
use Throwable;

class ProductManagementController extends Controller
{
    private const MAX_PRICE = '99999999.99';

    private const MAX_WEIGHT = '999999.99';

    private const MAX_UNSIGNED_INT = 4294967295;

    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'products.view');

        $vendors = Vendor::query()->with('profile')->orderBy('name')->get();
        $categories = Category::query()->with('parent')->orderBy('name')->get();

        // AD-UI-PROD-M3: every filter value is read as a plain string and used only when it is one of the values
        // the list offers; anything else (an array, an unknown id or word) is ignored, never a 500 (PRD-19).
        $text = fn (string $name): string => is_string($value = $request->query($name)) ? trim($value) : '';
        $search = mb_substr($text('search'), 0, 100);
        $vendorId = ctype_digit($text('vendor_id')) && $vendors->contains('id', (int) $text('vendor_id')) ? (int) $text('vendor_id') : null;
        $categoryId = ctype_digit($text('category_id')) && $categories->contains('id', (int) $text('category_id')) ? (int) $text('category_id') : null;
        $status = array_key_exists($text('status'), $this->statusKeys()) ? $text('status') : null;
        $suspension = in_array($text('suspension'), ['suspended', 'not_suspended'], true) ? $text('suspension') : null;
        // OPEN-PROD-04D: products that are not archived by default; "archived" or "all" on request.
        $archive = in_array($text('archive'), ['archived', 'all'], true) ? $text('archive') : 'current';
        // OPEN-PROD-16: available stock (physical minus reserved) at or below the threshold, the vendor's scope.
        $lowStock = $text('stock') === 'low';

        $products = Product::query()
            ->with(['vendor.profile', 'category.parent'])
            // PRD-10: the outstanding reserved quantity of every row in one query, not one query per row.
            ->withSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity')
            // PRD-04: the name and SKU alternatives are grouped, so they never bypass the other filters; the LIKE
            // wildcards typed by the admin are matched literally (escape character '!', MySQL and SQLite).
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

                $query->where(fn ($match) => $match
                    ->whereRaw("products.name like ? escape '!'", [$like])
                    ->orWhereRaw("products.sku like ? escape '!'", [$like]));
            })
            ->when($vendorId !== null, fn ($query) => $query->where('products.vendor_id', $vendorId))
            ->when($categoryId !== null, fn ($query) => $query->where('products.category_id', $categoryId))
            ->when($status !== null, fn ($query) => $query->where('products.status', $status))
            ->when($suspension === 'suspended', fn ($query) => $query->whereNotNull('products.suspended_at'))
            ->when($suspension === 'not_suspended', fn ($query) => $query->whereNull('products.suspended_at'))
            ->when($archive === 'current', fn ($query) => $query->whereNull('products.archived_at'))
            ->when($archive === 'archived', fn ($query) => $query->whereNotNull('products.archived_at'))
            ->when($lowStock, fn ($query) => $query->lowAvailableStock())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $filters = [
            'search' => $search,
            'vendor_id' => $vendorId,
            'category_id' => $categoryId,
            'status' => $status,
            'suspension' => $suspension,
            'archive' => $archive,
            'stock' => $lowStock ? 'low' : null,
        ];

        // Whether a filter really applies (ignored values and the default archive view do not count).
        $filtered = $search !== '' || $vendorId !== null || $categoryId !== null || $status !== null
            || $suspension !== null || $archive !== 'current' || $lowStock;

        return view('dashboard.products.index', [
            'products' => $products,
            'vendors' => $vendors,
            'categories' => $categories,
            'statusKeys' => $this->statusKeys(),
            'filters' => $filters,
            'filtered' => $filtered,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'products.create');

        return view('dashboard.products.form', $this->formData(new Product(['status' => 'draft'])));
    }

    /**
     * PRODUCT-MEDIA-01 P5-C: the images a new product may use, from the chosen vendor's own library (products.create).
     * Read only: no upload, no deletion; media.view and vendors.view are not needed.
     */
    public function vendorMediaForCreate(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'products.create');

        $vendorId = (int) $request->validate(['vendor_id' => ['required', 'integer', 'exists:vendors,id']])['vendor_id'];

        return $this->vendorMediaPage($request, $vendorId);
    }

    /**
     * P5-C: the images a product may use, from its stored vendor's library (products.edit). The id is a plain number,
     * resolved after the ability is checked, so an admin without it gets the same 403 for any id.
     */
    public function vendorMediaForProduct(Request $request, string $product): JsonResponse
    {
        $this->authorizeAbility($request, 'products.edit');

        return $this->vendorMediaPage($request, (int) Product::query()->findOrFail($product)->vendor_id);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.create');

        $this->returningErrorsTo(route('dashboard.products.create'), function () use ($request) {
            $data = $this->validatedData($request);
            $gallery = $this->validatedGallery($request, (int) $data['vendor_id']);
            $mediaIds = $this->validateImageUploads($request, (int) $data['vendor_id']);

            // P5-A: with images, the product and its links are written together, so a refused image leaves no
            // product. Without images the single insert stays as it was (no transaction around it).
            try {
                $mediaIds === [] && ($gallery['refs'] ?? []) === []
                    ? Product::create($data)
                    : DB::transaction(function () use ($data, $mediaIds, $gallery): void {
                        $product = Product::create($data);
                        $gallery === null ? $this->storeImageUploads($product, $mediaIds) : $this->syncGallery($product, $gallery['refs'], null);
                    });
            } catch (QueryException $exception) {
                $this->failUnlessCategoryVanished($exception, $data);
            }
        });

        return redirect()->route('dashboard.products.index')->with('success', t('dashboard.Product_saved', 'Product saved successfully.'));
    }

    /**
     * AD-UI-PROD-M4 (DEC-PROD-12): the read-only details page, five groups. The current suspension reason is read
     * with products.view (one query, the latest "suspended" entry). The full action log is queried only for an
     * admin holding products.audit (DEC-PROD-10), 20 entries per page.
     */
    public function show(Request $request, Product $product): View
    {
        $this->authorizeAbility($request, 'products.view');

        $product->load(['vendor.profile', 'category.parent', 'images'])
            ->loadSum(['stockReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved')], 'quantity');

        $suspensionReason = $product->isSuspended()
            ? ProductAdminAction::query()->where('product_id', $product->id)->where('action', 'suspended')
                ->latest('created_at')->latest('id')->value('reason')
            : null;

        $canAudit = $this->hasAbility($request, 'products.audit');
        $actions = $canAudit
            ? $product->adminActions()->paginate(20, ['*'], 'actions_page')->withQueryString()->fragment('product-panel-log')
            : null;

        return view('dashboard.products.show', [
            'product' => $product,
            'images' => $product->images->map(fn (ProductImage $image) => ['image' => $image, 'url' => $this->safeImageUrl($image->path)]),
            'suspensionReason' => $suspensionReason,
            'canAudit' => $canAudit,
            'actions' => $actions,
            'statusKeys' => $this->statusKeys(),
        ]);
    }

    /** AD-UI-PROD-M5 (DEC-PROD-01, DEC-PROD-03, DEC-PROD-07): suspend a product, with a reason. */
    public function suspend(Request $request, string $product): RedirectResponse
    {
        return $this->moderate($request, (int) $product, 'suspended');
    }

    /** AD-UI-PROD-M5 (DEC-PROD-09, OPEN-PROD-10A): lift a suspension directly, with a reason. */
    public function unsuspend(Request $request, string $product): RedirectResponse
    {
        return $this->moderate($request, (int) $product, 'unsuspended');
    }

    /** AD-UI-PROD-M6 (OPEN-PROD-04, 04C): archive a product instead of deleting it, with a reason. */
    public function archive(Request $request, string $product): RedirectResponse
    {
        return $this->moderate($request, (int) $product, 'archived');
    }

    /** AD-UI-PROD-M6 (OPEN-PROD-04A, 04B): restore an archived product as a draft, with a reason. */
    public function restore(Request $request, string $product): RedirectResponse
    {
        return $this->moderate($request, (int) $product, 'restored');
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeAbility($request, 'products.edit');

        return view('dashboard.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.edit');

        $this->returningErrorsTo(route('dashboard.products.edit', $product), function () use ($request, $product) {
            $data = $this->validatedData($request, $product);
            // P5-A: images are checked against the stored vendor, never the submitted one.
            $gallery = $this->validatedGallery($request, (int) $product->vendor_id, $product);
            $mediaIds = $this->validateImageUploads($request, (int) $product->vendor_id, $product);
            $basis = $this->stockBasis($request);

            // M0-FIX (lost update): the form sends back the stock it displayed. When the submitted stock equals it,
            // the admin did not touch the field, so stock_quantity is not written at all: a reservation committed
            // after the page was opened (which lowers the physical stock) is never undone by saving another field.
            $changesStock = (int) $data['stock_quantity'] !== $basis['stock'];

            if (! $changesStock) {
                unset($data['stock_quantity']);
            }

            // PRD-02 (VEN-BE-019, as in the vendor update): lock the product row first, the order checkout and
            // vendor accept use, then compare and re-read under that lock before writing.
            try {
                DB::transaction(function () use ($request, $product, $data, $basis, $changesStock, $mediaIds, $gallery): void {
                    $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                    if ($changesStock) {
                        // The new stock was chosen against the displayed one; it applies only if the row is still the
                        // one the admin saw: the same stock and the same updated_at (every Eloquent write, including
                        // the reservation commit's decrement, refreshes it), so a change and its reversal are refused too.
                        if ((int) $locked->stock_quantity !== $basis['stock'] || $this->stockVersion($locked) !== $basis['version']) {
                            throw new HttpResponseException($this->stockConflict($request, $product, (int) $locked->stock_quantity));
                        }

                        $reserved = $locked->reservedStockQuantity();

                        if ((int) $data['stock_quantity'] < $reserved) {
                            throw ValidationException::withMessages([
                                'stock_quantity' => str_replace(':reserved', (string) $reserved, $this->untranslatedDefault(
                                    'dashboard.Product_stock_below_reserved',
                                    'The stock quantity cannot be lower than the :reserved units currently reserved for open orders.'
                                )),
                            ]);
                        }
                    }

                    $locked->update($data);

                    // P5-B: a submitted gallery replaces the append-only media_image_ids path; under the product lock,
                    // so the fingerprint comparison and the writes are one step. A refusal rolls the update back.
                    $gallery === null
                        ? $this->storeImageUploads($locked, $mediaIds)
                        : $this->syncGallery($locked, $gallery['refs'], $gallery['fingerprint']);
                });
            } catch (QueryException $exception) {
                $this->failUnlessCategoryVanished($exception, $data);
            }
        });

        return redirect()->route('dashboard.products.index')->with('success', t('dashboard.Product_saved', 'Product saved successfully.'));
    }

    /**
     * PRD-01 / OPEN-PROD-17 (option A): permanent deletion from the admin is disabled. Deleting a product
     * cascades to its stock reservations, reviews and cart items and detaches its order items, so the request
     * is refused even when the route is called directly. Nothing is written. The route and the products.delete
     * ability stay for compatibility; archiving replaces deletion in M6.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAbility($request, 'products.delete');

        $message = $this->untranslatedDefault(
            'dashboard.Product_delete_disabled',
            'Permanent deletion of products is disabled. The product and its orders, stock reservations and reviews were kept.'
        );

        abort_if($request->expectsJson(), 409, $message);

        // PRD-03: a fixed admin route, never the Referer.
        return redirect()->route('dashboard.products.index')->with('danger', $message);
    }

    /**
     * AD-UI-PROD-M7-FIX-01: the form offers the active categories. F-2: a product whose current category is inactive
     * gets that one category added (and marked in the form), so it stays selected and saving another field does not
     * clear it; no other inactive category is offered. The validation rules are unchanged.
     */
    private function formData(Product $product): array
    {
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get();
        $inactiveCategory = $product->category_id !== null && ! $categories->contains('id', $product->category_id)
            ? Category::query()->whereKey($product->category_id)->first()
            : null;

        if ($inactiveCategory) {
            $categories->prepend($inactiveCategory);
        }

        return [
            'product' => $product,
            'vendors' => Vendor::query()->with('profile')->orderBy('name')->get(),
            'categories' => $categories,
            'inactiveCategoryId' => $inactiveCategory?->id,
            'statusKeys' => $this->statusKeys(),
            'fieldLabels' => $this->formAttributes(),
            'gallery' => $this->galleryFormData($product),
        ];
    }

    /**
     * P5-C: what the gallery editor of the form starts from. Editing: the product's images in their order, each
     * marked when its file is not in the vendor's own library (a legacy image, which can be kept, moved or removed but
     * not added again), and the fingerprint the P5-B sync compares. After a refused save the submitted list is shown
     * again (media references resolved in the vendor's library only, unknown ones dropped) with the fingerprint the
     * form was opened with, so a stale list stays refused until the page is reloaded.
     *
     * @return array<string, mixed>
     */
    private function galleryFormData(Product $product): array
    {
        $editing = $product->exists;
        $vendorId = (int) ($editing ? $product->vendor_id : old('vendor_id'));
        $images = $editing ? $product->images()->get() : collect();

        // P5-C-FIX-02 (D-3): a readable name for each image: its alternative text, else the original name of the
        // vendor library image with that exact path, else the file name. Only names are read; no path is shown.
        $originals = $images->isEmpty() ? collect() : VendorMediaPath::scopeToVendor(Media::query()->images(), (int) $product->vendor_id)
            ->whereIn('file_path', $images->pluck('path')->all())
            ->pluck('file_original_name', 'file_path');

        $items = $images
            ->map(fn (ProductImage $image) => [
                'ref' => 'existing:'.$image->id,
                'url' => $this->safeImageUrl($image->path),
                'name' => trim((string) $image->alt_text) !== '' ? trim($image->alt_text)
                    : (trim((string) ($originals[$image->path] ?? '')) !== '' ? trim($originals[$image->path]) : basename($image->path)),
                'alt' => (string) $image->alt_text,
                'legacy' => preg_match('#^vendors/media/'.(int) $product->vendor_id.'/[^/\\\\]+$#', $image->path) !== 1,
            ])->values();
        $restored = (bool) old('gallery_sync');

        if ($restored) {
            $refs = collect((array) old('gallery', []))
                ->filter(fn ($ref) => is_string($ref) && preg_match('/^(existing|media):[1-9][0-9]{0,18}$/', $ref) === 1)
                // P5-HARDEN-01: never cut to six; a refused longer list comes back whole, so nothing is dropped silently.
                ->unique()->values();
            $mediaIds = $refs->filter(fn (string $ref) => str_starts_with($ref, 'media:'))->map(fn (string $ref) => (int) substr($ref, 6))->values()->all();
            $media = $vendorId > 0 && $mediaIds !== [] ? $this->vendorImages($vendorId, $mediaIds)->get()->keyBy('id') : collect();
            $existing = $items->keyBy('ref');

            $items = $refs->map(function (string $ref) use ($existing, $media) {
                if (str_starts_with($ref, 'existing:')) {
                    return $existing[$ref] ?? null;
                }

                $item = $media[(int) substr($ref, 6)] ?? null;

                return $item ? $this->galleryMediaItem($item) : null;
            })->filter()->values();
        }

        return [
            'editing' => $editing,
            'max' => 6,
            'items' => $items->all(),
            'restored' => $restored,
            'fingerprint' => $editing ? (string) ($restored ? old('gallery_fingerprint', '') : self::galleryFingerprint($product)) : null,
            'mediaUrl' => $editing ? route('dashboard.products.vendor-media.edit', $product) : route('dashboard.products.vendor-media.create'),
        ];
    }

    /** @return array{ref: string, url: ?string, name: string, alt: string, legacy: bool} */
    private function galleryMediaItem(Media $item): array
    {
        return [
            'ref' => 'media:'.$item->id,
            'url' => $item->url,
            'name' => (string) ($item->file_original_name ?: '#'.$item->id),
            'alt' => (string) $item->alt,
            'legacy' => false,
        ];
    }

    /** P5-C: one page of a vendor's own images (the canonical rule of P3), newest first, P3's fields without paths. */
    private function vendorMediaPage(Request $request, int $vendorId): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $perPage = (int) $request->input('per_page', 24);
        $paginator = VendorMediaPath::scopeToVendor(Media::query()->images(), $vendorId)
            ->search($request->input('search'))
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage > 0 && $perPage <= 100 ? $perPage : 24);

        return response()->json([
            'data' => array_map(fn (Media $item) => [
                'id' => $item->id,
                'url' => $item->url,
                'file_original_name' => $item->file_original_name,
                'alt' => $item->alt,
                'title' => $item->title,
                'width' => $item->width,
                'height' => $item->height,
            ], $paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * AD-UI-PROD-M7-FIX-01: the field names of the form, shown as its labels and used as the attribute names of the
     * validation messages (not "stock_quantity"). Product-specific keys, read without auto-creating rows.
     *
     * @return array<string, string>
     */
    private function formAttributes(): array
    {
        $labels = [
            'name' => ['dashboard.Products_Form_Field_Name', 'Product name'],
            'slug' => ['dashboard.Products_Form_Field_Slug', 'Link name (slug)'],
            'vendor_id' => ['dashboard.Products_Form_Field_Vendor', 'Vendor'],
            'category_id' => ['dashboard.Products_Form_Field_Category', 'Category'],
            'sku' => ['dashboard.Products_Form_Field_Sku', 'SKU'],
            'price' => ['dashboard.Products_Form_Field_Price', 'Regular price'],
            'compare_at_price' => ['dashboard.Products_Form_Field_Compare_Price', 'Sale price'],
            'stock_quantity' => ['dashboard.Products_Form_Field_Stock', 'Stock quantity'],
            'low_stock_threshold' => ['dashboard.Products_Form_Field_Low_Stock', 'Low stock threshold'],
            'weight' => ['dashboard.Products_Form_Field_Weight', 'Weight'],
            'short_description' => ['dashboard.Products_Form_Field_Short_Description', 'Short description'],
            'description' => ['dashboard.Products_Form_Field_Description', 'Description'],
            'status' => ['dashboard.Products_Form_Field_Status', 'Status'],
            'is_featured' => ['dashboard.Products_Form_Field_Featured', 'Featured product'],
            'is_limited_edition' => ['dashboard.Products_Form_Field_Limited', 'Limited edition'],
            // P5-C: the gallery's errors (and the refused upload / old picker fields) are named after the images field.
            'gallery' => ['dashboard.Products_Form_Images', 'Product images'],
            'gallery_fingerprint' => ['dashboard.Products_Form_Images', 'Product images'],
            'media_image_ids' => ['dashboard.Products_Form_Images', 'Product images'],
            'images' => ['dashboard.Products_Form_Images', 'Product images'],
        ];

        return array_map(fn (array $label) => $this->untranslatedDefault(...$label), $labels);
    }

    /**
     * The field names inside a sentence ("The product name field is required."): a leading capital followed by a
     * small letter is lowered; "SKU" and Arabic names are unchanged.
     *
     * @return array<string, string>
     */
    private function messageAttributes(): array
    {
        return array_map(
            fn (string $label) => preg_replace_callback('/^\p{Lu}(?=\p{Ll})/u', fn (array $match) => mb_strtolower($match[0]), $label),
            $this->formAttributes()
        );
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        // PRD-06: bounds of the columns (products: price and compare_at_price DECIMAL(10,2), weight DECIMAL(8,2),
        // stock_quantity and low_stock_threshold UNSIGNED INT), so an out-of-range value is a field error and not
        // a database error. HIRFAH-PRICE-01 (supersedes PRD-06's "compare-at above the price"): price is the
        // regular price and compare_at_price the optional sale price, so it must be lower.
        $data = $request->validate([
            // P5-A (G2): a product keeps the vendor it was created for; another vendor is refused, never ignored.
            'vendor_id' => ['required', 'exists:vendors,id', ...($product?->exists ? [Rule::in([(string) $product->vendor_id])] : [])],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            // The slug rules are unchanged, except the characters that cannot be part of the storefront
            // products/{slug} URL (path separators, query, fragment, percent, whitespace).
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:/[\s\/\\\\?#%]/u', Rule::unique('products', 'slug')->ignore($product)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'compare_at_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE, 'lt:price'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:'.self::MAX_UNSIGNED_INT],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_UNSIGNED_INT],
            'weight' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_WEIGHT],
            'weight_unit' => ['nullable', Rule::in(['kg', 'g', 't'])],
            // The same four statuses as before, without reading their (shared) labels.
            'status' => ['required', Rule::in(array_keys($this->statusKeys()))],
            'is_featured' => ['nullable', 'boolean'],
            'is_limited_edition' => ['nullable', 'boolean'],
            'highlight_tags' => ['nullable', 'array', 'max:5'],
            // P5-C-FIX-01: an empty tag field arrives as null (ConvertEmptyStringsToNull) and is simply skipped.
            'highlight_tags.*' => ['nullable', 'string', 'max:40'],
            'craft_tags' => ['nullable', 'array', 'max:5'],
            'craft_tags.*' => ['nullable', 'string', 'max:40'],
        ], [
            'vendor_id.in' => $this->untranslatedDefault('dashboard.Products_Form_Vendor_Immutable', 'The vendor of an existing product cannot be changed.'),
        ], $this->messageAttributes());

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $product);
        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 1;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_limited_edition'] = $request->boolean('is_limited_edition');
        $data['highlight_tags'] = $this->tags(array_filter($data['highlight_tags'] ?? [], 'is_string'));
        $data['craft_tags'] = $this->tags(array_filter($data['craft_tags'] ?? [], 'is_string'));
        $data['weight_unit'] = ($data['weight'] ?? null) === null ? null : ($data['weight_unit'] ?? null);
        $data['published_at'] = $data['status'] === 'active' ? ($product?->published_at ?? now()) : null;

        return $data;
    }

    /** @param array<int, string> $tags */
    private function tags(array $tags): array
    {
        return collect($tags)->map(fn (string $tag) => trim($tag))->filter()->unique()->values()->all();
    }

    /**
     * P5-A (G1): link the chosen media to the product, by path, in the order they were chosen. The ownership check
     * is repeated here, inside the write, against the product's stored vendor. A path the product already has is
     * skipped; existing images (legacy paths included) keep their order and main image.
     *
     * @param  list<int>  $mediaIds
     */
    private function storeImageUploads(Product $product, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $media = $this->vendorImages((int) $product->vendor_id, $mediaIds)->get()->keyBy('id');

        if ($media->count() !== count($mediaIds)) {
            throw ValidationException::withMessages(['media_image_ids' => $this->vendorImagesOnlyMessage()]);
        }

        foreach ($mediaIds as $id) {
            $item = $media[$id];

            if ($product->images()->where('path', $item->file_path)->exists()) {
                continue;
            }

            $hasImages = $product->images()->exists();
            $product->images()->create([
                'path' => $item->file_path,
                'alt_text' => $item->alt ?: ($item->file_original_name ?: $product->name),
                'sort_order' => (int) $product->images()->max('sort_order') + 1,
                'is_primary' => ! $hasImages,
            ]);
        }
    }

    /**
     * P5-A: the media ids of the request, validated before anything is written. G1: each one must be an image in the
     * vendor's own library (VendorMediaPath::scopeToVendor, the canonical vendors/media/{vendorId}/ path); admin media,
     * another vendor's, look-alike or non-canonical paths and missing ids are refused for every admin, super admins
     * included (uploader_id is never read). G3: the former images[] upload is refused; images come from the vendor's
     * library only. The six-image limit counts the product's existing images.
     *
     * @return list<int>
     */
    private function validateImageUploads(Request $request, int $vendorId, ?Product $product = null): array
    {
        $data = $request->validate([
            'images' => ['prohibited'],
            'media_image_ids' => ['nullable', 'array', 'max:6'],
            'media_image_ids.*' => ['nullable', 'integer', 'distinct'],
        ], [
            'images.prohibited' => $this->untranslatedDefault('dashboard.Products_Form_Images_Upload_Disabled', 'Images cannot be uploaded here. Choose them from the vendor\'s media library.'),
        ]);

        $mediaIds = collect($data['media_image_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->values()->all();

        if ($mediaIds !== [] && $this->vendorImages($vendorId, $mediaIds)->count() !== count($mediaIds)) {
            throw ValidationException::withMessages(['media_image_ids' => $this->vendorImagesOnlyMessage()]);
        }

        // P5-HARDEN-01: only an addition is limited. Without one, a product already above six (given its images
        // elsewhere) can still be saved, its images left as they are.
        if ($mediaIds !== [] && ($product?->images()->count() ?? 0) + count($mediaIds) > 6) {
            throw ValidationException::withMessages([
                'media_image_ids' => $this->untranslatedDefault('dashboard.Products_Form_Images_Max', 'A product can have up to 6 images.'),
            ]);
        }

        return $mediaIds;
    }

    /** @param  list<int>  $mediaIds */
    private function vendorImages(int $vendorId, array $mediaIds): Builder
    {
        return VendorMediaPath::scopeToVendor(Media::query()->images(), $vendorId)->whereIn('id', $mediaIds);
    }

    private function vendorImagesOnlyMessage(): string
    {
        return $this->untranslatedDefault('dashboard.Products_Form_Images_Vendor_Only', 'Each selected image must come from this vendor\'s media library.');
    }

    /**
     * PRODUCT-MEDIA-01 P5-B: the gallery of the request, or null when none was submitted (the images are then left
     * exactly as they are). gallery_sync=1 marks a submitted gallery, so an empty list (every image removed) is told
     * apart from an omitted one. gallery[] is the ordered list, at most six references, each "existing:{product
     * image id}" (an image of this product) or "media:{media id}" (an image of the product vendor's own library,
     * checked like media_image_ids, never against a vendor named in the request). gallery_fingerprint is the
     * galleryFingerprint() of the images the form displayed; it is required on update. The checks are repeated under
     * the product lock in syncGallery().
     *
     * @return array{refs: list<array{type: string, id: int}>, fingerprint: ?string}|null
     */
    private function validatedGallery(Request $request, int $vendorId, ?Product $product = null): ?array
    {
        if (! $request->has('gallery_sync')) {
            return null;
        }

        $invalid = $this->untranslatedDefault('dashboard.Products_Form_Gallery_Invalid', 'The image list could not be read. Reload the page and try again.');
        $outdated = $this->galleryOutdatedMessage();

        $data = $request->validate([
            'gallery_sync' => ['required', 'in:1'],
            'gallery' => ['nullable', 'array', 'list', 'max:6'],
            'gallery.*' => ['required', 'string', 'distinct', 'regex:/^(existing|media):[1-9][0-9]{0,18}$/'],
            'gallery_fingerprint' => [$product ? 'required' : 'nullable', 'string', 'size:40'],
        ], [
            'gallery_sync.*' => $invalid,
            'gallery.array' => $invalid,
            'gallery.list' => $invalid,
            'gallery.max' => $this->untranslatedDefault('dashboard.Products_Form_Images_Max', 'A product can have up to 6 images.'),
            'gallery.*.required' => $invalid,
            'gallery.*.string' => $invalid,
            'gallery.*.regex' => $invalid,
            'gallery.*.distinct' => $this->galleryDuplicateMessage(),
            'gallery_fingerprint.*' => $outdated,
        ]);

        // One way at a time: the gallery replaces the append-only media_image_ids path.
        if (collect($request->input('media_image_ids', []))->filter()->isNotEmpty()) {
            throw ValidationException::withMessages(['gallery' => $invalid]);
        }

        $refs = collect($data['gallery'] ?? [])->map(function (string $ref): array {
            [$type, $id] = explode(':', $ref, 2);

            return ['type' => $type, 'id' => (int) $id];
        })->values()->all();

        $existing = $this->galleryIds($refs, 'existing');
        $media = $this->galleryIds($refs, 'media');

        if ($existing !== [] && ($product === null || $product->images()->whereKey($existing)->count() !== count($existing))) {
            // P5-C-FIX-02 (D-2): an image removed since the form was opened is a stale list, not a forged reference;
            // with the fingerprint the form was opened with still current, a missing image is not this product's.
            $stale = $product !== null && is_string($data['gallery_fingerprint'] ?? null)
                && ! hash_equals(self::galleryFingerprint($product), $data['gallery_fingerprint']);

            throw ValidationException::withMessages(['gallery' => $stale ? $this->galleryOutdatedMessage() : $this->galleryNotProductMessage()]);
        }

        if ($media !== [] && $this->vendorImages($vendorId, $media)->count() !== count($media)) {
            throw ValidationException::withMessages(['gallery' => $this->vendorImagesOnlyMessage()]);
        }

        return ['refs' => $refs, 'fingerprint' => $data['gallery_fingerprint'] ?? null];
    }

    /**
     * P5-B: make the product's images exactly the given list, inside the caller's transaction and under its product
     * lock. The image rows are locked too and compared with the submitted fingerprint (a change since the page was
     * opened is refused, nothing written). Ownership is checked again: each existing reference must still be an image
     * of this product, each media reference an image of the product's stored vendor (read with a shared lock, so it
     * cannot be removed or changed until the commit). The order becomes sort_order, the first image is the main one.
     * Rows that stay are only updated when their order or main flag changes; a media reference whose path the
     * product already has reuses that row. Rows that are no longer listed are deleted, never their files. No path
     * may appear twice.
     *
     * @param  list<array{type: string, id: int}>  $refs
     */
    private function syncGallery(Product $product, array $refs, ?string $fingerprint): void
    {
        $current = $product->images()->lockForUpdate()->get();

        if ($fingerprint !== null && ! hash_equals(self::galleryFingerprintOf($current), $fingerprint)) {
            throw ValidationException::withMessages(['gallery' => $this->galleryOutdatedMessage()]);
        }

        $rows = $current->keyBy('id');
        $mediaIds = $this->galleryIds($refs, 'media');
        $media = $mediaIds === [] ? collect() : $this->vendorImages((int) $product->vendor_id, $mediaIds)->sharedLock()->get()->keyBy('id');

        if ($media->count() !== count($mediaIds)) {
            throw ValidationException::withMessages(['gallery' => $this->vendorImagesOnlyMessage()]);
        }

        $entries = [];
        $paths = [];

        foreach ($refs as $ref) {
            if ($ref['type'] === 'existing') {
                $row = $rows[$ref['id']] ?? throw ValidationException::withMessages(['gallery' => $this->galleryNotProductMessage()]);
                $entry = ['row' => $row, 'path' => $row->path, 'media' => null];
            } else {
                $item = $media[$ref['id']];
                $entry = ['row' => $current->firstWhere('path', $item->file_path), 'path' => $item->file_path, 'media' => $item];
            }

            if (isset($paths[$entry['path']])) {
                throw ValidationException::withMessages(['gallery' => $this->galleryDuplicateMessage()]);
            }

            $paths[$entry['path']] = true;
            $entries[] = $entry;
        }

        $kept = collect($entries)->pluck('row')->filter()->pluck('id')->all();
        $product->images()->whereKeyNot($kept)->delete();

        foreach ($entries as $position => $entry) {
            $order = ['sort_order' => $position, 'is_primary' => $position === 0];

            if ($entry['row']) {
                $entry['row']->fill($order);
                $entry['row']->isDirty() && $entry['row']->save();

                continue;
            }

            $product->images()->create($order + [
                'path' => $entry['path'],
                'alt_text' => $entry['media']->alt ?: ($entry['media']->file_original_name ?: $product->name),
            ]);
        }
    }

    /**
     * @param  list<array{type: string, id: int}>  $refs
     * @return list<int>
     */
    private function galleryIds(array $refs, string $type): array
    {
        return collect($refs)->where('type', $type)->pluck('id')->values()->all();
    }

    private function galleryOutdatedMessage(): string
    {
        return $this->untranslatedDefault('dashboard.Products_Form_Gallery_Outdated', 'The product images changed while this page was open. Reload the page and review them before saving.');
    }

    private function galleryNotProductMessage(): string
    {
        return $this->untranslatedDefault('dashboard.Products_Form_Gallery_Not_Product', 'An image in the list does not belong to this product.');
    }

    private function galleryDuplicateMessage(): string
    {
        return $this->untranslatedDefault('dashboard.Products_Form_Gallery_Duplicate', 'Each image can appear only once in the list.');
    }

    /**
     * AD-UI-PROD-M3: the product statuses with their labels (product-specific keys, read without auto-creating rows).
     * Since AD-UI-PROD-M7-FIX-01 the form and its validation use them too, instead of the shared keys.
     *
     * @return array<string, array{0: string, 1: string}> status => [key, English default]
     */
    private function statusKeys(): array
    {
        return [
            'draft' => ['Products_Status_Draft', 'Draft'],
            'active' => ['Products_Status_Active', 'Active'],
            'inactive' => ['Products_Status_Inactive', 'Inactive'],
            'out_of_stock' => ['Products_Status_Out_Of_Stock', 'Out of stock'],
        ];
    }

    /**
     * The single-statement write failed after validation. If it was a foreign key failure and the submitted
     * category no longer exists (deleted in between), report it exactly as the category_id rule would
     * (redirect back with input and the field error); anything else is re-thrown unchanged.
     */
    private function failUnlessCategoryVanished(QueryException $exception, array $data): never
    {
        // MySQL 1452 (child row references a missing parent) or SQLite's foreign key failure.
        $foreignKeyFailure = ($exception->errorInfo[1] ?? null) === 1452
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');

        if ($foreignKeyFailure && filled($data['category_id'] ?? null)) {
            Validator::make(['category_id' => $data['category_id']], ['category_id' => ['exists:categories,id']], [], ['category_id' => $this->messageAttributes()['category_id']])->validate();
        }

        throw $exception;
    }

    /**
     * M0-FIX: the stock the edit form displayed and the product's updated_at at that moment, sent back as hidden
     * fields. A form without them (opened before this change, or a hand-made request) cannot show what the admin
     * based the stock on, so it is refused instead of guessed.
     *
     * @return array{stock: int, version: string}
     */
    private function stockBasis(Request $request): array
    {
        $validator = Validator::make($request->only(['stock_quantity_original', 'stock_version']), [
            'stock_quantity_original' => ['required', 'integer', 'min:0', 'max:'.self::MAX_UNSIGNED_INT],
            'stock_version' => ['present', 'nullable', 'string', 'max:32'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'stock_quantity' => $this->untranslatedDefault(
                    'dashboard.Product_form_outdated',
                    'This form is out of date. Reload the page and save again.'
                ),
            ]);
        }

        return [
            'stock' => (int) $request->input('stock_quantity_original'),
            'version' => (string) $request->input('stock_version', ''),
        ];
    }

    /** The edit form's version marker of the stock: updated_at, at the precision the column stores. */
    public static function stockVersion(Product $product): string
    {
        return (string) $product->updated_at?->format('Y-m-d H:i:s');
    }

    /**
     * P5-B: the fingerprint of a product's images as the edit form displays them: the image ids in their order
     * (sort_order, then id) with the main one marked. Any addition, removal, reordering or change of the main image,
     * by an admin or by the vendor, changes it.
     */
    public static function galleryFingerprint(Product $product): string
    {
        return self::galleryFingerprintOf($product->images()->get());
    }

    /** @param  Collection<int, ProductImage>  $images */
    private static function galleryFingerprintOf(Collection $images): string
    {
        return sha1($images->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (ProductImage $image) => $image->id.($image->is_primary ? '*' : ''))
            ->implode(','));
    }

    /**
     * Back to the edit form with the other fields as the admin typed them, but without the stock fields, so the
     * form shows the current stock and a fresh basis; the admin reviews it and saves again. Nothing was written.
     */
    private function stockConflict(Request $request, Product $product, int $current): RedirectResponse
    {
        return redirect()->route('dashboard.products.edit', $product)
            ->withInput($request->except(['stock_quantity', 'stock_quantity_original', 'stock_version']))
            ->withErrors(['stock_quantity' => str_replace(':current', (string) $current, $this->untranslatedDefault(
                'dashboard.Product_stock_conflict',
                'The stock changed while this page was open (it is now :current). Review it and save again.'
            ))]);
    }

    /**
     * PRD-03: validation errors of a save go back to the given form route. Without this, Laravel redirects to
     * url()->previous(), which prefers the Referer header and can therefore point outside the application.
     */
    private function returningErrorsTo(string $form, Closure $save): void
    {
        try {
            $save();
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($form);
        }
    }

    /**
     * t() without auto-creating the key (as in CategoryManagementController): until the products translations
     * are seeded, the English default is shown but no row is written, so an Arabic row is never created with
     * English text.
     */
    private function untranslatedDefault(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }

    /**
     * AD-UI-PROD-M5 / M6: the four moderation transitions. For each: its ability, the form field of its reason, the
     * state it may start from, the columns it writes, the columns its log entry records (before / after) and its
     * result messages. Suspension touches suspended_at only (M5). Archiving touches archived_at only; restoring
     * clears archived_at and always sets status to draft, recorded even when it already was (OPEN-PROD-04B, B1:
     * published_at is kept). Neither pair touches the other pair's column.
     *
     * @return array<string, array{ability: string, field: string, allowed: Closure(Product): bool, writes: Closure(): array<string, mixed>, logs: list<string>, done: array{0: string, 1: string}, refused: array{0: string, 1: string}}>
     */
    private function moderationTransitions(): array
    {
        return [
            'suspended' => [
                'ability' => 'products.suspend', 'field' => 'reason',
                'allowed' => fn (Product $product) => ! $product->isSuspended(),
                'writes' => fn () => ['suspended_at' => now()],
                'logs' => ['suspended_at'],
                'done' => ['dashboard.Product_suspended', 'The product was suspended. It can no longer be sold.'],
                'refused' => ['dashboard.Product_already_suspended', 'This product is already suspended. Nothing was changed.'],
            ],
            'unsuspended' => [
                'ability' => 'products.unsuspend', 'field' => 'reason',
                'allowed' => fn (Product $product) => $product->isSuspended(),
                'writes' => fn () => ['suspended_at' => null],
                'logs' => ['suspended_at'],
                'done' => ['dashboard.Product_unsuspended', 'The suspension was lifted.'],
                'refused' => ['dashboard.Product_not_suspended', 'This product is not suspended. Nothing was changed.'],
            ],
            'archived' => [
                'ability' => 'products.archive', 'field' => 'archive_reason',
                'allowed' => fn (Product $product) => ! $product->isArchived(),
                'writes' => fn () => ['archived_at' => now()],
                'logs' => ['archived_at'],
                'done' => ['dashboard.Product_archived', 'The product was archived. It can no longer be sold, and all its data is kept.'],
                'refused' => ['dashboard.Product_already_archived', 'This product is already archived. Nothing was changed.'],
            ],
            'restored' => [
                'ability' => 'products.restore', 'field' => 'archive_reason',
                'allowed' => fn (Product $product) => $product->isArchived(),
                'writes' => fn () => ['archived_at' => null, 'status' => 'draft'],
                'logs' => ['archived_at', 'status'],
                'done' => ['dashboard.Product_restored', 'The product was restored as a draft. It is not on sale until it is published again.'],
                'refused' => ['dashboard.Product_not_archived', 'This product is not archived. Nothing was changed.'],
            ],
        ];
    }

    /**
     * AD-UI-PROD-M5 / M6: one moderation change. In this order: the ability (before the product is read), the reason
     * (trimmed, 3 to 1000 characters, in the transition's own field), then one transaction that reads the product
     * row locked FOR UPDATE, checks its real state, writes the transition's columns only (updated_at follows) and
     * writes the log entry. The state and its log entry are committed together or not at all (G-5); the row lock
     * orders the change with checkout, which re-checks sellability on the rows it has locked (G-2, C-13).
     *
     * A refused transition (already in that state) writes nothing. A database failure (a deadlock that persists after
     * the transaction's bounded retries included) writes nothing either and is reported as such, never as a success.
     * Every outcome redirects to the product's moderation tab, never to the Referer.
     */
    private function moderate(Request $request, int $productId, string $action): RedirectResponse
    {
        $transition = $this->moderationTransitions()[$action];
        $this->authorizeAbility($request, $transition['ability']);

        $tab = fn (int $id) => route('dashboard.products.show', $id).'#product-panel-moderation';
        $field = $transition['field'];

        $reason = $request->input($field);
        $validator = Validator::make(
            [$field => is_string($reason) ? trim($reason) : $reason],
            [$field => ['required', 'string', 'min:3', 'max:1000']],
            [],
            [$field => $this->untranslatedDefault('dashboard.Product_Attribute_Reason', 'reason')]
        );

        if ($validator->fails()) {
            // The product is not read here; an unknown id still ends in a 404 on the details page it is sent to.
            throw (new ValidationException($validator))->redirectTo($tab($productId));
        }

        $reason = $validator->validated()[$field];
        $admin = $request->user('admin');
        $logged = fn ($value) => $value instanceof CarbonInterface ? $value->toIso8601String() : $value;

        try {
            $outcome = DB::transaction(function () use ($productId, $action, $transition, $reason, $admin, $logged): string {
                $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

                if (! $transition['allowed']($product)) {
                    return 'refused';
                }

                $before = array_map($logged, $product->only($transition['logs']));
                $product->forceFill($transition['writes']())->save();
                $after = array_map($logged, $product->only($transition['logs']));

                ProductAdminAction::create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'action' => $action,
                    'reason' => $reason,
                    'changes' => collect($transition['logs'])->mapWithKeys(fn (string $column) => [$column => [$before[$column], $after[$column]]])->all(),
                ]);

                return 'done';
            }, attempts: 3);
        } catch (QueryException $exception) {
            report($exception); // The cause stays in the log; the admin is told nothing was saved.

            return redirect()->to($tab($productId))->withInput($request->only($field))->with('danger', $this->untranslatedDefault(
                'dashboard.Product_moderation_failed',
                'The change could not be saved, so nothing was changed. Please try again.'
            ));
        }

        [$key, $default] = $transition[$outcome];

        return redirect()->to($tab($productId))->with($outcome === 'done' ? 'success' : 'danger', $this->untranslatedDefault($key, $default));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        abort_unless($this->hasAbility($request, $ability), 403);
    }

    private function hasAbility(Request $request, string $ability): bool
    {
        $admin = $request->user('admin');

        return (bool) ($admin?->isSuperAdmin() || $admin?->hasAbility($ability));
    }

    /**
     * AD-UI-PROD-M4 (C-11): product_images.path is free text from the vendor (no upload, no defined meaning). Only a
     * relative path of plain segments, with an image extension, that exists on the public disk is shown, through the
     * disk's own URL; the raw value never goes into src. Refused: any scheme (http:, javascript:, data:, …), absolute
     * or protocol-relative paths, empty, "." or ".." segments, backslashes, percent-encoding, control and other
     * unusual characters, and other extensions (svg included).
     */
    private function safeImageUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '' || strlen($path) > 2048) {
            return null;
        }

        if (! preg_match('#^[\p{L}\p{N}_\-]+(?:\.[\p{L}\p{N}_\-]+)*(?:/[\p{L}\p{N}_\-]+(?:\.[\p{L}\p{N}_\-]+)*)*$#u', $path)) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable) {
            return null;
        }
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
