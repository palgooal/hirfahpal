<?php

namespace App\Support\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * PAGES-02: the one place the HTML of a storefront content page is cleaned (symfony/html-sanitizer). Strict allowlist:
 * paragraphs, line breaks, h2–h4 (the page title is the h1), bold/italic/underline/strike, quotes, rules, lists, simple
 * tables, links and images. No style, class, id, event handler, script, iframe, form, SVG or embed. Links: http(s),
 * mailto, tel, or a path on this site; always rel="noopener noreferrer". Images: only this site's media library files
 * (/storage/media/...), stored as relative paths. Anything else is removed, its text kept where the element is only
 * a wrapper.
 *
 * Used on save (PAGES-03) and again on every render, so a page can never show HTML that was not cleaned by the
 * current rules.
 */
class PageContentSanitizer
{
    /** The longest body accepted (characters); saving validates it first. */
    public const MAX_LENGTH = 100_000;

    /**
     * The sanitizer's own limit counts bytes and cuts what is longer: four bytes per character (the UTF-8 maximum), so a
     * body within MAX_LENGTH is never cut, Arabic included.
     */
    private const MAX_INPUT_BYTES = self::MAX_LENGTH * 4;

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        $clean = $this->sanitizer()->sanitize((string) $html);

        // An image whose address was refused keeps no element at all (no broken image with only its alt text).
        return trim(preg_replace('#<img(?![^>]*\ssrc=)[^>]*>#i', '', $clean) ?? '');
    }

    /**
     * The HTML of a plain text: each block separated by a blank line is a paragraph, a single line break inside it
     * is a <br>; the text is escaped, so nothing in it can become markup.
     */
    public static function fromPlainText(?string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));

        if ($text === '') {
            return '';
        }

        $paragraphs = array_filter(array_map('trim', preg_split('/\n[ \t]*\n\s*/', $text)), fn (string $block) => $block !== '');

        return implode("\n", array_map(
            fn (string $block) => '<p>'.str_replace("\n", '<br>', htmlspecialchars($block, ENT_QUOTES | ENT_HTML5, 'UTF-8')).'</p>',
            $paragraphs,
        ));
    }

    /**
     * PAGES-03B: the plain text of cleaned HTML, for the page's legacy body column (the rollback copy): blocks become
     * paragraphs separated by a blank line, <br> a line break, table cells are separated by spaces. The reverse of
     * fromPlainText() for the content it produces.
     */
    public static function toPlainText(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|h[1-6]|li|blockquote|tr|table|ul|ol)>|<hr\s*/?>#i', "\n\n", $html);
        $html = preg_replace('#</t[dh]>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)), explode("\n", $text));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /** Whether cleaned HTML holds any visible text or image. */
    public static function hasContent(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' || stripos((string) $html, '<img') !== false;
    }

    /**
     * PAGES-05B: whether cleaned HTML holds visible text (an image alone, empty markup or whitespace does not count);
     * the body part of a translation's completeness (StorefrontContentPage::isTranslationComplete()).
     */
    public static function hasText(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[\s\x{00A0}\x{200B}-\x{200F}\x{FEFF}]+/u', '', $text) !== '';
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $appUrl = (string) config('app.url');
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p', ['dir'])
            ->allowElement('br')
            ->allowElement('h2', ['dir'])
            ->allowElement('h3', ['dir'])
            ->allowElement('h4', ['dir'])
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('blockquote', ['dir'])
            ->allowElement('hr')
            ->allowElement('a', ['href'])
            ->allowElement('ul', ['dir'])
            ->allowElement('ol', ['dir', 'start'])
            ->allowElement('li', ['dir'])
            ->allowElement('table', ['dir'])
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan', 'scope', 'dir'])
            ->allowElement('td', ['colspan', 'rowspan', 'dir'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            // Media: this site only; ContentAttributeSanitizer then keeps only media library paths, made relative.
            ->allowMediaSchemes(['https', 'http'])
            ->allowMediaHosts(array_filter([parse_url($appUrl, PHP_URL_HOST)]))
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new ContentAttributeSanitizer($appUrl))
            ->withMaxInputLength(self::MAX_INPUT_BYTES);

        // Wrappers whose text is kept (pasted content); every other element not listed is dropped with its content.
        foreach (['div', 'span', 'b', 'i', 'font', 'small', 'section', 'article', 'header', 'footer', 'figure', 'figcaption', 'main'] as $wrapper) {
            $config = $config->blockElement($wrapper);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}
