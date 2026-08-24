<?php

namespace App\Services\Orders;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidReservationStateException;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Inventory\InventoryService;
use App\Services\Tax\TaxCalculator;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        private readonly ProductPriceResolver $prices,
        private readonly TaxCalculator $taxes,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int, product_variation_id?: int}>  $lines
     * @param  array<string, mixed>  $details
     */
    public function create(array $lines, array $details, ?int $actorId = null): Order
    {
        if ($lines === []) {
            throw new DomainException('An order must contain at least one item.');
        }

        $customer = $this->customerDetails($details);
        $idempotency = $this->idempotency($lines, $details, $customer);

        if ($idempotency !== null) {
            $existing = Order::query()->where('idempotency_key', $idempotency['key'])->first();

            if ($existing) {
                return $this->idempotentOrder($existing, $idempotency['fingerprint']);
            }
        }

        try {
            return DB::transaction(function () use ($lines, $details, $customer, $actorId, $idempotency): Order {
                $order = new Order;
                $order->forceFill([
                    'order_number' => $this->orderNumber(),
                    'status' => OrderStatus::Pending,
                    'idempotency_key' => $idempotency['key'] ?? null,
                    'request_fingerprint' => $idempotency['fingerprint'] ?? null,
                    ...$customer,
                    'user_id' => $this->nullablePositiveInteger($details['user_id'] ?? null),
                    'cart_id' => $this->nullablePositiveInteger($details['cart_id'] ?? null),
                    'billing_address' => $details['billing_address'] ?? null,
                    'shipping_address' => $details['shipping_address'] ?? null,
                    'currency' => $details['currency'] ?? 'IRR',
                    'ip_address' => $details['ip_address'] ?? null,
                    'user_agent' => $details['user_agent'] ?? null,
                    'customer_note' => $details['customer_note'] ?? null,
                    'items_subtotal' => 0,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'shipping_total' => 0,
                    'grand_total' => 0,
                    'paid_total' => 0,
                    'refunded_total' => 0,
                ])->save();

                $itemsSubtotal = 0;
                $taxTotal = 0;
                $taxBreakdown = [];
                $reservableItems = [];

                foreach ($lines as $line) {
                    $snapshot = $this->snapshotLine($line);
                    $item = $order->items()->create($snapshot['attributes']);
                    $reservableItems[] = ['item' => $item, 'owner' => $snapshot['inventory_owner']];

                    $itemsSubtotal = $this->add($itemsSubtotal, $snapshot['line_subtotal']);
                    $taxTotal = $this->add($taxTotal, $snapshot['tax_amount']);
                    $taxBreakdown[] = $snapshot['tax_snapshot'];
                }

                $this->reserveInventoryForItems($reservableItems, $this->reservationExpiry($details));

                $order->forceFill([
                    'items_subtotal' => $itemsSubtotal,
                    'tax_total' => $taxTotal,
                    'tax_breakdown' => $taxBreakdown,
                    'grand_total' => $this->add($itemsSubtotal, $taxTotal),
                ])->save();

                $order->statusHistories()->create([
                    'user_id' => $actorId,
                    'from_status' => null,
                    'to_status' => OrderStatus::Pending->value,
                    'type' => 'status',
                    'comment' => 'Initial order state.',
                ]);

                return $order->load(['items.inventoryReservation', 'statusHistories']);
            });
        } catch (QueryException $exception) {
            if ($idempotency === null) {
                throw $exception;
            }

            $existing = Order::query()->where('idempotency_key', $idempotency['key'])->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->idempotentOrder($existing, $idempotency['fingerprint']);
        }
    }

    public function transitionStatus(Order $order, OrderStatus|string $to, ?int $actorId = null, ?string $comment = null): Order
    {
        $to = $to instanceof OrderStatus ? $to : OrderStatus::from($to);

        return DB::transaction(function () use ($order, $to, $actorId, $comment): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if ($from === $to) {
                throw new DomainException('The order is already in the requested status.');
            }

            if (! in_array($to, $this->allowedTransitions($from), true)) {
                throw new DomainException("The order cannot transition from {$from->value} to {$to->value}.");
            }

            if ($to === OrderStatus::Cancelled) {
                $this->cancelInventoryForOrder($order);
            }

            $order->applyStatus($to);
            $order->statusHistories()->create([
                'user_id' => $actorId,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'type' => 'status',
                'comment' => $comment,
            ]);

            return $order->load('statusHistories');
        });
    }

    public function releaseInventoryForOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            foreach ($this->reservationsForOrder($order) as $reservation) {
                if ($reservation->status === InventoryReservationStatus::Active) {
                    $this->inventory->release($reservation);
                }
            }

            return $order->load('items.inventoryReservation');
        });
    }

    public function commitInventoryForOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            foreach ($this->reservationsForOrder($order) as $reservation) {
                $this->inventory->commit($reservation);
            }

            return $order->load('items.inventoryReservation');
        });
    }

    /** @return array<int, OrderStatus> */
    private function allowedTransitions(OrderStatus $from): array
    {
        return match ($from) {
            OrderStatus::Pending => [OrderStatus::AwaitingPayment, OrderStatus::Cancelled, OrderStatus::Failed],
            OrderStatus::AwaitingPayment => [OrderStatus::Processing, OrderStatus::Cancelled, OrderStatus::Failed],
            OrderStatus::Processing => [OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Cancelled],
            OrderStatus::Shipped => [OrderStatus::Delivered],
            OrderStatus::Delivered => [OrderStatus::Completed],
            OrderStatus::Completed => [OrderStatus::Refunded],
            OrderStatus::Failed => [OrderStatus::AwaitingPayment, OrderStatus::Cancelled],
            OrderStatus::Cancelled, OrderStatus::Refunded => [],
        };
    }

    /** @param array<string, mixed> $line */
    private function snapshotLine(array $line): array
    {
        $productId = $this->positiveInteger($line['product_id'] ?? null, 'product_id');
        $quantity = $this->positiveInteger($line['quantity'] ?? null, 'quantity');
        $product = Product::query()->with('taxClass')->findOrFail($productId);
        $variation = $this->variationFor($product, $line['product_variation_id'] ?? null);
        $inventoryOwner = $this->inventoryOwnerFor($product, $variation);
        $unitPrice = $variation
            ? $this->prices->effectivePriceForVariation($variation)
            : $this->prices->pricesForProduct($product)['effective_price'];

        if ($unitPrice === null || $unitPrice < 0) {
            throw new DomainException('The selected product does not have a valid price.');
        }

        $lineSubtotal = $this->multiply($unitPrice, $quantity);
        $taxClass = $product->getEffectiveTaxClass();
        $taxAmount = $this->taxes->calculateTax($lineSubtotal, $taxClass, $quantity);
        $attributes = $variation ? $this->variationAttributes($variation) : null;
        $taxSnapshot = [
            'tax_class_id' => $taxClass?->id,
            'tax_class_name' => $taxClass?->name,
            'tax_type' => $taxClass?->type?->value,
            'tax_value' => $taxClass?->value,
            'taxable_amount' => $lineSubtotal,
            'tax_amount' => $taxAmount,
        ];

        return [
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'tax_snapshot' => $taxSnapshot,
            'inventory_owner' => $inventoryOwner,
            'attributes' => [
                'product_id' => $product->id,
                'product_variation_id' => $variation?->id,
                'product_name' => $product->name,
                'sku' => $variation?->sku ?? $product->sku,
                'variation_attributes' => $attributes,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'tax_snapshot' => $taxSnapshot,
                'line_total' => $this->add($lineSubtotal, $taxAmount),
            ],
        ];
    }

    private function variationFor(Product $product, mixed $variationId): ?ProductVariation
    {
        if (! in_array($product->type, ['simple', 'variable'], true)) {
            throw new DomainException('Only simple and variable products can be ordered in this phase.');
        }

        if ($product->type !== 'variable') {
            if ($variationId !== null) {
                throw new DomainException('Only variable products may include a variation.');
            }

            return null;
        }

        $variationId = $this->positiveInteger($variationId, 'product_variation_id');

        return $product->variations()
            ->whereKey($variationId)
            ->where('is_active', true)
            ->with('attributeValues.attribute')
            ->firstOrFail();
    }

    private function inventoryOwnerFor(Product $product, ?ProductVariation $variation): Product|ProductVariation
    {
        if ($product->type === 'simple') {
            return $product;
        }

        if ($variation === null) {
            throw new DomainException('A variable product requires a valid variation.');
        }

        return $variation;
    }

    /** @param array<int, array{item: OrderItem, owner: Product|ProductVariation}> $items */
    private function reserveInventoryForItems(array $items, CarbonInterface $expiresAt): void
    {
        usort($items, fn (array $left, array $right) => $this->ownerKey($left['owner']) <=> $this->ownerKey($right['owner']));

        foreach ($items as $entry) {
            $reservation = $this->inventory->reserve(
                $entry['owner'],
                $entry['item']->quantity,
                $expiresAt,
                'order_item',
                (string) $entry['item']->id,
            );

            $entry['item']->forceFill(['inventory_reservation_id' => $reservation->id])->save();
        }
    }

    /** @return Collection<int, InventoryReservation> */
    private function reservationsForOrder(Order $order)
    {
        $items = $order->items()->get(['id', 'inventory_reservation_id']);

        if ($items->contains(fn (OrderItem $item) => $item->inventory_reservation_id === null)) {
            throw new InvalidReservationStateException('The order does not have complete inventory reservation coverage.');
        }

        $reservations = $order->inventoryReservations()
            ->lockForUpdate()
            ->orderBy('inventory_owner_type')
            ->orderBy('inventory_owner_id')
            ->orderBy('inventory_reservations.id')
            ->get();

        if ($reservations->count() !== $items->count()) {
            throw new InvalidReservationStateException('The order inventory reservations are incomplete.');
        }

        return $reservations;
    }

    private function cancelInventoryForOrder(Order $order): void
    {
        $reservations = $this->reservationsForOrder($order);

        if ($reservations->contains(fn (InventoryReservation $reservation) => $reservation->status === InventoryReservationStatus::Committed)) {
            throw new DomainException('An order with committed inventory cannot be cancelled without a return workflow.');
        }

        foreach ($reservations as $reservation) {
            if ($reservation->status === InventoryReservationStatus::Active) {
                $this->inventory->release($reservation);
            }
        }
    }

    private function reservationExpiry(array $details): CarbonInterface
    {
        $expiry = $details['reservation_expires_at'] ?? now()->addMinutes(30);

        if (! $expiry instanceof CarbonInterface) {
            throw new DomainException('reservation_expires_at must be a date-time value.');
        }

        return $expiry;
    }

    private function ownerKey(Product|ProductVariation $owner): string
    {
        return $owner::class.':'.str_pad((string) $owner->id, 20, '0', STR_PAD_LEFT);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function idempotency(array $lines, array $details, array $customer): ?array
    {
        $key = trim((string) ($details['idempotency_key'] ?? ''));

        if ($key === '') {
            return null;
        }

        if (mb_strlen($key) > 100) {
            throw new DomainException('idempotency_key may not exceed 100 characters.');
        }

        $canonicalLines = array_map(function (array $line): array {
            return [
                'product_id' => $this->positiveInteger($line['product_id'] ?? null, 'product_id'),
                'product_variation_id' => ! array_key_exists('product_variation_id', $line) || $line['product_variation_id'] === null
                    ? null
                    : $this->positiveInteger($line['product_variation_id'], 'product_variation_id'),
                'quantity' => $this->positiveInteger($line['quantity'] ?? null, 'quantity'),
            ];
        }, $lines);
        usort($canonicalLines, fn (array $left, array $right) => json_encode($left) <=> json_encode($right));

        return [
            'key' => $key,
            'fingerprint' => hash('sha256', json_encode([
                'customer' => $customer,
                'user_id' => $this->nullablePositiveInteger($details['user_id'] ?? null),
                'currency' => $details['currency'] ?? 'IRR',
                'lines' => $canonicalLines,
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    private function idempotentOrder(Order $order, string $fingerprint): Order
    {
        if (! hash_equals((string) $order->request_fingerprint, $fingerprint)) {
            throw new DomainException('The idempotency key has already been used with a different order request.');
        }

        return $order->load(['items.inventoryReservation', 'statusHistories']);
    }

    /** @return array<string, string> */
    private function variationAttributes(ProductVariation $variation): array
    {
        return $variation->attributeValues
            ->sortBy(fn ($value) => $value->attribute->sort_order)
            ->mapWithKeys(fn ($value) => [$value->attribute->name => $value->value])
            ->all();
    }

    /** @param array<string, mixed> $details */
    private function customerDetails(array $details): array
    {
        $name = trim((string) ($details['customer_name'] ?? ''));
        $mobile = trim((string) ($details['customer_mobile'] ?? ''));

        if ($name === '' || $mobile === '') {
            throw new DomainException('Customer name and mobile are required to create an order.');
        }

        return Arr::only([
            'customer_name' => $name,
            'customer_mobile' => $mobile,
            'customer_email' => $details['customer_email'] ?? null,
            'national_id' => $details['national_id'] ?? null,
        ], ['customer_name', 'customer_mobile', 'customer_email', 'national_id']);
    }

    private function orderNumber(): string
    {
        return 'ORD-'.Str::upper((string) Str::ulid());
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new DomainException("{$field} must be a positive integer.");
        }

        return (int) $value;
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        return $value === null ? null : $this->positiveInteger($value, 'identifier');
    }

    private function multiply(int $left, int $right): int
    {
        if ($left > intdiv(PHP_INT_MAX, $right)) {
            throw new InvalidArgumentException('Order amount exceeds the supported range.');
        }

        return $left * $right;
    }

    private function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Order amount exceeds the supported range.');
        }

        return $left + $right;
    }
}
