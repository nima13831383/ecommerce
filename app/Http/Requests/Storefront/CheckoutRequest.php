<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'shipping_address_id' => ['required', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'min:1'],
            'shipping_service' => ['required', 'string', 'max:50'],
            'shipping_payment_type' => ['required', 'string', 'max:50'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_service' => is_string($this->shipping_service) ? trim($this->shipping_service) : $this->shipping_service,
            'shipping_payment_type' => is_string($this->shipping_payment_type) ? trim($this->shipping_payment_type) : $this->shipping_payment_type,
            'customer_note' => is_string($this->customer_note) ? trim($this->customer_note) : $this->customer_note,
            'idempotency_key' => is_string($this->idempotency_key) ? trim($this->idempotency_key) : $this->idempotency_key,
        ]);
    }
}
