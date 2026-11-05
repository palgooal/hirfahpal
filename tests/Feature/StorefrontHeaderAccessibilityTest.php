<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-FIX-01 / VP-FIX-02: the shared storefront header (desktop and mobile menus, cart) and the product cards' add to
 * cart button. Layout and keyboard behaviour were verified in a browser; this guards the markup and script contract.
 */
class StorefrontHeaderAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mobile_menu_button_is_a_disclosure_for_the_mobile_menu(): void
    {
        $html = $this->get(route('vendors'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button id="menuButton" type="button" aria-expanded="false" aria-controls="mobileMenu"/', $html);
        $this->assertStringContainsString('<div id="mobileMenu"', $html);
    }

    public function test_the_mega_menu_button_is_a_disclosure_for_its_panel(): void
    {
        $html = $this->get(route('vendors'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="mega-menu group relative py-1">\s*(?:<!--.*?-->\s*)?<button type="button" class="flex items-center gap-1.5" aria-expanded="false" aria-controls="storefrontMegaMenu">/s', $html);
        $this->assertStringContainsString('<div id="storefrontMegaMenu"', $html);
        // A disclosure, not an ARIA menu: no aria-haspopup on the button.
        $this->assertDoesNotMatchRegularExpression('/aria-haspopup="true" aria-expanded="false">\s*<span>تسوّق<\/span>/u', $html);
    }

    public function test_the_desktop_header_fits_from_1024px_and_keeps_its_spacing_from_1280px(): void
    {
        $header = file_get_contents(resource_path('views/storefront/partials/header.blade.php'));

        // VP-FIX-01: tighter spacing between 1024 and 1279px, the original spacing from xl (1280px).
        $this->assertStringContainsString('class="flex w-auto shrink-0 items-center justify-start gap-3 xl:w-[184px]"', $header);
        // VP-FIX-02A: a 12px nav gap there leaves room for large cart totals.
        $this->assertStringContainsString('class="flex shrink-0 items-center gap-3 text-[15px] font-semibold leading-[22.5px] text-olive xl:gap-8"', $header);
        $this->assertStringContainsString('class="flex h-[50px] shrink-0 items-center gap-2 py-[10px] xl:gap-3 xl:p-[10px]"', $header);
    }

    public function test_the_cart_total_stays_on_one_line_clear_of_the_badge(): void
    {
        $html = $this->get(route('vendors'))->assertOk()->getContent();

        // VP-FIX-02: the button grows from its original width; the total never wraps; the icon keeps a gap before it.
        $this->assertMatchesRegularExpression('/id="cartButton"\s+class="relative flex h-\[30px\] w-auto min-w-\[86\.208px\]/', $html);
        $this->assertStringContainsString('<span class="relative me-1"><img src="'.asset('assets/storefront/imgs/mcp/header-cart.svg').'"', $html);
        $this->assertMatchesRegularExpression('/<bdi id="cartTotal" class="whitespace-nowrap text-right/', $html);
        $this->assertDoesNotMatchRegularExpression('/<bdi id="cartTotal" class="[^"]*\bw-10\b/', $html);
    }

    public function test_the_directory_search_has_a_visible_keyboard_focus_ring(): void
    {
        $this->get(route('vendors'))->assertOk()
            ->assertSee('focus-visible:border-olive focus-visible:ring-2 focus-visible:ring-olive', false);
    }

    public function test_every_hover_revealed_add_to_cart_button_is_also_revealed_by_keyboard_focus(): void
    {
        foreach (['account/favorites', 'browse/index', 'home/new-arrivals', 'home/season', 'vendor/index', 'product/index'] as $view) {
            $source = file_get_contents(resource_path("views/storefront/{$view}.blade.php"));
            preg_match_all('/class="add-cart [^"]*"/', $source, $buttons);

            foreach ($buttons[0] as $classes) {
                if (! str_contains($classes, 'sm:opacity-0')) {
                    continue; // always visible (not hover-revealed)
                }
                $this->assertMatchesRegularExpression('/(?:sm:)?group-focus-within:opacity-100/', $classes, $view);
                $this->assertMatchesRegularExpression('/(?:sm:)?group-focus-within:translate-y-0/', $classes, $view);
            }
        }
    }

    public function test_the_menu_script_keeps_one_state_per_menu_and_handles_escape(): void
    {
        $script = file_get_contents(resource_path('js/storefront.js'));

        $this->assertStringContainsString('const setMegaMenu = ($menu, open) => {', $script);
        $this->assertStringContainsString("children('button').attr('aria-expanded', String(open));", $script);
        $this->assertStringContainsString('const setMobileMenu = (open, moveFocus = false) => {', $script);
        $this->assertStringContainsString("\$menuButton.attr('aria-expanded', String(open));", $script);
        $this->assertStringContainsString("\$('.mega-menu > button').on('click'", $script);
        $this->assertStringContainsString("if (hadFocus) \$(this).children('button').trigger('focus');", $script);
        $this->assertStringContainsString("if (hadFocus) \$menuButton.trigger('focus');", $script);
        // Hover still opens; leaving closes unless the menu was opened by click or keyboard.
        $this->assertMatchesRegularExpression("/\\\$\\('\\.mega-menu'\\)\\.on\\('mouseleave', function \\(\\) \\{\\s*const \\\$menu = \\\$\\(this\\);\\s*if \\(\\\$menu\\.data\\('megaMenuPinned'\\)\\) return;/", $script);
        $this->assertSame(1, substr_count($script, "\$('#menuButton')"));
        $this->assertSame(1, substr_count($script, "\$('.mega-menu > button').on('click'"));
    }
}
