<?php

namespace App\Support\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * PAGES-02: the value rules of the attributes PageContentSanitizer allows, after symfony's own URL rules.
 *
 * - img src: a file of this site's media library only, /storage/media/<folders>/<name>.<jpg|jpeg|png|webp|gif>,
 *   returned as a relative path (an absolute address on this site is reduced to its path). No query, fragment,
 *   percent-encoding, backslash, "." or ".." segment.
 * - a href: no protocol-relative (//host), backslash, control character or whitespace; a relative link must start
 *   with "/" (a path on this site) or "#".
 * - dir: rtl, ltr or auto. colspan / rowspan: 1–50. ol start: 1–100000. th scope: row, col, rowgroup, colgroup.
 *   img width / height: 1–4000.
 */
class ContentAttributeSanitizer implements AttributeSanitizerInterface
{
    private const MEDIA_PATH = '#^/storage/media/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:jpe?g|png|webp|gif)$#i';

    private ?string $host;

    private ?int $port;

    public function __construct(string $appUrl)
    {
        $this->host = ($host = parse_url($appUrl, PHP_URL_HOST)) ? strtolower($host) : null;
        $this->port = parse_url($appUrl, PHP_URL_PORT) ?: null;
    }

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src', 'href', 'dir', 'colspan', 'rowspan', 'start', 'scope', 'width', 'height'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $value = trim($value);

        return match ($attribute) {
            'src' => $element === 'img' ? $this->mediaPath($value) : null,
            'href' => $this->link($value),
            'dir' => in_array(strtolower($value), ['rtl', 'ltr', 'auto'], true) ? strtolower($value) : null,
            'colspan', 'rowspan' => $this->number($value, 1, 50),
            'start' => $this->number($value, 1, 100_000),
            'scope' => in_array(strtolower($value), ['row', 'col', 'rowgroup', 'colgroup'], true) ? strtolower($value) : null,
            'width', 'height' => $this->number($value, 1, 4000),
            default => null,
        };
    }

    private function mediaPath(string $value): ?string
    {
        if ($value === '' || preg_match('/[\s\x00-\x1F\x7F\\\\%]/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['host'])) {
            // An absolute address: this site only (scheme, host and port), reduced to its path.
            $sameSite = in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                && strtolower($parts['host']) === $this->host
                && (($parts['port'] ?? null) ?: null) === $this->port;

            if (! $sameSite) {
                return null;
            }
        } elseif (isset($parts['scheme']) || str_starts_with($value, '//')) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match('#(^|/)\.{1,2}(/|$)#', $path) === 1 || preg_match(self::MEDIA_PATH, $path) !== 1) {
            return null;
        }

        return $path;
    }

    private function link(string $value): ?string
    {
        if ($value === '' || str_starts_with($value, '//') || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $value) === 1) {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme) && ! str_starts_with($value, '/') && ! str_starts_with($value, '#')) {
            return null;
        }

        return $value;
    }

    private function number(string $value, int $min, int $max): ?string
    {
        return ctype_digit($value) && (int) $value >= $min && (int) $value <= $max ? (string) (int) $value : null;
    }
}
