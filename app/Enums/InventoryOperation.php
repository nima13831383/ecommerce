<?php

namespace App\Enums;

enum InventoryOperation: string
{
    case OpeningStock = 'opening_stock';
    case ManualAdjustment = 'manual_adjustment';
    case Restock = 'restock';
    case Deduction = 'deduction';
    case ReservationCommit = 'reservation_commit';
    case Correction = 'correction';
}
