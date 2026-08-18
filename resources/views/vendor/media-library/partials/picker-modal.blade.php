{{-- media-library::partials.picker-modal
     Include ONCE per layout (e.g. at the end of your main <body>):
       @include('media-library::partials.picker-modal')
     Together with <x-media-library::picker> triggers and media-picker.js. --}}

@once
    {{-- الخلفية --}}
    <div id="media-picker-backdrop" class="fixed inset-0 z-[9998] bg-black/40 hidden"></div>

    {{-- المودال --}}
    <div id="media-picker-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center px-4">
        <div
            class="relative w-full max-w-5xl rounded-2xl bg-white dark:bg-gray-950 shadow-2xl border border-gray-200 dark:border-gray-800 max-h-[80vh] flex flex-col overflow-hidden">

            {{-- الهيدر --}}
            <header class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    اختيار وسائط من المكتبة
                </h2>

                <div class="flex items-center gap-2">
                    {{-- زر رفع داخل الـ popup --}}
                    <button type="button" id="media-picker-upload-btn"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                        <span>رفع صورة جديدة</span>
                    </button>

                    {{-- input مخفي للملفات --}}
                    <input type="file" id="media-picker-file-input" class="hidden" accept="image/*" multiple>

                    <button type="button" id="media-picker-close"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                        aria-label="إغلاق">
                        ✕
                    </button>
                </div>
            </header>

            {{-- الفلاتر + البحث --}}
            <section class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                    {{-- الفلاتر --}}
                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                        <button type="button" data-type=""
                            class="media-picker-filter-btn rounded-full border border-gray-300 dark:border-gray-700 px-3 py-1 font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            الكل
                        </button>
                        <button type="button" data-type="image"
                            class="media-picker-filter-btn rounded-full border border-gray-300 dark:border-gray-700 px-3 py-1 font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            صور
                        </button>
                        <button type="button" data-type="video"
                            class="media-picker-filter-btn rounded-full border border-gray-300 dark:border-gray-700 px-3 py-1 font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            فيديو
                        </button>
                        <button type="button" data-type="document"
                            class="media-picker-filter-btn rounded-full border border-gray-300 dark:border-gray-700 px-3 py-1 font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            مستندات
                        </button>
                        <button type="button" data-type="other"
                            class="media-picker-filter-btn rounded-full border border-gray-300 dark:border-gray-700 px-3 py-1 font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            أخرى
                        </button>
                    </div>

                    {{-- البحث --}}
                    <div class="w-full sm:w-64">
                        <input id="media-picker-search" type="text" placeholder="بحث بالاسم أو العنوان..."
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-800
                                   focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500
                                   dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    </div>
                </div>
            </section>

            {{-- المحتوى (Drag & Drop + الصور) --}}
            <main class="flex-1 overflow-y-auto px-5 py-4 space-y-3">

                {{-- منطقة السحب والإفلات داخل البوب-أب --}}
                <div id="media-picker-dropzone"
                    class="flex min-h-[100px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center text-gray-500 transition
                           hover:border-indigo-400 hover:bg-indigo-50
                           dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-indigo-400 dark:hover:bg-gray-900/60">
                    <p class="text-xs font-medium">
                        اسحب الملفات هنا أو اضغط على زر "رفع صورة جديدة" في الأعلى
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        يدعم الصور حتى 10MB للصورة الواحدة (JPEG, PNG, WEBP, SVG...)
                    </p>
                </div>

                {{-- Grid الوسائط --}}
                <div id="media-picker-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
                    {{-- يتم تعبئتها عبر JS --}}
                </div>

                <div id="media-picker-loading" class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400 hidden">
                    جاري التحميل...
                </div>

                <div id="media-picker-empty" class="mt-2 text-center text-xs text-gray-400 dark:text-gray-500 hidden">
                    لا توجد وسائط مطابقة حاليًا.
                </div>
                {{-- زر تحميل المزيد --}}
                <button type="button" id="media-picker-load-more"
                    class="mt-4 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700
                   hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 hidden">
                    تحميل المزيد من الوسائط
                </button>
            </main>

            {{-- الفوتر --}}
            <footer
                class="px-5 py-3 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between gap-3 text-[11px]">
                <div class="text-gray-500 dark:text-gray-400">
                    العناصر المحددة:
                    <span id="media-picker-selection-count">0</span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="media-picker-clear"
                        class="hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        إلغاء التحديد
                    </button>

                    <button type="button" id="media-picker-cancel"
                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100
                               dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        إلغاء
                    </button>

                    <button type="button" id="media-picker-confirm"
                        class="rounded-full bg-primary px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700
                               disabled:opacity-60 disabled:cursor-not-allowed">
                        استخدام العناصر المحددة
                    </button>
                </div>
            </footer>
        </div>
    </div>

    <script>
        window.MEDIA_CONFIG = window.MEDIA_CONFIG || {
            baseUrl: @json(route('media-library.media.index')),
            csrfToken: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('vendor/media-library/js/media-picker.js') }}" defer></script>
@endonce
