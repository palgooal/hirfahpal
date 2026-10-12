<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * AD-UI-CAT-M2-C (decisions Q-M1, D5): a category's image becomes a reference to the media library.
 * categories.image_media_id is a nullable unsigned big integer (the type of media.id), indexed, with a foreign
 * key to media.id ON DELETE RESTRICT: a media row a category points at cannot be deleted at database level.
 * The legacy categories.image column is left untouched and no value is copied from it (decision D4).
 *
 * MySQL: the column, its index and its key are added by ONE "ALTER TABLE", which MySQL applies atomically, so
 * a failed run leaves nothing half-added. The target state is checked before (an already migrated table is
 * skipped, any other state of the column is refused) and after.
 *
 * SQLite (test suite): adding a foreign key makes Laravel rebuild the table, which is only safe outside a
 * transaction (see 2026_10_10_000000_restrict_category_foreign_keys), so the migration runs outside one and
 * refuses to rebuild while one is open.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const COLUMN = 'image_media_id';

    private const INDEX = 'categories_image_media_id_index';

    private const FOREIGN = 'categories_image_media_restrict_fk';

    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            throw new RuntimeException('The media table must exist before categories.image_media_id can reference it.');
        }

        if (Schema::hasColumn('categories', self::COLUMN)) {
            $this->assertTargetState();

            return; // Already migrated.
        }

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();

            DB::statement(sprintf(
                'ALTER TABLE %s ADD COLUMN %s BIGINT UNSIGNED NULL AFTER %s, ADD INDEX %s (%s), ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE RESTRICT',
                $grammar->wrapTable('categories'),
                $grammar->wrap(self::COLUMN),
                $grammar->wrap('image'),
                $grammar->wrap(self::INDEX),
                $grammar->wrap(self::COLUMN),
                $grammar->wrap(self::FOREIGN),
                $grammar->wrap(self::COLUMN),
                $grammar->wrapTable('media'),
                $grammar->wrap('id')
            ));
        } else {
            $this->refuseInsideTransaction();

            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedBigInteger(self::COLUMN)->nullable()->after('image');
                $table->index(self::COLUMN, self::INDEX);
                $table->foreign(self::COLUMN, self::FOREIGN)->references('id')->on('media')->restrictOnDelete();
            });

            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('SQLite foreign key check failed after adding categories.image_media_id.');
            }
        }

        $this->assertTargetState();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', self::COLUMN)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $grammar = DB::getQueryGrammar();

            DB::statement(sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s, DROP INDEX %s, DROP COLUMN %s',
                $grammar->wrapTable('categories'),
                $grammar->wrap(self::FOREIGN),
                $grammar->wrap(self::INDEX),
                $grammar->wrap(self::COLUMN)
            ));

            return;
        }

        $this->refuseInsideTransaction();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign([self::COLUMN]); // SQLite keys have no name; dropped by column.
            $table->dropIndex(self::INDEX);
            $table->dropColumn(self::COLUMN);
        });
    }

    private function assertTargetState(): void
    {
        $keys = collect(Schema::getForeignKeys('categories'))
            ->filter(fn (array $fk) => $fk['columns'] === [self::COLUMN])
            ->values();

        $valid = $keys->count() === 1
            && $keys[0]['foreign_table'] === 'media'
            && $keys[0]['foreign_columns'] === ['id']
            && $keys[0]['on_delete'] === 'restrict'
            && (DB::getDriverName() !== 'mysql' || $keys[0]['name'] === self::FOREIGN);

        if (! $valid) {
            throw new RuntimeException('categories.image_media_id exists but is not the expected nullable reference to media.id ON DELETE RESTRICT. Nothing was changed.');
        }

        $column = collect(Schema::getColumns('categories'))->firstWhere('name', self::COLUMN);

        if (! $column['nullable']) {
            throw new RuntimeException('categories.image_media_id must be nullable.');
        }
    }

    private function refuseInsideTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Refusing to rebuild categories on SQLite inside a transaction: foreign key enforcement cannot be suspended there, so dependent rows could be changed.');
        }
    }
};
