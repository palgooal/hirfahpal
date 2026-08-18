<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANT — Schema::hasTable() short-circuit:
     * This migration silently does nothing if a `media` table already
     * exists (e.g. created by another package, or by your own application).
     * That check only tells you a table with this *name* exists — it does
     * NOT verify its columns, types, nullability, or indexes match what
     * this package expects (file_name, file_path, file_type, disk, size,
     * uploader_id, alt/title/caption/description, etc. — see below).
     *
     * If you already have a `media` table from another source, this
     * package will NOT fail loudly; it will instead run against whatever
     * schema is actually there, and any missing/incompatible column will
     * surface later as a runtime SQL error (e.g. "column not found") when
     * the Model or Controller tries to read/write it. There is no
     * automatic conflict detection here, and it would be inaccurate to
     * claim running two media-table packages side by side is always safe
     * — verify the existing schema yourself (`php artisan db:table media`
     * or an equivalent inspection) before installing this package on top
     * of a project that already has a `media` table.
     */
    public function up(): void
    {
        if (Schema::hasTable('media')) {
            return;
        }

        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Internal stored file name (usually hashed)
            $table->string('file_name');

            // Original file name as uploaded by the user
            $table->string('file_original_name')->nullable();

            // Relative path inside storage (e.g. media/2025/06/file.jpg)
            $table->string('file_path');

            // Basic technical info
            $table->string('file_extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0); // in bytes

            // Optional classification (image, video, audio, document, other)
            $table->string('file_type', 50)->nullable();

            // Disk name (public, s3, etc.)
            $table->string('disk', 50)->default('public');

            // Optional image dimensions
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Ownership / uploader.
            //
            // Intentionally NOT a real foreign key constraint. The
            // uploader's table name/PK type varies per host application
            // (config('auth.providers.users.model')), and declaring a hard
            // FK here would tie this migration to a specific users table
            // that may not exist yet at migration time, or may use a
            // non-bigint primary key, breaking portability across MySQL,
            // MariaDB, PostgreSQL, and SQLite. An indexed nullable column
            // is used instead so lookups stay fast without that coupling.
            $table->unsignedBigInteger('uploader_id')->nullable()->index();

            // SEO / content fields
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Because up() may have been a no-op (pre-existing table), down() is
     * intentionally conservative and uses dropIfExists() rather than an
     * unconditional drop — this avoids failing if the table was already
     * absent, but it also means running `migrate:rollback` after this
     * migration will drop a `media` table even if this package never
     * created it in the first place (i.e. it existed before this package
     * was installed). If that matters for your project, don't rely on
     * rollback here — back up the table first.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
