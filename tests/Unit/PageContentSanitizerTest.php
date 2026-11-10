<?php

namespace Tests\Unit;

use App\Support\Content\PageContentSanitizer;
use Tests\TestCase;

/**
 * PAGES-02: the content page HTML allowlist, link and media rules, XSS vectors and the plain-text conversion.
 */
class PageContentSanitizerTest extends TestCase
{
    private PageContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://hirfah.test']);
        $this->sanitizer = new PageContentSanitizer;
    }

    public function test_the_allowed_structure_is_kept(): void
    {
        $html = '<h2 dir="rtl">عنوان</h2><h3>Sub</h3><h4>Small</h4><p dir="ltr">A <strong>b</strong> <em>c</em> <u>d</u> <s>e</s><br>f</p>'
            .'<blockquote>q</blockquote><hr><ul><li>one</li></ul><ol start="3"><li>three</li></ol>'
            .'<table><thead><tr><th scope="col" colspan="2">h</th></tr></thead><tbody><tr><td rowspan="2">c</td></tr></tbody></table>';

        $clean = $this->sanitizer->sanitize($html);

        foreach (['<h2 dir="rtl">عنوان</h2>', '<h3>Sub</h3>', '<h4>Small</h4>', '<p dir="ltr">A <strong>b</strong> <em>c</em> <u>d</u> <s>e</s><br />f</p>',
            '<blockquote>q</blockquote>', '<hr />', '<ul><li>one</li></ul>', '<ol start="3"><li>three</li></ol>', '<th scope="col" colspan="2">h</th>', '<td rowspan="2">c</td>'] as $kept) {
            $this->assertStringContainsString($kept, $clean);
        }
        $this->assertSame($clean, $this->sanitizer->sanitize($clean), 'Cleaning twice changes nothing.');
    }

    public function test_scripts_handlers_styles_and_embeds_are_removed(): void
    {
        $vectors = [
            '<script>alert(1)</script>', '<p onclick="alert(1)">x</p>', '<img src="/storage/media/a.png" onerror="alert(1)">',
            '<svg onload="alert(1)"><circle /></svg>', '<iframe src="https://evil.example"></iframe>', '<object data="x"></object>',
            '<embed src="x">', '<form action="/x"><input name="a"><button>b</button></form>', '<style>p{color:red}</style>',
            '<p style="background:url(javascript:alert(1))">x</p>', '<math><mi>x</mi></math>', '<base href="https://evil.example/">',
            '<meta http-equiv="refresh" content="0;url=https://evil.example">', '<link rel="stylesheet" href="https://evil.example/x.css">',
            '<p class="x" id="y" data-x="1" title="t">x</p>', '<h1>Page title twice</h1>', '<noscript><p>x</p></noscript>',
            '<template><script>alert(1)</script></template>', '<a href="https://x.example" target="_blank" onmouseover="alert(1)">x</a>',
            '<img src="/storage/media/a.png" srcset="https://evil.example/a.png 2x">', '<details open ontoggle="alert(1)">x</details>',
        ];

        foreach ($vectors as $vector) {
            $clean = $this->sanitizer->sanitize($vector);
            $this->assertDoesNotMatchRegularExpression('#<(script|svg|iframe|object|embed|form|input|button|style|math|base|meta|link|h1|noscript|template|details)\b#i', $clean, $vector);
            $this->assertDoesNotMatchRegularExpression('#\s(on[a-z]+|style|class|id|data-[a-z-]+|title|target|srcset)=#i', $clean, $vector);
            $this->assertStringNotContainsStringIgnoringCase('javascript', $clean, $vector);
        }

        // Wrappers (div, span, b…) go but keep their text.
        $this->assertSame('Text x <strong>bold</strong>', $this->sanitizer->sanitize('<div><span>Text <b>x</b></span> <strong>bold</strong></div>'));
    }

    public function test_links_keep_only_safe_destinations_and_always_get_rel(): void
    {
        $kept = ['https://example.org/a?b=c#d', 'http://example.org', 'mailto:info@hirfah.test', 'tel:+970599000000', '/pages/faq', '#section'];
        foreach ($kept as $href) {
            // The sanitizer may encode characters such as "=" (&#61;): compare the decoded address.
            $clean = $this->sanitizer->sanitize('<a href="'.e($href).'">x</a>');
            $this->assertSame(1, preg_match('#^<a href="([^"]*)" rel="noopener noreferrer">x</a>$#', $clean, $match), $href);
            $this->assertSame($href, html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), $href);
        }

        $refused = ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'jav&#x09;ascript:alert(1)', 'jav&#x0A;ascript:alert(1)', '&#106;avascript:alert(1)',
            ' javascript:alert(1)', 'vbscript:msgbox(1)', 'data:text/html,<script>alert(1)</script>', 'file:///etc/passwd', '//evil.example/x',
            '\\\\evil.example\\x', '/\\evil.example', 'relative/path', 'ftp://example.org', 'https://exa mple.org'];
        foreach ($refused as $href) {
            $clean = $this->sanitizer->sanitize('<a href="'.$href.'">x</a>');
            $this->assertDoesNotMatchRegularExpression('#href=#', $clean, $href);
            $this->assertStringContainsString('rel="noopener noreferrer"', $clean, $href);
        }
    }

    public function test_images_come_only_from_this_sites_media_library_as_relative_paths(): void
    {
        $kept = [
            '/storage/media/2026/10/a.jpg' => '/storage/media/2026/10/a.jpg',
            'https://hirfah.test/storage/media/2026/10/b.webp' => '/storage/media/2026/10/b.webp',
            'http://hirfah.test/storage/media/c.PNG' => '/storage/media/c.PNG',
            '/storage/media/x_y-1.2.gif' => '/storage/media/x_y-1.2.gif',
        ];
        foreach ($kept as $src => $stored) {
            $this->assertSame('<img src="'.$stored.'" alt="A" width="640" height="480" />', $this->sanitizer->sanitize('<img src="'.$src.'" alt="A" width="640" height="480">'), $src);
        }

        $refused = ['https://evil.example/storage/media/a.jpg', 'https://hirfah.test.evil.example/storage/media/a.jpg', 'https://hirfah.test:8443/storage/media/a.jpg',
            '//hirfah.test/storage/media/a.jpg', '/storage/media/../../.env', '/storage/media/%2e%2e/a.jpg', '/storage/media/a.jpg?x=1', '/storage/media/a.jpg#x',
            '/storage/media/a.php', '/storage/media/a.svg', '/storage/other/a.jpg', '/storage/media/./a.jpg', 'data:image/png;base64,AAAA',
            'javascript:alert(1)', '/storage/media\\a.jpg', 'https://user:pass@hirfah.test/storage/media/a.jpg', 'storage/media/a.jpg', ''];
        foreach ($refused as $src) {
            $this->assertSame('', $this->sanitizer->sanitize('<img src="'.$src.'" alt="A">'), $src.' (the whole image goes)');
        }

        $this->assertSame('<img src="/storage/media/a.jpg" />', $this->sanitizer->sanitize('<img src="/storage/media/a.jpg" width="0" height="99999">'), 'Out-of-range sizes are dropped.');
    }

    public function test_attribute_values_are_limited(): void
    {
        $this->assertSame('<p>x</p>', $this->sanitizer->sanitize('<p dir="sideways">x</p>'));
        $this->assertSame('<p dir="auto">x</p>', $this->sanitizer->sanitize('<p dir="AUTO">x</p>'));
        $this->assertStringContainsString('<td>c</td>', $this->sanitizer->sanitize('<table><tr><td colspan="999" rowspan="-1">c</td></tr></table>'));
        $this->assertStringContainsString('<th>h</th>', $this->sanitizer->sanitize('<table><tr><th scope="everything">h</th></tr></table>'));
        $this->assertSame('<ol><li>x</li></ol>', $this->sanitizer->sanitize('<ol start="1e9"><li>x</li></ol>'));
    }

    public function test_plain_text_becomes_escaped_paragraphs_and_line_breaks(): void
    {
        $this->assertSame("<p>Q one?<br>Answer one.</p>\n<p>Second &lt;b&gt;para&lt;/b&gt; &amp; &quot;more&quot;</p>",
            PageContentSanitizer::fromPlainText("  Q one?\r\nAnswer one.\r\n\r\n\n  Second <b>para</b> & \"more\"  "));
        $this->assertSame('', PageContentSanitizer::fromPlainText("  \n\n "));
        $this->assertSame('', PageContentSanitizer::fromPlainText(null));
        $converted = PageContentSanitizer::fromPlainText("<script>alert(1)</script>\n\nok");
        $this->assertSame("<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n<p>ok</p>", $converted, 'Markup in the text is escaped, never parsed.');
        $this->assertStringNotContainsString('<script', $this->sanitizer->sanitize($converted));
    }

    public function test_a_body_within_the_limit_is_never_cut_even_in_four_byte_characters(): void
    {
        foreach (['ب', '😀'] as $character) {
            $body = '<p>'.str_repeat($character, PageContentSanitizer::MAX_LENGTH - 7).'</p>';
            $this->assertSame($body, $this->sanitizer->sanitize($body), 'Characters, not bytes: '.strlen($character).'-byte text.');
        }
    }

    public function test_plain_text_and_content_helpers(): void
    {
        $this->assertSame(implode("\n", ['Title', '', 'One', 'two', '', 'A B', '', 'item']), PageContentSanitizer::toPlainText('<h2>Title</h2><p>One<br />two</p><table><tr><td>A</td><td>B</td></tr></table><ul><li>item</li></ul>'));
        $this->assertSame('Tom & Jerry', PageContentSanitizer::toPlainText('<p>Tom &amp; Jerry</p>'));
        $this->assertFalse(PageContentSanitizer::hasContent('<p></p><p> </p>'));
        $this->assertTrue(PageContentSanitizer::hasContent('<img src="/storage/media/a.jpg" alt="a" />'));
        $this->assertTrue(PageContentSanitizer::hasContent('<p>x</p>'));
    }
}
