<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelTramiteAsignacionRequest;
use App\Http\Requests\ReassignTramiteAsignacionRequest;
use App\Http\Requests\StoreTramiteAsignacionRequest;
use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteObservacionRevision;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use App\Services\Tramites\ManageTramiteAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteAsignacionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', Rule::in(['borrador_preparado', 'pendiente_asignacion', 'asignado'])],
            'destino' => ['nullable', 'string', Rule::in(['docente', 'oficina'])],
        ]);

        $query = Tramite::query()
            ->with(['borradorActual.plantilla', 'asignacionActual.revisor'])
            ->withCount('documentos')
            ->whereIn('estado', ['borrador_preparado', 'pendiente_asignacion', 'asignado'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($query) use ($term): void {
                $query->where('codigo', 'like', $term)
                    ->orWhere('asunto', 'like', $term)
                    ->orWhere('persona_nombre', 'like', $term)
                    ->orWhere('persona_identificador', 'like', $term)
                    ->orWhereHas('asignacionActual.revisor', fn ($reviewerQuery) => $reviewerQuery->where('name', 'like', $term));
            });
        }

        if (($filters['estado'] ?? '') !== '') {
            $query->where('estado', $filters['estado']);
        }

        if (($filters['destino'] ?? '') !== '') {
            $query->whereHas('asignacionActual', fn ($assignmentQuery) => $assignmentQuery->where('destino', $filters['destino']));
        }

        $tramites = $query->paginate(15)->through(fn (Tramite $tramite): array => [
            'id' => $tramite->id,
            'codigo' => $tramite->codigo,
            'asunto' => $tramite->asunto,
            'persona_nombre' => $tramite->persona_nombre,
            'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
            'estado' => $tramite->estado,
            'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
            'borrador' => $tramite->borradorActual?->plantilla->nombre,
            'documentos_count' => $tramite->documentos_count,
            'asignacion' => $tramite->asignacionActual === null ? null : [
                'destino' => $tramite->asignacionActual->destino,
                'revisor' => $tramite->asignacionActual->revisor->name,
                'fecha_esperada' => $tramite->asignacionActual->fecha_esperada?->toDateString(),
            ],
        ])->withQueryString();

        return Inertia::render('tramites/asignaciones/index', [
            'tramites' => $tramites,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'estado' => $filters['estado'] ?? '',
                'destino' => $filters['destino'] ?? '',
            ],
            'resumen' => [
                'pendientes' => Tramite::query()->where('estado', 'pendiente_asignacion')->count(),
                'asignados_hoy' => TramiteAsignacion::query()
                    ->where('created_at', '>=', today())
                    ->where('created_at', '<', today()->addDay())
                    ->count(),
                'docentes' => TramiteAsignacion::query()->where('activa', true)->where('destino', 'docente')->count(),
                'oficina' => TramiteAsignacion::query()->where('activa', true)->where('destino', 'oficina')->count(),
                'reasignados' => TramiteAsignacion::query()->where('estado', 'reasignada')->count(),
                'sin_revisor' => Tramite::query()->where('estado', 'pendiente_asignacion')->whereDoesntHave('asignacionActual')->count(),
            ],
        ]);
    }

    public function create(Tramite $tramite, ManageTramiteAssignment $manageTramiteAssignment): InertiaResponse
    {
        abort_unless($tramite->estado === 'pendiente_asignacion', 409);
        abort_unless($tramite->borradorActual?->estado === 'preparado_asignacion', 409);
        $revisores = $manageTramiteAssignment->eligibleReviewers();
        $docenteSugeridoId = $tramite->destino_tipo === 'docente'
            && collect($revisores['docente'])->contains('id', (int) $tramite->destino_docente_id)
                ? (int) $tramite->destino_docente_id
                : null;

        return Inertia::render('tramites/asignaciones/form', [
            'tramite' => $this->tramiteSummary($tramite),
            'modo' => 'asignar',
            'asignacion' => null,
            'revisores' => $revisores,
            'destino_inicial' => $tramite->destino_tipo,
            'revisor_sugerido_id' => $docenteSugeridoId,
        ]);
    }

    public function reassign(Tramite $tramite, ManageTramiteAssignment $manageTramiteAssignment): InertiaResponse
    {
        abort_unless($tramite->estado === 'asignado', 409);

        $asignacion = $tramite->asignacionActual()->with('revisor')->first();
        abort_unless($asignacion !== null && $asignacion->fecha_inicio_revision === null, 409);

        return Inertia::render('tramites/asignaciones/form', [
            'tramite' => $this->tramiteSummary($tramite),
            'modo' => 'reasignar',
            'asignacion' => [
                'id' => $asignacion->id,
                'destino' => $asignacion->destino,
                'revisor_id' => $asignacion->revisor_id,
                'revisor' => $asignacion->revisor->name,
            ],
            'revisores' => $manageTramiteAssignment->eligibleReviewers(),
        ]);
    }

    public function store(StoreTramiteAsignacionRequest $request, Tramite $tramite, ManageTramiteAssignment $manageTramiteAssignment): RedirectResponse
    {
        $manageTramiteAssignment->assign($tramite, $request->validated(), $request->user());

        return redirect()->route('tramites.show', $tramite)->with('success', 'El trámite fue asignado al revisor seleccionado.');
    }

    public function update(ReassignTramiteAsignacionRequest $request, Tramite $tramite, ManageTramiteAssignment $manageTramiteAssignment): RedirectResponse
    {
        $manageTramiteAssignment->reassign($tramite, $request->validated(), $request->user());

        return redirect()->route('tramites.show', $tramite)->with('success', 'El trámite fue reasignado.');
    }

    public function cancel(CancelTramiteAsignacionRequest $request, Tramite $tramite, ManageTramiteAssignment $manageTramiteAssignment): RedirectResponse
    {
        $manageTramiteAssignment->cancel($tramite, $request->validated('motivo_finalizacion'), $request->user());

        return redirect()->route('tramites.show', $tramite)->with('success', 'La asignación se canceló y el trámite volvió a pendientes.');
    }

    public function docenteIndex(Request $request): InertiaResponse
    {
        return $this->reviewerIndex($request, 'docente');
    }

    public function oficinaIndex(Request $request): InertiaResponse
    {
        return $this->reviewerIndex($request, 'oficina');
    }

    public function docenteShow(Request $request, Tramite $tramite): InertiaResponse
    {
        return $this->reviewerShow($request->user(), $tramite, 'docente');
    }

    public function oficinaShow(Request $request, Tramite $tramite): InertiaResponse
    {
        return $this->reviewerShow($request->user(), $tramite, 'oficina');
    }

    private function reviewerIndex(Request $request, string $destino): InertiaResponse
    {
        $rolEsperado = $destino === 'docente' ? 'docente' : 'administrador';
        abort_unless($request->user()->activo && $request->user()->rol === $rolEsperado, 403);

        $filters = $request->validate([
            'estado' => ['nullable', 'string', Rule::in(['asignado', 'en_revision', 'observado', 'corregido'])],
        ]);

        $asignaciones = TramiteAsignacion::query()
            ->with(['tramite.borradorActual.plantilla'])
            ->where('revisor_id', $request->user()->id)
            ->where('destino', $destino)
            ->where('activa', true)
            ->whereHas('tramite', fn ($tramiteQuery) => $tramiteQuery->whereIn('estado', ['asignado', 'en_revision', 'observado', 'corregido']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (TramiteAsignacion $asignacion): array => [
                'tramite_id' => $asignacion->tramite->id,
                'codigo' => $asignacion->tramite->codigo,
                'asunto' => $asignacion->tramite->asunto,
                'persona_nombre' => $asignacion->tramite->persona_nombre,
                'prioridad' => config('tramites.prioridades.'.$asignacion->tramite->prioridad, $asignacion->tramite->prioridad),
                'fecha_asignacion' => $asignacion->created_at?->toIso8601String(),
                'fecha_esperada' => $asignacion->fecha_esperada?->toDateString(),
                'estado' => $asignacion->tramite->estado,
                'estado_label' => config('tramites.estados.'.$asignacion->tramite->estado, $asignacion->tramite->estado),
                'plantilla' => $asignacion->tramite->borradorActual?->plantilla->nombre,
            ]);

        return Inertia::render('tramites/asignaciones/reviewer-index', [
            'destino' => $destino,
            'destino_label' => $destino === 'docente' ? 'Docente' : 'Oficina',
            'asignaciones' => $asignaciones,
            'filters' => ['estado' => $filters['estado'] ?? ''],
        ]);
    }

    private function reviewerShow(?User $user, Tramite $tramite, string $destino): InertiaResponse
    {
        $rolEsperado = $destino === 'docente' ? 'docente' : 'administrador';
        abort_unless($user !== null && $user->activo && $user->rol === $rolEsperado, 403);

        $asignacion = TramiteAsignacion::query()
            ->with(['revisor', 'asignadoPor'])
            ->where('tramite_id', $tramite->id)
            ->where('revisor_id', $user->id)
            ->where('destino', $destino)
            ->where(fn ($assignment) => $assignment->where('activa', true)
                ->orWhereIn('estado', ['aprobado', 'rechazado']))
            ->orderByDesc('id')
            ->first();

        if ($asignacion === null) {
            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $user->id,
                'accion' => 'acceso_revision_no_autorizado',
                'descripcion' => 'Se rechazó un acceso a una asignación ajena.',
                'metadatos' => ['destino_solicitado' => $destino],
            ]);

            abort(403);
        }

        $tramite->load(['documentos' => fn ($query) => $query->where('vigente', true)->orderBy('id')]);
        $rondas = TramiteRondaRevision::query()
            ->with(['observaciones.respuesta', 'versionBorrador'])
            ->where('tramite_id', $tramite->id)
            ->where('asignacion_id', $asignacion->id)
            ->orderBy('numero_ronda')
            ->get();
        $borrador = $asignacion->activa ? $tramite->borradorActual()->with('plantilla')->first() : $rondas->last()?->versionBorrador;
        abort_unless($borrador !== null && (! $asignacion->activa || $borrador->estado === 'preparado_asignacion'), 409);
        $borrador->loadMissing(['plantilla', 'remitente', 'firmante']);
        $rondaActual = $rondas->first(fn (TramiteRondaRevision $ronda): bool => $ronda->activa);

        TramiteEvento::query()->create([
            'tramite_id' => $tramite->id,
            'usuario_id' => $user->id,
            'accion' => 'apertura_borrador_revisor',
            'descripcion' => 'El revisor asignado abrió el borrador preparado.',
            'metadatos' => [
                'asignacion_id' => $asignacion->id,
                'version' => $borrador->version,
            ],
        ]);

        return Inertia::render('tramites/asignaciones/reviewer-show', [
            'destino' => $destino,
            'categorias_observacion' => TramiteObservacionRevision::CATEGORIAS,
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'persona_nombre' => $tramite->persona_nombre,
                'persona_identificador' => $tramite->persona_identificador,
                'descripcion' => $tramite->descripcion,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'prioridad' => config('tramites.prioridades.'.$tramite->prioridad, $tramite->prioridad),
            ],
            'asignacion' => [
                'id' => $asignacion->id,
                'destino' => $asignacion->destino,
                'motivo' => $asignacion->motivo,
                'instrucciones_revision' => $asignacion->instrucciones_revision,
                'fecha_esperada' => $asignacion->fecha_esperada?->toDateString(),
                'fecha_asignacion' => $asignacion->created_at?->toIso8601String(),
                'asignado_por' => $asignacion->asignadoPor?->name,
            ],
            'borrador' => [
                'version' => $borrador->version,
                'plantilla' => $borrador->plantilla->nombre,
                'remitente' => $borrador->remitente?->name,
                'firmante' => $borrador->firmante?->name,
                'fecha_documento' => $borrador->fecha_documento->toDateString(),
                'lugar' => $borrador->lugar,
                'asunto' => $borrador->asunto,
                'introduccion' => $borrador->introduccion,
                'contenido_principal' => $borrador->contenido_principal,
                'contenido_renderizado' => $borrador->contenido_renderizado,
                'cierre' => $borrador->cierre,
                'destinatarios' => $borrador->destinatarios ?? [],
                'personas_mencionadas' => $borrador->personas_mencionadas ?? [],
                'adjuntos' => $borrador->adjuntos ?? [],
            ],
            'archivos' => $tramite->documentos->map(fn ($documento): array => [
                'id' => $documento->id,
                'nombre' => $documento->nombre_original,
                'categoria' => $documento->categoria,
                'version' => $documento->version,
                'vigente' => $documento->vigente,
            ])->all(),
            'documentos_finales' => TramiteDocumentoFinal::query()
                ->where('tramite_id', $tramite->id)
                ->whereIn('estado', ['emitido', 'anulado', 'sustituido'])
                ->whereHas('rondaRevision', fn ($query) => $query->where('asignacion_id', $asignacion->id))
                ->orderBy('version')
                ->get(['id', 'numero_documento', 'estado', 'version'])
                ->map(fn (TramiteDocumentoFinal $documento): array => [
                    'id' => $documento->id,
                    'numero' => $documento->numero_documento,
                    'estado' => $documento->estado,
                    'version' => $documento->version,
                ])->all(),
            'revision' => [
                'puede_iniciar' => $asignacion->activa && in_array($tramite->estado, ['asignado', 'corregido'], true),
                'puede_observar' => $asignacion->activa && $tramite->estado === 'en_revision' && $rondaActual !== null,
                'puede_decidir' => $asignacion->activa && $tramite->estado === 'en_revision' && $rondaActual !== null,
                'rondas' => $rondas->map(fn (TramiteRondaRevision $ronda): array => [
                    'numero' => $ronda->numero_ronda,
                    'estado' => $ronda->estado,
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
            ],
        ]);
    }

    /**
     * @return array{id: int, codigo: string, asunto: string, persona_nombre: string, fecha_recepcion: string}
     */
    private function tramiteSummary(Tramite $tramite): array
    {
        return [
            'id' => $tramite->id,
            'codigo' => $tramite->codigo,
            'asunto' => $tramite->asunto,
            'persona_nombre' => $tramite->persona_nombre,
            'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
        ];
    }
}
