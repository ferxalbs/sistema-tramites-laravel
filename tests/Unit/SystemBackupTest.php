<?php

use App\Services\SystemBackup;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->backupRoot = sys_get_temp_dir().'/tramites-backup-test-'.Str::uuid();
    mkdir($this->backupRoot.'/private/firmas', 0700, true);
    touch($this->backupRoot.'/live.sqlite');
    config([
        'database.default' => 'backup_test',
        'database.connections.backup_test' => [...config('database.connections.sqlite'), 'database' => $this->backupRoot.'/live.sqlite'],
        'filesystems.disks.local.root' => $this->backupRoot.'/private',
    ]);
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    DB::table('users')->insert([['name' => 'Usuario original'], ['name' => 'Usuario conservado']]);
    file_put_contents($this->backupRoot.'/private/firmas/test.jpg', 'private signature bytes');
    $this->backupPassword = 'test-only-long-encryption-password';
});

afterEach(function () {
    DB::disconnect('backup_test');
    (new Filesystem)->deleteDirectory($this->backupRoot);
});

test('encrypted backup restores a consistent database and private files without touching live data', function () {
    $backups = app(SystemBackup::class);
    $archive = $backups->create($this->backupRoot.'/backups', $this->backupPassword);
    $zip = new ZipArchive;
    $zip->open($archive, ZipArchive::RDONLY);
    expect($zip->getFromName('database.sqlite'))->toBeFalse()
        ->and($zip->getFromName('environment.json'))->toBeFalse();
    $zip->close();
    DB::table('users')->insert(['name' => 'Cambio posterior al respaldo']);
    $backups->restoreToEmptyDirectory($archive, $this->backupPassword, $this->backupRoot.'/restored');
    $copy = new PDO('sqlite:'.$this->backupRoot.'/restored/database.sqlite');
    expect((int) $copy->query('SELECT COUNT(*) FROM users')->fetchColumn())->toBe(2)
        ->and(DB::table('users')->count())->toBe(3)
        ->and(file_get_contents($this->backupRoot.'/restored/private/firmas/test.jpg'))->toBe('private signature bytes');
    expect(fn () => $backups->verify($archive, 'wrong-but-long-test-password'))->toThrow(RuntimeException::class);
    expect(fn () => $backups->restoreToEmptyDirectory($archive, $this->backupPassword, $this->backupRoot.'/restored'))->toThrow(RuntimeException::class);
    expect(fn () => $backups->restoreToEmptyDirectory($archive, $this->backupPassword, base_path()))->toThrow(RuntimeException::class);
});

test('backup verification refuses modified entries and incomplete unencrypted archives', function () {
    $backups = app(SystemBackup::class);
    $archive = $backups->create($this->backupRoot.'/backups', $this->backupPassword);
    $zip = new ZipArchive;
    $zip->open($archive);
    $zip->setPassword($this->backupPassword);
    $zip->addFromString('private/firmas/test.jpg', 'tampered bytes');
    $zip->setEncryptionName('private/firmas/test.jpg', ZipArchive::EM_AES_256);
    $zip->close();
    expect(fn () => $backups->verify($archive, $this->backupPassword))->toThrow(RuntimeException::class);
    expect(fn () => $backups->create($this->backupRoot.'/backups', 'short'))->toThrow(RuntimeException::class);
});
