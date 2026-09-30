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

    $this->actingAs($administrator)->get(route('admin.users.index', ['estado' => 'pendiente']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios')
        ->has('users.data', 1)
        ->where('users.data.0.id', $pending->id)
        ->where('counts.pendiente', 1));
    $this->get(route('admin.users.index', ['estado' => 'arbitrario']))->assertSessionHasErrors('estado');
});

test('approving and rejecting accounts creates private account notices without an expediente link', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $approved = User::factory()->create(['rol' => 'docente', 'activo' => false, 'estado_cuenta' => 'pendiente']);
    $rejected = User::factory()->create(['rol' => 'estudiante', 'activo' => false, 'estado_cuenta' => 'pendiente']);

    $this->actingAs($administrator)->patch(route('admin.users.update', $approved), ['accion' => 'activate'])->assertRedirect();
    $this->patch(route('admin.users.update', $rejected), ['accion' => 'reject', 'motivo' => 'Datos incompletos'])->assertRedirect();

    $notices = DB::table('tramite_notificaciones')->orderBy('id')->get();
    expect($notices)->toHaveCount(2)
        ->and($notices[0]->usuario_id)->toBe($approved->id)
        ->and($notices[0]->tipo)->toBe('cuenta_aprobada')
        ->and($notices[0]->tramite_id)->toBeNull()
        ->and($notices[0]->account_event_id)->not->toBeNull()
        ->and($notices[1]->usuario_id)->toBe($rejected->id)
        ->and($notices[1]->tipo)->toBe('cuenta_rechazada')
        ->and($notices[1]->mensaje)->not->toContain('Datos incompletos');

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $approved->email, 'password' => 'password'])->assertRedirect();
    $this->get(route('notificaciones.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('notificaciones')
            ->where('notifications.total', 1)
            ->where('notifications.data.0.url', null)
            ->where('notificationUnreadCount', 1));
    $this->patch(route('notificaciones.read', $notices[1]->id))->assertNotFound();
    $this->patch(route('notificaciones.read', $notices[0]->id))->assertRedirect(route('notificaciones.index'));
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
    expect(DB::table('user_account_events')->where('user_id', $pending->id)->count())->toBe(2)
        ->and(DB::table('user_account_events')->where('user_id', $otherPending->id)->count())->toBe(1);
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

test('assistant creates and edits only student accounts without changing roles or account states', function () {
    $program = ProgramaEstudio::factory()->create();
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $payload = adminUserPayload('estudiante', $program->id, 72);

    $this->get(route('assistant.students.index'))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('assistant.students.index'))->assertForbidden();
    $this->actingAs($assistant)->get(route('assistant.students.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios-form')->where('mode', 'assistant')->has('programas', 3));
    $this->post(route('assistant.students.store'), [
        ...$payload, 'rol' => 'administrador', 'estado_cuenta' => 'rechazado', 'activar_inmediatamente' => '0', 'cuenta_provisional' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $created = User::query()->where('email', $payload['email'])->firstOrFail();
    expect($created->rol)->toBe('estudiante')
        ->and($created->estado_cuenta)->toBe('activo')
        ->and($created->activo)->toBeTrue()
        ->and($created->debe_cambiar_password)->toBeTrue()
        ->and($created->cuenta_provisional)->toBeTrue()
        ->and($created->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check($payload['password'], $created->password))->toBeTrue()
        ->and($created->perfilEstudiante?->codigo_estudiante)->toBe($payload['dni'])
        ->and($created->perfilEstudiante?->programa_estudio_id)->toBe($program->id);

    $this->get(route('assistant.students.index', ['q' => $payload['dni'], 'programa' => $program->id, 'condicion' => 'Estudiante', 'estado' => 'activo']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('estudiantes')->has('students.data', 1)
            ->where('students.data.0.id', $created->id)
            ->where('students.data.0.dni', $payload['dni'])
            ->missing('students.data.0.password'));
    $this->get(route('assistant.students.edit', $teacher))->assertNotFound();
    $this->put(route('assistant.students.save', $teacher), $payload)->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();

    $this->put(route('assistant.students.save', $created), [
        ...$payload,
        'rol' => 'administrador',
        'estado_cuenta' => 'inactivo',
        'activo' => '0',
        'cuenta_provisional' => '0',
        'apellidos' => 'Ramos Chávez',
        'condicion_academica' => 'Egresado',
        'anio_egreso' => now()->year,
    ])->assertSessionHasNoErrors()->assertRedirect(route('assistant.students.index'));
    expect($created->fresh()->rol)->toBe('estudiante')
        ->and($created->fresh()->estado_cuenta)->toBe('activo')
        ->and($created->fresh()->cuenta_provisional)->toBeTrue()
        ->and($created->fresh()->apellidos)->toBe('Ramos Chávez')
        ->and($created->fresh()->perfilEstudiante?->condicion_academica)->toBe('Egresado')
        ->and($created->fresh()->perfilEstudiante?->anio_egreso)->toBe(now()->year)
        ->and(DB::table('user_account_events')->where('user_id', $created->id)->pluck('accion')->all())->toBe(['create', 'edit']);
});

test('administrator creates every source role with the matching profile and temporary password', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();
    $this->actingAs($administrator)->get(route('admin.users.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios-form')->has('programas', 3)->has('cargos', 5));

    foreach (['estudiante', 'docente', 'asistente', 'administrador'] as $index => $role) {
        $payload = adminUserPayload($role, $program->id, $index + 1);
        if ($role !== 'administrador') {
            unset($payload['confirmar_administrador']);
        }
        $this->post(route('admin.users.store'), [...$payload, 'cuenta_provisional' => '1', 'cargo_institucional_id' => $position->id])->assertSessionHasNoErrors()
            ->assertRedirect();
        $created = User::query()->where('email', $payload['email'])->firstOrFail();

        expect($created->rol)->toBe($role)
            ->and($created->activo)->toBeTrue()
            ->and($created->hasVerifiedEmail())->toBeTrue()
            ->and($created->debe_cambiar_password)->toBeTrue()
            ->and($created->cuenta_provisional)->toBeFalse()
            ->and($created->cargo_institucional_id)->toBe($role === 'estudiante' ? null : $position->id)
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
        ->assertSessionHasErrors(['email', 'programa_estudio_id']);

    $payload = adminUserPayload('estudiante', $activeProgram->id, 77);
    $payload['ciclo_actual'] = 7;
    $this->post(route('admin.users.store'), $payload)->assertSessionHasErrors('ciclo_actual');
    expect(User::query()->where('email', $payload['email'])->exists())->toBeFalse();

    $payload = adminUserPayload('asistente', $activeProgram->id, 2);
    $inactivePosition = DB::table('cargos_institucionales')->where('codigo', 'otro')->value('id');
    DB::table('cargos_institucionales')->where('id', $inactivePosition)->update(['activo' => false]);
    $this->post(route('admin.users.store'), [...$payload, 'cargo_institucional_id' => $inactivePosition])
        ->assertSessionHasErrors('cargo_institucional_id');
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
    unset($payload['confirmar_administrador']);
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();
    $payload['cargo_institucional_id'] = $position->id;

    $this->actingAs($administrator)->get(route('admin.users.edit', $student))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('usuarios-form')->where('user.id', $student->id)
        ->where('user.dni', $student->dni));
    $this->put(route('admin.users.save', $student), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $student->refresh();
    expect($student->rol)->toBe('docente')
        ->and($student->name)->toBe('María Pérez Ramos')
        ->and($student->sesion_version)->toBe(1)
        ->and($student->perfilEstudiante)->toBeNull()
        ->and($student->perfilDocente?->codigo_docente)->toBe('DOC3AA')
        ->and($student->cargo_institucional_id)->toBe($position->id)
        ->and(DB::table('sessions')->where('user_id', $student->id)->exists())->toBeFalse()
        ->and(DB::table('user_account_events')->where('user_id', $student->id)->count())->toBe(2);
});

test('an existing inactive position can be retained but cannot be assigned to another account', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $positionId = DB::table('cargos_institucionales')->where('codigo', 'otro')->value('id');
    $assigned = User::factory()->create(['rol' => 'asistente', 'cargo_institucional_id' => $positionId]);
    $other = User::factory()->create(['rol' => 'asistente']);
    DB::table('cargos_institucionales')->where('id', $positionId)->update(['activo' => false]);

    $this->actingAs($administrator)->get(route('admin.users.edit', $assigned))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('user.cargo_institucional_id', $positionId)
            ->where('cargos', fn ($positions): bool => collect($positions)->contains('id', $positionId)));
    $this->put(route('admin.users.save', $assigned), [
        ...adminUserPayload('asistente', $program->id, 42), 'cargo_institucional_id' => $positionId,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($assigned->fresh()->cargo_institucional_id)->toBe($positionId);

    $this->put(route('admin.users.save', $other), [
        ...adminUserPayload('asistente', $program->id, 43), 'cargo_institucional_id' => $positionId,
    ])->assertSessionHasErrors('cargo_institucional_id');
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
    unset($payload['confirmar_administrador']);
    $payload['condicion_academica'] = 'Egresado';
    $payload['anio_egreso'] = 2025;

    $this->actingAs($administrator)->put(route('admin.users.save', $student), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    $student->refresh();
    expect($student->rol)->toBe('estudiante')
        ->and($student->sesion_version)->toBe(0)
        ->and($student->perfilEstudiante?->codigo_estudiante)->toBe('80000007')
        ->and($student->perfilEstudiante?->condicion_academica)->toBe('Egresado')
        ->and($student->perfilEstudiante?->ciclo_actual)->toBeNull()
        ->and($student->perfilEstudiante?->anio_egreso)->toBe(2025)
        ->and(DB::table('user_account_events')->where('user_id', $student->id)->count())->toBe(1);
});

test('editing an administrator does not require a new role confirmation', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create();
    $payload = adminUserPayload('administrador', $program->id, 90);
    unset($payload['confirmar_administrador']);

    $this->actingAs($administrator)->put(route('admin.users.save', $administrator), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($administrator->fresh()->name)->toBe('María Pérez Ramos')
        ->and($administrator->fresh()->rol)->toBe('administrador');
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
