<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTramiteRequest;
use App\Http\Requests\UploadTramiteDocumentRequest;
use App\Models\Tramite;
use App\Models\TramiteDocumento;
use App\Models\TramiteEvento;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TramiteController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
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
            'clasificacion' => config('tramites.clasificaciones.'.$tramite->clasificacion, $tramite->clasificacion),
            'tipo_documento' => config('tramites.tipos_documento.'.$tramite->clasificacion.'.'.$tramite->tipo_documento, $tramite->tipo_documento),
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

    public function create(): InertiaResponse
    {
        return Inertia::render('tramites/create', [
            'catalogos' => [
                'clasificaciones' => config('tramites.clasificaciones'),
                'tipos_documento' => config('tramites.tipos_documento'),
                'destinos' => config('tramites.destinos'),
                'prioridades' => config('tramites.prioridades'),
            ],
            'hoy' => now()->toDateString(),
            'estudiantes' => User::query()
                ->where('rol', 'estudiante')
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->all(),
        ]);
    }

    public function store(StoreTramiteRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $documento = $datos['documento'] ?? null;
        unset($datos['documento']);

        $codigo = $this->siguienteCodigo();
        $datosDocumento = $documento === null ? null : $this->guardarDocumento($documento);
        $ruta = $datosDocumento['ruta'] ?? null;

        try {
            $tramite = DB::transaction(function () use ($codigo, $datos, $datosDocumento, $request): Tramite {
                $estado = $datosDocumento === null ? 'recibido_oficina' : 'digitalizado';
                $tramite = Tramite::create([
                    ...$datos,
                    'codigo' => $codigo,
                    'estado' => $estado,
                    'recibido_por' => $request->user()->id,
                    'propietario_id' => $datos['propietario_id'] ?? null,
                ]);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => 'recepcion',
                    'descripcion' => 'El trámite fue recibido en oficina.',
                    'estado_nuevo' => 'recibido_oficina',
                    'metadatos' => isset($datos['propietario_id']) ? ['propietario_id' => $datos['propietario_id']] : null,
                ]);

                if ($datosDocumento !== null) {
                    $registroDocumento = $tramite->documentos()->create([
                        ...$datosDocumento,
                        'categoria' => 'documento_original',
                        'disco' => 'local',
                        'cargado_por' => $request->user()->id,
                    ]);

                    TramiteEvento::create([
                        'tramite_id' => $tramite->id,
                        'usuario_id' => $request->user()->id,
                        'accion' => 'digitalizacion',
                        'descripcion' => 'Se cargó el documento digitalizado.',
                        'estado_anterior' => 'recibido_oficina',
                        'estado_nuevo' => 'digitalizado',
                        'metadatos' => ['documento_id' => $registroDocumento->id],
                    ]);
                }

                return $tramite;
            });
        } catch (Throwable $exception) {
            if ($ruta !== null) {
                try {
                    $tramiteGuardado = Tramite::query()->where('codigo', $codigo)->exists();

                    if (! $tramiteGuardado) {
                        Storage::disk('local')->delete($ruta);
                    }
                } catch (Throwable) {
                    // Keep the file if Turso cannot confirm whether the transaction committed.
                }
            }

            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'El trámite fue registrado.');
    }

    public function upload(UploadTramiteDocumentRequest $request, Tramite $tramite): RedirectResponse
    {
        abort_unless($tramite->estado === 'recibido_oficina' && ! $tramite->documentos()->exists(), 409);

        $datosDocumento = $this->guardarDocumento($request->file('documento'));

        try {
            DB::transaction(function () use ($request, $tramite, $datosDocumento): void {
                $updated = DB::table('tramites')
                    ->where('id', $tramite->id)
                    ->where('estado', 'recibido_oficina')
                    ->update([
                        'estado' => 'digitalizado',
                        'updated_at' => now(),
                    ]);

                abort_unless($updated === 1, 409);

                $documento = $tramite->documentos()->create([
                    ...$datosDocumento,
                    'categoria' => 'documento_original',
                    'disco' => 'local',
                    'cargado_por' => $request->user()->id,
                ]);

                TramiteEvento::create([
                    'tramite_id' => $tramite->id,
                    'usuario_id' => $request->user()->id,
                    'accion' => 'digitalizacion',
                    'descripcion' => 'Se cargó el documento digitalizado.',
                    'estado_anterior' => 'recibido_oficina',
                    'estado_nuevo' => 'digitalizado',
                    'metadatos' => ['documento_id' => $documento->id],
                ]);
            });
        } catch (Throwable $exception) {
            try {
                $documentoGuardado = TramiteDocumento::query()->where('ruta', $datosDocumento['ruta'])->exists();

                if (! $documentoGuardado) {
                    Storage::disk('local')->delete($datosDocumento['ruta']);
                }
            } catch (Throwable) {
                // Keep the file if Turso cannot confirm whether the transaction committed.
            }

            throw $exception;
        }

        return redirect()->route('tramites.show', $tramite)->with('success', 'El documento fue digitalizado.');
    }

    public function show(Request $request, Tramite $tramite): InertiaResponse
    {
        $tramite->load([
            'documentos' => fn ($query) => $query->orderBy('id'),
            'eventos' => fn ($query) => $query->with('usuario')->orderBy('id'),
            'recibidoPor',
            'propietario',
            'borradorActual.plantilla',
            'borradores' => fn ($query) => $query->with('plantilla')->orderByDesc('version'),
            'asignacionActual.revisor',
            'asignaciones' => fn ($query) => $query->with(['revisor', 'asignadoPor'])->orderByDesc('created_at'),
            'rondasRevision.revisor',
            'rondasRevision.observaciones.respuesta',
            'rondasRevision.versionBorrador.plantilla',
            'documentoFinalActual',
        ]);

        $borradorActual = $tramite->borradorActual;
        $asignacionActual = $tramite->asignacionActual;
        $documentoFinal = $tramite->documentoFinalActual;

        return Inertia::render('tramites/show', [
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'clasificacion' => config('tramites.clasificaciones.'.$tramite->clasificacion, $tramite->clasificacion),
                'tipo_documento' => config('tramites.tipos_documento.'.$tramite->clasificacion.'.'.$tramite->tipo_documento, $tramite->tipo_documento),
                'persona_nombre' => $tramite->persona_nombre,
                'persona_identificador' => $tramite->persona_identificador,
                'destino_tipo' => config('tramites.destinos.'.$tramite->destino_tipo, $tramite->destino_tipo),
                'destino_nombre' => $tramite->destino_nombre,
                'asunto' => $tramite->asunto,
                'descripcion' => $tramite->descripcion,
                'prioridad' => config('tramites.prioridades.'.$tramite->prioridad, $tramite->prioridad),
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'folios' => $tramite->folios,
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'recibido_por' => $tramite->recibidoPor?->name,
                'propietario' => $tramite->propietario?->name,
                'puede_gestionar_asignacion' => $request->user()->rol === 'asistente',
                'puede_corregir_revision' => $request->user()->rol === 'asistente' && $tramite->estado === 'observado',
                'puede_emitir_documento_final' => $request->user()->rol === 'asistente'
                    && in_array($tramite->estado, ['aprobado', 'rechazado'], true)
                    && $documentoFinal === null,
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
            'usuario_id' => request()->user()->id,
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
