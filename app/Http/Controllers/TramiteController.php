<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTramiteRequest;
use App\Http\Requests\UpdateTramiteRequest;
use App\Http\Requests\UploadTramiteDocumentRequest;
use App\Models\ProgramaEstudio;
use App\Models\Tramite;
use App\Models\TramiteDocumento;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use App\Services\Tramites\TramiteClassificationCatalog;
use App\Services\Tramites\TramiteTypeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TramiteController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $classificationLabels = TramiteClassificationCatalog::labels();
        $typeLabels = TramiteTypeCatalog::labels();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', Rule::in(array_keys(config('tramites.estados')))],
        ]);

        $query = Tramite::query()
            ->withCount('documentos')
            ->orderByDesc('fecha_recepcion')
            ->orderByDesc('id');

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('codigo', 'like', $term)
                    ->orWhere('numero_expediente_externo', 'like', $term)
                    ->orWhere('persona_nombre', 'like', $term)
                    ->orWhere('persona_identificador', 'like', $term)
                    ->orWhere('asunto', 'like', $term);
            });
        }

        if (($filters['estado'] ?? '') !== '') {
            $query->where('estado', $filters['estado']);
        }

        $tramites = $query->paginate(15)->through(fn (Tramite $tramite): array => [
            'id' => $tramite->id,
            'codigo' => $tramite->codigo,
            'clasificacion' => $classificationLabels[$tramite->clasificacion] ?? $tramite->clasificacion,
            'tipo_documento' => $typeLabels[$tramite->tipo_documento] ?? $tramite->tipo_documento,
            'persona_nombre' => $tramite->persona_nombre,
            'asunto' => $tramite->asunto,
            'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
            'estado' => $tramite->estado,
            'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
            'documentos_count' => $tramite->documentos_count,
        ])->withQueryString();

        return Inertia::render('tramites/index', [
            'tramites' => $tramites,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'estado' => $filters['estado'] ?? '',
            ],
            'estados' => config('tramites.estados'),
            'resumen' => [
                'total' => Tramite::query()->count(),
                'recibidos' => Tramite::query()->where('estado', 'recibido_oficina')->count(),
                'digitalizados' => Tramite::query()->where('estado', '!=', 'recibido_oficina')->count(),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $seleccion = $request->session()->get('tramite_selection');
        $tiposParaIniciar = DB::table('tipos_tramite')->where('activo', true)
            ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
            ->orderBy('nombre')->get(['codigo', 'nombre', 'requiere_solicitante'])
            ->map(fn (object $tipo): array => [
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'requiere_solicitante' => (bool) $tipo->requiere_solicitante,
            ])->all();
        $codigoSeleccionado = is_array($seleccion) ? ($seleccion['tipo_documento'] ?? null) : null;

        if (! is_string($codigoSeleccionado)) {
            return Inertia::render('tramites/start', [
                'tipos' => $tiposParaIniciar,
            ]);
        }

        $tipo = DB::table('tipos_tramite')->where('codigo', $codigoSeleccionado)
            ->where('activo', true)
            ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
            ->first([
                'codigo', 'nombre', 'clasificacion_sugerida', 'requiere_solicitante',
                'tipo_documento_salida_sugerido', 'modalidad_documento_sugerida',
            ]);
        $requiereSolicitante = $tipo === null || (bool) $tipo->requiere_solicitante;
        $dni = is_array($seleccion) && is_string($seleccion['dni'] ?? null) ? $seleccion['dni'] : null;

        if ($tipo === null || ($requiereSolicitante && (! is_string($dni) || preg_match('/^[0-9]{8}$/', $dni) !== 1))) {
            $request->session()->forget('tramite_selection');

            return Inertia::render('tramites/start', [
                'tipos' => $tiposParaIniciar,
            ]);
        }

        $rolSolicitante = $requiereSolicitante
            ? User::query()->where('dni', $dni)->where('activo', true)->value('rol')
            : null;
        $clasificacion = $requiereSolicitante ? $tipo->clasificacion_sugerida : 'administrativo';
        if ($clasificacion === 'institucional') {
            $clasificacion = 'administrativo';
        }
        if ($clasificacion === null) {
            $clasificacion = match (true) {
                $tipo->codigo === 'CONSTANCIA_PRACTICA',
                $rolSolicitante === 'estudiante' => 'estudiantil',
                default => 'administrativo',
            };
        }

        return Inertia::render('tramites/create', [
            ...$this->receptionFormData(),
            'ahora' => now()->format('Y-m-d\TH:i'),
            'tramite' => null,
            'seleccion' => [
                'tipo_documento' => $tipo->codigo,
                'tipo_nombre' => $tipo->nombre,
                'clasificacion' => $clasificacion,
                'requiere_solicitante' => $requiereSolicitante,
                'dni' => $requiereSolicitante ? $dni : null,
                'formato_salida' => $tipo->tipo_documento_salida_sugerido,
                'modalidad_documento' => $tipo->modalidad_documento_sugerida,
            ],
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $codigo = $request->input('tipo_documento');
        $requiereSolicitante = TramiteTypeCatalog::requiresApplicant(is_string($codigo) ? $codigo : null);
        $seleccion = $request->validate([
            'tipo_documento' => ['required', 'string', Rule::exists('tipos_tramite', 'codigo')->where(fn ($query) => $query
                ->where('activo', true)->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes()))],
            'dni' => [Rule::requiredIf($requiereSolicitante), Rule::prohibitedIf(! $requiereSolicitante), 'nullable', 'regex:/^[0-9]{8}$/'],
        ], [], [
            'tipo_documento' => 'tipo de trámite',
            'dni' => 'DNI',
        ]);

        if (! $requiereSolicitante) {
            unset($seleccion['dni']);
        }

        return redirect()->route('tramites.create')->with('tramite_selection', $seleccion);
    }

    public function edit(Tramite $tramite): InertiaResponse
    {
        abort_unless($this->canEditReception($tramite), 409);

        return Inertia::render('tramites/create', [
            ...$this->receptionFormData(),
            'ahora' => now()->format('Y-m-d\TH:i'),
            'tramite' => $tramite->only([
                'id', 'codigo', 'clasificacion', 'tipo_documento', 'persona_nombre', 'persona_identificador',
                'formato_salida', 'modalidad_documento',
                'propietario_id', 'programa_estudio_id', 'destino_tipo', 'destino_nombre', 'destino_docente_id', 'asunto', 'descripcion', 'prioridad', 'folios',
                'numero_expediente_externo', 'area_procedencia', 'persona_entrega_documento', 'observacion_recepcion',
            ]) + [
                'fecha_llegada_oficina' => $tramite->fecha_llegada_oficina?->format('Y-m-d\TH:i')
                    ?? $tramite->fecha_recepcion->format('Y-m-d\T00:00'),
                'fecha_presentacion_original' => $tramite->fecha_presentacion_original?->toDateString(),
                ...$this->receptionLists($tramite),
            ],
        ]);
    }

    public function store(StoreTramiteRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $documentos = $datos['documentos'] ?? [];
        $listas = $this->extractReceptionLists($datos);
        unset($datos['documentos'], $datos['confirmar_recepcion']);
        $this->resolveStudentApplicant($datos);
        $this->resolveReceptionDestination($datos);
        $datos['fecha_recepcion'] = substr($datos['fecha_llegada_oficina'], 0, 10);
        $datos['fecha_llegada_oficina'] = str_replace('T', ' ', $datos['fecha_llegada_oficina']).':00';

        $codigo = $this->siguienteCodigo();
        $datosDocumentos = [];

        try {
            foreach ($documentos as $documento) {
                $datosDocumentos[] = [
                    ...$this->guardarDocumento($documento['archivo']),
                    'categoria' => $documento['categoria'],
                ];
            }

            $tramite = DB::transaction(function () use ($codigo, $datos, $listas, $datosDocumentos, $request): Tramite {
                $datos['programa_estudio_id'] = $this->programaParaRecepcion($datos);
                $estado = $datosDocumentos === [] ? 'recibido_oficina' : 'digitalizado';
                $tramite = Tramite::create([
                    ...$datos,
                    'codigo' => $codigo,
                    'estado' => $estado,
                    'recibido_por' => $request->user()->id,
                    'propietario_id' => $datos['propietario_id'] ?? null,
                ]);
                $this->saveReceptionLists($tramite, $listas, false);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => 'recepcion',
                    'descripcion' => 'El trámite fue recibido en oficina.',
                    'estado_nuevo' => 'recibido_oficina',
                    'metadatos' => isset($datos['propietario_id']) ? ['propietario_id' => $datos['propietario_id']] : null,
                ]);

                if ($datosDocumentos !== []) {
                    $versiones = [];
                    $documentosIds = [];

                    foreach ($datosDocumentos as $datosDocumento) {
                        $categoria = $datosDocumento['categoria'];
                        $versiones[$categoria] = ($versiones[$categoria] ?? 0) + 1;
                        $registroDocumento = $tramite->documentos()->create([
                            ...$datosDocumento,
                            'disco' => 'local',
                            'version' => $versiones[$categoria],
                            'cargado_por' => $request->user()->id,
                        ]);
                        $documentosIds[] = $registroDocumento->id;
                    }

                    TramiteEvento::create([
                        'tramite_id' => $tramite->id,
                        'usuario_id' => $request->user()->id,
                        'accion' => 'digitalizacion',
                        'descripcion' => 'Se cargó el documento digitalizado.',
                        'estado_anterior' => 'recibido_oficina',
                        'estado_nuevo' => 'digitalizado',
                        'metadatos' => ['documentos_ids' => $documentosIds, 'cantidad' => count($documentosIds)],
                    ]);
                }

                return $tramite;
            });
        } catch (Throwable $exception) {
            if ($datosDocumentos !== []) {
                try {
                    if (! Tramite::query()->where('codigo', $codigo)->exists()) {
                        Storage::disk('local')->delete(array_column($datosDocumentos, 'ruta'));
                    }
                } catch (Throwable) {
                    // Keep files if the database cannot confirm whether the transaction committed.
                }
            }

            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'El trámite fue registrado.');
    }

    public function update(UpdateTramiteRequest $request, Tramite $tramite): RedirectResponse
    {
        $datos = $request->validated();
        $listas = $this->extractReceptionLists($datos);
        $this->resolveStudentApplicant($datos);
        $this->resolveReceptionDestination($datos);
        $datos['fecha_recepcion'] = substr($datos['fecha_llegada_oficina'], 0, 10);
        $datos['fecha_llegada_oficina'] = str_replace('T', ' ', $datos['fecha_llegada_oficina']).':00';

        DB::transaction(function () use ($request, $tramite, $datos, $listas): void {
            $datos['programa_estudio_id'] = $this->programaParaRecepcion($datos);
            $actualizados = Tramite::query()
                ->whereKey($tramite->id)
                ->whereIn('estado', ['recibido_oficina', 'digitalizado'])
                ->whereDoesntHave('asignaciones', fn (Builder $asignaciones): Builder => $asignaciones->where('activa', true))
                ->update([...$datos, 'updated_at' => now()]);
            abort_unless($actualizados === 1, 409);
            $this->saveReceptionLists($tramite, $listas, true);

            TramiteEvento::create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $request->user()->id,
                'accion' => 'edicion_recepcion',
                'descripcion' => 'Se corrigieron los datos de recepción antes de la asignación.',
                'estado_anterior' => $tramite->estado,
                'estado_nuevo' => $tramite->estado,
                'metadatos' => ['campos' => array_keys($datos)],
            ]);
        });

        return redirect()->route('tramites.show', $tramite)->with('success', 'Datos de recepción actualizados.');
    }

    public function upload(UploadTramiteDocumentRequest $request, Tramite $tramite): RedirectResponse
    {
        abort_unless(in_array($tramite->estado, ['recibido_oficina', 'digitalizado'], true), 409);

        $categoria = $request->validated('categoria') ?? 'documento_original';
        $datosDocumento = $this->guardarDocumento($request->file('documento'));

        try {
            DB::transaction(function () use ($request, $tramite, $datosDocumento, $categoria): void {
                $updated = DB::table('tramites')
                    ->where('id', $tramite->id)
                    ->whereIn('estado', ['recibido_oficina', 'digitalizado'])
                    ->update([
                        'estado' => 'digitalizado',
                        'updated_at' => now(),
                    ]);

                abort_unless($updated === 1, 409);

                $version = (int) $tramite->documentos()->where('categoria', $categoria)->max('version') + 1;
                $documento = $tramite->documentos()->create([
                    ...$datosDocumento,
                    'categoria' => $categoria,
                    'disco' => 'local',
                    'version' => $version,
                    'cargado_por' => $request->user()->id,
                ]);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => $tramite->estado === 'recibido_oficina' ? 'digitalizacion' : 'documento_adicional',
                    'descripcion' => $tramite->estado === 'recibido_oficina'
                        ? 'Se cargó el documento digitalizado.'
                        : 'Se agregó un documento independiente sin reemplazar evidencia anterior.',
                    'estado_anterior' => $tramite->estado,
                    'estado_nuevo' => 'digitalizado',
                    'metadatos' => ['documento_id' => $documento->id, 'categoria' => $categoria, 'version' => $version],
                ]);
            });
        } catch (Throwable $exception) {
            $this->eliminarDocumentoNoPersistido($datosDocumento['ruta']);
            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'El documento recibido fue registrado.');
    }

    public function replace(UploadTramiteDocumentRequest $request, Tramite $tramite, TramiteDocumento $documento): RedirectResponse
    {
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless(in_array($tramite->estado, ['recibido_oficina', 'digitalizado'], true)
            && $documento->vigente
            && in_array($documento->categoria, ['documento_original', 'documento_escaneado'], true), 409);

        $datosDocumento = $this->guardarDocumento($request->file('documento'));

        try {
            DB::transaction(function () use ($request, $tramite, $documento, $datosDocumento): void {
                $updated = DB::table('tramites')->where('id', $tramite->id)
                    ->whereIn('estado', ['recibido_oficina', 'digitalizado'])
                    ->update(['estado' => 'digitalizado', 'updated_at' => now()]);
                abort_unless($updated === 1, 409);

                $replaced = DB::table('tramite_documentos')->where('id', $documento->id)
                    ->where('tramite_id', $tramite->id)->where('vigente', true)
                    ->update(['vigente' => false, 'updated_at' => now()]);
                abort_unless($replaced === 1, 409);

                $nuevaVersion = $tramite->documentos()->create([
                    ...$datosDocumento,
                    'categoria' => $documento->categoria,
                    'disco' => 'local',
                    'version' => $documento->version + 1,
                    'documento_anterior_id' => $documento->id,
                    'cargado_por' => $request->user()->id,
                ]);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => 'reemplazo_archivo',
                    'descripcion' => 'Se registró una nueva versión; la evidencia anterior se conservó.',
                    'estado_anterior' => $tramite->estado,
                    'estado_nuevo' => 'digitalizado',
                    'metadatos' => ['documento_id' => $nuevaVersion->id, 'documento_anterior_id' => $documento->id],
                ]);
            });
        } catch (Throwable $exception) {
            $this->eliminarDocumentoNoPersistido($datosDocumento['ruta']);
            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'La nueva versión fue registrada.');
    }

    public function correct(UploadTramiteDocumentRequest $request, Tramite $tramite): RedirectResponse
    {
        abort_unless($tramite->estado === 'observado', 409);
        $validated = $request->validate(['observacion' => ['required', 'string', 'min:3', 'max:2000']]);
        $datosDocumento = $this->guardarDocumento($request->file('documento'));

        try {
            DB::transaction(function () use ($request, $tramite, $validated, $datosDocumento): void {
                $updated = DB::table('tramites')->where('id', $tramite->id)->where('estado', 'observado')
                    ->update(['updated_at' => now()]);
                abort_unless($updated === 1, 409);

                $version = (int) $tramite->documentos()->where('categoria', 'documento_corregido')->max('version') + 1;
                $documento = $tramite->documentos()->create([
                    ...$datosDocumento,
                    'categoria' => 'documento_corregido',
                    'disco' => 'local',
                    'version' => $version,
                    'cargado_por' => $request->user()->id,
                ]);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => 'subsanacion_fisica',
                    'descripcion' => 'Subsanación física recibida: '.trim($validated['observacion']),
                    'estado_anterior' => 'observado',
                    'estado_nuevo' => 'observado',
                    'metadatos' => ['documento_id' => $documento->id, 'version' => $version],
                ]);
            });
        } catch (Throwable $exception) {
            $this->eliminarDocumentoNoPersistido($datosDocumento['ruta']);
            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'Subsanación física registrada; el expediente sigue observado.');
    }

    public function receipt(Tramite $tramite): InertiaResponse
    {
        $recepcion = $tramite->eventos()->where('accion', 'recepcion')->orderBy('id')->first(['created_at']);
        $typeLabels = TramiteTypeCatalog::labels();

        return Inertia::render('tramites/comprobante', [
            'institucion' => config('app.name'),
            'comprobante' => [
                'tramite_id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'fecha_documento' => $tramite->fecha_presentacion_original?->toDateString(),
                'fecha_registro' => ($recepcion?->created_at ?? $tramite->created_at)?->toIso8601String(),
                'tipo_tramite' => $typeLabels[$tramite->tipo_documento] ?? $tramite->tipo_documento,
                'estado_inicial' => config('tramites.estados.recibido_oficina'),
                'destino' => $tramite->destino_nombre ?: 'Pendiente',
                'interesado' => $tramite->persona_nombre ?? 'Documento institucional',
                'asunto' => $tramite->asunto,
            ],
        ]);
    }

    public function show(Request $request, Tramite $tramite): InertiaResponse
    {
        $classificationLabels = TramiteClassificationCatalog::labels();
        $typeLabels = TramiteTypeCatalog::labels();
        $tramite->load([
            'documentos' => fn ($query) => $query->orderBy('id'),
            'eventos' => fn ($query) => $query->with('usuario')->orderBy('id'),
            'recibidoPor',
            'propietario',
            'programa',
            'borradorActual.plantilla',
            'borradores' => fn ($query) => $query->with('plantilla')->orderByDesc('version'),
            'asignacionActual.revisor',
            'asignaciones' => fn ($query) => $query->with(['revisor', 'asignadoPor'])->orderByDesc('created_at'),
            'rondasRevision.revisor',
            'rondasRevision.observaciones.respuesta',
            'rondasRevision.versionBorrador.plantilla',
            'documentoFinalActual',
            'documentosFinales',
        ]);

        $borradorActual = $tramite->borradorActual;
        $asignacionActual = $tramite->asignacionActual;
        $documentoFinal = $tramite->documentoFinalActual;
        $formatoNombre = $tramite->formato_salida === null ? null : DB::table('tipos_documento_salida')
            ->where('codigo', $tramite->formato_salida)->value('nombre');
        $modalidadNombre = $tramite->modalidad_documento === null ? null : DB::table('modalidades_documento')
            ->where('tipo_documento_salida', $tramite->formato_salida)
            ->where('codigo', $tramite->modalidad_documento)->value('nombre');

        return Inertia::render('tramites/show', [
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'clasificacion' => $classificationLabels[$tramite->clasificacion] ?? $tramite->clasificacion,
                'tipo_documento' => $typeLabels[$tramite->tipo_documento] ?? $tramite->tipo_documento,
                'formato_salida' => $formatoNombre ?? $tramite->formato_salida,
                'modalidad_documento' => $modalidadNombre ?? $tramite->modalidad_documento,
                'persona_nombre' => $tramite->persona_nombre,
                'es_documento_institucional' => ! TramiteTypeCatalog::requiresApplicant($tramite->tipo_documento),
                'persona_identificador' => $tramite->persona_identificador,
                'destino_tipo' => config('tramites.destinos.'.$tramite->destino_tipo, $tramite->destino_tipo),
                'destino_nombre' => $tramite->destino_nombre,
                'asunto' => $tramite->asunto,
                'descripcion' => $tramite->descripcion,
                'prioridad' => config('tramites.prioridades.'.$tramite->prioridad, $tramite->prioridad),
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'fecha_llegada_oficina' => $tramite->fecha_llegada_oficina?->format('Y-m-d H:i'),
                'fecha_presentacion_original' => $tramite->fecha_presentacion_original?->toDateString(),
                'numero_expediente_externo' => $tramite->numero_expediente_externo,
                'area_procedencia' => $tramite->area_procedencia,
                'persona_entrega_documento' => $tramite->persona_entrega_documento,
                'observacion_recepcion' => $tramite->observacion_recepcion,
                'folios' => $tramite->folios,
                ...$this->receptionLists($tramite),
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'recibido_por' => $tramite->recibidoPor?->name,
                'propietario' => $tramite->propietario?->name,
                'programa' => $tramite->programa?->nombre,
                'puede_gestionar_asignacion' => $request->user()->rol === 'asistente',
                'puede_corregir_revision' => $request->user()->rol === 'asistente' && $tramite->estado === 'observado',
                'puede_gestionar_documentos_recepcion' => $request->user()->rol === 'asistente'
                    && in_array($tramite->estado, ['recibido_oficina', 'digitalizado'], true),
                'puede_registrar_subsanacion' => $request->user()->rol === 'asistente' && $tramite->estado === 'observado',
                'puede_editar_recepcion' => $request->user()->rol === 'asistente' && $this->canEditReception($tramite),
                'puede_emitir_documento_final' => $request->user()->rol === 'asistente'
                    && in_array($tramite->estado, ['aprobado', 'rechazado'], true)
                    && $documentoFinal === null,
                'puede_anular_documento_final' => $request->user()->rol === 'administrador'
                    && $tramite->estado === 'documento_final_generado'
                    && $documentoFinal?->estado === 'emitido',
                'url_gestion_entrega' => in_array($request->user()->rol, ['asistente', 'administrador'], true)
                    && $documentoFinal !== null
                    ? route('tramites.entrega.show', $tramite)
                    : null,
                'documento_final' => $documentoFinal === null ? null : [
                    'id' => $documentoFinal->id,
                    'numero' => $documentoFinal->numero_documento,
                    'tipo' => $documentoFinal->tipo_documento,
                    'estado' => $documentoFinal->estado,
                    'version' => $documentoFinal->version,
                    'sha256' => $documentoFinal->sha256,
                    'tamano_bytes' => $documentoFinal->tamano_bytes,
                    'numero_paginas' => $documentoFinal->numero_paginas,
                    'fecha_emision' => $documentoFinal->fecha_emision?->toIso8601String(),
                    'url_descarga' => $documentoFinal->estado === 'emitido'
                        ? route('tramites.documento-final.descargar', [$tramite->id, $documentoFinal->id])
                        : null,
                ],
                'documentos_finales_anteriores' => $tramite->documentosFinales
                    ->filter(fn (TramiteDocumentoFinal $documento): bool => ! $documento->activo)
                    ->map(fn (TramiteDocumentoFinal $documento): array => [
                        'id' => $documento->id,
                        'version' => $documento->version,
                        'numero' => $documento->numero_documento,
                        'estado' => $documento->estado,
                        'documento_anterior_id' => $documento->documento_anterior_id,
                        'fecha_anulacion' => $documento->fecha_anulacion?->toIso8601String(),
                        'url_descarga' => in_array($documento->estado, ['anulado', 'sustituido'], true)
                            ? route('tramites.documento-final.descargar', [$tramite->id, $documento->id])
                            : null,
                    ])->values()->all(),
                'asignacion_actual' => $asignacionActual === null ? null : [
                    'id' => $asignacionActual->id,
                    'destino' => $asignacionActual->destino,
                    'revisor_id' => $asignacionActual->revisor_id,
                    'revisor' => $asignacionActual->revisor->name,
                    'motivo' => $asignacionActual->motivo,
                    'instrucciones_revision' => $asignacionActual->instrucciones_revision,
                    'fecha_esperada' => $asignacionActual->fecha_esperada?->toDateString(),
                    'fecha_inicio_revision' => $asignacionActual->fecha_inicio_revision?->toIso8601String(),
                ],
                'asignaciones' => $tramite->asignaciones->map(fn ($asignacion): array => [
                    'id' => $asignacion->id,
                    'destino' => $asignacion->destino,
                    'revisor' => $asignacion->revisor?->name,
                    'asignado_por' => $asignacion->asignadoPor?->name,
                    'motivo' => $asignacion->motivo,
                    'estado' => $asignacion->estado,
                    'activa' => $asignacion->activa,
                    'motivo_finalizacion' => $asignacion->motivo_finalizacion,
                    'fecha_esperada' => $asignacion->fecha_esperada?->toDateString(),
                    'created_at' => $asignacion->created_at?->toIso8601String(),
                    'fecha_finalizacion' => $asignacion->fecha_finalizacion?->toIso8601String(),
                ])->all(),
                'borrador_actual' => $borradorActual === null ? null : [
                    'id' => $borradorActual->id,
                    'version' => $borradorActual->version,
                    'estado' => $borradorActual->estado,
                    'plantilla' => $borradorActual->plantilla->nombre,
                    'preparado_en' => $borradorActual->preparado_en?->toIso8601String(),
                ],
                'borradores' => $tramite->borradores->map(fn ($borrador): array => [
                    'id' => $borrador->id,
                    'version' => $borrador->version,
                    'estado' => $borrador->estado,
                    'plantilla' => $borrador->plantilla->nombre,
                    'actual' => $borrador->es_actual,
                    'created_at' => $borrador->created_at?->toIso8601String(),
                ])->all(),
                'revision_rondas' => $tramite->rondasRevision->map(fn (TramiteRondaRevision $ronda): array => [
                    'numero' => $ronda->numero_ronda,
                    'estado' => $ronda->estado,
                    'revisor' => $ronda->revisor?->name,
                    'version' => $ronda->versionBorrador?->version,
                    'resumen_observacion' => $ronda->resumen_observacion,
                    'resumen_correccion' => $ronda->resumen_correccion,
                    'conclusion' => $ronda->conclusion,
                    'comentario_publico' => $ronda->comentario_publico,
                    'comentario_interno' => $ronda->comentario_interno,
                    'observaciones' => $ronda->observaciones->map(fn ($observacion): array => [
                        'id' => $observacion->id,
                        'categoria' => $observacion->categoria,
                        'titulo' => $observacion->titulo,
                        'descripcion' => $observacion->descripcion,
                        'seccion' => $observacion->seccion,
                        'obligatoria' => $observacion->obligatoria,
                        'visible_para_interesado' => $observacion->visible_para_interesado,
                        'respuesta' => $observacion->respuesta?->respuesta,
                    ])->all(),
                ])->all(),
                'documentos' => $tramite->documentos->map(fn (TramiteDocumento $documento): array => [
                    'id' => $documento->id,
                    'categoria' => $documento->categoria,
                    'nombre_original' => $documento->nombre_original,
                    'mime_type' => $documento->mime_type,
                    'tamano_bytes' => $documento->tamano_bytes,
                    'version' => $documento->version,
                    'vigente' => $documento->vigente,
                    'documento_anterior_id' => $documento->documento_anterior_id,
                    'created_at' => $documento->created_at?->toIso8601String(),
                ])->all(),
                'eventos' => $tramite->eventos->map(fn (TramiteEvento $evento): array => [
                    'id' => $evento->id,
                    'accion' => $evento->accion,
                    'descripcion' => $evento->descripcion,
                    'estado_anterior' => $evento->estado_anterior,
                    'estado_nuevo' => $evento->estado_nuevo,
                    'usuario' => $evento->usuario?->name,
                    'created_at' => $evento->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    public function download(Tramite $tramite, TramiteDocumento $documento): BinaryFileResponse
    {
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        $user = request()->user();
        $allowed = in_array($user->rol, ['asistente', 'administrador'], true)
            || ($user->rol === 'estudiante' && (int) $tramite->propietario_id === (int) $user->id)
            || ($user->rol === 'docente' && $tramite->asignaciones()->where('revisor_id', $user->id)->exists());
        abort_unless($allowed, 403);
        abort_unless($documento->disco === 'local', 404);

        $disk = Storage::disk('local');
        $root = realpath((string) config('filesystems.disks.local.root'));
        $path = realpath($disk->path($documento->ruta));

        abort_unless(
            is_string($root)
                && is_string($path)
                && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                && is_file($path),
            404,
        );

        $hash = hash_file('sha256', $path);
        abort_unless(is_string($hash) && hash_equals($documento->sha256, $hash), 404);

        TramiteEvento::create([
            'tramite_id' => $tramite->id,
            'usuario_id' => $user->id,
            'accion' => 'descarga',
            'descripcion' => 'Se descargó un documento del trámite.',
            'metadatos' => ['documento_id' => $documento->id],
        ]);

        return response()->download($path, $documento->nombre_original, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function siguienteCodigo(): string
    {
        $anio = (int) now()->format('Y');
        $ahora = now()->toDateTimeString();

        DB::table('tramite_secuencias')->insertOrIgnore([
            'anio' => $anio,
            'ultimo_numero' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $secuencia = DB::selectOne(
            'UPDATE tramite_secuencias SET ultimo_numero = ultimo_numero + 1, updated_at = ? WHERE anio = ? RETURNING ultimo_numero',
            [$ahora, $anio],
        );

        if ($secuencia === null) {
            throw new RuntimeException('No se pudo reservar un código interno para el trámite.');
        }

        return sprintf('TRM-%d-%06d', $anio, $secuencia->ultimo_numero);
    }

    /**
     * @return array<string, mixed>
     */
    private function receptionFormData(): array
    {
        return [
            'catalogos' => [
                'clasificaciones' => TramiteClassificationCatalog::activeLabels(),
                'tipos_documento' => TramiteTypeCatalog::activeByClassification(),
                'formatos_salida' => DB::table('tipos_documento_salida')->where('activo', true)
                    ->orderBy('orden')->pluck('nombre', 'codigo')->all(),
                'modalidades_documento' => DB::table('modalidades_documento')->where('activo', true)
                    ->where('tipo_documento_salida', 'memorando')->orderBy('orden')->pluck('nombre', 'codigo')->all(),
                'formatos_sugeridos' => DB::table('tipos_tramite')->where('activo', true)
                    ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
                    ->whereNotNull('tipo_documento_salida_sugerido')
                    ->pluck('tipo_documento_salida_sugerido', 'codigo')->all(),
                'requisitos_tipo' => DB::table('tipos_tramite')->where('activo', true)
                    ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
                    ->get([
                        'codigo', 'requiere_solicitante', 'modalidad_documento_sugerida',
                        'requiere_personas_relacionadas', 'requiere_destinatarios_multiples', 'requiere_documento_original',
                    ])
                    ->mapWithKeys(fn (object $tipo): array => [$tipo->codigo => [
                        'requiere_solicitante' => (bool) $tipo->requiere_solicitante,
                        'modalidad_documento' => $tipo->modalidad_documento_sugerida,
                        'personas_relacionadas' => (bool) $tipo->requiere_personas_relacionadas,
                        'destinatarios_multiples' => (bool) $tipo->requiere_destinatarios_multiples,
                        'documento_original' => (bool) $tipo->requiere_documento_original,
                    ]])->all(),
                'tipos_relacion' => [
                    'interesado' => 'Interesado',
                    'solicitante' => 'Solicitante',
                    'personal_autorizado' => 'Personal autorizado',
                    'participante' => 'Participante',
                    'personal_externo' => 'Personal externo',
                    'persona_mencionada' => 'Persona mencionada',
                    'otro' => 'Otro',
                ],
                'destinos' => config('tramites.destinos'),
                'prioridades' => config('tramites.prioridades'),
            ],
            'estudiantes' => User::query()
                ->where('rol', 'estudiante')
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name', 'dni', 'email', 'celular', 'correo_alternativo'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'dni' => $user->dni,
                    'email' => $user->email,
                    'celular' => $user->celular,
                    'correo_alternativo' => $user->correo_alternativo,
                ])
                ->all(),
            'docentes' => User::query()
                ->where('rol', 'docente')
                ->where('activo', true)
                ->where('estado_cuenta', 'activo')
                ->orderBy('name')
                ->get(['id', 'name', 'dni'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'dni' => $user->dni])
                ->all(),
            'programas' => ProgramaEstudio::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (ProgramaEstudio $programa): array => ['id' => $programa->id, 'nombre' => $programa->nombre])
                ->all(),
            'cargos_institucionales' => DB::table('cargos_institucionales')->where('activo', true)
                ->orderBy('nombre')->get(['id', 'nombre'])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function extractReceptionLists(array &$datos): array
    {
        $listas = [];
        foreach (['personas_relacionadas', 'destinatarios', 'personas_mencionadas'] as $nombre) {
            $listas[$nombre] = $datos[$nombre] ?? [];
            unset($datos[$nombre]);
        }

        return $listas;
    }

    /** @param array<string, mixed> $datos */
    private function resolveReceptionDestination(array &$datos): void
    {
        if ($datos['destino_tipo'] !== 'docente') {
            $datos['destino_docente_id'] = null;
            if (! filled($datos['destino_nombre'] ?? null)) {
                $datos['destino_nombre'] = 'Pendiente de asignación';
            }

            return;
        }

        $nombre = User::query()
            ->whereKey($datos['destino_docente_id'])
            ->where('rol', 'docente')
            ->where('activo', true)
            ->where('estado_cuenta', 'activo')
            ->value('name');

        if ($nombre === null) {
            throw ValidationException::withMessages(['destino_docente_id' => 'Seleccione un docente activo.']);
        }

        $datos['destino_nombre'] = $nombre;
    }

    /** @param array<string, mixed> $datos */
    private function resolveStudentApplicant(array &$datos): void
    {
        if ($datos['clasificacion'] !== 'estudiantil') {
            $datos['propietario_id'] = null;

            return;
        }

        $estudiante = User::query()
            ->whereKey($datos['propietario_id'] ?? null)
            ->where('rol', 'estudiante')
            ->where('activo', true)
            ->first(['id', 'name', 'dni']);

        if ($estudiante === null || $estudiante->dni === null) {
            throw ValidationException::withMessages([
                'propietario_id' => 'Vincule una cuenta activa de estudiante con DNI registrado.',
            ]);
        }

        $datos['propietario_id'] = $estudiante->id;
        $datos['persona_nombre'] = $estudiante->name;
        $datos['persona_identificador'] = $estudiante->dni;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $listas
     */
    private function saveReceptionLists(Tramite $tramite, array $listas, bool $replace): void
    {
        foreach ([
            'personas_relacionadas' => 'personas_relacionadas_expediente',
            'destinatarios' => 'documento_destinatarios',
            'personas_mencionadas' => 'documento_personas_mencionadas',
        ] as $nombre => $tabla) {
            if ($replace) {
                DB::table($tabla)->where('tramite_id', $tramite->id)->where('activo', true)
                    ->update(['activo' => false]);
            }
            foreach ($listas[$nombre] as $index => $fila) {
                DB::table($tabla)->insert([
                    ...$fila,
                    'tramite_id' => $tramite->id,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    ...($nombre === 'destinatarios' ? ['es_destinatario_principal' => $index === 0] : []),
                ]);
            }
        }
    }

    /** @return array<string, array<int, object>> */
    private function receptionLists(Tramite $tramite): array
    {
        return [
            'personas_relacionadas' => DB::table('personas_relacionadas_expediente')->where('tramite_id', $tramite->id)
                ->where('activo', true)->orderBy('orden')->get(['id', 'nombres', 'apellidos', 'dni', 'cargo_funcion', 'tipo_relacion'])->all(),
            'destinatarios' => DB::table('documento_destinatarios as destinatario')
                ->leftJoin('cargos_institucionales as cargo', 'cargo.id', '=', 'destinatario.cargo_institucional_id')
                ->where('destinatario.tramite_id', $tramite->id)
                ->where('destinatario.activo', true)->orderBy('destinatario.orden')
                ->get(['destinatario.id', 'destinatario.nombres', 'destinatario.apellidos', 'destinatario.cargo_institucional_id',
                    'destinatario.cargo_texto', 'destinatario.correo_institucional', 'cargo.nombre as cargo_catalogo'])->all(),
            'personas_mencionadas' => DB::table('documento_personas_mencionadas')->where('tramite_id', $tramite->id)
                ->where('activo', true)->orderBy('orden')->get(['id', 'nombres', 'apellidos', 'dni', 'cargo_funcion', 'descripcion'])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function programaParaRecepcion(array $datos): ?int
    {
        if (empty($datos['propietario_id'])) {
            return isset($datos['programa_estudio_id']) ? (int) $datos['programa_estudio_id'] : null;
        }

        $programaId = DB::table('perfiles_estudiante')
            ->where('user_id', $datos['propietario_id'])
            ->value('programa_estudio_id');

        if ($programaId !== null && ! ProgramaEstudio::query()->whereKey($programaId)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['propietario_id' => 'El programa del estudiante ya no está activo.']);
        }

        return $programaId === null ? null : (int) $programaId;
    }

    private function canEditReception(Tramite $tramite): bool
    {
        return in_array($tramite->estado, ['recibido_oficina', 'digitalizado'], true)
            && ! $tramite->asignaciones()->where('activa', true)->exists();
    }

    private function eliminarDocumentoNoPersistido(string $ruta): void
    {
        try {
            if (! TramiteDocumento::query()->where('ruta', $ruta)->exists()) {
                Storage::disk('local')->delete($ruta);
            }
        } catch (Throwable) {
            // Keep the file if the database cannot confirm whether the transaction committed.
        }
    }

    /**
     * @return array{ruta: string, nombre_original: string, mime_type: string, tamano_bytes: int, sha256: string}
     */
    private function guardarDocumento(UploadedFile $documento): array
    {
        $extension = $documento->extension();
        $nombreGuardado = Str::uuid().($extension === null ? '' : '.'.$extension);
        $ruta = $documento->storeAs('tramites/'.now()->format('Y'), $nombreGuardado, 'local');

        if (! is_string($ruta)) {
            throw new RuntimeException('No se pudo guardar el documento digitalizado.');
        }

        $rutaAbsoluta = Storage::disk('local')->path($ruta);
        $hash = hash_file('sha256', $rutaAbsoluta);
        $tamano = filesize($rutaAbsoluta);

        if (! is_string($hash) || ! is_int($tamano)) {
            Storage::disk('local')->delete($ruta);

            throw new RuntimeException('No se pudo verificar el documento digitalizado.');
        }

        $nombreOriginal = basename(str_replace('\\', '/', $documento->getClientOriginalName()));

        return [
            'ruta' => $ruta,
            'nombre_original' => mb_substr($nombreOriginal, 0, 255),
            'mime_type' => $documento->getMimeType() ?? 'application/octet-stream',
            'tamano_bytes' => $tamano,
            'sha256' => $hash,
        ];
    }
}
