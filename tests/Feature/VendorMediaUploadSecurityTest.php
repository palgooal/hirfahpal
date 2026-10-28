<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Database\Seeders\AdminMediaTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * VENDOR-MEDIA-02A: uploads to the vendor's media library (vendor.dashboard.products.media.store, files[]) pass the
 * same content checks as the admin library (EnsureSafeMediaUpload, through EnsureSafeVendorMediaUpload): the type
 * sniffed from the bytes is an allowed image type, the client extension belongs to that type, the image header agrees
 * with non-zero dimensions within the pixel cap, and the image decodes. One refused file refuses the whole request,
 * before anything is stored. The files are real temporary files (not UploadedFile::fake(), whose MIME type follows the
 * name), so the content is what is checked. Storage path, response shape and ownership are unchanged.
 */
class VendorMediaUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    /** @var list<string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vendor = Vendor::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- accepted

    public function test_real_jpeg_png_gif_and_webp_images_are_stored_in_the_vendors_folder(): void
    {
        foreach (['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'] as $extension => $mime) {
            $response = $this->upload([$this->file($this->image($mime), "photo.{$extension}", $mime)])->assertCreated();

            $item = $response->json('images.0');
            $this->assertSame(['id', 'path', 'alt_text', 'url'], array_keys($item), 'The response contract is unchanged.');
            $this->assertMatchesRegularExpression('#^vendors/media/'.$this->vendor->id.'/[0-9a-f-]{36}\.'.($extension === 'jpeg' ? 'jpe?g' : $extension).'$#', $item['path']);
            Storage::disk('public')->assertExists($item['path']);
            $this->assertSame($this->vendor->id, Media::query()->findOrFail($item['id'])->uploader_id);
        }

        $this->assertSame(5, Media::query()->count());
    }

    public function test_several_valid_images_in_one_request_are_all_stored(): void
    {
        $this->upload([
            $this->file($this->image('image/png'), 'a.png', 'image/png'),
            $this->file($this->image('image/jpeg'), 'b.jpg', 'image/jpeg'),
        ])->assertCreated()->assertJsonCount(2, 'images');
    }

    public function test_an_uploaded_image_can_still_be_attached_to_the_vendors_product(): void
    {
        $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $path = $this->upload([$this->file($this->image('image/png'), 'jug.png', 'image/png')])->assertCreated()->json('images.0.path');

        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path])
            ->assertCreated()->assertJsonPath('image.path', $path)->assertJsonPath('image.is_primary', true);
    }

    // ---------------------------------------------------------------- refused

    public function test_disguised_malformed_and_mismatched_files_are_refused_and_nothing_is_stored(): void
    {
        $png = $this->image('image/png');
        $cases = [
            'HTML named .png' => ['<html><script>alert(1)</script></html>', 'evil.png', 'image/png'],
            'PHP named .jpg' => ['<?php echo "x"; ?>', 'evil.jpg', 'image/jpeg'],
            'a PNG named .jpg' => [$png, 'photo.jpg', 'image/jpeg'],
            'a PNG named .html' => [$png, 'photo.html', 'text/html'],
            'a PNG named .php' => [$png, 'photo.php', 'image/png'],
            'a PNG without extension' => [$png, 'photo', 'image/png'],
            'an SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'logo.svg', 'image/svg+xml'],
            'a GIF header followed by script' => ['GIF89a'.pack('vv', 10, 10)."\x00\x00\x00<script>alert(1)</script>", 'trap.gif', 'image/gif'],
            'a truncated PNG' => [substr($png, 0, 60), 'broken.png', 'image/png'],
            'an empty PNG name' => ['', 'empty.png', 'image/png'],
        ];

        foreach ($cases as $label => [$bytes, $name, $mime]) {
            $this->upload([$this->file($bytes, $name, $mime)])->assertUnprocessable()->assertJsonValidationErrors('files.0');
            $this->assertSame(0, Media::query()->count(), "{$label}: no media row.");
            $this->assertSame([], Storage::disk('public')->allFiles(), "{$label}: no file.");
        }
    }

    public function test_one_bad_file_refuses_the_whole_request(): void
    {
        $this->upload([
            $this->file($this->image('image/png'), 'good.png', 'image/png'),
            $this->file('<script>alert(1)</script>', 'bad.png', 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('files.1')->assertJsonMissingValidationErrors('files.0');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_image_over_the_pixel_cap_is_refused_with_its_own_message(): void
    {
        config(['media-library.max_image_pixels' => 100]);

        $this->upload([$this->file($this->image('image/png', 20, 20), 'big.png', 'image/png')])
            ->assertUnprocessable()->assertJsonValidationErrors(['files.0' => 'The image dimensions are too large.']);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_the_10_mb_limit_still_applies(): void
    {
        $this->upload([UploadedFile::fake()->image('huge.png', 10, 10)->size(10241)])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->assertSame(0, Media::query()->count());
    }

    public function test_the_refusal_message_is_translated_and_names_no_server_detail(): void
    {
        $response = $this->upload([$this->file('<script>alert(1)</script>', 'evil.png', 'image/png')])->assertUnprocessable();

        $this->assertSame(['files.0' => ['The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.']], $response->json('errors'));
        $this->assertStringNotContainsString(sys_get_temp_dir(), $response->getContent());
    }

    public function test_the_refusal_messages_are_served_in_arabic_from_the_seeded_admin_media_keys(): void
    {
        // The vendor JSON routes carry no setLocale: the message follows the application locale.
        $this->seed(AdminMediaTranslationSeeder::class);
        config(['app.locale' => 'ar']);

        $this->upload([$this->file('<script>alert(1)</script>', 'evil.png', 'image/png')])
            ->assertUnprocessable()->assertJsonValidationErrors(['files.0' => 'يجب أن يكون الملف صورة JPG أو PNG أو GIF أو WebP، وأن يطابق امتداده محتواه.']);

        config(['media-library.max_image_pixels' => 100]);
        $this->upload([$this->file($this->image('image/png', 20, 20), 'big.png', 'image/png')])
            ->assertUnprocessable()->assertJsonValidationErrors(['files.0' => 'أبعاد الصورة كبيرة جدًا.']);
    }

    // ---------------------------------------------------------------- access and isolation

    public function test_guests_and_unapproved_vendors_cannot_upload(): void
    {
        $file = fn () => $this->file($this->image('image/png'), 'a.png', 'image/png');

        $this->post(route('vendor.dashboard.products.media.store'), ['files' => [$file()]], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->actingAs(Vendor::factory()->withProfile('pending')->create(), 'vendor')
            ->post(route('vendor.dashboard.products.media.store'), ['files' => [$file()]], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_uploads_stay_in_the_uploading_vendors_own_library(): void
    {
        $other = Vendor::factory()->approved()->create();
        $path = $this->upload([$this->file($this->image('image/png'), 'mine.png', 'image/png')])->assertCreated()->json('images.0.path');

        $this->assertStringStartsWith("vendors/media/{$this->vendor->id}/", $path);
        $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.products.media.library'))->assertOk()->assertJsonCount(0, 'images');
        $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.media.library'))->assertOk()->assertJsonPath('images.0.path', $path);
        // The other vendor cannot delete it, nor attach it to their product.
        $media = Media::query()->where('file_path', $path)->sole();
        $this->actingAs($other, 'vendor')->deleteJson(route('vendor.dashboard.products.media.destroy', $media))->assertNotFound();
        $theirs = Product::create(['vendor_id' => $other->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $this->actingAs($other, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $theirs), ['path' => $path])->assertUnprocessable();
        $this->assertSame(0, ProductImage::query()->count());
        Storage::disk('public')->assertExists($path);
    }

    // ---------------------------------------------------------------- helpers

    private function upload(array $files): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->post(route('vendor.dashboard.products.media.store'), ['files' => $files], ['Accept' => 'application/json']);
    }

    /** A real temporary file, so its bytes are what the checks see. */
    private function file(string $bytes, string $name, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vm02a');
        file_put_contents($path, $bytes);
        $this->temporary[] = $path;

        return new UploadedFile($path, $name, $clientMime, null, true);
    }

    private function image(string $mime, int $width = 8, int $height = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 61, 71, 43));
        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($image),
            'image/gif' => imagegif($image),
            'image/webp' => imagewebp($image),
            default => imagepng($image),
        };
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
