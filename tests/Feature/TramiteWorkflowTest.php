<?php

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
use App\Models\TramiteMedioEntrega;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramitePlantilla;
use App\Models\TramiteRondaRevision;
use App\Models\TramiteSerieDocumental;
use App\Models\User;
use App\Services\PdfDocumentGenerator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

test('assistant intake can be digitized, searched, audited, and downloaded privately through Turso', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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
            ->assertOk();

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
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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

test('official document emission reconciles a lost commit response without deleting the PDF or reusing a number', function () {
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();

    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 2) {
            throw new RuntimeException('Simulated lost response after the final commit.');
        }
    });

    $this->actingAs($assistant)
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
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 1) {
            throw new RuntimeException('Simulated lost response after reserving the number.');
        }
    });

    $this->actingAs($assistant)
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
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();
    $interrupted = false;
    DB::listen(function (QueryExecuted $query) use (&$interrupted): void {
        if (! $interrupted
            && str_starts_with(strtolower(trim($query->sql)), 'update')
            && str_contains($query->sql, 'tramite_documentos_finales')) {
            $interrupted = true;

            throw new RuntimeException('Simulated interruption before the final commit.');
        }
    });

    $this->actingAs($assistant)
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
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits === 1) {
            throw new RuntimeException('Simulated lost reservation response.');
        }
    });

    $this->actingAs($assistant)
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
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();
    $commits = 0;
    Event::listen(TransactionCommitted::class, function () use (&$commits): void {
        $commits++;

        if ($commits <= 2) {
            throw new RuntimeException('Simulated lost commit response.');
        }
    });

    $this->actingAs($assistant)
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
    $assistant = User::factory()->create(['rol' => 'asistente', 'activo' => true]);
    $student = User::factory()->create(['rol' => 'estudiante', 'activo' => true]);
    $otherStudent = User::factory()->create(['rol' => 'estudiante', 'activo' => true]);
    $reviewer = User::factory()->create(['rol' => 'docente', 'activo' => true]);
    $tramite = Tramite::factory()->create([
        'estado' => 'listo_entrega',
        'propietario_id' => $student->id,
        'recibido_por' => $assistant->id,
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
        'entregado_por' => $assistant->id,
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
    [$assistant, $tramite] = createApprovedTramiteForNumberingTest();
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

    $this->actingAs($assistant)->post(route('tramites.documento-final.emit', $tramite), ['confirmar' => true])->assertRedirect();
    $this->post(route('tramites.entrega.prepare', $tramite))->assertRedirect();
    $this->post(route('tramites.entrega.registrar', $tramite), $payload)->assertRedirect();
    $primeraEntrega = $tramite->entregaActual()->firstOrFail();
    $this->actingAs($administrator)->get(route('tramites.entrega.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('puede_anular', true)->where('puede_reabrir', false)->etc());

    $motivoAnulacion = 'La entrega fue registrada con datos incorrectos.';
    $rutaAnular = route('tramites.entrega.anular', [$tramite, $primeraEntrega]);
    $this->actingAs($assistant)->post($rutaAnular, ['motivo' => $motivoAnulacion])->assertForbidden();
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

    $this->actingAs($assistant)->post(route('tramites.entrega.registrar', $tramite), $payload)->assertRedirect();
    $segundaEntrega = $tramite->entregaActual()->firstOrFail();
    expect($segundaEntrega->id)->not->toBe($primeraEntrega->id);
    $this->post(route('tramites.entrega.confirmar', $tramite), ['confirmar' => true])->assertRedirect();
    $this->actingAs($administrator)->post(route('tramites.entrega.anular', [$tramite, $segundaEntrega]), ['motivo' => $motivoAnulacion])->assertStatus(409);
    $this->actingAs($assistant);
    $this->post(route('tramites.entrega.cerrar', $tramite), ['resumen' => 'Primer cierre administrativo después de la entrega corregida.'])->assertRedirect();
    $primerCierre = $tramite->cierre()->firstOrFail();
    $primerInforme = $primerCierre->informe()->firstOrFail();
    $rutaInforme = route('tramites.informes-cierre.descargar', [$tramite, $primerInforme]);
    $this->get($rutaInforme)->assertOk();
    $this->actingAs($administrator)->get(route('tramites.entrega.show', $tramite))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('puede_reabrir', true)->where('puede_anular', false)->etc());

    $motivoReapertura = 'Se requiere corregir el informe administrativo de cierre.';
    $rutaReabrir = route('tramites.entrega.reabrir', $tramite);
    $this->actingAs($assistant)->post($rutaReabrir, ['motivo' => $motivoReapertura])->assertForbidden();
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

    $this->actingAs($assistant)->post(route('tramites.entrega.cerrar', $tramite), ['resumen' => 'Segundo cierre tras la reapertura administrativa del expediente.'])->assertRedirect();
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
function createApprovedTramiteForNumberingTest(): array
{
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);

    $assistant = User::factory()->create(['rol' => 'asistente', 'activo' => true]);
    $administrator = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $reviewer = User::factory()->create(['rol' => 'docente', 'activo' => true]);
    $tramite = Tramite::factory()->create(['estado' => 'aprobado', 'recibido_por' => $assistant->id]);
    $plantilla = TramitePlantilla::factory()->create(['tipo_documento_salida' => 'informe', 'modalidad' => 'unica']);
    $borrador = TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'plantilla_id' => $plantilla->id,
        'remitente_id' => $administrator->id,
        'firmante_id' => $reviewer->id,
        'creado_por' => $assistant->id,
    ]);
    $asignacion = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $assistant->id,
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

    return [$assistant, $tramite];
}

test('signature delivery and closure require confirmation and produce an auditable private report', function () {
    if (! configureDisposableTursoConnection()) {
        $this->markTestSkipped('Set separate TURSO_TEST_* credentials and confirm the database is disposable.');
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

test('intake receipt preserves the initial state and excludes private fields', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $tramite = Tramite::factory()->create([
        'propietario_id' => $student->id,
        'recibido_por' => $assistant->id,
        'persona_nombre' => 'Interesada de prueba',
        'persona_identificador' => 'DNI-PRIVADO-12345678',
        'descripcion' => 'Nota privada del expediente',
        'asunto' => 'Solicitud de constancia',
        'fecha_recepcion' => '2026-09-28',
        'estado' => 'cerrado',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $assistant->id,
        'accion' => 'recepcion',
        'descripcion' => 'Dato interno de recepción',
        'estado_nuevo' => 'recibido_oficina',
        'metadatos' => ['ruta_privada' => 'secreto-de-prueba'],
    ]);

    $this->get(route('tramites.receipt', $tramite))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('tramites.receipt', $tramite))->assertForbidden();

    $this->actingAs($assistant);
    $response = $this->get(route('tramites.receipt', $tramite));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/comprobante')
        ->where('comprobante.codigo', $tramite->codigo)
        ->where('comprobante.fecha_recepcion', '2026-09-28')
        ->where('comprobante.tipo_tramite', 'Formulario Único de Trámite')
        ->where('comprobante.estado_inicial', 'Recibido en oficina')
        ->where('comprobante.interesado', 'Interesada de prueba')
        ->where('comprobante.asunto', 'Solicitud de constancia')
        ->missing('comprobante.persona_identificador')
        ->missing('comprobante.descripcion')
        ->missing('comprobante.ruta_privada'));
    expect($response->getContent())->not->toContain('DNI-PRIVADO-12345678', 'Nota privada del expediente', 'secreto-de-prueba');

    $this->actingAs($administrator)->get(route('tramites.receipt', $tramite))->assertOk();
    $assistant->forceFill(['activo' => false])->save();
    $this->actingAs($assistant)->get(route('tramites.receipt', $tramite))->assertForbidden();
});

test('global search restricts results by role and assignment', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
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
        'asignado_por' => $assistant->id,
        'destino' => 'docente',
        'activa' => true,
    ]);

    $this->get(route('search.index', ['q' => 'SEARCH']))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('search.index', ['q' => 'SEARCH']))->assertForbidden();
    $this->actingAs($otherReviewer)->get(route('search.index', ['q' => 'SEARCH']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.expedientes', 0)->has('results.personas', 0));
    $this->actingAs($reviewer)->get(route('search.index', ['q' => 'SEARCH']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.codigo', 'TRM-SEARCH-000001')
        ->has('results.personas', 0)
        ->has('results.documentos', 0));
    $this->get(route('search.index', ['q' => '87654321']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('results.expedientes', 0)
        ->has('results.personas', 0));

    $assistantResponse = $this->actingAs($assistant)->get(route('search.index', ['q' => 'DNI-PRIVADO-555']));
    $assistantResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.expedientes.0.codigo', 'TRM-SEARCH-000001')
        ->missing('results.expedientes.0.persona_identificador'));

    $this->actingAs($assistant)->get(route('search.index', ['q' => 'Docente Reservado']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.personas', 0));
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
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.personas', 0));
    $this->actingAs($administrator)->get(route('search.index', ['q' => 'Docente Reservado']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('results.personas.0.name', 'Docente Reservado'));
    $this->get(route('search.index', ['q' => 'DOC-SEARCH-999']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('administrator', true)
        ->where('results.personas.0.id', $reviewer->id)
        ->missing('results.personas.0.codigo_docente'));
    $assistant->forceFill(['activo' => false])->save();
    $this->actingAs($assistant)->get(route('search.index', ['q' => 'SEARCH']))->assertForbidden();
});

test('global search opens only a reviewers completed assignment as read only', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $reviewer = User::factory()->create(['rol' => 'docente']);
    $otherReviewer = User::factory()->create(['rol' => 'docente']);
    $tramite = Tramite::factory()->create(['codigo' => 'TRM-HISTORICO-001', 'estado' => 'aprobado']);
    $reviewedDraft = TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'version' => 1,
        'es_actual' => false,
        'asunto' => 'Borrador revisado en la ronda finalizada',
    ]);
    TramiteBorrador::factory()->create([
        'tramite_id' => $tramite->id,
        'version' => 2,
        'es_actual' => true,
        'asunto' => 'Borrador posterior ajeno a la ronda',
    ]);
    $assignment = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $assistant->id,
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
    $assistant = User::factory()->create(['rol' => 'asistente']);
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

    $this->actingAs($assistant)->get(route('search.index', ['q' => '  X%  ']))
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
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $reviewer = User::factory()->create(['rol' => 'docente', 'name' => 'Revisor Confidencial']);
    $tramite = Tramite::factory()->create([
        'propietario_id' => $student->id,
        'recibido_por' => $assistant->id,
        'estado' => 'observado',
    ]);
    $asignacion = TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => $reviewer->id,
        'asignado_por' => $assistant->id,
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
        'usuario_id' => $assistant->id,
        'accion' => 'recepcion',
        'descripcion' => 'Nota privada de recepción.',
        'estado_nuevo' => 'recibido_oficina',
    ]);
    TramiteEvento::query()->create([
        'tramite_id' => $tramite->id,
        'usuario_id' => $assistant->id,
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
        'usuario_id' => $assistant->id,
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
    $this->actingAs($assistant)->get(route('estudiante.tramites.show', $tramite))->assertForbidden();
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

test('assistant adds independent files and replaces a current file while keeping the private history', function () {
    Storage::fake('local');
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/local')]);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $teacher = User::factory()->create(['rol' => 'docente']);
    $otherTeacher = User::factory()->create(['rol' => 'docente']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $tramite = Tramite::factory()->create(['estado' => 'recibido_oficina', 'propietario_id' => $student->id]);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($assistant)->post(route('tramites.documentos.store', $tramite), [
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
        'asignado_por' => $assistant->id,
        'activa' => false,
        'estado' => 'finalizada',
    ]);
    $this->actingAs($teacher)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertDownload('sustento-v2.pdf');
    $this->actingAs($otherTeacher)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertForbidden();
    $this->actingAs($administrator)->get(route('tramites.documentos.descargar', [$tramite, $replacement->id]))->assertOk();
    $this->post(route('tramites.documentos.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('admin.pdf', $pdf),
    ])->assertForbidden();

    $tramite->update(['estado' => 'borrador_preparado']);
    $this->actingAs($assistant)->post(route('tramites.documentos.store', $tramite), [
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
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $tramite = Tramite::factory()->create(['estado' => 'observado', 'propietario_id' => $student->id]);
    $pdf = "%PDF-1.4\n% Subsanación artificial\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($student)->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('indebido.pdf', $pdf),
        'observacion' => 'Intento de estudiante.',
    ])->assertForbidden();
    $this->actingAs($assistant)->post(route('tramites.subsanaciones.store', $tramite), [
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
    $this->actingAs($assistant)->post(route('tramites.subsanaciones.store', $tramite), [
        'documento' => UploadedFile::fake()->createWithContent('tardio.pdf', $pdf),
        'observacion' => 'Intento tras el cierre.',
    ])->assertStatus(409);
    expect(DB::table('tramite_documentos')->where('tramite_id', $tramite->id)->count())->toBe(1);
});

test('reception file validation rejects disguised names and mismatched content before storing', function () {
    Storage::fake('local');
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $tramite = Tramite::factory()->create(['estado' => 'digitalizado']);
    $pdf = "%PDF-1.4\n% Archivo artificial\n1 0 obj <<>> endobj\n%%EOF";

    $this->actingAs($assistant)->post(route('tramites.documentos.store', $tramite), [
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

    $student = User::factory()->create(['rol' => 'estudiante']);
    $this->post(route('tramites.store'), [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'FUT',
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

test('assistant registers multiple independent documents in one physical reception', function () {
    Storage::fake('local');
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'FUT',
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
    $this->actingAs($assistant)->post(route('tramites.store'), [
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

test('physical reception requires confirmation and preserves arrival and provenance details', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $programa = ProgramaEstudio::factory()->create();
    $otroPrograma = ProgramaEstudio::factory()->create();
    $perfil = PerfilEstudiante::factory()->create(['user_id' => $student->id, 'programa_estudio_id' => $programa->id]);
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'FUT',
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
        'numero_expediente_externo' => 'EXT-2026/42 #B',
        'area_procedencia' => 'Coordinación Académica',
        'persona_entrega_documento' => 'Apoderada de prueba',
        'observacion_recepcion' => 'Se cotejó el documento físico.',
        'folios' => 8,
    ];

    $this->actingAs($assistant)->post(route('tramites.store'), $payload)->assertSessionHasErrors('confirmar_recepcion');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '0'])->assertSessionHasErrors('confirmar_recepcion');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'numero_expediente_externo' => 'EXT@42'])
        ->assertSessionHasErrors('numero_expediente_externo');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'fecha_llegada_oficina' => '2026-02-31T16:45'])
        ->assertSessionHasErrors('fecha_llegada_oficina');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'folios' => 5001])
        ->assertSessionHasErrors('folios');
    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1', 'descripcion' => 'ab'])
        ->assertSessionHasErrors('descripcion');
    expect(Tramite::query()->where('asunto', $payload['asunto'])->count())->toBe(0);

    $this->post(route('tramites.store'), [...$payload, 'confirmar_recepcion' => '1'])->assertRedirect();
    $tramite = Tramite::query()->where('asunto', $payload['asunto'])->sole();
    expect($tramite->fecha_recepcion->toDateString())->toBe('2026-09-28')
        ->and($tramite->programa_estudio_id)->toBe($programa->id)
        ->and($tramite->fecha_llegada_oficina->format('Y-m-d H:i'))->toBe('2026-09-28 16:45')
        ->and($tramite->fecha_presentacion_original->toDateString())->toBe('2026-09-27')
        ->and($tramite->numero_expediente_externo)->toBe('EXT-2026/42 #B')
        ->and($tramite->area_procedencia)->toBe('Coordinación Académica')
        ->and($tramite->persona_entrega_documento)->toBe('Apoderada de prueba')
        ->and($tramite->observacion_recepcion)->toBe('Se cotejó el documento físico.')
        ->and($tramite->prioridad)->toBe('alta');
    $perfil->update(['programa_estudio_id' => $otroPrograma->id]);
    expect($tramite->fresh()->programa_estudio_id)->toBe($programa->id);
    $this->get(route('tramites.show', $tramite))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramite.fecha_llegada_oficina', '2026-09-28 16:45')
            ->where('tramite.programa', $programa->nombre)
            ->where('tramite.numero_expediente_externo', 'EXT-2026/42 #B')
            ->where('tramite.observacion_recepcion', 'Se cotejó el documento físico.')
            ->etc());
    $this->get(route('tramites.index', ['q' => 'EXT-2026/42']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tramites.data.0.id', $tramite->id)->etc());
    $this->get(route('search.index', ['q' => 'EXT-2026/42']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('results.expedientes.0.id', $tramite->id)->etc());

    $administrativePayload = [
        ...$payload,
        'clasificacion' => 'administrativo',
        'tipo_documento' => 'COMUNICACION_ADMINISTRATIVA',
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
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $pdf = "%PDF-1.4\n% Archivo artificial de prueba\n1 0 obj <<>> endobj\n%%EOF";
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'FUT',
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

    $this->actingAs($assistant)->post(route('tramites.store'), [
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

test('assistant edits reception data before assignment with validation and audit', function () {
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $student = User::factory()->create(['rol' => 'estudiante']);
    $otherStudent = User::factory()->create(['rol' => 'estudiante']);
    $tramite = Tramite::factory()->create([
        'codigo' => 'TRM-EDITAR-000001',
        'estado' => 'digitalizado',
        'propietario_id' => $student->id,
        'recibido_por' => $assistant->id,
    ]);
    $payload = [
        'clasificacion' => 'estudiantil',
        'tipo_documento' => 'FUT',
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
        'numero_expediente_externo' => 'EXP-2026/29 #A',
        'area_procedencia' => 'Dirección Académica',
        'persona_entrega_documento' => 'Representante autorizado',
        'observacion_recepcion' => 'Documento original visto en oficina.',
        'folios' => 4,
    ];

    $this->get(route('tramites.edit', $tramite))->assertRedirect(route('login'));
    $this->actingAs($student)->get(route('tramites.edit', $tramite))->assertForbidden();
    $this->actingAs($administrator)->put(route('tramites.update', $tramite), $payload)->assertForbidden();
    $this->actingAs($assistant)->get(route('tramites.edit', $tramite))
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
        ->and($actualizado->recibido_por)->toBe($assistant->id)
        ->and($actualizado->asunto)->toBe('Asunto corregido en recepción')
        ->and($actualizado->propietario_id)->toBe($otherStudent->id)
        ->and($actualizado->folios)->toBe(4)
        ->and($actualizado->fecha_recepcion->toDateString())->toBe('2026-09-29')
        ->and($actualizado->fecha_llegada_oficina->format('Y-m-d H:i'))->toBe('2026-09-29 11:30')
        ->and($actualizado->fecha_presentacion_original->toDateString())->toBe('2026-09-28')
        ->and($actualizado->numero_expediente_externo)->toBe('EXP-2026/29 #A')
        ->and($actualizado->area_procedencia)->toBe('Dirección Académica')
        ->and($actualizado->persona_entrega_documento)->toBe('Representante autorizado')
        ->and($actualizado->observacion_recepcion)->toBe('Documento original visto en oficina.');
    $evento = $tramite->eventos()->where('accion', 'edicion_recepcion')->sole();
    expect($evento->usuario_id)->toBe($assistant->id)
        ->and($evento->metadatos['campos'])->toContain('asunto', 'propietario_id')
        ->and(json_encode($evento->metadatos))->not->toContain('87654321');

    TramiteAsignacion::factory()->create([
        'tramite_id' => $tramite->id,
        'revisor_id' => User::factory()->create(['rol' => 'docente'])->id,
        'asignado_por' => $assistant->id,
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

test('only administrators see the issued delivery register and can configure delivery media', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
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
    $this->actingAs($assistant)->get(route('admin.deliveries.index'))->assertForbidden();
    $this->actingAs($student)->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => false,
        'requiere_evidencia' => false,
    ])->assertForbidden();

    $this->actingAs($administrator)->get(route('admin.deliveries.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tramites/entregas-admin')
        ->where('tramites.total', 1)
        ->where('tramites.data.0.codigo', 'TRM-ENTREGA-EMITIDO')
        ->where('medios.0.activo', true)
        ->where('medios.0.requiere_evidencia', true)
        ->missing('tramites.data.1'));

    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => 'incorrecto',
        'requiere_evidencia' => '0',
    ])->assertSessionHasErrors('activo');
    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => '0',
        'requiere_evidencia' => '0',
    ])->assertRedirect(route('admin.deliveries.index'));

    expect($medio->fresh()->activo)->toBeFalse()
        ->and($medio->fresh()->requiere_evidencia)->toBeFalse();
    $event = DB::table('tramite_config_events')->where('entidad', 'medio_entrega')->sole();
    expect($event->accion)->toBe('configurar_medio')
        ->and((int) $event->actor_id)->toBe($administrator->id)
        ->and(json_decode($event->valor_anterior, true))->toBe(['activo' => true, 'requiere_evidencia' => true])
        ->and(json_decode($event->valor_nuevo, true))->toBe(['activo' => false, 'requiere_evidencia' => false]);

    $this->patch(route('admin.deliveries.media.update', $medio), [
        'activo' => '0',
        'requiere_evidencia' => '0',
    ])->assertRedirect();
    expect(DB::table('tramite_config_events')->count())->toBe(1);
    $this->get(route('admin.audit.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('events.data.0.modulo', 'entregas')
        ->where('events.data.0.accion', 'configurar_medio')
        ->where('events.data.0.entidad_id', $medio->id)
        ->missing('events.data.0.valor_anterior')
        ->missing('events.data.0.valor_nuevo')
        ->etc());
});

test('only administrators configure signing on active templates without changing issued documents', function () {
    $administrator = User::factory()->create(['rol' => 'administrador']);
    $assistant = User::factory()->create(['rol' => 'asistente']);
    $active = TramitePlantilla::factory()->create([
        'requiere_firma_fisica' => false,
        'permite_no_firma' => true,
    ]);
    $inactive = TramitePlantilla::factory()->create(['activa' => false]);
    $issued = TramiteDocumentoFinal::factory()->create([
        'estado' => 'emitido',
        'contenido_snapshot' => ['requiere_firma_fisica' => false, 'permite_no_firma' => true],
    ]);

    $this->actingAs($assistant)->patch(route('admin.deliveries.templates.update', $active), [
        'requiere_firma_fisica' => '1',
        'permite_no_firma' => '0',
    ])->assertForbidden();
    $this->actingAs($administrator)->get(route('admin.deliveries.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('plantillas', fn ($plantillas): bool => collect($plantillas)->contains('id', $active->id)
            && ! collect($plantillas)->contains('id', $inactive->id))
        ->etc());

    $this->patch(route('admin.deliveries.templates.update', $inactive), [
        'requiere_firma_fisica' => '1',
        'permite_no_firma' => '0',
    ])->assertNotFound();
    $this->patch(route('admin.deliveries.templates.update', $active), [
        'requiere_firma_fisica' => '1',
    ])->assertSessionHasErrors('permite_no_firma');
    $this->patch(route('admin.deliveries.templates.update', $active), [
        'requiere_firma_fisica' => '1',
        'permite_no_firma' => '0',
    ])->assertRedirect(route('admin.deliveries.index'));

    expect($active->fresh()->requiere_firma_fisica)->toBeTrue()
        ->and($active->fresh()->permite_no_firma)->toBeFalse()
        ->and($inactive->fresh()->requiere_firma_fisica)->toBeFalse()
        ->and($issued->fresh()->contenido_snapshot)->toBe(['requiere_firma_fisica' => false, 'permite_no_firma' => true]);
    $event = DB::table('tramite_config_events')->where('entidad', 'plantilla')->sole();
    expect($event->accion)->toBe('configurar_firma_plantilla')
        ->and((int) $event->actor_id)->toBe($administrator->id)
        ->and((int) $event->entidad_id)->toBe($active->id);

    $this->patch(route('admin.deliveries.templates.update', $active), [
        'requiere_firma_fisica' => '1',
        'permite_no_firma' => '0',
    ])->assertRedirect();
    expect(DB::table('tramite_config_events')->count())->toBe(1);
});
