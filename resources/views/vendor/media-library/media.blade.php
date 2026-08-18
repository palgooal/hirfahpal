{{--
    Layout is resolved dynamically so this page can render as part of a
    host dashboard instead of as a standalone screen. Set
    config('media-library.layout') to any component name Laravel can
    resolve via <x-{name}> (it must accept a default slot); leave it null
    to use the package's own self-contained layout. See
    config/media-library.php for details — nothing in this file assumes
    any particular host application or dashboard.
--}}
@php
    $mediaLibraryLayout = config('media-library.layout') ?: 'media-library::layouts.minimal';
    $mediaLibraryBreadcrumb = config('media-library.breadcrumb');
@endphp
<x-dynamic-component :component="$mediaLibraryLayout">

    <div class="px-4 py-6">

        @if (! empty($mediaLibraryBreadcrumb))
            {{-- Breadcrumb: only rendered when config('media-library.breadcrumb') is set,
                 so the page can visually sit inside a dashboard's navigation instead of
                 looking like an isolated, unrelated screen. --}}
            <nav aria-label="Breadcrumb" class="mb-4">
                <ol class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    @foreach ($mediaLibraryBreadcrumb as $crumb)
                        <li class="flex items-center gap-1.5">
                            @if (! $loop->first)
                                <span aria-hidden="true">/</span>
                            @endif
                            @if (! empty($crumb['url']) && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $crumb['label'] }}</a>
                            @else
                                <span class="text-gray-700 dark:text-gray-200 font-medium" @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- Header --}}
        <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مكتبة الوسائط</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    إدارة الصور والملفات المرفوعة، مع فلترة وبحث وتعديل سريع.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button id="btn-upload"
                    class="inline-flex items-center gap-2 justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    رفع ملفات
                </button>
                <input id="file-input" type="file" class="hidden" multiple accept="image/*">
            </div>
        </header>

        {{-- Filter & search bar --}}
        <section class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <button data-filter-type="" class="filter-btn rounded-full border px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">الكل</button>
                <button data-filter-type="image" class="filter-btn rounded-full border px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">صور</button>
                <button data-filter-type="video" class="filter-btn rounded-full border px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">فيديو</button>
                <button data-filter-type="document" class="filter-btn rounded-full border px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">مستندات</button>
                <button data-filter-type="other" class="filter-btn rounded-full border px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">أخرى</button>
            </div>
            <input id="search-input" type="text" placeholder="بحث بالاسم أو العنوان..."
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 sm:w-64">
        </section>

        {{-- Multi-select bar (appears when items are selected) --}}
        <div id="selection-bar" class="hidden mb-3 flex items-center justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm dark:border-indigo-800 dark:bg-indigo-950/40">
            <span class="font-medium text-indigo-700 dark:text-indigo-300">
                تم تحديد <span id="selection-count">0</span> عنصر
            </span>
            <div class="flex items-center gap-3">
                <button type="button" id="btn-clear-selection"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    إلغاء التحديد
                </button>
                <button type="button" id="btn-bulk-delete"
                    class="rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white hover:bg-red-600">
                    حذف المحدد
                </button>
            </div>
        </div>

        {{-- Media section (full width) --}}
        <section>
            {{-- Drag & Drop zone --}}
            <div id="dropzone"
                class="mb-4 flex min-h-[120px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center text-gray-500 transition hover:border-indigo-400 hover:bg-indigo-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-indigo-400 dark:hover:bg-gray-900/60">
                <svg xmlns="http://www.w3.org/2000/svg" class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <p class="text-sm font-medium">اسحب الملفات هنا أو اضغط على زر "رفع ملفات"</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">يدعم الصور حتى 10MB لكل ملف (JPEG, PNG, GIF, WEBP, SVG)</p>
            </div>

            {{-- Media grid --}}
            <div id="media-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7"></div>

            {{-- States --}}
            <div id="media-loading" class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">جاري التحميل...</div>
            <div id="media-empty" class="mt-6 hidden text-center text-sm text-gray-400 dark:text-gray-500">لا توجد وسائط لعرضها حاليًا.</div>

            {{-- Load more --}}
            <div class="mt-6 flex justify-center">
                <button id="btn-load-more"
                    class="hidden rounded-full border border-gray-300 px-5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    تحميل المزيد
                </button>
            </div>
        </section>
    </div>

    {{-- ===== MEDIA DETAILS MODAL ===== --}}
    <div id="media-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4 pt-16" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div id="modal-backdrop" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

        {{-- Modal panel --}}
        <div class="relative z-10 w-full max-w-3xl rounded-2xl bg-white shadow-2xl dark:bg-gray-900 flex flex-col" style="max-height: min(90vh, calc(100vh - 80px))">

            {{-- Modal header --}}
            <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white">تفاصيل الملف</h2>
                <button id="btn-modal-close" type="button"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="flex min-h-0 flex-1 flex-col md:flex-row overflow-y-auto">

                {{-- Left: Image preview --}}
                <div class="flex flex-col items-center justify-center gap-3 border-b border-gray-100 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950 md:w-64 md:border-b-0 md:border-e md:shrink-0">
                    <div class="flex items-center justify-center w-full rounded-xl overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700" style="min-height:180px">
                        <img id="details-preview" src="" alt="" class="max-h-52 max-w-full object-contain">
                    </div>

                    {{-- File info --}}
                    <div class="w-full space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-600 dark:text-gray-300">النوع</span>
                            <span id="details-type" class="text-end"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-600 dark:text-gray-300">الحجم</span>
                            <span id="details-size"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-600 dark:text-gray-300">الأبعاد</span>
                            <span id="details-dimensions"></span>
                        </div>
                    </div>

                    {{-- Copy URL --}}
                    <button type="button" id="btn-copy-url"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 hover:bg-indigo-50 hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        نسخ رابط الملف
                    </button>
                </div>

                {{-- Right: Edit form --}}
                <div class="flex-1 overflow-y-auto p-5">
                    <form id="details-form" class="space-y-4">

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">الاسم الأصلي</label>
                            <input id="details-original-name" type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">العنوان (Title)</label>
                            <input id="details-title" type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">النص البديل (Alt)</label>
                            <input id="details-alt" type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">يُستخدم لتحسين إمكانية الوصول وتحسين محركات البحث.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">التسمية التوضيحية (Caption)</label>
                            <textarea id="details-caption" rows="2"
                                class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">الوصف</label>
                            <textarea id="details-description" rows="3"
                                class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                        </div>

                        {{-- المسار الكامل --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2">
                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-0.5">المسار</p>
                            <p id="details-path" class="text-[11px] text-gray-600 dark:text-gray-300 break-all"></p>
                        </div>

                    </form>
                </div>
            </div>

            {{-- Modal footer --}}
            <div class="flex shrink-0 items-center justify-between border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                <button type="button" id="btn-delete"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    حذف الملف
                </button>
                <button type="submit" form="details-form"
                    class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                    حفظ التعديلات
                </button>
            </div>
        </div>
    </div>

    <script>
        window.MEDIA_CONFIG = {
            baseUrl: @json(route('media-library.media.index')),
            csrfToken: @json(csrf_token())
        };
    </script>
    <script src="{{ asset('vendor/media-library/js/media-library.js') }}" defer></script>

</x-dynamic-component>
