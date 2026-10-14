<?php

namespace App\Console\Commands;

use App\Services\SystemBackup;
use Illuminate\Console\Command;
use Throwable;

class VerifySystemBackup extends Command
{
    protected $signature = 'system:verify-backup {archive} {--restore-to=}';

    protected $description = 'Verifica un respaldo cifrado y opcionalmente lo restaura en una carpeta vacía fuera del proyecto.';

    public function handle(SystemBackup $backups): int
    {
        try {
            $archive = (string) $this->argument('archive');
            $password = (string) config('backup.password');
            $manifest = $backups->verify($archive, $password);
            $target = $this->option('restore-to');
            if (is_string($target)) {
                $backups->restoreToEmptyDirectory($archive, $password, $target);
            }
            $this->info('Integridad confirmada: '.count($manifest['files']).' componentes; fecha '.$manifest['created_at'].'.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
