<?php

use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\Tramite;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteInformeCierre;
use App\Models\TramiteMedioEntrega;
use App\Models\TramitePlantilla;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('a scanned student request reaches an approved PDF and confirmed delivery', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    $this->seed();

    $directorPosition = DB::table('cargos_institucionales')->where('codigo', 'director_general')->value('id');
    $teacherPosition = DB::table('cargos_institucionales')->where('codigo', 'docente')->value('id');
    $admin = User::factory()->create(['rol' => 'administrador', 'cargo_institucional_id' => $directorPosition]);
    $teacher = User::factory()->create(['rol' => 'docente', 'cargo_institucional_id' => $teacherPosition]);
    $student = User::factory()->create([
        'rol' => 'estudiante', 'name' => 'Ana Pérez Soto', 'dni' => '72345678',
    ]);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $program = ProgramaEstudio::query()->where('activo', true)->firstOrFail();
    PerfilEstudiante::factory()->create(['user_id' => $student->id, 'programa_estudio_id' => $program->id]);

    $signature = imagecreatetruecolor(160, 60);
    imagefill($signature, 0, 0, imagecolorallocate($signature, 255, 255, 255));
    imageline($signature, 10, 40, 145, 15, imagecolorallocate($signature, 20, 20, 20));
    ob_start();
    imagejpeg($signature, null, 90);
    $signatureBytes = (string) ob_get_clean();
    imagedestroy($signature);
    Storage::disk('local')->put('firmas-perfil/'.$teacher->id.'.jpg', $signatureBytes);

    $this->actingAs($admin)->post(route('tramites.start'), [
        'tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION', 'dni' => $student->dni,
    ])->assertRedirect(route('tramites.create'));
    $this->get(route('tramites.create'))->assertOk();
    $this->post(route('tramites.store'), [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION',
        'formato_salida' => 'constancia',
        'propietario_id' => $student->id,
        'persona_nombre' => $student->name,
        'persona_identificador' => $student->dni,
        'destino_tipo' => 'docente',
        'destino_docente_id' => $teacher->id,
        'asunto' => 'Constancia de modalidad de titulación',
        'descripcion' => 'Solicito constancia de modalidad de examen de titulación.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
        'documentos' => [[
            'categoria' => 'documento_original',
            'archivo' => UploadedFile::fake()->createWithContent('fut.pdf', "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF"),
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $tramite = Tramite::query()->sole();
    expect($tramite->estado)->toBe('digitalizado')
        ->and($tramite->propietario_id)->toBe($student->id)
        ->and($tramite->documentos()->count())->toBe(1);

    $plantilla = TramitePlantilla::query()->where('codigo', 'CONSTANCIA_MODALIDAD_TITULACION')->sole();
    $this->get(route('tramites.borradores.create', $tramite))->assertOk();
    $this->post(route('tramites.borradores.store', $tramite), [
        'plantilla_id' => $plantilla->id,
        'remitente_id' => $admin->id,
        'firmante_id' => $teacher->id,
        'fecha_documento' => now()->toDateString(),
        'lugar' => 'Lima',
        'asunto' => 'Constancia de modalidad de titulación',
        'contenido_principal' => 'Se acredita la modalidad de examen de titulación solicitada por la estudiante.',
        'destinatarios' => [[
            'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'cargo' => 'Estudiante', 'principal' => true,
        ]],
        'preparar' => true,
    ])->assertRedirect(route('tramites.show', $tramite))->assertSessionHasNoErrors();
    expect($tramite->fresh()->estado)->toBe('borrador_preparado');

    $this->post(route('tramites.asignacion.prepare', $tramite))->assertRedirect();
    $this->post(route('tramites.asignaciones.store', $tramite), [
        'destino' => 'docente', 'revisor_id' => $teacher->id,
        'motivo' => 'Revisar la constancia de titulación.',
    ])->assertRedirect(route('tramites.show', $tramite))->assertSessionHasNoErrors();
    expect($tramite->fresh()->estado)->toBe('asignado');

    $this->actingAs($teacher)->get(route('asignaciones.docente.show', $tramite))->assertOk();
    $this->post(route('tramites.revision.start', $tramite))->assertRedirect();
    $this->post(route('tramites.revision.decide', $tramite), [
        'decision' => 'aprobar',
        'conclusion' => 'La solicitud cumple los requisitos para emitir la constancia.',
        'comentario_publico' => 'Su constancia fue aprobada.',
    ])->assertRedirect(route('asignaciones.docente.index'))->assertSessionHasNoErrors();
    expect($tramite->fresh()->estado)->toBe('aprobado');

    $this->actingAs($admin)->get(route('tramites.documento-final.preview', $tramite))->assertOk();
    $this->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite))->assertSessionHasNoErrors();
    $document = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->sole();
    expect($tramite->fresh()->estado)->toBe('documento_final_generado')
        ->and($document->estado)->toBe('emitido')
        ->and($document->contenido_snapshot['contenido_renderizado'])->toContain($student->name)
        ->and(Storage::disk('local')->get($document->ruta))->toStartWith('%PDF-');
    $this->actingAs($student)->get(route('tramites.documento-final.descargar', [$tramite, $document]))
        ->assertForbidden();
    $this->get(route('estudiante.tramites.show', $tramite))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('documento_final.url_descarga')
        ->where('puede_confirmar_entrega', false)
        ->etc());

    $this->actingAs($admin)->post(route('tramites.entrega.prepare', $tramite))->assertRedirect();
    expect($tramite->fresh()->estado)->toBe('listo_entrega');
    $medio = TramiteMedioEntrega::query()->where('codigo', 'descarga_sistema')->sole();
    $this->post(route('tramites.entrega.registrar', $tramite), [
        'medio_entrega_id' => $medio->id,
        'receptor_nombre' => $student->name,
        'receptor_documento' => $student->dni,
        'receptor_tipo' => 'Estudiante',
        'fecha_entrega' => now()->format('Y-m-d\TH:i'),
        'medio_utilizado' => 'Descarga desde el sistema',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(TramiteEntrega::query()->where('tramite_id', $tramite->id)->sole()->confirmado)->toBeFalse();

    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('documento_final.url_descarga', route('tramites.documento-final.descargar', [$tramite, $document]))
        ->where('puede_confirmar_entrega', true)
        ->etc());
    $this->get(route('tramites.documento-final.descargar', [$tramite, $document]))->assertOk();
    $this->actingAs($otherStudent)->get(route('tramites.documento-final.descargar', [$tramite, $document]))
        ->assertForbidden();
    $this->actingAs($student);
    $this->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => '1'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($tramite->fresh()->estado)->toBe('entregado');

    $this->actingAs($admin)->post(route('tramites.entrega.cerrar', $tramite), [
        'resumen' => 'La constancia fue recibida por la estudiante.',
    ])->assertRedirect(route('tramites.entrega.show', $tramite))->assertSessionHasNoErrors();
    $report = TramiteInformeCierre::query()->where('tramite_id', $tramite->id)->sole();
    expect($tramite->fresh()->estado)->toBe('cerrado')
        ->and(Storage::disk('local')->get($report->ruta))->toStartWith('%PDF-');
});
