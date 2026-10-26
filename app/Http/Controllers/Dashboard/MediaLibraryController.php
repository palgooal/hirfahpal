<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Admin;
use App\Support\Media\MediaUsageGuard;
use App\Support\Media\VendorMediaPath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Http\Controllers\MediaController;
use Palgoal\MediaLibrary\Models\Media;
use Throwable;

/**
 * AD-UI-CAT-M2-C (finding M1-F2, decisions D1-D3, D7): the package controller with safe deletion. Bound in
 * place of Palgoal\MediaLibrary\Http\Controllers\MediaController (AppServiceProvider), so the package routes
 * resolve to it; every other action is inherited unchanged (index only adds the uploader's name, AD-UI-CAT-M3).
 *
 * A delete runs in ONE transaction that locks the media rows (FOR UPDATE, ascending id), checks their use
 * (MediaUsageGuard) and deletes them only if none is used: a used item answers 409 with in_use and nothing is
 * deleted, for a single item and for a whole bulk request alike. Files are removed only after the commit, so
 * a rolled back or retried transaction (3 attempts on deadlock) never touches the disk. The foreign keys
 * (ON DELETE RESTRICT) refuse what a race could slip past the check; that refusal is also answered with 409.
 *
 * MEDIA-SECURITY-01: vendor-owned media (VendorMediaPath) is never deleted here, whatever the admin's abilities.
 * The check sits in the delete itself, not in MediaPolicy, because Gate::before lets a super admin skip the
 * policy; it answers the same 403 as a refused authorization, and a bulk request is refused whole.
 */
class MediaLibraryController extends MediaController
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly MediaUsageGuard $usage) {}

    /**
     * AD-UI-CAT-M3 (decision Q-M9): the JSON list also carries the uploader's name, shown in the admin library.
     * Only the name, never other account details; null when the uploader no longer exists. The page itself
     * and every other field come from the package unchanged.
     */
    public function index(Request $request): View|JsonResponse
    {
        $response = parent::index($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $items = collect($payload['data'] ?? []);
        $uploaderIds = $items->pluck('uploader_id')->filter()->unique()->values();
        $names = $uploaderIds->isEmpty() ? collect() : Admin::query()->whereKey($uploaderIds)->pluck('name', 'id');

        $payload['data'] = $items
            ->map(fn (array $item) => $item + ['uploader_name' => $names[$item['uploader_id'] ?? 0] ?? null])
            ->all();

        return $response->setData($payload);
    }

    public function destroy(string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('delete', $media);

        $result = $this->deleteUnused([$media->getKey()]);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        // Deleted by another request after the authorization: nothing left to delete here.
        abort_if($result['deleted'] === 0, 404);

        return response()->json($this->withCleanup(
            ['message' => $this->message('dashboard.Media_deleted', 'The media item was deleted.')],
            $result
        ));
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:media,id',
        ]);

        $items = Media::whereIn('id', $request->input('ids'))->get();

        // Every item is authorized before anything is deleted (as in the package).
        foreach ($items as $media) {
            $this->authorize('delete', $media);
        }

        $result = $this->deleteUnused($items->modelKeys());

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json($this->withCleanup([
            'message' => $this->message('dashboard.Media_bulk_deleted', 'The selected media items were deleted.'),
            'deleted' => $result['deleted'],
        ], $result));
    }

    /**
     * Deletes the rows of the given media ids unless one of them is used; then deletes their files.
     *
     * @param  list<int>  $ids
     * @return JsonResponse|array{deleted: int, cleanup_failed: list<int>}
     */
    private function deleteUnused(array $ids): JsonResponse|array
    {
        try {
            $outcome = DB::transaction(function () use ($ids): array {
                $locked = Media::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

                // MEDIA-SECURITY-01: before anything is deleted; the exception rolls the transaction back.
                if ($locked->contains(fn (Media $media) => VendorMediaPath::isVendorOwned($media->file_path))) {
                    throw new AuthorizationException;
                }

                $inUse = $this->usage->inUse($locked->modelKeys());

                if ($inUse !== []) {
                    return ['in_use' => $inUse];
                }

                // Rows a concurrent request deleted first are simply not locked, so not counted again.
                $deleted = Media::query()->whereKey($locked->modelKeys())->delete();

                return ['deleted' => $deleted, 'files' => $locked->map(fn (Media $media) => [
                    'id' => (int) $media->getKey(),
                    'disk' => $media->disk ?: 'public',
                    'path' => $media->file_path,
                ])->all()];
            }, self::ATTEMPTS);
        } catch (QueryException $exception) {
            if (! $this->isMediaReferenceViolation($exception)) {
                throw $exception;
            }

            // The database refused the delete: a reference was added in a way the check could not see.
            // Nothing was deleted (the transaction rolled back), and no file was touched.
            return $this->inUseResponse($this->usage->inUse($ids) ?: $ids);
        }

        if (isset($outcome['in_use'])) {
            return $this->inUseResponse($outcome['in_use']);
        }

        // After the commit only.
        $failed = [];

        foreach ($outcome['files'] as $file) {
            if (! $this->deleteFile($file)) {
                $failed[] = $file['id'];
            }
        }

        return ['deleted' => $outcome['deleted'], 'cleanup_failed' => $failed];
    }

    /**
     * @param  array{id: int, disk: string, path: ?string}  $file
     */
    private function deleteFile(array $file): bool
    {
        if (! $file['path']) {
            return true;
        }

        $exception = null;

        try {
            $removed = Storage::disk($file['disk'])->delete($file['path']);
        } catch (Throwable $caught) {
            $removed = false;
            $exception = $caught;
        }

        if (! $removed) {
            // The record is gone, the file may not be: enough detail to remove the orphan later.
            Log::error('Media file left on disk after its record was deleted.', [
                'media_id' => $file['id'],
                'disk' => $file['disk'],
                'path' => $file['path'],
                'exception' => $exception ? $exception::class.': '.$exception->getMessage() : null,
            ]);
        }

        return $removed;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array{deleted: int, cleanup_failed: list<int>}  $result
     * @return array<string, mixed>
     */
    private function withCleanup(array $body, array $result): array
    {
        if ($result['cleanup_failed'] !== []) {
            // Do not claim the files are gone.
            $body['file_cleanup_failed'] = $result['cleanup_failed'];
        }

        return $body;
    }

    /** @param  list<int>  $ids */
    private function inUseResponse(array $ids): JsonResponse
    {
        return response()->json([
            'message' => $this->message('dashboard.Media_in_use', 'This media is used and cannot be deleted. Remove it from where it is used first.'),
            'in_use' => array_values($ids),
        ], 409);
    }

    /**
     * MySQL 1451 (row referenced) or SQLite's foreign key failure, raised by a delete from media. Any other
     * database error is not a media reference problem and is re-thrown.
     */
    private function isMediaReferenceViolation(QueryException $exception): bool
    {
        if (! preg_match('/^\s*delete\s+from\s+[`"]?media[`"]?\s/i', $exception->getSql())) {
            return false;
        }

        return ($exception->errorInfo[1] ?? null) === 1451
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
    }

    /** t() without auto-creating the key, so an Arabic row is never created with English text. */
    private function message(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
