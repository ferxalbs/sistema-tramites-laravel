<?php

use App\Models\ProgramaEstudio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('only administrators can edit institutional positions', function () {
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();
    $payload = ['nombre' => 'Docente de oficina', 'descripcion' => 'Cargo actualizado', 'activo' => '1'];

    $this->get(route('admin.positions.index'))->assertRedirect(route('login'));
    $this->patch(route('admin.positions.update', $position->id), $payload)->assertRedirect(route('login'));

    foreach (['estudiante', 'docente'] as $role) {
        $this->actingAs(User::factory()->create(['rol' => $role]))
            ->get(route('admin.positions.index'))->assertForbidden();
        $this->patch(route('admin.positions.update', $position->id), $payload)->assertForbidden();
    }

    expect(DB::table('cargos_institucionales')->where('id', $position->id)->value('nombre'))->toBe('Docente')
        ->and(DB::table('tramite_config_events')->count())->toBe(0);
});

test('editing a position is audited and inactive positions cannot be requested', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $program = ProgramaEstudio::factory()->create(['activo' => true]);
    $position = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();

    $this->actingAs($administrator)->get(route('admin.positions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('cargos-institucionales')->has('cargos', 5));
    $this->patch(route('admin.positions.update', $position->id), [
        'nombre' => 'Docente institucional', 'descripcion' => 'Cargo sin privilegios de sistema', 'activo' => '0',
    ])->assertRedirect(route('admin.positions.index'));

    $saved = DB::table('cargos_institucionales')->where('id', $position->id)->first();
    expect($saved->codigo)->toBe('docente')
        ->and($saved->nombre)->toBe('Docente institucional')
        ->and($saved->descripcion)->toBe('Cargo sin privilegios de sistema')
        ->and((int) $saved->activo)->toBe(0)
        ->and(DB::table('tramite_config_events')->value('accion'))->toBe('edicion_catalogo_documental');
    $this->post(route('logout'));
    $this->get(route('teacher-access.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/teacher-access-request')->has('cargos', 2));
    $this->post(route('teacher-access.store'), [
        'nombres' => 'Elena', 'apellidos' => 'Quispe', 'dni' => '76543210', 'email' => 'elena@seoane.edu.pe',
        'programa_estudio_id' => $program->id, 'cargo_institucional_id' => $position->id,
        'motivo' => 'Solicitud de acceso institucional como docente.',
    ])->assertSessionHasErrors('cargo_institucional_id');
    expect(DB::table('teacher_access_requests')->count())->toBe(0);
});

test('invalid catalog edits do not change positions or audit', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $position = DB::table('cargos_institucionales')->where('codigo', 'otro')->first();
    $this->actingAs($administrator)->patch(route('admin.positions.update', $position->id), [
        'nombre' => 'A', 'descripcion' => str_repeat('x', 256), 'activo' => 'inventado',
    ])->assertSessionHasErrors(['nombre', 'descripcion', 'activo']);
    $this->patch(route('admin.positions.update', 99999), [
        'nombre' => 'Otro cargo', 'descripcion' => '', 'activo' => '1',
    ])->assertNotFound();

    expect(DB::table('cargos_institucionales')->where('id', $position->id)->value('nombre'))->toBe('Otro')
        ->and(DB::table('tramite_config_events')->count())->toBe(0);
});
