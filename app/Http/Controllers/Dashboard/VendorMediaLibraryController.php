<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Support\Media\VendorMediaPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Palgoal\MediaLibrary\Models\Media;

/**
 * ADMIN-MEDIA-ORGANIZATION-01 P3: read-only JSON views of the vendor media libraries, apart from the admin
 * library. Each vendor is a virtual folder (no physical folder is created): its media are the images under
 * vendors/media/{vendorId}/ (VendorMediaPath::scopeToVendor), never chosen by uploader_id.
 *
 * Both endpoints need media.view (MediaPolicy, where Gate::before lets a super admin through) and vendors.view
 * (vendor status and approval are vendor-management data), checked before any vendor is read: the vendor id is
 * a plain number resolved after authorization, so an admin without access gets the same 403 for any id.
 * Nothing here writes; the responses carry an allow-list of fields, without storage paths or uploader ids.
 */
class VendorMediaLibraryController extends Controller
{
    private const FOLDERS_PER_PAGE = 24;

    private const MEDIA_PER_PAGE = 40;

    public function folders(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(['active', 'pending', 'blocked'])],
            'has_media' => ['nullable', 'boolean'],
        ]);

        $label = "coalesce(nullif(trim(vendor_profiles.store_name), ''), vendors.name)";
        $counts = VendorMediaPath::folderCounts(Media::query()->images())->toBase();

        $query = Vendor::query()
            ->leftJoin('vendor_profiles', 'vendor_profiles.vendor_id', '=', 'vendors.id')
            ->leftJoinSub($counts, 'vendor_media', fn (JoinClause $join) => $join->whereRaw('vendor_media.vendor_key = hex(cast(vendors.id as char))'))
            ->select([
                'vendors.id', 'vendors.name', 'vendors.status',
                'vendor_profiles.store_name', 'vendor_profiles.approval_status',
            ])
            ->selectRaw("{$label} as label, coalesce(vendor_media.media_count, 0) as media_count")
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = '%'.$request->input('search').'%';

                $query->where(fn (Builder $names) => $names->where('vendor_profiles.store_name', 'like', $search)
                    ->orWhere('vendors.name', 'like', $search));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('vendors.status', $request->input('status')))
            ->when($request->boolean('has_media'), fn (Builder $query) => $query->whereNotNull('vendor_media.media_count'))
            ->orderByRaw($label)
            ->orderBy('vendors.id');

        $paginator = $query->paginate($this->perPage($request, self::FOLDERS_PER_PAGE));

        return response()->json($this->page($paginator, fn (Vendor $vendor) => [
            'vendor_id' => $vendor->id,
            'label' => $vendor->label,
            'store_name' => $vendor->store_name,
            'vendor_name' => $vendor->name,
            'status' => $vendor->status,
            'approval_status' => $vendor->approval_status,
            'media_count' => (int) $vendor->media_count,
            'media_url' => route('dashboard.vendor-media.media', $vendor->id),
        ]));
    }

    public function media(Request $request, string $vendor): JsonResponse
    {
        $this->authorizeAccess($request);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $vendor = Vendor::query()->with('profile')->findOrFail($vendor);

        // Ownership first, then search, order and pagination inside that folder.
        $paginator = VendorMediaPath::scopeToVendor(Media::query()->images(), $vendor->id)
            ->search($request->input('search'))
            ->latest()
            ->orderByDesc('id')
            ->paginate($this->perPage($request, self::MEDIA_PER_PAGE));

        $storeName = trim((string) $vendor->profile?->store_name);

        return response()->json([
            'vendor' => [
                'vendor_id' => $vendor->id,
                'label' => $storeName !== '' ? $vendor->profile->store_name : $vendor->name,
                'status' => $vendor->status,
                'approval_status' => $vendor->profile?->approval_status,
            ],
        ] + $this->page($paginator, fn (Media $item) => [
            'id' => $item->id,
            'url' => $item->url,
            'file_original_name' => $item->file_original_name,
            'alt' => $item->alt,
            'title' => $item->title,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'width' => $item->width,
            'height' => $item->height,
            'created_at' => $item->created_at?->toJSON(),
        ]));
    }

    private function authorizeAccess(Request $request): void
    {
        Gate::forUser($request->user('admin'))->authorize('viewAny', Media::class);

        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility('vendors.view'), 403);
    }

    /** The admin library's rule: a page size outside 1-100 falls back to the default. */
    private function perPage(Request $request, int $default): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return $perPage > 0 && $perPage <= 100 ? $perPage : $default;
    }

    private function page($paginator, callable $item): array
    {
        return [
            'data' => array_map($item, $paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
