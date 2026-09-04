<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupDevelopmentDatabase extends Command
{
    protected $signature = 'db:backup-development';

    protected $description = 'Create a timestamped backup of the local ecommerce development database.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'development'])) {
            $this->error('Development database backups are allowed only in local or development environments.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");
        $database = strtolower((string) ($config['database'] ?? ''));

        if ($connection !== 'mysql' || $database !== 'ecommerce') {
            $this->error('Refusing backup: the configured connection must be MySQL database ecommerce.');

            return self::FAILURE;
        }

        $backupDirectory = (string) env(
            'DEVELOPMENT_DB_BACKUP_PATH',
            dirname(base_path()).DIRECTORY_SEPARATOR.'db'.DIRECTORY_SEPARATOR.'backups',
        );

        if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0775, true) && ! is_dir($backupDirectory)) {
            $this->error('Unable to create the backup directory.');

            return self::FAILURE;
        }

        $timestamp = now()->format('Y-m-d_His');
        $path = $backupDirectory.DIRECTORY_SEPARATOR."ecommerce_{$timestamp}.sql";
        if (is_file($path)) {
            $this->error('Refusing to overwrite an existing backup.');

            return self::FAILURE;
        }

        $binary = (string) env('MYSQLDUMP_BINARY', 'mysqldump');
        if (PHP_OS_FAMILY === 'Windows' && $binary === 'mysqldump' && is_file('C:\\xampp\\mysql\\bin\\mysqldump.exe')) {
            $binary = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        }

        $arguments = [
            $binary,
            '--protocol=TCP',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--events',
            '--triggers',
            '--result-file='.$path,
            $database,
        ];

        $environment = null;
        if (filled($config['password'] ?? null)) {
            $environment = ['MYSQL_PWD' => (string) $config['password']];
        }

        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            if (is_file($path)) {
                @unlink($path);
            }

            $this->error('Database backup failed.');

            return self::FAILURE;
        }

        $this->info("Backup created: {$path}");

        return self::SUCCESS;
    }
}
