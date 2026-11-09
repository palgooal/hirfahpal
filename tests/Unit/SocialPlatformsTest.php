<?php

namespace Tests\Unit;

use App\Support\Storefront\SocialPlatforms;
use PHPUnit\Framework\TestCase;

/**
 * FOOTER-SOCIAL-02A: the eight approved platforms, the URLs each may link to (exact hosts, no lookalikes) and the
 * read-side normalization of the footer's social links, legacy fields included.
 */
class SocialPlatformsTest extends TestCase
{
    public function test_the_eight_approved_platforms_are_defined_once(): void
    {
        $this->assertSame(['facebook', 'instagram', 'whatsapp', 'tiktok', 'youtube', 'x', 'linkedin', 'telegram'], array_keys(SocialPlatforms::PLATFORMS));
        $this->assertSame(['instagram' => 'instagram_url', 'facebook' => 'facebook_url', 'x' => 'x_url'], SocialPlatforms::LEGACY_FIELDS);

        foreach (SocialPlatforms::PLATFORMS as $platform => [$key, $name, $hosts]) {
            $this->assertMatchesRegularExpression('/^[A-Za-z]+$/', $key, $platform);
            $this->assertNotSame('', $name);
            $this->assertNotEmpty($hosts);
            foreach ($hosts as $host) {
                $this->assertSame(strtolower($host), $host, $platform);
            }
        }
    }

    public function test_each_platform_accepts_its_own_addresses(): void
    {
        $accepted = [
            'facebook' => ['https://facebook.com/hirfah', 'https://www.facebook.com/hirfah', 'https://m.facebook.com/hirfah', 'https://fb.com/hirfah', 'http://facebook.com/hirfah'],
            'instagram' => ['https://instagram.com/hirfah', 'https://www.instagram.com/hirfah/', 'HTTPS://WWW.INSTAGRAM.COM/hirfah'],
            'whatsapp' => ['https://wa.me/970599000000', 'https://api.whatsapp.com/send?phone=970599000000', 'https://www.whatsapp.com/channel/abc'],
            'tiktok' => ['https://www.tiktok.com/@hirfah', 'https://tiktok.com/@hirfah'],
            'youtube' => ['https://www.youtube.com/@hirfah', 'https://youtu.be/abc123', 'https://m.youtube.com/@hirfah'],
            'x' => ['https://x.com/hirfah', 'https://twitter.com/hirfah', 'https://www.x.com/hirfah'],
            'linkedin' => ['https://www.linkedin.com/company/hirfah', 'https://linkedin.com/in/hirfah'],
            'telegram' => ['https://t.me/hirfah', 'https://telegram.me/hirfah'],
        ];

        foreach ($accepted as $platform => $urls) {
            foreach ($urls as $url) {
                $this->assertTrue(SocialPlatforms::isAllowedUrl($platform, $url), "{$platform}: {$url}");
            }
        }
    }

    public function test_lookalike_foreign_and_unsafe_addresses_are_rejected(): void
    {
        $rejected = [
            ['instagram', 'https://instagram.com.example.org/hirfah'],
            ['instagram', 'https://evilinstagram.com/hirfah'],
            ['instagram', 'https://instagram.co/hirfah'],
            ['instagram', 'https://instagram.com./hirfah'],
            ['instagram', 'https://xn--nstagram-6za.com/hirfah'],
            ['instagram', 'https://example.org/instagram.com'],
            ['instagram', 'https://instagram.com@example.org/hirfah'],
            ['instagram', 'https://user:pass@instagram.com/hirfah'],
            ['instagram', 'https://instagram.com:8080/hirfah'],
            ['instagram', 'https://instagram.com\\@example.org'],
            ['instagram', 'https://instagram.com/hir fah'],
            ['instagram', "https://instagram.com/\nhirfah"],
            ['instagram', '//instagram.com/hirfah'],
            ['instagram', 'instagram.com/hirfah'],
            ['instagram', 'javascript:alert(1)//instagram.com'],
            ['instagram', 'data:text/html,instagram.com'],
            ['instagram', 'ftp://instagram.com/hirfah'],
            ['instagram', 'https://instagram.com/'.str_repeat('a', 250)],
            ['instagram', ''],
            ['instagram', 'https://facebook.com/hirfah'],
            ['facebook', 'https://instagram.com/hirfah'],
            ['whatsapp', 'https://chat.whatsapp.com/abc'],
            ['x', 'https://mobile.twitter.com/hirfah'],
            ['telegram', 'https://www.t.me/hirfah'],
            ['unknown', 'https://instagram.com/hirfah'],
        ];

        foreach ($rejected as [$platform, $url]) {
            $this->assertFalse(SocialPlatforms::isAllowedUrl($platform, $url), "{$platform}: {$url}");
        }
    }

    public function test_legacy_fields_are_read_in_the_footer_order_and_checked_like_any_link(): void
    {
        $this->assertSame([
            ['platform' => 'instagram', 'url' => 'https://instagram.com/hirfah'],
            ['platform' => 'x', 'url' => 'https://x.com/hirfah'],
        ], SocialPlatforms::linksFrom([
            'tagline' => 'كلمة', 'x_url' => ' https://x.com/hirfah ', 'facebook_url' => '', 'instagram_url' => 'https://instagram.com/hirfah',
        ]));

        // A legacy link to another site (saved before hosts were checked) is not rendered as the platform's link.
        $this->assertSame([], SocialPlatforms::linksFrom(['facebook_url' => 'http://127.0.0.1:8002/dashboard/settings', 'instagram_url' => null]));
        $this->assertSame([], SocialPlatforms::linksFrom(['instagram_url' => ['https://instagram.com/x'], 'x_url' => 42]));

        foreach ([null, '', 'text', 42, true, []] as $content) {
            $this->assertSame([], SocialPlatforms::linksFrom($content));
        }
    }

    public function test_stored_social_links_keep_their_order_and_only_enabled_valid_ones_render(): void
    {
        $links = SocialPlatforms::linksFrom([
            'instagram_url' => 'https://instagram.com/legacy', // ignored: social_links is stored
            'social_links' => [
                ['platform' => 'telegram', 'url' => 'https://t.me/hirfah', 'enabled' => true],
                ['platform' => 'facebook', 'url' => 'https://facebook.com/hirfah', 'enabled' => false],
                ['platform' => 'whatsapp', 'url' => 'https://wa.me/970599000000', 'enabled' => true],
                ['platform' => 'myspace', 'url' => 'https://myspace.com/hirfah', 'enabled' => true],
                ['platform' => 'tiktok', 'url' => 'https://tiktok.com.example.org/@hirfah', 'enabled' => true],
                ['platform' => 'youtube', 'url' => 'https://youtu.be/abc', 'enabled' => 'true'],
                ['platform' => 'linkedin', 'url' => null, 'enabled' => true],
                'not an entry',
                ['url' => 'https://x.com/hirfah', 'enabled' => true],
                ['platform' => 'x', 'url' => 'https://x.com/hirfah', 'enabled' => true],
            ],
        ]);

        $this->assertSame([
            ['platform' => 'telegram', 'url' => 'https://t.me/hirfah'],
            ['platform' => 'whatsapp', 'url' => 'https://wa.me/970599000000'],
            ['platform' => 'x', 'url' => 'https://x.com/hirfah'],
        ], $links);
    }

    public function test_the_first_entry_of_a_platform_decides_it(): void
    {
        $this->assertSame([], SocialPlatforms::linksFrom(['social_links' => [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => false],
            ['platform' => 'instagram', 'url' => 'https://instagram.com/b', 'enabled' => true],
        ]]));
        $this->assertSame([['platform' => 'instagram', 'url' => 'https://instagram.com/a']], SocialPlatforms::linksFrom(['social_links' => [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => true],
            ['platform' => 'instagram', 'url' => 'https://instagram.com/b', 'enabled' => true],
        ]]));
    }

    // ---------------------------------------------------------------- FOOTER-SOCIAL-02B: the settings form

    public function test_the_form_sees_every_saved_entry_disabled_or_invalid_and_legacy_links_without_migration(): void
    {
        $this->assertSame([
            ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => true],
            ['platform' => 'facebook', 'url' => 'http://127.0.0.1:8002/dashboard/settings', 'enabled' => true],
        ], SocialPlatforms::entriesFrom(['instagram_url' => 'https://instagram.com/a', 'facebook_url' => 'http://127.0.0.1:8002/dashboard/settings', 'x_url' => '']));

        $this->assertSame([
            ['platform' => 'tiktok', 'url' => 'https://tiktok.com.example.org/a', 'enabled' => true],
            ['platform' => 'x', 'url' => 'https://x.com/a', 'enabled' => false],
        ], SocialPlatforms::entriesFrom(['instagram_url' => 'https://instagram.com/legacy', 'social_links' => [
            ['platform' => 'tiktok', 'url' => ' https://tiktok.com.example.org/a ', 'enabled' => true],
            ['platform' => 'x', 'url' => 'https://x.com/a', 'enabled' => 'yes'],
            ['platform' => 'x', 'url' => 'https://x.com/b', 'enabled' => true],
            ['platform' => 'youtube', 'url' => '', 'enabled' => true],
            ['platform' => 'myspace', 'url' => 'https://myspace.com/a', 'enabled' => true],
        ]]));
        $this->assertSame([], SocialPlatforms::entriesFrom(['social_links' => [], 'x_url' => 'https://x.com/legacy']));
        $this->assertSame([], SocialPlatforms::entriesFrom(null));
    }

    public function test_the_submitted_rows_become_an_ordered_list_without_empty_ones(): void
    {
        $this->assertSame([
            ['platform' => 'telegram', 'url' => 'https://t.me/a', 'enabled' => true],
            ['platform' => 'facebook', 'url' => 'https://facebook.com/a', 'enabled' => false],
            ['platform' => 'x', 'url' => 'https://x.com/a', 'enabled' => true],
        ], SocialPlatforms::fromForm([
            'x' => ['url' => 'https://x.com/a', 'enabled' => '1', 'position' => '3'],
            'facebook' => ['url' => ' https://facebook.com/a ', 'enabled' => '0', 'position' => '2'],
            'telegram' => ['url' => 'https://t.me/a', 'enabled' => '1', 'position' => '1'],
            'youtube' => ['url' => null, 'enabled' => '1', 'position' => '4'],
            'myspace' => ['url' => 'https://myspace.com/a', 'enabled' => '1', 'position' => '5'],
        ]));

        // Without positions: the platforms' order.
        $this->assertSame(['instagram', 'linkedin'], array_column(SocialPlatforms::fromForm([
            'linkedin' => ['url' => 'https://linkedin.com/in/a'], 'instagram' => ['url' => 'https://instagram.com/a'],
        ]), 'platform'));
        $this->assertSame([], SocialPlatforms::fromForm([]));
    }

    public function test_the_legacy_fields_follow_the_list(): void
    {
        $this->assertSame(['instagram_url' => '', 'facebook_url' => 'https://facebook.com/a', 'x_url' => ''], SocialPlatforms::legacyFieldsFor([
            ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => false],
            ['platform' => 'facebook', 'url' => 'https://facebook.com/a', 'enabled' => true],
            ['platform' => 'telegram', 'url' => 'https://t.me/a', 'enabled' => true],
        ]));
        $this->assertSame(['instagram_url' => '', 'facebook_url' => '', 'x_url' => ''], SocialPlatforms::legacyFieldsFor([]));
    }

    public function test_a_stored_empty_or_malformed_list_shows_nothing_and_never_falls_back_to_legacy(): void
    {
        foreach ([[], 'instagram', 42, ['a' => ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => true]]] as $stored) {
            $this->assertSame([], SocialPlatforms::linksFrom(['instagram_url' => 'https://instagram.com/legacy', 'social_links' => $stored]));
        }
    }
}
