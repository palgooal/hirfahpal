<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\RoleUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

/**
 * AD-UI-CAT-M2-A (finding M1-F1): App\Http\Middleware\EnsureSafeMediaUpload binds the stored extension to the
 * real image content. Uploads use real temporary files whose type is sniffed from their bytes; the client
 * Content-Type is never trusted.
 */
class MediaLibraryUploadSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID = 'The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.';

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_each_supported_type_is_stored_under_an_extension_of_its_real_type(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $cases = [
            ['photo.jpg', 'jpeg', 'image/jpeg', 'jpg'],
            ['photo.jpeg', 'jpeg', 'image/jpeg', 'jpeg'],
            ['PHOTO.PNG', 'png', 'image/png', 'png'],
            ['anim.gif', 'gif', 'image/gif', 'gif'],
            ['modern.webp', 'webp', 'image/webp', 'webp'],
        ];

        foreach ($cases as [$name, $format, $mime, $extension]) {
            $response = $this->postJson(route('media-library.media.store'), ['image' => $this->upload($name, $this->image($format))])->assertCreated();

            $media = Media::findOrFail($response->json('id'));
            $this->assertSame($mime, $media->mime_type, $name);
            $this->assertSame($extension, $media->file_extension, $name);
            $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[^/]+\.'.$extension.'$#', $media->file_path, $name);
            Storage::disk('public')->assertExists($media->file_path);
        }

        $this->assertSame(5, Media::count());
    }

    public function test_polyglots_with_active_extensions_are_refused(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $payloads = [
            "GIF89a\x01\x00\x01\x00<script>alert(document.cookie)</script>",
            $this->image('png').'<script>alert(1)</script>',
            $this->image('jpeg').'<?php system($_GET["c"]); ?>',
        ];

        foreach ($payloads as $payload) {
            foreach (['html', 'htm', 'xhtml', 'svg', 'js', 'mjs', 'php', 'phtml', 'phar', 'shtml', 'xml', 'txt', 'exe', ''] as $extension) {
                $name = $extension === '' ? 'payload' : 'payload.'.$extension;

                $this->postJson(route('media-library.media.store'), ['image' => $this->upload($name, $payload)])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('image');
            }
        }

        $this->assertNothingStored();
    }

    public function test_a_valid_image_under_another_image_extension_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin');

        foreach (['png' => ['jpg', 'gif', 'webp'], 'jpeg' => ['png', 'gif'], 'gif' => ['png', 'webp'], 'webp' => ['jpg', 'png']] as $format => $wrong) {
            foreach ($wrong as $extension) {
                $this->postJson(route('media-library.media.store'), ['image' => $this->upload('picture.'.$extension, $this->image($format))])
                    ->assertUnprocessable()
                    ->assertJsonPath('errors.image.0', self::INVALID);
            }
        }

        $this->assertNothingStored();
    }

    public function test_an_image_header_without_a_decodable_image_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $cases = [
            'header-only.gif' => "GIF89a\x01\x00\x01\x00<script>alert(1)</script>",
            'truncated.png' => substr($this->image('png'), 0, 40),
            'truncated.jpg' => substr($this->image('jpeg'), 0, 200),
            'garbage.webp' => 'RIFF'.pack('V', 30).'WEBPVP8 '.str_repeat("\0", 22),
        ];

        foreach ($cases as $name => $content) {
            $this->postJson(route('media-library.media.store'), ['image' => $this->upload($name, $content)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('image');
        }

        $this->assertNothingStored();
    }

    public function test_the_client_content_type_is_not_trusted(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('note.png', 'just text', 'image/png')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('page.png', '<html><script>alert(1)</script></html>', 'image/png')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertNothingStored();

        // A genuine image announced with a wrong client type is judged on its bytes and its extension.
        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('real.png', $this->image('png'), 'text/html')])->assertCreated();
        $this->assertSame('image/png', Media::sole()->mime_type);
    }

    public function test_svg_and_php_stay_refused(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>';

        foreach (['logo.svg' => $svg, 'logo.png' => $svg, 'shell.php' => $this->image('gif'), 'shell.jpg' => '<?php echo 1; ?>'] as $name => $content) {
            $this->postJson(route('media-library.media.store'), ['image' => $this->upload($name, $content)])->assertUnprocessable();
        }

        $this->assertNothingStored();
    }

    public function test_a_double_extension_never_reaches_the_stored_name(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->postJson(route('media-library.media.store'), ['image' => $this->upload('shell.php.png', $this->image('png'))])->assertCreated();

        $media = Media::findOrFail($response->json('id'));
        $this->assertStringEndsWith('.png', $media->file_path);
        $this->assertStringNotContainsString('php', $media->file_path);
        $this->assertSame('shell.php.png', $media->file_original_name, 'The client name is kept as metadata only.');
    }

    public function test_one_unsafe_file_refuses_the_whole_multiple_upload(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('media-library.media.store'), ['files' => [
            $this->upload('good.png', $this->image('png')),
            $this->upload('evil.html', "GIF89a\x01\x00\x01\x00<script>alert(1)</script>"),
            $this->upload('also-good.jpg', $this->image('jpeg')),
        ]])->assertUnprocessable()->assertJsonValidationErrors('files.1')->assertJsonMissingValidationErrors(['files.0', 'files.2']);

        $this->assertNothingStored();
    }

    public function test_the_pixel_ceiling_is_checked_before_decoding(): void
    {
        $this->assertSame(25_000_000, config('media-library.max_image_pixels'));
        config(['media-library.max_image_pixels' => 150]);
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('wide.png', $this->image('png', 20, 10))])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'The image dimensions are too large.');
        $this->assertNothingStored();

        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('small.png', $this->image('png', 12, 12))])->assertCreated();
    }

    public function test_the_package_size_limit_still_answers_oversized_files(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('media-library.media.store'), ['image' => UploadedFile::fake()->image('big.jpg')->size(10241)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertNothingStored();
    }

    public function test_an_admin_who_may_not_upload_gets_403_not_a_validation_answer(): void
    {
        $viewer = Admin::factory()->create();
        RoleUser::create(['role_name' => 'media.view', 'user_id' => $viewer->id, 'ability' => 'allow']);

        $this->actingAs($viewer, 'admin')
            ->postJson(route('media-library.media.store'), ['image' => $this->upload('evil.html', "GIF89a\x01\x00\x01\x00<script>alert(1)</script>")])
            ->assertForbidden();

        $this->assertNothingStored();
    }

    public function test_translated_messages_are_not_auto_created(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('media-library.media.store'), ['image' => $this->upload('evil.html', 'text')])->assertUnprocessable();

        $this->assertDatabaseMissing('translation_values', ['key' => 'dashboard.Media_invalid_image']);
        $this->assertTrue((bool) config('palgoals-locale.auto_create'), 'The setting is restored after the message is resolved.');
    }

    private function admin(): Admin
    {
        return Admin::factory()->create(['super_admin' => true]);
    }

    /** Real image bytes produced by GD. */
    private function image(string $format, int $width = 8, int $height = 8): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 140, 60));

        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
        };
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /** An upload whose type can only be learnt from its content (the client type is whatever is given). */
    private function upload(string $name, string $content, ?string $clientType = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'm2');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, $clientType, null, true);
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
