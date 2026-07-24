<?php

namespace App\Services;

use App\Models\{Cart, CartItem, TaxRate};
use Illuminate\Support\Collection;

class TaxService
{
    /**
     * محاسبه مالیات سبد با پشتیبانی از نرخ‌های compound.
     * خروجی: ['total' => int, 'breakdown' => [['name'=>..,'amount'=>..], ...]]
     */
    public function calculate(Cart $cart, array $location = [], int $shippingTotal = 0): array
    {
        $cart->items->loadMissing('product.taxClass.rates');

        $lineBase   = [];   // مبلغ مشمول هر آیتم بر اساس tax_class
        foreach ($cart->items as $item) {
            $classId = $item->product->tax_class_id;
            if (! $classId) {
                continue; // محصول غیرمشمول
            }
            $lineBase[$classId] = ($lineBase[$classId] ?? 0)
                + (int) $item->unit_price * $item->quantity;
        }

        $breakdown = [];
        $total     = 0;

        foreach ($lineBase as $classId => $base) {
            $rates = $this->ratesFor($classId, $location);
            $runningBase = $base;
            $simpleTaxSum = 0;

            // ابتدا نرخ‌های ساده، سپس compound روی (base + مالیات‌های ساده)
            foreach ($rates->where('compound', false) as $rate) {
                $amount = (int) round($base * $rate->rate / 100);
                $simpleTaxSum += $amount;
                $total += $amount;
                $breakdown[] = ['name' => $rate->name, 'amount' => $amount];
            }

            $compoundBase = $base + $simpleTaxSum;
            foreach ($rates->where('compound', true) as $rate) {
                $amount = (int) round($compoundBase * $rate->rate / 100);
                $total += $amount;
                $breakdown[] = ['name' => $rate->name, 'amount' => $amount];
            }

            // مالیات حمل‌ونقل اگر نرخ shipping_taxable داشته باشد
            if ($shippingTotal > 0) {
                foreach ($rates->where('shipping_taxable', true) as $rate) {
                    $amount = (int) round($shippingTotal * $rate->rate / 100);
                    $total += $amount;
                    $breakdown[] = ['name' => $rate->name . ' (حمل)', 'amount' => $amount];
                }
            }
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    protected function ratesFor(int $classId, array $location): Collection
    {
        return TaxRate::where('tax_class_id', $classId)
            ->where('country', $location['country'] ?? 'IR')
            ->where(fn($q) => $q
                ->whereNull('state')
                ->orWhere('state', $location['state'] ?? null))
            ->orderBy('priority')
            ->get();
    }
}
