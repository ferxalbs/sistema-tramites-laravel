<?php

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramitePlantilla;
use App\Models\TramiteRondaRevision;
use App\Models\TramiteSerieDocumental;
use App\Models\User;
use App\Services\PdfDocumentGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

test('assistant intake can be digitized, searched, audited, and downloaded privately through Turso', function () {
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $assistantId = null;
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
        $assistant = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'asistente',
            'activo' => true,
        ]);
        $assistantId = $assistant->id;
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
        $this->actingAs($assistant);

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
            'fecha_recepcion' => now()->toDateString(),
            'folios' => 2,
        ];

        $this->from(route('tramites.create'))
            ->post(route('tramites.store'), [...$payload, 'propietario_id' => null])
            ->assertSessionHasErrors('propietario_id');

        $this->from(route('tramites.create'))
            ->post(route('tramites.store'), [
                ...$payload,
                'documento' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('documento');

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
        $this->actingAs($assistant);

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
        ]);
        $senderId = $remitente->id;
        $firmante = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'docente',
            'activo' => true,
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
                ->missing('borrador.adjuntos.0.ruta'));
        $this->actingAs($secondReviewer)->get(route('asignaciones.docente.show', $tramiteId))->assertForbidden();

        $this->actingAs($assistant);
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

        $this->actingAs($assistant);
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

        $this->actingAs($assistant);
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

        $this->actingAs($assistant)->post(route('tramites.asignaciones.store', $tramiteId), [
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

        $this->actingAs($assistant)
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
            ->assertForbidden();

        $this->actingAs($assistant);
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

        if ($assistantId !== null) {
            DB::table('users')->where('id', $assistantId)->delete();
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
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');

    $studentId = null;
    $assistantId = null;
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
        $assistant = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'asistente',
            'activo' => true,
        ]);
        $assistantId = $assistant->id;
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
            'recibido_por' => $assistantId,
        ]);
        $tramiteId = $tramite->id;
        $plantilla = TramitePlantilla::factory()->create();
        $plantillaId = $plantilla->id;
        TramiteBorrador::factory()->create([
            'tramite_id' => $tramiteId,
            'plantilla_id' => $plantillaId,
            'remitente_id' => $assistantId,
            'firmante_id' => $reviewerId,
            'creado_por' => $assistantId,
        ]);
        TramiteAsignacion::factory()->create([
            'tramite_id' => $tramiteId,
            'revisor_id' => $reviewerId,
            'rol_revisor' => 'docente',
            'asignado_por' => $assistantId,
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

        foreach ([$studentId, $assistantId, $reviewerId] as $userId) {
            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }

        DB::setDefaultConnection($defaultConnection);
    }
});

test('official document issuance consumes failed numbers and verifies the private PDF', function () {
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    $defaultConnection = DB::getDefaultConnection();
    $originalSeries = config('tramites.series_documentales', []);
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $assistantId = null;
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
        $assistant = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'asistente',
            'activo' => true,
        ]);
        $assistantId = $assistant->id;
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
            'recibido_por' => $assistantId,
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
            'creado_por' => $assistantId,
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
            'asignado_por' => $assistantId,
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
        $this->actingAs($assistant);

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

        $studentDownload = $this->actingAs($student)
            ->get(route('tramites.documento-final.descargar', [$tramiteId, $documento->id]))
            ->assertOk()
            ->assertDownload($documento->nombre_archivo)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($studentDownload->headers->get('Cache-Control'))
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
        $this->actingAs($assistant)
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

            foreach ([$assistantId, $studentId, $foreignStudentId, $senderId, $signerId, $reviewerId] as $userId) {
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

test('signature delivery and closure require confirmation and produce an auditable private report', function () {
    if (! config('database.connections.libsql.turso_url') || ! config('database.connections.libsql.auth_token')) {
        $this->markTestSkipped('Turso credentials are not configured.');
    }

    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('libsql');
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $assistantId = null;
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
        $assistant = User::factory()->create([
            'email' => Str::lower(Str::random(16)).'@example.test',
            'rol' => 'asistente',
            'activo' => true,
        ]);
        $assistantId = $assistant->id;
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
            'recibido_por' => $assistantId,
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
            'creado_por' => $assistantId,
            'version' => 2,
            'asunto' => 'Documento final ficticio para cierre',
        ]);
        $borradorId = $borrador->id;
        $assignment = TramiteAsignacion::factory()->create([
            'tramite_id' => $tramiteId,
            'revisor_id' => $reviewerId,
            'asignado_por' => $assistantId,
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
            'reservada_por' => $assistantId,
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
            'generado_por' => $assistantId,
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
        $this->actingAs($assistant)
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
        $this->actingAs($assistant)
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

        $this->actingAs($assistant);
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
        $this->actingAs($assistant)
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

            foreach ([$assistantId, $studentId, $foreignStudentId, $senderId, $signerId, $reviewerId] as $userId) {
                if ($userId !== null) {
                    DB::table('users')->where('id', $userId)->delete();
                }
            }
        } finally {
            DB::setDefaultConnection($defaultConnection);
        }
    }
});
