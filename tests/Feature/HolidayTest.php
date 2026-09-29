<?php

use App\Models\Tramite;
use App\Models\User;
use App\Services\Tramites\CalculateTramiteDeadline;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('only administrators can view or change holidays', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($administrator)->post(route('admin.holidays.store'), [
        'fecha' => '2026-10-08', 'nombre' => 'Feriado confirmado',
    ])->assertRedirect(route('admin.holidays.index'));
    $holiday = DB::table('feriados')->first();

    auth()->logout();
    $this->get(route('admin.holidays.index'))->assertRedirect(route('login'));
    $this->post(route('admin.holidays.store'), ['fecha' => '2026-11-01', 'nombre' => 'Otro feriado'])
        ->assertRedirect(route('login'));
    $this->patch(route('admin.holidays.deactivate', $holiday->id))->assertRedirect(route('login'));

    foreach (['estudiante', 'asistente', 'docente'] as $role) {
        $viewer = User::factory()->create(['rol' => $role]);
        $this->actingAs($viewer)->get(route('admin.holidays.index'))->assertForbidden();
        $this->post(route('admin.holidays.store'), ['fecha' => '2026-11-01', 'nombre' => 'Otro feriado'])
            ->assertForbidden();
        $this->patch(route('admin.holidays.deactivate', $holiday->id))->assertForbidden();
    }

    expect(DB::table('feriados')->count())->toBe(1)
        ->and(DB::table('feriados')->where('id', $holiday->id)->value('activo'))->toBe(1);
});

test('holiday validation rejects invalid dates and names without writing an event', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($administrator)->post(route('admin.holidays.store'), [
        'fecha' => '2026-02-30', 'nombre' => 'Feriado',
    ])->assertSessionHasErrors('fecha');
    $this->post(route('admin.holidays.store'), [
        'fecha' => '2026-10-08', 'nombre' => '  ',
    ])->assertSessionHasErrors('nombre');
    $this->post(route('admin.holidays.store'), [
        'fecha' => '2026-10-08', 'nombre' => str_repeat('a', 161),
    ])->assertSessionHasErrors('nombre');

    expect(DB::table('feriados')->count())->toBe(0)
        ->and(DB::table('feriado_eventos')->count())->toBe(0);
});

test('holidays are upserted by date, deactivated without deletion, and recorded in audit', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($administrator)->post(route('admin.holidays.store'), [
        'fecha' => '2026-10-08', 'nombre' => 'Nombre inicial',
    ])->assertRedirect(route('admin.holidays.index'));
    $holiday = DB::table('feriados')->first();

    $this->patch(route('admin.holidays.deactivate', $holiday->id))->assertRedirect(route('admin.holidays.index'));
    $this->get(route('admin.holidays.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('feriados')->has('holidays', 0));
    $this->patch(route('admin.holidays.deactivate', $holiday->id))->assertNotFound();

    $this->post(route('admin.holidays.store'), [
        'fecha' => '2026-10-08', 'nombre' => 'Nombre confirmado',
    ])->assertRedirect(route('admin.holidays.index'));
    $this->get(route('admin.holidays.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('feriados')
            ->has('holidays', 1)
            ->where('holidays.0.id', $holiday->id)
            ->where('holidays.0.fecha', '2026-10-08')
            ->where('holidays.0.nombre', 'Nombre confirmado'));

    expect(DB::table('feriados')->count())->toBe(1)
        ->and(DB::table('feriados')->where('id', $holiday->id)->value('activo'))->toBe(1)
        ->and(DB::table('feriados')->where('id', $holiday->id)->value('creado_por'))->toBe($administrator->id)
        ->and(DB::table('feriado_eventos')->where('feriado_id', $holiday->id)->pluck('accion')->all())
        ->toBe(['configurar_feriado', 'desactivar_feriado', 'configurar_feriado']);

    $this->get(route('admin.audit.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auditoria')
            ->where('events.total', 3)
            ->where('events.data.0.modulo', 'feriados')
            ->where('events.data.0.actor', $administrator->name));
});

test('demonstration dates remain visible to administrators and become confirmed by date', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    DB::table('feriados')->insert([
        'fecha' => '2026-12-25',
        'nombre' => 'Fecha de demostración',
        'es_demostracion' => true,
        'activo' => true,
    ]);

    $this->actingAs($administrator)->get(route('admin.holidays.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('feriados')->has('holidays', 1)
            ->where('holidays.0.es_demostracion', 1));
    $this->post(route('admin.holidays.store'), [
        'fecha' => '2026-12-25', 'nombre' => 'Fecha confirmada',
    ])->assertRedirect(route('admin.holidays.index'));

    expect(DB::table('feriados')->count())->toBe(1)
        ->and(DB::table('feriados')->value('es_demostracion'))->toBe(0);
    $this->get(route('admin.holidays.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('feriados')->has('holidays', 1)
            ->where('holidays.0.nombre', 'Fecha confirmada')
            ->where('holidays.0.es_demostracion', 0));
});

test('deadline settings are administrative and business days exclude confirmed holidays', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $configuration = DB::table('configuracion_plazos as c')
        ->join('tipos_tramite as t', 't.id', '=', 'c.tipo_tramite_id')
        ->where('t.codigo', 'FUT')->first(['c.id']);
    expect($configuration)->not->toBeNull();

    $data = [
        'dias_estimados' => 1,
        'dias_maximos' => 2,
        'tipo_dias' => 'habiles',
        'dias_anticipacion_recordatorio' => 1,
    ];
    $this->get(route('admin.deadlines.index'))->assertRedirect(route('login'));
    $this->actingAs($assistant)->get(route('admin.deadlines.index'))->assertForbidden();
    $this->patch(route('admin.deadlines.update', $configuration->id), $data)->assertForbidden();
    $this->actingAs($administrator)->get(route('admin.deadlines.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('plazos')->has('plazos', 8));
    $this->patch(route('admin.deadlines.update', $configuration->id), [
        ...$data, 'dias_estimados' => 3,
    ])->assertSessionHasErrors('dias_estimados');
    $this->patch(route('admin.deadlines.update', $configuration->id), [
        ...$data, 'tipo_dias' => 'inventado',
    ])->assertSessionHasErrors('tipo_dias');
    $this->patch(route('admin.deadlines.update', $configuration->id), $data)
        ->assertRedirect(route('admin.deadlines.index'));
    expect(DB::table('configuracion_plazos')->where('id', $configuration->id)->value('es_plazo_oficial'))->toBe(0)
        ->and(DB::table('tramite_config_events')->where('entidad', 'configuracion_plazo')->count())->toBe(1);
    $this->get(route('admin.audit.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('events.data.0.modulo', 'plazos'));

    $tramite = Tramite::factory()->create([
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'FUT',
        'fecha_recepcion' => '2026-07-17',
        'fecha_llegada_oficina' => '2026-07-17 10:00:00',
        'estado' => 'recibido_oficina',
    ]);
    DB::table('feriados')->insert([
        'fecha' => '2026-07-20', 'nombre' => 'Fecha de demostración', 'es_demostracion' => true, 'activo' => true,
    ]);
    $calculator = app(CalculateTramiteDeadline::class);
    $initial = $calculator->forTramite($tramite, new DateTimeImmutable('2026-07-20'));
    expect($initial['fecha_estimada'])->toBe('2026-07-20')
        ->and($initial['fecha_maxima'])->toBe('2026-07-21')
        ->and($initial['dias_restantes'])->toBe(1)
        ->and($initial['alerta'])->toBe('proximo')
        ->and($initial['etiqueta'])->toBe('Plazo estimado referencial');

    DB::table('feriados')->where('fecha', '2026-07-20')->update(['es_demostracion' => false]);
    $confirmed = $calculator->forTramite($tramite, new DateTimeImmutable('2026-07-20'));
    expect($confirmed['fecha_estimada'])->toBe('2026-07-21')
        ->and($confirmed['fecha_maxima'])->toBe('2026-07-22')
        ->and($confirmed['dias_restantes'])->toBe(2)
        ->and($confirmed['alerta'])->toBe('normal');
    $this->actingAs($assistant)->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramite.plazo.fecha_maxima', '2026-07-22')
            ->where('tramite.plazo.etiqueta', 'Plazo estimado referencial'));
    expect($calculator->forTramite($tramite, new DateTimeImmutable('2026-07-23'))['alerta'])->toBe('vencido');
    $tramite->update(['estado' => 'cerrado']);
    expect($calculator->forTramite($tramite, new DateTimeImmutable('2026-07-23'))['alerta'])->toBe('completado');
});
