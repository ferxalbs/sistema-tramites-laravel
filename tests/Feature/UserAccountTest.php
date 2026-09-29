<?php

use App\Models\ProgramaEstudio;
use App\Models\TramiteAsignacion;
use App\Models\User;
use App\Notifications\AdministrativeResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function adminUserPayload(string $role, int $programId, int $sequence = 1): array
{
    return [
        'rol' => $role,
        'nombres' => 'María',
        'apellidos' => 'Pérez Ramos',
        'dni' => str_pad((string) (80000000 + $sequence), 8, '0', STR_PAD_LEFT),
        'celular' => '987654321',
        'email' => ($role === 'estudiante' ? 'a.' : '')."usuario{$sequence}@seoane.edu.pe",
        'correo_alternativo' => "alternativo{$sequence}@example.com",
        'programa_estudio_id' => $programId,
        'codigo_estudiante' => "EST{$sequence}AA",
        'condicion_academica' => 'Estudiante',
        'ciclo_actual' => 4,
        'codigo_docente' => "DOC{$sequence}AA",
        'especialidad' => 'Gestión documental',
        'condicion_laboral' => 'Contratado',
        'password' => 'Temporal2026A',
        'password_confirmation' => 'Temporal2026A',
        'activar_inmediatamente' => '1',
        'confirmar_administrador' => '1',
    ];
}

test('only administrators can list and change account states', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $pending = User::factory()->create(['activo' => false, 'estado_cuenta' => 'pendiente']);

    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    foreach (['estudiante', 'asistente', 'docente'] as $role) {
        $viewer = User::factory()->create(['rol' => $role]);
        $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
        $this->patch(route('admin.users.update', $pending), ['accion' => 'activate'])->assertForbidden();
    }

    $this->actingAs($administrator)->get(route('admin.users.index', ['estado' => 'pendiente', 'selected' => $pending->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios')
        ->has('users.data', 1)
        ->where('users.data.0.id', $pending->id)
        ->where('selected.id', $pending->id)
        ->where('counts.pendiente', 1));
    $this->get(route('admin.users.index', ['estado' => 'arbitrario']))->assertSessionHasErrors('estado');
});

test('activation, deactivation and rejection are audited and revoke stored sessions', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $pending = User::factory()->create(['activo' => false, 'estado_cuenta' => 'pendiente', 'email_verified_at' => null]);
    $otherPending = User::factory()->create(['activo' => false, 'estado_cuenta' => 'pendiente']);
    $originalToken = $pending->remember_token;
    DB::table('sessions')->insert([
        'id' => 'pending-session',
        'user_id' => $pending->id,
        'payload' => 'test',
        'last_activity' => time(),
    ]);

    $this->actingAs($administrator)->patch(route('admin.users.update', $pending), ['accion' => 'activate'])
        ->assertRedirect();
    $pending->refresh();
    expect($pending->activo)->toBeTrue()
        ->and($pending->estado_cuenta)->toBe('activo')
        ->and($pending->email_verified_at)->not->toBeNull()
        ->and($pending->sesion_version)->toBe(1)
        ->and($pending->remember_token)->not->toBe($originalToken);
    expect(DB::table('sessions')->where('user_id', $pending->id)->exists())->toBeFalse();

    $this->patch(route('admin.users.update', $pending), ['accion' => 'deactivate', 'motivo' => 'Cuenta suspendida por el administrador.'])
        ->assertRedirect();
    $pending->refresh();
    expect($pending->activo)->toBeFalse()
        ->and($pending->estado_cuenta)->toBe('inactivo')
        ->and($pending->sesion_version)->toBe(2)
        ->and($pending->motivo_inactivacion)->toBe('Cuenta suspendida por el administrador.');

    $this->patch(route('admin.users.update', $otherPending), ['accion' => 'reject', 'motivo' => 'Solicitud institucional no válida.'])
        ->assertRedirect();
    expect($otherPending->fresh()->estado_cuenta)->toBe('rechazado');
    $this->get(route('admin.users.index', ['selected' => $pending->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('selected.events', 2)
        ->where('selected.events.0.estado_nuevo', 'inactivo')
        ->where('selected.events.0.actor', $administrator->name));
    expect(DB::table('user_account_events')->where('user_id', $otherPending->id)->count())->toBe(1);
});

test('invalid transitions and the last administrator are rejected without an audit entry', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $otherAdministrator = User::factory()->create(['rol' => 'administrador']);
    $pending = User::factory()->create(['activo' => false, 'estado_cuenta' => 'pendiente']);

    $this->actingAs($administrator)->patch(route('admin.users.update', $administrator), [
        'accion' => 'deactivate', 'motivo' => 'Intento propio.',
    ])->assertSessionHasErrors('accion');
    $this->patch(route('admin.users.update', $pending), ['accion' => 'reject', 'motivo' => '   '])
        ->assertSessionHasErrors('motivo');
    $this->patch(route('admin.users.update', $pending), ['accion' => 'deactivate', 'motivo' => 'No corresponde.'])
        ->assertSessionHasErrors('accion');
    $this->patch(route('admin.users.update', $pending), ['accion' => 'delete', 'motivo' => 'No corresponde.'])
        ->assertSessionHasErrors('accion');
    expect(DB::table('user_account_events')->count())->toBe(0);

    $this->patch(route('admin.users.update', $otherAdministrator), [
        'accion' => 'deactivate', 'motivo' => 'Cambio de responsabilidades.',
    ])->assertRedirect();
    $this->actingAs($otherAdministrator)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($administrator)->patch(route('admin.users.update', $otherAdministrator), [
        'accion' => 'deactivate', 'motivo' => 'Repetición.',
    ])->assertSessionHasErrors('accion');
    expect(User::query()->where('rol', 'administrador')->where('activo', true)->count())->toBe(1);
});

test('inactive accounts cannot login or use an old session after reactivation', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $account = User::factory()->create(['rol' => 'docente']);

    $this->actingAs($account)->get(route('dashboard'))->assertOk();
    $this->actingAs($administrator)->patch(route('admin.users.update', $account), [
        'accion' => 'deactivate', 'motivo' => 'Acceso retirado por seguridad.',
    ])->assertRedirect();
    $this->actingAs($account)->get(route('profile.edit'))->assertForbidden();
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password']);
    $this->assertGuest();

    $this->actingAs($administrator)->patch(route('admin.users.update', $account), ['accion' => 'activate'])
        ->assertRedirect();
    $this->withSession(['account_session_version' => 0])->actingAs($account)->get(route('dashboard'))->assertForbidden();
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($account);
});

test('only administrators can create or edit accounts through server routes', function () {
    $program = ProgramaEstudio::factory()->create();
    $target = User::factory()->create();
    $payload = adminUserPayload('estudiante', $program->id);

    $this->get(route('admin.users.create'))->assertRedirect(route('login'));
    $this->post(route('admin.users.store'), $payload)->assertRedirect(route('login'));

    foreach (['estudiante', 'asistente', 'docente'] as $role) {
        $viewer = User::factory()->create(['rol' => $role]);
        $this->actingAs($viewer)->get(route('admin.users.create'))->assertForbidden();
        $this->get(route('admin.users.edit', $target))->assertForbidden();
        $this->post(route('admin.users.store'), $payload)->assertForbidden();
        $this->put(route('admin.users.save', $target), $payload)->assertForbidden();
    }

    expect(User::query()->where('email', $payload['email'])->exists())->toBeFalse();
});

test('administrator creates every source role with the matching profile and temporary password', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $this->actingAs($administrator)->get(route('admin.users.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios-form')->has('programas', 1));

    foreach (['estudiante', 'docente', 'asistente', 'administrador'] as $index => $role) {
        $payload = adminUserPayload($role, $program->id, $index + 1);
        $this->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors()
            ->assertRedirect();
        $created = User::query()->where('email', $payload['email'])->firstOrFail();

        expect($created->rol)->toBe($role)
            ->and($created->activo)->toBeTrue()
            ->and($created->hasVerifiedEmail())->toBeTrue()
            ->and($created->debe_cambiar_password)->toBeTrue()
            ->and(Hash::check('Temporal2026A', $created->password))->toBeTrue()
            ->and(DB::table('user_account_events')->where('user_id', $created->id)->where('accion', 'create')->exists())->toBeTrue();
        expect($created->perfilEstudiante !== null)->toBe($role === 'estudiante')
            ->and($created->perfilDocente !== null)->toBe($role === 'docente');
    }
});

test('administrator creation validates confirmation, identity, program and inactive state', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $activeProgram = ProgramaEstudio::factory()->create();
    $inactiveProgram = ProgramaEstudio::factory()->create(['activo' => false]);
    $payload = adminUserPayload('administrador', $activeProgram->id);
    unset($payload['confirmar_administrador']);

    $this->actingAs($administrator)->post(route('admin.users.store'), $payload)
        ->assertSessionHasErrors('confirmar_administrador');

    $payload = adminUserPayload('estudiante', $inactiveProgram->id);
    $payload['email'] = 'correo@example.com';
    $payload['codigo_estudiante'] = 'X';
    $this->post(route('admin.users.store'), $payload)
        ->assertSessionHasErrors(['email', 'codigo_estudiante', 'programa_estudio_id']);

    $payload = adminUserPayload('asistente', $activeProgram->id, 2);
    unset($payload['activar_inmediatamente']);
    $this->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();
    $created = User::query()->where('email', $payload['email'])->firstOrFail();
    expect($created->estado_cuenta)->toBe('pendiente')
        ->and($created->activo)->toBeFalse()
        ->and($created->hasVerifiedEmail())->toBeTrue();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $created->email, 'password' => 'Temporal2026A']);
    $this->assertGuest();
});

test('editing an account changes role and profile and revokes prior sessions', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $student = User::factory()->create(['rol' => 'estudiante', 'email' => 'a.editado@seoane.edu.pe']);
    $student->perfilEstudiante()->create([
        'programa_estudio_id' => $program->id,
        'codigo_estudiante' => 'ESTORIG',
        'condicion_academica' => 'Estudiante',
        'ciclo_actual' => 3,
    ]);
    DB::table('sessions')->insert(['id' => 'student-old-session', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
    $payload = adminUserPayload('docente', $program->id, 3);

    $this->actingAs($administrator)->get(route('admin.users.edit', $student))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios-form')->where('user.id', $student->id)
        ->where('user.codigo_estudiante', 'ESTORIG'));
    $this->put(route('admin.users.save', $student), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $student->refresh();
    expect($student->rol)->toBe('docente')
        ->and($student->name)->toBe('María Pérez Ramos')
        ->and($student->sesion_version)->toBe(1)
        ->and($student->perfilEstudiante)->toBeNull()
        ->and($student->perfilDocente?->codigo_docente)->toBe('DOC3AA')
        ->and(DB::table('sessions')->where('user_id', $student->id)->exists())->toBeFalse()
        ->and(DB::table('user_account_events')->where('user_id', $student->id)->count())->toBe(2);
});

test('editing a student keeps its own unique identity and updates its profile', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $student = User::factory()->create([
        'rol' => 'estudiante',
        'email' => 'a.usuario7@seoane.edu.pe',
        'dni' => '80000007',
        'correo_alternativo' => 'alternativo7@example.com',
    ]);
    $student->perfilEstudiante()->create([
        'programa_estudio_id' => $program->id,
        'codigo_estudiante' => 'EST7AA',
        'condicion_academica' => 'Estudiante',
        'ciclo_actual' => 3,
    ]);
    $payload = adminUserPayload('estudiante', $program->id, 7);
    $payload['condicion_academica'] = 'Egresado';
    $payload['anio_egreso'] = 2025;

    $this->actingAs($administrator)->put(route('admin.users.save', $student), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $student->refresh();
    expect($student->rol)->toBe('estudiante')
        ->and($student->sesion_version)->toBe(0)
        ->and($student->perfilEstudiante?->codigo_estudiante)->toBe('EST7AA')
        ->and($student->perfilEstudiante?->condicion_academica)->toBe('Egresado')
        ->and($student->perfilEstudiante?->ciclo_actual)->toBeNull()
        ->and($student->perfilEstudiante?->anio_egreso)->toBe(2025)
        ->and(DB::table('user_account_events')->where('user_id', $student->id)->count())->toBe(1);
});

test('promoting an account to administrator requires explicit confirmation', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $target = User::factory()->create(['rol' => 'asistente']);
    $program = ProgramaEstudio::factory()->create();
    $payload = adminUserPayload('administrador', $program->id, 8);
    unset($payload['confirmar_administrador']);

    $this->actingAs($administrator)->put(route('admin.users.save', $target), $payload)
        ->assertSessionHasErrors('confirmar_administrador');
    expect($target->fresh()->rol)->toBe('asistente');

    $payload['confirmar_administrador'] = '1';
    $this->put(route('admin.users.save', $target), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($target->fresh()->rol)->toBe('administrador')
        ->and($target->fresh()->sesion_version)->toBe(1);
});

test('self role changes and changes with active assignments are rejected', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $program = ProgramaEstudio::factory()->create();
    $this->actingAs($administrator)->put(route('admin.users.save', $administrator), adminUserPayload('asistente', $program->id, 4))
        ->assertSessionHasErrors('rol');

    TramiteAsignacion::factory()->create(['revisor_id' => $teacher->id]);
    $this->put(route('admin.users.save', $teacher), adminUserPayload('asistente', $program->id, 5))
        ->assertSessionHasErrors('rol');

    expect($administrator->fresh()->rol)->toBe('administrador')
        ->and($teacher->fresh()->rol)->toBe('docente')
        ->and(DB::table('user_account_events')->count())->toBe(0);
});

test('temporary password must be changed before accessing the application', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $payload = adminUserPayload('asistente', $program->id, 6);
    $this->actingAs($administrator)->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();
    $created = User::query()->where('email', $payload['email'])->firstOrFail();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $created->email, 'password' => 'Temporal2026A'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get(route('dashboard'))->assertRedirect(route('password.change-required'));
    $this->get(route('profile.edit'))->assertRedirect(route('password.change-required'));
    $this->get(route('password.change-required'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/change-required-password'));

    $this->put(route('user-password.update'), [
        'current_password' => 'Temporal2026A',
        'password' => 'Temporal2026A',
        'password_confirmation' => 'Temporal2026A',
    ])->assertSessionHasErrors('password');
    expect($created->fresh()->debe_cambiar_password)->toBeTrue();

    $this->put(route('user-password.update'), [
        'current_password' => 'Temporal2026A',
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($created->fresh()->debe_cambiar_password)->toBeFalse()
        ->and($created->fresh()->sesion_version)->toBe(1);
    $this->get(route('dashboard'))->assertOk();
});

test('only administrators can send an administrative reset link to an active institutional account', function () {
    Notification::fake();
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente', 'email' => 'docente.enlace@seoane.edu.pe']);
    $inactive = User::factory()->create([
        'rol' => 'docente',
        'email' => 'docente.inactivo@seoane.edu.pe',
        'activo' => false,
        'estado_cuenta' => 'inactivo',
    ]);

    $this->post(route('admin.users.reset-password', $teacher))->assertRedirect(route('login'));
    $this->actingAs($teacher)->post(route('admin.users.reset-password', $administrator))->assertForbidden();
    $this->actingAs($administrator)->post(route('admin.users.reset-password', $inactive))
        ->assertSessionHasErrors('reset');
    Notification::assertNothingSent();

    config()->set('app.url', 'https://tramites.seoane.edu.pe');
    $this->withServerVariables(['HTTP_HOST' => 'attacker.invalid'])
        ->post(route('admin.users.reset-password', $teacher))
        ->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');
    Notification::assertSentTo($teacher, AdministrativeResetPassword::class);
    $notification = Notification::sent($teacher, AdministrativeResetPassword::class)->first();
    $url = $notification->toMail($teacher)->actionUrl;
    expect($url)->toStartWith('https://tramites.seoane.edu.pe/')
        ->not->toContain('attacker.invalid');

    $audit = DB::table('user_account_events')->where('user_id', $teacher->id)->first();
    expect($audit->accion)->toBe('reset_link')
        ->and($audit->actor_id)->toBe($administrator->id)
        ->and($audit->motivo)->not->toContain($notification->token);
    expect(DB::table('password_reset_tokens')->where('email', $teacher->email)->exists())->toBeTrue();

    $this->post(route('logout'));
    $this->post(route('password.update'), [
        'token' => $notification->token,
        'email' => $teacher->email,
        'password' => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    expect(Hash::check('NuevaClave2026', $teacher->fresh()->password))->toBeTrue();
});

test('administrative reset reports delivery failure without disclosing the token', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente', 'email' => 'docente.fallo@seoane.edu.pe']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('transport unavailable'));

    $this->actingAs($administrator)->post(route('admin.users.reset-password', $teacher))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $audit = DB::table('user_account_events')->where('user_id', $teacher->id)->first();
    expect($audit->accion)->toBe('reset_link')
        ->and($audit->motivo)->toBe('El correo no pudo enviarse.')
        ->and(DB::table('password_reset_tokens')->where('email', $teacher->email)->exists())->toBeTrue();
});
