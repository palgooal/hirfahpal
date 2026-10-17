@extends('layouts.storefront')

@section('title', $page->title.' | '.($siteSetting?->site_name ?: 'حِرفة'))
@section('meta_description', $page->excerpt ?: $page->title)

@section('content')
<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
        <div class="mx-auto max-w-[1216px] text-start">
            <p class="text-xs font-bold text-copper">معلومات حِرفة</p>
            <h1 class="mt-2 text-[30px] font-bold leading-9 text-ink sm:text-4xl">{{ $page->title }}</h1>
            @if ($page->excerpt)<p class="mt-2 text-sm leading-6 text-muted sm:text-base">{{ $page->excerpt }}</p>@endif
        </div>
    </section>
    <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
        <article class="mx-auto max-w-[900px] whitespace-pre-line rounded-[20px] border border-line bg-surface p-6 text-sm leading-8 text-ink shadow-card sm:p-8 sm:text-base">{{ $page->body }}</article>
    </section>
</main>
@endsection
