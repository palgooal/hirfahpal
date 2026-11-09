<?php

namespace App\Support\Storefront;

/**
 * FOOTER-SOCIAL-02A: the approved social platforms of the storefront footer, in one place, and the read side of the
 * footer's social links.
 *
 * Stored shape (FOOTER-SOCIAL-01): settings.footer_content.social_links, a list of
 * {platform, url, enabled}, in display order; language-neutral, so it stays outside any per-language footer text.
 * Until it is saved, the three legacy fields (instagram_url, facebook_url, x_url) are read in the footer's existing
 * order. Nothing here writes: what is stored is never rewritten by reading it.
 */
final class SocialPlatforms
{
    /**
     * platform => [translation key (storefront.Footer_Social_<key>), English name, exact hosts].
     *
     * Hosts are matched exactly (lowercase), so lookalikes (instagram.com.example.org, evilinstagram.com,
     * instagram.co) never match; the only subdomains are the ones listed.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const PLATFORMS = [
        'facebook' => ['Facebook', 'Facebook', ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com']],
        'instagram' => ['Instagram', 'Instagram', ['instagram.com', 'www.instagram.com']],
        'whatsapp' => ['WhatsApp', 'WhatsApp', ['wa.me', 'api.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com']],
        'tiktok' => ['TikTok', 'TikTok', ['tiktok.com', 'www.tiktok.com']],
        'youtube' => ['YouTube', 'YouTube', ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be']],
        'x' => ['X', 'X', ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com']],
        'linkedin' => ['LinkedIn', 'LinkedIn', ['linkedin.com', 'www.linkedin.com']],
        'telegram' => ['Telegram', 'Telegram', ['t.me', 'telegram.me']],
    ];

    /** The legacy footer fields, in the order the footer has always shown them. */
    public const LEGACY_FIELDS = ['instagram' => 'instagram_url', 'facebook' => 'facebook_url', 'x' => 'x_url'];

    /**
     * Whether a URL may be rendered as the given platform's link: http(s), no credentials, no port, no whitespace,
     * control characters or backslashes, at most 255 characters, and a host of that platform.
     */
    public static function isAllowedUrl(string $platform, string $url): bool
    {
        $hosts = self::PLATFORMS[$platform][2] ?? null;

        if ($hosts === null || $url === '' || strlen($url) > 255 || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }

        return in_array(strtolower($parts['host'] ?? ''), $hosts, true);
    }

    /**
     * The links to render, in order: only enabled entries of a known platform with an allowed URL. The first entry of
     * a platform decides it (a later duplicate is ignored). social_links, when stored, is the whole truth (an empty
     * list shows nothing; a value that is not a list shows nothing); without it, the legacy fields are read.
     *
     * @return list<array{platform: string, url: string}>
     */
    public static function linksFrom(mixed $footerContent): array
    {
        $content = is_array($footerContent) ? $footerContent : [];

        if (array_key_exists('social_links', $content)) {
            $entries = is_array($content['social_links']) && array_is_list($content['social_links']) ? $content['social_links'] : [];
        } else {
            $entries = [];
            foreach (self::LEGACY_FIELDS as $platform => $field) {
                $entries[] = ['platform' => $platform, 'url' => $content[$field] ?? null, 'enabled' => true];
            }
        }

        $links = [];
        $seen = [];

        foreach ($entries as $entry) {
            $platform = is_array($entry) ? ($entry['platform'] ?? null) : null;

            if (! is_string($platform) || ! isset(self::PLATFORMS[$platform]) || isset($seen[$platform])) {
                continue;
            }

            $seen[$platform] = true;
            $url = is_string($entry['url'] ?? null) ? trim($entry['url']) : '';

            if (($entry['enabled'] ?? null) === true && self::isAllowedUrl($platform, $url)) {
                $links[] = ['platform' => $platform, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * FOOTER-SOCIAL-02B: the saved entries as the settings form shows them, in order: every entry of a known platform
     * (the first of a platform decides it), its address and state as saved, valid or not, so an address that would not
     * be rendered can still be seen and fixed. Without social_links, each legacy field holding an address is an
     * enabled entry, so links saved before this list appear without any migration.
     *
     * @return list<array{platform: string, url: string, enabled: bool}>
     */
    public static function entriesFrom(mixed $footerContent): array
    {
        $content = is_array($footerContent) ? $footerContent : [];

        if (array_key_exists('social_links', $content)) {
            $entries = is_array($content['social_links']) && array_is_list($content['social_links']) ? $content['social_links'] : [];
        } else {
            $entries = [];
            foreach (self::LEGACY_FIELDS as $platform => $field) {
                $entries[] = ['platform' => $platform, 'url' => $content[$field] ?? null, 'enabled' => true];
            }
        }

        $result = [];

        foreach ($entries as $entry) {
            $platform = is_array($entry) ? ($entry['platform'] ?? null) : null;
            $url = is_array($entry) && is_string($entry['url'] ?? null) ? trim($entry['url']) : '';

            if (is_string($platform) && isset(self::PLATFORMS[$platform]) && ! isset($result[$platform]) && $url !== '') {
                $result[$platform] = ['platform' => $platform, 'url' => $url, 'enabled' => ($entry['enabled'] ?? null) === true];
            }
        }

        return array_values($result);
    }

    /**
     * FOOTER-SOCIAL-02B: the list to save from the validated form (keyed by platform: url, enabled, position). A
     * platform without an address is not saved (removed); the rest are ordered by position, then by platform order.
     *
     * @param  array<string, mixed>  $submitted
     * @return list<array{platform: string, url: string, enabled: bool}>
     */
    public static function fromForm(array $submitted): array
    {
        $order = array_flip(array_keys(self::PLATFORMS));
        $entries = [];

        foreach ($submitted as $platform => $row) {
            $url = is_array($row) && is_string($row['url'] ?? null) ? trim($row['url']) : '';

            if (isset(self::PLATFORMS[$platform]) && $url !== '') {
                $entries[] = [(int) ($row['position'] ?? 0) ?: 99, $order[$platform], [
                    'platform' => $platform, 'url' => $url, 'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                ]];
            }
        }

        usort($entries, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($entries, 2);
    }

    /**
     * FOOTER-SOCIAL-02B: the legacy fields kept in step with a saved list, so code reading only them (a rollback) shows
     * the same Instagram, Facebook and X links: an enabled entry's address, otherwise empty.
     *
     * @param  list<array{platform: string, url: string, enabled: bool}>  $entries
     * @return array<string, string>
     */
    public static function legacyFieldsFor(array $entries): array
    {
        $fields = array_fill_keys(self::LEGACY_FIELDS, '');

        foreach ($entries as $entry) {
            if (isset(self::LEGACY_FIELDS[$entry['platform']]) && $entry['enabled']) {
                $fields[self::LEGACY_FIELDS[$entry['platform']]] = $entry['url'];
            }
        }

        return $fields;
    }
}
