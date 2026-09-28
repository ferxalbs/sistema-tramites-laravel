<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('Turso supports prepared values, generated IDs, foreign keys, and transaction rollback', function () {
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    $connection = DB::connection('libsql');
    $tablePrefix = 'codex_probe_'.bin2hex(random_bytes(6));
    $parentTable = $tablePrefix.'_parents';
    $childTable = $tablePrefix.'_children';
    $label = "D'Angelo prepared test value";

    try {
        expect(fn () => $connection->transaction(function () use ($connection, $parentTable, $childTable, $label): void {
            Schema::connection('libsql')->create($parentTable, function (Blueprint $table): void {
                $table->id();
            });

            Schema::connection('libsql')->create($childTable, function (Blueprint $table) use ($parentTable): void {
                $table->id();
                $table->foreignId('parent_id')->constrained($parentTable);
                $table->string('label');
                $table->string('optional_value')->nullable();
            });

            $parentId = $connection->table($parentTable)->insertGetId([]);
            $childId = $connection->table($childTable)->insertGetId([
                'parent_id' => $parentId,
                'label' => $label,
                'optional_value' => null,
            ]);
            $child = $connection->table($childTable)->where('id', $childId)->first();

            expect($parentId)->toBeGreaterThan(0);
            expect($childId)->toBeGreaterThan(0);
            expect($child->label)->toBe($label);
            expect($child->optional_value)->toBeNull();
            expect(fn () => $connection->table($childTable)->insert([
                'parent_id' => 999999,
                'label' => 'foreign key rejection test',
            ]))->toThrow(QueryException::class);

            throw new RuntimeException('Rollback Turso probe transaction.');
        }))->toThrow(RuntimeException::class, 'Rollback Turso probe transaction.');

        $remainingTables = $connection->select(
            'SELECT name FROM sqlite_schema WHERE type = ? AND name IN (?, ?)',
            ['table', $parentTable, $childTable],
        );

        expect($remainingTables)->toBeEmpty();
        expect(fn () => $connection->select('SELECT 1 FOR UPDATE'))->toThrow(QueryException::class);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        $connection->statement("DROP TABLE IF EXISTS {$childTable}");
        $connection->statement("DROP TABLE IF EXISTS {$parentTable}");
    }
});

test('Turso atomically sequences simultaneous updates from separate Laravel connections', function () {
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    if (! function_exists('proc_open')) {
        $this->markTestSkipped('The PHP proc_open function is not available.');
    }

    $connection = DB::connection('libsql');
    $table = 'codex_probe_'.bin2hex(random_bytes(6)).'_counter';
    $childCode = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $connection = Illuminate\Support\Facades\DB::connection('libsql');
    $values = [];

    for ($index = 0; $index < (int) $argv[2]; $index++) {
        $values[] = $connection->selectOne(
            'UPDATE '.$argv[1].' SET value = value + 1 WHERE id = 1 RETURNING value',
        )->value;
    }

    echo json_encode($values, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception));
    exit(1);
}
PHP;

    $connection->statement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, value INTEGER NOT NULL)");
    $connection->statement("INSERT INTO {$table} (id, value) VALUES (1, 0)");

    try {
        $processes = [];
        $writerCount = 4;
        $writesPerWriter = 10;

        for ($index = 0; $index < $writerCount; $index++) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, '-d', 'error_reporting=24575', '-r', $childCode, $table, (string) $writesPerWriter],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
            );

            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start a Turso writer process.');
            }

            fclose($pipes[0]);
            $processes[] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];

        foreach ($processes as $child) {
            $standardOutput = stream_get_contents($child['pipes'][1]);
            $standardError = stream_get_contents($child['pipes'][2]);
            fclose($child['pipes'][1]);
            fclose($child['pipes'][2]);
            $results[] = [
                'exit_code' => proc_close($child['process']),
                'values' => json_decode((string) $standardOutput, true),
                'error' => trim((string) $standardError),
            ];
        }

        expect(array_column($results, 'exit_code'))->toBe([0, 0, 0, 0]);
        expect(array_column($results, 'error'))->toBe(['', '', '', '']);

        $values = array_merge(...array_column($results, 'values'));
        sort($values);

        expect($values)->toBe(range(1, $writerCount * $writesPerWriter));
        expect((int) $connection->table($table)->where('id', 1)->value('value'))
            ->toBe($writerCount * $writesPerWriter);
    } finally {
        $connection->statement("DROP TABLE IF EXISTS {$table}");
    }
});
