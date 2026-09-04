<?php

use App\Support\DatabaseSafetyGuard;
use Illuminate\Support\Facades\Config;

test('the testing environment uses a database isolated from development ecommerce', function (): void {
    $connection = (string) config('database.default');
    $database = strtolower((string) config("database.connections.{$connection}.database"));

    expect(app()->environment('testing'))->toBeTrue()
        ->and($database)->not->toBe('ecommerce');
});

test('the guard rejects ecommerce when a testing connection is misconfigured', function (): void {
    $originalDefault = config('database.default');
    $originalDatabase = config('database.connections.mysql.database');

    Config::set('database.default', 'mysql');
    Config::set('database.connections.mysql.database', 'ecommerce');

    try {
        expect(fn (): never => DatabaseSafetyGuard::assertTestingDatabaseIsolated())
            ->toThrow(LogicException::class);
    } finally {
        Config::set('database.default', $originalDefault);
        Config::set('database.connections.mysql.database', $originalDatabase);
    }
});
