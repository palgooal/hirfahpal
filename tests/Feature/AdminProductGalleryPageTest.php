<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ProductManagementController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminProductTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * PRODUCT-MEDIA-01 P5-C: the product gallery editor of the admin product form and its read-only vendor image
 * endpoints. The endpoints list only the vendor's own canonical images (the chosen vendor for a new product, the
 * stored one for a product) under products.create / products.edit, without media.view or vendors.view, and offer
 * no upload. The form shows the product's images in order with the P5-B fingerprint, keeps the vendor read only on
 * edit, replaces the six media pickers and shows the gallery errors. The browser behaviour is in tests/js.
 */
class AdminProductGalleryPageTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/admin-product-gallery.js';

    private string $hotFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://vite.test');
        Vite::useHotFile($this->hotFile);

        Storage::fake('public');
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);

        foreach ([1, 10, 11] as $id) {
            Vendor::factory()->approved()->create(['id' => $id]);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the endpoints

    public function test_the_create_endpoint_lists_the_chosen_vendors_own_images_only(): void
    {
        $mine = [$this->media('vendors/media/1/a.png'), $this->media('vendors/media/1/b.png')];
        $this->seedOthers();

        $response = $this->createList($this->superAdmin(), ['vendor_id' => 1])->assertOk();

        $this->assertEqualsCanonicalizing(array_map(fn ($m) => $m->id, $mine), array_column($response->json('data'), 'id'));
        $this->assertSame(['alt', 'file_original_name', 'height', 'id', 'title', 'url', 'width'], $this->keys($response->json('data.0')));
        $this->assertSame(['current_page', 'data', 'last_page', 'per_page', 'total'], $this->keys($response->json()));
        $this->assertSame([], $this->createList($this->superAdmin(), ['vendor_id' => 11])->json('data'));
        $this->assertCount(1, $this->createList($this->superAdmin(), ['vendor_id' => 10])->json('data'));
    }

    public function test_the_edit_endpoint_uses_the_products_stored_vendor(): void
    {
        $mine = $this->media('vendors/media/1/a.png');
        $this->seedOthers();
        $product = $this->product(1);

        $ids = array_column($this->editList($this->superAdmin(), $product, ['vendor_id' => 10])->assertOk()->json('data'), 'id');

        $this->assertSame([$mine->id], $ids, 'A vendor_id in the query is ignored.');
        $this->editList($this->superAdmin(), 999999)->assertNotFound();
    }

    public function test_search_and_pagination_stay_inside_the_vendor(): void
    {
        foreach (range(1, 3) as $i) {
            $this->media("vendors/media/1/jug-{$i}.png");
        }
        $this->media('vendors/media/10/jug-other.png');

        $first = $this->createList($this->superAdmin(), ['vendor_id' => 1, 'per_page' => 2])->assertOk();
        $this->assertSame([1, 2, 2, 3], [$first->json('current_page'), $first->json('last_page'), $first->json('per_page'), $first->json('total')]);
        $this->assertCount(1, $this->createList($this->superAdmin(), ['vendor_id' => 1, 'per_page' => 2, 'page' => 2])->json('data'));
        $this->assertCount(3, $this->createList($this->superAdmin(), ['vendor_id' => 1, 'search' => 'jug'])->json('data'));
        $this->createList($this->superAdmin(), ['vendor_id' => 1, 'search' => str_repeat('a', 101)])->assertUnprocessable();
        $this->createList($this->superAdmin(), ['vendor_id' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('vendor_id');
        $this->createList($this->superAdmin(), ['vendor_id' => 999999])->assertUnprocessable();
        $this->createList($this->superAdmin(), [])->assertUnprocessable();
    }

    public function test_the_endpoints_need_the_product_ability_only(): void
    {
        $product = $this->product(1);
        $this->media('vendors/media/1/a.png');

        $this->getJson(route('dashboard.products.vendor-media.create', ['vendor_id' => 1]))->assertUnauthorized();
        $this->getJson(route('dashboard.products.vendor-media.edit', $product))->assertUnauthorized();
        $this->actingAs(Vendor::query()->findOrFail(1), 'vendor')->getJson(route('dashboard.products.vendor-media.create', ['vendor_id' => 1]))->assertUnauthorized();
        $this->app['auth']->forgetGuards();

        // No media.view or vendors.view needed.
        $this->createList($this->staff(['products.create']), ['vendor_id' => 1])->assertOk();
        $this->editList($this->staff(['products.edit']), $product)->assertOk();

        foreach ([[], ['products.edit'], ['media.view', 'vendors.view', 'products.view']] as $abilities) {
            $this->createList($this->staff($abilities), ['vendor_id' => 1])->assertForbidden();
        }

        foreach ([[], ['products.create'], ['media.view', 'vendors.view', 'products.view']] as $abilities) {
            $admin = $this->staff($abilities);
            $this->editList($admin, $product)->assertForbidden();
            $this->editList($admin, 999999)->assertForbidden();
        }
    }

    public function test_the_endpoints_are_read_only(): void
    {
        $super = $this->superAdmin();
        $product = $this->product(1);

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->actingAs($super, 'admin')->{$method}(route('dashboard.products.vendor-media.edit', $product))->assertMethodNotAllowed();
            $this->assertContains($this->actingAs($super, 'admin')->{$method}(route('dashboard.products.vendor-media.create'))->status(), [404, 405]);
        }

        foreach (['dashboard.products.vendor-media.create', 'dashboard.products.vendor-media.edit'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
        }

        // The resource's product page is unaffected by the new path.
        $this->actingAs($super, 'admin')->get(route('dashboard.products.show', $product))->assertOk();
    }

    // ---------------------------------------------------------------- the form

    public function test_the_create_form_has_the_gallery_and_no_media_pickers(): void
    {
        $html = $this->page($this->superAdmin(), route('dashboard.products.create'));
        $config = $this->config($html);

        $this->assertStringContainsString('data-product-gallery', $html);
        $this->assertStringContainsString('id="product-gallery-dialog"', $html);
        $this->assertStringNotContainsString('media_image_ids', $html);
        $this->assertStringNotContainsString('data-media-picker', $html);
        $this->assertStringNotContainsString('admin-media-picker.js', $html);
        $this->assertStringContainsString('resources/js/admin-product-gallery.js', $html);
        $this->assertStringNotContainsString('name="gallery_fingerprint"', $html, 'No fingerprint for a new product.');
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertMatchesRegularExpression('#<select[^>]*id="product-vendor"[^>]*name="vendor_id"#', $html);

        $this->assertFalse($config['editing']);
        $this->assertSame(6, $config['max']);
        $this->assertSame([], $config['items']);
        $this->assertSame(route('dashboard.products.vendor-media.create'), $config['mediaUrl']);
        $this->assertArrayNotHasKey('fingerprint', $config);
    }

    public function test_the_edit_form_shows_the_images_in_order_with_the_fingerprint_and_a_read_only_vendor(): void
    {
        $product = $this->product(1);
        $legacy = $product->images()->create(['path' => 'products/'.$product->id.'/old.png', 'alt_text' => 'Old jug', 'sort_order' => 2, 'is_primary' => false]);
        $own = $product->images()->create(['path' => 'vendors/media/1/own.png', 'alt_text' => 'Blue jug', 'sort_order' => 0, 'is_primary' => true]);
        $foreign = $product->images()->create(['path' => 'vendors/media/10/x.png', 'alt_text' => null, 'sort_order' => 1, 'is_primary' => false]);
        foreach (['products/'.$product->id.'/old.png', 'vendors/media/1/own.png', 'vendors/media/10/x.png'] as $path) {
            Storage::disk('public')->put($path, 'file');
        }

        $html = $this->page($this->superAdmin(), route('dashboard.products.edit', $product));
        $config = $this->config($html);

        $this->assertSame(["existing:{$own->id}", "existing:{$foreign->id}", "existing:{$legacy->id}"], array_column($config['items'], 'ref'));
        $this->assertSame([false, true, true], array_column($config['items'], 'legacy'), 'Files outside the vendor library are legacy images.');
        // P5-C-FIX-02 (D-3): the alternative text names an image; without one, the file name (no media row here).
        $this->assertSame(['Blue jug', 'x.png', 'Old jug'], array_column($config['items'], 'name'));
        $this->assertStringNotContainsString('vendors/media/1/own.png"', json_encode(array_column($config['items'], 'name')));
        $this->assertSame(route('dashboard.products.vendor-media.edit', $product), $config['mediaUrl']);
        $this->assertStringContainsString('name="gallery_fingerprint" value="'.ProductManagementController::galleryFingerprint($product).'"', $html);

        // Rendered for readers without the script too, the main one marked.
        $this->assertMatchesRegularExpression('#data-ref="existing:'.$own->id.'".*?alt="Blue jug".*?is-main#s', $html);
        $this->assertSame(1, substr_count($html, 'admin-product-gallery-badge is-main'));

        // The vendor: a disabled select (ADMIN-PRODUCT-FORM-02, D6), its id still submitted, the label and markers kept.
        $this->assertMatchesRegularExpression('#<select class="form-select" id="product-vendor" disabled aria-disabled="true" aria-required="true"#', $html);
        $this->assertStringContainsString('<input type="hidden" name="vendor_id" value="1">', $html);
        $this->assertDoesNotMatchRegularExpression('#<select[^>]*name="vendor_id"#', $html);
    }

    public function test_the_create_gallery_waits_for_an_explicit_vendor(): void
    {
        // P5-C-FIX-03 (D-1): no vendor is preselected, so the editor starts with "Add image" disabled and the
        // vendor-first note (canChoose() needs a vendor id; the browser check is in P5-C QA). The gallery is unchanged.
        $html = $this->page($this->superAdmin(), route('dashboard.products.create'));
        $config = $this->config($html);

        $this->assertMatchesRegularExpression('#<option value=""\s+selected\s*>Select a vendor</option>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value="[1-9][0-9]*"\s+selected#', $html, 'No vendor is preselected.');
        $this->assertStringContainsString('id="product-gallery-vendor-first"', $html);
        $this->assertStringContainsString('Choose the vendor first to add its images.', $html);
        $this->assertMatchesRegularExpression('#id="product-gallery-add"[^>]*hidden#', $html, 'Shown (disabled) by the script only.');
        $this->assertSame([false, 6, [], route('dashboard.products.vendor-media.create')], [$config['editing'], $config['max'], $config['items'], $config['mediaUrl']]);

        // A vendor chosen and refused for another field comes back selected; the gallery restores its own images.
        $own = $this->media('vendors/media/10/a.png');
        $this->actingAs($this->superAdmin(), 'admin')->from(route('dashboard.products.create'))->post(route('dashboard.products.store'), [
            'vendor_id' => '10', 'name' => '', 'price' => '10', 'stock_quantity' => '4', 'status' => 'draft', 'gallery_sync' => '1', 'gallery' => ["media:{$own->id}"],
        ])->assertSessionHasErrors('name');
        $again = $this->actingAs($this->superAdmin(), 'admin')->get(route('dashboard.products.create'))->getContent();

        $this->assertMatchesRegularExpression('#<option value="10"\s+selected#', $again);
        $this->assertSame(["media:{$own->id}"], array_column($this->config($again)['items'], 'ref'));
    }

    public function test_existing_images_are_named_by_alt_text_then_original_name_then_file_name(): void
    {
        // P5-C-FIX-02 (D-3): never the stored UUID when a better name is known; never a path.
        $product = $this->product(1);
        $withAlt = $this->media('vendors/media/1/aaaa-1111.png');
        $withOriginal = $this->media('vendors/media/1/bbbb-2222.png');
        $foreignOriginal = $this->media('vendors/media/10/cccc-3333.png');
        $rows = [
            ['path' => $withAlt->file_path, 'alt_text' => '  Blue jug  '],
            ['path' => $withOriginal->file_path, 'alt_text' => '   '],
            ['path' => $foreignOriginal->file_path, 'alt_text' => null],
            ['path' => 'products/'.$product->id.'/dddd-4444.png', 'alt_text' => ''],
        ];
        foreach ($rows as $index => $row) {
            $product->images()->create($row + ['sort_order' => $index, 'is_primary' => $index === 0]);
        }
        $before = $product->images()->get()->toArray();

        $config = $this->config($this->page($this->superAdmin(), route('dashboard.products.edit', $product)));

        $this->assertSame([
            'Blue jug',             // 1. the alternative text, trimmed
            'photo-bbbb-2222.png',  // 2. the vendor library image with that exact path: its original name
            'cccc-3333.png',        // 3. another vendor's media row is not trusted: the file name
            'dddd-4444.png',        // 3. no media row: the file name
        ], array_column($config['items'], 'name'));
        $this->assertSame(['  Blue jug  ', '   ', '', ''], array_column($config['items'], 'alt'), 'The alternative text itself is unchanged.');
        $this->assertSame([false, false, true, true], array_column($config['items'], 'legacy'));
        $this->assertTrue(collect($config['items'])->every(fn ($item) => ! str_contains($item['name'], '/')), 'No path in a name.');
        $this->assertSame($before, $product->images()->get()->toArray(), 'Nothing is written.');
    }

    public function test_saving_other_fields_from_the_form_keeps_the_images(): void
    {
        $product = $this->product(1);
        $product->images()->create(['path' => 'vendors/media/1/a.png', 'sort_order' => 0, 'is_primary' => true]);
        $product->images()->create(['path' => 'products/'.$product->id.'/b.png', 'sort_order' => 1, 'is_primary' => false]);
        $before = $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $this->formFields($this->page($admin, route('dashboard.products.edit', $product)), ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame($before, $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray());
    }

    public function test_the_gallery_the_form_submits_is_synced_with_its_fingerprint(): void
    {
        $product = $this->product(1);
        $a = $product->images()->create(['path' => 'vendors/media/1/a.png', 'sort_order' => 0, 'is_primary' => true]);
        $new = $this->media('vendors/media/1/new.png');
        $admin = $this->superAdmin();
        $fields = $this->formFields($this->page($admin, route('dashboard.products.edit', $product)));

        // What the script writes once the list changed: the new image first.
        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $fields + ['gallery_sync' => '1', 'gallery' => ["media:{$new->id}", "existing:{$a->id}"]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['vendors/media/1/new.png', 'vendors/media/1/a.png'], $product->images()->pluck('path')->all());
        $this->assertSame('vendors/media/1/new.png', $product->fresh()->primaryImage->path);
    }

    public function test_a_stale_gallery_is_refused_and_shown_with_its_translated_message(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
        $product = $this->product(1);
        $a = $product->images()->create(['path' => 'vendors/media/1/a.png', 'sort_order' => 0, 'is_primary' => true]);
        $admin = $this->superAdmin();
        $fields = $this->formFields($this->page($admin, route('dashboard.products.edit', $product), 'ar'));

        // The vendor changes the images after the page was opened.
        $vendorImage = $product->images()->create(['path' => 'vendors/products/1/'.$product->id.'/v.png', 'sort_order' => 1, 'is_primary' => false]);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $fields + ['gallery_sync' => '1', 'gallery' => ["existing:{$a->id}"]])
            ->assertRedirect(route('dashboard.products.edit', $product))
            ->assertSessionHasErrors('gallery');

        $this->assertModelExists($vendorImage);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.products.edit', $product))->getContent();
        $message = 'تغيّرت صور المنتج أثناء فتح هذه الصفحة. أعد تحميل الصفحة وراجعها قبل الحفظ.';
        $this->assertStringContainsString('<p class="admin-cat-form-error" id="product-images-error">'.$message.'</p>', $html);
        $this->assertStringContainsString('<a href="#product-images">صور المنتج</a>: '.$message, $html);

        // The submitted list and the fingerprint it was made against come back, so saving it again stays refused.
        $config = $this->config($html);
        $this->assertTrue($config['restored']);
        $this->assertSame(["existing:{$a->id}"], array_column($config['items'], 'ref'));
        $this->assertStringContainsString('name="gallery_fingerprint" value="'.$fields['gallery_fingerprint'].'"', $html);
    }

    public function test_a_refused_new_product_restores_only_the_vendors_own_chosen_images(): void
    {
        $own = $this->media('vendors/media/1/a.png');
        $foreign = $this->media('vendors/media/10/b.png');
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->from(route('dashboard.products.create'))->post(route('dashboard.products.store'), [
            'vendor_id' => '1', 'name' => '', 'price' => '10', 'stock_quantity' => '4', 'status' => 'draft',
            'gallery_sync' => '1', 'gallery' => ["media:{$own->id}", "media:{$foreign->id}", 'existing:5', 'junk'],
        ])->assertSessionHasErrors('name');

        $config = $this->config($this->actingAs($admin, 'admin')->get(route('dashboard.products.create'))->getContent());

        $this->assertSame(["media:{$own->id}"], array_column($config['items'], 'ref'), 'Another vendor\'s, unknown and malformed references are dropped.');
        $this->assertSame('photo-a.png', $config['items'][0]['name']);
        $this->assertSame(0, Product::count());
    }

    public function test_the_six_image_limit_is_the_editors_limit_too(): void
    {
        $product = $this->product(1);
        foreach (range(0, 5) as $i) {
            $product->images()->create(['path' => "vendors/media/1/{$i}.png", 'sort_order' => $i, 'is_primary' => $i === 0]);
        }
        $extra = $this->media('vendors/media/1/extra.png');
        $admin = $this->superAdmin();
        $config = $this->config($this->page($admin, route('dashboard.products.edit', $product)));

        $this->assertSame(6, $config['max']);
        $this->assertCount(6, $config['items']);

        $refs = [...array_column($config['items'], 'ref'), "media:{$extra->id}"];
        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $this->formFields($this->page($admin, route('dashboard.products.edit', $product))) + ['gallery_sync' => '1', 'gallery' => $refs])
            ->assertSessionHasErrors(['gallery' => 'A product can have up to 6 images.']);
        $this->assertSame(6, $product->images()->count());
    }

    // ---------------------------------------------------------------- P5-HARDEN-01: more than six images

    public function test_a_product_with_seven_images_shows_all_of_them_with_a_translated_warning(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
        [$product, $images] = $this->productWithSevenImages();
        $admin = $this->superAdmin();
        $refs = $images->map(fn ($image) => "existing:{$image->id}")->all();

        $en = $this->page($admin, route('dashboard.products.edit', $product), 'en');
        $config = $this->config($en);
        $this->assertSame($refs, array_column($config['items'], 'ref'), 'Every image is listed, in order, none cut off.');
        $this->assertSame(6, $config['max']);
        $this->assertSame(7, preg_match_all('#<li class="admin-product-gallery-item" data-ref="existing:\d+">#', $en), 'All seven are rendered without the script too.');
        $this->assertStringContainsString('7 of 6 images', $en);
        $this->assertMatchesRegularExpression('#<p class="admin-product-gallery-note" id="product-gallery-over"\s*>This product has 7 images, more than the maximum of 6\. Nothing can be added\. Remove images until 6 or fewer remain to save changes to the images\. No image is removed automatically\.</p>#', $en);

        $ar = $this->page($admin, route('dashboard.products.edit', $product), 'ar');
        $this->assertStringContainsString('>يحتوي هذا المنتج على 7 صور، وهذا أكثر من الحد الأقصى وهو 6 صور. لا يمكن إضافة صور. أزل صورًا حتى يبقى 6 أو أقل لحفظ تغييرات الصور. لا تُزال أي صورة تلقائيًا.</p>', $ar);
        $this->assertStringStartsWith('يحتوي هذا المنتج على :count صور', $this->config($ar)['messages']['overMax']);
    }

    public function test_six_images_show_no_over_limit_warning(): void
    {
        $product = $this->product(1);
        foreach (range(0, 5) as $i) {
            $product->images()->create(['path' => "vendors/media/1/{$i}.png", 'sort_order' => $i, 'is_primary' => $i === 0]);
        }

        $html = $this->page($this->superAdmin(), route('dashboard.products.edit', $product));

        $this->assertMatchesRegularExpression('#<p class="admin-product-gallery-note" id="product-gallery-over"\s+hidden\s*></p>#', $html);
        $this->assertStringContainsString('6 of 6 images', $html);
    }

    public function test_other_fields_of_a_product_with_seven_images_save_and_the_images_stay(): void
    {
        [$product, $images] = $this->productWithSevenImages();
        $admin = $this->superAdmin();
        $before = $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray();
        $fields = $this->formFields($this->page($admin, route('dashboard.products.edit', $product)), ['name' => 'Blue clay jug']);

        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $fields)
            ->assertSessionHasNoErrors();

        $this->assertSame('Blue clay jug', $product->fresh()->name);
        $this->assertSame($before, $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray());
        // An addition through the append-only path is still refused.
        $extra = $this->media('vendors/media/1/extra.png');
        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $fields + ['media_image_ids' => [$extra->id]])
            ->assertSessionHasErrors(['media_image_ids' => 'A product can have up to 6 images.']);
        $this->assertSame(7, $product->images()->count());
    }

    public function test_saving_seven_images_is_refused_changes_nothing_and_comes_back_whole(): void
    {
        [$product, $images] = $this->productWithSevenImages();
        $admin = $this->superAdmin();
        $edit = route('dashboard.products.edit', $product);
        $fields = $this->formFields($this->page($admin, $edit));
        $before = $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray();
        // The seven, reordered: the last one first.
        $refs = $images->map(fn ($image) => "existing:{$image->id}")->all();
        $refs = [array_pop($refs), ...$refs];

        $this->actingAs($admin, 'admin')->from($edit)
            ->put(route('dashboard.products.update', $product), $fields + ['gallery_sync' => '1', 'gallery' => $refs])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['gallery' => 'A product can have up to 6 images.']);

        $this->assertSame($before, $product->images()->get(['id', 'path', 'sort_order', 'is_primary'])->toArray(), 'No association changed.');
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }

        // The refused list comes back in full, in the submitted order, never cut to six.
        $html = $this->actingAs($admin, 'admin')->get($edit)->getContent();
        $config = $this->config($html);
        $this->assertTrue($config['restored']);
        $this->assertSame($refs, array_column($config['items'], 'ref'));
        $this->assertStringContainsString('name="gallery_fingerprint" value="'.$fields['gallery_fingerprint'].'"', $html);
    }

    public function test_bringing_seven_images_down_to_six_saves(): void
    {
        [$product, $images] = $this->productWithSevenImages();
        $admin = $this->superAdmin();
        $fields = $this->formFields($this->page($admin, route('dashboard.products.edit', $product)));
        $removed = $images[2];
        $kept = $images->reject(fn ($image) => $image->is($removed))->values();
        // Remove the third, then move the last one first.
        $refs = $kept->map(fn ($image) => "existing:{$image->id}")->all();
        $refs = [array_pop($refs), ...$refs];

        $this->actingAs($admin, 'admin')->from(route('dashboard.products.edit', $product))
            ->put(route('dashboard.products.update', $product), $fields + ['gallery_sync' => '1', 'gallery' => $refs])
            ->assertSessionHasNoErrors();

        $this->assertSame(array_map(fn ($ref) => (int) substr($ref, 9), $refs), $product->images()->pluck('id')->all());
        $this->assertSame($kept->last()->id, $product->fresh()->primaryImage->id, 'The first image is the main one.');
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $this->assertModelMissing($removed);
        Storage::disk('public')->assertExists($removed->path);
    }

    public function test_more_than_six_images_are_still_checked_against_the_vendor_and_the_fingerprint(): void
    {
        [$product, $images] = $this->productWithSevenImages();
        $foreign = $this->media('vendors/media/10/theirs.png');
        $admin = $this->superAdmin();
        $edit = route('dashboard.products.edit', $product);
        $fields = $this->formFields($this->page($admin, $edit));
        $six = $images->take(6)->map(fn ($image) => "existing:{$image->id}")->all();

        // Another vendor's image in place of one of the six: refused.
        $this->actingAs($admin, 'admin')->from($edit)
            ->put(route('dashboard.products.update', $product), $fields + ['gallery_sync' => '1', 'gallery' => [...array_slice($six, 0, 5), "media:{$foreign->id}"]])
            ->assertSessionHasErrors('gallery');
        // A stale fingerprint: refused, even with six.
        $this->actingAs($admin, 'admin')->from($edit)
            ->put(route('dashboard.products.update', $product), ['gallery_fingerprint' => str_repeat('0', 40)] + $fields + ['gallery_sync' => '1', 'gallery' => $six])
            ->assertSessionHasErrors('gallery');

        $this->assertSame(7, $product->images()->count());
    }

    public function test_the_empty_library_explains_that_product_images_come_from_the_vendors_library(): void
    {
        // ADMIN-P5-UX-01: the hint sits in the empty state only (hidden until the script shows an empty library), never
        // in the loading or error states; the first sentence is the script's libraryEmpty message.
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
        $admin = $this->superAdmin();
        $texts = [
            'ar' => ['لا توجد صور في مكتبة وسائط هذا البائع بعد.', 'تُختار صور المنتجات من مكتبة البائع المحدد، وليس من مكتبة وسائط الإدارة. يمكن للبائع رفع الصور من لوحة التحكم الخاصة به.'],
            'en' => ['There are no images in this vendor\'s media library yet.', 'Product images must be selected from the chosen vendor\'s media library, not the admin media library. The vendor can upload images from their own dashboard.'],
        ];

        foreach ($texts as $locale => [$empty, $hint]) {
            foreach ([route('dashboard.products.create'), route('dashboard.products.edit', $this->product(1))] as $url) {
                $html = $this->page($admin, $url, $locale);

                $this->assertMatchesRegularExpression('#<html lang="'.$locale.'"[^>]* dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"#', $html);
                $this->assertSame($empty, $this->config($html)['messages']['libraryEmpty']);
                $this->assertMatchesRegularExpression(
                    '#<div class="admin-media-state" id="product-gallery-choices-empty" hidden>\s*<i[^>]*></i>\s*<span id="product-gallery-choices-empty-text"></span>\s*(?:\{\{--.*?--\}\}\s*)?<span class="admin-media-hint" id="product-gallery-choices-empty-hint" hidden>'.preg_quote(e($hint), '#').'</span>\s*</div>#s',
                    $html
                );
                $this->assertSame(1, substr_count($html, e($hint)), 'Only in the empty state.');
            }
        }

        // The loading and error states keep their own content.
        $this->assertMatchesRegularExpression('#id="product-gallery-loading" role="status" hidden>\s*<span class="admin-media-spinner"[^>]*></span>\s*<span>[^<]+</span>\s*</div>#', $html);
        $this->assertMatchesRegularExpression('#id="product-gallery-error" role="alert" hidden>\s*<i[^>]*></i>\s*<span id="product-gallery-error-text"></span>#', $html);
    }

    public function test_neither_the_script_nor_the_form_data_cut_the_gallery(): void
    {
        $this->assertDoesNotMatchRegularExpression('/\.slice\(\s*0\s*,\s*max\s*\)/', file_get_contents(base_path(self::SCRIPT)));
        $this->assertDoesNotMatchRegularExpression('/->take\(\s*6\s*\)/', file_get_contents(app_path('Http/Controllers/Dashboard/ProductManagementController.php')));
    }

    public function test_the_form_never_creates_translation_rows(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $admin = $this->superAdmin();

        $this->page($admin, route('dashboard.products.create'), 'ar');
        $this->page($admin, route('dashboard.products.edit', $this->product(1)), 'ar');

        $this->assertSame([], TranslationValue::query()->pluck('key')->filter(fn ($key) => str_starts_with($key, 'dashboard.Products_'))->values()->all());
    }

    public function test_the_gallery_is_translated_and_follows_the_direction(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
        $admin = $this->superAdmin();

        $ar = $this->page($admin, route('dashboard.products.create'), 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar"[^>]* dir="rtl"#', $ar);
        $this->assertStringContainsString('إضافة صورة', $ar);
        $this->assertStringContainsString('اختر البائع أولًا لإضافة صوره.', $ar);
        $this->assertSame('أُضيفت :name.', $this->config($ar)['messages']['added']);

        $en = $this->page($admin, route('dashboard.products.create'), 'en');
        $this->assertMatchesRegularExpression('#<html lang="en"[^>]* dir="ltr"#', $en);
        $this->assertStringContainsString('Add image', $en);
    }

    // ---------------------------------------------------------------- the script

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-product-gallery.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output, 'The JS tests must actually run.');
    }

    public function test_the_script_holds_no_wording_no_html_injection_and_no_writes(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source);
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $source);
        $this->assertDoesNotMatchRegularExpression("/method:\\s*'(?:POST|PUT|PATCH|DELETE)'/i", $source, 'The chooser only reads.');
        $this->assertStringNotContainsString('body.message', $source);

        preg_match_all('/messages\.([a-zA-Z]+)/', $source, $matches);
        $partial = file_get_contents(resource_path('views/dashboard/products/partials/gallery.blade.php'));
        $this->assertGreaterThan(15, count(array_unique($matches[1])));

        foreach (array_unique($matches[1]) as $key) {
            $this->assertMatchesRegularExpression("/'{$key}' => \\\$prodText\\(/", $partial, "messages.{$key} must be provided by the page.");
        }
    }

    // ---------------------------------------------------------------- helpers

    private function seedOthers(): void
    {
        foreach ([
            'vendors/media/10/b.png', 'vendors/media-old/1/c.png', 'vendors/media/01/d.png', 'VENDORS/media/1/e.png', 'media/2026/10/admin.png',
        ] as $path) {
            $this->media($path, $path === 'media/2026/10/admin.png' ? ['uploader_id' => 1] : []);
        }

        $this->media('vendors/media/1/guide.pdf', ['file_type' => 'document', 'mime_type' => 'application/pdf']);
    }

    private function createList(Admin $admin, array $query): TestResponse
    {
        return $this->actingAs($admin, 'admin')->getJson(route('dashboard.products.vendor-media.create', $query));
    }

    private function editList(Admin $admin, Product|int $product, array $query = []): TestResponse
    {
        $id = $product instanceof Product ? $product->id : $product;

        return $this->actingAs($admin, 'admin')->getJson(route('dashboard.products.vendor-media.edit', ['product' => $id] + $query));
    }

    private function page(Admin $admin, string $url, string $locale = 'en'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="product-gallery-config">(.*?)</script>#s', $html, $match), 'Gallery config not rendered.');

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** The named inputs, selects and textareas the edit form submits, as a browser would send them. */
    private function formFields(string $html, array $overrides = []): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($dom);
        $fields = [];

        foreach ($xpath->query('//form[@novalidate]//input[@name]') as $input) {
            if (in_array($input->getAttribute('type'), ['checkbox', 'radio'], true) && ! $input->hasAttribute('checked')) {
                continue;
            }

            $name = $input->getAttribute('name');
            str_ends_with($name, '[]') ? $fields[substr($name, 0, -2)][] = $input->getAttribute('value') : $fields[$name] = $input->getAttribute('value');
        }
        foreach ($xpath->query('//form[@novalidate]//select[@name]') as $select) {
            $selected = $xpath->query('.//option[@selected]', $select)->item(0) ?? $xpath->query('.//option', $select)->item(0);
            $fields[$select->getAttribute('name')] = $selected?->getAttribute('value') ?? '';
        }
        foreach ($xpath->query('//form[@novalidate]//textarea[@name]') as $textarea) {
            $fields[$textarea->getAttribute('name')] = $textarea->textContent;
        }

        unset($fields['_method']);

        // Empty tag fields are left out: a browser sends them as "", which ConvertEmptyStringsToNull turns into null,
        // and the existing highlight_tags.* / craft_tags.* rules (string, without nullable) refuse null. That defect
        // predates the gallery and is reported separately; these tests are about the images.
        foreach (['highlight_tags', 'craft_tags'] as $tags) {
            $fields[$tags] = array_values(array_filter($fields[$tags] ?? [], fn ($tag) => $tag !== ''));

            if ($fields[$tags] === []) {
                unset($fields[$tags]);
            }
        }

        return $overrides + $fields;
    }

    /** @param  array<string, mixed>  $data */
    private function keys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }

    private function product(int $vendorId): Product
    {
        // ADMIN-PRODUCT-FORM-02: a category is required on save, so the fixture has one.
        $category = Category::query()->firstOrCreate(['slug' => 'gallery-fixture'], ['name' => 'Pottery', 'is_active' => true]);

        return Product::create(['vendor_id' => $vendorId, 'category_id' => $category->id, 'name' => 'Clay jug', 'slug' => 'clay-jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 4, 'status' => 'draft']);
    }

    /**
     * A product given seven images outside the admin form (the vendor side has no limit yet), files on the disk.
     *
     * @return array{0: Product, 1: Collection<int, ProductImage>}
     */
    private function productWithSevenImages(): array
    {
        $product = $this->product(1);
        $images = collect(range(0, 6))->map(function (int $i) use ($product) {
            Storage::disk('public')->put("vendors/media/1/seven-{$i}.png", 'file');

            return $product->images()->create(['path' => "vendors/media/1/seven-{$i}.png", 'alt_text' => "Jug {$i}", 'sort_order' => $i, 'is_primary' => $i === 0]);
        });

        return [$product, $images];
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    private function staff(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function media(string $path, array $attributes = []): Media
    {
        Storage::disk('public')->put($path, 'file');

        return Media::create($attributes + [
            'file_name' => basename($path),
            'file_original_name' => 'photo-'.basename($path),
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }

    private function node(): string
    {
        foreach (['node', 'node.exe'] as $candidate) {
            $found = trim((string) shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ').$candidate.' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));

            if ($found !== '') {
                return strtok($found, "\r\n");
            }
        }

        $this->markTestSkipped('Node.js is not available.');
    }
}
