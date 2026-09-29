<?php

use App\Database\TursoConnection;
use App\Database\TursoHttpClient;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

test('Turso HTTP pipeline serializes named bindings and verifies its response', function () {
    Http::fake(fn () => Http::response([
        'baton' => null,
        'base_url' => null,
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [
                            ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                            [
                                'affected_row_count' => 0,
                                'cols' => [['name' => 'id']],
                                'rows' => [[['type' => 'integer', 'value' => '42']]],
                            ],
                        ],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');
    $response = $client->pipeline(null, [[
        'sql' => 'SELECT id FROM users WHERE name = :name AND active = :active AND token = :token',
        'bindings' => [
            'name' => "D'Angelo",
            'active' => true,
            'token' => "\x00\xFF",
        ],
        'want_rows' => true,
    ]], closeStream: true);

    expect($response['results'][0]['response']['result']['rows'][0][0]['value'])->toBe('42');
    Http::assertSent(function (Request $request): bool {
        $data = $request->data();
        $steps = $data['requests'][0]['batch']['steps'];
        $bindings = $steps[1]['stmt']['named_args'];

        return $request->url() === 'https://example.turso.io/v3/pipeline'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $data['requests'][0]['type'] === 'batch'
            && $steps[0]['stmt']['sql'] === 'PRAGMA foreign_keys = ON'
            && $bindings[0]['value'] === ['type' => 'text', 'value' => "D'Angelo"]
            && $bindings[1]['value'] === ['type' => 'integer', 'value' => '1']
            && $bindings[2]['value'] === ['type' => 'blob', 'base64' => 'AP8'];
    });
});

test('Turso HTTP client does not retry a failed or ambiguous request', function () {
    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');
    $statement = [['sql' => 'UPDATE counters SET value = value + 1', 'want_rows' => false]];

    Http::fake(fn () => Http::response(['error' => 'unavailable'], 503));

    expect(fn () => $client->pipeline(null, $statement, closeStream: true))
        ->toThrow(RuntimeException::class, 'Turso HTTP API returned status 503.');
    Http::assertSentCount(1);

    $remoteWrites = 0;
    $failedConnection = Http::failedConnection('response lost after remote write');
    Http::fake(function ($request, array $options) use (&$remoteWrites, $failedConnection) {
        $remoteWrites++;

        return $failedConnection($request, $options);
    });

    expect(fn () => $client->pipeline(null, $statement, closeStream: true))
        ->toThrow(ConnectionException::class);
    expect($remoteWrites)->toBe(1);
});

test('Turso HTTP client rejects an untrusted server supplied pipeline host', function () {
    Http::fake(fn () => Http::response([
        'baton' => 'server-stream',
        'base_url' => 'https://attacker.example/v3/pipeline',
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [
                            ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                            ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                        ],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');

    expect(fn () => $client->pipeline(null, [['sql' => 'SELECT 1']], closeStream: true))
        ->toThrow(RuntimeException::class, 'Turso returned an untrusted HTTP pipeline URL.');
    Http::assertSentCount(1);
});

test('Turso HTTP client rejects incomplete pipeline responses', function () {
    Http::fake(fn () => Http::response(['results' => []]));

    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');

    expect(fn () => $client->pipeline(null, [['sql' => 'SELECT 1']], closeStream: true))
        ->toThrow(RuntimeException::class, 'Turso returned an invalid pipeline response.');
    Http::assertSentCount(1);
});

test('Turso HTTP client rejects a batch that did not confirm foreign keys', function () {
    $result = ['affected_row_count' => 0, 'cols' => [], 'rows' => []];
    Http::fake(fn () => Http::response([
        'baton' => null,
        'base_url' => null,
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [null, $result],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');

    expect(fn () => $client->pipeline(null, [['sql' => 'SELECT 1']], closeStream: true))
        ->toThrow(RuntimeException::class, 'Turso did not confirm foreign-key enforcement.');
    Http::assertSentCount(1);
});

test('Turso connection rejects a successful HTTP response with incomplete SQL results', function () {
    $result = ['affected_row_count' => 0, 'cols' => [], 'rows' => []];
    Http::fake(fn () => Http::response([
        'baton' => null,
        'base_url' => null,
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [$result, ['affected_row_count' => 1]],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');

    expect(fn () => $connection->statement('UPDATE counters SET value = value + 1'))
        ->toThrow(RuntimeException::class, 'Turso returned an incomplete SQL statement result.');
    Http::assertSentCount(1);
});

test('Turso HTTP client starts a new stream at its primary endpoint after an incomplete response', function () {
    $urls = [];
    Http::fake(function (Request $request) use (&$urls) {
        $urls[] = $request->url();

        return Http::response([
            'baton' => count($urls) === 1 ? 'lost-baton' : null,
            'base_url' => count($urls) === 1 ? 'https://secondary.turso.io/v3/pipeline' : null,
            'results' => count($urls) === 1 ? [[
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [['affected_row_count' => 0, 'cols' => [], 'rows' => []]],
                        'step_errors' => [null],
                    ],
                ],
            ]] : [
                [
                    'type' => 'ok',
                    'response' => [
                        'type' => 'batch',
                        'result' => [
                            'step_results' => [
                                ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                                ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                            ],
                            'step_errors' => [null, null],
                        ],
                    ],
                ],
                ['type' => 'ok', 'response' => ['type' => 'close']],
            ],
        ]);
    });

    $client = new TursoHttpClient('libsql://example.turso.io', 'test-token');

    expect(fn () => $client->pipeline(null, [['sql' => 'SELECT 1']], closeStream: false))
        ->toThrow(RuntimeException::class, 'Turso returned an incomplete batch result.');
    expect($client->pipeline(null, [['sql' => 'SELECT 1']], closeStream: true)['results'])->toHaveCount(1);
    expect($urls)->toBe([
        'https://example.turso.io/v3/pipeline',
        'https://example.turso.io/v3/pipeline',
    ]);
});

test('Turso connection carries its baton across begin, write, and commit without retries', function () {
    $requests = [];
    $statementResult = ['affected_row_count' => 0, 'cols' => [], 'rows' => []];

    Http::fake(function (Request $request) use (&$requests, $statementResult) {
        $body = $request->data();
        $requests[] = $body;
        $requestIndex = count($requests) - 1;

        if ($requestIndex === 0) {
            $results = [[
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [$statementResult, $statementResult],
                        'step_errors' => [null, null],
                    ],
                ],
            ]];
            $baton = 'transaction-baton-1';
        } elseif ($requestIndex === 1) {
            $results = [[
                'type' => 'ok',
                'response' => [
                    'type' => 'execute',
                    'result' => ['affected_row_count' => 1, 'cols' => [], 'rows' => []],
                ],
            ]];
            $baton = 'transaction-baton-2';
        } else {
            $results = [
                ['type' => 'ok', 'response' => ['type' => 'execute', 'result' => $statementResult]],
                ['type' => 'ok', 'response' => ['type' => 'close']],
            ];
            $baton = null;
        }

        return Http::response([
            'baton' => $baton,
            'base_url' => null,
            'results' => $results,
        ]);
    });

    $connection = new TursoConnection(
        new TursoHttpClient('libsql://example.turso.io', 'test-token'),
        'turso',
    );

    $connection->transaction(function (TursoConnection $connection): void {
        $connection->statement('UPDATE counters SET value = value + 1');
    });

    expect(count($requests))->toBe(3)
        ->and($requests[0]['baton'])->toBeNull()
        ->and($requests[0]['requests'][0]['batch']['steps'][1]['stmt']['sql'])->toBe('BEGIN')
        ->and($requests[1]['baton'])->toBe('transaction-baton-1')
        ->and($requests[1]['requests'][0]['stmt']['sql'])->toBe('UPDATE counters SET value = value + 1')
        ->and($requests[2]['baton'])->toBe('transaction-baton-2')
        ->and($requests[2]['requests'][0]['stmt']['sql'])->toBe('COMMIT')
        ->and($requests[2]['requests'][1]['type'])->toBe('close');
});

test('Turso connection decodes SQL value types after the HTTP client receives them', function () {
    Http::fake(fn () => Http::response([
        'baton' => null,
        'base_url' => null,
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [
                            ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                            [
                                'affected_row_count' => 0,
                                'cols' => array_map(fn (string $name): array => ['name' => $name], ['id', 'large_id', 'ratio', 'label', 'missing', 'binary']),
                                'rows' => [[
                                    ['type' => 'integer', 'value' => '42'],
                                    ['type' => 'integer', 'value' => '9223372036854775808'],
                                    ['type' => 'float', 'value' => 1.5],
                                    ['type' => 'text', 'value' => 'Expediente'],
                                    ['type' => 'null'],
                                    ['type' => 'blob', 'base64' => 'AP8'],
                                ]],
                            ],
                        ],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');
    $row = $connection->selectOne('SELECT id, large_id, ratio, label, missing, binary FROM probe');

    expect($row->id)->toBe(42)
        ->and($row->large_id)->toBe('9223372036854775808')
        ->and($row->ratio)->toBe(1.5)
        ->and($row->label)->toBe('Expediente')
        ->and($row->missing)->toBeNull()
        ->and($row->binary)->toBe("\x00\xFF");
    Http::assertSentCount(1);
});

test('Turso connection reads the remote SQLite version for schema alterations', function () {
    Http::fake(fn () => Http::response([
        'baton' => null,
        'base_url' => null,
        'results' => [
            [
                'type' => 'ok',
                'response' => [
                    'type' => 'batch',
                    'result' => [
                        'step_results' => [
                            ['affected_row_count' => 0, 'cols' => [], 'rows' => []],
                            [
                                'affected_row_count' => 0,
                                'cols' => [['name' => 'version']],
                                'rows' => [[['type' => 'text', 'value' => '3.50.4']]],
                            ],
                        ],
                        'step_errors' => [null, null],
                    ],
                ],
            ],
            ['type' => 'ok', 'response' => ['type' => 'close']],
        ],
    ]));

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');

    expect($connection->getServerVersion())->toBe('3.50.4');
    Http::assertSentCount(1);
});

test('Turso connection previews bound SQL without a PDO connection or remote write', function () {
    Http::preventStrayRequests();

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');
    $queries = $connection->pretend(fn (TursoConnection $connection): bool => $connection->statement(
        'UPDATE users SET name = ? WHERE id = ?',
        ["D'Angelo", 42],
    ));

    expect($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toBe("UPDATE users SET name = 'D''Angelo' WHERE id = 42");
    Http::assertNothingSent();
});

test('Turso schema inspection omits unsupported pragma schema arguments', function () {
    $queries = [];

    Http::fake(function (Request $request) use (&$queries) {
        $queries[] = $request->data()['requests'][0]['batch']['steps'][1]['stmt']['sql'];
        $emptyResult = ['affected_row_count' => 0, 'cols' => [], 'rows' => []];

        return Http::response([
            'baton' => null,
            'base_url' => null,
            'results' => [
                [
                    'type' => 'ok',
                    'response' => [
                        'type' => 'batch',
                        'result' => [
                            'step_results' => [$emptyResult, $emptyResult],
                            'step_errors' => [null, null],
                        ],
                    ],
                ],
                ['type' => 'ok', 'response' => ['type' => 'close']],
            ],
        ]);
    });

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');
    $schema = $connection->getSchemaBuilder();

    expect($schema->getColumns('tramite_documentos'))->toBeEmpty();
    expect($schema->getIndexes('tramite_documentos'))->toBeEmpty();
    expect($schema->getForeignKeys('tramite_documentos'))->toBeEmpty();
    expect($queries)->toHaveCount(4)
        ->and($queries[0])->toContain("pragma_table_xinfo('tramite_documentos')")
        ->and($queries[2])->toContain("pragma_index_list('tramite_documentos')")
        ->and($queries[3])->toContain("pragma_foreign_key_list('tramite_documentos')");
});

test('Turso connection rolls back on the latest baton after a failed transaction callback', function () {
    $requests = [];
    $statementResult = ['affected_row_count' => 1, 'cols' => [], 'rows' => []];

    Http::fake(function (Request $request) use (&$requests, $statementResult) {
        $requests[] = $request->data();
        $index = count($requests);

        return Http::response([
            'baton' => $index === 3 ? null : 'transaction-baton-'.$index,
            'base_url' => null,
            'results' => $index === 1
                ? [[
                    'type' => 'ok',
                    'response' => [
                        'type' => 'batch',
                        'result' => [
                            'step_results' => [$statementResult, $statementResult],
                            'step_errors' => [null, null],
                        ],
                    ],
                ]]
                : [
                    ['type' => 'ok', 'response' => ['type' => 'execute', 'result' => $statementResult]],
                    ...($index === 3 ? [['type' => 'ok', 'response' => ['type' => 'close']]] : []),
                ],
        ]);
    });

    $connection = new TursoConnection(new TursoHttpClient('libsql://example.turso.io', 'test-token'), 'turso');

    expect(fn () => $connection->transaction(function (TursoConnection $connection): void {
        $connection->statement('UPDATE counters SET value = value + 1');
        throw new RuntimeException('Stop this transaction.');
    }))->toThrow(RuntimeException::class, 'Stop this transaction.');

    expect($connection->transactionLevel())->toBe(0)
        ->and($requests)->toHaveCount(3)
        ->and($requests[1]['baton'])->toBe('transaction-baton-1')
        ->and($requests[2]['baton'])->toBe('transaction-baton-2')
        ->and($requests[2]['requests'][0]['stmt']['sql'])->toBe('ROLLBACK')
        ->and($requests[2]['requests'][1]['type'])->toBe('close');
});

test('Turso supports prepared values, generated IDs, foreign keys, and transaction rollback', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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
    $testUrl = (string) env('TURSO_TEST_DATABASE_URL', '');
    $testToken = (string) env('TURSO_TEST_AUTH_TOKEN', '');
    $isDisposable = filter_var(env('TURSO_TEST_DATABASE_DISPOSABLE', false), FILTER_VALIDATE_BOOLEAN);
    $applicationUrl = (string) config('database.connections.libsql.turso_url', '');
    $testParts = parse_url($testUrl);
    $applicationParts = parse_url($applicationUrl);
    $testHost = strtolower((string) ($testParts['host'] ?? ''));
    $applicationHost = strtolower((string) ($applicationParts['host'] ?? ''));

    if ($testUrl === '' || $testToken === '' || ! $isDisposable || ($testHost !== '' && $testHost === $applicationHost)) {
        throw new RuntimeException('A separate disposable Turso test database must be configured.');
    }

    config([
        'database.connections.libsql.turso_url' => $testUrl,
        'database.connections.libsql.auth_token' => $testToken,
    ]);
    Illuminate\Support\Facades\DB::purge('libsql');
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

    try {
        $connection->statement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, value INTEGER NOT NULL)");
        $connection->statement("INSERT INTO {$table} (id, value) VALUES (1, 0)");

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
