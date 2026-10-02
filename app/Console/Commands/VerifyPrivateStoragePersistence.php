<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerifyPrivateStoragePersistence extends Command
{
    protected $signature = 'storage:verify-persistence {--write : Create a marker before deployment} {--check= : Compare with the marker after deployment}';

    protected $description = 'Check that private files survive a deployment without exposing a document';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $expected = $this->option('check');

        if ($write === ($expected !== null)) {
            $this->error('Choose exactly one of --write or --check=MARKER.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $path = '.persistence-probe';

        if ($write) {
            $marker = (string) Str::uuid();
            if (! $disk->put($path, $marker) || $disk->get($path) !== $marker) {
                $this->error('Cannot write and read from private storage.');

                return self::FAILURE;
            }

            $this->line($marker);

            return self::SUCCESS;
        }

        if (! is_string($expected) || ! Str::isUuid($expected)) {
            $this->error('Invalid marker.');

            return self::FAILURE;
        }

        if (! $disk->exists($path) || ! hash_equals($expected, (string) $disk->get($path))) {
            $this->error('Private storage did not retain the marker. Check the volume before accepting uploads or issuing PDFs.');

            return self::FAILURE;
        }

        $this->info('Private storage retained the marker.');

        return self::SUCCESS;
    }
}
