<?php

use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;

uses(EnsuresMySqlTestDatabase::class);

beforeEach(function (): void {
    $this->assertSafeMySqlTestDatabase();
});

it('uses the isolated MySQL testing database and has critical unique constraints', function (): void {
    $database = (string) config('database.connections.mysql.database');

    expect(config('database.default'))->toBe('mysql')
        ->and(app()->environment())->toBe('testing')
        ->and($database)->toBe('ecommerce_testing');

    $indexes = collect(DB::select(
        'select table_name, index_name from information_schema.statistics where table_schema = ? and non_unique = 0',
        [$database],
    ))->map(fn (object $index): string => "{$index->table_name}.{$index->index_name}");

    expect($indexes)->toContain(
        'orders.orders_idempotency_key_unique',
        'coupon_usages.coupon_usages_coupon_order_unique',
        'shipments.shipments_order_id_unique',
        'customer_notifications.customer_notifications_key_unique',
        'product_variations.product_variation_combination_unique',
    );
});
