<?php

namespace HasanHawary\LookupManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HelpEnumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enums' => ['sometimes', 'array'],
            'enums.*.name' => ['required_with:enums', 'string'],
            'enums.*.module' => ['sometimes', 'nullable', 'string'],
            'enums.*.method' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
