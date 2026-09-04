<?php

namespace App\Http\Requests\Api\V1;

class ResolveVariationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['required', 'array'],
            'options.*.attribute_id' => ['required', 'integer', 'min:1'],
            'options.*.value_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
