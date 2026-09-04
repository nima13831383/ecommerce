<?php

namespace App\Http\Requests\Storefront;

use App\Services\Shipping\ShippingOptionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $options = app(ShippingOptionCatalog::class);

        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'service' => ['required', 'string', Rule::in(array_keys($options->services()))],
            'payment_type' => ['required', 'string', Rule::in(array_keys($options->paymentTypes()))],
        ];
    }
}
