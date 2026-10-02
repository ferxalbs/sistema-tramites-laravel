<?php

use App\Mail\TramiteDocumentoEntregado;
use App\Models\PerfilDocente;
use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramiteDocumento;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteEvento;
use App\Models\TramiteFirma;
use App\Models\TramiteMedioEntrega;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramitePlantilla;
use App\Models\TramiteRondaRevision;
use App\Models\TramiteSerieDocumental;
use App\Models\User;
use App\Services\PdfDocumentGenerator;
use App\Services\Tramites\GenerateTramiteFinalDocument;
use App\Services\Tramites\LinkApplicantAccount;
use App\Services\Tramites\ProcessTramiteDelivery;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Database\Seeders\TramitePlantillaSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

test('unapproved institutional models cannot consume an official document number', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);

    foreach (['JUSTIFICACION_TARDANZA', 'CONSTANCIA_PRACTICA'] as $type) {
        $tramite = Tramite::factory()->create(['tipo_documento' => $type, 'estado' => 'aprobado']);

        expect(fn () => app(GenerateTramiteFinalDocument::class)->execute($tramite, $admin))
            ->toThrow(ValidationException::class, 'Falta el modelo institucional aprobado');
    }

    expect(TramiteNumeracionDocumental::query()->count())->toBe(0);
});

test('administrators edit classifications while reception requires active codes', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $classification = DB::table('clasificaciones_expediente')->where('codigo', 'administrativo')->first();
    expect($classification)->not->toBeNull();

    $changes = ['nombre' => 'Gestión administrativa', 'descripcion' => 'Clasificación institucional', 'activo' => '0'];
    $this->get(route('admin.classifications.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->get(route('admin.classifications.index'))->assertForbidden();
    $this->patch(route('admin.classifications.update', $classification->id), $changes)->assertForbidden();

    $this->actingAs($admin)->get(route('admin.classifications.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clasificaciones-expediente')->has('clasificaciones', 2));
    $this->patch(route('admin.classifications.update', $classification->id), $changes)
        ->assertRedirect(route('admin.classifications.index'));
    $saved = DB::table('clasificaciones_expediente')->where('id', $classification->id)->first();
    expect($saved->codigo)->toBe('administrativo')
        ->and($saved->nombre)->toBe('Gestión administrativa')
        ->and((int) $saved->activo)->toBe(0)
        ->and(DB::table('tramite_config_events')->where('entidad', 'clasificacion_expediente')->count())->toBe(1);

    $this->actingAs($officeAdmin)->post(route('tramites.start'), [
        'tipo_documento' => 'INFORME',
    ])->assertRedirect(route('tramites.create'));
    $this->get(route('tramites.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('catalogos.clasificaciones.administrativo'));
    $payload = [
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'INFORME',
        'formato_salida' => 'informe',
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Solicitud recibida físicamente',
        'descripcion' => 'Expediente de prueba local.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\\TH:i'),
        'confirmar_recepcion' => '1',
    ];
    $this->post(route('tramites.store'), $payload)->assertSessionHasErrors('clasificacion');
    expect(Tramite::query()->count())->toBe(0);

    $this->actingAs($admin)->patch(route('admin.classifications.update', $classification->id), [
        'nombre' => 'Gestión administrativa', 'descripcion' => 'Clasificación institucional', 'activo' => '1',
    ])->assertRedirect(route('admin.classifications.index'));
    $this->actingAs($officeAdmin)->post(route('tramites.store'), $payload)->assertRedirect();
    $this->get(route('tramites.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramites.data.0.clasificacion', 'Gestión administrativa'));

    $this->actingAs($admin)->patch(route('admin.classifications.update', 99999), $changes)->assertNotFound();
    $this->patch(route('admin.classifications.update', $classification->id), [
        'nombre' => 'Estudiantil', 'descripcion' => '', 'activo' => '1',
    ])->assertSessionHasErrors('nombre');
});

test('administrators edit retained types while retired types stay unavailable', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $type = DB::table('tipos_tramite')->where('codigo', 'INFORME')->first();
    expect($type)->not->toBeNull();

    $retiredType = DB::table('tipos_tramite')->where('codigo', 'FUT')->first();
    $changes = ['nombre' => 'Informe de prueba', 'descripcion' => 'Tipo disponible', 'activo' => '0'];
    $this->get(route('admin.types.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->get(route('admin.types.index'))->assertForbidden();
    $this->patch(route('admin.types.update', $type->id), $changes)->assertForbidden();

    $this->actingAs($admin)->get(route('admin.types.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tipos-tramite')->has('tipos', 6));
    $this->patch(route('admin.types.update', $retiredType->id), $changes)->assertNotFound();
    $this->patch(route('admin.types.update', $type->id), $changes)
        ->assertRedirect(route('admin.types.index'));
    $saved = DB::table('tipos_tramite')->where('id', $type->id)->first();
    expect($saved->codigo)->toBe('INFORME')
        ->and($saved->nombre)->toBe('Informe de prueba')
        ->and((int) $saved->activo)->toBe(0)
        ->and(DB::table('tramite_config_events')->where('entidad', 'tipo_tramite')->count())->toBe(1);

    $this->actingAs($officeAdmin)->post(route('tramites.start'), [
        'tipo_documento' => 'JUSTIFICACION_TARDANZA', 'dni' => '90000002',
    ])->assertRedirect(route('tramites.create'));
    $this->get(route('tramites.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('catalogos.tipos_documento.administrativo.FUT')
            ->missing('catalogos.tipos_documento.administrativo.AUTORIZACION_INGRESO')
            ->missing('catalogos.tipos_documento.administrativo.COMUNICACION_ADMINISTRATIVA')
            ->missing('catalogos.tipos_documento.administrativo.SOLICITUD_GENERAL')
            ->missing('catalogos.tipos_documento.administrativo.REQUERIMIENTO_EQUIPAMIENTO'));

    $payload = [
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'REQUERIMIENTO_EQUIPAMIENTO',
        'formato_salida' => 'informe',
        'persona_nombre' => 'Persona de prueba',
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Solicitud recibida físicamente',
        'descripcion' => 'Expediente de prueba local.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
    ];
    $this->post(route('tramites.store'), $payload)->assertSessionHasErrors('tipo_documento');
    $this->post(route('tramites.store'), [...$payload, 'tipo_documento' => 'FUT'])
        ->assertSessionHasErrors('tipo_documento');
    expect(Tramite::query()->count())->toBe(0);

    $this->actingAs($admin)->patch(route('admin.types.update', $type->id), [
        'nombre' => 'Informe de prueba', 'descripcion' => 'Tipo disponible', 'activo' => '1',
    ])->assertRedirect(route('admin.types.index'));
    $validPayload = [
        ...$payload,
        'tipo_documento' => 'INFORME',
    ];
    unset($validPayload['persona_nombre']);
    $this->actingAs($officeAdmin)->post(route('tramites.store'), $validPayload)->assertRedirect();
    $this->get(route('tramites.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramites.data.0.tipo_documento', 'Informe de prueba'));

    $this->actingAs($admin)->patch(route('admin.types.update', 99999), $changes)->assertNotFound();
    $this->patch(route('admin.types.update', $type->id), [
        'nombre' => 'A', 'descripcion' => str_repeat('x', 256), 'activo' => 'no',
    ])->assertSessionHasErrors(['nombre', 'descripcion', 'activo']);
    $this->patch(route('admin.types.update', $type->id), [
        'nombre' => 'Justificación', 'descripcion' => '', 'activo' => '1',
    ])->assertSessionHasErrors('nombre');
});

test('institutional reports and memorandums register without a request applicant or DNI', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($officeAdmin)->get(route('tramites.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/start')
            ->where('tipos', function ($tipos): bool {
                $tipos = collect($tipos)->keyBy('codigo');

                return $tipos->has(['INFORME', 'MEMORANDO_SIMPLE', 'MEMORANDO_MULTIPLE'])
                    && ! $tipos->has('REQUERIMIENTO_EQUIPAMIENTO')
                    && $tipos['INFORME']['requiere_solicitante'] === false;
            }));

    foreach ([
        ['INFORME', 'informe', null, []],
        ['MEMORANDO_SIMPLE', 'memorando', 'simple', []],
        ['MEMORANDO_MULTIPLE', 'memorando', 'multiple', [
            ['nombres' => 'Ana Ruiz'],
            ['nombres' => 'Luis Pérez'],
        ]],
    ] as [$tipo, $formato, $modalidad, $destinatarios]) {
        $this->post(route('tramites.start'), ['tipo_documento' => $tipo])
            ->assertRedirect(route('tramites.create'));
        $this->get(route('tramites.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seleccion.tipo_documento', $tipo)
            ->where('seleccion.requiere_solicitante', false)
            ->where('seleccion.dni', null));

        $payload = [
            'clasificacion' => 'administrativo',
            'tipo_documento' => $tipo,
            'formato_salida' => $formato,
            'modalidad_documento' => $modalidad,
            'destino_tipo' => 'oficina',
            'destino_nombre' => 'Secretaría Académica',
            'asunto' => 'Documento interno de prueba',
            'descripcion' => 'Contenido institucional para verificar el registro.',
            'prioridad' => 'normal',
            'fecha_llegada_oficina' => now()->format('Y-m-d\\TH:i'),
            'confirmar_recepcion' => '1',
        ];
        if ($destinatarios !== []) {
            $payload['destinatarios'] = $destinatarios;
        }

        $this->post(route('tramites.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $tramite = Tramite::query()->where('tipo_documento', $tipo)->sole();
        expect($tramite->persona_nombre)->toBeNull()
            ->and($tramite->persona_identificador)->toBeNull()
            ->and($tramite->propietario_id)->toBeNull()
            ->and($tramite->formato_salida)->toBe($formato)
            ->and($tramite->modalidad_documento)->toBe($modalidad)
            ->and($tramite->estado)->toBe('digitalizado');
        $this->get(route('tramites.borradores.create', $tramite))->assertOk();
    }

    $this->post(route('tramites.start'), [
        'tipo_documento' => 'MEMORANDO_SIMPLE', 'dni' => '12345678',
    ])->assertSessionHasErrors('dni');
    expect(Tramite::query()->count())->toBe(3);
});

test('office registers a scanned FUT without a student account and links an approved account later', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $programa = ProgramaEstudio::factory()->create(['activo' => true]);
    $dni = '72345678';
    $pdf = "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($admin)->post(route('tramites.start'), [
        'tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION', 'dni' => $dni,
    ])->assertRedirect(route('tramites.create'));
    $this->post(route('tramites.store'), [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION',
        'formato_salida' => 'constancia',
        'persona_nombre' => 'Ana Pérez Soto',
        'persona_identificador' => $dni,
        'solicitante_correo' => 'ana@example.test',
        'solicitante_celular' => '987654321',
        'programa_estudio_id' => $programa->id,
        'destino_tipo' => 'oficina',
        'asunto' => 'Constancia de titulación',
        'descripcion' => 'Solicito constancia de modalidad de examen de titulación.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
        'documentos' => [[
            'categoria' => 'documento_original',
            'archivo' => UploadedFile::fake()->createWithContent('fut.pdf', $pdf),
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $tramite = Tramite::query()->where('persona_identificador', $dni)->sole();
    expect($tramite->propietario_id)->toBeNull()
        ->and($tramite->persona_nombre)->toBe('Ana Pérez Soto')
        ->and($tramite->solicitante_correo)->toBe('ana@example.test')
        ->and($tramite->estado)->toBe('digitalizado');
    $this->get(route('tramites.borradores.create', $tramite))->assertOk();

    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => $dni, 'activo' => true, 'estado_cuenta' => 'activo']);
    app(LinkApplicantAccount::class)->execute($student, $admin);
    expect($tramite->fresh()->propietario_id)->toBe($student->id)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->where('accion', 'solicitante_vinculado')->exists())->toBeTrue();
});

test('the final PDF includes the exact resolved body of a constancia', function () {
    $body = "CONSTANCIA DE MODALIDAD DE EXAMEN DE TITULACION\n\nAna Perez Soto, DNI 72345678, programa de Desarrollo de Sistemas.\n\nTexto especifico aprobado para esta constancia.\n\nDocente Firmante";
    $pdf = app(PdfDocumentGenerator::class)->generate([
        'institucion' => 'Instituto Manuel Seoane Corrales',
        'tipo_documento' => 'Constancia de titulación',
        'tipo_documento_salida' => 'constancia',
        'numero' => 'CONST-2026-001',
        'codigo_expediente' => 'TRM-2026-001',
        'codigo_verificacion' => 'ABCD-EFGH-IJKL-MNOP',
        'firmante_nombre' => 'Docente Firmante',
        'firmante_cargo' => 'Coordinador',
        'decision' => 'aprobado',
        'contenido_renderizado' => $body,
    ]);

    expect($pdf['bytes'])->toStartWith('%PDF-')
        ->toContain('Ana Perez Soto')
        ->toContain('72345678')
        ->toContain('Texto especifico aprobado para esta constancia.');
});

test('email delivery sends the official PDF before marking the delivery as registered', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    Mail::fake();
    config(['mail.default' => 'smtp']);
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $tramite = Tramite::factory()->create(['estado' => 'listo_entrega']);
    $pdf = "%PDF-1.4\n".str_repeat('Documento oficial de prueba. ', 30)."\n%%EOF";
    $ruta = 'documentos-finales/prueba-correo.pdf';
    Storage::disk('local')->put($ruta, $pdf);
    $documento = TramiteDocumentoFinal::factory()->create([
        'tramite_id' => $tramite->id,
        'estado' => 'emitido',
        'activo' => true,
        'ruta' => $ruta,
        'nombre_archivo' => 'documento-oficial.pdf',
        'sha256' => hash('sha256', $pdf),
        'tamano_bytes' => strlen($pdf),
        'numero_paginas' => 1,
    ]);
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'correo_electronico', 'nombre' => 'Correo electrónico',
        'tipo' => 'digital', 'requiere_evidencia' => false, 'activo' => true,
    ]);

    $entrega = app(ProcessTramiteDelivery::class)->registerDelivery($tramite, $admin, [
        'medio_entrega_id' => $medio->id,
        'receptor_nombre' => 'Ana Pérez Soto',
        'receptor_tipo' => 'Estudiante',
        'correo_destino' => 'ana@example.test',
        'fecha_entrega' => now()->format('Y-m-d\TH:i'),
    ], null);

    expect($entrega->estado)->toBe('registrada')
        ->and($entrega->documento_final_id)->toBe($documento->id)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->where('accion', 'entrega_registrada')->exists())->toBeTrue();
    Mail::assertSent(TramiteDocumentoEntregado::class, fn (TramiteDocumentoEntregado $mail): bool => $mail->hasTo('ana@example.test')
        && $mail->codigoExpediente === $tramite->codigo);
});

test('administrators edit output formats while keeping them active for drafts', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $format = DB::table('tipos_documento_salida')->where('codigo', 'memorando')->first();
    expect($format)->not->toBeNull();
    $finalTemplate = TramitePlantilla::factory()->create([
        'codigo' => 'MEMORANDO_FINAL_PRUEBA',
        'tipo_documento_salida' => 'memorando',
        'estado' => 'publicada',
        'activa' => true,
        'version' => 2,
    ]);

    $changes = ['nombre' => 'Memorando institucional', 'descripcion' => 'Formato de memorando', 'activo' => '0'];
    $this->get(route('admin.output-formats.index'))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('admin.output-formats.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->patch(route('admin.output-formats.update', $format->id), $changes)->assertForbidden();

    $this->actingAs($admin)->get(route('admin.output-formats.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('formatos-salida')
            ->has('formatos', 3)
            ->where('plantillasFinales.0.id', $finalTemplate->id)
            ->where('plantillasFinales.0.version', 2));
    $this->patch(route('admin.output-formats.update', $format->id), $changes)
        ->assertRedirect(route('admin.output-formats.index'));
    $saved = DB::table('tipos_documento_salida')->where('id', $format->id)->first();
    expect($saved->codigo)->toBe('memorando')
        ->and($saved->nombre)->toBe('Memorando institucional')
        ->and((int) $saved->permite_modalidad_multiple)->toBe(1)
        ->and((int) $saved->activo)->toBe(1)
        ->and(DB::table('tramite_config_events')->where('entidad', 'tipo_documento_salida')->count())->toBe(1);

    $tramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $memorando = TramitePlantilla::factory()->create(['tipo_documento_salida' => 'memorando', 'modalidad' => 'simple']);
    $informe = TramitePlantilla::factory()->create(['tipo_documento_salida' => 'informe']);
    $this->actingAs($officeAdmin)->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plantillas', fn ($plantillas): bool => collect($plantillas)->contains('id', $informe->id)
            && collect($plantillas)->contains('id', $memorando->id))->etc());

    $payload = [
        'plantilla_id' => $memorando->id,
        'fecha_documento' => now()->toDateString(),
        'lugar' => 'Lima',
        'asunto' => 'Comunicación de prueba',
        'preparar' => false,
    ];
    $this->post(route('tramites.borradores.store', $tramite), $payload)->assertRedirect(route('tramites.show', $tramite));
    expect($tramite->borradores()->count())->toBe(1);

    $this->actingAs($admin)->patch(route('admin.output-formats.update', $format->id), [
        'nombre' => 'Memorando institucional', 'descripcion' => 'Formato de memorando', 'activo' => '1',
    ])->assertRedirect(route('admin.output-formats.index'));
    $invalidInforme = TramitePlantilla::factory()->create(['tipo_documento_salida' => 'informe', 'modalidad' => 'unica']);
    $this->actingAs($officeAdmin)->post(route('tramites.borradores.store', $tramite), [
        ...$payload, 'plantilla_id' => $invalidInforme->id,
    ])->assertSessionHasErrors('plantilla_id');
    $invalidMemorando = TramitePlantilla::factory()->create(['tipo_documento_salida' => 'memorando', 'modalidad' => null]);
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$payload, 'plantilla_id' => $invalidMemorando->id,
    ])->assertSessionHasErrors('plantilla_id');
    expect($tramite->borradores()->count())->toBe(1)
        ->and($tramite->fresh()->formato_salida)->toBe('memorando')
        ->and($tramite->fresh()->modalidad_documento)->toBe('simple');

    $this->actingAs($admin)->patch(route('admin.output-formats.update', 99999), $changes)->assertNotFound();
    $this->patch(route('admin.output-formats.update', $format->id), [
        'nombre' => 'A', 'descripcion' => str_repeat('x', 256),
    ])->assertSessionHasErrors(['nombre', 'descripcion']);
    $this->patch(route('admin.output-formats.update', $format->id), [
        'nombre' => 'Informe', 'descripcion' => '', 'activo' => '1',
    ])->assertSessionHasErrors('nombre');
});

test('administrators version templates and drafts retain the selected content', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $original = TramitePlantilla::factory()->create();
    $originalContent = $original->contenido;
    $newContent = '<p>{{NUMERO_DOCUMENTO_PREVIO}}</p><script>alert(1)</script><p>{{CONTENIDO_PRINCIPAL}}</p>';
    $payload = [
        'nombre' => 'Informe versión dos',
        'descripcion' => 'Contenido institucional revisado',
        'contenido' => $newContent,
        'publicar' => '0',
    ];

    $this->get(route('admin.templates.index'))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('admin.templates.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->post(route('admin.templates.version', $original), $payload)->assertForbidden();
    $this->patch(route('admin.templates.state', $original), ['activa' => '0'])->assertForbidden();

    $this->actingAs($admin)->get(route('admin.templates.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('plantillas')->has('plantillas'));
    $this->post(route('admin.templates.version', $original), [
        ...$payload, 'contenido' => '<p>{{CONTENIDO_PRINCIPAL}}</p>',
    ])->assertSessionHasErrors('contenido');
    expect(TramitePlantilla::query()->where('codigo', $original->codigo)->count())->toBe(1);

    $this->post(route('admin.templates.version', $original), $payload)
        ->assertRedirect(route('admin.templates.index'));
    $second = TramitePlantilla::query()->where('codigo', $original->codigo)->where('version', 2)->sole();
    expect($second->estado)->toBe('borrador')
        ->and($second->activa)->toBeFalse()
        ->and($second->contenido)->not->toContain('<script>')
        ->and($original->fresh()->contenido)->toBe($originalContent)
        ->and($original->fresh()->activa)->toBeTrue();

    $this->patch(route('admin.templates.state', $second), ['activa' => '1'])
        ->assertRedirect(route('admin.templates.index'));
    expect($second->fresh()->activa)->toBeTrue()
        ->and($second->fresh()->estado)->toBe('publicada')
        ->and($original->fresh()->activa)->toBeFalse();

    $tramite = Tramite::factory()->create(['estado' => 'digitalizado', 'propietario_id' => $student->id]);
    $draftPayload = [
        'plantilla_id' => $second->id,
        'fecha_documento' => now()->toDateString(),
        'lugar' => 'Lima',
        'asunto' => 'Documento de prueba',
        'contenido_principal' => 'Texto revisado sin publicar.',
        'preparar' => false,
    ];
    $this->actingAs($officeAdmin)->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plantillas', fn ($plantillas): bool => collect($plantillas)->contains('id', $second->id)
            && ! collect($plantillas)->contains('id', $original->id))->etc());
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$draftPayload, 'plantilla_id' => $original->id,
    ])->assertSessionHasErrors('plantilla_id');
    $this->post(route('tramites.borradores.store', $tramite), $draftPayload)
        ->assertRedirect(route('tramites.show', $tramite));
    $firstDraft = $tramite->borradores()->sole();
    expect($firstDraft->version_plantilla)->toBe(2)
        ->and($firstDraft->contenido_plantilla_snapshot)->toBe($second->contenido)
        ->and($firstDraft->contenido_renderizado)->toContain('BORRADOR SIN NUMERACIÓN OFICIAL')
        ->toContain('Texto revisado sin publicar.')
        ->not->toContain('<script>');
    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('borrador')
        ->missing('contenido_renderizado'));

    $this->actingAs($admin)->post(route('admin.templates.version', $second), [
        ...$payload, 'nombre' => 'Informe versión tres', 'contenido' => '<p>{{NUMERO_DOCUMENTO_PREVIO}} {{CONTENIDO_PRINCIPAL}} Cambio</p>', 'publicar' => '1',
    ])->assertRedirect(route('admin.templates.index'));
    $third = TramitePlantilla::query()->where('codigo', $original->codigo)->where('version', 3)->sole();
    expect($third->activa)->toBeTrue()
        ->and($second->fresh()->activa)->toBeFalse()
        ->and($firstDraft->fresh()->contenido_plantilla_snapshot)->toBe($second->contenido)
        ->and($firstDraft->fresh()->contenido_renderizado)->toBe($firstDraft->contenido_renderizado)
        ->and(DB::table('tramite_config_events')->where('entidad', 'plantilla')->count())->toBe(3);
});

test('template fields are versioned and all active values enter immutable draft snapshots', function () {
    $this->seed(TramitePlantillaSeeder::class);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $original = TramitePlantilla::query()->where('codigo', 'INFORME_INSTITUCIONAL')->sole();
    $senderPosition = DB::table('cargos_institucionales')->where('codigo', 'director_general')->first();
    $signerPosition = DB::table('cargos_institucionales')->where('codigo', 'docente')->first();
    $admin->update(['cargo_institucional_id' => $senderPosition->id]);
    $signer = User::factory()->create(['rol' => 'docente', 'cargo_institucional_id' => $signerPosition->id]);
    $fields = DB::table('tramite_plantilla_campos')->where('plantilla_id', $original->id);
    expect($fields->count())->toBe(26);
    $turno = DB::table('tramite_plantilla_campos')->where('plantilla_id', $original->id)
        ->where('clave_variable', 'TURNO')->sole();
    $changes = [
        'etiqueta' => 'Turno académico', 'grupo' => 'Datos académicos', 'tipo_campo' => 'select',
        'obligatorio' => '0', 'requiere_confirmacion' => '0', 'permite_html' => '0',
        'longitud_maxima' => '30', 'texto_ayuda' => 'Turno del estudiante', 'activo' => '1',
    ];

    $this->actingAs(User::factory()->create(['rol' => 'docente']))->get(route('admin.templates.fields', $original))->assertForbidden();
    $this->patch(route('admin.templates.fields.update', [$original, $turno->id]), $changes)->assertForbidden();
    $newTemplate = [
        'codigo' => 'INFORME_NUEVO', 'nombre' => 'Informe nuevo', 'descripcion' => 'Prueba local',
        'tipo_documento_salida' => 'informe', 'modalidad' => 'sin_modalidad',
        'contenido' => '<article><h1>{{NUMERO_DOCUMENTO_PREVIO}}</h1><p>{{CONTENIDO_PRINCIPAL}}</p></article>',
    ];
    $this->post(route('admin.templates.store'), $newTemplate)->assertForbidden();
    $this->actingAs($admin)->get(route('admin.templates.fields', $original))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('plantilla-campos')->has('campos', 26));
    $this->post(route('admin.templates.store'), [...$newTemplate, 'codigo' => 'MAL CODIGO'])
        ->assertSessionHasErrors('codigo');
    $this->post(route('admin.templates.store'), [...$newTemplate, 'modalidad' => 'multiple'])
        ->assertSessionHasErrors('modalidad');
    $this->post(route('admin.templates.store'), $newTemplate)->assertRedirect(route('admin.templates.index'));
    $created = TramitePlantilla::query()->where('codigo', 'INFORME_NUEVO')->sole();
    expect($created->activa)->toBeFalse()
        ->and($created->estado)->toBe('borrador')
        ->and(DB::table('tramite_plantilla_campos')->where('plantilla_id', $created->id)->count())->toBe(26);
    $this->post(route('admin.templates.store'), $newTemplate)->assertSessionHasErrors('codigo');
    $this->patch(route('admin.templates.fields.update', [$original, $turno->id]), $changes)
        ->assertRedirect(route('admin.templates.fields', $original));
    expect(DB::table('tramite_plantilla_campos')->where('id', $turno->id)->value('etiqueta'))->toBe('Turno académico');
    $this->patch(route('admin.templates.fields.move', [$original, $turno->id]), ['direccion' => 'up'])
        ->assertRedirect(route('admin.templates.fields', $original));
    expect(DB::table('tramite_plantilla_campos')->where('id', $turno->id)->value('orden'))->toBe(34);

    $this->post(route('admin.templates.version', $original), [
        'nombre' => 'Informe con turno', 'descripcion' => '',
        'contenido' => '<p>{{NUMERO_DOCUMENTO_PREVIO}}</p><p>{{TURNO}}</p><p>{{CONTENIDO_PRINCIPAL}}</p>',
        'publicar' => '1',
    ])->assertRedirect(route('admin.templates.index'));
    $version = TramitePlantilla::query()->where('codigo', $original->codigo)->where('version', 2)->sole();
    expect(DB::table('tramite_plantilla_campos')->where('plantilla_id', $version->id)->count())->toBe(26);
    $program = ProgramaEstudio::query()->where('codigo', 'DESARROLLO_SISTEMAS_INFORMACION')->firstOrFail();
    $student->update(['name' => 'María Estudiante', 'dni' => '12345678']);
    $profile = PerfilEstudiante::factory()->create([
        'user_id' => $student->id, 'programa_estudio_id' => $program->id,
        'codigo_estudiante' => '12345678', 'ciclo_actual' => 5,
    ]);
    $tramite = Tramite::factory()->create([
        'estado' => 'digitalizado', 'propietario_id' => $student->id,
        'programa_estudio_id' => $program->id,
    ]);
    $payload = [
        'plantilla_id' => $version->id, 'fecha_documento' => now()->toDateString(),
        'remitente_id' => $admin->id, 'firmante_id' => $signer->id,
        'lugar' => 'Lima', 'asunto' => 'Informe de prueba',
        'contenido_principal' => 'Contenido de prueba local.', 'preparar' => false,
        'destinatarios' => [['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'cargo' => 'Coordinadora', 'principal' => true]],
        'personas_mencionadas' => [
            ['nombres' => 'José', 'apellidos' => 'Torres', 'cargo' => 'Secretario', 'dni' => '87654321'],
            ['nombres' => 'Ana', 'apellidos' => 'Sin DNI', 'cargo' => 'Invitada'],
        ],
        'campos' => ['TURNO' => 'Mañana', 'ENCABEZADO_INSTITUCIONAL' => 'Instituto local'],
    ];
    $this->actingAs($officeAdmin)->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plantillas', fn ($plantillas): bool => collect($plantillas)
            ->contains(fn ($plantilla): bool => $plantilla['id'] === $version->id
                && collect($plantilla['campos'])->contains('clave', 'TURNO')))->etc());
    $signer->update(['cargo_institucional_id' => null]);
    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('usuarios', fn ($users): bool => ! collect($users)
            ->contains('id', $signer->id)));
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$payload, 'preparar' => true,
        'contenido_principal' => 'Contenido completo para probar la validación del firmante.',
    ])->assertSessionHasErrors('firmante_id');
    $signer->update(['cargo_institucional_id' => $signerPosition->id]);
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$payload, 'campos' => ['ESTUDIANTE_NOMBRE' => 'Falsificado'],
    ])->assertSessionHasErrors('campos');
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$payload,
        'personas_mencionadas' => [['nombres' => 'José', 'dni' => '123']],
    ])->assertSessionHasErrors('personas_mencionadas.0.dni');
    $this->post(route('tramites.borradores.store', $tramite), $payload)
        ->assertRedirect(route('tramites.show', $tramite));
    $draft = $tramite->borradores()->sole();
    $values = DB::table('tramite_borrador_valores as value')
        ->join('tramite_plantilla_campos as field', 'field.id', '=', 'value.campo_id')
        ->where('value.borrador_id', $draft->id)->pluck('value.valor', 'field.clave_variable');
    expect($draft->contenido_renderizado)->toContain('Mañana')
        ->and($draft->personas_mencionadas)->toBe([
            ['nombres' => 'José', 'apellidos' => 'Torres', 'cargo' => 'Secretario', 'dni' => '87654321'],
            ['nombres' => 'Ana', 'apellidos' => 'Sin DNI', 'cargo' => 'Invitada', 'dni' => null],
        ])
        ->and($values)->toHaveCount(26)
        ->and($values['TURNO'])->toBe('Mañana')
        ->and($values['ESTUDIANTE_NOMBRE'])->toBe('María Estudiante')
        ->and($values['DNI'])->toBe('12345678')
        ->and($values['CODIGO_ESTUDIANTE'])->toBe($student->dni)
        ->and($values['CICLO'])->toBe('5')
        ->and($values['PROGRAMA_ESTUDIO'])->toBe('Desarrollo de Sistemas de Información')
        ->and($values['DESTINATARIO_NOMBRE'])->toBe('Ana Ruiz')
        ->and($values['DESTINATARIO_CARGO'])->toBe('Coordinadora')
        ->and($values['LISTA_DESTINATARIOS'])->toBe('Ana Ruiz — Coordinadora')
        ->and($values['LISTA_PERSONAS_MENCIONADAS'])->toBe('José Torres — Secretario DNI: 87654321; Ana Sin DNI — Invitada')
        ->and($values['REMITENTE_CARGO'])->toBe('Director General')
        ->and($values['FIRMANTE_CARGO'])->toBe('Docente')
        ->and($values['LUGAR_FECHA'])->toContain('Lima, ')
        ->and($values['ENCABEZADO_INSTITUCIONAL'])->toBe('Instituto local');
    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('borrador')->missing('campos'));
    $this->actingAs($officeAdmin);
    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('borrador.campos.TURNO', 'Mañana')
            ->missing('borrador.campos.DNI'));
    $profile->update(['codigo_estudiante' => 'EST-456']);
    $this->post(route('tramites.borradores.store', $tramite), [
        ...$payload, 'campos' => [...$payload['campos'], 'TURNO' => 'Tarde'],
    ])->assertRedirect(route('tramites.show', $tramite));
    $nextDraft = $tramite->borradores()->where('es_actual', true)->sole();
    expect(DB::table('tramite_borrador_valores')->where('borrador_id', $draft->id)->count())->toBe(26)
        ->and(DB::table('tramite_borrador_valores as value')
            ->join('tramite_plantilla_campos as field', 'field.id', '=', 'value.campo_id')
            ->where('value.borrador_id', $draft->id)->where('field.clave_variable', 'CODIGO_ESTUDIANTE')
            ->value('value.valor'))->toBe($student->dni)
        ->and(DB::table('tramite_borrador_valores as value')
            ->join('tramite_plantilla_campos as field', 'field.id', '=', 'value.campo_id')
            ->where('value.borrador_id', $nextDraft->id)->where('field.clave_variable', 'CODIGO_ESTUDIANTE')
            ->value('value.valor'))->toBe($student->dni);
    $previewUrl = route('tramites.borradores.show', [$tramite, $draft]);
    $this->post(route('logout'));
    $this->get($previewUrl)->assertRedirect(route('login'));
    $this->actingAs($student)->get($previewUrl)->assertForbidden();
    $this->actingAs($signer)->get($previewUrl)->assertForbidden();
    $this->actingAs($officeAdmin)->get($previewUrl)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/borrador-preview')
            ->where('borrador.version', 1)
            ->where('borrador.actual', false)
            ->where('borrador.puede_pdf', true)
            ->where('borrador.contenido', $draft->contenido_renderizado));
    $otherTramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $this->get(route('tramites.borradores.show', [$otherTramite, $draft]))->assertNotFound();
    $this->actingAs($admin)->get($previewUrl)->assertOk();
    $draft->update(['contenido_plantilla_snapshot' => null, 'contenido_renderizado' => null]);
    $this->actingAs($officeAdmin)->get($previewUrl)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('borrador.puede_pdf', false));
    $this->get(route('tramites.borradores.pdf', [$tramite, $draft]))->assertStatus(409);
    $this->actingAs($admin)->patch(route('admin.templates.fields.update', [$version, DB::table('tramite_plantilla_campos')
        ->where('plantilla_id', $version->id)->where('clave_variable', 'TURNO')->value('id')]), $changes)
        ->assertStatus(409);
    $this->patch(route('admin.templates.fields.move', [$version, DB::table('tramite_plantilla_campos')
        ->where('plantilla_id', $version->id)->where('clave_variable', 'TURNO')->value('id')]), ['direccion' => 'down'])
        ->assertStatus(409);
});

test('reception keeps preliminary lists private, validates type requirements, and preserves edit history', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $this->actingAs($officeAdmin);
    DB::table('tipos_tramite')->where('codigo', 'INFORME')->update([
        'requiere_personas_relacionadas' => true,
    ]);
    $this->post(route('tramites.start'), [
        'tipo_documento' => 'INFORME',
    ])->assertRedirect(route('tramites.create'));
    $payload = [
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'INFORME',
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Autorización presentada físicamente',
        'descripcion' => 'Documento recibido en Mesa de Partes.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'formato_salida' => 'informe',
        'modalidad_documento' => null,
        'confirmar_recepcion' => '1',
    ];
    $this->get(route('tramites.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('catalogos.requisitos_tipo.INFORME.personas_relacionadas', true)
        ->where('catalogos.requisitos_tipo.INFORME.destinatarios_multiples', false));
    $this->post(route('tramites.store'), $payload)->assertSessionHasErrors('personas_relacionadas');
    $related = ['nombres' => 'Lucía Pérez', 'apellidos' => 'Gómez', 'dni' => '12345678', 'tipo_relacion' => 'interesado'];
    $this->post(route('tramites.store'), [...$payload, 'personas_relacionadas' => [[...$related, 'dni' => '123']]])
        ->assertSessionHasErrors('personas_relacionadas.0.dni');
    $this->post(route('tramites.store'), [
        ...$payload, 'personas_relacionadas' => [[...$related, 'tipo_relacion' => 'ajeno']],
    ])->assertSessionHasErrors('personas_relacionadas.0.tipo_relacion');
    expect(Tramite::query()->count())->toBe(0);

    $lists = [
        'personas_relacionadas' => [$related],
        'destinatarios' => [['nombres' => 'Ana Ruiz', 'correo_institucional' => 'ana@example.edu.pe']],
        'personas_mencionadas' => [['nombres' => 'José Torres', 'descripcion' => 'Representante']],
    ];
    $this->post(route('tramites.store'), [...$payload, ...$lists])->assertRedirect()->assertSessionHasNoErrors();
    $tramite = Tramite::query()->sole();
    expect(DB::table('personas_relacionadas_expediente')->where('tramite_id', $tramite->id)->where('activo', true)->count())->toBe(1)
        ->and(DB::table('documento_destinatarios')->where('tramite_id', $tramite->id)->where('es_destinatario_principal', true)->count())->toBe(1)
        ->and(DB::table('documento_personas_mencionadas')->where('tramite_id', $tramite->id)->count())->toBe(1);
    $this->get(route('tramites.show', $tramite))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.personas_relacionadas.0.dni', '12345678')
        ->where('tramite.destinatarios.0.correo_institucional', 'ana@example.edu.pe')
        ->where('tramite.personas_mencionadas.0.descripcion', 'Representante'));
    $this->get(route('tramites.edit', $tramite))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.personas_relacionadas.0.nombres', 'Lucía Pérez'));
    $this->actingAs($student)->get(route('tramites.show', $tramite))->assertForbidden();
    $this->actingAs($student)->get(route('tramites.edit', $tramite))->assertForbidden();
    $this->actingAs($officeAdmin);

    $edit = $payload;
    unset($edit['confirmar_recepcion']);
    $this->put(route('tramites.update', $tramite), [
        ...$edit, 'personas_relacionadas' => [['nombres' => 'Nueva persona', 'tipo_relacion' => 'participante']],
    ])->assertRedirect(route('tramites.show', $tramite));
    expect(DB::table('personas_relacionadas_expediente')->where('tramite_id', $tramite->id)->count())->toBe(2)
        ->and(DB::table('personas_relacionadas_expediente')->where('tramite_id', $tramite->id)->where('activo', false)->count())->toBe(1)
        ->and(DB::table('documento_destinatarios')->where('tramite_id', $tramite->id)->where('activo', false)->count())->toBe(1)
        ->and(DB::table('documento_personas_mencionadas')->where('tramite_id', $tramite->id)->where('activo', false)->count())->toBe(1);
    $this->get(route('tramites.show', $tramite))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.personas_relacionadas.0.nombres', 'Nueva persona')
        ->has('tramite.destinatarios', 0)->has('tramite.personas_mencionadas', 0));
    $this->put(route('tramites.update', $tramite), $edit)->assertSessionHasErrors('personas_relacionadas');
    expect(DB::table('personas_relacionadas_expediente')->where('tramite_id', $tramite->id)->where('activo', true)->count())->toBe(1);
});

test('reception enforces multiple recipients, active positions, and a required original file', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($officeAdmin);
    DB::table('tipos_tramite')->where('codigo', 'MEMORANDO_MULTIPLE')->update([
        'requiere_destinatarios_multiples' => true,
        'requiere_documento_original' => true,
    ]);
    $this->post(route('tramites.start'), [
        'tipo_documento' => 'MEMORANDO_MULTIPLE',
    ])->assertRedirect(route('tramites.create'));
    $payload = [
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'MEMORANDO_MULTIPLE',
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Comunicación física',
        'descripcion' => 'Comunicación recibida en oficina.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'formato_salida' => 'memorando',
        'modalidad_documento' => 'multiple',
        'confirmar_recepcion' => '1',
    ];
    $this->post(route('tramites.store'), [...$payload, 'destinatarios' => [['nombres' => 'Una persona']]])
        ->assertSessionHasErrors('destinatarios');
    $cargoId = DB::table('cargos_institucionales')->value('id');
    DB::table('cargos_institucionales')->where('id', $cargoId)->update(['activo' => false]);
    $destinatarios = [
        ['nombres' => 'Primera persona', 'cargo_institucional_id' => $cargoId],
        ['nombres' => 'Segunda persona', 'correo_institucional' => 'no-es-correo'],
    ];
    $this->post(route('tramites.store'), [...$payload, 'destinatarios' => $destinatarios])
        ->assertSessionHasErrors(['destinatarios.0.cargo_institucional_id', 'destinatarios.1.correo_institucional']);
    DB::table('cargos_institucionales')->where('id', $cargoId)->update(['activo' => true]);
    $destinatarios[1]['correo_institucional'] = 'segunda@example.edu.pe';
    $this->post(route('tramites.store'), [...$payload, 'destinatarios' => $destinatarios])
        ->assertSessionHasErrors('documentos');
    $pdf = "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF";
    $this->post(route('tramites.store'), [
        ...$payload,
        'destinatarios' => $destinatarios,
        'documentos' => [['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('original.pdf', $pdf)]],
    ])->assertRedirect();
    $tramite = Tramite::query()->sole();
    expect($tramite->estado)->toBe('digitalizado')
        ->and(DB::table('documento_destinatarios')->where('tramite_id', $tramite->id)->count())->toBe(2)
        ->and(DB::table('documento_destinatarios')->where('tramite_id', $tramite->id)->where('es_destinatario_principal', true)->value('nombres'))->toBe('Primera persona');
});

test('draft suggests the director and active teachers as editable recipients', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create([
        'rol' => 'docente',
        'nombres' => 'Henry Bernardo',
        'apellidos' => 'Arteaga Chauca',
        'name' => 'Henry Bernardo Arteaga Chauca',
    ]);
    $inactiveTeacher = User::factory()->create(['rol' => 'docente', 'activo' => false, 'estado_cuenta' => 'inactivo']);
    $tramite = Tramite::factory()->create(['estado' => 'digitalizado']);

    $this->actingAs($administrator)->get(route('tramites.borradores.create', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('destinatarios_sugeridos.0.nombres', 'Mg. RAUL WILLIAM')
        ->where('destinatarios_sugeridos.0.apellidos', 'LOPEZ REYNA')
        ->where('destinatarios_sugeridos', fn ($people): bool => collect($people)
            ->contains('key', 'docente-'.$teacher->id)
            && ! collect($people)->contains('key', 'docente-'.$inactiveTeacher->id)));
});

test('reception validates and preserves the planned output format and memorandum modality', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $admin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000005']);
    $this->actingAs($officeAdmin)->post(route('tramites.start'), [
        'tipo_documento' => 'JUSTIFICACION_TARDANZA', 'dni' => $student->dni,
    ])->assertRedirect(route('tramites.create'));
    $this->get(route('tramites.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('catalogos.tipos_documento.estudiantil.FUT')
            ->where('catalogos.tipos_documento.estudiantil.CONSTANCIA_MODALIDAD_TITULACION', 'Constancia de modalidad de examen de titulación')
            ->where('catalogos.formatos_salida.informe', 'Informe')
            ->where('catalogos.formatos_salida.memorando', 'Memorando')
            ->where('catalogos.formatos_salida.constancia', 'Constancia')
            ->where('catalogos.modalidades_documento.simple', 'Simple')
            ->where('catalogos.modalidades_documento.multiple', 'Múltiple')
            ->missing('catalogos.formatos_sugeridos.AUTORIZACION_INGRESO')
            ->where('catalogos.formatos_sugeridos.INFORME', 'informe')
            ->where('catalogos.formatos_sugeridos.MEMORANDO_SIMPLE', 'memorando')
            ->where('catalogos.requisitos_tipo.MEMORANDO_SIMPLE.modalidad_documento', 'simple')
            ->where('catalogos.requisitos_tipo.MEMORANDO_MULTIPLE.modalidad_documento', 'multiple')
            ->where('catalogos.formatos_sugeridos.CONSTANCIA_MODALIDAD_TITULACION', 'constancia'));

    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'JUSTIFICACION_TARDANZA',
        'propietario_id' => $student->id,
        'persona_nombre' => 'Persona de prueba',
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Justificación de tardanza recibida físicamente',
        'descripcion' => 'Documento presentado en mesa de partes.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'formato_salida' => 'memorando',
        'modalidad_documento' => 'simple',
        'confirmar_recepcion' => '1',
    ];

    $this->post(route('tramites.store'), [...$payload, 'formato_salida' => 'informe', 'modalidad_documento' => null])
        ->assertSessionHasErrors('formato_salida');
    $this->post(route('tramites.store'), [...$payload, 'modalidad_documento' => null])
        ->assertSessionHasErrors('modalidad_documento');
    $this->post(route('tramites.store'), [...$payload, 'modalidad_documento' => 'desconocida'])
        ->assertSessionHasErrors('modalidad_documento');
    expect(Tramite::query()->count())->toBe(0);

    $this->post(route('tramites.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $tramite = Tramite::query()->sole();
    expect($tramite->formato_salida)->toBe('memorando')
        ->and($tramite->modalidad_documento)->toBe('simple');
    $this->get(route('tramites.show', $tramite))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.formato_salida', 'Memorando')
        ->where('tramite.modalidad_documento', 'Simple'));
    $this->get(route('tramites.edit', $tramite))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.formato_salida', 'memorando')
        ->where('tramite.modalidad_documento', 'simple'));

    $edit = $payload;
    unset($edit['confirmar_recepcion']);
    $this->put(route('tramites.update', $tramite), [...$edit, 'modalidad_documento' => 'multiple'])
        ->assertRedirect(route('tramites.show', $tramite));
    expect($tramite->fresh()->modalidad_documento)->toBe('multiple');
    $this->put(route('tramites.update', $tramite), [
        ...$edit, 'tipo_documento' => 'FUT', 'formato_salida' => 'informe', 'modalidad_documento' => 'simple',
    ])->assertSessionHasErrors('tipo_documento');
    expect($tramite->fresh()->formato_salida)->toBe('memorando');

    $this->put(route('tramites.update', $tramite), [
        ...$edit, 'tipo_documento' => 'FUT', 'formato_salida' => 'informe', 'modalidad_documento' => null,
    ])->assertSessionHasErrors('tipo_documento');
    expect($tramite->fresh()->formato_salida)->toBe('memorando');

    $formato = DB::table('tipos_documento_salida')->where('codigo', 'memorando')->first();
    $this->actingAs($admin)->patch(route('admin.output-formats.update', $formato->id), [
        'nombre' => 'Memorando', 'descripcion' => $formato->descripcion, 'activo' => '0',
    ])->assertRedirect();
    $this->actingAs($officeAdmin)->post(route('tramites.store'), [
        ...$payload, 'tipo_documento' => 'FUT',
    ])->assertSessionHasErrors('tipo_documento');
    expect(Tramite::query()->count())->toBe(1);

    $this->post(route('tramites.store'), [
        ...$payload, 'tipo_documento' => 'FUT', 'formato_salida' => 'pendiente', 'modalidad_documento' => null,
    ])->assertSessionHasErrors('tipo_documento');
    expect(Tramite::query()->whereNull('formato_salida')->count())->toBe(0);
});

test('reception lists active teachers and saves the selected teacher as planned destination', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente', 'name' => 'Docente Activo', 'activo' => true, 'estado_cuenta' => 'activo']);
    $inactiveTeacher = User::factory()->create(['rol' => 'docente', 'name' => 'Docente Inactivo', 'activo' => false, 'estado_cuenta' => 'inactivo']);
    $student = User::factory()->create(['rol' => 'estudiante']);

    $this->actingAs($officeAdmin)->post(route('tramites.start'), [
        'tipo_documento' => 'CONSTANCIA_PRACTICA', 'dni' => '90000007',
    ])->assertRedirect(route('tramites.create'));
    $this->get(route('tramites.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('docentes', fn ($docentes): bool => collect($docentes)->contains('id', $teacher->id)
            && ! collect($docentes)->contains('id', $inactiveTeacher->id)));

    $payload = [
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona de prueba',
        'destino_tipo' => 'docente',
        'destino_docente_id' => $teacher->id,
        'destino_nombre' => 'Nombre alterado por el cliente',
        'asunto' => 'Solicitud para revisión docente',
        'descripcion' => 'Recepción local de prueba.',
        'prioridad' => 'normal',
        'formato_salida' => 'informe',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
    ];

    $this->post(route('tramites.store'), [...$payload, 'destino_docente_id' => $student->id])
        ->assertSessionHasErrors('destino_docente_id');
    $this->post(route('tramites.store'), [...$payload, 'destino_docente_id' => $inactiveTeacher->id])
        ->assertSessionHasErrors('destino_docente_id');
    $this->post(route('tramites.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $tramite = Tramite::query()->sole();
    expect($tramite->destino_docente_id)->toBe($teacher->id)
        ->and($tramite->destino_nombre)->toBe('Docente Activo');
    $this->get(route('tramites.edit', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramite.destino_docente_id', $teacher->id));

    $update = [...$payload, 'destino_tipo' => 'oficina', 'destino_nombre' => 'Secretaría Académica'];
    unset($update['confirmar_recepcion'], $update['destino_docente_id']);
    $this->put(route('tramites.update', $tramite), $update)->assertRedirect(route('tramites.show', $tramite));
    expect($tramite->fresh()->destino_docente_id)->toBeNull()
        ->and($tramite->fresh()->destino_nombre)->toBe('Secretaría Académica');

    $prepared = Tramite::factory()->create([
        'destino_tipo' => 'docente',
        'destino_docente_id' => $teacher->id,
        'destino_nombre' => $teacher->name,
        'estado' => 'pendiente_asignacion',
    ]);
    TramiteBorrador::factory()->create([
        'tramite_id' => $prepared->id,
        'estado' => 'preparado_asignacion',
        'es_actual' => true,
    ]);
    $this->get(route('tramites.asignaciones.create', $prepared))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('destino_inicial', 'docente')
            ->where('revisor_sugerido_id', $teacher->id));
});

test('office administrator can receive a request for a title modality constancia and prepare its draft', function () {
    $this->seed(TramitePlantillaSeeder::class);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000006']);
    PerfilEstudiante::factory()->create(['user_id' => $student->id]);
    $plantilla = TramitePlantilla::query()->where('codigo', 'CONSTANCIA_MODALIDAD_TITULACION')->sole();
    expect(DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)->count())->toBe(26);

    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION',
        'propietario_id' => $student->id,
        'persona_nombre' => $student->name,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría Académica',
        'asunto' => 'Constancia de modalidad de examen de titulación',
        'descripcion' => 'Solicitud presentada en el formulario de recepción.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'formato_salida' => 'constancia',
        'confirmar_recepcion' => '1',
    ];

    $this->actingAs($officeAdmin)->post(route('tramites.store'), [
        ...$payload, 'formato_salida' => 'pendiente',
    ])->assertSessionHasErrors('formato_salida');
    $this->post(route('tramites.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $tramite = Tramite::query()->sole();
    expect($tramite->formato_salida)->toBe('constancia');
    $tramite->forceFill(['estado' => 'digitalizado'])->save();
    $this->get(route('tramites.borradores.create', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plantillas', fn ($plantillas): bool => collect($plantillas)->contains('id', $plantilla->id))->etc());
    $this->post(route('tramites.borradores.store', $tramite), [
        'plantilla_id' => $plantilla->id,
        'fecha_documento' => now()->toDateString(),
        'lugar' => 'Lima',
        'asunto' => $payload['asunto'],
        'contenido_principal' => 'Se solicita acreditar la modalidad de examen de titulación.',
        'preparar' => false,
    ])->assertRedirect(route('tramites.show', $tramite));
    expect($tramite->borradores()->sole()->contenido_renderizado)
        ->toContain('CONSTANCIA DE MODALIDAD DE EXAMEN DE TITULACIÓN', $student->name);

    $this->post(route('tramites.store'), [...$payload, 'tipo_documento' => 'FUT'])->assertSessionHasErrors('tipo_documento');
    expect(Tramite::query()->where('tipo_documento', 'FUT')->where('formato_salida', 'constancia')->count())->toBe(0);
});

test('office administrator intake can be digitized, searched, audited, and downloaded privately through Turso', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $officeAdminId = null;
    $studentId = null;
    $foreignStudentId = null;
    $senderId = null;
    $signerId = null;
    $secondReviewerId = null;
    $inactiveReviewerId = null;
    $plantillaId = null;
    $tramiteId = null;
    $reservedNumber = null;
    $year = (int) now()->format('Y');
    $sequenceBefore = DB::table('tramite_secuencias')->where('anio', $year)->value('ultimo_numero');
    $marker = 'CODEX-'.Str::upper(Str::random(12));

    try {
        $officeAdmin = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $officeAdminId = $officeAdmin->id;
        $student = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $studentId = $student->id;
        $foreignStudent = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $foreignStudentId = $foreignStudent->id;
        $this->actingAs($officeAdmin);

        $this->get(route('tramites.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('estudiantes'));

        $payload = [
            'clasificacion' => 'estudiantil',
            'tipo_documento' => 'FUT',
            'persona_nombre' => 'Persona ficticia de prueba',
            'persona_identificador' => $marker,
            'propietario_id' => $studentId,
            'destino_tipo' => 'oficina',
            'destino_nombre' => 'Secretaría de prueba',
            'asunto' => 'Solicitud ficticia de prueba',
            'descripcion' => 'Registro artificial para la prueba automatizada.',
            'prioridad' => 'normal',
            'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
            'folios' => 2,
            'confirmar_recepcion' => '1',
        ];

        $this->from(route('tramites.create'))
            ->post(route('tramites.store'), [...$payload, 'propietario_id' => null])
            ->assertSessionHasErrors('propietario_id');

        $this->from(route('tramites.create'))
            ->post(route('tramites.store'), [
                ...$payload,
                'documentos' => [['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')]],
            ])
            ->assertSessionHasErrors('documentos.0.archivo');

        $this->post(route('tramites.store'), $payload)->assertRedirect();

        $tramite = DB::table('tramites')->where('persona_identificador', $marker)->first();
        expect($tramite)->not->toBeNull();

        $tramiteId = (int) $tramite->id;
        $codigo = $tramite->codigo;
        $reservedNumber = (int) substr($codigo, -6);
        expect($tramite->estado)->toBe('recibido_oficina');
        expect((int) $tramite->propietario_id)->toBe($studentId);
        expect($codigo)->toMatch('/^TRM-\d{4}-\d{6}$/');

        $this->actingAs($student)
            ->get(route('estudiante.tramites.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/estudiante-index')
                ->where('tramites.data.0.codigo', $codigo));
        $this->get(route('estudiante.tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/estudiante-show')
                ->where('tramite.estado_label', 'Recibido en oficina')
                ->missing('borrador')
                ->missing('eventos'));
        $this->actingAs($foreignStudent)->get(route('estudiante.tramites.show', $tramiteId))->assertForbidden();
        $this->actingAs($officeAdmin);

        $this->get(route('tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/show')
                ->where('tramite.codigo', $codigo)
                ->where('tramite.estado', 'recibido_oficina')
                ->has('tramite.documentos', 0));

        $pdfContents = "%PDF-1.4\n% Artificial test fixture\n1 0 obj <<>> endobj\n%%EOF";
        $this->post(route('tramites.documentos.store', $tramiteId), [
            'documento' => UploadedFile::fake()->createWithContent('escaneo-prueba.pdf', $pdfContents),
        ])->assertRedirect(route('tramites.show', $tramiteId));

        $documento = DB::table('tramite_documentos')->where('tramite_id', $tramiteId)->first();
        expect($documento)->not->toBeNull();
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('digitalizado');
        $privateRoot = realpath((string) config('filesystems.disks.local.root'));
        $privateFile = realpath(Storage::disk('local')->path($documento->ruta));
        expect((int) $documento->tramite_id)->toBe($tramiteId);
        expect($documento->disco)->toBe('local');
        expect($privateRoot)->not->toBeFalse();
        expect($privateFile)->not->toBeFalse();
        expect(str_starts_with((string) $privateFile, rtrim((string) $privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))->toBeTrue();
        expect(hash_file('sha256', (string) $privateFile))->toBe($documento->sha256);

        $this->get(route('tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/show')
                ->where('tramite.estado', 'digitalizado')
                ->has('tramite.documentos', 1)
                ->missing('tramite.documentos.0.ruta')
                ->where('tramite.eventos.2.accion', 'digitalizacion'));

        $this->get(route('tramites.index', ['q' => $marker]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/index')
                ->where('tramites.data.0.codigo', $codigo)
                ->where('tramites.data.0.tipo_documento', 'Formulario Único de Trámite')
                ->where('tramites.data.0.estado_label', 'Digitalizado'));

        $this->get(route('tramites.documentos.descargar', [$tramiteId, $documento->id]))
            ->assertOk()
            ->assertDownload('escaneo-prueba.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $remitente = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
            'cargo_institucional_id' => DB::table('cargos_institucionales')->where('codigo', 'director_general')->value('id'),
        ]);
        $senderId = $remitente->id;
        $firmante = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
            'cargo_institucional_id' => DB::table('cargos_institucionales')->where('codigo', 'docente')->value('id'),
        ]);
        $signerId = $firmante->id;
        $plantilla = TramitePlantilla::factory()->create([
            'tipo_documento_salida' => 'memorando',
            'modalidad' => 'simple',
        ]);
        $plantillaId = $plantilla->id;

        $this->get(route('tramites.borradores.create', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/borrador')
                ->where('tramite.codigo', $codigo)
                ->has('plantillas')
                ->has('archivos', 1));

        $draftPayload = [
            'plantilla_id' => $plantillaId,
            'remitente_id' => $senderId,
            'firmante_id' => $signerId,
            'fecha_documento' => now()->toDateString(),
            'lugar' => 'Lima',
            'asunto' => 'Borrador ficticio de prueba',
            'introduccion' => 'Introducción de prueba.',
            'contenido_principal' => '',
            'cierre' => 'Atentamente.',
            'destinatarios' => [[
                'nombres' => 'Destinatario ficticio',
                'apellidos' => 'Prueba',
                'cargo' => 'Dirección de prueba',
                'correo' => 'destinatario@example.test',
                'principal' => true,
            ]],
            'personas_mencionadas' => [[
                'nombres' => 'Persona mencionada ficticia',
                'apellidos' => 'Prueba',
                'cargo' => 'Docente',
            ]],
            'adjuntos' => [(int) $documento->id],
            'preparar' => false,
            'confirmar_fecha_anterior' => false,
        ];

        $this->post(route('tramites.borradores.store', $tramiteId), $draftPayload)
            ->assertRedirect(route('tramites.show', $tramiteId));

        $primeraVersion = DB::table('tramite_borradores')
            ->where('tramite_id', $tramiteId)
            ->where('version', 1)
            ->first();
        expect($primeraVersion)->not->toBeNull();
        expect($primeraVersion->estado)->toBe('incompleto');
        expect((int) $primeraVersion->es_actual)->toBe(1);
        expect(json_decode($primeraVersion->personas_mencionadas, true)[0]['nombres'])->toBe('Persona mencionada ficticia');
        expect(json_decode($primeraVersion->adjuntos, true)[0]['id'])->toBe((int) $documento->id);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('digitalizado');

        $this->post(route('tramites.borradores.store', $tramiteId), [
            ...$draftPayload,
            'contenido_principal' => 'Contenido de prueba con suficiente detalle para preparar el borrador.',
            'preparar' => true,
        ])->assertRedirect(route('tramites.show', $tramiteId));

        $versiones = DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->orderBy('version')->get();
        expect($versiones)->toHaveCount(2);
        expect($versiones[0]->estado)->toBe('obsoleto');
        expect((int) $versiones[0]->es_actual)->toBe(0);
        expect($versiones[1]->estado)->toBe('preparado_asignacion');
        expect((int) $versiones[1]->es_actual)->toBe(1);
        expect($versiones[1]->preparado_en)->not->toBeNull();
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('borrador_preparado');

        $this->post(route('tramites.asignacion.prepare', $tramiteId))
            ->assertRedirect(route('tramites.show', $tramiteId));
        $this->post(route('tramites.asignacion.prepare', $tramiteId))
            ->assertRedirect(route('tramites.show', $tramiteId));

        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('pendiente_asignacion');
        expect(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->where('accion', 'preparar_asignacion')->count())->toBe(1);

        $secondReviewer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $secondReviewerId = $secondReviewer->id;
        $inactiveReviewer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => false,
        ]);
        $inactiveReviewerId = $inactiveReviewer->id;

        $this->get(route('tramites.asignaciones.index', ['q' => $marker]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/index')
                ->where('tramites.data.0.codigo', $codigo)
                ->has('tramites.data', 1));
        $this->get(route('tramites.asignaciones.create', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/form')
                ->where('modo', 'asignar')
                ->has('revisores.docente')
                ->has('revisores.oficina'));

        $assignmentPayload = [
            'destino' => 'oficina',
            'revisor_id' => $signerId,
            'motivo' => 'Revisión documental formal',
            'instrucciones_revision' => 'Comprobar el contenido y destinatarios.',
            'fecha_esperada' => now()->addDays(7)->toDateString(),
        ];
        $this->post(route('tramites.asignaciones.store', $tramiteId), $assignmentPayload)
            ->assertSessionHasErrors('revisor_id');
        $this->post(route('tramites.asignaciones.store', $tramiteId), [
            ...$assignmentPayload,
            'destino' => 'docente',
            'revisor_id' => $inactiveReviewerId,
        ])->assertSessionHasErrors('revisor_id');
        $this->post(route('tramites.asignaciones.store', $tramiteId), [
            ...$assignmentPayload,
            'destino' => 'docente',
            'revisor_id' => $studentId,
        ])->assertSessionHasErrors('revisor_id');
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('pendiente_asignacion');
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->count())->toBe(0);

        $this->post(route('tramites.asignaciones.store', $tramiteId), [
            ...$assignmentPayload,
            'destino' => 'docente',
            'revisor_id' => $signerId,
        ])->assertRedirect(route('tramites.show', $tramiteId));

        $primeraAsignacion = DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->first();
        expect($primeraAsignacion)->not->toBeNull();
        expect($primeraAsignacion->destino)->toBe('docente');
        expect($primeraAsignacion->rol_revisor)->toBe('docente');
        expect($primeraAsignacion->estado)->toBe('activa');
        expect((int) $primeraAsignacion->activa)->toBe(1);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('asignado');

        $this->actingAs($firmante)
            ->get(route('asignaciones.docente.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/reviewer-index')
                ->where('destino', 'docente')
                ->has('asignaciones.data', 1));
        $this->get(route('asignaciones.docente.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/reviewer-show')
                ->where('tramite.codigo', $codigo)
                ->where('borrador.version', 2)
                ->where('borrador.contenido_renderizado', $versiones[1]->contenido_renderizado)
                ->missing('borrador.adjuntos.0.ruta'));
        $this->actingAs($secondReviewer)->get(route('asignaciones.docente.show', $tramiteId))->assertForbidden();

        $this->actingAs($officeAdmin);
        $this->get(route('tramites.asignaciones.reassign', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/form')
                ->where('modo', 'reasignar')
                ->where('asignacion.revisor_id', $signerId));
        $this->post(route('tramites.asignaciones.update', $tramiteId), [
            'destino' => 'docente',
            'revisor_id' => $secondReviewerId,
            'motivo_reasignacion' => 'Cambio por disponibilidad del revisor',
            'instrucciones_revision' => 'Continuar con el análisis del borrador.',
            'fecha_esperada' => now()->addDays(10)->toDateString(),
        ])->assertRedirect(route('tramites.show', $tramiteId));

        $asignaciones = DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->orderBy('id')->get();
        expect($asignaciones)->toHaveCount(2);
        expect($asignaciones[0]->estado)->toBe('reasignada');
        expect((int) $asignaciones[0]->activa)->toBe(0);
        expect((int) $asignaciones[1]->activa)->toBe(1);
        $this->actingAs($firmante)->get(route('asignaciones.docente.show', $tramiteId))->assertForbidden();
        $this->actingAs($secondReviewer)->get(route('asignaciones.docente.show', $tramiteId))->assertOk();

        $this->actingAs($officeAdmin);
        $this->post(route('tramites.asignaciones.update', $tramiteId), [
            'destino' => 'oficina',
            'revisor_id' => $senderId,
            'motivo_reasignacion' => 'Derivación justificada para revisión de oficina',
            'motivo' => 'Validación administrativa',
            'fecha_esperada' => now()->addDays(14)->toDateString(),
        ])->assertRedirect(route('tramites.show', $tramiteId));
        $this->actingAs($remitente)
            ->get(route('asignaciones.oficina.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/asignaciones/reviewer-index')
                ->where('destino', 'oficina')
                ->has('asignaciones.data', 1));
        $this->get(route('asignaciones.oficina.show', $tramiteId))->assertOk();

        $this->actingAs($officeAdmin);
        $this->post(route('tramites.asignaciones.cancel', $tramiteId), [
            'motivo_finalizacion' => 'Cancelación ficticia previa al inicio de revisión',
        ])->assertRedirect(route('tramites.show', $tramiteId));
        $this->post(route('tramites.asignaciones.cancel', $tramiteId), [
            'motivo_finalizacion' => 'Segundo intento de cancelación no permitido',
        ])->assertStatus(409);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('pendiente_asignacion');
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->where('activa', true)->count())->toBe(0);
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->where('estado', 'reasignada')->count())->toBe(2);
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->where('estado', 'cancelada')->count())->toBe(1);

        $this->actingAs($officeAdmin)->post(route('tramites.asignaciones.store', $tramiteId), [
            'destino' => 'docente',
            'revisor_id' => $secondReviewerId,
            'motivo' => 'Revisión de contenido y formato',
            'instrucciones_revision' => 'Registrar cada observación con su nivel de obligatoriedad.',
            'fecha_esperada' => now()->addDays(5)->toDateString(),
        ])->assertRedirect(route('tramites.show', $tramiteId));

        $this->actingAs($firmante)->get(route('asignaciones.docente.show', $tramiteId))->assertForbidden();
        $this->actingAs($secondReviewer)
            ->post(route('tramites.revision.start', $tramiteId))
            ->assertRedirect(route('asignaciones.docente.show', $tramiteId));
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('en_revision');
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->where('activa', true)->count())->toBe(1);
        expect((int) DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->value('borrador_id'))
            ->toBe((int) DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->where('version', 2)->value('id'));
        $this->post(route('tramites.revision.start', $tramiteId))->assertStatus(409);

        $this->post(route('tramites.revision.observe', $tramiteId), [])->assertSessionHasErrors(['resumen', 'observaciones']);
        $this->post(route('tramites.revision.observe', $tramiteId), [
            'resumen' => 'Se requiere corregir el contenido y confirmar un dato.',
            'observaciones' => [
                [
                    'categoria' => 'Contenido',
                    'titulo' => 'Completar motivación',
                    'descripcion' => 'Agrega la referencia y explica el motivo de la solicitud.',
                    'seccion' => 'Contenido principal',
                    'obligatoria' => true,
                    'visible_para_interesado' => true,
                ],
                [
                    'categoria' => 'Datos personales',
                    'titulo' => 'Verificar dato complementario',
                    'descripcion' => 'Confirma que el dato complementario sea correcto.',
                    'seccion' => 'Datos del solicitante',
                    'obligatoria' => false,
                    'visible_para_interesado' => false,
                ],
            ],
        ])->assertRedirect(route('asignaciones.docente.show', $tramiteId));
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('observado');
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->value('estado'))->toBe('observada');
        $observacionObligatoriaId = (int) DB::table('tramite_observaciones_revision')
            ->where('tramite_id', $tramiteId)
            ->where('obligatoria', true)
            ->value('id');

        $this->actingAs($student)
            ->get(route('estudiante.tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/estudiante-show')
                ->where('tramite.estado_label', 'Observado')
                ->has('observaciones_visibles', 1)
                ->where('observaciones_visibles.0.titulo', 'Completar motivación')
                ->missing('comentario_interno')
                ->missing('revision_rondas'));

        $this->actingAs($officeAdmin)
            ->get(route('tramites.revision.correction', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/borrador')
                ->where('modo', 'corregir')
                ->has('observaciones', 2));
        $correctionPayload = [
            ...$draftPayload,
            'preparar' => true,
            'contenido_principal' => 'Contenido corregido con la motivación ampliada y los datos de la solicitud.',
            'resumen_correccion' => 'Se completó la motivación de la solicitud.',
            'respuestas' => [$observacionObligatoriaId => 'Se añadió la referencia solicitada.'],
        ];
        $this->post(route('tramites.revision.correct', $tramiteId), [
            ...$correctionPayload,
            'respuestas' => [],
        ])->assertSessionHasErrors('respuestas.'.$observacionObligatoriaId);
        expect(DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->count())->toBe(2);

        $this->post(route('tramites.revision.correct', $tramiteId), $correctionPayload)
            ->assertRedirect(route('tramites.show', $tramiteId));
        $versionCorregida = DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->where('es_actual', true)->first();
        expect($versionCorregida->estado)->toBe('preparado_asignacion');
        expect((int) $versionCorregida->version)->toBe(3);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('corregido');
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->value('estado'))->toBe('corregida');
        expect(DB::table('tramite_respuestas_observacion')->where('observacion_id', $observacionObligatoriaId)->value('respuesta'))
            ->toBe('Se añadió la referencia solicitada.');

        $this->actingAs($secondReviewer)
            ->post(route('tramites.revision.start', $tramiteId))
            ->assertRedirect(route('asignaciones.docente.show', $tramiteId));
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->count())->toBe(2);
        expect((int) DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->orderByDesc('numero_ronda')->value('borrador_id'))
            ->toBe((int) $versionCorregida->id);
        $this->post(route('tramites.revision.decide', $tramiteId), ['decision' => 'aprobar'])
            ->assertSessionHasErrors('conclusion');
        $this->post(route('tramites.revision.decide', $tramiteId), [
            'decision' => 'rechazar',
            'conclusion' => 'No cumple con el criterio documental descrito en la revisión.',
            'comentario_publico' => 'El expediente no cumple los requisitos para continuar.',
            'comentario_interno' => 'Rechazo ficticio para prueba funcional.',
        ])->assertRedirect(route('asignaciones.docente.index'));
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('rechazado');
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->where('activa', true)->count())->toBe(0);
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->orderByDesc('numero_ronda')->value('estado'))->toBe('rechazado');
        $this->post(route('tramites.revision.decide', $tramiteId), [
            'decision' => 'rechazar',
            'conclusion' => 'No debe poder emitir una segunda decisión sobre esta ronda.',
            'comentario_publico' => 'Este segundo resultado no está autorizado.',
        ])->assertForbidden();
        $this->actingAs($student)
            ->get(route('estudiante.tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tramite.estado_label', 'Rechazado')
                ->where('comentario_publico', 'El expediente no cumple los requisitos para continuar.')
                ->missing('comentario_interno'));

        $this->actingAs($student)
            ->get(route('tramites.documentos.descargar', [$tramiteId, $documento->id]))
            ->assertOk();

        $this->actingAs($officeAdmin);
        Storage::disk('local')->put($documento->ruta, 'contenido alterado');

        $this->get(route('tramites.documentos.descargar', [$tramiteId, $documento->id]))
            ->assertNotFound();

        expect(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->pluck('accion')->all())
            ->toBe([
                'recepcion',
                'acceso_tramite_no_autorizado',
                'digitalizacion',
                'descarga',
                'borrador_guardado',
                'borrador_preparado',
                'preparar_asignacion',
                'asignacion_creada',
                'apertura_borrador_revisor',
                'acceso_revision_no_autorizado',
                'asignacion_reasignada',
                'acceso_revision_no_autorizado',
                'apertura_borrador_revisor',
                'asignacion_reasignada',
                'apertura_borrador_revisor',
                'asignacion_cancelada',
                'asignacion_creada',
                'acceso_revision_no_autorizado',
                'inicio_revision',
                'punto_observado',
                'punto_observado',
                'observacion',
                'correccion_reenvio',
                'inicio_revision',
                'revision_rechazada',
                'descarga',
            ]);
    } finally {
        if ($tramiteId !== null) {
            $observacionIds = DB::table('tramite_observaciones_revision')->where('tramite_id', $tramiteId)->pluck('id');
            DB::table('tramite_respuestas_observacion')->whereIn('observacion_id', $observacionIds)->delete();
            DB::table('tramite_observaciones_revision')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_borrador_secuencias')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramites')->where('id', $tramiteId)->delete();
        }

        if ($plantillaId !== null) {
            DB::table('tramite_plantillas')->where('id', $plantillaId)->delete();
        }

        if ($officeAdminId !== null) {
            DB::table('users')->where('id', $officeAdminId)->delete();
        }

        if ($studentId !== null) {
            DB::table('users')->where('id', $studentId)->delete();
        }

        if ($foreignStudentId !== null) {
            DB::table('users')->where('id', $foreignStudentId)->delete();
        }

        if ($senderId !== null) {
            DB::table('users')->where('id', $senderId)->delete();
        }

        if ($signerId !== null) {
            DB::table('users')->where('id', $signerId)->delete();
        }

        if ($secondReviewerId !== null) {
            DB::table('users')->where('id', $secondReviewerId)->delete();
        }

        if ($inactiveReviewerId !== null) {
            DB::table('users')->where('id', $inactiveReviewerId)->delete();
        }

        if ($reservedNumber !== null) {
            $nextSequence = $sequenceBefore === null ? 1 : (int) $sequenceBefore + 1;

            if ($reservedNumber === $nextSequence) {
                $sequenceQuery = DB::table('tramite_secuencias')
                    ->where('anio', $year)
                    ->where('ultimo_numero', $reservedNumber);

                if ($sequenceBefore === null) {
                    $sequenceQuery->delete();
                } else {
                    $sequenceQuery->update([
                        'ultimo_numero' => (int) $sequenceBefore,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Storage::disk('local')->deleteDirectory('tramites');
        DB::setDefaultConnection($defaultConnection);
    }
});

test('assigned reviewer can approve a pinned round and publish a student-safe result', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');

    $studentId = null;
    $officeAdminId = null;
    $reviewerId = null;
    $plantillaId = null;
    $tramiteId = null;

    try {
        $student = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $studentId = $student->id;
        $officeAdmin = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $officeAdminId = $officeAdmin->id;
        $reviewer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $reviewerId = $reviewer->id;
        $tramite = Tramite::factory()->create([
            'codigo' => 'TST-APPROVE-'.Str::upper(Str::random(8)),
            'estado' => 'asignado',
            'propietario_id' => $studentId,
            'recibido_por' => $officeAdminId,
        ]);
        $tramiteId = $tramite->id;
        $plantilla = TramitePlantilla::factory()->create();
        $plantillaId = $plantilla->id;
        TramiteBorrador::factory()->create([
            'tramite_id' => $tramiteId,
            'plantilla_id' => $plantillaId,
            'remitente_id' => $officeAdminId,
            'firmante_id' => $reviewerId,
            'creado_por' => $officeAdminId,
        ]);
        TramiteAsignacion::factory()->create([
            'tramite_id' => $tramiteId,
            'revisor_id' => $reviewerId,
            'rol_revisor' => 'docente',
            'asignado_por' => $officeAdminId,
            'destino' => 'docente',
        ]);

        $this->actingAs($reviewer)
            ->post(route('tramites.revision.start', $tramiteId))
            ->assertRedirect(route('asignaciones.docente.show', $tramiteId));
        $this->post(route('tramites.revision.decide', $tramiteId), ['decision' => 'aprobar'])
            ->assertSessionHasErrors('conclusion');
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('en_revision');

        $this->post(route('tramites.revision.decide', $tramiteId), [
            'decision' => 'aprobar',
            'conclusion' => 'El documento cumple con los requisitos revisados.',
            'comentario_publico' => 'Su solicitud fue aprobada y continuará el proceso.',
            'comentario_interno' => 'Nota interna ficticia no visible para la persona solicitante.',
        ])->assertRedirect(route('asignaciones.docente.index'));

        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('aprobado');
        expect(DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->where('activa', true)->count())->toBe(0);
        expect(DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->value('estado'))->toBe('aprobado');
        $this->actingAs($student)
            ->get(route('estudiante.tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/estudiante-show')
                ->where('tramite.estado_label', 'Aprobado')
                ->where('comentario_publico', 'Su solicitud fue aprobada y continuará el proceso.')
                ->missing('comentario_interno')
                ->missing('revision_rondas'));
    } finally {
        if ($tramiteId !== null) {
            $observacionIds = DB::table('tramite_observaciones_revision')->where('tramite_id', $tramiteId)->pluck('id');
            DB::table('tramite_respuestas_observacion')->whereIn('observacion_id', $observacionIds)->delete();
            DB::table('tramite_observaciones_revision')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_asignaciones')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_borradores')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramite_borrador_secuencias')->where('tramite_id', $tramiteId)->delete();
            DB::table('tramites')->where('id', $tramiteId)->delete();
        }

        if ($plantillaId !== null) {
            DB::table('tramite_plantillas')->where('id', $plantillaId)->delete();
        }

        foreach ([$studentId, $officeAdminId, $reviewerId] as $userId) {
            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }

        DB::setDefaultConnection($defaultConnection);
    }
});

test('official document issuance consumes failed numbers and verifies the private PDF', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
    }

    $defaultConnection = DB::getDefaultConnection();
    $originalSeries = config('tramites.series_documentales', []);
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $officeAdminId = null;
    $studentId = null;
    $foreignStudentId = null;
    $senderId = null;
    $signerId = null;
    $reviewerId = null;
    $plantillaId = null;
    $tramiteId = null;
    $assignmentId = null;
    $borradorId = null;
    $rondaId = null;
    $serieId = null;
    $marker = 'DOC-'.Str::upper(Str::random(10));
    $tipoDocumento = 'qa'.Str::lower(Str::random(8));
    $codigoSerie = 'QA'.Str::upper(Str::random(8));
    $prefijoSerie = 'Q'.Str::upper(Str::random(7));
    $anio = (int) now()->format('Y');

    config(['tramites.series_documentales' => [
        ...$originalSeries,
        [
            'tipo_documento_salida' => $tipoDocumento,
            'modalidad' => 'unica',
            'codigo' => $codigoSerie,
            'prefijo' => $prefijoSerie,
        ],
    ]]);

    try {
        $officeAdmin = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $officeAdminId = $officeAdmin->id;
        $student = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $studentId = $student->id;
        $foreignStudent = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $foreignStudentId = $foreignStudent->id;
        $sender = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $senderId = $sender->id;
        $signer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $signerId = $signer->id;
        $reviewer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $reviewerId = $reviewer->id;

        $tramite = Tramite::factory()->create([
            'codigo' => $marker,
            'estado' => 'aprobado',
            'propietario_id' => $studentId,
            'recibido_por' => $officeAdminId,
        ]);
        $tramiteId = $tramite->id;
        $plantilla = TramitePlantilla::factory()->create([
            'tipo_documento_salida' => $tipoDocumento,
            'modalidad' => 'unica',
            'requiere_firma_fisica' => false,
        ]);
        $plantillaId = $plantilla->id;
        $borrador = TramiteBorrador::factory()->create([
            'tramite_id' => $tramiteId,
            'plantilla_id' => $plantillaId,
            'remitente_id' => $senderId,
            'firmante_id' => $signerId,
            'creado_por' => $officeAdminId,
            'version' => 3,
            'fecha_documento' => now()->toDateString(),
            'asunto' => 'Emisión ficticia de documento oficial',
            'contenido_principal' => 'Texto de prueba para la emisión del documento.',
            'destinatarios' => [[
                'nombres' => 'Destinatario',
                'apellidos' => 'Ficticio',
                'cargo' => 'Área de prueba',
                'principal' => true,
            ]],
        ]);
        $borradorId = $borrador->id;
        $assignment = TramiteAsignacion::factory()->create([
            'tramite_id' => $tramiteId,
            'revisor_id' => $reviewerId,
            'asignado_por' => $officeAdminId,
            'estado' => 'finalizada',
            'activa' => false,
            'fecha_finalizacion' => now(),
        ]);
        $assignmentId = $assignment->id;
        $ronda = TramiteRondaRevision::factory()->create([
            'tramite_id' => $tramiteId,
            'asignacion_id' => $assignmentId,
            'revisor_id' => $reviewerId,
            'borrador_id' => $borradorId,
            'estado' => 'aprobado',
            'activa' => false,
            'cerrada_en' => now(),
            'conclusion' => 'El documento cumple los requisitos de la prueba.',
            'comentario_publico' => 'Resultado ficticio publicado para la prueba.',
        ]);
        $rondaId = $ronda->id;
        $this->actingAs($officeAdmin);

        $seriesBeforeDraftPreview = DB::table('tramite_series_documentales')->count();
        $seriesCountersBeforeDraftPreview = DB::table('tramite_series_documentales')
            ->pluck('ultimo_correlativo', 'id')->all();
        $numbersBeforeDraftPreview = DB::table('tramite_numeraciones_documentales')->count();
        $finalsBeforeDraftPreview = DB::table('tramite_documentos_finales')->count();
        $eventsBeforeDraftPreview = DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->count();
        $filesBeforeDraftPreview = Storage::disk('local')->allFiles();
        $draftPreview = $this->get(route('tramites.borradores.pdf', [$tramiteId, $borradorId]))
            ->assertOk();
        $marcaVistaPrevia = (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Borrador sin numeración oficial');
        expect($draftPreview->headers->get('Content-Type'))->toContain('application/pdf')
            ->and($draftPreview->headers->get('Content-Disposition'))->toContain('inline;')
            ->and($draftPreview->headers->get('Cache-Control'))->toContain('private')
            ->and($draftPreview->headers->get('Cache-Control'))->toContain('no-store')
            ->and($draftPreview->getContent())->toStartWith('%PDF-')
            ->and($draftPreview->getContent())->toContain('%%EOF')
            ->and($draftPreview->getContent())->toContain($marcaVistaPrevia)
            ->and(DB::table('tramite_series_documentales')->count())->toBe($seriesBeforeDraftPreview)
            ->and(DB::table('tramite_series_documentales')->pluck('ultimo_correlativo', 'id')->all())
            ->toBe($seriesCountersBeforeDraftPreview)
            ->and(DB::table('tramite_numeraciones_documentales')->count())->toBe($numbersBeforeDraftPreview)
            ->and(DB::table('tramite_documentos_finales')->count())->toBe($finalsBeforeDraftPreview)
            ->and(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->count())->toBe($eventsBeforeDraftPreview)
            ->and(Storage::disk('local')->allFiles())->toBe($filesBeforeDraftPreview);
        $this->actingAs($student)
            ->get(route('tramites.borradores.pdf', [$tramiteId, $borradorId]))
            ->assertForbidden();
        $this->actingAs($officeAdmin);
        $otherTramite = Tramite::factory()->create();
        $this->get(route('tramites.borradores.pdf', [$otherTramite, $borradorId]))
            ->assertNotFound();

        $this->get(route('tramites.documento-final.preview', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/documento-final')
                ->where('tramite.codigo', $marker)
                ->where('revision.decision', 'aprobado')
                ->where('borrador.version', 3));

        $this->from(route('tramites.documento-final.preview', $tramiteId))
            ->post(route('tramites.documento-final.emit', $tramiteId), [])
            ->assertSessionHasErrors('confirmar');
        expect(DB::table('tramite_series_documentales')
            ->where('tipo_documento_salida', $tipoDocumento)
            ->where('anio', $anio)
            ->exists())->toBeFalse();

        $this->mock(PdfDocumentGenerator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('Fallo artificial de generación.'));
        });

        $this->from(route('tramites.documento-final.preview', $tramiteId))
            ->post(route('tramites.documento-final.emit', $tramiteId), ['confirmar' => true])
            ->assertSessionHasErrors('documento');

        $serie = DB::table('tramite_series_documentales')
            ->where('tipo_documento_salida', $tipoDocumento)
            ->where('anio', $anio)
            ->first();
        expect($serie)->not->toBeNull();
        $serieId = (int) $serie->id;
        expect((int) $serie->ultimo_correlativo)->toBe(1);
        $failedNumber = DB::table('tramite_numeraciones_documentales')
            ->where('tramite_id', $tramiteId)
            ->first();
        expect($failedNumber->estado)->toBe('fallida');
        expect($failedNumber->numero_completo)->toBe($prefijoSerie.'-'.$anio.'-000001');
        $failedDocument = DB::table('tramite_documentos_finales')
            ->where('tramite_id', $tramiteId)
            ->first();
        expect($failedDocument->estado)->toBe('fallido');
        expect((int) $failedDocument->activo)->toBe(0);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('aprobado');
        expect(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->pluck('accion')->all())
            ->toBe(['numero_reservado', 'emision_pdf_fallida']);

        $this->app->instance(PdfDocumentGenerator::class, new PdfDocumentGenerator);
        $this->post(route('tramites.documento-final.emit', $tramiteId), ['confirmar' => true])
            ->assertRedirect(route('tramites.show', $tramiteId));

        $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramiteId)->where('estado', 'emitido')->firstOrFail();
        expect($documento->numero_documento)->toBe($prefijoSerie.'-'.$anio.'-000002');
        expect((int) $documento->borrador_id)->toBe($borradorId);
        expect((int) $documento->ronda_revision_id)->toBe($rondaId);
        expect($documento->contenido_snapshot['version_borrador'])->toBe(3);
        expect($documento->sha256)->toMatch('/^[a-f0-9]{64}$/');
        expect((int) $documento->tamano_bytes)->toBeGreaterThan(500);
        expect((int) $documento->numero_paginas)->toBeGreaterThan(0);
        expect(DB::table('tramite_numeraciones_documentales')->where('id', $documento->numeracion_id)->value('estado'))
            ->toBe('emitida');
        expect(DB::table('tramite_series_documentales')->where('id', $serieId)->value('ultimo_correlativo'))->toBe(2);
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('documento_final_generado');

        $path = Storage::disk('local')->path($documento->ruta);
        $bytes = file_get_contents($path);
        expect($bytes)->toBeString();
        expect(str_starts_with((string) $bytes, '%PDF-'))->toBeTrue();
        expect(str_contains((string) $bytes, '%%EOF'))->toBeTrue();
        expect(hash('sha256', (string) $bytes))->toBe($documento->sha256);
        expect(filesize($path))->toBe((int) $documento->tamano_bytes);

        $this->post(route('tramites.documento-final.emit', $tramiteId), ['confirmar' => true])->assertStatus(409);
        expect(DB::table('tramite_series_documentales')->where('id', $serieId)->value('ultimo_correlativo'))->toBe(2);

        $this->actingAs($student)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertForbidden();
        $this->get(route('estudiante.tramites.show', $tramiteId))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documento_final.numero', $documento->numero_documento)
                ->missing('documento_final.url_descarga')
                ->etc());
        $officeAdminDownload = $this->actingAs($officeAdmin)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertOk()->assertDownload($documento->nombre_archivo)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($officeAdminDownload->headers->get('Cache-Control'))
            ->toContain('private')
            ->toContain('no-store')
            ->not->toContain('public');
        $this->actingAs($foreignStudent)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertOk()
            ->assertDownload($documento->nombre_archivo);

        Storage::disk('local')->put($documento->ruta, 'contenido alterado');
        $this->actingAs($officeAdmin)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertNotFound();

        expect(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->pluck('accion')->all())
            ->toBe([
                'numero_reservado',
                'emision_pdf_fallida',
                'numero_reservado',
                'documento_final_emitido',
                'descarga_documento_final',
                'acceso_documento_final_no_autorizado',
                'descarga_documento_final',
            ]);
    } finally {
        try {
            Storage::disk('local')->deleteDirectory('documentos-finales');

            if ($tramiteId !== null) {
                DB::table('tramite_documentos_finales')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_numeraciones_documentales')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->delete();

                if ($assignmentId !== null) {
                    DB::table('tramite_asignaciones')->where('id', $assignmentId)->delete();
                }

                if ($borradorId !== null) {
                    DB::table('tramite_borradores')->where('id', $borradorId)->delete();
                }

                DB::table('tramite_borrador_secuencias')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramites')->where('id', $tramiteId)->delete();
            }

            if ($serieId !== null) {
                TramiteSerieDocumental::query()->whereKey($serieId)->delete();
            }

            if ($plantillaId !== null) {
                DB::table('tramite_plantillas')->where('id', $plantillaId)->delete();
            }

            foreach ([$officeAdminId, $studentId, $foreignStudentId, $senderId, $signerId, $reviewerId] as $userId) {
                if ($userId !== null) {
                    DB::table('users')->where('id', $userId)->delete();
                }
            }
        } finally {
            config(['tramites.series_documentales' => $originalSeries]);
            DB::setDefaultConnection($defaultConnection);
        }
    }
});

test('administrator can emit the approved official document', function () {
    [, $tramite] = createApprovedTramiteForNumberingTest();
    $administrator = User::factory()->create(['rol' => 'administrador']);

    $this->actingAs($administrator)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));

    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();
    expect($documento->estado)->toBe('emitido')
        ->and($documento->generado_por)->toBe($administrator->id);
});

test('official document emission reconciles a lost commit response without deleting the PDF or reusing a number', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();

    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 2) {
            throw new RuntimeException('Simulated lost response after the final commit.');
        }
    });

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));

    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();
    $numeracion = TramiteNumeracionDocumental::query()->findOrFail($documento->numeracion_id);
    $archivo = Storage::disk('local')->path($documento->ruta);

    expect($commits)->toBe(2)
        ->and($documento->estado)->toBe('emitido')
        ->and($numeracion->estado)->toBe('emitida')
        ->and($numeracion->correlativo)->toBe(1)
        ->and(is_file($archivo))->toBeTrue()
        ->and(hash_file('sha256', $archivo))->toBe($documento->sha256)
        ->and(TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->count())->toBe(1)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->pluck('accion')->all())->toBe(['numero_reservado', 'documento_final_emitido']);

    $this->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])->assertStatus(409);
    expect(TramiteSerieDocumental::query()->where('tipo_documento_salida', 'informe')->value('ultimo_correlativo'))->toBe(1);
});

test('lost reservation commit response leaves one reserved number and blocks a second reservation', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 1) {
            throw new RuntimeException('Simulated lost response after reserving the number.');
        }
    });

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertSessionHasErrors('documento');

    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();
    $numeracion = TramiteNumeracionDocumental::query()->findOrFail($documento->numeracion_id);

    expect($commits)->toBe(1)
        ->and($documento->estado)->toBe('generando')
        ->and($numeracion->estado)->toBe('reservada')
        ->and($numeracion->correlativo)->toBe(1)
        ->and(TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->count())->toBe(1)
        ->and(TramiteSerieDocumental::query()->where('tipo_documento_salida', 'informe')->value('ultimo_correlativo'))->toBe(1);

    $this->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])->assertStatus(409);
    expect(TramiteNumeracionDocumental::query()->where('tramite_id', $tramite->id)->count())->toBe(1);
});

test('interrupted final transaction keeps the reserved number and PDF for reconciliation', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $interrupted = false;
    DB::listen(function (QueryExecuted $query) use (&$interrupted): void {
        if (! $interrupted
            && str_starts_with(strtolower(trim($query->sql)), 'update')
            && str_contains($query->sql, 'tramite_documentos_finales')) {
            $interrupted = true;

            throw new RuntimeException('Simulated interruption before the final commit.');
        }
    });

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertSessionHasErrors('documento');

    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();
    $numeracion = TramiteNumeracionDocumental::query()->findOrFail($documento->numeracion_id);
    $archivos = Storage::disk('local')->allFiles('documentos-finales');

    expect($interrupted)->toBeTrue()
        ->and($documento->estado)->toBe('generando')
        ->and($numeracion->estado)->toBe('reservada')
        ->and($numeracion->correlativo)->toBe(1)
        ->and($archivos)->toHaveCount(1)
        ->and(str_starts_with(Storage::disk('local')->get($archivos[0]), '%PDF-'))->toBeTrue()
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->pluck('accion')->all())->toBe(['numero_reservado']);
});

test('CLI reconciliation closes an inconclusive reservation without releasing its correlativo', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 1) {
            throw new RuntimeException('Simulated lost reservation response.');
        }
    });

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertSessionHasErrors('documento');
    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();

    expect(Artisan::call('documents:reconcile-reservation', ['documento' => $documento->id]))->toBe(0)
        ->and($documento->fresh()->estado)->toBe('generando')
        ->and(Artisan::call('documents:reconcile-reservation', ['documento' => $documento->id, '--fail' => true]))->toBe(1)
        ->and($documento->fresh()->estado)->toBe('generando');

    expect(Artisan::call('documents:reconcile-reservation', [
        'documento' => $documento->id,
        '--fail' => true,
        '--reason' => 'Respuesta de reserva perdida; proceso original concluido.',
    ]))->toBe(0);
    expect($documento->refresh()->estado)->toBe('fallido')
        ->and($documento->activo)->toBeFalse()
        ->and($documento->numeracion()->value('estado'))->toBe('fallida')
        ->and(TramiteSerieDocumental::query()->where('tipo_documento_salida', 'informe')->value('ultimo_correlativo'))->toBe(1)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->where('accion', 'reserva_reconciliada_cli')->count())->toBe(1);

    expect(Artisan::call('documents:reconcile-reservation', ['documento' => $documento->id, '--fail' => true]))->toBe(0)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->where('accion', 'reserva_reconciliada_cli')->count())->toBe(1);

    $this->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));
    $emitido = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->where('estado', 'emitido')->firstOrFail();
    expect($emitido->numeracion()->value('correlativo'))->toBe(2)
        ->and($emitido->numero_documento)->toContain('-000002')
        ->and(Artisan::call('documents:reconcile-reservation', [
            'documento' => $emitido->id,
            '--fail' => true,
            '--reason' => 'No debe aceptarse.',
        ]))->toBe(1)
        ->and($emitido->fresh()->estado)->toBe('emitido');
});

test('CLI reconciliation can be inspected again after losing its own commit response', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits <= 2) {
            throw new RuntimeException('Simulated lost commit response.');
        }
    });

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertSessionHasErrors('documento');
    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();

    expect(Artisan::call('documents:reconcile-reservation', [
        'documento' => $documento->id,
        '--fail' => true,
        '--reason' => 'Prueba de respuesta perdida.',
    ]))->toBe(1)
        ->and($documento->fresh()->estado)->toBe('fallido')
        ->and($documento->numeracion()->value('estado'))->toBe('fallida')
        ->and(TramiteSerieDocumental::query()->where('tipo_documento_salida', 'informe')->value('ultimo_correlativo'))->toBe(1);

    expect(Artisan::call('documents:reconcile-reservation', ['documento' => $documento->id]))->toBe(0)
        ->and(Artisan::call('documents:reconcile-reservation', ['documento' => $documento->id, '--fail' => true]))->toBe(0)
        ->and(TramiteEvento::query()->where('tramite_id', $tramite->id)->where('accion', 'reserva_reconciliada_cli')->count())->toBe(1);
});

test('student confirms only their own pending delivery once and leaves an auditable record in SQLite', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $student = User::factory()->create(['rol' => 'estudiante', 'activo' => true]);
    $otherStudent = User::factory()->create(['rol' => 'estudiante', 'activo' => true]);
    $reviewer = User::factory()->create(['rol' => 'docente', 'activo' => true]);
    $tramite = Tramite::factory()->create([
        'estado' => 'listo_entrega',
        'propietario_id' => $student->id,
        'recibido_por' => $officeAdmin->id,
    ]);
    $documento = TramiteDocumentoFinal::factory()->create([
        'tramite_id' => $tramite->id,
        'estado' => 'emitido',
        'activo' => true,
    ]);
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'presencial',
        'nombre' => 'Presencial',
        'tipo' => 'presencial',
        'requiere_evidencia' => false,
        'activo' => true,
    ]);
    $entrega = TramiteEntrega::query()->create([
        'tramite_id' => $tramite->id,
        'documento_final_id' => $documento->id,
        'medio_entrega_id' => $medio->id,
        'entregado_por' => $officeAdmin->id,
        'receptor_usuario_id' => $student->id,
        'receptor_nombre' => $student->name,
        'receptor_tipo' => 'Estudiante',
        'fecha_entrega' => now(),
        'codigo_confirmacion' => 'CONF-PRUEBA-001',
        'confirmado' => false,
        'activa' => true,
        'estado' => 'registrada',
    ]);

    $this->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertRedirect(route('login'));
    $this->actingAs($reviewer)->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertForbidden();
    $this->actingAs($otherStudent)->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertForbidden();
    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('puede_confirmar_entrega', true)->etc());
    $this->post(route('tramites.entrega.confirmar', $tramite), [])->assertSessionHasErrors('confirmar');
    expect($entrega->fresh()->confirmado)->toBeFalse();

    $this->post(route('tramites.entrega.confirmar', $tramite), [
        'confirmar' => '1',
        'observacion' => 'Documento recibido físicamente por la persona interesada.',
    ])->assertRedirect();
    expect($tramite->fresh()->estado)->toBe('entregado')
        ->and($entrega->refresh()->confirmado)->toBeTrue()
        ->and($entrega->confirmado_por_estudiante)->toBeTrue();
    $evidencia = DB::table('tramite_evidencias_entrega')->where('entrega_id', $entrega->id)->sole();
    expect($evidencia->tipo_evidencia)->toBe('Confirmación manual')
        ->and($evidencia->registrado_por)->toBe($student->id)
        ->and($evidencia->codigo_confirmacion)->toBe('CONF-PRUEBA-001');
    $evento = $tramite->eventos()->where('accion', 'recepcion_confirmada')->sole();
    expect($evento->usuario_id)->toBe($student->id)
        ->and($evento->metadatos['confirmado_por_interesado'])->toBeTrue();
    $this->get(route('estudiante.tramites.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tramite.estado', 'entregado')
        ->where('entrega.confirmado', true)
        ->where('puede_confirmar_entrega', false)
        ->etc());
    $this->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertStatus(409);
    expect(DB::table('tramite_evidencias_entrega')->where('entrega_id', $entrega->id)->count())->toBe(1);
});

test('administrator annuls a pending delivery and reopens a closed case without erasing history', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $administrator = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $student = $tramite->propietario;
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'presencial',
        'nombre' => 'Presencial',
        'tipo' => 'presencial',
        'requiere_evidencia' => false,
        'activo' => true,
    ]);
    $payload = [
        'medio_entrega_id' => $medio->id,
        'receptor_nombre' => $student->name,
        'receptor_tipo' => 'Estudiante',
        'fecha_entrega' => now()->format('Y-m-d\TH:i'),
        'tipo_evidencia' => 'Confirmación manual',
    ];

    $this->actingAs($officeAdmin)->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])->assertRedirect();
    $this->post(route('tramites.entrega.prepare', $tramite))->assertRedirect();
    $this->post(route('tramites.entrega.registrar', $tramite), $payload)->assertRedirect();
    $primeraEntrega = $tramite->entregaActual()->firstOrFail();
    $this->actingAs($administrator)->get(route('tramites.entrega.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('puede_anular', true)->where('puede_reabrir', false)->etc());

    $motivoAnulacion = 'La entrega fue registrada con datos incorrectos.';
    $rutaAnular = route('tramites.entrega.anular', [$tramite, $primeraEntrega]);
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->post($rutaAnular, ['motivo' => $motivoAnulacion])->assertForbidden();
    $this->actingAs($student)->post($rutaAnular, ['motivo' => $motivoAnulacion])->assertForbidden();
    $this->actingAs($administrator)->post($rutaAnular, ['motivo' => 'corto'])->assertSessionHasErrors('motivo');
    $this->post(route('tramites.entrega.anular', [Tramite::factory()->create(), $primeraEntrega]), ['motivo' => $motivoAnulacion])->assertNotFound();
    $this->post($rutaAnular, ['motivo' => $motivoAnulacion])->assertRedirect(route('tramites.entrega.show', $tramite));
    expect($primeraEntrega->fresh()->activa)->toBeFalse()
        ->and($primeraEntrega->fresh()->estado)->toBe('anulada')
        ->and($tramite->fresh()->estado)->toBe('listo_entrega')
        ->and(DB::table('tramite_evidencias_entrega')->where('entrega_id', $primeraEntrega->id)->where('activa', true)->count())->toBe(0);
    expect($tramite->eventos()->where('accion', 'entrega_anulada')->sole()->metadatos['motivo'])->toBe($motivoAnulacion);
    $this->post($rutaAnular, ['motivo' => $motivoAnulacion])->assertStatus(409);

    $this->actingAs($officeAdmin)->post(route('tramites.entrega.registrar', $tramite), $payload)->assertRedirect();
    $segundaEntrega = $tramite->entregaActual()->firstOrFail();
    expect($segundaEntrega->id)->not->toBe($primeraEntrega->id);
    $this->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertRedirect();
    $this->actingAs($administrator)->post(route('tramites.entrega.anular', [$tramite, $segundaEntrega]), ['motivo' => $motivoAnulacion])->assertStatus(409);
    $this->actingAs($officeAdmin);
    $this->post(route('tramites.entrega.cerrar', $tramite), ['resumen' => 'Primer cierre administrativo después de la entrega corregida.'])->assertRedirect();
    $primerCierre = $tramite->cierre()->firstOrFail();
    $primerInforme = $primerCierre->informe()->firstOrFail();
    $rutaInforme = route('tramites.informes-cierre.descargar', [$tramite, $primerInforme]);
    $this->get($rutaInforme)->assertOk();
    $this->actingAs($administrator)->get(route('tramites.entrega.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('puede_reabrir', true)->where('puede_anular', false)->etc());

    $motivoReapertura = 'Se requiere corregir el informe administrativo de cierre.';
    $rutaReabrir = route('tramites.entrega.reabrir', $tramite);
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->post($rutaReabrir, ['motivo' => $motivoReapertura])->assertForbidden();
    $this->actingAs($administrator)->post($rutaReabrir, ['motivo' => 'corto'])->assertSessionHasErrors('motivo');
    $this->post($rutaReabrir, ['motivo' => $motivoReapertura])->assertRedirect(route('tramites.entrega.show', $tramite));
    expect($tramite->fresh()->estado)->toBe('entregado')
        ->and($primerCierre->fresh()->activo)->toBeFalse()
        ->and($primerCierre->fresh()->reabierto)->toBeTrue()
        ->and($primerCierre->fresh()->motivo_reapertura)->toBe($motivoReapertura)
        ->and($primerCierre->fresh()->reabierto_por)->toBe($administrator->id)
        ->and($primerInforme->fresh()->activo)->toBeFalse()
        ->and(Storage::disk('local')->exists($primerInforme->ruta))->toBeTrue();
    $this->get($rutaInforme)->assertNotFound();
    $this->post($rutaReabrir, ['motivo' => $motivoReapertura])->assertStatus(409);
    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))->assertOk()->assertDontSee($motivoReapertura);

    $this->actingAs($officeAdmin)->post(route('tramites.entrega.cerrar', $tramite), ['resumen' => 'Segundo cierre tras la reapertura administrativa del expediente.'])->assertRedirect();
    $segundoCierre = $tramite->cierre()->firstOrFail();
    $segundoInforme = $segundoCierre->informe()->firstOrFail();
    expect($segundoCierre->id)->not->toBe($primerCierre->id)
        ->and($segundoInforme->id)->not->toBe($primerInforme->id)
        ->and($tramite->fresh()->estado)->toBe('cerrado')
        ->and(DB::table('tramite_cierres')->where('tramite_id', $tramite->id)->count())->toBe(2)
        ->and(DB::table('tramite_cierres')->where('tramite_id', $tramite->id)->where('activo', true)->count())->toBe(1)
        ->and(DB::table('tramite_informes_cierre')->where('tramite_id', $tramite->id)->count())->toBe(2)
        ->and($tramite->eventos()->where('accion', 'expediente_reabierto')->sole()->metadatos['motivo'])->toBe($motivoReapertura);

    DB::table('tramite_informes_cierre')->where('id', $segundoInforme->id)->update(['activo' => false]);
    $this->actingAs($administrator)->post($rutaReabrir, ['motivo' => $motivoReapertura])->assertStatus(409);
    expect($tramite->fresh()->estado)->toBe('cerrado')
        ->and($segundoCierre->fresh()->activo)->toBeTrue()
        ->and($segundoCierre->fresh()->reabierto)->toBeFalse();
});

/** @return array{User, Tramite} */
function createApprovedTramiteForNumberingTest(string $tipoDocumentoSalida = 'informe', ?string $modalidad = null): array
{
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $officeAdmin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $administrator = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $reviewer = User::factory()->create(['rol' => 'docente', 'activo' => true]);
    $signature = imagecreatetruecolor(160, 60);
    $white = imagecolorallocate($signature, 255, 255, 255);
    $ink = imagecolorallocate($signature, 30, 30, 30);
    imagefill($signature, 0, 0, $white);
    imageline($signature, 8, 42, 52, 18, $ink);
    imageline($signature, 52, 18, 68, 48, $ink);
    imageline($signature, 68, 48, 116, 17, $ink);
    imageline($signature, 116, 17, 151, 38, $ink);
    ob_start();
    imagejpeg($signature, null, 90);
    $signatureBytes = ob_get_clean();
    imagedestroy($signature);
    Storage::disk('local')->put('firmas-perfil/'.$reviewer->id.'.jpg', (string) $signatureBytes);
    $tramite = Tramite::factory()->create(['estado' => 'aprobado', 'recibido_por' => $officeAdmin->id]);
    $plantilla = TramitePlantilla::factory()->create([
        'tipo_documento_salida' => $tipoDocumentoSalida,
        'modalidad' => $modalidad ?? 'unica',
        'nombre' => $tipoDocumentoSalida === 'memorando'
            ? ($modalidad === 'multiple' ? 'MEMORANDO MULTIPLE' : 'MEMORANDO')
            : 'INFORME',
    ]);
    $borrador = TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'plantilla_id' => $plantilla->id,
        'remitente_id' => $administrator->id,
        'firmante_id' => $reviewer->id,
        'creado_por' => $officeAdmin->id,
    ]);
    $asignacion = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $officeAdmin->id,
        'activa' => false,
        'estado' => 'finalizada',
    ]);
    TramiteRondaRevision::factory()->create([
        'tramite_id' => $tramite->id,
        'asignacion_id' => $asignacion->id,
        'revisor_id' => $reviewer->id,
        'borrador_id' => $borrador->id,
        'estado' => 'aprobado',
        'activa' => false,
    ]);

    return [$officeAdmin, $tramite];
}

test('simple and multiple memorandum series keep distinct configured numbers', function () {
    [$simpleAssistant, $simpleTramite] = createApprovedTramiteForNumberingTest('memorando', 'simple');
    $this->actingAs($simpleAssistant)
        ->post(route('tramites.documento-final.emit', $simpleTramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $simpleTramite));
    $simpleNumber = TramiteDocumentoFinal::query()->where('tramite_id', $simpleTramite->id)->sole()->numero_documento;

    [$multipleAssistant, $multipleTramite] = createApprovedTramiteForNumberingTest('memorando', 'multiple');
    $this->actingAs($multipleAssistant)
        ->post(route('tramites.documento-final.emit', $multipleTramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $multipleTramite));
    $multipleNumber = TramiteDocumentoFinal::query()->where('tramite_id', $multipleTramite->id)->sole()->numero_documento;
    $anio = now()->format('Y');

    expect($simpleNumber)->toBe('001-DSI-HACH-IESTP”MSC”-'.$anio)
        ->and($multipleNumber)->toBe('001/DSI/HACH/IESTP “MSC”-'.$anio)
        ->and($multipleNumber)->not->toBe($simpleNumber)
        ->and(TramiteDocumentoFinal::query()->where('numero_documento', $simpleNumber)->count())->toBe(1)
        ->and(TramiteDocumentoFinal::query()->where('numero_documento', $multipleNumber)->count())->toBe(1);
});

test('administrator substitutes an issued document without reusing its number or exposing its private reason', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $administrator = User::query()->where('rol', 'administrador')->firstOrFail();
    $student = User::factory()->create(['rol' => 'estudiante', 'activo' => true]);
    $tramite->update(['propietario_id' => $student->id]);

    $tramite->borradorActual()->update(['contenido_renderizado' => 'BORRADOR REVISADO DE PRUEBA']);

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));
    $primero = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->sole();
    expect($primero->contenido_snapshot['borrador_renderizado_sha256'])
        ->toBe(hash('sha256', 'BORRADOR REVISADO DE PRUEBA'));
    $this->actingAs($student)->get(route('tramites.documento-final.descargar', [$tramite, $primero]))
        ->assertForbidden();
    $this->get(route('estudiante.tramites.show', $tramite))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documento_final.numero', $primero->numero_documento)
            ->missing('documento_final.url_descarga')
            ->etc());
    $this->actingAs(User::factory()->create(['rol' => 'docente']));
    $ruta = route('tramites.documento-final.anular', [$tramite, $primero]);
    $motivo = 'Se detectó un error administrativo en el documento.';

    $this->post($ruta, ['accion' => 'sustituir', 'motivo' => $motivo])->assertForbidden();
    $this->actingAs($student)->post($ruta, ['accion' => 'sustituir', 'motivo' => $motivo])->assertForbidden();
    $this->actingAs($administrator)->get(route('tramites.show', $tramite))
        ->assertInertia(fn (Assert $page) => $page->where('tramite.puede_anular_documento_final', true)->etc());
    $this->post($ruta, ['accion' => 'sustituir', 'motivo' => '      '])->assertSessionHasErrors('motivo');
    $this->post($ruta, ['accion' => 'otra', 'motivo' => $motivo])->assertSessionHasErrors('accion');
    $otroTramite = Tramite::factory()->create();
    $this->post(route('tramites.documento-final.anular', [$otroTramite, $primero]), [
        'accion' => 'sustituir',
        'motivo' => $motivo,
    ])->assertNotFound();
    expect($primero->fresh()->estado)->toBe('emitido');

    $this->post($ruta, ['accion' => 'sustituir', 'motivo' => $motivo])
        ->assertRedirect(route('tramites.show', $tramite));

    expect($primero->refresh()->estado)->toBe('sustituido')
        ->and($primero->activo)->toBeFalse()
        ->and($primero->motivo_anulacion)->toBe($motivo)
        ->and($primero->anulado_por)->toBe($administrator->id)
        ->and($primero->fecha_anulacion)->not->toBeNull()
        ->and($primero->numeracion()->value('estado'))->toBe('anulada')
        ->and($tramite->fresh()->estado)->toBe('aprobado')
        ->and(TramiteSerieDocumental::query()->where('tipo_documento_salida', 'informe')->value('ultimo_correlativo'))->toBe(1);
    expect($tramite->eventos()->where('accion', 'sustitucion_autorizada')->sole()->metadatos['motivo'])->toBe($motivo);
    $this->post($ruta, ['accion' => 'sustituir', 'motivo' => $motivo])->assertStatus(409);

    $this->app['auth']->forgetGuards();
    $this->get(route('documentos.verificar', ['codigo' => $primero->codigo_verificacion]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resultado.estado', 'sustituido')
            ->missing('resultado.documento.motivo_anulacion')
            ->etc());
    $this->actingAs($administrator)->get(route('tramites.show', $tramite))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramite.documento_final', null)
            ->where('tramite.documentos_finales_anteriores.0.estado', 'sustituido')
            ->etc());
    $this->actingAs($officeAdmin)->get(route('tramites.documento-final.descargar', [$tramite, $primero]))
        ->assertOk()->assertDownload($primero->nombre_archivo);
    $this->actingAs($student)->get(route('tramites.documento-final.descargar', [$tramite, $primero]))
        ->assertForbidden();
    $reviewer = User::query()->findOrFail($primero->rondaRevision()->value('revisor_id'));
    $otherReviewer = User::factory()->create(['rol' => 'docente', 'activo' => true]);
    $this->actingAs($otherReviewer)->get(route('tramites.documento-final.descargar', [$tramite, $primero]))
        ->assertForbidden();
    $this->actingAs($reviewer)->get(route('tramites.documento-final.descargar', [$tramite, $primero]))
        ->assertOk()->assertDownload($primero->nombre_archivo);
    TramiteAsignacion::query()->where('tramite_id', $tramite->id)->update(['estado' => 'aprobado']);
    $this->get(route('asignaciones.docente.show', $tramite))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentos_finales.0.id', $primero->id)
            ->where('documentos_finales.0.estado', 'sustituido')
            ->etc());

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));
    $segundo = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->where('estado', 'emitido')->sole();

    expect($segundo->documento_anterior_id)->toBe($primero->id)
        ->and($segundo->version)->toBe(2)
        ->and($segundo->numeracion()->value('correlativo'))->toBe(2)
        ->and($segundo->numero_documento)->not->toBe($primero->numero_documento)
        ->and($primero->fresh()->estado)->toBe('sustituido');
    $this->actingAs($administrator)->get(route('tramites.show', $tramite))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramite.documento_final.id', $segundo->id)
            ->where('tramite.documentos_finales_anteriores.0.id', $primero->id)
            ->etc());
});

test('administrator annulment is blocked after document enters signature or delivery', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $administrator = User::query()->where('rol', 'administrador')->firstOrFail();
    $this->actingAs($officeAdmin)->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));
    $documento = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->sole();
    $tramite->update(['estado' => 'pendiente_firma']);

    $this->actingAs($administrator)->post(route('tramites.documento-final.anular', [$tramite, $documento]), [
        'accion' => 'anular',
        'motivo' => 'Motivo administrativo suficiente.',
    ])->assertStatus(409);

    expect($documento->fresh()->estado)->toBe('emitido')
        ->and($documento->numeracion()->value('estado'))->toBe('emitida')
        ->and($tramite->fresh()->estado)->toBe('pendiente_firma')
        ->and($tramite->eventos()->where('accion', 'documento_final_anulado')->count())->toBe(0);

    $tramite->update(['estado' => 'documento_final_generado']);
    $this->post(route('tramites.documento-final.anular', [$tramite, $documento]), [
        'accion' => 'anular',
        'motivo' => 'Motivo administrativo suficiente.',
    ])->assertRedirect(route('tramites.show', $tramite));

    expect($documento->refresh()->estado)->toBe('anulado')
        ->and($documento->activo)->toBeFalse()
        ->and($documento->numeracion()->value('estado'))->toBe('anulada')
        ->and($tramite->fresh()->estado)->toBe('aprobado')
        ->and($tramite->eventos()->where('accion', 'documento_final_anulado')->count())->toBe(1);
    $this->app['auth']->forgetGuards();
    $this->get(route('documentos.verificar', ['codigo' => $documento->codigo_verificacion]))
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'anulado')->etc());
});

test('signature delivery and closure require confirmation and produce an auditable private report', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $officeAdminId = null;
    $studentId = null;
    $foreignStudentId = null;
    $senderId = null;
    $signerId = null;
    $reviewerId = null;
    $plantillaId = null;
    $tramiteId = null;
    $assignmentId = null;
    $borradorId = null;
    $rondaId = null;
    $serieId = null;
    $numeracionId = null;
    $documentoId = null;
    $entregaId = null;
    $informeId = null;
    $marker = 'CLOSE-'.Str::upper(Str::random(10));
    $tipoDocumento = 'qa'.Str::lower(Str::random(8));
    $anio = (int) now()->format('Y');

    try {
        $officeAdmin = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $officeAdminId = $officeAdmin->id;
        $student = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $studentId = $student->id;
        $foreignStudent = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'estudiante',
            'activo' => true,
        ]);
        $foreignStudentId = $foreignStudent->id;
        $sender = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'administrador',
            'activo' => true,
        ]);
        $senderId = $sender->id;
        $signer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $signerId = $signer->id;
        $reviewer = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
        ]);
        $reviewerId = $reviewer->id;

        $tramite = Tramite::factory()->create([
            'codigo' => $marker,
            'estado' => 'documento_final_generado',
            'propietario_id' => $studentId,
            'recibido_por' => $officeAdminId,
        ]);
        $tramiteId = $tramite->id;
        $plantilla = TramitePlantilla::factory()->create([
            'tipo_documento_salida' => $tipoDocumento,
            'modalidad' => 'unica',
            'requiere_firma_fisica' => true,
            'permite_no_firma' => false,
        ]);
        $plantillaId = $plantilla->id;
        $borrador = TramiteBorrador::factory()->create([
            'tramite_id' => $tramiteId,
            'plantilla_id' => $plantillaId,
            'remitente_id' => $senderId,
            'firmante_id' => $signerId,
            'creado_por' => $officeAdminId,
            'version' => 2,
            'asunto' => 'Documento final ficticio para cierre',
        ]);
        $borradorId = $borrador->id;
        $assignment = TramiteAsignacion::factory()->create([
            'tramite_id' => $tramiteId,
            'revisor_id' => $reviewerId,
            'asignado_por' => $officeAdminId,
            'estado' => 'finalizada',
            'activa' => false,
            'fecha_finalizacion' => now(),
        ]);
        $assignmentId = $assignment->id;
        $ronda = TramiteRondaRevision::factory()->create([
            'tramite_id' => $tramiteId,
            'asignacion_id' => $assignmentId,
            'revisor_id' => $reviewerId,
            'borrador_id' => $borradorId,
            'estado' => 'aprobado',
            'activa' => false,
            'cerrada_en' => now(),
            'conclusion' => 'La versión de prueba quedó aprobada.',
        ]);
        $rondaId = $ronda->id;

        $numeroDocumento = 'QAP7-'.$anio.'-'.Str::upper(Str::random(8));
        $codigoVerificacion = implode('-', str_split(Str::upper(bin2hex(random_bytes(8))), 4));
        $snapshot = [
            'institucion' => (string) config('app.name'),
            'tipo_documento' => $plantilla->nombre,
            'numero' => $numeroDocumento,
            'codigo_expediente' => $marker,
            'fecha_documento' => now()->format('d/m/Y'),
            'asunto' => 'Documento final ficticio para cierre',
            'destinatarios' => ['Destinatario Ficticio · Secretaría de prueba'],
            'remitente' => $sender->name.' · Administración',
            'introduccion' => 'Introducción artificial para verificar el cierre del expediente.',
            'contenido_principal' => 'Texto de prueba del documento oficial aprobado.',
            'cierre' => 'Atentamente.',
            'personas' => [],
            'firmante' => $signer->name.' · Docente',
            'decision' => 'aprobado',
            'conclusion' => 'La versión de prueba quedó aprobada.',
            'comentario_publico' => 'Resultado ficticio para pruebas.',
            'codigo_verificacion' => $codigoVerificacion,
            'version_borrador' => 2,
            'requiere_firma_fisica' => true,
            'permite_no_firma' => false,
            'plantilla' => $plantilla->nombre,
        ];
        $pdf = app(PdfDocumentGenerator::class)->generate($snapshot);
        $rutaDocumento = 'documentos-finales/prueba-'.$marker.'.pdf';
        Storage::disk('local')->put($rutaDocumento, $pdf['bytes']);

        $serie = TramiteSerieDocumental::query()->create([
            'tipo_documento_salida' => $tipoDocumento,
            'modalidad' => 'unica',
            'anio' => $anio,
            'codigo' => 'QA-P7-'.Str::upper(Str::random(7)),
            'prefijo' => 'QAP7'.Str::upper(Str::random(3)),
            'ultimo_correlativo' => 1,
            'activa' => true,
        ]);
        $serieId = $serie->id;
        $numero = $serie->prefijo.'-'.$anio.'-000001';
        $numeracion = TramiteNumeracionDocumental::query()->create([
            'serie_id' => $serieId,
            'tramite_id' => $tramiteId,
            'borrador_id' => $borradorId,
            'ronda_revision_id' => $rondaId,
            'anio' => $anio,
            'correlativo' => 1,
            'numero_completo' => $numero,
            'estado' => 'emitida',
            'reservada_por' => $officeAdminId,
        ]);
        $numeracionId = $numeracion->id;
        $documento = TramiteDocumentoFinal::query()->create([
            'tramite_id' => $tramiteId,
            'numeracion_id' => $numeracionId,
            'borrador_id' => $borradorId,
            'ronda_revision_id' => $rondaId,
            'version' => 1,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numero,
            'codigo_verificacion' => $codigoVerificacion,
            'estado' => 'emitido',
            'activo' => true,
            'disco' => 'local',
            'ruta' => $rutaDocumento,
            'nombre_archivo' => basename($rutaDocumento),
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $pdf['bytes']),
            'tamano_bytes' => strlen($pdf['bytes']),
            'numero_paginas' => $pdf['paginas'],
            'contenido_snapshot' => $snapshot,
            'generado_por' => $officeAdminId,
            'fecha_emision' => now(),
        ]);
        $documentoId = $documento->id;
        $medio = DB::table('tramite_medios_entrega')->where('codigo', 'presencial')->first();
        expect($medio)->not->toBeNull();

        $deliveryPayload = [
            'medio_entrega_id' => (int) $medio->id,
            'receptor_nombre' => 'Estudiante Ficticio',
            'receptor_documento' => '12345678',
            'receptor_tipo' => 'Estudiante',
            'receptor_relacion' => null,
            'fecha_entrega' => now()->format('Y-m-d\\TH:i'),
            'tipo_evidencia' => 'Constancia firmada',
            'observacion' => 'Entrega ficticia de prueba.',
        ];

        $this->actingAs($reviewer)->get(route('tramites.entrega.show', $tramiteId))->assertForbidden();
        $this->actingAs($officeAdmin)
            ->post(route('tramites.entrega.registrar', $tramiteId), $deliveryPayload)
            ->assertStatus(409);
        $this->get(route('tramites.entrega.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/entrega')
                ->where('tramite.estado', 'documento_final_generado')
                ->where('puede_preparar', true));

        $this->post(route('tramites.entrega.prepare', $tramiteId))->assertRedirect(route('tramites.entrega.show', $tramiteId));
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('pendiente_firma');
        $this->post(route('tramites.entrega.prepare', $tramiteId))->assertStatus(409);
        $this->post(route('tramites.entrega.firma', $tramiteId), [])->assertSessionHasErrors('fecha_firma');
        $this->post(route('tramites.entrega.firma', $tramiteId), ['no_requiere_firma' => '1'])->assertStatus(409);

        $this->post(route('tramites.entrega.firma', $tramiteId), [
            'fecha_firma' => now()->format('Y-m-d\\TH:i'),
            'observacion' => 'Firma física ficticia para prueba.',
            'evidencia' => UploadedFile::fake()->createWithContent('firma-ficticia.pdf', $pdf['bytes']),
        ])->assertRedirect(route('tramites.entrega.show', $tramiteId));
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('listo_entrega');
        $firma = DB::table('tramite_firmas')->where('tramite_id', $tramiteId)->first();
        expect($firma)->not->toBeNull();
        expect((int) $firma->no_requiere_firma)->toBe(0);
        expect($firma->nombre_original)->toBe('firma-ficticia.pdf');
        expect(hash_file('sha256', Storage::disk('local')->path($firma->ruta)))->toBe($firma->sha256);
        $this->post(route('tramites.entrega.firma', $tramiteId), ['fecha_firma' => now()->format('Y-m-d\\TH:i')])->assertStatus(409);

        $this->from(route('tramites.entrega.show', $tramiteId))
            ->post(route('tramites.entrega.registrar', $tramiteId), [...$deliveryPayload, 'tipo_evidencia' => null])
            ->assertSessionHasErrors('evidencia');
        $this->post(route('tramites.entrega.cerrar', $tramiteId), ['resumen' => 'Cierre sin una entrega confirmada.'])
            ->assertStatus(409);
        $this->post(route('tramites.entrega.registrar', $tramiteId), [
            ...$deliveryPayload,
            'evidencia' => UploadedFile::fake()->createWithContent('constancia-ficticia.pdf', $pdf['bytes']),
        ])->assertRedirect(route('tramites.entrega.show', $tramiteId));

        $entrega = TramiteEntrega::query()->where('tramite_id', $tramiteId)->firstOrFail();
        $entregaId = $entrega->id;
        expect($entrega->confirmado)->toBeFalse();
        expect($entrega->receptor_documento)->toBe('*****678');
        expect(DB::table('tramite_entregas')->where('tramite_id', $tramiteId)->where('receptor_documento', '12345678')->exists())->toBeFalse();
        $evidencia = DB::table('tramite_evidencias_entrega')->where('entrega_id', $entregaId)->whereNotNull('ruta')->first();
        expect($evidencia)->not->toBeNull();
        expect(hash_file('sha256', Storage::disk('local')->path($evidencia->ruta)))->toBe($evidencia->sha256);
        $this->post(route('tramites.entrega.registrar', $tramiteId), [
            ...$deliveryPayload,
            'evidencia' => UploadedFile::fake()->createWithContent('duplicada.pdf', $pdf['bytes']),
        ])->assertStatus(409);

        $this->actingAs($student)
            ->get(route('estudiante.tramites.show', $tramiteId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tramites/estudiante-show')
                ->where('entrega.receptor_documento', '*****678')
                ->where('puede_confirmar_entrega', true));
        $this->actingAs($foreignStudent)->get(route('estudiante.tramites.show', $tramiteId))->assertForbidden();
        $this->actingAs($officeAdmin)
            ->post(route('tramites.entrega.cerrar', $tramiteId), ['resumen' => 'Intento de cierre previo a la recepción.'])
            ->assertStatus(409);
        $this->post(route('tramites.entrega.confirmar', $tramiteId), [])->assertSessionHasErrors('confirmar');
        $this->actingAs($foreignStudent)
            ->post(route('tramites.entrega.confirmar', $tramiteId), ['confirmar' => true])
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('tramites.entrega.confirmar', $tramiteId), [
                'confirmar' => true,
                'observacion' => 'Recepción ficticia confirmada por la persona propietaria.',
            ])->assertRedirect();
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('entregado');
        expect(DB::table('tramite_entregas')->where('id', $entregaId)->value('confirmado_por_estudiante'))->toBe(1);
        $this->post(route('tramites.entrega.confirmar', $tramiteId), ['confirmar' => true])->assertStatus(409);

        $this->actingAs($officeAdmin);
        $this->post(route('tramites.entrega.cerrar', $tramiteId), ['resumen' => 'corto'])->assertSessionHasErrors('resumen');
        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('entregado');
        $this->post(route('tramites.entrega.cerrar', $tramiteId), [
            'resumen' => 'Cierre administrativo ficticio posterior a la recepción confirmada.',
            'observacion' => 'Informe de prueba sin datos reales.',
        ])->assertRedirect(route('tramites.entrega.show', $tramiteId));

        expect(DB::table('tramites')->where('id', $tramiteId)->value('estado'))->toBe('cerrado');
        $cierre = DB::table('tramite_cierres')->where('tramite_id', $tramiteId)->first();
        $informe = DB::table('tramite_informes_cierre')->where('tramite_id', $tramiteId)->first();
        expect($cierre)->not->toBeNull();
        expect($informe)->not->toBeNull();
        $informeId = (int) $informe->id;
        expect((int) $informe->numero_paginas)->toBeGreaterThan(0);
        expect($informe->sha256)->toMatch('/^[a-f0-9]{64}$/');
        $informePath = Storage::disk('local')->path($informe->ruta);
        $informeBytes = file_get_contents($informePath);
        expect(str_starts_with((string) $informeBytes, '%PDF-'))->toBeTrue();
        expect(str_contains((string) $informeBytes, 'INFORME FINAL ADMINISTRATIVO'))->toBeTrue();
        expect(hash('sha256', (string) $informeBytes))->toBe($informe->sha256);
        expect(filesize($informePath))->toBe((int) $informe->tamano_bytes);
        $root = realpath((string) config('filesystems.disks.local.root'));
        $privatePath = realpath($informePath);
        expect(is_string($root) && is_string($privatePath))->toBeTrue();
        expect(str_starts_with((string) $privatePath, rtrim((string) $root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))->toBeTrue();

        $studentReport = $this->actingAs($student)
            ->get(route('tramites.informes-cierre.descargar', [$tramiteId, $informeId]))
            ->assertOk()
            ->assertDownload($informe->nombre_archivo)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($studentReport->headers->get('Cache-Control'))
            ->toContain('private')
            ->toContain('no-store')
            ->not->toContain('public');
        $this->actingAs($foreignStudent)
            ->get(route('tramites.informes-cierre.descargar', [$tramiteId, $informeId]))
            ->assertForbidden();
        $this->actingAs($officeAdmin)
            ->get(route('tramites.evidencias.descargar', [$tramiteId, $evidencia->id]))
            ->assertOk()
            ->assertDownload('constancia-ficticia.pdf');
        $this->post(route('tramites.entrega.cerrar', $tramiteId), [
            'resumen' => 'Intento de cierre repetido posterior a la conclusión.',
        ])->assertStatus(409);

        expect(DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->pluck('accion')->all())
            ->toContain('entrega_preparada')
            ->toContain('firma_registrada')
            ->toContain('entrega_registrada')
            ->toContain('recepcion_confirmada')
            ->toContain('expediente_cerrado')
            ->toContain('informe_cierre_generado')
            ->toContain('descarga_informe_cierre')
            ->toContain('acceso_entrega_no_autorizado');

        Storage::disk('local')->put($informe->ruta, 'archivo de cierre modificado');
        $this->get(route('tramites.informes-cierre.descargar', [$tramiteId, $informeId]))->assertNotFound();
    } finally {
        try {
            foreach (['documentos-finales', 'firmas', 'evidencias-entrega', 'informes-cierre'] as $directory) {
                Storage::disk('local')->deleteDirectory($directory);
            }

            if ($tramiteId !== null) {
                DB::table('tramite_informes_cierre')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_cierres')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_evidencias_entrega')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_entregas')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_firmas')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_documentos_finales')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_numeraciones_documentales')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_eventos')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramite_rondas_revision')->where('tramite_id', $tramiteId)->delete();

                if ($assignmentId !== null) {
                    DB::table('tramite_asignaciones')->where('id', $assignmentId)->delete();
                }

                if ($borradorId !== null) {
                    DB::table('tramite_borradores')->where('id', $borradorId)->delete();
                }

                DB::table('tramite_borrador_secuencias')->where('tramite_id', $tramiteId)->delete();
                DB::table('tramites')->where('id', $tramiteId)->delete();
            }

            if ($serieId !== null) {
                TramiteSerieDocumental::query()->whereKey($serieId)->delete();
            }

            if ($plantillaId !== null) {
                DB::table('tramite_plantillas')->where('id', $plantillaId)->delete();
            }

            foreach ([$officeAdminId, $studentId, $foreignStudentId, $senderId, $signerId, $reviewerId] as $userId) {
                if ($userId !== null) {
                    DB::table('users')->where('id', $userId)->delete();
                }
            }
        } finally {
            DB::setDefaultConnection($defaultConnection);
        }
    }
});

test('intake receipt preserves the initial state and excludes private fields', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $tramite = Tramite::factory()->create([
        'propietario_id' => $student->id,
        'recibido_por' => $officeAdmin->id,
        'persona_nombre' => 'Interesada de prueba',
        'persona_identificador' => 'DNI-PRIVADO-12345678',
        'descripcion' => 'Nota privada del expediente',
        'asunto' => 'Solicitud de constancia',
        'fecha_recepcion' => '2026-09-28',
        'estado' => 'cerrado',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'recepcion',
        'descripcion' => 'Dato interno de recepción',
        'estado_nuevo' => 'recibido_oficina',
        'metadatos' => ['ruta_privada' => 'secreto-de-prueba'],
    ]);

    $this->get(route('tramites.receipt', $tramite))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('tramites.receipt', $tramite))->assertForbidden();

    $this->actingAs($officeAdmin);
    $response = $this->get(route('tramites.receipt', $tramite));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/comprobante')
        ->where('comprobante.codigo', $tramite->codigo)
        ->where('comprobante.fecha_recepcion', '2026-09-28')
        ->where('comprobante.tipo_tramite', 'FUT')
        ->where('comprobante.estado_inicial', 'Recibido en oficina')
        ->where('comprobante.interesado', 'Interesada de prueba')
        ->where('comprobante.asunto', 'Solicitud de constancia')
        ->missing('comprobante.persona_identificador')
        ->missing('comprobante.descripcion')
        ->missing('comprobante.ruta_privada'));
    expect($response->getContent())->not->toContain('DNI-PRIVADO-12345678', 'Nota privada del expediente', 'secreto-de-prueba');

    $this->actingAs($administrator)->get(route('tramites.receipt', $tramite))->assertOk();
    $officeAdmin->forceFill(['activo' => false])->save();
    $this->actingAs($officeAdmin)->get(route('tramites.receipt', $tramite))->assertForbidden();
});

test('global search restricts results by role and assignment', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $reviewer = User::factory()->create(['rol' => 'docente', 'name' => 'Docente Reservado']);
    $otherReviewer = User::factory()->create(['rol' => 'docente']);
    $student = User::factory()->create(['rol' => 'estudiante', 'name' => 'Estudiante Visible']);
    $student->forceFill(['dni' => '87654321', 'nombres' => 'Nombre Reservado', 'apellidos' => 'Apellido Reservado'])->save();
    PerfilEstudiante::factory()->create(['user_id' => $student->id, 'codigo_estudiante' => 'EST-SEARCH-555']);
    PerfilDocente::factory()->create(['user_id' => $reviewer->id, 'codigo_docente' => 'DOC-SEARCH-999']);
    $tramite = Tramite::factory()->create([
        'codigo' => 'TRM-SEARCH-000001',
        'asunto' => 'Consulta reservada',
        'propietario_id' => $student->id,
        'persona_nombre' => 'Persona Privada',
        'persona_identificador' => 'DNI-PRIVADO-555',
        'estado' => 'asignado',
    ]);
    TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $officeAdmin->id,
        'destino' => 'docente',
        'activa' => true,
    ]);

    DB::table('personas_relacionadas_expediente')->insert([
        'tramite_id' => $tramite->id,
        'nombres' => 'Persona relacionada',
        'dni' => '11112222',
        'tipo_relacion' => 'interesado',
        'orden' => 1,
        'activo' => true,
        'created_at' => now(),
    ]);
    DB::table('documento_personas_mencionadas')->insert([
        'tramite_id' => $tramite->id,
        'nombres' => 'Persona mencionada',
        'dni' => '33334444',
        'orden' => 1,
        'activo' => true,
        'created_at' => now(),
    ]);

    $this->get(route('search.index', ['q' => 'SEARCH']))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('search.index', ['q' => 'SEARCH']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('student', true)
        ->where('results.expedientes.0.id', $tramite->id)
        ->has('results.personas', 0)
        ->has('results.documentos', 0));
    $this->get(route('search.index', ['q' => '87654321']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id));
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent->forceFill(['dni' => '22223333'])->save();
    Tramite::factory()->create(['codigo' => 'TRM-PRIVADO-OTRO', 'propietario_id' => $otherStudent->id]);
    $this->actingAs($student)->get(route('search.index', ['q' => '22223333']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('results.expedientes', 0)
        ->has('results.personas', 0));
    $this->actingAs($otherReviewer)->get(route('search.index', ['q' => 'SEARCH']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.expedientes', 0)->has('results.personas', 0));
    $this->actingAs($reviewer)->get(route('search.index', ['q' => 'SEARCH']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.codigo', 'TRM-SEARCH-000001')
        ->has('results.personas', 0)
        ->has('results.documentos', 0));
    $this->get(route('search.index', ['q' => '87654321']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.id', $tramite->id)
        ->has('results.personas', 0));
    $this->get(route('search.index', ['q' => '11112222']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id));
    $this->get(route('search.index', ['q' => '33334444']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id));

    $officeAdminResponse = $this->actingAs($officeAdmin)->get(route('search.index', ['q' => 'DNI-PRIVADO-555']));
    $officeAdminResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.codigo', 'TRM-SEARCH-000001')
        ->missing('results.expedientes.0.persona_identificador'));
    $this->get(route('search.index', ['q' => '11112222']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id));

    $this->actingAs($officeAdmin)->get(route('search.index', ['q' => 'Docente Reservado']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.personas.0.id', $reviewer->id));
    $this->get(route('search.index', ['q' => 'EST-SEARCH-555']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.personas.0.id', $student->id)
        ->missing('results.personas.0.codigo_estudiante'));
    $this->get(route('search.index', ['q' => '87654321']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.id', $tramite->id)
        ->where('results.personas.0.id', $student->id)
        ->missing('results.personas.0.dni'));
    $this->get(route('search.index', ['q' => 'DOC-SEARCH-999']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.personas.0.id', $reviewer->id));
    $this->actingAs($administrator)->get(route('search.index', ['q' => 'Docente Reservado']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.personas.0.name', 'Docente Reservado'));
    $this->get(route('search.index', ['q' => 'DOC-SEARCH-999']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('administrator', true)
        ->where('results.personas.0.id', $reviewer->id)
        ->missing('results.personas.0.codigo_docente'));
    $this->get(route('search.index', ['q' => '87654321']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.id', $tramite->id)
        ->where('results.personas.0.id', $student->id));
    $officeAdmin->forceFill(['activo' => false])->save();
    $this->actingAs($officeAdmin)->get(route('search.index', ['q' => 'SEARCH']))->assertForbidden();
});

test('global search opens only a reviewers completed assignment as read only', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $reviewer = User::factory()->create(['rol' => 'docente']);
    $otherReviewer = User::factory()->create(['rol' => 'docente']);
    $tramite = Tramite::factory()->create(['codigo' => 'TRM-HISTORICO-001', 'estado' => 'aprobado']);
    $reviewedDraft = TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'version' => 1,
        'es_actual' => false,
        'asunto' => 'Borrador revisado en la ronda finalizada',
        'contenido_renderizado' => 'Texto exacto de la versión revisada',
    ]);
    TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'version' => 2,
        'es_actual' => true,
        'asunto' => 'Borrador posterior ajeno a la ronda',
        'contenido_renderizado' => 'Texto ajeno de una versión posterior',
    ]);
    $assignment = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $officeAdmin->id,
        'destino' => 'docente',
        'estado' => 'aprobado',
        'activa' => false,
    ]);
    TramiteRondaRevision::factory()->create([
        'tramite_id' => $tramite->id,
        'asignacion_id' => $assignment->id,
        'revisor_id' => $reviewer->id,
        'borrador_id' => $reviewedDraft->id,
        'estado' => 'aprobado',
        'activa' => false,
    ]);

    $this->actingAs($reviewer)->get(route('search.index', ['q' => 'HISTORICO']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id));
    $this->get(route('asignaciones.docente.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('borrador.version', 1)
        ->where('borrador.asunto', 'Borrador revisado en la ronda finalizada')
        ->where('borrador.contenido_renderizado', 'Texto exacto de la versión revisada')
        ->where('revision.puede_iniciar', false)
        ->where('revision.puede_observar', false)
        ->where('revision.puede_decidir', false));
    $this->post(route('tramites.revision.start', $tramite))->assertForbidden();
    $this->actingAs($otherReviewer)->get(route('search.index', ['q' => 'HISTORICO']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.expedientes', 0));
    $this->get(route('asignaciones.docente.show', $tramite))->assertForbidden();

    $assignment->forceFill(['estado' => 'cancelada'])->save();
    $this->actingAs($reviewer)->get(route('search.index', ['q' => 'HISTORICO']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.expedientes', 0));
    $this->get(route('asignaciones.docente.show', $tramite))->assertForbidden();
});

test('global search limits results, treats wildcard characters literally, and finds issued documents', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['codigo' => 'TRM-X%-001', 'asunto' => 'Caso de prueba']);
    Tramite::factory()->create(['codigo' => 'TRM-XA-002', 'asunto' => 'Otro caso']);
    $documento = TramiteDocumentoFinal::factory()->create([
        'tramite_id' => $tramite->id,
        'numero_documento' => 'OFI-SEARCH-001',
        'codigo_verificacion' => 'ABCD-EFGH-SEARCH',
        'estado' => 'emitido',
        'activo' => true,
        'fecha_emision' => now(),
    ]);

    $this->actingAs($officeAdmin)->get(route('search.index', ['q' => '  X%  ']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('query', 'X%')
        ->has('results.expedientes', 1)
        ->where('results.expedientes.0.codigo', 'TRM-X%-001'));
    $this->get(route('search.index', ['q' => 'OFI-SEARCH-001']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.documentos.0.id', $documento->id)
        ->where('results.documentos.0.tramite_id', $tramite->id)
        ->missing('results.documentos.0.ruta'));
    $this->get(route('search.index', ['q' => 'A']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'short')->has('results.expedientes', 0));
    $this->get(route('search.index', ['q' => str_repeat('A', 81)]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'too_long')->has('results.expedientes', 0));

    for ($index = 0; $index < 13; $index++) {
        Tramite::factory()->create(['asunto' => 'LIMITE GLOBAL '.$index]);
    }

    $this->get(route('search.index', ['q' => 'LIMITE GLOBAL']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.expedientes', 12));
});

test('student timeline shows ordered public milestones and observations only to the owner', function () {
    $student = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $reviewer = User::factory()->create(['rol' => 'docente', 'name' => 'Revisor Confidencial']);
    $tramite = Tramite::factory()->create([
        'propietario_id' => $student->id,
        'recibido_por' => $officeAdmin->id,
        'estado' => 'observado',
    ]);
    $asignacion = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $officeAdmin->id,
        'activa' => false,
    ]);
    $borrador = TramiteBorrador::factory()->create(['tramite_id' => $tramite->id]);
    $ronda = TramiteRondaRevision::factory()->create([
        'tramite_id' => $tramite->id,
        'asignacion_id' => $asignacion->id,
        'revisor_id' => $reviewer->id,
        'borrador_id' => $borrador->id,
        'estado' => 'observada',
        'activa' => false,
    ]);

    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'recepcion',
        'descripcion' => 'Nota privada de recepción.',
        'estado_nuevo' => 'recibido_oficina',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'asignacion_creada',
        'descripcion' => 'Asignado a Revisor Confidencial.',
        'estado_anterior' => 'pendiente_asignacion',
        'estado_nuevo' => 'asignado',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $reviewer->id,
        'accion' => 'observacion',
        'descripcion' => 'Comentario interno de revisión.',
        'estado_anterior' => 'en_revision',
        'estado_nuevo' => 'observado',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'entrega_registrada',
        'descripcion' => 'Evidencia interna de entrega.',
        'estado_anterior' => 'listo_entrega',
        'estado_nuevo' => 'listo_entrega',
    ]);
    $ronda->observaciones()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'categoria' => 'Contenido',
        'titulo' => 'Observación pública',
        'descripcion' => 'Complete el dato solicitado.',
        'obligatoria' => true,
        'visible_para_interesado' => true,
        'orden' => 1,
    ]);
    $ronda->observaciones()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'categoria' => 'Contenido',
        'titulo' => 'Observación interna',
        'descripcion' => 'Ruta privada y nota interna.',
        'obligatoria' => false,
        'visible_para_interesado' => false,
        'orden' => 2,
    ]);

    $this->actingAs($student);
    $response = $this->get(route('estudiante.tramites.show', $tramite));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/estudiante-show')
        ->has('historial', 5)
        ->where('historial.0.label', 'Expediente recibido')
        ->where('historial.1.label', 'Asignado')
        ->where('historial.2.label', 'Observado')
        ->where('historial.3.label', 'Entrega registrada')
        ->where('historial.4.descripcion', 'Complete el dato solicitado.')
        ->missing('historial.4.id')
        ->missing('historial.4.usuario_id'));
    expect($response->getContent())->not->toContain(
        'Nota privada de recepción.',
        'Revisor Confidencial',
        'Comentario interno de revisión.',
        'Evidencia interna de entrega.',
        'Ruta privada y nota interna.',
    );

    $this->actingAs($otherStudent)->get(route('estudiante.tramites.show', $tramite))->assertForbidden();
    $this->actingAs($officeAdmin)->get(route('estudiante.tramites.show', $tramite))->assertForbidden();
});

test('student timeline distinguishes a waived physical signature from a registered signature', function () {
    $student = User::factory()->create(['rol' => 'estudiante']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create([
        'propietario_id' => $student->id,
        'estado' => 'listo_entrega',
        'fecha_recepcion' => '2026-09-28',
        'fecha_llegada_oficina' => '2026-09-28 16:45:00',
    ]);
    $documento = TramiteDocumentoFinal::factory()->create(['tramite_id' => $tramite->id, 'estado' => 'emitido']);
    $firma = TramiteFirma::query()->create([
        'tramite_id' => $tramite->id,
        'documento_final_id' => $documento->id,
        'registrado_por' => $officeAdmin->id,
        'no_requiere_firma' => true,
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'firma_registrada',
        'descripcion' => 'Dato interno de exoneración.',
        'estado_anterior' => 'pendiente_firma',
        'estado_nuevo' => 'listo_entrega',
        'metadatos' => ['firma_id' => $firma->id, 'evidencia' => false],
    ]);

    $response = $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('historial', 2)
        ->where('historial.0.fecha', fn (string $fecha): bool => str_contains($fecha, 'T16:45:00'))
        ->where('historial.1.label', 'Firma no requerida')
        ->where('historial.1.descripcion', 'El documento quedó habilitado sin firma física.')
        ->missing('historial.1.id')
        ->missing('historial.1.metadatos'));
    expect($response->getContent())->not->toContain('Dato interno de exoneración.', 'Firma registrada');

    $conFirma = Tramite::factory()->create(['propietario_id' => $student->id, 'estado' => 'listo_entrega']);
    $documentoFirmado = TramiteDocumentoFinal::factory()->create(['tramite_id' => $conFirma->id, 'estado' => 'emitido']);
    $firmaFisica = TramiteFirma::query()->create([
        'tramite_id' => $conFirma->id,
        'documento_final_id' => $documentoFirmado->id,
        'registrado_por' => $officeAdmin->id,
        'no_requiere_firma' => false,
        'fecha_firma' => now(),
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $conFirma->id,
        'usuario_id' => $officeAdmin->id,
        'accion' => 'firma_registrada',
        'descripcion' => 'Nota interna de firma.',
        'estado_anterior' => 'pendiente_firma',
        'estado_nuevo' => 'listo_entrega',
        'metadatos' => ['firma_id' => $firmaFisica->id, 'evidencia' => true],
    ]);
    $this->get(route('estudiante.tramites.show', $conFirma))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('historial.1.label', 'Firma registrada')
            ->where('historial.1.descripcion', 'La firma del documento fue registrada.'));
});

test('official PDF draws a QR for the configured public verification URL and omits it for an invalid URL', function () {
    $codigo = 'A1B2-C3D4-E5F6-7890';
    $documento = [
        'institucion' => 'Instituto Seoane',
        'tipo_documento' => 'Memorando',
        'numero' => 'MEM-2026-000001',
        'codigo_expediente' => 'EXP-2026-000001',
        'fecha_documento' => '29/09/2026',
        'asunto' => 'Constancia de prueba',
        'destinatarios' => ['Secretaría académica'],
        'remitente' => 'Gestión documentaria',
        'introduccion' => null,
        'contenido_principal' => 'Contenido público del memorando.',
        'cierre' => null,
        'personas' => [],
        'firmante' => 'Dirección académica',
        'decision' => 'aprobado',
        'conclusion' => null,
        'comentario_publico' => null,
        'codigo_verificacion' => $codigo,
        'version_borrador' => 1,
    ];
    $urlOriginal = config('app.url');
    $generador = app(PdfDocumentGenerator::class);

    config(['app.url' => 'https://tramites.example.test']);
    $pdf = $generador->generate($documento);
    config(['app.url' => 'https://otra-sede.example.test']);
    $pdfConOtraUrl = $generador->generate($documento);
    config(['app.url' => 'https://usuario@tramites.example.test']);
    $pdfSinUrlPublica = $generador->generate($documento);
    config(['app.url' => $urlOriginal]);

    $urlEsperada = 'https://tramites.example.test'.route('documentos.verificar', ['codigo' => $codigo], false);
    $matrizEsperada = Encoder::encode($urlEsperada, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
    $modulosOscuros = count(array_filter(iterator_to_array($matrizEsperada->getBytes()), static fn (int $valor): bool => $valor === 1));

    expect($pdf['paginas'])->toBe(1);
    expect($pdf['bytes'])->toContain('463 62 82 82 re f')
        ->toContain($codigo)
        ->not->toBe($pdfConOtraUrl['bytes']);
    expect(substr_count($pdf['bytes'], ' re f'))->toBe($modulosOscuros + 1);
    expect($pdfSinUrlPublica['bytes'])->not->toContain('463 62 82 82 re f');
});

test('memorandum layout follows the canonical output type when its template is renamed', function () {
    $multiple = app(PdfDocumentGenerator::class)->generate([
        'institucion' => 'Instituto Seoane',
        'tipo_documento' => 'Autorización de ingreso',
        'tipo_documento_salida' => 'memorando',
        'modalidad_documento' => 'multiple',
        'numero' => '005 / DSI / HACH / IESTP “MSC”-2026',
        'codigo_expediente' => 'EXP-TIPO-000001',
        'fecha_documento' => '28/05/2026',
        'lugar' => 'Lima',
        'asunto' => 'Comunicación institucional',
        'destinatarios' => ['Área académica', 'Área administrativa'],
        'remitente' => 'Gestión documentaria',
        'contenido_principal' => 'Contenido del memorando.',
        'personas' => [],
        'firmante' => 'Dirección académica',
        'codigo_verificacion' => '',
    ]);
    $simple = app(PdfDocumentGenerator::class)->generate([
        'institucion' => 'Instituto Seoane',
        'tipo_documento' => 'MEMORANDO MULTIPLE RENOMBRADO',
        'tipo_documento_salida' => 'memorando',
        'modalidad_documento' => 'simple',
        'numero' => '028-DSI-HACH-IESTP MSC-2026',
        'codigo_expediente' => 'EXP-TIPO-000002',
        'fecha_documento' => '18/06/2026',
        'lugar' => 'SJL',
        'asunto' => 'Autorización de ingreso',
        'destinatarios' => ['Dirección general'],
        'remitente' => 'Coordinación académica',
        'contenido_principal' => 'Contenido del memorando simple.',
        'personas' => [],
        'firmante' => 'Dirección académica',
        'codigo_verificacion' => '',
    ]);
    $nonMemorando = app(PdfDocumentGenerator::class)->generate([
        'institucion' => 'Instituto Seoane',
        'tipo_documento' => 'MEMORANDO MULTIPLE RENOMBRADO',
        'tipo_documento_salida' => 'informe',
        'modalidad_documento' => 'multiple',
        'numero' => 'INF-2026-000003',
        'codigo_expediente' => 'EXP-TIPO-000003',
        'fecha_documento' => '18/06/2026',
        'lugar' => 'SJL',
        'asunto' => 'Informe de prueba',
        'destinatarios' => ['Dirección general'],
        'remitente' => 'Coordinación académica',
        'introduccion' => null,
        'contenido_principal' => 'Contenido del informe.',
        'cierre' => null,
        'personas' => [],
        'firmante' => 'Dirección académica',
        'decision' => 'aprobado',
        'conclusion' => null,
        'comentario_publico' => null,
        'codigo_verificacion' => '',
        'version_borrador' => 1,
    ]);
    $crest = file_get_contents(base_path('resources/images/institucion/encabezado-memorando-multiple.jpeg'));

    $titulo = (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', 'MEMORANDO MÚLTIPLE');
    $tituloSimple = 'MEMORANDUM';

    expect($multiple['bytes'])->toContain($titulo)
        ->toContain('INSTITUTO DE EDUCACI');
    expect(is_string($crest))->toBeTrue();
    expect($multiple['bytes'])->toContain($crest);
    expect($simple['bytes'])->not->toContain($crest);
    expect($simple['bytes'])
        ->toContain($tituloSimple)
        ->toContain('/Width 1248 /Height 116')
        ->toContain('/SMask');
    expect($nonMemorando['bytes'])
        ->toContain('MEMORANDO MULTIPLE RENOMBRADO')
        ->not->toContain($titulo)
        ->not->toContain('INSTITUTO DE EDUCACI');
});

test('long memorandum keeps its closing and signature on the final page', function () {
    $documento = [
        'institucion' => 'Instituto Seoane',
        'tipo_documento' => 'MEMORANDO MULTIPLE',
        'numero' => '005 / DSI / HACH / IESTP “MSC”-2026',
        'codigo_expediente' => 'EXP-LARGO-000001',
        'fecha_documento' => '28/05/2026',
        'lugar' => 'San Juan de Lurigancho',
        'asunto' => 'Justificación por tardanza',
        'destinatarios' => ['Docente 1', 'Docente 2', 'Docente 3'],
        'remitente_nombre' => 'Henry Arteaga Chauca',
        'remitente_cargo' => 'Coordinador Academico',
        'introduccion' => str_repeat('Texto extenso de justificación para comprobar la paginación del memorando. ', 80),
        'contenido_principal' => str_repeat('El contenido debe conservar el orden y permitir la lectura completa del expediente. ', 80),
        'cierre' => 'Agradeciendo la atención prestada, quedo de ustedes.',
        'personas' => [],
        'personas_detalle' => array_map(static fn (int $indice): array => [
            'nombres' => 'Persona '.$indice,
            'apellidos' => 'Mencionada',
            'cargo' => 'Docente de prueba',
            'dni' => $indice < 10 ? '1000000'.$indice : null,
        ], range(1, 20)),
        'firmante_nombre' => 'Henry Arteaga Chauca',
        'firmante_cargo' => 'Coordinador Academico',
        'codigo_verificacion' => '',
    ];
    $pdf = app(PdfDocumentGenerator::class)->generate($documento);
    $pdfConCodigo = app(PdfDocumentGenerator::class)->generate([
        ...$documento,
        'codigo_verificacion' => 'AAAA-BBBB-CCCC-DDDD',
    ]);

    preg_match_all('/\/Contents (\d+) 0 R/', $pdf['bytes'], $referencias);
    $ultimoContenido = end($referencias[1]);
    $ultimoObjeto = is_string($ultimoContenido)
        ? preg_quote($ultimoContenido, '/')
        : '';
    preg_match('/'.$ultimoObjeto.' 0 obj\n<< \/Length \d+ >>\nstream\n(.*?)\nendstream/s', $pdf['bytes'], $coincidencia);
    preg_match_all('/\d+ 0 obj\n<< \/Length \d+ >>\nstream\n(.*?)\nendstream/s', $pdf['bytes'], $streams);
    preg_match('/BT \/F1 10 Tf 1 0 0 1 [0-9.]+ ([0-9.]+) Tm \(Atentamente\)/', $coincidencia[1] ?? '', $atentamente);
    preg_match('/BT \/F1 9 Tf 1 0 0 1 [0-9.]+ ([0-9.]+) Tm \(____/', $coincidencia[1] ?? '', $lineaFirma);
    $marcaVistaPrevia = (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Borrador sin numeración oficial');

    expect($pdf['paginas'])->toBeGreaterThan(1)
        ->and($pdfConCodigo['paginas'])->toBe($pdf['paginas'])
        ->and($pdf['bytes'])->toStartWith('%PDF-')
        ->and($pdf['bytes'])->toContain('%%EOF')
        ->and($pdf['bytes'])->toContain($marcaVistaPrevia)
        ->and($coincidencia[1] ?? '')->toContain('Atentamente')
        ->toContain('Henry Arteaga Chauca')
        ->toContain('Coordinador Academico')
        ->and(array_filter($streams[1] ?? [], static fn (string $stream): bool => str_contains($stream, 'Atentamente')))
        ->toHaveCount(1)
        ->and((float) ($atentamente[1] ?? 0))->toBeGreaterThan(200)
        ->and((float) ($lineaFirma[1] ?? 0))->toBeGreaterThan(156);
});

test('public document verification normalizes valid codes and exposes only approved metadata', function () {
    $codigo = 'A1B2-C3D4-E5F6-7890';
    $documento = TramiteDocumentoFinal::factory()->create([
        'codigo_verificacion' => $codigo,
        'tipo_documento' => 'Memorando',
        'estado' => 'emitido',
        'activo' => true,
        'fecha_emision' => now(),
        'contenido_snapshot' => ['persona_nombre' => 'Dato privado de prueba'],
    ]);

    $this->get(route('documentos.verificar', ['codigo' => strtolower($codigo)]))
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('documentos/verificacion-publica')
            ->where('resultado.estado', 'vigente')
            ->where('resultado.documento.tipo', 'Memorando')
            ->where('resultado.documento.numero', $documento->numero_documento)
            ->where('resultado.documento.expediente', $documento->tramite->codigo)
            ->where('resultado.documento.codigo', $codigo)
            ->missing('resultado.documento.contenido_snapshot')
            ->missing('resultado.documento.sha256')
            ->missing('resultado.documento.ruta'));
});

test('public document verification returns a neutral result for malformed and unknown codes', function () {
    foreach (['FFFF-FFFF-FFFF-FFFF', '<script>alert(1)</script>'] as $codigo) {
        $this->get(route('documentos.verificar', ['codigo' => $codigo]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documentos/verificacion-publica')
                ->where('resultado.estado', 'invalido')
                ->where('resultado.documento', null));
    }

    $this->get(route('documentos.verificar', ['codigo' => ['malformed']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resultado.estado', 'invalido')
            ->where('resultado.documento', null));
});

test('public document verification distinguishes annulled and superseded documents', function () {
    $anulado = TramiteDocumentoFinal::factory()->create([
        'codigo_verificacion' => 'AAAA-BBBB-CCCC-DDDD',
        'estado' => 'anulado',
        'activo' => false,
    ]);
    $sustituido = TramiteDocumentoFinal::factory()->create([
        'codigo_verificacion' => '1111-2222-3333-4444',
        'estado' => 'sustituido',
        'activo' => false,
    ]);
    $inactivo = TramiteDocumentoFinal::factory()->create([
        'codigo_verificacion' => '9999-8888-7777-6666',
        'estado' => 'emitido',
        'activo' => false,
    ]);

    $this->get(route('documentos.verificar', ['codigo' => $anulado->codigo_verificacion]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'anulado'));
    $this->get(route('documentos.verificar', ['codigo' => $sustituido->codigo_verificacion]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'sustituido'));
    $this->get(route('documentos.verificar', ['codigo' => $inactivo->codigo_verificacion]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'sustituido'));
});

test('office administrator adds independent files and replaces a current file while keeping the private history', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $otherTeacher = User::factory()->create(['rol' => 'docente']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['estado' => 'recibido_oficina', 'propietario_id' => $student->id]);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($officeAdmin)->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('original.pdf', $pdf),
        'categoria' => 'documento_original',
    ])->assertRedirect(route('tramites.show', $tramite));
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('sustento.pdf', $pdf),
        'categoria' => 'documento_escaneado',
    ])->assertRedirect(route('tramites.show', $tramite));
    $first = DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->orderBy('id')->first();
    $second = DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->orderByDesc('id')->first();

    expect($tramite->fresh()->estado)->toBe('digitalizado')
        ->and(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->where('vigente', true)->count())->toBe(2)
        ->and($first->categoria)->toBe('documento_original')
        ->and($second->categoria)->toBe('documento_escaneado');

    $this->post(route('tramites.documentos.replace', [$tramite, $second->id]), [
        'documento' => UploadedFile::fake()->createWithContent('sustento-v2.pdf', $pdf),
    ])->assertRedirect(route('tramites.show', $tramite));
    $replacement = DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->orderByDesc('id')->first();
    expect(DB::table('tramite_documentos')->where('id', $second->id)->value('vigente'))->toBe(0)
        ->and($replacement->documento_anterior_id)->toBe($second->id)
        ->and($replacement->version)->toBe(2)
        ->and($replacement->vigente)->toBe(1);
    Storage::disk('local')->assertExists([$second->ruta, $replacement->ruta]);
    $this->post(route('tramites.documentos.replace', [$tramite, $second->id]), [
        'documento' => UploadedFile::fake()->createWithContent('repetido.pdf', $pdf),
    ])->assertStatus(409);
    expect(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->count())->toBe(3);
    $otroTramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $this->post(route('tramites.documentos.replace', [$otroTramite, $replacement->id]), [
        'documento' => UploadedFile::fake()->createWithContent('ajeno.pdf', $pdf),
    ])->assertNotFound();

    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/show')
            ->where('tramite.puede_gestionar_documentos_recepcion', true)
            ->has('tramite.documentos', 3)
            ->where('tramite.documentos.2.documento_anterior_id', $second->id)
            ->missing('tramite.documentos.2.ruta'));
    $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/estudiante-show')
            ->has('documentos_recepcion', 3)
            ->missing('documentos_recepcion.0.ruta'));
    $this->get(route('tramites.documentos.descargar', [$tramite, $second->id]))->assertDownload('sustento.pdf');
    $this->actingAs($otherStudent)->get(route('tramites.documentos.descargar', [$tramite, $second->id]))->assertForbidden();

    TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $teacher->id,
        'asignado_por' => $officeAdmin->id,
        'activa' => false,
        'estado' => 'finalizada',
    ]);
    $this->actingAs($teacher)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertDownload('sustento-v2.pdf');
    $this->actingAs($otherTeacher)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertForbidden();
    $this->actingAs($administrator)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertOk();
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('admin.pdf', $pdf),
    ])->assertRedirect(route('tramites.show', $tramite));
    expect($tramite->documentos()->where('cargado_por', $administrator->id)->exists())->toBeTrue();

    $tramite->update(['estado' => 'borrador_preparado']);
    $this->actingAs($officeAdmin)->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('tardio.pdf', $pdf),
    ])->assertStatus(409);
    $this->post(route('tramites.documentos.replace', [$tramite, $replacement->id]), [
        'documento' => UploadedFile::fake()->createWithContent('tardio-v2.pdf', $pdf),
    ])->assertStatus(409);

    Storage::disk('local')->put($replacement->ruta, 'archivo alterado');
    $this->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertNotFound();
});

test('physical correction requires an observed expediente and preserves its state and earlier files', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $tramite = Tramite::factory()->create(['estado' => 'observado', 'propietario_id' => $student->id]);
    $pdf = "%PDF-1.4\n% Subsanación artificial\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($student)->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('indebido.pdf', $pdf),
        'observacion' => 'Intento de estudiante.',
    ])->assertForbidden();
    $this->actingAs($officeAdmin)->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('corregido.pdf', $pdf),
        'observacion' => '  ',
    ])->assertSessionHasErrors('observacion');
    expect(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->count())->toBe(0);

    $this->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('corregido.pdf', $pdf),
        'observacion' => 'Documento corregido recibido presencialmente.',
    ])->assertRedirect(route('tramites.show', $tramite));
    $correction = DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->first();
    expect($tramite->fresh()->estado)->toBe('observado')
        ->and($correction->categoria)->toBe('documento_corregido')
        ->and(DB::table('tramite_eventos')->where('tramite_id', $tramite->id)->where('accion', 'subsanacion_fisica')->count())->toBe(1);
    Storage::disk('local')->assertExists($correction->ruta);

    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/show')
            ->where('tramite.puede_registrar_subsanacion', true)
            ->where('tramite.estado', 'observado'));
    /** Each real HTTP request has a fresh scoped Inertia SSR state; the test client reuses one container. */
    app()->forgetScopedInstances();
    $studentResponse = $this->actingAs($student)->get(route('estudiante.tramites.show', $tramite));
    $studentResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tramites/estudiante-show')
            ->has('documentos_recepcion', 1)
            ->missing('eventos'));
    expect(str_contains($studentResponse->getContent(), 'Documento corregido recibido presencialmente.'))->toBeFalse();
    $this->actingAs($student)->get(route('tramites.documentos.descargar', [$tramite, $correction->id]))->assertDownload('corregido.pdf');
    $tramite->update(['estado' => 'cerrado']);
    $this->actingAs($officeAdmin)->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('tardio.pdf', $pdf),
        'observacion' => 'Intento tras el cierre.',
    ])->assertStatus(409);
    expect(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->count())->toBe(1);
});

test('reception file validation rejects disguised names and mismatched content before storing', function () {
    Storage::fake('local');
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $pdf = "%PDF-1.4\n% Archivo artificial\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($officeAdmin)->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('informe.php.pdf', $pdf),
    ])->assertSessionHasErrors('documento');
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('informe.pdf.exe', $pdf),
    ])->assertSessionHasErrors('documento');
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('informe.pdf', '<?php echo 1;'),
    ])->assertSessionHasErrors('documento');
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('informe.pdf', $pdf),
        'categoria' => 'documento_corregido',
    ])->assertSessionHasErrors('categoria');
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('vacio.pdf', ''),
    ])->assertSessionHasErrors('documento');

    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000010']);
    $this->post(route('tramites.store'), [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona de prueba',
        'propietario_id' => $student->id,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Documento recibido',
        'descripcion' => 'Documento recibido físicamente.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
        'documentos' => [['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('trampa.php.pdf', $pdf)]],
    ])->assertSessionHasErrors('documentos.0.archivo');

    expect(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('tramites');
});

test('office administrator registers multiple independent documents in one physical reception', function () {
    Storage::fake('local');
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000008']);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona de prueba',
        'propietario_id' => $student->id,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Ingreso físico con anexos',
        'descripcion' => 'Ingreso físico con anexos recibidos.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
    ];

    $this->actingAs($student)->post(route('tramites.store'), $payload)->assertForbidden();
    $this->actingAs($officeAdmin)->post(route('tramites.store'), [
        ...$payload,
        'documentos' => [
            ['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('solicitud.pdf', $pdf)],
            ['categoria' => 'documento_escaneado', 'archivo' => UploadedFile::fake()->createWithContent('anexo.pdf', $pdf)],
            ['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('constancia.pdf', $pdf)],
        ],
    ])->assertRedirect();

    $tramite = Tramite::query()->where('asunto', 'Ingreso físico con anexos')->sole();
    $documentos = $tramite->documentos()->orderBy('id')->get();
    expect($tramite->estado)->toBe('digitalizado')
        ->and($documentos)->toHaveCount(3)
        ->and($documentos->pluck('categoria')->all())->toBe(['documento_original', 'documento_escaneado', 'documento_original'])
        ->and($documentos->pluck('version')->all())->toBe([1, 1, 2])
        ->and($documentos->pluck('sha256')->every(fn (string $hash): bool => strlen($hash) === 64))->toBeTrue();
    Storage::disk('local')->assertExists($documentos->pluck('ruta')->all());
    $evento = $tramite->eventos()->where('accion', 'digitalizacion')->sole();
    expect($evento->metadatos['documentos_ids'])->toBe($documentos->pluck('id')->all())
        ->and($evento->metadatos['cantidad'])->toBe(3);

    $this->post(route('tramites.store'), $payload)->assertRedirect();
    expect(Tramite::query()->where('asunto', 'Ingreso físico con anexos')->where('estado', 'recibido_oficina')->count())->toBe(1);
});

test('physical reception requires confirmation and preserves document dates', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000009']);
    $programa = ProgramaEstudio::factory()->create();
    $otroPrograma = ProgramaEstudio::factory()->create();
    $perfil = PerfilEstudiante::factory()->create(['user_id' => $student->id, 'programa_estudio_id' => $programa->id]);
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona de prueba',
        'propietario_id' => $student->id,
        'programa_estudio_id' => $otroPrograma->id,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Ingreso físico confirmado',
        'descripcion' => 'Solicitud entregada en mesa de partes.',
        'prioridad' => 'alta',
        'fecha_llegada_oficina' => '2026-09-28T16:45',
        'fecha_presentacion_original' => '2026-09-27',
        'observacion_recepcion' => 'Se cotejó el documento físico.',
        'folios' => 8,
    ];

    $this->actingAs($officeAdmin)->post(route('tramites.store'), $payload)->assertSessionHasErrors('confirmar_recepcion');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '0'])->assertSessionHasErrors('confirmar_recepcion');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'fecha_llegada_oficina' => '2026-02-31T16:45'])
        ->assertSessionHasErrors('fecha_llegada_oficina');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'folios' => 5001])
        ->assertSessionHasErrors('folios');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'descripcion' => 'ab'])
        ->assertSessionHasErrors('descripcion');
    expect(Tramite::query()->where('asunto', $payload['asunto'])->count())->toBe(0);

    $this->post(route('tramites.store'), [
        ...$payload,
        'confirmar_recepcion' => '1',
        'numero_expediente_externo' => 'Dato de un cliente anterior',
        'area_procedencia' => 'Dato de un cliente anterior',
        'persona_entrega_documento' => 'Dato de un cliente anterior',
    ])->assertRedirect();
    $tramite = Tramite::query()->where('asunto', $payload['asunto'])->sole();
    expect($tramite->fecha_recepcion->toDateString())->toBe('2026-09-28')
        ->and($tramite->programa_estudio_id)->toBe($programa->id)
        ->and($tramite->fecha_llegada_oficina->format('Y-m-d H:i'))->toBe('2026-09-28 16:45')
        ->and($tramite->fecha_presentacion_original->toDateString())->toBe('2026-09-27')
        ->and($tramite->numero_expediente_externo)->toBeNull()
        ->and($tramite->area_procedencia)->toBeNull()
        ->and($tramite->persona_entrega_documento)->toBeNull()
        ->and($tramite->observacion_recepcion)->toBe('Se cotejó el documento físico.')
        ->and($tramite->prioridad)->toBe('alta');
    $perfil->update(['programa_estudio_id' => $otroPrograma->id]);
    expect($tramite->fresh()->programa_estudio_id)->toBe($programa->id);
    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramite.fecha_llegada_oficina', '2026-09-28 16:45')
            ->where('tramite.programa', $programa->nombre)
            ->missing('tramite.numero_expediente_externo')
            ->missing('tramite.area_procedencia')
            ->missing('tramite.persona_entrega_documento')
            ->where('tramite.observacion_recepcion', 'Se cotejó el documento físico.')
            ->etc());
    $this->get(route('tramites.index', ['q' => 'Ingreso físico confirmado']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramites.data.0.id', $tramite->id)->etc());
    $this->get(route('search.index', ['q' => 'Ingreso físico confirmado']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id)->etc());

    $administrativePayload = [
        ...$payload,
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'destinatarios' => [
            ['nombres' => 'Destinataria principal'],
            ['nombres' => 'Segundo destinatario'],
        ],
        'formato_salida' => 'informe',
        'modalidad_documento' => null,
        'propietario_id' => null,
        'programa_estudio_id' => $otroPrograma->id,
        'asunto' => 'Ingreso administrativo con programa',
        'confirmar_recepcion' => '1',
    ];
    $inactiveProgram = ProgramaEstudio::factory()->create(['activo' => false]);
    $this->post(route('tramites.store'), [...$administrativePayload, 'programa_estudio_id' => $inactiveProgram->id])
        ->assertSessionHasErrors('programa_estudio_id');
    $this->post(route('tramites.store'), $administrativePayload)->assertRedirect();
    expect(Tramite::query()->where('asunto', $administrativePayload['asunto'])->sole()->programa_estudio_id)
        ->toBe($otroPrograma->id);

    $otroPrograma->update(['activo' => false]);
    $this->post(route('tramites.store'), [
        ...$payload,
        'programa_estudio_id' => null,
        'asunto' => 'Ingreso con programa inactivo',
        'confirmar_recepcion' => '1',
    ])->assertSessionHasErrors('propietario_id');
    expect(Tramite::query()->where('asunto', 'Ingreso con programa inactivo')->exists())->toBeFalse();
});

test('initial reception rejects an invalid file and removes stored files when its transaction fails', function () {
    Storage::fake('local');
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000010']);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona de prueba',
        'propietario_id' => $student->id,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría',
        'asunto' => 'Ingreso que debe revertirse',
        'descripcion' => 'Ingreso físico que debe revertirse.',
        'prioridad' => 'normal',
        'fecha_llegada_oficina' => now()->format('Y-m-d\TH:i'),
        'confirmar_recepcion' => '1',
    ];

    $this->actingAs($officeAdmin)->post(route('tramites.store'), [
        ...$payload,
        'documentos' => [
            ['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('valido.pdf', $pdf)],
            ['categoria' => 'documento_escaneado', 'archivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto plano')],
        ],
    ])->assertSessionHasErrors('documentos.1.archivo');
    $this->post(route('tramites.store'), [
        ...$payload,
        'documentos' => [['categoria' => 'documento_corregido', 'archivo' => UploadedFile::fake()->createWithContent('valido.pdf', $pdf)]],
    ])->assertSessionHasErrors('documentos.0.categoria');
    expect(Tramite::query()->where('asunto', $payload['asunto'])->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('tramites');

    $insertedDocuments = 0;
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$insertedDocuments): void {
        if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'tramite_documentos')) {
            $insertedDocuments++;

            if ($insertedDocuments === 2) {
                throw new RuntimeException('Fallo simulado al guardar el segundo documento.');
            }
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('tramites.store'), [
        ...$payload,
        'documentos' => [
            ['categoria' => 'documento_original', 'archivo' => UploadedFile::fake()->createWithContent('uno.pdf', $pdf)],
            ['categoria' => 'documento_escaneado', 'archivo' => UploadedFile::fake()->createWithContent('dos.pdf', $pdf)],
        ],
    ]))->toThrow(RuntimeException::class, 'Fallo simulado');

    expect($insertedDocuments)->toBe(2)
        ->and(Tramite::query()->where('asunto', $payload['asunto'])->count())->toBe(0)
        ->and(TramiteDocumento::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('tramites');
});

test('office administrator edits reception data before assignment with validation and audit', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000011']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante', 'dni' => '90000012']);
    $tramite = Tramite::factory()->create([
        'codigo' => 'TRM-EDITAR-000001',
        'estado' => 'digitalizado',
        'propietario_id' => $student->id,
        'recibido_por' => $officeAdmin->id,
    ]);
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'CONSTANCIA_PRACTICA',
        'persona_nombre' => 'Persona corregida',
        'persona_identificador' => '87654321',
        'propietario_id' => $otherStudent->id,
        'destino_tipo' => 'oficina',
        'destino_nombre' => 'Secretaría Académica',
        'asunto' => 'Asunto corregido en recepción',
        'descripcion' => 'Datos corregidos físicamente en mesa de partes.',
        'prioridad' => 'urgente',
        'fecha_llegada_oficina' => '2026-09-29T11:30',
        'fecha_presentacion_original' => '2026-09-28',
        'observacion_recepcion' => 'Documento original visto en oficina.',
        'folios' => 4,
    ];

    $this->get(route('tramites.edit', $tramite))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('tramites.edit', $tramite))->assertForbidden();
    $this->actingAs($administrator)->get(route('tramites.edit', $tramite))->assertOk();
    $this->actingAs($officeAdmin)->get(route('tramites.edit', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/create')
        ->where('tramite.codigo', 'TRM-EDITAR-000001')
        ->where('tramite.fecha_llegada_oficina', $tramite->fecha_recepcion->format('Y-m-d\T00:00'))
        ->missing('tramite.estado')->etc());
    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramite.puede_editar_recepcion', true));

    $this->from(route('tramites.edit', $tramite))->put(route('tramites.update', $tramite), [
        ...$payload,
        'tipo_documento' => 'REQUERIMIENTO_EQUIPAMIENTO',
    ])->assertSessionHasErrors('tipo_documento');
    $this->from(route('tramites.edit', $tramite))->put(route('tramites.update', $tramite), [
        ...$payload,
        'documentos' => [['categoria' => 'documento_original', 'archivo' => 'no permitido']],
    ])->assertSessionHasErrors('documentos');
    $this->from(route('tramites.edit', $tramite))->put(route('tramites.update', $tramite), [
        ...$payload,
        'confirmar_recepcion' => '1',
    ])->assertSessionHasErrors('confirmar_recepcion');
    expect($tramite->fresh()->asunto)->not->toBe('Asunto corregido en recepción');

    $this->put(route('tramites.update', $tramite), $payload)->assertRedirect(route('tramites.show', $tramite));
    $actualizado = $tramite->fresh();
    expect($actualizado->codigo)->toBe('TRM-EDITAR-000001')
        ->and($actualizado->estado)->toBe('digitalizado')
        ->and($actualizado->recibido_por)->toBe($officeAdmin->id)
        ->and($actualizado->asunto)->toBe('Asunto corregido en recepción')
        ->and($actualizado->propietario_id)->toBe($otherStudent->id)
        ->and($actualizado->folios)->toBe(4)
        ->and($actualizado->fecha_recepcion->toDateString())->toBe('2026-09-29')
        ->and($actualizado->fecha_llegada_oficina->format('Y-m-d H:i'))->toBe('2026-09-29 11:30')
        ->and($actualizado->fecha_presentacion_original->toDateString())->toBe('2026-09-28')
        ->and($actualizado->observacion_recepcion)->toBe('Documento original visto en oficina.');
    $evento = $tramite->eventos()->where('accion', 'edicion_recepcion')->sole();
    expect($evento->usuario_id)->toBe($officeAdmin->id)
        ->and($evento->metadatos['campos'])->toContain('asunto', 'propietario_id')
        ->and(json_encode($evento->metadatos))->not->toContain('87654321');

    TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => User::factory()->create(['rol' => 'docente'])->id,
        'asignado_por' => $officeAdmin->id,
        'activa' => true,
    ]);
    $this->get(route('tramites.edit', $tramite))->assertStatus(409);
    $this->put(route('tramites.update', $tramite), $payload)->assertStatus(409);
    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramite.puede_editar_recepcion', false));
    expect($tramite->eventos()->where('accion', 'edicion_recepcion')->count())->toBe(1);

    $tramite->asignaciones()->update(['activa' => false]);
    $tramite->update(['estado' => 'borrador_preparado']);
    $this->get(route('tramites.edit', $tramite))->assertStatus(409);
    $this->put(route('tramites.update', $tramite), $payload)->assertStatus(409);
});

test('only administrators see the issued delivery register and cannot deactivate delivery media', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $issuedTramite = Tramite::factory()->create(['estado' => 'listo_entrega', 'codigo' => 'TRM-ENTREGA-EMITIDO']);
    $unissuedTramite = Tramite::factory()->create(['estado' => 'aprobado', 'codigo' => 'TRM-ENTREGA-PENDIENTE']);
    TramiteDocumentoFinal::factory()->create(['tramite_id' => $issuedTramite->id, 'estado' => 'emitido']);
    TramiteDocumentoFinal::factory()->create(['tramite_id' => $unissuedTramite->id, 'estado' => 'generando']);
    $medio = TramiteMedioEntrega::query()->create([
        'codigo' => 'presencial',
        'nombre' => 'Presencial',
        'tipo' => 'presencial',
        'activo' => true,
        'requiere_evidencia' => true,
    ]);

    $this->get(route('admin.deliveries.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['rol' => 'docente']))->get(route('admin.deliveries.index'))->assertForbidden();
    $this->actingAs($student)->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => false,
        'requiere_evidencia' => false,
    ])->assertForbidden();

    $this->actingAs($administrator)->get(route('admin.deliveries.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/entregas-admin')
        ->where('tramites.total', 1)
        ->where('tramites.data.0.codigo', 'TRM-ENTREGA-EMITIDO')
        ->where('medios.0.requiere_evidencia', true)
        ->missing('medios.0.activo')
        ->where('medios', fn ($medios): bool => collect($medios)->every(
            fn ($medio): bool => in_array($medio['codigo'], ['presencial', 'correo_electronico', 'descarga_sistema'], true),
        ))
        ->missing('tramites.data.1'));

    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => 'incorrecto',
        'requiere_evidencia' => 'incorrecto',
    ])->assertSessionHasErrors('requiere_evidencia');
    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => '0',
        'requiere_evidencia' => '0',
    ])->assertRedirect(route('admin.deliveries.index'));

    expect($medio->fresh()->activo)->toBeTrue()
        ->and($medio->fresh()->requiere_evidencia)->toBeFalse();
    $event = DB::table('tramite_config_events')->where('entidad', 'medio_entrega')->sole();
    expect($event->accion)->toBe('configurar_medio')
        ->and((int) $event->actor_id)->toBe($administrator->id)
        ->and(json_decode($event->valor_anterior, true))->toBe(['requiere_evidencia' => true])
        ->and(json_decode($event->valor_nuevo, true))->toBe(['requiere_evidencia' => false]);

    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => '0',
        'requiere_evidencia' => '0',
    ])->assertRedirect();
    expect(DB::table('tramite_config_events')->count())->toBe(1);
});

test('issued PDF automatically embeds the signer profile signature', function () {
    [$officeAdmin, $tramite] = createApprovedTramiteForNumberingTest();
    $firmante = $tramite->borradores()->latest('id')->firstOrFail()->firmante;
    $signatureBytes = Storage::disk('local')->get('firmas-perfil/'.$firmante->id.'.jpg');

    $this->actingAs($officeAdmin)
        ->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])
        ->assertRedirect(route('tramites.show', $tramite));

    $document = TramiteDocumentoFinal::query()->where('tramite_id', $tramite->id)->firstOrFail();
    $pdf = Storage::disk('local')->get($document->ruta);

    expect($document->contenido_snapshot['firma_perfil_sha256'])->toBe(hash('sha256', $signatureBytes))
        ->and($pdf)->toContain('/Subtype /Image');
});

test('administrator can assign a prepared case after reception duties move to administration', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $tramite = Tramite::factory()->create(['estado' => 'pendiente_asignacion']);
    TramiteBorrador::factory()->create(['tramite_id' => $tramite->id]);

    $this->actingAs($administrator)->get(route('tramites.asignaciones.index'))->assertOk();
    $this->post(route('tramites.asignaciones.store', $tramite), [
        'destino' => 'docente',
        'revisor_id' => $teacher->id,
        'motivo' => 'Revisión académica del expediente',
    ])->assertRedirect(route('tramites.show', $tramite));

    expect($tramite->fresh()->estado)->toBe('asignado')
        ->and($tramite->asignacionActual?->revisor_id)->toBe($teacher->id)
        ->and($tramite->asignacionActual?->asignado_por)->toBe($administrator->id);
});

test('office administrator assignment inbox includes prepared drafts and prepares them once', function () {
    $officeAdmin = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $prepared = Tramite::factory()->create(['estado' => 'borrador_preparado']);
    TramiteBorrador::factory()->create(['tramite_id' => $prepared->id]);
    $pending = Tramite::factory()->create(['estado' => 'pendiente_asignacion']);
    $assignedToTeacher = Tramite::factory()->create(['estado' => 'asignado']);
    $assignedToOffice = Tramite::factory()->create(['estado' => 'asignado']);
    Tramite::factory()->create(['estado' => 'aprobado']);
    TramiteAsignacion::factory()->create(['tramite_id' => $assignedToTeacher->id]);
    TramiteAsignacion::factory()->create([
        'tramite_id' => $assignedToTeacher->id,
        'activa' => false,
        'estado' => 'reasignada',
        'created_at' => now()->subDay(),
    ]);
    TramiteAsignacion::factory()->create([
        'tramite_id' => $assignedToOffice->id,
        'destino' => 'oficina',
        'rol_revisor' => 'administrador',
        'revisor_id' => User::factory()->state(['rol' => 'administrador']),
    ]);

    $this->actingAs($student)->get(route('tramites.asignaciones.index'))->assertForbidden();
    $this->post(route('tramites.asignacion.prepare', $prepared))->assertForbidden();

    $this->actingAs($officeAdmin)->get(route('tramites.asignaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tramites/asignaciones/index')
            ->has('tramites.data', 4)
            ->where('resumen.pendientes', 1)
            ->where('resumen.asignados_hoy', 2)
            ->where('resumen.docentes', 1)
            ->where('resumen.oficina', 1)
            ->where('resumen.reasignados', 1)
            ->where('resumen.sin_revisor', 1));
    $this->get(route('tramites.asignaciones.index', ['estado' => 'borrador_preparado']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tramites.data', 1)
            ->where('tramites.data.0.id', $prepared->id)
            ->where('tramites.data.0.asignacion', null)
            ->where('filters.estado', 'borrador_preparado')
            ->etc());

    $this->post(route('tramites.asignacion.prepare', $prepared))
        ->assertRedirect(route('tramites.show', $prepared));
    expect($prepared->fresh()->estado)->toBe('pendiente_asignacion')
        ->and($prepared->eventos()->where('accion', 'preparar_asignacion')->count())->toBe(1);
    $this->post(route('tramites.asignacion.prepare', $prepared))->assertRedirect();
    expect($prepared->eventos()->where('accion', 'preparar_asignacion')->count())->toBe(1);
    $this->post(route('tramites.asignacion.prepare', $pending))->assertStatus(409);
    $this->get(route('tramites.asignaciones.index', ['estado' => 'borrador_preparado']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tramites.data', 0)
            ->where('resumen.pendientes', 2)
            ->where('resumen.sin_revisor', 2)
            ->etc());
});
