<?php

namespace App\Support\SiteAvailability;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SITE-AVAILABILITY (§5.3, DEC-SA-02): the response for a request the gate blocks. No Blade, no view composer and no
 * database access, so it works with an empty, unmigrated or unreachable database.
 *
 * Status: 200 for a safe request (GET, HEAD) during coming_soon, 503 otherwise. Headers: no-store caching and
 * noindex on every blocked response; Retry-After during maintenance only. JSON requests get a structured body.
 *
 * HTML: the converted original design (Phase 2A) with its CSS inlined, and a logged built-in fallback page if a
 * template or the CSS is missing. Status and headers are the same either way.
 */
class SiteAvailabilityResponse
{
    public static function make(SiteAvailabilityState $state, Request $request): Response|JsonResponse
    {
        $status = $state->statusFor($request->isMethodSafe());
        $message = (string) config('site-availability.messages.'.$state->value, '');

        $response = $request->expectsJson()
            ? new JsonResponse(array_filter([
                'state' => $state->value,
                'message' => $message,
                'retry_after' => $state === SiteAvailabilityState::Maintenance ? self::retryAfter() : null,
            ], fn ($value) => $value !== null), $status)
            : new Response(self::html($state), $status, ['Content-Type' => 'text/html; charset=UTF-8']);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        if ($state === SiteAvailabilityState::Maintenance) {
            $response->headers->set('Retry-After', (string) self::retryAfter());
        }

        return $response;
    }

    private static function retryAfter(): int
    {
        return max(0, (int) config('site-availability.retry_after', 3600));
    }

    /**
     * Phase 2A: the converted original design (resources/site-availability/{coming-soon,maintenance}.html), read from
     * disk; {{STYLESHEET}} becomes an inline <style> with the tracked site-availability.min.css (no Vite manifest, no
     * public/build, no public/hot, no extra request) and {{LOGO_URL}} the logo URL. No Blade and no database. If the
     * template or the CSS cannot be read, the failure is logged and the built-in page below is served instead, so a
     * visitor never gets an unstyled page.
     */
    private static function html(SiteAvailabilityState $state): string
    {
        $template = self::readFile(resource_path('site-availability/'.($state === SiteAvailabilityState::Maintenance ? 'maintenance' : 'coming-soon').'.html'));
        $css = self::readFile(resource_path('site-availability/site-availability.min.css'));

        if ($template === null || $css === null || stripos($css, '</style') !== false) {
            self::logFallback($state, $template === null ? 'template' : 'stylesheet');

            return self::fallbackHtml($state);
        }

        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_replace(
            ['{{STYLESHEET}}', '{{LOGO_URL}}'],
            ['<style>'.$css.'</style>', $escape(asset('assets/site-availability/hirfah-logo.jpeg'))],
            $template,
        );
    }

    /** The file's non-empty content, or null if it is missing, unreadable or empty. */
    private static function readFile(string $path): ?string
    {
        $content = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;

        return is_string($content) && trim($content) !== '' ? $content : null;
    }

    private static function logFallback(SiteAvailabilityState $state, string $missing): void
    {
        try {
            Log::error('Site availability page: the '.$missing.' could not be read; serving the built-in fallback page.', [
                'state' => $state->value,
            ]);
        } catch (Throwable) {
            // Logging must never prevent the availability response.
        }
    }

    /** Built-in page used only if a template or the CSS is missing: static Arabic text, inline styles, no external resource, no form. */
    private static function fallbackHtml(SiteAvailabilityState $state): string
    {
        [$title, $heading, $text] = $state === SiteAvailabilityState::Maintenance
            ? ['الموقع تحت الصيانة | حِرفة', 'الموقع تحت الصيانة', 'نجري حاليًا بعض أعمال الصيانة والتحسين على متجر حِرفة. سنعود إليكم قريبًا. شكرًا لتفهمكم.']
            : ['متجر حِرفة | قريبًا', 'متجر حِرفة', 'قريبًا بين أيديكم'];

        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<!doctype html>'
            .'<html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            .'<meta name="robots" content="noindex, nofollow">'
            .'<title>'.$escape($title).'</title></head>'
            .'<body data-site-availability="'.$escape($state->value).'" style="margin:0;font-family:sans-serif;background:#f7f0e4;color:#263014;text-align:center">'
            .'<main style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem">'
            .'<h1>'.$escape($heading).'</h1><p>'.$escape($text).'</p>'
            .'</main></body></html>';
    }
}
