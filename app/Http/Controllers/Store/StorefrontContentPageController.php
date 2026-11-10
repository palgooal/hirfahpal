<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StorefrontContentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use XMLWriter;

class StorefrontContentPageController extends Controller
{
    /**
     * PAGES-05D: a published page in the URL's language (/{locale}/pages/{slug}; contentPageLocale has checked the
     * language and set it). PAGES-02: the page in that language, or in the source language when its translation is
     * not complete (StorefrontContentPage::contentFor()), its body cleaned.
     */
    public function show(string $locale, string $slug): View
    {
        $page = StorefrontContentPage::query()->published()->where('slug', $slug)->firstOrFail();
        $content = $page->contentFor($locale);

        return view('storefront.content.show', compact('page', 'content'));
    }

    /**
     * PAGES-05E: /sitemap-pages.xml, the content pages only: one <url> per public address (a published page in an
     * enabled language with a complete translation; StorefrontContentPage::publicAlternates()), each listing every
     * public language of its page and x-default (the source language's address, when public) as xhtml:link. Two
     * queries for the pages and their translations, one for the languages; nothing from the visitor's session.
     */
    public function sitemap(): Response
    {
        $pages = StorefrontContentPage::query()->published()->with('translations')->orderBy('id')->get();
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach ($pages as $page) {
            $alternates = $page->publicAlternates();
            $links = $alternates + (isset($alternates[StorefrontContentPage::SOURCE_LOCALE]) ? ['x-default' => $alternates[StorefrontContentPage::SOURCE_LOCALE]] : []);

            foreach ($alternates as $locale => $url) {
                $translation = $page->translations->firstWhere('locale', $locale);
                $updated = max($page->updated_at, $translation->updated_at);

                $xml->startElement('url');
                $xml->writeElement('loc', $url);
                $xml->writeElement('lastmod', $updated->toAtomString());
                foreach ($links as $hreflang => $href) {
                    $xml->startElement('xhtml:link');
                    $xml->writeAttribute('rel', 'alternate');
                    $xml->writeAttribute('hreflang', $hreflang);
                    $xml->writeAttribute('href', $href);
                    $xml->endElement();
                }
                $xml->endElement();
            }
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * PAGES-05D: the former address, /pages/{slug}: a published page is sent to its address in the visitor's language
     * (SetLocale has resolved it; StorefrontContentPage::publicLocale() keeps it to an enabled one). Temporary (302),
     * as the destination varies by visitor. A draft or unknown page is not found, so a draft is never revealed.
     */
    public function legacy(string $slug): RedirectResponse
    {
        $page = StorefrontContentPage::query()->published()->where('slug', $slug)->firstOrFail();

        return redirect()->to(StorefrontContentPage::publicUrl($page->slug), 302);
    }
}
