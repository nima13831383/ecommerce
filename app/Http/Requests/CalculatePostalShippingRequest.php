<?php

namespace App\Http\Requests;

use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\ShippingOptionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalculatePostalShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(WordpressShippingDataLoader $dataLoader, ShippingOptionCatalog $options): array
    {
        return [
            'origin_province' => ['required', 'integer', Rule::in(array_keys($dataLoader->provinces()))],
            'origin_city' => ['required', 'integer'],
            'destination_province' => ['required', 'integer', Rule::in(array_keys($dataLoader->provinces()))],
            'destination_city' => ['required', 'integer'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:30000'],
            'declared_value' => ['required', 'integer', 'min:0'],
            'parcel_type' => ['required', Rule::in(array_keys($options->parcelTypes()))],
            'payment_type' => ['required', Rule::in(array_keys($options->paymentTypes()))],
            'package_size' => ['required', 'integer', Rule::in(array_keys($options->packageSizes()))],
            'service' => ['required', Rule::in(array_keys($options->services()))],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'origin_province',
                    'origin_city',
                    'destination_province',
                    'destination_city',
                ])) {
                    return;
                }

                $dataLoader = app(WordpressShippingDataLoader::class);

                if (! $dataLoader->cityBelongsToProvince(
                    (int) $this->input('origin_city'),
                    (int) $this->input('origin_province')
                )) {
                    $validator->errors()->add('origin_city', 'شهر مبدأ با استان مبدأ انتخاب‌شده همخوانی ندارد.');
                }

                if (! $dataLoader->cityBelongsToProvince(
                    (int) $this->input('destination_city'),
                    (int) $this->input('destination_province')
                )) {
                    $validator->errors()->add('destination_city', 'شهر مقصد با استان مقصد انتخاب‌شده همخوانی ندارد.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'وارد کردن :attribute الزامی است.',
            'integer' => ':attribute باید یک عدد صحیح باشد.',
            'numeric' => ':attribute باید عدد باشد.',
            'in' => ':attribute انتخاب‌شده معتبر نیست.',
            'weight.gt' => 'وزن مرسوله باید بیشتر از صفر گرم باشد.',
            'weight.max' => 'حداکثر وزن قابل بررسی ۳۰٬۰۰۰ گرم است.',
            'declared_value.min' => 'ارزش مرسوله نمی‌تواند منفی باشد.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'origin_province' => 'استان مبدأ',
            'origin_city' => 'شهر مبدأ',
            'destination_province' => 'استان مقصد',
            'destination_city' => 'شهر مقصد',
            'weight' => 'وزن مرسوله',
            'declared_value' => 'ارزش مرسوله',
            'parcel_type' => 'نوع مرسوله',
            'payment_type' => 'نوع پرداخت',
            'package_size' => 'اندازه بسته',
            'service' => 'نوع سفارش',
        ];
    }
}
