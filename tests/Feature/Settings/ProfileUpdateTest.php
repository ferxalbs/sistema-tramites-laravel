<?php

use App\Models\PerfilDocente;
use App\Models\PerfilEstudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('student sees validated identity and updates only contact details', function () {
    $student = User::factory()->create([
        'rol' => 'estudiante',
        'email' => 'a.estudiante@seoane.edu.pe',
        'dni' => '87654321',
    ]);
    $profile = PerfilEstudiante::factory()->create(['user_id' => $student->id, 'codigo_estudiante' => 'EST-123']);
    $originalName = $student->name;
    $originalEmailVerification = $student->email_verified_at;

    $this->actingAs($student)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('identity.email', $student->email)
            ->where('identity.dni', '87654321')
            ->where('profile.codigo', 'EST-123')
            ->missing('identity.password')
            ->etc());

    $this->patch(route('profile.update'), [
        'celular' => ' 987654321 ',
        'correo_alternativo' => '  NUEVO@EXAMPLE.COM ',
        'direccion_residencia' => '  Jr. Los Pinos 123 ',
        'name' => 'Nombre inyectado',
        'email' => 'a.inyectado@seoane.edu.pe',
        'dni' => '12345678',
        'rol' => 'administrador',
        'programa_estudio_id' => 999,
    ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->celular)->toBe('987654321')
        ->and($student->correo_alternativo)->toBe('nuevo@example.com')
        ->and($student->name)->toBe($originalName)
        ->and($student->email)->toBe('a.estudiante@seoane.edu.pe')
        ->and($student->dni)->toBe('87654321')
        ->and($student->rol)->toBe('estudiante')
        ->and($student->email_verified_at?->equalTo($originalEmailVerification))->toBeTrue()
        ->and($profile->fresh()->direccion_residencia)->toBe('Jr. Los Pinos 123')
        ->and($profile->fresh()->programa_estudio_id)->toBe($profile->programa_estudio_id);
    $event = DB::table('user_account_events')->sole();
    expect($event->accion)->toBe('edit_profile')
        ->and((int) $event->actor_id)->toBe($student->id)
        ->and($event->motivo)->toBeNull();
});

test('student contact validation rejects duplicates and malformed values without partial updates', function () {
    $student = User::factory()->create(['rol' => 'estudiante', 'celular' => '987654321']);
    $profile = PerfilEstudiante::factory()->create(['user_id' => $student->id]);
    $other = User::factory()->create(['email' => 'otra@seoane.edu.pe', 'correo_alternativo' => 'otro@example.com']);

    $payload = ['celular' => '900111222', 'correo_alternativo' => $other->email, 'direccion_residencia' => 'Cambio indebido'];
    $this->actingAs($student)->patch(route('profile.update'), $payload)->assertSessionHasErrors('correo_alternativo');
    $this->patch(route('profile.update'), [...$payload, 'correo_alternativo' => $other->correo_alternativo])
        ->assertSessionHasErrors('correo_alternativo');
    $this->patch(route('profile.update'), [...$payload, 'correo_alternativo' => $student->email])
        ->assertSessionHasErrors('correo_alternativo');
    $this->patch(route('profile.update'), [...$payload, 'correo_alternativo' => 'libre@example.com', 'celular' => 'abc'])
        ->assertSessionHasErrors('celular');
    $this->patch(route('profile.update'), [...$payload, 'correo_alternativo' => 'libre@example.com', 'direccion_residencia' => str_repeat('X', 256)])
        ->assertSessionHasErrors('direccion_residencia');
    $this->patch(route('profile.update'), [...$payload, 'celular' => ['900111222'], 'correo_alternativo' => ['otro@example.com']])
        ->assertSessionHasErrors(['celular', 'correo_alternativo']);

    expect($student->fresh()->celular)->toBe('987654321')
        ->and($profile->fresh()->direccion_residencia)->toBeNull()
        ->and(DB::table('user_account_events')->count())->toBe(0);
});

test('profile update rolls back when the academic profile is missing', function () {
    $student = User::factory()->create(['rol' => 'estudiante', 'celular' => '987654321']);

    $this->actingAs($student)->patch(route('profile.update'), [
        'celular' => '900111222',
        'correo_alternativo' => 'otro@example.com',
        'direccion_residencia' => 'Nueva dirección',
    ])->assertNotFound();

    expect($student->fresh()->celular)->toBe('987654321')
        ->and($student->fresh()->correo_alternativo)->toBeNull()
        ->and(DB::table('user_account_events')->count())->toBe(0);
});

test('teacher changes professional contacts without changing institutional identity', function () {
    $teacher = User::factory()->create(['rol' => 'docente', 'email' => 'docente@seoane.edu.pe']);
    $profile = PerfilDocente::factory()->create(['user_id' => $teacher->id, 'codigo_docente' => 'DOC-1']);

    $this->actingAs($teacher)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('identity.rol', 'docente')
            ->where('profile.codigo', 'DOC-1')
            ->etc());
    $this->patch(route('profile.update'), [
        'celular' => '+51 987 654 321',
        'especialidad' => '  Matemática ',
        'condicion_laboral' => '  Nombrado ',
        'name' => 'Docente ajeno',
        'email' => 'otro@seoane.edu.pe',
    ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

    expect($teacher->fresh()->celular)->toBe('+51 987 654 321')
        ->and($teacher->email)->toBe('docente@seoane.edu.pe')
        ->and($profile->fresh()->especialidad)->toBe('Matemática')
        ->and($profile->fresh()->condicion_laboral)->toBe('Nombrado')
        ->and(DB::table('user_account_events')->where('accion', 'edit_profile')->count())->toBe(1);
    $this->patch(route('profile.update'), ['celular' => '987654321', 'especialidad' => str_repeat('X', 161), 'condicion_laboral' => 'Contratado'])
        ->assertSessionHasErrors('especialidad');
});

test('assistant and administrator cannot self-edit or delete institutional accounts', function () {
    foreach (['asistente', 'administrador'] as $role) {
        $user = User::factory()->create(['rol' => $role]);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('identity.rol', $role)
                ->where('identity.cuenta_provisional', false)
                ->where('profile', null)
                ->etc());
        $this->patch(route('profile.update'), ['celular' => '987654321'])->assertForbidden();
        $this->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
        expect($user->fresh())->not->toBeNull();
    }
    $student = User::factory()->create(['rol' => 'estudiante']);
    $this->actingAs($student)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
    expect($student->fresh())->not->toBeNull();
    $legacyAssistant = User::factory()->create(['rol' => 'asistente', 'cuenta_provisional' => null]);
    $this->actingAs($legacyAssistant)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('identity.cuenta_provisional', null)->etc());
    expect(DB::table('user_account_events')->count())->toBe(0);
});
