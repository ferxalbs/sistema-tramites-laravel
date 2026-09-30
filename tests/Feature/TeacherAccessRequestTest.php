<?php

use App\Models\ProgramaEstudio;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('public teacher request is pending, keeps the teacher role, and awaits verification and approval', function () {
    Notification::fake();
    $program = ProgramaEstudio::factory()->create(['activo' => true]);
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();

    $this->get(route('teacher-access.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/teacher-access-request')
            ->has('programas', 3)->has('cargos', 3));

    $this->post(route('teacher-access.store'), [
        'nombres' => 'Elena',
        'apellidos' => 'Quispe Soto',
        'email' => 'Docente@seoane.edu.pe',
        'programa_estudio_id' => $program->id,
        'cargo_institucional_id' => $position->id,
        'motivo' => 'Solicito acceso para revisar expedientes asignados.',
        'rol' => 'administrador',
        'activo' => '1',
        'cuenta_provisional' => '0',
    ])->assertRedirect(route('teacher-access.create'));

    $user = User::query()->where('email', 'docente@seoane.edu.pe')->firstOrFail();
    expect($user->rol)->toBe('docente')
        ->and($user->activo)->toBeFalse()
        ->and($user->estado_cuenta)->toBe('pendiente')
        ->and($user->debe_cambiar_password)->toBeTrue()
        ->and($user->cuenta_provisional)->toBeTrue()
        ->and($user->cargo_institucional_id)->toBe($position->id)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->perfilDocente->programa_estudio_id)->toBe($program->id)
        ->and(DB::table('teacher_access_requests')->where('user_id', $user->id)->value('motivo'))
        ->toBe('Solicito acceso para revisar expedientes asignados.');
    Notification::assertSentTo($user, VerifyEmail::class, 1);
    $this->get(route('teacher-access.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('status', 'Solicitud recibida. Verifique su correo institucional; la aprobación administrativa continuará siendo necesaria.'));
    $verificationUrl = Notification::sent($user, VerifyEmail::class)->first()->toMail($user->fresh())->actionUrl;
    $this->get($verificationUrl)->assertRedirect(route('login'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->estado_cuenta)->toBe('pendiente');

    $administrator = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($administrator)->get(route('admin.users.edit', $user))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('user.teacher_request.cargo', 'Docente')
            ->where('user.teacher_request.motivo', 'Solicito acceso para revisar expedientes asignados.'));
    $this->patch(route('admin.users.update', $user), ['accion' => 'activate'])->assertRedirect();
    expect($user->fresh()->estado_cuenta)->toBe('activo')
        ->and($user->fresh()->cuenta_provisional)->toBeTrue();
});

test('teacher request rejects noninstitutional mail, inactive programs, unrelated roles and invalid positions', function () {
    Notification::fake();
    $program = ProgramaEstudio::factory()->create(['activo' => true]);
    $inactiveProgram = ProgramaEstudio::factory()->create(['activo' => false]);
    $director = DB::table('cargos_institucionales')->where('codigo', 'director_general')->first();
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();
    $payload = [
        'nombres' => 'Elena', 'apellidos' => 'Quispe',
        'email' => 'elena@example.com', 'programa_estudio_id' => $inactiveProgram->id,
        'cargo_institucional_id' => $director->id, 'motivo' => 'Corto',
    ];

    $this->post(route('teacher-access.store'), $payload)
        ->assertSessionHasErrors(['email', 'programa_estudio_id', 'cargo_institucional_id', 'motivo']);
    expect(User::query()->count())->toBe(0);

    $payload['email'] = 'elena@seoane.edu.pe';
    $payload['programa_estudio_id'] = $program->id;
    $payload['cargo_institucional_id'] = $position->id;
    $payload['motivo'] = 'Necesito acceso docente para revisar expedientes.';
    $this->post(route('teacher-access.store'), $payload)->assertRedirect(route('teacher-access.create'));
    $this->post(route('teacher-access.store'), $payload)->assertSessionHasErrors('email');
    expect(User::query()->count())->toBe(1);
    Notification::assertSentTo(User::query()->firstOrFail(), VerifyEmail::class, 1);
});

test('authenticated accounts cannot submit a public teacher request', function () {
    $user = User::factory()->create(['rol' => 'estudiante']);
    $this->actingAs($user)->get(route('teacher-access.create'))->assertRedirect(route('dashboard'));
    $this->post(route('teacher-access.store'), [])->assertRedirect(route('dashboard'));
    expect(DB::table('teacher_access_requests')->count())->toBe(0);
});
