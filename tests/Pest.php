<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function configureDisposableTursoConnection(): bool
{
    $url = (string) env('TURSO_TEST_DATABASE_URL', '');
    $authToken = (string) env('TURSO_TEST_AUTH_TOKEN', '');
    $isDisposable = filter_var(env('TURSO_TEST_DATABASE_DISPOSABLE', false), FILTER_VALIDATE_BOOLEAN);
    $applicationUrl = (string) config('database.connections.libsql.turso_url', '');
    $normalizeEndpoint = static function (string $databaseUrl): ?string {
        $parts = parse_url($databaseUrl);

        if (! is_array($parts) || ! is_string($parts['host'] ?? null)) {
            return null;
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $path = preg_replace('~/v3/pipeline$~', '', $path) ?? $path;

        return strtolower($parts['host']).':'.(int) ($parts['port'] ?? 443).$path;
    };
    $sameDatabase = $applicationUrl !== ''
        && $normalizeEndpoint($applicationUrl) !== null
        && $normalizeEndpoint($applicationUrl) === $normalizeEndpoint($url);

    if ($url === '' || $authToken === '' || ! $isDisposable || $sameDatabase) {
        return false;
    }

    config([
        'database.connections.libsql.turso_url' => $url,
        'database.connections.libsql.auth_token' => $authToken,
    ]);
    DB::purge('libsql');

    return true;
}
