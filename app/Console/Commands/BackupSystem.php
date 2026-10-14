<?php

namespace App\Console\Commands;

use App\Services\SystemBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupSystem extends Command
{
    protected $signature = 'system:backup {--directory=}';

    protected $description = 'Crea un respaldo SQLite y de archivos privados cifrado y verificado.';

    public function handle(SystemBackup $backups): int
    {
        try {
            $path = $backups->create((string) ($this->option('directory') ?? config('backup.directory')), (string) config('backup.password'));
            $this->info('Respaldo cifrado y verificado: '.$path);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
