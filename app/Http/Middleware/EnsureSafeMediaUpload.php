<?php

namespace App\Http\Middleware;

use Closure;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Palgoal\MediaLibrary\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * AD-UI-CAT-M2-A (finding M1-F1): the media library package stores an upload under the CLIENT's extension and
 * its `mimes` rule only checks the extension guessed from the content, so a GIF-headed file named .html was
 * written to the public disk as <id>.html. This middleware runs on the package routes (config
 * media-library.middleware), before the package controller, and refuses any uploaded file unless:
 *
 *  - its real type, sniffed from the bytes (never the client Content-Type), is an allowed image type;
 *  - its client extension (the one the package will store) is an extension of THAT type;
 *  - the image header agrees with the sniffed type, with non-zero dimensions within the pixel cap;
 *  - GD can actually decode it (a header followed by script or junk is not an image).
 *
 * Files the package itself will refuse (failed upload, over the size limit) are left to its validation, and a
 * request the policy will refuse anyway is passed through untouched so the answer stays 403, not 422.
 */
class EnsureSafeMediaUpload
{
    /** Allowed image types and the only extensions each may be stored under. */
    private const EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $files = array_filter(Arr::dot($request->allFiles()), fn ($file) => $file instanceof UploadedFile);

        if ($files === [] || Gate::denies('create', Media::class)) {
            return $next($request);
        }

        $errors = [];

        foreach ($files as $field => $file) {
            if ($message = $this->rejection($file)) {
                $errors[$field] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }

    private function rejection(UploadedFile $file): ?string
    {
        $maxBytes = (int) config('media-library.max_upload_size_kb', 10240) * 1024;

        if (! $file->isValid() || $file->getSize() > $maxBytes) {
            return null; // The package's own `file` / `max` rules answer these.
        }

        $path = $file->getRealPath();
        $mime = $path ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $allowed = array_intersect_key(self::EXTENSIONS, array_flip((array) config('media-library.allowed_mimetypes', [])));
        $extension = strtolower($file->getClientOriginalExtension());

        if (! is_string($mime) || ! isset($allowed[$mime]) || ! in_array($extension, $allowed[$mime], true)) {
            return $this->message('dashboard.Media_invalid_image', 'The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.');
        }

        $size = @getimagesize($path);

        if ($size === false || ($size['mime'] ?? null) !== $mime || $size[0] < 1 || $size[1] < 1) {
            return $this->message('dashboard.Media_invalid_image', 'The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.');
        }

        // Checked before decoding: GD holds every pixel in memory.
        if ((int) config('media-library.max_image_pixels', 25_000_000) < $size[0] * $size[1]) {
            return $this->message('dashboard.Media_image_too_large', 'The image dimensions are too large.');
        }

        if (! $this->decodes($path)) {
            return $this->message('dashboard.Media_invalid_image', 'The file must be a JPG, PNG, GIF or WebP image, and its extension must match its content.');
        }

        return null;
    }

    private function decodes(string $path): bool
    {
        // Some production PHP installations do not load GD. The MIME and
        // getimagesize checks performed before this method still validate the
        // uploaded image; do not reject every valid upload solely because the
        // optional decoder is unavailable. Where GD exists, keep the stronger
        // full-decode check for truncated/polyglot images.
        if (! function_exists('imagecreatefromstring')) {
            return true;
        }

        try {
            $image = @imagecreatefromstring((string) file_get_contents($path));
        } catch (Throwable) {
            return false;
        }

        if ($image === false) {
            return false;
        }

        imagedestroy($image);

        return true;
    }

    /** t() without auto-creating the key, so an Arabic row is never created with English text. */
    private function message(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
