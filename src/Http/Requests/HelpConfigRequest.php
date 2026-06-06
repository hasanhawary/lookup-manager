<?php

namespace HasanHawary\LookupManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HelpConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'configs' => ['required', 'array'],
            'configs.*.name' => ['required', 'string'],
            'configs.*.keys' => ['sometimes', 'array'],
            'configs.*.keys.*' => ['string'],
        ];
    }
}
