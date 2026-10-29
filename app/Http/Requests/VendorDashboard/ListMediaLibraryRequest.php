<?php

namespace App\Http\Requests\VendorDashboard;

use Illuminate\Foundation\Http\FormRequest;

/** VENDOR-MEDIA-03A: the optional page, page size and search of the vendor's media library listing. */
class ListMediaLibraryRequest extends FormRequest
{
    public const MAX_PER_PAGE = 50;

    public const MAX_SEARCH = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'search' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_SEARCH],
        ];
    }

    /** Any of the three parameters asks for a page; without them the listing is the whole library, as before. */
    public function wantsPage(): bool
    {
        return $this->query->has('page') || $this->query->has('per_page') || $this->query->has('search');
    }
}
