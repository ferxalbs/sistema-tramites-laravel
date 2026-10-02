<?php

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteEvento;
use App\Models\User;
use App\Services\Tramites\CreateTramiteNotifications;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('notification routes require an authenticated verified account', function () {
    $this->get(route('notificaciones.index'))->assertRedirect(route('login'));
    $this->patch(route('notificaciones.read', 1))->assertRedirect(route('login'));
    $this->patch(route('notificaciones.read-all'))->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create(['rol' => 'estudiante']);
    $this->actingAs($unverified)->get(route('notificaciones.index'))->assertRedirect(route('verification.notice'));
});

test('workflow events notify only active owner, reviewer, and office staff without private notes', function () {
    $owner = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $reviewer = User::factory()->create(['rol' => 'docente']);
    $otherTeacher = User::factory()->create(['rol' => 'docente']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $inactiveAssistant = User::factory()->create(['rol' => 'asistente', 'activo' => false, 'estado_cuenta' => 'inactivo']);
    $tramite = Tramite::factory()->create(['propietario_id' => $owner->id, 'recibido_por' => $assistant->id]);
    TramiteAsignacion::factory()->create(['tramite_id' => $tramite->id, 'revisor_id' => $reviewer->id, 'asignado_por' => $assistant->id]);

    $event = TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $reviewer->id,
        'accion' => 'observacion',
        'descripcion' => 'Nota reservada que no debe notificarse.',
        'metadatos' => ['observacion' => 'Dato privado'],
    ]);

    $notifications = DB::table('tramite_notificaciones')->where('evento_id', $event->id)->get();
    expect($notifications->pluck('usuario_id')->sort()->values()->all())
        ->toBe(collect([$owner->id, $reviewer->id, $admin->id])->sort()->values()->all())
        ->and($notifications->pluck('mensaje')->unique()->all())->toHaveCount(1)
        ->and($notifications->first()->mensaje)->toContain($tramite->codigo)
        ->not->toContain('Nota reservada', 'Dato privado');

    app(CreateTramiteNotifications::class)->forEvent($event);
    expect(DB::table('tramite_notificaciones')->count())->toBe(3);

    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $otherStudent->id,
        'accion' => 'acceso_tramite_no_autorizado',
        'descripcion' => 'Acceso denegado',
    ]);
    expect(DB::table('tramite_notificaciones')->count())->toBe(3)
        ->and(DB::table('tramite_notificaciones')->where('usuario_id', $inactiveAssistant->id)->exists())->toBeFalse()
        ->and(DB::table('tramite_notificaciones')->where('usuario_id', $otherTeacher->id)->exists())->toBeFalse();
});

test('notifications are scoped on list, mark one, mark all, and audit', function () {
    $owner = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $assistant = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['propietario_id' => $owner->id, 'recibido_por' => $assistant->id]);

    TramiteEvento::query()->create(['tramite_id' => $tramite->id, 'usuario_id' => $assistant->id, 'accion' => 'recepcion', 'descripcion' => 'Recepción']);
    TramiteEvento::query()->create(['tramite_id' => $tramite->id, 'usuario_id' => $assistant->id, 'accion' => 'revision_aprobada', 'descripcion' => 'Aprobación']);
    $ownerNotifications = DB::table('tramite_notificaciones')->where('usuario_id', $owner->id)->orderBy('id')->pluck('id');
    $assistantNotification = DB::table('tramite_notificaciones')->where('usuario_id', $assistant->id)->value('id');

    $this->actingAs($otherStudent)->get(route('notificaciones.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('notificaciones')->where('notifications.total', 0));
    $this->patch(route('notificaciones.read', $ownerNotifications[0]))->assertNotFound();
    $this->patch(route('notificaciones.read-all'))->assertRedirect(route('notificaciones.index'));
    expect(DB::table('tramite_notificaciones')->where('leida', true)->count())->toBe(0);

    $this->actingAs($owner)->get(route('notificaciones.index', ['estado' => 'no_leidas', 'prioridad' => 'alta']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('notificaciones')
        ->where('notifications.total', 1)
        ->where('notifications.data.0.url', route('estudiante.tramites.show', $tramite->id, false))
        ->where('notificationUnreadCount', 2));
    $this->get(route('notificaciones.index', ['estado' => 'inventado']))->assertSessionHasErrors('estado');

    $this->patch(route('notificaciones.read', $assistantNotification))->assertNotFound();
    $this->patch(route('notificaciones.read', $ownerNotifications[0]))->assertRedirect(route('notificaciones.index'));
    $this->patch(route('notificaciones.read', $ownerNotifications[0]))->assertRedirect(route('notificaciones.index'));
    expect(DB::table('tramite_notificacion_eventos')->where('accion', 'marcar_leida')->count())->toBe(1);

    $this->patch(route('notificaciones.read-all'))->assertRedirect(route('notificaciones.index'));
    expect(DB::table('tramite_notificaciones')->where('usuario_id', $owner->id)->where('leida', false)->count())->toBe(0)
        ->and(DB::table('tramite_notificaciones')->where('usuario_id', $assistant->id)->where('leida', false)->count())->toBe(2)
        ->and(DB::table('tramite_notificacion_eventos')->where('accion', 'marcar_todas')->count())->toBe(1);

});

test('notification writes roll back with their workflow event', function () {
    $owner = User::factory()->create(['rol' => 'estudiante']);
    $assistant = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['propietario_id' => $owner->id, 'recibido_por' => $assistant->id]);

    try {
        DB::transaction(function () use ($tramite, $assistant): void {
            TramiteEvento::query()->create(['tramite_id' => $tramite->id, 'usuario_id' => $assistant->id, 'accion' => 'recepcion', 'descripcion' => 'Recepción']);
            throw new RuntimeException('Operación revertida');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Operación revertida');
    }

    expect(DB::table('tramite_eventos')->count())->toBe(0)
        ->and(DB::table('tramite_notificaciones')->count())->toBe(0);
});
