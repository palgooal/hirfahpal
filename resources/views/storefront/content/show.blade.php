@extends('layouts.storefront')

{{-- PAGES-02: the page in the requested language, or in Arabic when it has none. PAGES-03A: the whole header and the
     body carry the content's own lang/dir, so an Arabic page shown in English reads as one right-to-left block; the
     small heading appears on Arabic pages only. The site name follows the page language. The body is the HTML cleaned
     by PageContentSanitizer (StorefrontContentPage::contentFor()), the only unescaped output here. A dashboard preview
     ($preview) is never indexed.

     PAGES-05E: a page is indexed only in a language where it is public (published, the language enabled, the
     translation complete): then a self-referencing canonical in the URL's language, hreflang for every public language
     (StorefrontContentPage::publicAlternates(), the same for each of them, so they are reciprocal) with x-default on
     the source language's version when it is public, and Open Graph tags from the translation. A fallback (the source
     content shown for a missing or incomplete translation) keeps its metadata for visitors but gets "noindex, follow"
     and no canonical, hreflang or Open Graph. --}}
@php($pageLocale = app()->getLocale())
@php($siteName = \App\Models\Setting::siteNameFor($siteSetting ?? null, $pageLocale))
@php($indexable = ! ($preview ?? false) && ! $content['fallback'] && $page->hasPublicTranslation($pageLocale))
@section('title', $content['meta_title'].' | '.$siteName)
@section('meta_description', $content['meta_description'])
@if ($preview ?? false)
    @section('robots', 'noindex, nofollow')
@elseif (! $indexable)
    @section('robots', 'noindex, follow')
@endif
@if ($indexable)
    @php($alternates = $page->publicAlternates())
    @php($canonical = $alternates[$pageLocale])
    @php($ogLocale = \App\Models\StorefrontContentPage::openGraphLocale($pageLocale))
    @push('head')
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
    @endforeach
    @if (isset($alternates[\App\Models\StorefrontContentPage::SOURCE_LOCALE]))
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Models\StorefrontContentPage::SOURCE_LOCALE] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $content['meta_title'] }}">
    <meta property="og:description" content="{{ $content['meta_description'] }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($ogLocale)
    <meta property="og:locale" content="{{ $ogLocale }}">
    @endif
    @endpush
@endif

@section('content')
<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
        <div class="mx-auto max-w-[1216px] text-start" lang="{{ $content['locale'] }}" dir="{{ $content['dir'] }}" data-content-header>
            @if ($pageLocale === 'ar' && $content['locale'] === 'ar')
                <p class="mb-2 text-xs font-bold text-copper">{{ \App\Models\StorefrontContentPage::EYEBROW_AR }}</p>
            @endif
            <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl">{{ $content['title'] }}</h1>
            @if ($content['excerpt'])<p class="mt-2 text-sm leading-6 text-muted sm:text-base">{{ $content['excerpt'] }}</p>@endif
        </div>
    </section>
    <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
        <article class="hirfah-prose mx-auto max-w-[900px] rounded-[20px] border border-line bg-surface p-6 text-sm leading-8 text-ink shadow-card sm:p-8 sm:text-base" lang="{{ $content['locale'] }}" dir="{{ $content['dir'] }}" data-content-body>{!! $content['body_html'] !!}</article>
    </section>
</main>
@endsection
