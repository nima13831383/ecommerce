<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StorefrontOrderQuery
{
    public function paginateFor(User $user, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->base($user)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function recentFor(User $user, int $limit = 3): Collection
    {
        return $this->base($user)->latest()->limit($limit)->get();
    }

    public function findFor(User $user, string $identifier): Order
    {
        return $this->detailBase($user)
            ->where(function (Builder $query) use ($identifier): void {
                $query->where('order_number', $identifier)
                    ->orWhere('orders.id', is_numeric($identifier) ? (int) $identifier : 0);
            })
            ->firstOrFail();
    }

    private function base(User $user): Builder
    {
        return Order::query()
            ->whereBelongsTo($user)
            ->withCount('items')
            ->with([
                'items.product' => fn ($query) => $query->withTrashed()->with('primaryImage'),
            ]);
    }

    private function detailBase(User $user): Builder
    {
        return Order::query()
            ->whereBelongsTo($user)
            ->with([
                'items.product' => fn ($query) => $query->withTrashed()->with('primaryImage'),
                'items.inventoryReservation',
                'shipment.statusHistories',
                'payments' => fn ($query) => $query->latest('id'),
                'statusHistories' => fn ($query) => $query->orderBy('created_at'),
            ]);
    }
}
