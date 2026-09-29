<?php

namespace App\Providers;

use App\Database\TursoConnection;
use App\Database\TursoHttpClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::extend('libsql', function (array $config, string $name): TursoConnection {
            $url = $config['turso_url'] ?? null;
            $authToken = $config['auth_token'] ?? null;

            if (! is_string($url) || $url === '' || ! is_string($authToken) || $authToken === '') {
                throw new InvalidArgumentException('Turso database URL and authentication token must be configured.');
            }

            $connection = new TursoConnection(
                new TursoHttpClient(
                    $url,
                    $authToken,
                    (bool) ($config['foreign_key_constraints'] ?? true),
                    (int) ($config['timeout_seconds'] ?? 30),
                ),
                $config['database'] ?? 'turso',
                $config['prefix'] ?? '',
                [...$config, 'name' => $name],
            );

            return $connection;
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction() || DB::getDefaultConnection() === 'libsql',
        );

        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8)->mixedCase()->numbers(),
        );
    }
}
