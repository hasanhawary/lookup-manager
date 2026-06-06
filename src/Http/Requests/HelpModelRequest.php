<?php

namespace HasanHawary\LookupManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HelpModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tables' => ['sometimes', 'array'],
            'tables.*.name' => ['required_with:tables', 'string'],
            'tables.*.module' => ['sometimes', 'nullable', 'string'],
            'tables.*.extra' => ['sometimes', 'nullable', 'array'],
            'tables.*.extra.*' => ['string'],
            'tables.*.scopes' => ['sometimes', 'nullable', 'array'],
            'tables.*.scopes.*' => ['string'],
            'tables.*.values' => ['sometimes', 'nullable', 'array'],
            'tables.*.search' => ['sometimes', 'nullable'],
            'tables.*.search.term' => ['required_with:tables.*.search', 'string'],
            'tables.*.search.fields' => ['sometimes', 'array'],
            'tables.*.search.fields.*' => ['string'],
            'tables.*.with' => ['sometimes', 'array'],
            'tables.*.with.*' => ['string'],
            'tables.*.paginate' => ['sometimes', 'boolean'],
            'tables.*.per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('lookup.models.max_per_page', 1000)],
            'tables.*.page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
