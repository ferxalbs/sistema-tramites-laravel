<?php

namespace App\Database;

use BackedEnum;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class TursoHttpClient
{
    private string $pipelineUrl;

    private readonly string $primaryPipelineUrl;

    private readonly string $primaryHost;

    private readonly int $primaryPort;

    public function __construct(
        string $databaseUrl,
        private readonly string $authToken,
        private readonly bool $foreignKeyConstraints = true,
        private readonly int $timeoutSeconds = 30,
    ) {
        if ($authToken === '') {
            throw new InvalidArgumentException('The Turso authentication token must be configured.');
        }

        [$this->primaryPipelineUrl, $this->primaryHost, $this->primaryPort] = $this->pipelineUrl($databaseUrl);
        $this->pipelineUrl = $this->primaryPipelineUrl;
    }

    /**
     * @param  list<array{sql: string, bindings?: array<int|string, mixed>, want_rows?: bool}>  $statements
     * @return array{results: list<array<string, mixed>>, setup_error: array<string, mixed>|null, baton: string|null}
     */
    public function pipeline(?string $baton, array $statements, bool $closeStream): array
    {
        if ($statements === []) {
            throw new InvalidArgumentException('A Turso pipeline must contain at least one SQL statement.');
        }

        $initializesForeignKeys = $baton === null && $this->foreignKeyConstraints;
        $requests = $initializesForeignKeys
            ? [$this->foreignKeyBatch($statements)]
            : $this->executeRequests($statements);

        if ($closeStream) {
            $requests[] = ['type' => 'close'];
        }

        $response = Http::withToken($this->authToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds)
            ->post($this->pipelineUrl, [
                'baton' => $baton,
                'requests' => $requests,
            ]);

        if (! $response->successful()) {
            $this->pipelineUrl = $this->primaryPipelineUrl;

            throw new RuntimeException('Turso HTTP API returned status '.$response->status().'.');
        }

        $body = $response->json();

        if (! is_array($body)
            || ! array_key_exists('baton', $body)
            || ! array_key_exists('base_url', $body)
            || ! array_key_exists('results', $body)
            || ! is_array($body['results'])
            || ($body['baton'] !== null && ! is_string($body['baton']))
            || ($body['base_url'] !== null && ! is_string($body['base_url']))) {
            $this->pipelineUrl = $this->primaryPipelineUrl;

            throw new RuntimeException('Turso returned an invalid pipeline response.');
        }

        $nextBaton = $body['baton'] ?? null;

        if ($nextBaton !== null && is_string($body['base_url'] ?? null)) {
            $this->pipelineUrl = $this->pipelineUrlFromServer($body['base_url']);
        } elseif ($nextBaton === null) {
            $this->pipelineUrl = $this->primaryPipelineUrl;
        }

        $setupError = null;

        if ($initializesForeignKeys) {
            [$results, $setupError] = $this->batchResults($body['results'][0] ?? null, count($statements));
            $closeResultIndex = 1;
        } else {
            $results = array_slice($body['results'], 0, count($statements));
            $closeResultIndex = count($statements);
        }

        if (count($results) !== count($statements)) {
            throw new RuntimeException('Turso returned an incomplete pipeline result.');
        }

        if ($closeStream) {
            $this->assertStreamClosed($body['results'][$closeResultIndex] ?? null);

            if ($nextBaton !== null) {
                $this->pipelineUrl = $this->primaryPipelineUrl;

                throw new RuntimeException('Turso kept a stream open after it was closed.');
            }
        }

        return [
            'results' => $results,
            'setup_error' => $setupError,
            'baton' => $nextBaton,
        ];
    }

    /**
     * @param  list<array{sql: string, bindings?: array<int|string, mixed>, want_rows?: bool}>  $statements
     * @return array{type: string, batch: array{steps: list<array<string, mixed>>}}
     */
    private function foreignKeyBatch(array $statements): array
    {
        $steps = [[
            'stmt' => $this->statement('PRAGMA foreign_keys = ON', [], false),
        ]];
        $previousStep = 0;

        foreach ($statements as $statement) {
            $currentStep = count($steps);
            $steps[] = [
                'condition' => ['type' => 'ok', 'step' => $previousStep],
                'stmt' => $this->statement(
                    $statement['sql'],
                    $statement['bindings'] ?? [],
                    $statement['want_rows'] ?? true,
                ),
            ];
            $previousStep = $currentStep;
        }

        return ['type' => 'batch', 'batch' => ['steps' => $steps]];
    }

    /**
     * @param  list<array{sql: string, bindings?: array<int|string, mixed>, want_rows?: bool}>  $statements
     * @return list<array{type: string, stmt: array<string, mixed>}>
     */
    private function executeRequests(array $statements): array
    {
        return array_map(fn (array $statement): array => [
            'type' => 'execute',
            'stmt' => $this->statement(
                $statement['sql'],
                $statement['bindings'] ?? [],
                $statement['want_rows'] ?? true,
            ),
        ], $statements);
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<string, mixed>
     */
    private function statement(string $sql, array $bindings, bool $wantRows): array
    {
        $statement = [
            'sql' => $sql,
            'want_rows' => $wantRows,
        ];

        if ($bindings === []) {
            return $statement;
        }

        if (array_is_list($bindings)) {
            $statement['args'] = array_map($this->encodeValue(...), $bindings);

            return $statement;
        }

        $statement['named_args'] = [];

        foreach ($bindings as $name => $value) {
            if (! is_string($name)) {
                throw new InvalidArgumentException('Named Turso bindings must use string keys.');
            }

            $statement['named_args'][] = [
                'name' => ltrim($name, ':@$'),
                'value' => $this->encodeValue($value),
            ];
        }

        return $statement;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeValue(mixed $value): array
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return ['type' => 'null'];
        }

        if (is_bool($value)) {
            $value = (int) $value;
        }

        if (is_int($value)) {
            return ['type' => 'integer', 'value' => (string) $value];
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Turso SQL bindings cannot contain non-finite floats.');
            }

            return ['type' => 'float', 'value' => $value];
        }

        if (is_resource($value)) {
            $contents = stream_get_contents($value);

            if (! is_string($contents)) {
                throw new InvalidArgumentException('A binary Turso SQL binding could not be read.');
            }

            $value = $contents;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Turso SQL bindings must be scalar values or binary streams.');
        }

        if (preg_match('//u', $value) !== 1) {
            return ['type' => 'blob', 'base64' => rtrim(base64_encode($value), '=')];
        }

        return ['type' => 'text', 'value' => $value];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private function batchResults(mixed $batchEntry, int $statementCount): array
    {
        if (! is_array($batchEntry) || ($batchEntry['type'] ?? null) !== 'ok') {
            $error = is_array($batchEntry) && is_array($batchEntry['error'] ?? null)
                ? $batchEntry['error']
                : ['code' => 'TURSO_BATCH_ERROR', 'message' => 'Turso could not initialize foreign-key enforcement.'];

            return [array_fill(0, $statementCount, ['type' => 'error', 'error' => $error]), $error];
        }

        $batch = $batchEntry['response']['result'] ?? null;

        if (! is_array($batch) || ! is_array($batch['step_results'] ?? null) || ! is_array($batch['step_errors'] ?? null)) {
            throw new RuntimeException('Turso returned an invalid batch result.');
        }

        $setupError = $batch['step_errors'][0] ?? null;
        $results = [];

        for ($index = 0; $index < $statementCount; $index++) {
            $stepIndex = $index + 1;
            $stepError = $batch['step_errors'][$stepIndex] ?? null;
            $stepResult = $batch['step_results'][$stepIndex] ?? null;

            if (is_array($stepError)) {
                $results[] = ['type' => 'error', 'error' => $stepError];
            } elseif (is_array($stepResult)) {
                $results[] = [
                    'type' => 'ok',
                    'response' => ['type' => 'execute', 'result' => $stepResult],
                ];
            } else {
                $results[] = [
                    'type' => 'error',
                    'error' => ['code' => 'TURSO_BATCH_SKIPPED', 'message' => 'Turso skipped a statement after an earlier batch failure.'],
                ];
            }
        }

        return [$results, is_array($setupError) ? $setupError : null];
    }

    private function assertStreamClosed(mixed $closeResult): void
    {
        if (! is_array($closeResult) || ($closeResult['type'] ?? null) !== 'ok' || ($closeResult['response']['type'] ?? null) !== 'close') {
            throw new RuntimeException('Turso could not close the HTTP database stream.');
        }
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function pipelineUrl(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! is_string($parts['host'] ?? null)) {
            throw new InvalidArgumentException('TURSO_DATABASE_URL must be a valid remote database URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['turso', 'libsql', 'https'], true)) {
            throw new InvalidArgumentException('Turso HTTP connections require a Turso or HTTPS URL.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('TURSO_DATABASE_URL must not contain credentials, a query, or a fragment.');
        }

        $host = strtolower($parts['host']);
        $origin = 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $path = str_ends_with($path, '/v3/pipeline') ? $path : $path.'/v3/pipeline';

        return [$origin.$path, $host, (int) ($parts['port'] ?? 443)];
    }

    private function pipelineUrlFromServer(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new RuntimeException('Turso returned an untrusted HTTP pipeline URL.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $trustedTursoHost = $host === 'turso.io' || str_ends_with($host, '.turso.io');
        $port = (int) ($parts['port'] ?? 443);
        $trustedEndpoint = ($host === $this->primaryHost && $port === $this->primaryPort)
            || ($trustedTursoHost && $port === 443);

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || $host === ''
            || ! $trustedEndpoint
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('Turso returned an untrusted HTTP pipeline URL.');
        }

        $path = (string) ($parts['path'] ?? '');

        if ($path === '' || $path === '/') {
            $path = '/v3/pipeline';
        } elseif (! str_ends_with($path, '/v3/pipeline')) {
            throw new RuntimeException('Turso returned an invalid HTTP pipeline path.');
        }

        return 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }
}
