<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAGES-02: storefront content pages in Arabic and English. Additive only:
 * - storefront_content_pages gains status (draft|published), published_at, is_system and updated_by; existing rows get
 *   the status of is_published (published_at = created_at when published) and the seven pages created with the table
 *   become system pages. title, excerpt, body and is_published stay as they are (the Arabic copy, for a rollback).
 * - storefront_content_page_translations holds each language's title, excerpt, body (HTML) and SEO texts, one row per
 *   page and locale. Every existing page gets its Arabic row: its plain-text body turned into escaped paragraphs
 *   (blank line) and line breaks (<br>), so nothing in the text can become markup and no text is lost.
 *
 * Running it again changes nothing (a page that already has its Arabic row is skipped).
 */
return new class extends Migration
{
    private const SYSTEM_PAGES = ['about', 'authenticity', 'shipping', 'returns', 'faq', 'terms', 'privacy'];

    public function up(): void
    {
        if (! Schema::hasColumn('storefront_content_pages', 'status')) {
            Schema::table('storefront_content_pages', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->after('is_published');
                $table->timestamp('published_at')->nullable()->after('status');
                $table->boolean('is_system')->default(false)->after('published_at');
                $table->foreignId('updated_by')->nullable()->after('is_system')->constrained('admins')->nullOnDelete();
                $table->index('status');
            });

            DB::table('storefront_content_pages')->where('is_published', true)->update(['status' => 'published', 'published_at' => DB::raw('created_at')]);
            DB::table('storefront_content_pages')->where('is_published', false)->update(['status' => 'draft']);
            DB::table('storefront_content_pages')->whereIn('slug', self::SYSTEM_PAGES)->update(['is_system' => true]);
        }

        if (! Schema::hasTable('storefront_content_page_translations')) {
            Schema::create('storefront_content_page_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('page_id')->constrained('storefront_content_pages')->cascadeOnDelete();
                $table->string('locale', 5);
                $table->string('title');
                $table->text('excerpt')->nullable();
                $table->longText('body_html');
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->timestamps();
                $table->unique(['page_id', 'locale']);
            });
        }

        $now = now();

        foreach (DB::table('storefront_content_pages')->orderBy('id')->get() as $page) {
            $exists = DB::table('storefront_content_page_translations')->where('page_id', $page->id)->where('locale', 'ar')->exists();

            if (! $exists) {
                DB::table('storefront_content_page_translations')->insert([
                    'page_id' => $page->id,
                    'locale' => 'ar',
                    'title' => $page->title,
                    'excerpt' => $page->excerpt,
                    'body_html' => $this->paragraphs((string) $page->body),
                    'meta_title' => null,
                    'meta_description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_content_page_translations');

        if (Schema::hasColumn('storefront_content_pages', 'status')) {
            Schema::table('storefront_content_pages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
                $table->dropIndex(['status']);
                $table->dropColumn(['status', 'published_at', 'is_system']);
            });
        }
    }

    /** The same conversion as PageContentSanitizer::fromPlainText(), kept here so this migration never changes. */
    private function paragraphs(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        if ($text === '') {
            return '';
        }

        $paragraphs = array_filter(array_map('trim', preg_split('/\n[ \t]*\n\s*/', $text)), fn (string $block) => $block !== '');

        return implode("\n", array_map(
            fn (string $block) => '<p>'.str_replace("\n", '<br>', htmlspecialchars($block, ENT_QUOTES | ENT_HTML5, 'UTF-8')).'</p>',
            $paragraphs,
        ));
    }
};
