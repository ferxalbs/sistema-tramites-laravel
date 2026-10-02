<?php

use App\Models\ProgramaEstudio;
use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteCierre;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteEvento;
use App\Models\TramiteMedioEntrega;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        ->where('notificationUnreadCount', 1)
        ->where('states.0.codigo', 'observado')
        ->has('activity', 1)
        ->where('activity.0.codigo', 'TRM-OWN-001')
        ->where('activity.0.title', 'Observado')
        ->where('adminCharts', null)
        ->missing('activity.0.usuario_id')
        ->missing('activity.0.descripcion'));
    expect($response->getContent())->not->toContain('TRM-FOREIGN-001', 'Comentario interno que no debe publicarse.', 'Actividad ajena.');

    $this->actingAs($other)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationUnreadCount', 1)
            ->where('summary.total', 1));

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
    $this->actingAs($assistant)->get(route('dashboard'))->assertForbidden();
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
    Tramite::factory()->create(['estado' => 'digitalizado', 'clasificacion' => 'administrativo', 'tipo_documento' => 'INFORME']);
    Tramite::factory()->create(['estado' => 'observado', 'created_at' => now()->subMonths(2)]);

    $this->actingAs($administrator)->get(route('dashboard', ['estado' => 'cerrado', 'clasificacion' => 'estudiantil']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.total', 1)
        ->where('summary.cerrados', 1)
        ->where('filters.estado', 'cerrado')
        ->where('states.0.codigo', 'cerrado'));
    $this->get(route('dashboard', ['estado' => 'no-existe']))->assertSessionHasErrors('estado');
    $this->get(route('dashboard', ['tipo' => 'INFORME']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.total', 1)->where('filters.tipo', 'INFORME'));
    $this->get(route('dashboard', ['desde' => now()->subDay()->toDateString()]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.total', 2));
    $this->get(route('dashboard', ['desde' => now()->toDateString(), 'hasta' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors('hasta');
});

test('dashboard filters by saved program and calculates referential deadlines and attended hours within role scope', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');

    try {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $student = User::factory()->create(['rol' => 'estudiante']);
        $program = ProgramaEstudio::factory()->create(['nombre' => 'Programa visible']);
        $otherProgram = ProgramaEstudio::factory()->create(['nombre' => 'Programa ajeno']);
        Tramite::factory()->create([
            'programa_estudio_id' => $program->id,
            'estado' => 'digitalizado',
            'fecha_llegada_oficina' => '2026-09-01 09:00:00',
            'fecha_recepcion' => '2026-09-01',
        ]);
        Tramite::factory()->create([
            'programa_estudio_id' => $program->id,
            'propietario_id' => $student->id,
            'estado' => 'digitalizado',
            'fecha_llegada_oficina' => '2026-09-15 09:00:00',
            'fecha_recepcion' => '2026-09-15',
        ]);
        $attended = Tramite::factory()->create([
            'programa_estudio_id' => $program->id,
            'estado' => 'entregado',
            'fecha_llegada_oficina' => '2026-09-01 09:00:00',
            'fecha_recepcion' => '2026-09-01',
        ]);
        $closed = Tramite::factory()->create([
            'programa_estudio_id' => $program->id,
            'estado' => 'cerrado',
            'fecha_llegada_oficina' => '2026-09-01 09:00:00',
            'fecha_recepcion' => '2026-09-01',
        ]);
        Tramite::factory()->create([
            'programa_estudio_id' => $otherProgram->id,
            'estado' => 'digitalizado',
            'fecha_llegada_oficina' => '2026-09-01 09:00:00',
            'fecha_recepcion' => '2026-09-01',
        ]);
        $document = TramiteDocumentoFinal::factory()->create(['tramite_id' => $attended->id, 'estado' => 'emitido']);
        $medium = TramiteMedioEntrega::query()->create([
            'codigo' => 'presencial-indicador', 'nombre' => 'Presencial', 'tipo' => 'presencial', 'activo' => true,
        ]);
        TramiteEntrega::query()->create([
            'tramite_id' => $attended->id,
            'documento_final_id' => $document->id,
            'medio_entrega_id' => $medium->id,
            'receptor_nombre' => 'Persona de prueba',
            'receptor_tipo' => 'estudiante',
            'fecha_entrega' => '2026-09-03 09:00:00',
            'codigo_confirmacion' => 'DASH-PLAZO-001',
            'activa' => true,
        ]);
        $closedDocument = TramiteDocumentoFinal::factory()->create(['tramite_id' => $closed->id, 'estado' => 'emitido']);
        $inactiveDelivery = TramiteEntrega::query()->create([
            'tramite_id' => $closed->id,
            'documento_final_id' => $closedDocument->id,
            'medio_entrega_id' => $medium->id,
            'receptor_nombre' => 'Persona de prueba',
            'receptor_tipo' => 'estudiante',
            'fecha_entrega' => '2026-09-03 09:00:00',
            'codigo_confirmacion' => 'DASH-PLAZO-002',
            'activa' => false,
        ]);
        TramiteCierre::query()->create([
            'tramite_id' => $closed->id,
            'entrega_id' => $inactiveDelivery->id,
            'resumen' => 'Atención completada',
            'fecha_cierre' => '2026-09-04 09:00:00',
            'activo' => true,
        ]);

        $this->actingAs($administrator)->get(route('dashboard', ['programa' => $program->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.programa', (string) $program->id)
            ->where('summary.total', 4)
            ->where('summary.horas_promedio_atencion', fn (float|int $value): bool => (float) $value === 60.0)
            ->has('catalogs.programas', 4));
        $this->get(route('dashboard', ['programa' => $program->id, 'estado' => 'entregado']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 1));
        $this->get(route('dashboard', ['programa' => 999999]))->assertSessionHasErrors('programa');
        $this->actingAs($student)->get(route('dashboard', ['programa' => $program->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 1)
            ->where('summary.horas_promedio_atencion', null));
        $this->get(route('dashboard', ['programa' => $otherProgram->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 0));
        $otherProgram->update(['activo' => false]);
        $this->get(route('dashboard', ['programa' => $otherProgram->id]))
            ->assertSessionHasErrors('programa');
    } finally {
        Carbon::setTestNow();
    }
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

test('administrative reports filter saved program and current assignment without exposing rows to other roles', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $programa = ProgramaEstudio::factory()->create(['nombre' => 'Programa de prueba']);
    $otroPrograma = ProgramaEstudio::factory()->create();
    $matching = Tramite::factory()->create([
        'codigo' => 'TRM-REPORT-001',
        'programa_estudio_id' => $programa->id,
        'estado' => 'entregado',
    ]);
    Tramite::factory()->create([
        'codigo' => 'TRM-REPORT-002',
        'programa_estudio_id' => $otroPrograma->id,
        'estado' => 'entregado',
    ]);
    TramiteAsignacion::factory()->create([
        'tramite_id' => $matching->id,
        'revisor_id' => $teacher->id,
        'asignado_por' => $assistant->id,
        'activa' => true,
    ]);
    $documento = TramiteDocumentoFinal::factory()->create(['tramite_id' => $matching->id, 'estado' => 'emitido']);
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'report-presencial',
        'nombre' => 'Presencial para reporte',
        'tipo' => 'presencial',
        'activo' => true,
    ]);
    TramiteEntrega::query()->create([
        'tramite_id' => $matching->id,
        'documento_final_id' => $documento->id,
        'medio_entrega_id' => $medio->id,
        'receptor_nombre' => 'Persona de prueba',
        'receptor_tipo' => 'estudiante',
        'fecha_entrega' => now(),
        'codigo_confirmacion' => 'REPORT-MEDIO-001',
        'activa' => true,
    ]);

    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('admin.reports.index'))->assertForbidden();
    $this->actingAs($teacher)->get(route('admin.reports.index'))->assertForbidden();
    $this->actingAs($assistant)->get(route('admin.reports.index'))->assertForbidden();
    $this->get(route('admin.reports.export'))->assertRedirect(route('login'));

    $filters = [
        'programa' => $programa->id,
        'clasificacion' => 'estudiantil',
        'tipo' => 'FUT',
        'estado' => 'entregado',
        'revisor' => $teacher->id,
        'medio' => $medio->id,
    ];
    $this->actingAs($administrator)->get(route('admin.reports.index', $filters))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 1)
        ->where('rows.data.0.codigo', 'TRM-REPORT-001')
        ->where('rows.data.0.programa', 'Programa de prueba')
        ->where('rows.data.0.revisor', $teacher->name)
        ->where('canExport', true)
        ->etc());
    $this->get(route('admin.reports.index', ['programa' => 999999]))->assertSessionHasErrors('programa');
    $this->get(route('admin.reports.index', ['desde' => now()->toDateString(), 'hasta' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors('hasta');
});

test('only administrators export filtered semicolon CSV with BOM and neutralized spreadsheet formulas', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $programa = ProgramaEstudio::factory()->create(['nombre' => 'Programa CSV']);
    Tramite::factory()->create([
        'codigo' => 'TRM-CSV-001',
        'asunto' => '=2+2',
        'programa_estudio_id' => $programa->id,
        'estado' => 'cerrado',
    ]);
    Tramite::factory()->create(['codigo' => 'TRM-CSV-002', 'estado' => 'digitalizado']);

    $this->actingAs($assistant)->get(route('admin.reports.export'))->assertForbidden();
    expect(DB::table('tramite_report_export_events')->count())->toBe(0);
    $response = $this->actingAs($administrator)->get(route('admin.reports.export', [
        'programa' => $programa->id,
        'estado' => 'cerrado',
    ]));
    $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $contents = $response->streamedContent();
    expect($contents)->toStartWith("\xEF\xBB\xBF")
        ->and($contents)->toContain('TRM-CSV-001', "'=2+2", 'Programa CSV')
        ->and($contents)->not->toContain('TRM-CSV-002');
    $lines = explode("\n", trim(substr($contents, 3)));
    expect($lines)->toHaveCount(2)
        ->and(str_getcsv($lines[0], ';'))->toHaveCount(9)
        ->and(str_getcsv($lines[1], ';'))->toHaveCount(9);
    $event = DB::table('tramite_report_export_events')->sole();
    expect((int) $event->actor_id)->toBe($administrator->id)
        ->and((int) $event->filas)->toBe(1)
        ->and(json_decode($event->filtros, true))->toBe(['programa' => (string) $programa->id, 'estado' => 'cerrado']);
});

test('report pagination keeps filters and returns only the requested page', function () {
    $assistant = User::factory()->create(['rol' => 'administrador']);
    $programa = ProgramaEstudio::factory()->create();
    Tramite::factory()->count(26)->create(['programa_estudio_id' => $programa->id]);

    $this->actingAs($assistant)->get(route('admin.reports.index', ['programa' => $programa->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 26)
        ->has('rows.data', 25)
        ->where('filters.programa', (string) $programa->id)
        ->where('rows.next_page_url', fn (string $url): bool => str_contains($url, 'programa='.$programa->id) && str_contains($url, 'page=2'))
        ->etc());
    $this->get(route('admin.reports.index', ['programa' => $programa->id, 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 26)
        ->has('rows.data', 1)
        ->where('rows.current_page', 2)
        ->etc());
});
