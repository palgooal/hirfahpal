<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\ListMediaLibraryRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VendorProfile;
use App\Support\Media\VendorMediaPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Palgoal\MediaLibrary\Models\Media;

/** A vendor-scoped facade over the shared Media Library table. */
class MediaLibraryController extends Controller
{
    /** VENDOR-MEDIA-02B: the most images one upload request may carry. */
    public const MAX_FILES_PER_UPLOAD = 10;

    /** MYSTORE-MEDIA-01: the refusal of deleting an image that is a store logo. */
    public const USED_AS_LOGO = 'This image is currently used as the store logo.';

    /** VENDOR-MEDIA-02C: a deletion that hits a deadlock is retried, as in the admin library. */
    private const DELETE_ATTEMPTS = 3;

    /** VENDOR-MEDIA-03A: the page size when a page is asked for without per_page. */
    private const PER_PAGE = 24;

    /**
     * VENDOR-MEDIA-03A: the vendor's own images, newest first (id breaks equal timestamps, so pages never overlap or
     * skip). Ownership is the canonical folder rule (VendorMediaPath::scopeToVendor, as attaching uses), applied
     * before the search, which is its own where group and so can only narrow it. Without page, per_page or search the
     * answer is the whole library as {images}, as the product editor reads it; with any of them, one page of it as
     * {images, pagination}.
     */
    public function index(ListMediaLibraryRequest $request): JsonResponse
    {
        $query = VendorMediaPath::scopeToVendor(Media::query()->images(), (int) $request->user('vendor')->id)
            ->where(fn (Builder $query) => $this->search($query, $request->validated('search')))
            ->latest()
            ->orderByDesc('id');

        if (! $request->wantsPage()) {
            return response()->json(['images' => $query->get()->map(fn (Media $item) => $this->item($item))]);
        }

        $paginator = $query->paginate((int) $request->validated('per_page', self::PER_PAGE));

        return response()->json([
            'images' => array_map(fn (Media $item) => $this->item($item), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // VENDOR-MEDIA-02B: at most MAX_FILES_PER_UPLOAD images per request (the library itself has no limit). The
        // whole request is refused before anything is stored.
        $data = $request->validate(
            ['files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES_PER_UPLOAD], 'files.*' => ['file', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240']],
            ['files.max' => $this->text('vendor.Media_Upload_Max_Files', 'You can upload up to :max images at a time.')]
        );
        $vendor = $request->user('vendor');
        $uploaded = [];

        foreach ($data['files'] as $file) {
            // G7-C2-4: the folder without its trailing slash, so the stored path is canonical
            // (vendors/media/{id}/{uuid}.ext, not ...//...): only canonical paths can be attached to a product.
            $path = $file->storeAs(rtrim($this->prefix($vendor->id), '/'), Str::uuid().'.'.strtolower($file->extension()), 'public');
            $uploaded[] = Media::create([
                'file_name' => basename($path), 'file_original_name' => $file->getClientOriginalName(), 'file_path' => $path,
                'file_extension' => strtolower($file->extension()), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
            ]);
        }

        return response()->json(['images' => collect($uploaded)->map(fn (Media $item) => $this->item($item))], 201);
    }

    /**
     * VENDOR-MEDIA-02C: race-safe deletion. One transaction locks the media row FOR UPDATE first, then checks, still
     * inside it, that the row is the vendor's and that no product image and no store logo (MYSTORE-MEDIA-01) uses its
     * path (locking reads, so they see every committed attachment), and deletes the row. Every attachment re-reads that row with a shared lock in its
     * own transaction (VendorLibraryImage::lockedInLibrary(), and the admin gallery's sharedLock), so the two are
     * serialised on it: an attachment that got there first makes this a 409; one that comes later finds no row and is
     * refused. The file is removed only after the commit, so a refused or rolled back deletion never touches it.
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        $prefix = $this->prefix($request->user('vendor')->id);
        abort_unless(str_starts_with((string) $media->file_path, $prefix), 404);

        $file = DB::transaction(function () use ($media, $prefix): array {
            $locked = Media::query()->whereKey($media->getKey())->lockForUpdate()->first();

            // Deleted meanwhile (by a concurrent request): answered like any media that is not the vendor's.
            abort_unless($locked && str_starts_with((string) $locked->file_path, $prefix), 404);
            abort_if(ProductImage::query()->where('path', $locked->file_path)->sharedLock()->exists(), 409, 'This image is currently used by a product.');
            // MYSTORE-MEDIA-01: nor a store logo (ProfileLogoController sets it under a shared lock on this same row).
            abort_if(VendorProfile::query()->where('logo', $locked->file_path)->sharedLock()->exists(), 409, self::USED_AS_LOGO);
            // PRODUCT-STORY-MEDIA: nor a product's story image (the admin product form sets it under a shared lock on
            // this same row, ProductManagementController::storyImagePath()).
            abort_if(Product::query()->where('story_image_path', $locked->file_path)->sharedLock()->exists(), 409, 'This image is currently used by a product.');

            $locked->delete();

            return ['disk' => $locked->disk ?: 'public', 'path' => $locked->file_path];
        }, self::DELETE_ATTEMPTS);

        Storage::disk($file['disk'])->delete($file['path']);

        return response()->json(['message' => 'Media deleted.']);
    }

    /** t() without auto-creating the key, so an Arabic row is never created with English text. */
    private function text(string $key, string $default): string
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
     * VENDOR-MEDIA-03A: the term is matched literally (% and _ are escaped, so they are not wildcards) against the
     * original file name, title and alt text, always as a bound value. An empty term matches everything.
     */
    private function search(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        foreach (['file_original_name', 'title', 'alt'] as $column) {
            $query->orWhereRaw($query->getQuery()->getGrammar()->wrap($column)." like ? escape '!'", [$like]);
        }
    }

    private function prefix(int $vendorId): string
    {
        return 'vendors/media/'.$vendorId.'/';
    }

    private function item(Media $media): array
    {
        return ['id' => $media->id, 'path' => $media->file_path, 'alt_text' => $media->alt ?: $media->file_original_name, 'url' => $media->url];
    }
}
