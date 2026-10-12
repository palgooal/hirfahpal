<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * AD-UI-CAT-M1: baseline of the media library package (palgoal/media-library v0.1.0) as it behaves today,
 * before any HIRFAH change. Every test runs on the in-memory test database and a faked public disk.
 *
 * Tests named test_finding_* pin CURRENT behaviour that is NOT acceptable and must change in M2 or later:
 * when that work lands, each of them is expected to fail and be rewritten with the secure expectation.
 * M2-A closed M1-F1 and M1-F3 (their tests below now hold the secure expectation; details in
 * MediaLibraryUploadSafetyTest and MediaLibraryAuthorizationTest). M2-C closed M1-F2 (MediaLibrarySafeDeletionTest).
 */
class MediaLibraryBaselineTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The page renders inside the admin shell since AD-UI-CAT-M3, like every other admin page test.
        $this->withoutVite();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- access

    public function test_guest_cannot_reach_any_library_route(): void
    {
        $media = $this->storedMedia();

        $this->get(route('media-library.page'))->assertRedirect(route('admin.login'));
        $this->get(route('media-library.media.index'))->assertRedirect(route('admin.login'));
        $this->getJson(route('media-library.media.index'))->assertUnauthorized();
        $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
        $this->getJson(route('media-library.media.show', $media->id))->assertUnauthorized();
        $this->putJson(route('media-library.media.update', $media->id), ['alt' => 'x'])->assertUnauthorized();
        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertUnauthorized();
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id]])->assertUnauthorized();

        $this->assertSame(1, Media::count());
        $this->assertNull($media->fresh()->alt);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_vendor_and_customer_sessions_cannot_reach_the_library(): void
    {
        $media = $this->storedMedia();
        $accounts = [
            'vendor' => Vendor::factory()->approved()->create(),
            'customer' => Customer::factory()->create(),
        ];

        foreach ($accounts as $guard => $account) {
            $this->actingAs($account, $guard);

            $this->get(route('media-library.page'))->assertRedirect(route('admin.login'));
            $this->getJson(route('media-library.media.index'))->assertUnauthorized();
            $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
            $this->deleteJson(route('media-library.media.destroy', $media->id))->assertUnauthorized();
        }

        $this->assertSame(1, Media::count());
    }

    public function test_inactive_admins_are_signed_out_and_refused(): void
    {
        $media = $this->storedMedia();

        foreach (['blocked', 'pending'] as $status) {
            $this->actingAs($this->admin(superAdmin: true, status: $status), 'admin');
            $this->getJson(route('media-library.media.index'))->assertUnauthorized()->assertExactJson(['message' => __('auth.inactive')]);
            $this->assertGuest('admin');

            $this->actingAs($this->admin(superAdmin: true, status: $status), 'admin');
            $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();

            $this->actingAs($this->admin(superAdmin: true, status: $status), 'admin');
            $this->deleteJson(route('media-library.media.destroy', $media->id))->assertUnauthorized();

            $this->actingAs($this->admin(superAdmin: true, status: $status), 'admin');
            $this->get(route('media-library.page'))->assertRedirect(route('admin.login'));
        }

        $this->assertSame(1, Media::count());
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_active_admin_reaches_the_page_and_the_json_list(): void
    {
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        $this->get(route('media-library.page'))->assertOk();
        $this->getJson(route('media-library.media.index'))->assertOk();
    }

    // ---------------------------------------------------------------- upload

    public function test_a_valid_image_is_uploaded_and_recorded(): void
    {
        $admin = $this->admin(superAdmin: true);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('Pottery Bowl.png', 40, 30)])
            ->assertCreated()
            ->assertJsonPath('file_original_name', 'Pottery Bowl.png')
            ->assertJsonPath('file_type', 'image')
            ->assertJsonPath('file_extension', 'png')
            ->assertJsonPath('disk', 'public')
            ->assertJsonPath('width', 40)
            ->assertJsonPath('height', 30);

        $media = Media::findOrFail($response->json('id'));
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[^/]+\.png$#', $media->file_path);
        $this->assertStringNotContainsString('Pottery', $media->file_path, 'The stored name is generated, not the client name.');
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_several_images_are_uploaded_through_files_array(): void
    {
        $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->postJson(route('media-library.media.store'), ['files' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.webp'),
                UploadedFile::fake()->image('three.gif'),
            ]])
            ->assertCreated()
            ->assertJsonCount(3, 'uploaded');

        $this->assertSame(3, Media::count());
        Media::all()->each(fn (Media $media) => Storage::disk('public')->assertExists($media->file_path));
    }

    public function test_uploader_id_is_the_signed_in_admin(): void
    {
        $first = $this->admin(superAdmin: true);
        $second = $this->admin(superAdmin: true);

        $a = $this->actingAs($first, 'admin')->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertCreated();
        $b = $this->actingAs($second, 'admin')->postJson(route('media-library.media.store'), ['files' => [UploadedFile::fake()->image('b.png')]])->assertCreated();

        $this->assertSame($first->id, Media::findOrFail($a->json('id'))->uploader_id);
        $this->assertSame($second->id, Media::findOrFail($b->json('uploaded.0.id'))->uploader_id);
    }

    public function test_svg_is_rejected_whatever_its_name(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        foreach (['logo.svg', 'logo.png', 'logo.jpg'] as $name) {
            $this->postJson(route('media-library.media.store'), ['image' => $this->realUpload($name, $svg)])->assertUnprocessable()->assertJsonValidationErrors('image');
            $this->postJson(route('media-library.media.store'), ['files' => [$this->realUpload($name, $svg)]])->assertUnprocessable()->assertJsonValidationErrors('files.0');
        }

        $this->assertNothingStored();
    }

    public function test_php_disguised_as_an_image_is_rejected(): void
    {
        $php = '<?php system($_GET["c"]); ?>';
        $gifWithPhp = "GIF89a\x01\x00\x01\x00".$php;
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        $cases = [
            'shell.jpg' => $php,        // image name, PHP content: content sniffed as text/x-php
            'shell.php.png' => $php,    // double extension, PHP content
            'shell.php' => $gifWithPhp, // image content, PHP extension: blocked by the framework's PHP upload guard
            'shell.phtml' => $gifWithPhp,
            'shell.phar' => $gifWithPhp,
        ];

        foreach ($cases as $name => $content) {
            $this->postJson(route('media-library.media.store'), ['image' => $this->realUpload($name, $content)])->assertUnprocessable()->assertJsonValidationErrors('image');
        }

        $this->assertNothingStored();
    }

    public function test_the_size_limit_is_enforced(): void
    {
        $this->assertSame(10240, (int) config('media-library.max_upload_size_kb', 10240));
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('big.jpg')->size(10241)])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(route('media-library.media.store'), ['files' => [UploadedFile::fake()->image('ok.jpg'), UploadedFile::fake()->image('big.jpg')->size(10241)]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.1');
        $this->assertNothingStored();

        $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('limit.jpg')->size(10240)])->assertCreated();
        $this->assertSame(1, Media::count());
    }

    public function test_a_request_without_a_file_is_rejected(): void
    {
        $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->postJson(route('media-library.media.store'), ['image' => 'not-a-file'])
            ->assertUnprocessable();

        $this->assertNothingStored();
    }

    /**
     * M1-F1, closed in M2-A: a GIF-headed polyglot named with an active extension used to be stored as
     * <id>.html on the public disk (stored XSS). It is now refused and nothing is written.
     */
    public function test_client_extension_must_match_the_detected_content(): void
    {
        $polyglot = "GIF89a\x01\x00\x01\x00<script>alert(document.cookie)</script>";
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        foreach (['html', 'htm', 'svg', 'js', 'gif'] as $extension) {
            $this->postJson(route('media-library.media.store'), ['image' => $this->realUpload('payload.'.$extension, $polyglot)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('image');
        }

        $this->assertNothingStored();
    }

    // ---------------------------------------------------------------- listing

    public function test_the_list_is_paginated_json_and_can_be_filtered(): void
    {
        $admin = $this->admin(superAdmin: true);
        $bowl = $this->storedMedia(['file_original_name' => 'bowl.png']);
        $this->storedMedia(['file_original_name' => 'vase.png']);
        $this->storedMedia(['file_original_name' => 'manual.pdf', 'file_type' => 'document', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf']);

        $this->actingAs($admin, 'admin')->getJson(route('media-library.media.index'))
            ->assertOk()
            ->assertJsonStructure(['current_page', 'last_page', 'per_page', 'total', 'data' => [['id', 'file_path', 'mime_type', 'file_type', 'url', 'readable_size', 'uploader_id']]])
            ->assertJsonPath('total', 3);

        $this->getJson(route('media-library.media.index', ['type' => 'image']))->assertOk()->assertJsonPath('total', 2);
        $this->getJson(route('media-library.media.index', ['search' => 'bowl']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $bowl->id);
        $this->getJson(route('media-library.media.index', ['per_page' => 2]))->assertOk()->assertJsonPath('per_page', 2)->assertJsonPath('last_page', 2);
        $this->getJson(route('media-library.media.index', ['per_page' => 500]))->assertOk()->assertJsonPath('per_page', 40);
        $this->getJson(route('media-library.media.index', ['type' => 'executable']))->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    // ---------------------------------------------------------------- deletion

    public function test_single_delete_removes_the_row_and_the_file(): void
    {
        $media = $this->storedMedia();
        $other = $this->storedMedia();

        $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->deleteJson(route('media-library.media.destroy', $media->id))
            ->assertOk();

        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->file_path);
        $this->assertModelExists($other);
        Storage::disk('public')->assertExists($other->file_path);

        $this->deleteJson(route('media-library.media.destroy', $media->id))->assertNotFound();
    }

    public function test_bulk_delete_removes_every_listed_row_and_file(): void
    {
        [$a, $b, $kept] = [$this->storedMedia(), $this->storedMedia(), $this->storedMedia()];

        $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        foreach ([$a, $b] as $media) {
            $this->assertModelMissing($media);
            Storage::disk('public')->assertMissing($media->file_path);
        }

        $this->assertModelExists($kept);
        Storage::disk('public')->assertExists($kept->file_path);
    }

    public function test_bulk_delete_with_an_unknown_or_invalid_id_deletes_nothing(): void
    {
        $media = $this->storedMedia();
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$media->id, 999999]])->assertUnprocessable()->assertJsonValidationErrors('ids.1');
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => ['abc']])->assertUnprocessable();

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->file_path);
    }

    /**
     * M1-F2, closed in M2-C: a media item a category uses (categories.image_media_id) used to be deletable,
     * leaving the category pointing at a missing file. It is now refused with 409, singly and in bulk, and
     * nothing is deleted. Details in MediaLibrarySafeDeletionTest and MediaDeletionConcurrencyMySqlTest.
     */
    public function test_media_in_use_cannot_be_deleted(): void
    {
        $single = $this->storedMedia();
        $bulk = $this->storedMedia();
        $free = $this->storedMedia();
        $first = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $second = Category::create(['name' => 'Glass', 'slug' => 'glass', 'is_active' => true]);
        $first->forceFill(['image_media_id' => $single->id])->save();
        $second->forceFill(['image_media_id' => $bulk->id])->save();
        $this->actingAs($this->admin(superAdmin: true), 'admin');

        $this->deleteJson(route('media-library.media.destroy', $single->id))->assertStatus(409)->assertJsonPath('in_use', [$single->id]);
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$free->id, $bulk->id]])->assertStatus(409)->assertJsonPath('in_use', [$bulk->id]);

        foreach ([[$first, $single], [$second, $bulk], [null, $free]] as [$category, $media]) {
            $this->assertModelExists($media);
            Storage::disk('public')->assertExists($media->file_path);
            $category && $this->assertSame($media->id, $category->fresh()->image_media_id);
        }
    }

    // ---------------------------------------------------------------- permissions

    /**
     * M1-F3, closed in M2-A: an active admin with no ability used to have full access to the library. Every
     * endpoint now refuses them and nothing changes.
     */
    public function test_an_active_admin_without_media_abilities_is_refused_everywhere(): void
    {
        $owner = $this->admin(superAdmin: true);
        $theirs = $this->storedMedia(['uploader_id' => $owner->id]);
        $alsoTheirs = $this->storedMedia(['uploader_id' => $owner->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('media-library.page'))->assertForbidden();
        $this->getJson(route('media-library.media.index'))->assertForbidden();
        $this->getJson(route('media-library.media.show', $theirs->id))->assertForbidden();
        $this->getJson(route('media-library.media.edit', $theirs->id))->assertForbidden();
        $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('mine.png')])->assertForbidden();
        $this->putJson(route('media-library.media.update', $theirs->id), ['file_original_name' => 'renamed.png'])->assertForbidden();
        $this->deleteJson(route('media-library.media.destroy', $theirs->id))->assertForbidden();
        $this->deleteJson(route('media-library.media.bulk-destroy'), ['ids' => [$alsoTheirs->id]])->assertForbidden();

        $this->assertSame($theirs->file_original_name, $theirs->fresh()->file_original_name);
        $this->assertModelExists($theirs);
        $this->assertModelExists($alsoTheirs);
        $this->assertSame(2, Media::count());
        $this->assertSame(0, RoleUser::query()->where('role_name', 'like', 'media%')->count(), 'No ability is granted automatically.');
    }

    // ---------------------------------------------------------------- helpers

    private function admin(bool $superAdmin = false, string $status = 'active'): Admin
    {
        return Admin::factory()->create(['super_admin' => $superAdmin, 'status' => $status]);
    }

    /** A media row with a real file on the faked public disk, created without going through the controller. */
    private function storedMedia(array $attributes = []): Media
    {
        $name = uniqid('', true).'.png';
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, 'file');

        return Media::create($attributes + [
            'file_name' => $name,
            'file_original_name' => $name,
            'file_path' => $path,
            'file_extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4,
            'file_type' => 'image',
            'disk' => 'public',
        ]);
    }

    /**
     * An upload whose MIME type is sniffed from its real content. UploadedFile::fake()->createWithContent()
     * reports a type derived from the NAME, which would hide exactly what these tests check.
     */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'm1');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
