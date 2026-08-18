@props([
    'name',
    'label' => null,
    'buttonText' => null,
    'multiple' => false,
    'storeValue' => 'id',
    'value' => null,
    'previewUrls' => [],
])

{{--
    Reusable media picker trigger. Usage anywhere in a Blade view:

        <x-media-library::picker name="logo" label="Logo" />

    Requires the global picker modal to be included once per layout:

        @include('media-library::partials.picker-modal')

    and that public/vendor/media-library/js/media-picker.js is loaded on
    the page (published via `php artisan vendor:publish --tag=media-library-assets`).
--}}

@php
    $rawId = $attributes->get('id');
    $inputId = $rawId ?: 'mp_' . uniqid();
    $previewId = $inputId . '_preview';
    $containerAttributes = $attributes->except('id');
    $isMultiple = (bool) $multiple;
    $buttonText = $buttonText ?: __('Choose From Media Library');

    $extractScalarValue = static function ($item): ?string {
        if (is_scalar($item)) {
            $value = trim((string) $item);
            return $value !== '' ? $value : null;
        }

        if (is_array($item)) {
            foreach (['id', 'media_id', 'value', 'file_path', 'path', 'url'] as $key) {
                if (array_key_exists($key, $item) && is_scalar($item[$key])) {
                    $value = trim((string) $item[$key]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            foreach ($item as $nested) {
                if (is_scalar($nested)) {
                    $value = trim((string) $nested);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    };

    if ($isMultiple) {
        if (is_string($value)) {
            $idsArray = array_values(array_filter(array_map('trim', explode(',', $value))));
        } elseif (is_array($value)) {
            $idsArray = collect($value)
                ->map($extractScalarValue)
                ->filter()
                ->values()
                ->all();
        } else {
            $idsArray = [];
        }

        $inputValue = implode(',', $idsArray);
    } else {
        $inputValue = is_array($value)
            ? ($extractScalarValue($value) ?? '')
            : (is_scalar($value) ? (string) $value : '');
    }

    if ($previewUrls instanceof \Illuminate\Support\Collection) {
        $previewUrls = $previewUrls->all();
    }
@endphp

<div {{ $containerAttributes->class('col-span-6') }}>
    @if ($label)
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ $label }}
        </label>
    @endif

    <input
        type="hidden"
        id="{{ $inputId }}"
        name="{{ $name }}"
        value="{{ $inputValue }}"
    >

    <button
        type="button"
        class="btn-open-media-picker inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        data-target-input="{{ $inputId }}"
        data-target-preview="{{ $previewId }}"
        data-multiple="{{ $isMultiple ? 'true' : 'false' }}"
        data-store-value="{{ $storeValue }}"
    >
        {{ $buttonText }}
    </button>

    <div id="{{ $previewId }}" class="mt-2 flex flex-wrap gap-2">
        @foreach ($previewUrls as $url)
            @if ($url)
                <div class="relative h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                    <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
                </div>
            @endif
        @endforeach
    </div>
</div>
