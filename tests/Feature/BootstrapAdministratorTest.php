<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('the first administrator is active and receives a one-time password', function () {
    $result = Artisan::call('accounts:bootstrap-admin', [
        '--nombres' => 'María',
        '--apellidos' => 'Pérez',
        '--email' => 'Admin@Seoane.Edu.Pe',
        '--no-interaction' => true,
    ]);

    $user = User::query()->sole();
    preg_match('/Contraseña temporal: (\S+)/', Artisan::output(), $matches);
    expect($result)->toBe(0)
        ->and($user->email)->toBe('admin@seoane.edu.pe')
        ->and($user->rol)->toBe('administrador')
        ->and($user->activo)->toBeTrue()
        ->and($user->estado_cuenta)->toBe('activo')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->debe_cambiar_password)->toBeTrue()
        ->and($user->cuenta_provisional)->toBeFalse()
        ->and($matches)->toHaveKey(1)
        ->and(Hash::check($matches[1], $user->password))->toBeTrue();
});

test('bootstrap cannot create a second administrator', function () {
    User::factory()->create();

    $result = Artisan::call('accounts:bootstrap-admin', [
        '--nombres' => 'María',
        '--apellidos' => 'Pérez',
        '--email' => 'admin@seoane.edu.pe',
        '--no-interaction' => true,
    ]);

    expect($result)->toBe(1)
        ->and(User::query()->count())->toBe(1)
        ->and(Artisan::output())->not->toContain('Contraseña temporal:');
});

test('bootstrap rejects a noninstitutional email without writing an account', function () {
    $result = Artisan::call('accounts:bootstrap-admin', [
        '--nombres' => 'María',
        '--apellidos' => 'Pérez',
        '--email' => 'admin@example.com',
        '--no-interaction' => true,
    ]);

    expect($result)->toBe(1)
        ->and(User::query()->count())->toBe(0);
});
