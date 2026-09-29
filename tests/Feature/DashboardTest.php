<?php

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteEvento;
use App\Models\TramiteMedioEntrega;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard scopes student metrics and activity to owned expedientes without internal notes', function () {
    $student = User::factory()->create(['rol' => 'estudiante']);
    $other = User::factory()->create(['rol' => 'estudiante']);
    $own = Tramite::factory()->create(['codigo' => 'TRM-OWN-001', 'propietario_id' => $student->id, 'estado' => 'observado']);
    $foreign = Tramite::factory()->create(['codigo' => 'TRM-FOREIGN-001', 'propietario_id' => $other->id, 'estado' => 'cerrado']);
    TramiteEvento::query()->create([
        'tramite_id' => $own->id,
        'usuario_id' => $other->id,
        'accion' => 'observacion',
        'descripcion' => 'Comentario interno que no debe publicarse.',
        'estado_nuevo' => 'observado',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $foreign->id,
        'usuario_id' => $other->id,
        'accion' => 'expediente_cerrado',
        'descripcion' => 'Actividad ajena.',
        'estado_nuevo' => 'cerrado',
    ]);

    $response = $this->actingAs($student)->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('role', 'estudiante')
        ->where('summary.total', 1)
        ->where('summary.cerrados', 0)
        ->where('states.0.codigo', 'observado')
        ->has('activity', 1)
        ->where('activity.0.codigo', 'TRM-OWN-001')
        ->where('activity.0.title', 'Observado')
        ->where('adminCharts', null)
        ->missing('activity.0.usuario_id')
        ->missing('activity.0.descripcion'));
    expect($response->getContent())->not->toContain('TRM-FOREIGN-001', 'Comentario interno que no debe publicarse.', 'Actividad ajena.');

    $student->forceFill(['activo' => false])->save();
    $this->actingAs($student)->get(route('dashboard'))->assertForbidden();
});

test('dashboard limits teacher metrics to assigned expedientes and shows staff totals', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $otherTeacher = User::factory()->create(['rol' => 'docente']);
    $active = Tramite::factory()->create(['codigo' => 'TRM-ACTIVE-001', 'estado' => 'asignado']);
    $historical = Tramite::factory()->create(['codigo' => 'TRM-HISTORY-001', 'estado' => 'cerrado']);
    $foreign = Tramite::factory()->create(['codigo' => 'TRM-OTHER-001', 'estado' => 'digitalizado']);
    TramiteAsignacion::factory()->create(['tramite_id' => $active->id, 'revisor_id' => $teacher->id, 'asignado_por' => $assistant->id, 'activa' => true]);
    TramiteAsignacion::factory()->create(['tramite_id' => $historical->id, 'revisor_id' => $teacher->id, 'asignado_por' => $assistant->id, 'activa' => false]);
    TramiteAsignacion::factory()->create(['tramite_id' => $foreign->id, 'revisor_id' => $otherTeacher->id, 'asignado_por' => $assistant->id, 'activa' => true]);

    foreach ([$active, $historical, $foreign] as $tramite) {
        TramiteEvento::query()->create([
            'tramite_id' => $tramite->id,
            'usuario_id' => $assistant->id,
            'accion' => 'recepcion',
            'descripcion' => 'Nota interna '.$tramite->codigo,
            'estado_nuevo' => $tramite->estado,
        ]);
    }

    $this->actingAs($teacher)->get(route('dashboard'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('role', 'docente')
        ->where('summary.total', 2)
        ->where('summary.cerrados', 1)
        ->has('activity', 2)
        ->where('adminCharts', null));
    $this->actingAs($assistant)->get(route('dashboard'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('role', 'asistente')
        ->where('summary.total', 3)
        ->where('adminCharts', null));
    $this->actingAs($administrator)->get(route('dashboard'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('role', 'administrador')
        ->where('summary.total', 3)
        ->has('adminCharts.monthly', 1)
        ->has('adminCharts.types')
        ->has('adminCharts.load')
        ->has('adminCharts.users'));
});

test('dashboard filters metrics and rejects unknown states', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    Tramite::factory()->create(['estado' => 'cerrado', 'clasificacion' => 'estudiantil']);
    Tramite::factory()->create(['estado' => 'digitalizado', 'clasificacion' => 'administrativo', 'tipo_documento' => 'COMUNICACION_ADMINISTRATIVA']);
    Tramite::factory()->create(['estado' => 'observado', 'created_at' => now()->subMonths(2)]);

    $this->actingAs($administrator)->get(route('dashboard', ['estado' => 'cerrado', 'clasificacion' => 'estudiantil']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.total', 1)
        ->where('summary.cerrados', 1)
        ->where('filters.estado', 'cerrado')
        ->where('states.0.codigo', 'cerrado'));
    $this->get(route('dashboard', ['estado' => 'no-existe']))->assertSessionHasErrors('estado');
    $this->get(route('dashboard', ['tipo' => 'FUT']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.total', 2)->where('filters.tipo', 'FUT'));
    $this->get(route('dashboard', ['desde' => now()->subDay()->toDateString()]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.total', 2));
    $this->get(route('dashboard', ['desde' => now()->toDateString(), 'hasta' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors('hasta');
});

test('dashboard filters active reviewer and delivery medium without widening role scope', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $reviewer = User::factory()->create(['rol' => 'docente']);
    $tramite = Tramite::factory()->create(['estado' => 'entregado']);
    Tramite::factory()->create(['estado' => 'entregado']);
    TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $assistant->id,
        'activa' => true,
    ]);
    $documento = TramiteDocumentoFinal::factory()->create(['tramite_id' => $tramite->id, 'estado' => 'emitido']);
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'presencial-prueba',
        'nombre' => 'Entrega presencial de prueba',
        'tipo' => 'presencial',
        'activo' => true,
    ]);
    TramiteEntrega::query()->create([
        'tramite_id' => $tramite->id,
        'documento_final_id' => $documento->id,
        'medio_entrega_id' => $medio->id,
        'receptor_nombre' => 'Persona de prueba',
        'receptor_tipo' => 'estudiante',
        'fecha_entrega' => now(),
        'codigo_confirmacion' => 'DASH-MEDIO-PRUEBA',
        'activa' => true,
    ]);

    $this->actingAs($administrator)->get(route('dashboard', ['revisor' => $reviewer->id, 'medio' => $medio->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.total', 1)
        ->where('filters.revisor', (string) $reviewer->id)
        ->where('filters.medio', (string) $medio->id));
    $this->get(route('dashboard', ['medio' => 999999]))->assertSessionHasErrors('medio');
    $this->actingAs($reviewer)->get(route('dashboard', ['medio' => $medio->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.total', 1)
        ->has('catalogs.revisores', 0));
});
