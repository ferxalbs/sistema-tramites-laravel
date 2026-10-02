<?php

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('only administrators can maintain institutional recipients', function () {
    $recipient = DB::table('destinatarios_institucionales')->first();
    $payload = [
        'nombres' => 'Director', 'apellidos' => 'Actualizado',
        'cargo' => 'Director General', 'correo' => '', 'activo' => '1',
    ];

    $this->get(route('admin.recipients.index'))->assertRedirect(route('login'));
    $this->post(route('admin.recipients.store'), $payload)->assertRedirect(route('login'));

    foreach (['estudiante', 'docente'] as $role) {
        $this->actingAs(User::factory()->create(['rol' => $role]));
        $this->get(route('admin.recipients.index'))->assertForbidden();
        $this->post(route('admin.recipients.store'), $payload)->assertForbidden();
        $this->patch(route('admin.recipients.update', $recipient->id), $payload)->assertForbidden();
    }

    expect(DB::table('destinatarios_institucionales')->count())->toBe(1)
        ->and(DB::table('tramite_config_events')->count())->toBe(0);
});

test('edited recipients appear in drafts and inactive recipients disappear', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $recipient = DB::table('destinatarios_institucionales')->first();
    $tramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $this->actingAs($administrator)->get(route('admin.recipients.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('destinatarios-institucionales')->has('destinatarios', 1));

    $this->patch(route('admin.recipients.update', $recipient->id), [
        'nombres' => 'Directora Ana', 'apellidos' => 'Paredes Ruiz',
        'cargo' => 'Dirección General', 'correo' => 'ana@seoane.edu.pe', 'activo' => '1',
    ])->assertRedirect(route('admin.recipients.index'));

    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('destinatarios_sugeridos.0.nombres', 'Directora Ana')
            ->where('destinatarios_sugeridos.0.correo', 'ana@seoane.edu.pe'));

    $this->post(route('admin.recipients.store'), [
        'nombres' => 'Coordinador', 'apellidos' => 'Sánchez Ruiz',
        'cargo' => 'Coordinación', 'correo' => '', 'activo' => '1',
    ])->assertRedirect(route('admin.recipients.index'));
    expect(DB::table('destinatarios_institucionales')->count())->toBe(2)
        ->and(DB::table('tramite_config_events')->count())->toBe(2);

    $this->patch(route('admin.recipients.update', $recipient->id), [
        'nombres' => 'Directora Ana', 'apellidos' => 'Paredes Ruiz',
        'cargo' => 'Dirección General', 'correo' => 'ana@seoane.edu.pe', 'activo' => '0',
    ])->assertRedirect(route('admin.recipients.index'));

    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('destinatarios_sugeridos', fn ($people): bool => ! collect($people)
                ->contains('nombres', 'Directora Ana') && collect($people)->contains('nombres', 'Coordinador')));
});

test('invalid recipient changes leave the catalog and audit intact', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $recipient = DB::table('destinatarios_institucionales')->first();

    $this->actingAs($administrator)->patch(route('admin.recipients.update', $recipient->id), [
        'nombres' => 'A', 'apellidos' => '', 'cargo' => '', 'correo' => 'incorrecto', 'activo' => 'x',
    ])->assertSessionHasErrors(['nombres', 'apellidos', 'cargo', 'correo', 'activo']);
    $this->patch(route('admin.recipients.update', 99999), [
        'nombres' => 'Nombre', 'apellidos' => 'Apellido', 'cargo' => 'Cargo', 'activo' => '1',
    ])->assertNotFound();

    expect(DB::table('destinatarios_institucionales')->where('id', $recipient->id)->value('nombres'))
        ->toBe('Mg. RAUL WILLIAM')
        ->and(DB::table('tramite_config_events')->count())->toBe(0);
});
