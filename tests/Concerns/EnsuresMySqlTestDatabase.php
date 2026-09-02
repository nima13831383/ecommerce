<?php

namespace Tests\Concerns;

use LogicException;

trait EnsuresMySqlTestDatabase
{
    protected function assertSafeMySqlTestDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (app()->environment() !== 'testing' || $connection !== 'mysql' || ! str_ends_with($database, '_testing')) {
            throw new LogicException('MySQL commerce tests require APP_ENV=testing, the mysql connection, and a database ending in _testing.');
        }

        if (in_array($database, ['ecommerce', 'production'], true)) {
            throw new LogicException('MySQL commerce tests must not use the configured development or production database.');
        }
    }
}
