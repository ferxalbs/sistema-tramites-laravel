<?php

use App\Models\ProgramaEstudio;
use App\Models\User;
use Database\Seeders\ProgramaEstudioSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->seed(ProgramaEstudioSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Notification::fake();
    $program = ProgramaEstudio::query()->firstOrFail();
    $response = $this->post(route('register.store'), [
        'nombres' => 'María',
        'apellidos' => 'Pérez Ramos',
        'dni' => '12345678',
        'celular' => '987654321',
        'email' => 'a.maria@seoane.edu.pe',
        'correo_alternativo' => 'maria@example.com',
        'programa_estudio_id' => $program->id,
        'condicion_academica' => 'Estudiante',
        'ciclo_actual' => 6,
        'acepta_terminos' => '1',
        'password' => 'ClaveNueva2026',
        'password_confirmation' => 'ClaveNueva2026',
        'rol' => 'administrador',
        'activo' => true,
        'cuenta_provisional' => true,
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $user = User::query()->where('email', 'a.maria@seoane.edu.pe')->firstOrFail();
    expect($user->rol)->toBe('estudiante')
        ->and($user->activo)->toBeFalse()
        ->and($user->estado_cuenta)->toBe('pendiente')
        ->and($user->cuenta_provisional)->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->dni)->toBe('12345678')
        ->and($user->perfilEstudiante->programa_estudio_id)->toBe($program->id)
        ->and($user->perfilEstudiante->ciclo_actual)->toBe(6);
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ClaveNueva2026']);
    $this->assertGuest();
});

test('public registration rejects a seventh cycle', function () {
    $program = ProgramaEstudio::query()->where('activo', true)->firstOrFail();

    $this->post(route('register.store'), [
        'nombres' => 'María', 'apellidos' => 'Pérez', 'dni' => '12345679',
        'celular' => '987654321', 'email' => 'a.ciclo7@seoane.edu.pe',
        'programa_estudio_id' => $program->id, 'condicion_academica' => 'Estudiante',
        'ciclo_actual' => 7, 'acepta_terminos' => '1',
        'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026',
    ])->assertSessionHasErrors('ciclo_actual');

    expect(User::query()->where('email', 'a.ciclo7@seoane.edu.pe')->exists())->toBeFalse();
});

test('public registration rejects noninstitutional identity, inactive programs and missing consent', function () {
    Notification::fake();
    $inactiveProgram = ProgramaEstudio::factory()->create(['activo' => false]);
    $payload = [
        'nombres' => 'Lucía', 'apellidos' => 'Quispe', 'dni' => '87654321',
        'celular' => '987654321', 'email' => 'lucia@example.com',
        'programa_estudio_id' => $inactiveProgram->id,
        'condicion_academica' => 'Egresado', 'anio_egreso' => now()->year + 1,
        'password' => 'password', 'password_confirmation' => 'password',
    ];

    $this->post(route('register.store'), $payload)->assertSessionHasErrors([
        'email', 'programa_estudio_id', 'anio_egreso', 'acepta_terminos', 'password',
    ]);
    expect(User::query()->where('email', 'lucia@example.com')->exists())->toBeFalse();

    $payload['email'] = 'a.lucia@seoane.edu.pe';
    $payload['programa_estudio_id'] = ProgramaEstudio::query()->where('activo', true)->firstOrFail()->id;
    $payload['anio_egreso'] = now()->year;
    $payload['acepta_terminos'] = '1';
    $payload['password'] = 'ClaveNueva2026';
    $payload['password_confirmation'] = 'ClaveNueva2026';
    $this->post(route('register.store'), $payload)->assertRedirect(route('login'));
    expect(User::query()->where('email', $payload['email'])->firstOrFail()->perfilEstudiante->anio_egreso)->toBe(now()->year);
    $this->post(route('register.store'), $payload)->assertSessionHasErrors(['dni', 'email']);
});
