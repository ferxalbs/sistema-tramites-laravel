<?php

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the audit log is available only to administrators', function () {
    $this->get(route('admin.audit.index'))->assertRedirect(route('login'));

    foreach (['estudiante', 'asistente', 'docente'] as $role) {
        $viewer = User::factory()->create(['rol' => $role]);
        $this->actingAs($viewer)->get(route('admin.audit.index'))->assertForbidden();
    }

    $administrator = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($administrator)->get(route('admin.audit.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auditoria')->where('events.total', 0));
});

test('the audit log combines account and expediente events with stable pagination and safe fields', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $target = User::factory()->create(['rol' => 'docente']);
    $tramite = Tramite::factory()->create(['persona_nombre' => 'Dato privado de persona']);

    DB::table('tramite_eventos')->insert([
        'tramite_id' => $tramite->id,
        'usuario_id' => $administrator->id,
        'accion' => 'recepcion',
        'descripcion' => 'Nota privada y token secreto del expediente',
        'metadatos' => json_encode(['ruta_privada' => '/private/documento.pdf']),
        'created_at' => now()->addMinute(),
        'updated_at' => now()->addMinute(),
    ]);

    for ($index = 0; $index < 26; $index++) {
        DB::table('user_account_events')->insert([
            'user_id' => $target->id,
            'actor_id' => $administrator->id,
            'accion' => 'edit',
            'estado_anterior' => 'activo',
            'estado_nuevo' => 'activo',
            'motivo' => 'Contraseña y token secretos '.$index,
            'created_at' => now()->subMinutes(26 - $index),
        ]);
    }

    $first = $this->actingAs($administrator)->get(route('admin.audit.index'));
    $first->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auditoria')
        ->where('events.total', 27)
        ->where('events.current_page', 1)
        ->has('events.data', 25)
        ->where('events.data.0.accion', 'recepcion')
        ->where('events.data.0.modulo', 'tramites')
        ->where('events.data.0.entidad_id', $tramite->id)
        ->where('events.data.0.actor', $administrator->name)
        ->missing('events.data.0.descripcion')
        ->missing('events.data.0.metadatos')
        ->missing('events.data.0.motivo'));
    expect($first->getContent())->not->toContain('Dato privado de persona', 'Nota privada', 'ruta_privada', 'Contraseña y token secretos');

    $this->get(route('admin.audit.index', ['page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('events.current_page', 2)
        ->has('events.data', 2)
        ->where('events.data.0.modulo', 'cuentas')
        ->where('events.data.1.modulo', 'cuentas'));
});
