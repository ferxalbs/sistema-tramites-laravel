<?php

namespace App\Database;

use Closure;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

class TursoConnection extends SQLiteConnection
{
    private ?string $baton = null;

    public function __construct(
        private readonly TursoHttpClient $client,
        string $database,
        string $tablePrefix = '',
        array $config = [],
    ) {
        parent::__construct(null, $database, $tablePrefix, $config);
    }

    /**
     * Execute a select through Turso's HTTP pipeline.
     *
     * @param  string  $query
     * @param  array<int|string, mixed>  $bindings
     * @param  array<int, mixed>  $fetchUsing
     * @return array<int, object|array<string|int, mixed>|mixed>
     */
    #[\Override]
    public function select(mixed $query, mixed $bindings = [], mixed $useReadPdo = true, array $fetchUsing = []): array
    {
        if (! is_string($query) || ! is_array($bindings) || ! is_bool($useReadPdo)) {
            throw new InvalidArgumentException('Invalid Turso select arguments.');
        }

        return $this->run($query, $bindings, function (string $query, array $bindings) use ($fetchUsing): array {
            if ($this->pretending()) {
                return [];
            }

            $result = $this->executeStatements([[
                'sql' => $query,
                'bindings' => $this->prepareBindings($bindings),
                'want_rows' => true,
            ]])[0];

            $this->recordsHaveBeenModified(((int) ($result['affected_row_count'] ?? 0)) > 0);

            return $this->formatRows($result, $fetchUsing);
        });
    }

    /**
     * Execute a statement through Turso's HTTP pipeline.
     *
     * @param  string  $query
     * @param  array<int|string, mixed>  $bindings
     */
    #[\Override]
    public function statement(mixed $query, mixed $bindings = []): bool
    {
        if (! is_string($query) || ! is_array($bindings)) {
            throw new InvalidArgumentException('Invalid Turso statement arguments.');
        }

        return $this->run($query, $bindings, function (string $query, array $bindings): bool {
            if ($this->pretending()) {
                return true;
            }

            $this->executeStatements([[
                'sql' => $query,
                'bindings' => $this->prepareBindings($bindings),
                'want_rows' => false,
            ]]);

            $this->recordsHaveBeenModified();

            return true;
        });
    }

    /**
     * Execute a write query and return its affected row count.
     *
     * @param  string  $query
     * @param  array<int|string, mixed>  $bindings
     */
    #[\Override]
    public function affectingStatement(mixed $query, mixed $bindings = []): int
    {
        if (! is_string($query) || ! is_array($bindings)) {
            throw new InvalidArgumentException('Invalid Turso write arguments.');
        }

        return $this->run($query, $bindings, function (string $query, array $bindings): int {
            if ($this->pretending()) {
                return 0;
            }

            $result = $this->executeStatements([[
                'sql' => $query,
                'bindings' => $this->prepareBindings($bindings),
                'want_rows' => false,
            ]])[0];
            $affectedRows = (int) ($result['affected_row_count'] ?? 0);

            $this->recordsHaveBeenModified($affectedRows > 0);

            return $affectedRows;
        });
    }

    /**
     * Execute a statement that returns its generated row ID on the same HTTP stream.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    public function insertAndGetId(string $query, array $bindings): int
    {
        return $this->run($query, $bindings, function (string $query, array $bindings): int {
            if ($this->pretending()) {
                return 0;
            }

            $results = $this->executeStatements([
                [
                    'sql' => $query,
                    'bindings' => $this->prepareBindings($bindings),
                    'want_rows' => false,
                ],
                [
                    'sql' => 'SELECT last_insert_rowid() AS id',
                    'want_rows' => true,
                ],
            ]);
            $id = $this->decodeValue($results[1]['rows'][0][0] ?? []);

            if (! is_numeric($id) || (int) $id < 1) {
                throw new RuntimeException('Turso did not return a generated row ID.');
            }

            $this->recordsHaveBeenModified();

            return (int) $id;
        });
    }

    /**
     * Start a transaction using an HTTP stream baton instead of a PDO connection.
     */
    #[\Override]
    protected function executeBeginTransactionStatement(): void
    {
        try {
            $this->executeStatements([[
                'sql' => 'BEGIN',
                'want_rows' => false,
            ]], requireBaton: true);
        } catch (Throwable $exception) {
            $this->rollbackAndCloseStream();

            throw $exception;
        }
    }

    /**
     * Run a callback in a transaction without relying on Laravel's PDO commit path.
     *
     * HTTP writes can have an ambiguous outcome after a network failure, so this
     * connection deliberately does not retry a transaction callback.
     */
    #[\Override]
    public function transaction(Closure $callback, $attempts = 1)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->transactionLevel() > 0) {
                $this->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Create a nested transaction savepoint on the open HTTP stream.
     */
    #[\Override]
    protected function createSavepoint(): void
    {
        $this->statement(
            $this->queryGrammar->compileSavepoint('trans'.($this->transactions + 1)),
        );
    }

    /**
     * Commit through the active HTTP stream and close it.
     */
    #[\Override]
    public function commit(): void
    {
        if ($this->transactionLevel() === 1) {
            $this->fireConnectionEvent('committing');

            try {
                $this->executeStatements([[
                    'sql' => 'COMMIT',
                    'want_rows' => false,
                ]], closeStream: true);
            } catch (Throwable $exception) {
                $this->baton = null;
                $this->transactions = 0;
                $this->transactionsManager?->rollback($this->getName(), 0);

                throw $exception;
            }
        }

        [$levelBeingCommitted, $this->transactions] = [
            $this->transactions,
            max(0, $this->transactions - 1),
        ];

        $this->transactionsManager?->commit(
            $this->getName(), $levelBeingCommitted, $this->transactions,
        );

        $this->fireConnectionEvent('committed');
    }

    /**
     * Roll back through the active HTTP stream.
     *
     * @param  int  $toLevel
     */
    #[\Override]
    protected function performRollBack($toLevel): void
    {
        if ($toLevel === 0) {
            if ($this->baton === null) {
                return;
            }

            try {
                $this->executeStatements([[
                    'sql' => 'ROLLBACK',
                    'want_rows' => false,
                ]], closeStream: true);
            } catch (Throwable $exception) {
                $this->baton = null;
                $this->transactions = 0;
                $this->transactionsManager?->rollback($this->getName(), 0);

                throw $exception;
            }

            return;
        }

        if ($this->queryGrammar->supportsSavepoints()) {
            $this->statement(
                $this->queryGrammar->compileSavepointRollBack('trans'.($toLevel + 1)),
            );
        }
    }

    /**
     * Prevent the base connection from retrying ambiguous HTTP writes.
     */
    #[\Override]
    protected function handleQueryException(QueryException $exception, $query, $bindings, Closure $callback): void
    {
        throw $exception;
    }

    /**
     * HTTP-backed Turso connections do not expose PDO.
     */
    #[\Override]
    public function getPdo(): never
    {
        throw new LogicException('The Turso HTTP connection does not expose a PDO instance.');
    }

    /**
     * There is no PDO handle to reconnect for the stateless HTTP transport.
     */
    #[\Override]
    public function reconnectIfMissingConnection(): void
    {
        // Each query establishes an HTTP request to the configured Turso endpoint.
    }

    /**
     * Close an open remote stream when Laravel disconnects this connection.
     */
    #[\Override]
    public function disconnect(): void
    {
        $this->rollbackAndCloseStream();
        $this->transactions = 0;

        parent::disconnect();
    }

    /**
     * @param  list<array{sql: string, bindings?: array<int|string, mixed>, want_rows?: bool}>  $statements
     * @return list<array<string, mixed>>
     */
    private function executeStatements(array $statements, bool $closeStream = false, bool $requireBaton = false): array
    {
        $inTransaction = $this->transactions > 0;

        $closeStream = $closeStream || (! $inTransaction && ! $requireBaton);

        if ($inTransaction && $this->baton === null) {
            throw new RuntimeException('The Turso transaction stream is no longer available.');
        }

        try {
            $response = $this->client->pipeline(
                $inTransaction ? $this->baton : null,
                $statements,
                $closeStream,
            );
        } catch (Throwable $exception) {
            $this->baton = null;

            throw $exception;
        }

        $this->baton = $response['baton'];

        if ($closeStream) {
            $this->baton = null;
        } elseif (($inTransaction || $requireBaton) && $this->baton === null) {
            throw new RuntimeException('Turso did not retain the active transaction stream.');
        }

        if (is_array($response['setup_error'])) {
            $this->throwQueryError($response['setup_error']);
        }

        $results = [];

        foreach ($response['results'] as $result) {
            if (($result['type'] ?? null) === 'error') {
                $this->throwQueryError($result['error'] ?? []);
            }

            $statementResult = $result['response']['result'] ?? null;

            if (! is_array($statementResult)) {
                throw new RuntimeException('Turso returned an invalid SQL statement result.');
            }

            $results[] = $statementResult;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function throwQueryError(array $error): never
    {
        $code = (string) ($error['code'] ?? 'TURSO_SQL_ERROR');
        $message = (string) ($error['message'] ?? 'Turso rejected the SQL statement.');

        throw new RuntimeException('Turso SQL error ['.$code.']: '.$message);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, mixed>  $fetchUsing
     * @return array<int, object|array<string|int, mixed>|mixed>
     */
    private function formatRows(array $result, array $fetchUsing): array
    {
        $columns = array_map(
            static fn (array $column, int $index): string => is_string($column['name'] ?? null) ? $column['name'] : (string) $index,
            $result['cols'] ?? [],
            array_keys($result['cols'] ?? []),
        );
        $mode = $fetchUsing[0] ?? $this->fetchMode;

        return array_map(function (array $row) use ($columns, $fetchUsing, $mode): mixed {
            $values = array_map(fn (array $value): mixed => $this->decodeValue($value), $row);

            if ($mode === PDO::FETCH_NUM) {
                return $values;
            }

            if ($mode === PDO::FETCH_COLUMN) {
                return $values[(int) ($fetchUsing[1] ?? 0)] ?? false;
            }

            $associative = [];

            foreach ($values as $index => $value) {
                $associative[$columns[$index] ?? (string) $index] = $value;
            }

            if ($mode === PDO::FETCH_ASSOC || $mode === PDO::FETCH_NAMED) {
                return $associative;
            }

            if ($mode === PDO::FETCH_BOTH) {
                foreach ($values as $index => $value) {
                    $associative[$index] = $value;
                }

                return $associative;
            }

            if ($mode !== PDO::FETCH_OBJ) {
                throw new InvalidArgumentException('The Turso HTTP connection does not support this PDO fetch mode.');
            }

            return (object) $associative;
        }, $result['rows'] ?? []);
    }

    private function decodeValue(array $value): mixed
    {
        return match ($value['type'] ?? null) {
            'null' => null,
            'integer' => $this->decodeInteger($value['value'] ?? null),
            'float' => ($value['value'] ?? null) === null ? NAN : (float) $value['value'],
            'text' => (string) ($value['value'] ?? ''),
            'blob' => $this->decodeBlob($value['base64'] ?? ''),
            default => throw new RuntimeException('Turso returned an unsupported SQL value type.'),
        };
    }

    private function decodeInteger(mixed $value): int|string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return (string) $value;
        }

        return (int) $value;
    }

    private function decodeBlob(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Turso returned an invalid binary SQL value.');
        }

        $blob = base64_decode($value, true);

        if (! is_string($blob)) {
            throw new RuntimeException('Turso returned an invalid base64 SQL value.');
        }

        return $blob;
    }

    private function rollbackAndCloseStream(): void
    {
        if ($this->baton === null) {
            return;
        }

        try {
            $this->client->pipeline($this->baton, [[
                'sql' => 'ROLLBACK',
                'want_rows' => false,
            ]], true);
        } catch (Throwable) {
            // Expired remote streams have already been rolled back by Turso.
        } finally {
            $this->baton = null;
        }
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new TursoProcessor;
    }
}
