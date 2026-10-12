<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Categories\CategoryDeleteConfirmation;
use App\Support\Categories\CategorySlug;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Palgoal\MediaLibrary\Models\Media;

class CategoryManagementController extends Controller
{
    private const ATTEMPTS = 3;

    /** categories.sort_order is INT UNSIGNED (AUD-01). */
    private const MAX_SORT_ORDER = 4294967295;

    /** The image types the media library accepts (EnsureSafeMediaUpload). */
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'categories.view');

        // AD-UI-CAT-01E-3A: each subcategory is listed right after its main category (the parent's order, then
        // the main category first, then its subcategories by their own order). Search is a literal match: the
        // LIKE wildcards typed by the admin are escaped. level=root|child filters by type.
        $search = trim((string) $request->input('search', ''));
        $level = in_array($request->input('level'), ['root', 'child'], true) ? $request->input('level') : null;
        $status = in_array($request->input('is_active'), ['0', '1'], true) ? $request->input('is_active') : null;

        $categories = Category::query()
            ->select('categories.*')
            ->leftJoin('categories as parent_order', 'parent_order.id', '=', 'categories.parent_id')
            ->with(['parent', 'imageMedia'])
            ->withCount(['products', 'children'])
            ->when($search !== '', fn ($query) => $query->whereRaw("categories.name like ? escape '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
            // AUD-06: only the two values of the status filter; anything else is ignored (not read as "inactive").
            ->when($status !== null, fn ($query) => $query->where('categories.is_active', $status === '1'))
            ->when($level === 'root', fn ($query) => $query->whereNull('categories.parent_id'))
            ->when($level === 'child', fn ($query) => $query->whereNotNull('categories.parent_id'))
            ->orderByRaw('COALESCE(parent_order.sort_order, categories.sort_order)')
            ->orderByRaw('COALESCE(parent_order.name, categories.name)')
            ->orderByRaw('COALESCE(categories.parent_id, categories.id)')
            ->orderByRaw('CASE WHEN categories.parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->paginate(15)
            ->withQueryString();

        // N-CAT-1: the list's confirm dialog carries a server-issued confirmation per deletable row.
        $deleteConfirmations = [];

        if ($this->hasAbility($request, 'categories.delete')) {
            foreach ($categories as $category) {
                if ($category->products_count === 0 && $category->children_count === 0) {
                    $deleteConfirmations[$category->id] = $this->confirmations()->issue($category->id, $this->adminId($request));
                }
            }
        }

        // AUD-03 / AUD-06: the list knows whether a filter really applies (ignored values do not count).
        $filtered = $search !== '' || $level !== null || $status !== null;

        return view('dashboard.categories.index', compact('categories', 'deleteConfirmations', 'filtered'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'categories.create');

        return view('dashboard.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.create');
        $form = route('dashboard.categories.create');

        return $this->returningErrorsSafely($form, function () use ($request, $form): RedirectResponse {
            $data = $this->validatedData($request);
            $image = $this->imageChange($request);

            try {
                // 3 attempts: a deadlock with a concurrent media delete is retried (AD-UI-CAT-M4-D5).
                $category = DB::transaction(function () use ($data, $image): Category {
                    $this->lockAndCheckHierarchy(null, $data['parent_id'] ?? null);

                    $category = new Category($data);
                    $this->applyImage($category, $image);
                    $category->save();

                    return $category;
                }, self::ATTEMPTS);
            } catch (QueryException $exception) {
                return $this->refusedSave($exception, $data, $image, $form);
            }

            return redirect()->to($this->afterSaveUrl($request, $category))->with('success', t('dashboard.Category_saved', 'Category saved successfully.'));
        });
    }

    public function edit(Request $request, Category $category): View
    {
        $this->authorizeAbility($request, 'categories.edit');

        return view('dashboard.categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.edit');
        $form = route('dashboard.categories.edit', $category);

        return $this->returningErrorsSafely($form, function () use ($request, $category, $form): RedirectResponse {
            $data = $this->validatedData($request, $category);
            $image = $this->imageChange($request, $category);

            try {
                DB::transaction(function () use ($category, $data, $image): void {
                    $locked = $this->lockAndCheckHierarchy($category, $data['parent_id'] ?? null);
                    $this->checkDeactivation($locked, (bool) $data['is_active']);

                    $locked->fill($data);
                    $this->applyImage($locked, $image);
                    $locked->save();
                }, self::ATTEMPTS);
            } catch (QueryException $exception) {
                return $this->refusedSave($exception, $data, $image, $form);
            }

            return redirect()->to($this->afterSaveUrl($request, $category))->with('success', t('dashboard.Category_saved', 'Category saved successfully.'));
        });
    }

    /**
     * N-CAT-1: the delete step without JavaScript. Shows the category, a warning and Confirm / Cancel, with a
     * fresh confirmation; a category that cannot be deleted shows why, and no confirm button. Never deletes.
     * A category deleted meanwhile leads back to the list with a message (no 404).
     */
    public function confirmDelete(Request $request, string $category): View|RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.delete');

        $found = ctype_digit($category) ? Category::query()->withCount(['products', 'children'])->find((int) $category) : null;

        if (! $found) {
            return redirect()->to($this->deleteExitUrl($request))->with('danger', $this->goneMessage());
        }

        $blockedBy = $found->products_count > 0 ? 'products' : ($found->children_count > 0 ? 'children' : null);

        return view('dashboard.categories.delete', [
            'category' => $found,
            'blockedMessage' => $blockedBy ? $this->deletionBlockedMessage($blockedBy) : null,
            'confirmation' => $blockedBy ? null : $this->confirmations()->issue($found->id, $this->adminId($request)),
        ]);
    }

    /**
     * DEC-CAT-Q3: only an empty category (no products of any status, no children) can be deleted.
     * The row is locked for the check and the delete, so a product or child created concurrently either
     * commits first (and is seen by the check) or waits and then fails its own foreign key check.
     *
     * N-CAT-1: only with a live confirmation the server issued for this category and this admin
     * (CategoryDeleteConfirmation); without one, nothing is deleted and the admin is sent to the confirmation
     * page. The category is resolved here, not by route binding, so one deleted meanwhile (before or during
     * this request) is reported on the list instead of a 404 or a 500.
     */
    public function destroy(Request $request, string $category): RedirectResponse
    {
        $this->authorizeAbility($request, 'categories.delete');

        $id = ctype_digit($category) ? (int) $category : 0;
        $confirmed = $this->confirmations()->consume($request->input('confirmation'), $id, $this->adminId($request));

        if (! Category::query()->whereKey($id)->exists()) {
            return redirect()->to($this->deleteExitUrl($request))->with('danger', $this->goneMessage());
        }

        if (! $confirmed) {
            $message = $this->untranslatedDefault('dashboard.Category_Delete_Confirmation_Invalid', 'The deletion was not confirmed or the confirmation expired. Confirm it again.');

            return redirect()->route('dashboard.categories.delete', $id)->with('danger', $message);
        }

        try {
            $blockedBy = DB::transaction(function () use ($id): ?string {
                $locked = Category::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($locked->products()->exists()) {
                    return 'products';
                }

                if ($locked->children()->exists()) {
                    return 'children';
                }

                $locked->delete();

                return null;
            });
        } catch (ModelNotFoundException) {
            // Deleted by another request between the check above and the lock.
            return redirect()->to($this->deleteExitUrl($request))->with('danger', $this->goneMessage());
        } catch (QueryException $exception) {
            // The database refused the delete (ON DELETE RESTRICT): a row now references the category.
            if (! $this->isForeignKeyViolation($exception)) {
                throw $exception;
            }

            $blockedBy = 'linked';
        }

        if ($blockedBy !== null) {
            // Back to the list or the confirmation page the admin came from (AUD-05: only this module's pages).
            // Without categories.view, back to this category's confirmation page (which shows why), never to the list.
            $back = $this->hasAbility($request, 'categories.view') ? $this->moduleUrl(route('dashboard.categories.index')) : route('dashboard.categories.delete', $id);

            return redirect()->to($back)->with('danger', $this->deletionBlockedMessage($blockedBy));
        }

        return redirect()->to($this->deleteExitUrl($request))->with('success', t('dashboard.Category_deleted', 'Category deleted successfully.'));
    }

    /**
     * AUD-04b (independent abilities): where the delete flow ends. With categories.view, the list (listUrl(),
     * keeping its filters, AUD-05). Without it, the dashboard home, which any signed-in admin may open
     * (routes/web.php, dashboard.home): never the list, which would refuse them. Never from the Referer.
     */
    private function deleteExitUrl(Request $request): string
    {
        return $this->hasAbility($request, 'categories.view') ? $this->listUrl() : route('dashboard.home');
    }

    /** The list the admin came from (keeping its filters and page), or the list itself after the confirmation page. */
    private function listUrl(): string
    {
        $list = route('dashboard.categories.index');

        return $this->moduleUrl($list, (string) parse_url($list, PHP_URL_PATH));
    }

    /**
     * AUD-05: where to send the admin back to. The previous URL (Referer, else the session's) is used only when
     * it is a page of this module on this application's own origin: the same scheme, host and port as
     * url('/'), compared exactly; no credentials, no control characters, backslashes or dot segments; the path
     * is this module's (or exactly $exactPath). Anything else (another or look-alike host, //host,
     * another scheme or port, a missing or unparsable Referer) gives $fallback.
     */
    private function moduleUrl(string $fallback, ?string $exactPath = null): string
    {
        $candidate = url()->previous();
        $parts = parse_url($candidate);
        $root = parse_url(url('/'));

        if (! is_array($parts) || ! is_array($root) || preg_match('/[\x00-\x20\x7F\\\\]/', $candidate) || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $rootScheme = strtolower($root['scheme'] ?? '');
        $defaultPort = fn (string $scheme) => $scheme === 'https' ? 443 : 80;

        if ($scheme === '' || $scheme !== $rootScheme
            || strtolower($parts['host'] ?? '') !== strtolower($root['host'] ?? '')
            || ($parts['port'] ?? $defaultPort($scheme)) !== ($root['port'] ?? $defaultPort($rootScheme))) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        $module = (string) parse_url(route('dashboard.categories.index'), PHP_URL_PATH);

        if (preg_match('#(^|/)\.{1,2}(/|$)|%2e|%2f|%5c#i', $path)) {
            return $fallback;
        }

        $inModule = $exactPath !== null ? $path === $exactPath : ($path === $module || str_starts_with($path, $module.'/'));

        return $inModule ? $candidate : $fallback;
    }

    /**
     * AUD-04 (option A: the four category abilities are independent): after a successful save, a page this
     * admin may open. With categories.view, the list (as before); without it, the category's edit page when
     * the admin holds categories.edit, else a new create form (categories.create, which the save required).
     * Built from routes, never from the Referer (AUD-05). The flash message names no category.
     */
    private function afterSaveUrl(Request $request, Category $category): string
    {
        if ($this->hasAbility($request, 'categories.view')) {
            return route('dashboard.categories.index');
        }

        return $this->hasAbility($request, 'categories.edit')
            ? route('dashboard.categories.edit', $category)
            : route('dashboard.categories.create');
    }

    /** Validation errors of a save go back to the form the admin came from, or to $form (AUD-05). */
    private function returningErrorsSafely(string $form, Closure $save): RedirectResponse
    {
        try {
            return $save();
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($this->moduleUrl($form));
        }
    }

    private function goneMessage(): string
    {
        return $this->untranslatedDefault('dashboard.Category_Delete_Gone', 'This category no longer exists. It may have been deleted already.');
    }

    private function confirmations(): CategoryDeleteConfirmation
    {
        return new CategoryDeleteConfirmation(request()->session());
    }

    private function adminId(Request $request): int
    {
        return (int) $request->user('admin')->getKey();
    }

    /**
     * N-CAT-2: field names in validation messages, in the admin's language (lang/{locale}/validation.php
     * supplies the sentences; these replace :attribute).
     *
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'name' => $this->untranslatedDefault('dashboard.Category_Attribute_Name', 'category name'),
            'slug' => $this->untranslatedDefault('dashboard.Category_Attribute_Slug', 'slug'),
            'parent_id' => $this->untranslatedDefault('dashboard.Category_Attribute_Parent', 'parent category'),
            'sort_order' => $this->untranslatedDefault('dashboard.Category_Attribute_Order', 'display order'),
            'description' => $this->untranslatedDefault('dashboard.Category_Attribute_Description', 'description'),
            'image_media_id' => $this->untranslatedDefault('dashboard.Category_Attribute_Image', 'image'),
            'remove_image' => $this->untranslatedDefault('dashboard.Category_Attribute_Remove_Image', 'image removal'),
        ];
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        // AUD-02: a typed slug is compared in its canonical form (A-Z lowercased, NFC, 0-9 digits). A stored slug
        // sent back unchanged is kept exactly as it is and not re-checked against the format: existing slugs are
        // never rewritten, and a category with an older slug can still be edited.
        $typedSlug = $request->input('slug');
        $keepsSlug = $category?->exists && is_string($typedSlug) && $typedSlug === $category->slug;

        if (is_string($typedSlug) && ! $keepsSlug) {
            $request->merge(['slug' => CategorySlug::normalize($typedSlug)]);
        }

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => array_merge(
                ['nullable', 'string', 'max:'.CategorySlug::MAX_LENGTH],
                $keepsSlug ? [] : [$this->slugFormatRule()],
                [Rule::unique('categories', 'slug')->ignore($category)],
            ),
            'description' => ['nullable', 'string'],
            // categories.image (legacy path) is no longer written from requests (AD-UI-CAT-M4-D4); the stored
            // value is kept. The image is image_media_id, handled by imageChange().
            // AUD-01: the column is INT UNSIGNED; a larger value is a validation error, never a database error.
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_SORT_ORDER],
        ], [], $this->attributeNames());

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $category);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        // DEC-CAT-Q16 (AD-UI-CAT-01D-3): activation is not managed from the admin UI. is_active is never read
        // from the request: a new category is always active, an update keeps the stored state. The column and
        // checkDeactivation() stay for a future dedicated action.
        $data['is_active'] = $category?->exists ? (bool) $category->is_active : true;

        return $data;
    }

    /**
     * DEC-CAT-Q13: two levels only. A root has no parent; a child's parent must be a root; a category that
     * has children stays a root; nothing is its own parent (so no cycle can form).
     *
     * The category and the chosen parent are locked together (FOR UPDATE, ascending id, so concurrent moves
     * take locks in the same order) and re-read under the lock. Two moves that would together create a
     * cycle or a third level (A under B while B under A; C under A while A moves under B) are serialised:
     * the second one sees the first one's result and is refused. A child inserted concurrently under the
     * edited category has to take a lock on it for its foreign key check, so it waits for this transaction.
     */
    private function lockAndCheckHierarchy(?Category $category, mixed $parentId): ?Category
    {
        $parentId = $parentId === null || $parentId === '' ? null : (int) $parentId;
        $ids = array_values(array_unique(array_filter([$category?->getKey(), $parentId])));

        $locked = $ids === []
            ? collect()
            : Category::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $self = $category ? $locked->get($category->getKey()) : null;

        if ($category && ! $self) {
            abort(404); // Deleted meanwhile.
        }

        if ($parentId === null) {
            return $self; // A root (new or moved up): always allowed.
        }

        $fail = fn (string $key, string $default) => throw ValidationException::withMessages([
            'parent_id' => $this->untranslatedDefault($key, $default),
        ]);

        if ($self && $parentId === (int) $self->getKey()) {
            $fail('dashboard.Category_parent_self', 'A category cannot be its own parent.');
        }

        $parent = $locked->get($parentId);

        if (! $parent) {
            $fail('dashboard.Category_parent_missing', 'The selected parent category no longer exists.');
        }

        if ($parent->parent_id !== null) {
            $fail('dashboard.Category_parent_not_root', 'Choose a main category as the parent. Subcategories cannot have subcategories of their own.');
        }

        if ($self && $self->children()->exists()) {
            $fail('dashboard.Category_parent_has_children', 'This category has subcategories, so it cannot be moved under another category.');
        }

        return $self;
    }

    /**
     * DEC-CAT-Q15 (AD-UI-CAT-01D-1, 01D-2 option B): an active category cannot be switched off while it has
     * products of any status, directly or, for a main category, through any of its subcategories
     * (Category::hasAssociatedProducts). Editing without switching off, and reactivating, are not affected.
     *
     * Since DEC-CAT-Q16 the requested state on update is always the stored one, so this check cannot trigger
     * from the admin UI or its HTTP requests; it stays on the update path as a safeguard and for a future
     * dedicated activation action.
     *
     * Runs under the lock lockAndCheckHierarchy holds on this category's row: a product cannot be added to it,
     * and a subcategory cannot be created under it or moved into it (both lock this row), until the update
     * commits. A product added at the same moment to an EXISTING subcategory only locks that subcategory, so it
     * can still land just after the check; that residual race is documented (decision Q14), not closed here.
     */
    private function checkDeactivation(Category $locked, bool $active): void
    {
        if ($active || ! $locked->is_active || ! $locked->hasAssociatedProducts()) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => $this->untranslatedDefault('dashboard.Category_deactivate_has_products', 'This category has products and cannot be deactivated.'),
        ]);
    }

    /**
     * Parents offered by the form: main categories only, never the category itself; a category that has
     * children is offered none. The current parent stays listed so the form never changes it silently;
     * the server check above still refuses it if it is not a main category.
     */
    private function parentOptions(?Category $category = null)
    {
        if ($category?->exists && $category->children()->exists()) {
            return collect();
        }

        return Category::query()
            ->where(fn ($query) => $query->whereNull('parent_id')->when($category?->parent_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->when($category?->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->orderBy('name')
            ->get();
    }

    /**
     * AD-UI-CAT-M4-B (decisions M4-D2, M4-D3): what the request asks for the category image.
     *
     *  - image_media_id absent, or empty without remove_image: no change (null is returned);
     *  - image_media_id equal to the current image: no change, and no media ability is needed;
     *  - remove_image=1: unlink (['id' => null]); categories.edit is enough, the media item and its file stay;
     *  - another image_media_id: link or replace (['id' => <id>]); needs media.view (403 otherwise), and the
     *    item must exist and be an allowed image (422 otherwise);
     *  - both remove_image=1 and an image_media_id: 422.
     *
     * The id is checked again by the foreign key when the category is saved (see vanishedRelation()).
     *
     * @return array{id: ?int}|null
     */
    private function imageChange(Request $request, ?Category $category = null): ?array
    {
        $request->validate([
            'remove_image' => ['nullable', 'boolean'],
            'image_media_id' => ['nullable', 'integer', 'min:1'],
        ], [], $this->attributeNames());

        $remove = $request->boolean('remove_image');
        $id = $request->filled('image_media_id') ? (int) $request->input('image_media_id') : null;

        if ($remove && $id !== null) {
            throw ValidationException::withMessages([
                'image_media_id' => $this->untranslatedDefault('dashboard.Category_image_conflict', 'Choose a new image or remove the current one, not both.'),
            ]);
        }

        if ($remove) {
            return ['id' => null];
        }

        if ($id === null || ($category?->exists && (int) $category->image_media_id === $id)) {
            return null;
        }

        abort_unless($request->user('admin')?->can('viewAny', Media::class), 403);

        $media = Media::query()->find($id);

        if (! $media) {
            throw ValidationException::withMessages([
                'image_media_id' => $this->untranslatedDefault('dashboard.Category_image_missing', 'The selected image no longer exists. Choose another image.'),
            ]);
        }

        if ($media->file_type !== 'image' || ! in_array($media->mime_type, self::IMAGE_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'image_media_id' => $this->untranslatedDefault('dashboard.Category_image_invalid', 'The selected file is not a supported image (JPG, PNG, GIF or WebP).'),
            ]);
        }

        return ['id' => (int) $media->getKey()];
    }

    /** image_media_id is assigned explicitly here, never through mass assignment. */
    private function applyImage(Category $category, ?array $image): void
    {
        if ($image !== null) {
            $category->image_media_id = $image['id'];
        }
    }

    /**
     * The database refused the save.
     *
     * - The slug was taken by a concurrent save after the unique check (AUD-02): reported on the slug field,
     *   as the unique rule would have.
     * - A foreign key: the error number alone does not say which reference failed (and SQLite does not name
     *   the key), so the current state is read again: the chosen parent or the chosen image that no longer
     *   exists is reported on its own field. A foreign key failure neither of them explains is not hidden
     *   behind a misleading message; it is re-thrown, like any other database error.
     */
    private function refusedSave(QueryException $exception, array $data, ?array $image, string $form): RedirectResponse
    {
        $back = $this->moduleUrl($form);

        if ($this->isDuplicateSlug($exception)) {
            return redirect()->to($back)->withInput()->withErrors([
                'slug' => __('validation.unique', ['attribute' => $this->attributeNames()['slug']]),
            ]);
        }

        if (! $this->isForeignKeyViolation($exception)) {
            throw $exception;
        }

        $errors = [];
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null && $parentId !== '' && ! Category::query()->whereKey($parentId)->exists()) {
            $errors['parent_id'] = $this->untranslatedDefault('dashboard.Category_parent_missing', 'The selected parent category no longer exists.');
        }

        if (($image['id'] ?? null) !== null && ! Media::query()->whereKey($image['id'])->exists()) {
            $errors['image_media_id'] = $this->untranslatedDefault('dashboard.Category_image_missing', 'The selected image no longer exists. Choose another image.');
        }

        if ($errors === []) {
            throw $exception;
        }

        return redirect()->to($back)->withInput()->withErrors($errors);
    }

    /** MySQL 1062 or SQLite's UNIQUE failure on categories.slug (the unique index categories_slug_unique). */
    private function isDuplicateSlug(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return (($exception->errorInfo[1] ?? null) === 1062 && str_contains($message, 'categories_slug_unique'))
            || str_contains($message, 'UNIQUE constraint failed: categories.slug');
    }

    /** AUD-02: Arabic and/or English letters and digits, single hyphens between segments (CategorySlug). */
    private function slugFormatRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! CategorySlug::isValid($value)) {
                $fail($this->untranslatedDefault('dashboard.Category_slug_invalid', 'Use Arabic or English letters and numbers only, with single hyphens between words.'));
            }
        };
    }

    private function deletionBlockedMessage(string $reason): string
    {
        return match ($reason) {
            'products' => $this->untranslatedDefault('dashboard.Category_delete_has_products', 'This category cannot be deleted because it has associated products.'),
            'children' => $this->untranslatedDefault('dashboard.Category_delete_has_children', 'This category has subcategories and cannot be deleted. Move or delete them first.'),
            default => $this->untranslatedDefault('dashboard.Category_delete_in_use', 'This category is in use and cannot be deleted.'),
        };
    }

    /**
     * MySQL 1451/1452 (parent row in use / parent row missing) or SQLite's foreign key failure.
     * Any other database error is not a category relation problem and is re-thrown.
     */
    private function isForeignKeyViolation(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;

        return in_array($driverCode, [1451, 1452], true)
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
    }

    /**
     * t() without auto-creating the key: until its translations are seeded, the English default is shown
     * but no row is written, so an Arabic row can never be created with English text.
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
     * AUD-02: a slug made from the name, keeping Arabic letters (CategorySlug::fromName), made unique with -2,
     * -3... within the column's 255 characters. A name that gives no usable slug (symbols only, for example)
     * is a validation error on the slug field: no generic slug is invented.
     */
    private function uniqueSlug(string $value, ?Category $category = null): string
    {
        $base = CategorySlug::fromName($value);

        if ($base === '') {
            throw ValidationException::withMessages([
                'slug' => $this->untranslatedDefault('dashboard.Category_slug_not_generated', 'A slug cannot be made from this name. Enter a slug.'),
            ]);
        }

        $slug = $base;
        $counter = 2;

        while (Category::query()
            ->when($category?->exists, fn ($query) => $query->whereKeyNot($category->id))
            ->where('slug', $slug)
            ->exists()
        ) {
            $suffix = '-'.$counter;
            $slug = CategorySlug::truncate($base, CategorySlug::MAX_LENGTH - strlen($suffix)).$suffix;
            $counter++;
        }

        return $slug;
    }
}
