<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculatePostalShippingRequest;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\PostShippingCalculator;
use App\Services\Shipping\ShippingOptionCatalog;
use Illuminate\View\View;

class TapinCalculatorController extends Controller
{
    public function show(
        WordpressShippingDataLoader $dataLoader,
        ShippingOptionCatalog $options,
    ): View {
        return view('shipping-calculator-test', $this->viewData($dataLoader, $options));
    }

    public function calculate(
        CalculatePostalShippingRequest $request,
        WordpressShippingDataLoader $dataLoader,
        ShippingOptionCatalog $options,
    ): View {
        $validated = $request->validated();
        $calculator = new PostShippingCalculator(
            $dataLoader,
            (int) config('postal-shipping.tapin_service_fee_rials', 30_000),
            (int) config('postal-shipping.postal_service_fee_rials', 35_000),
        );
        $quote = $calculator->calculate(ShippingQuoteInput::fromArray($validated));

        return view('shipping-calculator-test', array_replace($this->viewData($dataLoader, $options), [
            'quote' => $quote,
            'formData' => $validated,
        ]));
    }

    /** @return array<string, mixed> */
    private function viewData(
        WordpressShippingDataLoader $dataLoader,
        ShippingOptionCatalog $options,
    ): array {
        return [
            'provinces' => $dataLoader->provinces(),
            'citiesByProvince' => $dataLoader->citiesByProvince(),
            'services' => $options->services(),
            'parcelTypes' => $options->parcelTypes(),
            'paymentTypes' => $options->paymentTypes(),
            'packageSizes' => $options->packageSizes(),
            'formData' => [],
            'quote' => null,
        ];
    }
}
