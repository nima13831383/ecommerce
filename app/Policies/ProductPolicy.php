<?php

namespace App\Policies;

use App\Enums\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('products.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('products.restore');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('products.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('products.force-delete')
            && ! $this->hasActiveInventoryReservation($product);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('products.force-delete');
    }

    private function hasActiveInventoryReservation(Product $product): bool
    {
        $variationIds = $this->variationIds($product);

        return InventoryReservation::query()
            ->where(function ($query) use ($product, $variationIds): void {
                $query->where(function ($query) use ($product): void {
                    $query->where('inventory_owner_type', Product::class)
                        ->where('inventory_owner_id', $product->id);
                })->orWhere(function ($query) use ($variationIds): void {
                    $query->where('inventory_owner_type', ProductVariation::class)
                        ->whereIn('inventory_owner_id', $variationIds);
                });
            })
            ->where('status', InventoryReservationStatus::Active)
            ->exists();
    }

    private function variationIds(Product $product)
    {
        return ProductVariation::query()->where('product_id', $product->id)->pluck('id');
    }
}
