<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\DB;

/**
 * AD-UI-CAT-M2-C (finding M1-F2): which media items are referenced by application records, so they are not
 * deleted from under them.
 *
 * REFERENCES lists every column that holds a media.id. Each one must also have a foreign key to media.id ON
 * DELETE RESTRICT (the database refuses what a race slips past this check); a test fails when a foreign key
 * to media exists that is not listed here. Legacy path columns such as categories.image are not references
 * (decision D4). The optional mediables table of the package is not used by this application.
 *
 * The check is a locking read (FOR SHARE): it reads the latest committed rows, whatever the isolation level or
 * any snapshot the transaction already has, and waits for a transaction that is changing a matching row.
 * Call it inside the transaction that has locked the media rows (FOR UPDATE): a new reference needs a shared
 * lock on its media row for its foreign key check, so none can be added until that transaction ends.
 */
class MediaUsageGuard
{
    /** @var array<string, string> table => column holding media.id */
    public const REFERENCES = [
        'categories' => 'image_media_id',
    ];

    /**
     * The given media ids that are referenced, ascending.
     *
     * @param  iterable<int|string>  $mediaIds
     * @return list<int>
     */
    public function inUse(iterable $mediaIds): array
    {
        $ids = collect($mediaIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $used = collect();

        foreach (self::REFERENCES as $table => $column) {
            $used = $used->merge(
                DB::table($table)->whereIn($column, $ids)->sharedLock()->pluck($column)
            );
        }

        return $used->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }
}
