<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
use App\Models\User;

class InventoryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-transactions.viewAny');
    }

    public function view(User $user, InventoryTransaction $transaction): bool
    {
        return $user->can('inventory-transactions.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryTransaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, InventoryTransaction $transaction): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
