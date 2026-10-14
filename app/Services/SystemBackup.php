<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class SystemBackup
{
    public function create(string $directory, string $password): string
    {
        $this->requireEncryption($password);
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Este respaldo integrado requiere SQLite. Configure el respaldo nativo del proveedor para otro motor.');
        }
        $this->makeDirectory($directory);
        $directory = realpath($directory);
        if ($directory === false || $this->inside($directory, public_path())) {
            throw new RuntimeException('El respaldo debe guardarse fuera de la carpeta pública.');
        }
        $archive = $directory.'/sistema-tramites-'.now()->format('Ymd\THis').'-'.Str::uuid().'.zip';
        $snapshot = tempnam(sys_get_temp_dir(), 'tramites-db-');
        if ($snapshot === false) {
            throw new RuntimeException('No se pudo preparar el respaldo de la base.');
        }
        $zip = new ZipArchive;
        $opened = false;
        $completed = false;
        try {
            // VACUUM INTO takes a consistent SQLite snapshot, including committed WAL data.
            DB::statement('VACUUM INTO ?', [$snapshot]);
            $this->verifyDatabase($snapshot);
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('No se pudo crear el archivo de respaldo.');
            }
            $zip->setPassword($password);
            $opened = true;
            $files = ['database.sqlite' => hash_file('sha256', $snapshot)];
            $zip->addFile($snapshot, 'database.sqlite');
            $environment = json_encode([
                'APP_KEY' => config('app.key'), 'APP_URL' => config('app.url'),
                'APP_NAME' => config('app.name'), 'APP_LOCALE' => config('app.locale'),
                'database' => 'sqlite', 'created_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            $files['environment.json'] = hash('sha256', $environment);
            $zip->addFromString('environment.json', $environment);
            $root = realpath((string) config('filesystems.disks.local.root'));
            $database = realpath((string) config('database.connections.sqlite.database'));
            if ($root === false) {
                throw new RuntimeException('No existe el almacenamiento privado.');
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('El almacenamiento privado contiene un enlace simbólico; revise su destino antes de respaldar.');
                }
                if (! $file->isFile()) {
                    continue;
                }
                $path = $file->getRealPath();
                if ($path === false || $this->inside($path, $directory) || $path === $database
                    || ($database !== false && in_array($path, [$database.'-wal', $database.'-shm', $database.'-journal'], true))) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
                $entry = 'private/'.$relative;
                $files[$entry] = hash_file('sha256', $path);
                $zip->addFile($path, $entry);
            }
            $manifest = json_encode(['format' => 1, 'created_at' => now()->toIso8601String(), 'files' => $files], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            $zip->addFromString('manifest.json', $manifest);
            foreach (array_merge(array_keys($files), ['manifest.json']) as $entry) {
                if (! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256)) {
                    throw new RuntimeException('No se pudo cifrar un componente del respaldo.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('No se pudo terminar el respaldo.');
            }
            $opened = false;
            $this->verify($archive, $password);
            $completed = true;
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($archive, 0600);
            }

            return $archive;
        } finally {
            if ($opened) {
                $zip->close();
            }
            if (is_file($snapshot)) {
                unlink($snapshot);
            }
            if (! $completed && is_file($archive)) {
                unlink($archive);
            }
        }
    }

    /** @return array{format: int, created_at: string, files: array<string, string>} */
    public function verify(string $archive, string $password): array
    {
        $this->requireEncryption($password);
        $zip = $this->open($archive, $password);
        try {
            $json = $zip->getFromName('manifest.json');
            if (! is_string($json)) {
                throw new RuntimeException('Contraseña incorrecta o manifiesto de respaldo ilegible.');
            }
            $manifest = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1
                || ! is_string($manifest['created_at'] ?? null) || ! is_array($manifest['files'] ?? null)
                || ! isset($manifest['files']['database.sqlite'], $manifest['files']['environment.json'])
                || $zip->numFiles !== count($manifest['files']) + 1) {
                throw new RuntimeException('El respaldo no tiene un manifiesto válido.');
            }
            $files = [];
            foreach ($manifest['files'] as $entry => $expected) {
                if (! is_string($entry) || ! is_string($expected) || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1) {
                    throw new RuntimeException('Un componente tiene un identificador inválido.');
                }
                $this->validateEntry($entry);
                $stat = $zip->statName($entry);
                if (! is_array($stat) || $stat['encryption_method'] !== ZipArchive::EM_AES_256) {
                    throw new RuntimeException('Un componente del respaldo no está cifrado con AES-256.');
                }
                $stream = $zip->getStream($entry);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Un componente no se pudo descifrar.');
                }
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);
                if (! hash_equals($expected, hash_final($hash))) {
                    throw new RuntimeException('La integridad del respaldo no coincide. No debe restaurarse.');
                }
                $files[$entry] = $expected;
            }

            return ['format' => 1, 'created_at' => $manifest['created_at'], 'files' => $files];
        } finally {
            $zip->close();
        }
    }

    public function restoreToEmptyDirectory(string $archive, string $password, string $target): void
    {
        $manifest = $this->verify($archive, $password);
        $this->makeDirectory($target);
        $target = realpath($target);
        if ($target === false || is_link($target) || $this->inside($target, base_path())
            || count(scandir($target) ?: []) !== 2) {
            throw new RuntimeException('La restauración requiere una carpeta vacía fuera del proyecto. Nunca reemplaza la base activa.');
        }
        $zip = $this->open($archive, $password);
        try {
            foreach (array_keys($manifest['files']) as $entry) {
                $this->validateEntry($entry);
                $destination = $target.'/'.$entry;
                $this->makeDirectory(dirname($destination));
                $source = $zip->getStream($entry);
                $output = fopen($destination, 'x');
                if (! is_resource($source) || ! is_resource($output)) {
                    throw new RuntimeException('No se pudo restaurar un componente.');
                }
                try {
                    if (stream_copy_to_stream($source, $output) === false) {
                        throw new RuntimeException('No se pudo copiar un componente restaurado.');
                    }
                } finally {
                    fclose($source);
                    fclose($output);
                }
                if (! hash_equals($manifest['files'][$entry], (string) hash_file('sha256', $destination))) {
                    throw new RuntimeException('El componente restaurado no coincide con el respaldo.');
                }
                if (PHP_OS_FAMILY !== 'Windows') {
                    chmod($destination, 0600);
                }
            }
            $this->verifyDatabase($target.'/database.sqlite');
        } finally {
            $zip->close();
        }
    }

    private function open(string $archive, string $password): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('No se pudo abrir el respaldo.');
        }
        $zip->setPassword($password);

        return $zip;
    }

    private function requireEncryption(string $password): void
    {
        if (strlen($password) < 20 || ! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) {
            throw new RuntimeException('Configure BACKUP_PASSWORD con al menos 20 caracteres y una extensión ZIP compatible con AES-256.');
        }
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('No se pudo preparar una carpeta privada.');
        }
    }

    private function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');

        return strcasecmp($path, $root) === 0 || str_starts_with(strtolower($path), strtolower($root).'/');
    }

    private function validateEntry(string $entry): void
    {
        if (str_contains($entry, '\\') || str_contains($entry, "\0") || str_contains($entry, ':')
            || preg_match('~(?:^|/)(?:\.{1,2})(?:/|$)~', $entry) === 1
            || ! ($entry === 'database.sqlite' || $entry === 'environment.json' || str_starts_with($entry, 'private/'))) {
            throw new RuntimeException('El respaldo contiene una ruta no permitida.');
        }
    }

    private function verifyDatabase(string $path): void
    {
        $database = new PDO('sqlite:'.$path);
        $integrity = $database->query('PRAGMA integrity_check');
        $foreignKeys = $database->query('PRAGMA foreign_key_check');
        if ($integrity === false || $foreignKeys === false || $integrity->fetchColumn() !== 'ok'
            || $foreignKeys->fetchAll() !== []) {
            throw new RuntimeException('La base del respaldo no superó la comprobación de integridad.');
        }
    }
}
