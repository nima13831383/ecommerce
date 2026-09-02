<?php

use App\Enums\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);
it('reserves the last unit exactly once under two real MySQL worker processes', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $p = Product::query()->create(['name' => 'Race', 'slug' => 'race-'.uniqid(), 'sku' => 'RACE-'.uniqid(), 'type' => 'simple', 'price' => 100, 'status' => 'published']);
    app(InventoryService::class)->setOnHand($p, 1, reason: 'setup');
    DB::commit();
    $r = app(ConcurrentProcessRunner::class)->run('inventory_reserve', ['product_id' => $p->id, 'quantity' => 1]);
    $out = collect($r['results'])->pluck('json');
    expect($r['alive'])->toBeTrue()->and($out->where('ok', true))->toHaveCount(1)->and($out->where('ok', false))->toHaveCount(1);
    $p = $p->fresh();
    $active = InventoryReservation::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $p->id)->where('status', InventoryReservationStatus::Active)->get();
    expect($p->stock_quantity)->toBe(1)->and($active)->toHaveCount(1)->and($active->sum('quantity'))->toBe(1)->and(app(InventoryService::class)->availableQuantity($p))->toBe(0);
});
