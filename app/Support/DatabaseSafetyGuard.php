<?php

namespace App\Support;

use LogicException;

final class DatabaseSafetyGuard
{
    /** @var list<string> */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    public static function assertTestingDatabaseIsolated(): void
    {
        $connection = (string) config('database.default');
        $database = strtolower((string) config("database.connections.{$connection}.database"));

        if (! app()->environment('testing')) {
            return;
        }

        if ($database === '' || in_array($database, ['ecommerce', 'production'], true)) {
            throw new LogicException('Testing must use an isolated database; the development/production database is forbidden.');
        }
    }

    public static function assertNoDestructiveArtisanCommand(string $command): void
    {
        if (! app()->environment(['local', 'development'])) {
            return;
        }

        $connection = (string) config('database.default');
        $database = strtolower((string) config("database.connections.{$connection}.database"));

        if ($connection === 'mysql' && $database === 'ecommerce' && in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            throw new LogicException("Refusing {$command} against the development ecommerce database.");
        }
    }
}
