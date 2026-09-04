<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'brands' => ['nullable', 'array', 'max:20'],
            'brands.*' => ['string', 'max:100'],
            'in_stock' => ['nullable', 'boolean'],
            'type' => ['nullable', 'in:simple,variable'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name_asc,name_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $min = $this->input('min_price');
                $max = $this->input('max_price');

                if (is_numeric($min) && is_numeric($max) && (int) $min > (int) $max) {
                    $validator->errors()->add('min_price', 'حداقل قیمت نباید از حداکثر قیمت بیشتر باشد.');
                }
            },
        ];
    }

    public function filters(): array
    {
        return $this->validated();
    }
}
