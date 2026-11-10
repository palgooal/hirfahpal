<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreContentPageRequest;
use App\Http\Requests\Dashboard\UpdateContentPageRequest;
use App\Models\StorefrontContentPage;
use App\Models\StorefrontContentPageTranslation;
use App\Support\Content\PageContentSanitizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PAGES-03A: the storefront content pages in the dashboard (ContentPagePolicy: content-pages.view / .edit). The seven
 * system pages are listed, edited (state, and per language the title, excerpt, SEO texts and, PAGES-03B, the body from
 * the visual editor) and previewed as saved. A body is saved only when sent, always cleaned by PageContentSanitizer;
 * the Arabic one is also kept as plain text on the page (the rollback copy). Everything is saved through Eloquent in
 * one transaction, so the state stays in step with is_published.
 *
 * PAGES-04B: custom pages are created with the same form (content-pages.create), as a draft or published.
 *
 * PAGES-04C: a custom page's slug changes until its first publication; a custom draft can be deleted
 * (content-pages.delete) with its languages. Both are checked again on the locked row inside the transaction, so a
 * page published meanwhile keeps its slug and is not deleted.
 */
class ContentPageManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeFor($request, 'view');

        return view('dashboard.content-pages.index', [
            'pages' => StorefrontContentPage::query()->with('translations')->orderBy('id')->get(),
            'canEdit' => $request->user('admin')->can('edit', StorefrontContentPage::class),
            'canCreate' => $request->user('admin')->can('create', StorefrontContentPage::class),
            'canDelete' => $request->user('admin')->can('delete', StorefrontContentPage::class),
        ]);
    }

    public function edit(Request $request, StorefrontContentPage $contentPage): View
    {
        $this->authorizeFor($request, 'view');

        return view('dashboard.content-pages.edit', [
            'page' => $contentPage->load('translations'),
            'canEdit' => $request->user('admin')->can('edit', StorefrontContentPage::class),
        ]);
    }

    /** PAGES-04B: the same form for a new custom page (content-pages.view and content-pages.create). */
    public function create(Request $request): View
    {
        $this->authorizeFor($request, 'view');
        $this->authorizeFor($request, 'create');

        $page = new StorefrontContentPage(['status' => 'draft']);
        $page->setRelation('translations', collect());

        return view('dashboard.content-pages.edit', ['page' => $page, 'canEdit' => true]);
    }

    /**
     * PAGES-04B: a new custom page, a draft or published, saved like an existing one. Its slug is set only here. A
     * page saved with the same slug meanwhile (the database's unique index) is reported on the slug, not as an error.
     */
    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $page = new StorefrontContentPage;
        $page->slug = $request->validated('slug');
        $page->is_system = false;
        $page->setRelation('translations', collect());

        try {
            $this->saveContent($request, $page);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['slug' => $this->text('Content_Pages_Slug_Taken', 'Another page already uses this address.')]);
        }

        return redirect()->route('dashboard.content-pages.edit', $page)
            ->with('success', $this->text('Content_Pages_Created', 'The page was created.'));
    }

    public function update(UpdateContentPageRequest $request, StorefrontContentPage $contentPage): RedirectResponse
    {
        // PAGES-04C: the request allows a slug only while the page is unlocked.
        if ($request->has('slug')) {
            $contentPage->slug = $request->validated('slug');
        }

        try {
            $this->saveContent($request, $contentPage->load('translations'));
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['slug' => $this->text('Content_Pages_Slug_Taken', 'Another page already uses this address.')]);
        }

        return redirect()->route('dashboard.content-pages.edit', $contentPage)
            ->with('success', $this->text('Content_Pages_Saved', 'The page was saved.'));
    }

    /**
     * The page and its languages, in one transaction. The page is saved first (a new one needs its id), then each
     * language's row is saved or, for an emptied English one, deleted.
     */
    private function saveContent(UpdateContentPageRequest $request, StorefrontContentPage $contentPage): void
    {
        $data = $request->validated();
        $sanitizer = app(PageContentSanitizer::class);

        DB::transaction(function () use ($data, $contentPage, $request, $sanitizer): void {
            // PAGES-04C: a slug change only while the stored page is still unpublished (it may have been published
            // since the request was validated).
            if ($contentPage->exists && $contentPage->isDirty('slug')
                && StorefrontContentPage::query()->lockForUpdate()->whereKey($contentPage->getKey())->first()?->isSlugLocked() !== false) {
                throw ValidationException::withMessages(['slug' => $this->text('Content_Pages_Slug_Locked_Error', 'This address can no longer be changed: the page is a system page or has been published.')]);
            }

            $save = [];
            $delete = [];

            // PAGES-05B: every language that was sent (the registry's, checked by the request). A language not sent at
            // all, or no longer enabled, keeps its saved translation as it is.
            foreach (StorefrontContentPage::editableLocales() as $locale) {
                if (! array_key_exists($locale, $data['translations'])) {
                    continue;
                }

                $sent = $data['translations'][$locale] ?? [];
                $values = [];
                foreach (array_keys(UpdateContentPageRequest::LIMITS) as $field) {
                    $value = $sent[$field] ?? null;
                    $values[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                }

                $row = $contentPage->translations->firstWhere('locale', $locale);
                // The body only when the editor sent it; otherwise the saved one stays as it is.
                $bodySent = array_key_exists('body_html', $sent);
                $body = $bodySent ? $sanitizer->sanitize((string) $sent['body_html']) : (string) $row?->body_html;

                if ($bodySent) {
                    $values['body_html'] = PageContentSanitizer::hasContent($body) ? $body : '';
                }

                $empty = array_filter($values) === [] && ! PageContentSanitizer::hasContent($body);

                if ($row === null) {
                    if ($empty) {
                        continue;
                    }
                    $row = new StorefrontContentPageTranslation(['locale' => $locale, 'body_html' => '']);
                } elseif ($empty && $locale !== StorefrontContentPage::SOURCE_LOCALE) {
                    // A translation with nothing left in it goes: the page shows the source language again.
                    $delete[] = $row;

                    continue;
                }

                $save[] = $row->fill($values);

                if ($locale === StorefrontContentPage::SOURCE_LOCALE && $bodySent) {
                    // The rollback copy of the source (Arabic) body, as plain text.
                    $contentPage->body = PageContentSanitizer::toPlainText($row->body_html);
                }
            }

            // The page: its state, who saved it, and the source texts kept on the page as the rollback copy.
            $source = $data['translations'][StorefrontContentPage::SOURCE_LOCALE];
            $contentPage->status = $contentPage->is_system ? 'published' : $data['status'];
            $contentPage->title = $source['title'];
            $contentPage->excerpt = $source['excerpt'] ?? null;
            $contentPage->updated_by = $request->user('admin')->getKey();
            $contentPage->save();

            foreach ($delete as $row) {
                $row->delete();
            }

            foreach ($save as $row) {
                $row->page()->associate($contentPage)->save();
            }
        });
    }

    /**
     * PAGES-04C: deletes a custom draft and its languages (content-pages.view and content-pages.delete). A system page
     * is refused (403); a page published since the list was shown is kept and the admin told to unpublish it first.
     */
    public function destroy(Request $request, StorefrontContentPage $contentPage): RedirectResponse
    {
        $this->authorizeFor($request, 'view');
        $this->authorizeFor($request, 'delete');
        abort_if($contentPage->is_system, 403);

        $deleted = DB::transaction(function () use ($contentPage): bool {
            $page = StorefrontContentPage::query()->lockForUpdate()->find($contentPage->getKey());

            if ($page === null || ! $page->isDeletable()) {
                return false;
            }

            $page->translations()->delete();
            $page->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()->route('dashboard.content-pages.index')
                ->with('error', $this->text('Content_Pages_Delete_Not_Draft', 'Only a draft page can be deleted. Return the page to draft first.'));
        }

        return redirect()->route('dashboard.content-pages.index')
            ->with('success', $this->text('Content_Pages_Deleted', 'The page was deleted. Links to its address no longer work.'));
    }

    /** The saved page as the storefront shows it, in one language, drafts included; private and never cached. */
    public function preview(Request $request, StorefrontContentPage $contentPage, string $locale): Response
    {
        $this->authorizeFor($request, 'view');
        // PAGES-05C: a language of the registry (the source language, or an enabled one); any other is not found.
        abort_unless(in_array($locale, StorefrontContentPage::editableLocales(), true), 404);
        app()->setLocale($locale);

        return response()
            ->view('storefront.content.show', [
                'page' => $contentPage,
                'content' => $contentPage->load('translations')->contentFor($locale),
                'preview' => true,
                'previewBanner' => 'storefront.partials.content-preview-banner',
                'previewPage' => $contentPage,
                'previewLocale' => $locale,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    private function authorizeFor(Request $request, string $ability): void
    {
        abort_unless($request->user('admin')?->can($ability, StorefrontContentPage::class), 403);
    }

    private function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
