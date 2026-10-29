<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-MEDIA-03A: the vendor's media library listing (vendor.dashboard.products.media.library). Without parameters
 * it is the whole library as {images}, as the product editor reads it; page, per_page or search turn it into one page
 * as {images, pagination}. Either way only the canonical vendors/media/{vendorId}/ folder is listed.
 */
class VendorMediaLibraryListingTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    // ---------------------------------------------------------------- the product editor's contract

    public function test_without_parameters_the_whole_library_keeps_the_editors_shape_and_order(): void
    {
        $older = $this->library($this->vendor, ['file_original_name' => 'old.png'], '2026-10-01 10:00:00');
        $newer = $this->library($this->vendor, ['file_original_name' => 'new.png', 'alt' => 'إبريق فخاري'], '2026-10-02 10:00:00');
        $more = collect(range(1, 30))->map(fn () => $this->library($this->vendor, [], '2026-09-01 10:00:00'));

        $response = $this->list()->assertOk();

        $this->assertSame(['images'], array_keys($response->json()), 'No pagination key without parameters.');
        $this->assertCount(32, $response->json('images'), 'The whole library, not a page of it.');
        $this->assertSame([$newer->id, $older->id], array_slice($this->ids($response), 0, 2));
        $this->assertSame($more->pluck('id')->sortDesc()->values()->all(), array_slice($this->ids($response), 2));
        $this->assertSame([
            'id' => $newer->id,
            'path' => $newer->file_path,
            'alt_text' => 'إبريق فخاري',
            'url' => $newer->url,
        ], $response->json('images.0'));
        $this->assertSame('old.png', $response->json('images.1.alt_text'), 'Without alt, the original name is the alt text.');
    }

    public function test_a_paged_answer_keeps_the_same_image_items(): void
    {
        $media = $this->library($this->vendor, ['alt' => 'Jug']);

        $this->list(['page' => 1])->assertOk()->assertExactJson([
            'images' => [['id' => $media->id, 'path' => $media->file_path, 'alt_text' => 'Jug', 'url' => $media->url]],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 24, 'total' => 1],
        ]);
    }

    public function test_guests_cannot_list_the_library(): void
    {
        $this->getJson(route('vendor.dashboard.products.media.library'))->assertUnauthorized();
        $this->getJson(route('vendor.dashboard.products.media.library', ['page' => 1, 'search' => 'jug']))->assertUnauthorized();
    }

    // ---------------------------------------------------------------- ownership

    public function test_only_the_vendors_own_images_are_listed_with_or_without_parameters(): void
    {
        $own = $this->library($this->vendor, ['file_original_name' => 'jug.png']);
        $other = Vendor::factory()->approved()->create();
        $this->library($other, ['file_original_name' => 'jug.png']);
        $this->media('media/2026/10/jug.png', ['file_original_name' => 'jug.png']);
        // Vendor 1's folder is not a prefix of vendor 10's or 11's.
        foreach ([$this->vendor->id * 10, $this->vendor->id * 10 + 1, $this->vendor->id + 1000] as $id) {
            $this->media("vendors/media/{$id}/".uniqid().'.png', ['file_original_name' => 'jug.png']);
        }

        $this->assertSame([$own->id], $this->ids($this->list()));
        $this->assertSame([$own->id], $this->ids($this->list(['search' => 'jug', 'per_page' => 50])));
        $this->assertSame(1, $this->list(['page' => 1])->json('pagination.total'));
        $this->assertSame([], $this->ids($this->list(['page' => 2])), 'No page reaches other folders.');
    }

    public function test_only_canonical_image_paths_of_the_folder_are_listed(): void
    {
        $own = $this->library($this->vendor);
        $id = $this->vendor->id;
        $other = $id + 1000;
        foreach ([
            "vendors/media/{$id}//double.png",
            "VENDORS/MEDIA/{$id}/upper.png",
            "Vendors/media/{$id}/mixed.png",
            "/vendors/media/{$id}/leading.png",
            "vendors/media/{$id}/sub/nested.png",
            "vendors/media/{$id}/../{$other}/escape.png",
            "vendors/media/{$id}\\back.png",
            "vendors/media/{$id}/",
            "vendors/media/{$id}/..",
        ] as $path) {
            $this->media($path);
        }
        $this->media("vendors/media/{$id}/notes.pdf", ['file_type' => 'document', 'mime_type' => 'application/pdf']);

        $this->assertSame([$own->id], $this->ids($this->list()));
        $this->assertSame([$own->id], $this->ids($this->list(['per_page' => 50])));
    }

    // ---------------------------------------------------------------- pagination

    public function test_pages_are_stable_when_timestamps_are_equal(): void
    {
        $ids = collect(range(1, 5))->map(fn () => $this->library($this->vendor, [], '2026-10-01 10:00:00')->id)->sortDesc()->values()->all();

        $pages = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->list(['page' => $page, 'per_page' => 2])->assertOk()
                ->assertJsonPath('pagination', ['current_page' => $page, 'last_page' => 3, 'per_page' => 2, 'total' => 5]);
            $pages[] = $this->ids($response);
        }

        $this->assertSame([array_slice($ids, 0, 2), array_slice($ids, 2, 2), array_slice($ids, 4)], $pages);
        $this->assertSame($pages, array_map(fn (int $page) => $this->ids($this->list(['page' => $page, 'per_page' => 2])), [1, 2, 3]), 'Asking again gives the same pages.');
    }

    public function test_page_size_boundaries(): void
    {
        collect(range(1, 51))->each(fn () => $this->library($this->vendor));

        $this->list(['page' => 1])->assertJsonCount(24, 'images')->assertJsonPath('pagination.per_page', 24);
        $this->list(['per_page' => 1])->assertJsonCount(1, 'images')->assertJsonPath('pagination.last_page', 51);
        $this->list(['per_page' => 50])->assertJsonCount(50, 'images')->assertJsonPath('pagination.last_page', 2);
        $this->list(['per_page' => 50, 'page' => 2])->assertJsonCount(1, 'images');
        $this->list(['per_page' => 50, 'page' => 3])->assertOk()->assertJsonCount(0, 'images')
            ->assertJsonPath('pagination', ['current_page' => 3, 'last_page' => 2, 'per_page' => 50, 'total' => 51]);
    }

    // ---------------------------------------------------------------- search

    public function test_search_matches_the_original_name_title_and_alt_text(): void
    {
        $byName = $this->library($this->vendor, ['file_original_name' => 'blue-vase.png'], '2026-10-03 10:00:00');
        $byTitle = $this->library($this->vendor, ['title' => 'Vase of the week'], '2026-10-02 10:00:00');
        $byAlt = $this->library($this->vendor, ['alt' => 'A small vase'], '2026-10-01 10:00:00');
        $arabic = $this->library($this->vendor, ['alt' => 'سجادة صوف يدوية']);
        $this->library($this->vendor, ['file_original_name' => 'jug.png', 'caption' => 'vase', 'description' => 'vase']);

        $this->assertSame([$byName->id, $byTitle->id, $byAlt->id], $this->ids($this->list(['search' => 'vase'])));
        $this->assertSame([$arabic->id], $this->ids($this->list(['search' => 'صوف'])));
        $this->assertSame([], $this->ids($this->list(['search' => 'missing'])));
        $this->list(['search' => 'vase'])->assertJsonPath('pagination.total', 3);
        $this->list(['search' => '  vase  '])->assertJsonPath('pagination.total', 3);
    }

    public function test_search_is_literal_and_cannot_widen_the_folder(): void
    {
        $percent = $this->library($this->vendor, ['file_original_name' => '100%_cotton.png']);
        $this->library($this->vendor, ['file_original_name' => '100 cotton.png']);
        $this->library($this->vendor, ['file_original_name' => '100xxcotton.png']);
        $other = Vendor::factory()->approved()->create();
        $this->library($other, ['file_original_name' => "x' or 1=1 --"]);

        $this->assertSame([$percent->id], $this->ids($this->list(['search' => '%_'])));
        $this->assertSame([$percent->id], $this->ids($this->list(['search' => '100%'])));
        $this->assertSame([$percent->id], $this->ids($this->list(['search' => '%'])), 'Only the literal % matches.');
        $this->assertSame([], $this->ids($this->list(['search' => '_0_cotton'])), 'As a wildcard, _ would match "100 cotton".');
        $this->assertSame([], $this->ids($this->list(['search' => '!'])));
        $theirs = Media::query()->where('file_path', 'like', "vendors/media/{$other->id}/%")->value('id');
        foreach (["x' or 1=1 --", "') or ('1'='1", '%', '\\', 'vendors/media/'.$other->id] as $term) {
            $this->assertNotContains($theirs, $this->ids($this->list(['search' => $term])), $term);
        }
    }

    // ---------------------------------------------------------------- validation

    public function test_invalid_parameters_are_a_422(): void
    {
        $this->library($this->vendor);

        foreach ([
            'page' => [['page' => 0], ['page' => -1], ['page' => 'abc'], ['page' => '1.5'], ['page' => ''], ['page' => ['1']]],
            'per_page' => [['per_page' => 0], ['per_page' => 51], ['per_page' => 'all'], ['per_page' => ''], ['per_page' => ['10']]],
            'search' => [['search' => ['jug']], ['search' => str_repeat('a', 101)]],
        ] as $field => $cases) {
            foreach ($cases as $query) {
                $this->list($query)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }

        $this->list(['search' => str_repeat('ب', 100)])->assertOk();
        $this->list(['search' => ''])->assertOk()->assertJsonPath('pagination.total', 1);
    }

    // ---------------------------------------------------------------- empty library

    public function test_an_empty_library(): void
    {
        $this->media('media/2026/10/admin.png');

        $this->list()->assertOk()->assertExactJson(['images' => []]);
        $this->list(['page' => 1])->assertOk()->assertExactJson([
            'images' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 24, 'total' => 0],
        ]);
        $this->list(['search' => 'jug', 'page' => 5])->assertOk()->assertJsonCount(0, 'images')->assertJsonPath('pagination.total', 0);
    }

    // ---------------------------------------------------------------- helpers

    private function list(array $query = []): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.media.library', $query));
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return array_column($response->json('images') ?? [], 'id');
    }

    private function library(Vendor $vendor, array $attributes = [], ?string $createdAt = null): Media
    {
        return $this->media("vendors/media/{$vendor->id}/".uniqid('', true).'.png', $attributes + ['uploader_id' => $vendor->id], $createdAt);
    }

    private function media(string $path, array $attributes = [], ?string $createdAt = null): Media
    {
        $media = Media::create([
            'file_name' => basename($path), 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public',
        ]);
        $media->forceFill($attributes)->save();

        if ($createdAt !== null) {
            DB::table('media')->where('id', $media->id)->update(['created_at' => $createdAt]);
        }

        return $media->fresh();
    }
}
