<?php

namespace App\Services\Storefront;

use App\Enums\AddressType;
use App\Exceptions\AddressValidationException;
use App\Models\Cart;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Shipping\DTO\ShippingQuoteResult;
use App\Services\Shipping\ShippingCostResolver;
use App\Services\Shipping\ShippingOptionCatalog;

class StorefrontShippingQuoteService
{
    public function __construct(
        private readonly AddressService $addresses,
        private readonly ShippingCostResolver $shipping,
        private readonly ShippingOptionCatalog $options,
    ) {}

    public function quote(User $user, Cart $cart, int $addressId, string $service, string $paymentType): ShippingQuoteResult
    {
        $address = $this->addresses->getForUser($user, $addressId);
        $type = $address->type instanceof AddressType ? $address->type : AddressType::tryFrom((string) $address->type);

        if (! in_array($type, [AddressType::Shipping, AddressType::Both], true)) {
            throw new AddressValidationException('این آدرس برای ارسال قابل استفاده نیست.');
        }

        $snapshot = $this->addresses->snapshot($address);
        if ($snapshot['province_id'] === null || $snapshot['city_id'] === null) {
            throw new AddressValidationException('آدرس انتخاب‌شده باید استان و شهر معتبر داشته باشد.');
        }

        if (! array_key_exists($service, $this->options->services())) {
            throw new AddressValidationException('روش ارسال انتخاب‌شده معتبر نیست.');
        }

        if (! array_key_exists($paymentType, $this->options->paymentTypes())) {
            throw new AddressValidationException('روش پرداخت ارسال انتخاب‌شده معتبر نیست.');
        }

        return $this->shipping->quote(
            cart: $cart,
            destinationProvinceId: (int) $snapshot['province_id'],
            destinationCityId: (int) $snapshot['city_id'],
            service: $service,
            paymentType: $paymentType,
        );
    }

    /** @return array{service: string, service_label: string, payment_type: string, payment_type_label: string, amount: int, currency: string, mode: string, breakdown: array<int, array{key: string, label: string, amount: int|float}>} */
    public function present(ShippingQuoteResult $quote, string $paymentType): array
    {
        $services = $this->options->services();
        $payments = $this->options->paymentTypes();

        return [
            'service' => $quote->service,
            'service_label' => $services[$quote->service] ?? $quote->service,
            'payment_type' => $paymentType,
            'payment_type_label' => $payments[$paymentType] ?? $paymentType,
            'amount' => (int) $quote->total,
            'currency' => $quote->currency,
            'mode' => (string) ($quote->metadata['calculation_mode'] ?? 'calculator'),
            'breakdown' => $quote->breakdown,
        ];
    }
}
