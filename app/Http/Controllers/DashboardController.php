<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteEvento;
use App\Models\TramiteMedioEntrega;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $documentTypes = [];

        foreach (config('tramites.tipos_documento', []) as $classificationTypes) {
            if (is_array($classificationTypes)) {
                $documentTypes = array_merge($documentTypes, $classificationTypes);
            }
        }
        $filters = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'clasificacion' => ['nullable', Rule::in(array_keys(config('tramites.clasificaciones')))],
            'tipo' => ['nullable', Rule::in(array_keys($documentTypes))],
            'estado' => ['nullable', Rule::in(array_keys(config('tramites.estados')))],
            'revisor' => ['nullable', 'integer', 'exists:users,id'],
            'medio' => ['nullable', 'integer', 'exists:tramite_medios_entrega,id'],
        ]);
        $tramites = $this->scopedTramites($actor, $filters);
        $states = (clone $tramites)
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->map(fn (Tramite $tramite): array => [
                'codigo' => $tramite->estado,
                'nombre' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'total' => (int) $tramite->getAttribute('total'),
            ])->sortBy(fn (array $state): int => array_flip(array_keys(config('tramites.estados')))[$state['codigo']] ?? PHP_INT_MAX)->values()->all();
        $total = array_sum(array_column($states, 'total'));

        $events = TramiteEvento::query()
            ->with('tramite:id,codigo,asunto,estado')
            ->whereIn('tramite_id', (clone $tramites)->select('id'))
            ->where(function (Builder $query): void {
                $query->whereNotNull('estado_nuevo')
                    ->orWhereIn('accion', ['recepcion', 'digitalizacion', 'documento_final_emitido', 'firma_registrada', 'entrega_registrada', 'recepcion_confirmada', 'expediente_cerrado']);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'tramite_id', 'accion', 'estado_nuevo', 'created_at']);

        $activeAssignmentIds = $actor->rol === 'docente'
            ? TramiteAsignacion::query()
                ->where('revisor_id', $actor->id)
                ->where('destino', 'docente')
                ->where('activa', true)
                ->whereIn('tramite_id', $events->pluck('tramite_id'))
                ->pluck('tramite_id')->all()
            : [];
        $activityLabels = [
            'recepcion' => 'Expediente recibido',
            'digitalizacion' => 'Documento digitalizado',
            'documento_final_emitido' => 'Documento final generado',
            'firma_registrada' => 'Firma registrada',
            'entrega_registrada' => 'Entrega registrada',
            'recepcion_confirmada' => 'Recepción confirmada',
            'expediente_cerrado' => 'Expediente cerrado',
        ];
        $activity = $events->map(function (TramiteEvento $event) use ($actor, $activeAssignmentIds, $activityLabels): ?array {
            $tramite = $event->tramite;

            if (! $tramite instanceof Tramite) {
                return null;
            }

            $estado = $event->estado_nuevo;

            return [
                'tramite_id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'title' => $activityLabels[$event->accion] ?? config('tramites.estados.'.$estado, 'Expediente actualizado'),
                'estado' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'fecha' => $event->created_at?->toIso8601String(),
                'linkable' => $actor->rol !== 'docente' || in_array($tramite->id, $activeAssignmentIds, true),
            ];
        })->filter()->values()->all();

        $adminCharts = null;

        if ($actor->rol === 'administrador') {
            $monthly = (clone $tramites)
                ->selectRaw("substr(created_at, 1, 7) as periodo, COUNT(*) as registrados, SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados")
                ->groupByRaw('substr(created_at, 1, 7)')
                ->orderByDesc('periodo')
                ->limit(12)
                ->get()
                ->reverse()
                ->map(fn (Tramite $row): array => [
                    'periodo' => (string) $row->getAttribute('periodo'),
                    'registrados' => (int) $row->getAttribute('registrados'),
                    'cerrados' => (int) $row->getAttribute('cerrados'),
                ])->values()->all();
            $chartTypes = (clone $tramites)
                ->select('clasificacion', 'tipo_documento')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('clasificacion', 'tipo_documento')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn (Tramite $row): array => [
                    'nombre' => config('tramites.tipos_documento.'.$row->clasificacion.'.'.$row->tipo_documento, $row->tipo_documento),
                    'total' => (int) $row->getAttribute('total'),
                ])->all();
            $load = TramiteAsignacion::query()
                ->join('users', 'users.id', '=', 'tramite_asignaciones.revisor_id')
                ->where('tramite_asignaciones.activa', true)
                ->select('users.name')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn (TramiteAsignacion $row): array => ['nombre' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')])->all();
            $users = User::query()
                ->selectRaw('substr(created_at, 1, 7) as periodo, rol, COUNT(*) as total')
                ->groupByRaw('substr(created_at, 1, 7), rol')
                ->orderByDesc('periodo')
                ->limit(48)
                ->get()
                ->map(fn (User $row): array => [
                    'nombre' => $row->getAttribute('periodo').' · '.$row->rol,
                    'total' => (int) $row->getAttribute('total'),
                ])->all();

            $adminCharts = ['monthly' => $monthly, 'types' => $chartTypes, 'load' => $load, 'users' => $users];
        }

        $closedState = collect($states)->firstWhere('codigo', 'cerrado');

        return Inertia::render('dashboard', [
            'role' => $actor->rol,
            'filters' => [
                'desde' => $filters['desde'] ?? '',
                'hasta' => $filters['hasta'] ?? '',
                'clasificacion' => $filters['clasificacion'] ?? '',
                'tipo' => $filters['tipo'] ?? '',
                'estado' => $filters['estado'] ?? '',
                'revisor' => (string) ($filters['revisor'] ?? ''),
                'medio' => (string) ($filters['medio'] ?? ''),
            ],
            'catalogs' => [
                'clasificaciones' => config('tramites.clasificaciones'),
                'tipos' => $documentTypes,
                'estados' => config('tramites.estados'),
                'revisores' => in_array($actor->rol, ['asistente', 'administrador'], true)
                    ? User::query()->whereIn('rol', ['docente', 'administrador'])->where('activo', true)->orderBy('name')->get(['id', 'name'])
                        ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all()
                    : [],
                'medios' => TramiteMedioEntrega::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                    ->map(fn (TramiteMedioEntrega $medio): array => ['id' => $medio->id, 'nombre' => $medio->nombre])->all(),
            ],
            'summary' => [
                'total' => $total,
                'cerrados' => $closedState['total'] ?? 0,
            ],
            'states' => $states,
            'activity' => $activity,
            'adminCharts' => $adminCharts,
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Tramite>
     */
    private function scopedTramites(User $actor, array $filters): Builder
    {
        return Tramite::query()
            ->when($actor->rol === 'estudiante', fn (Builder $query): Builder => $query->where('propietario_id', $actor->id))
            ->when($actor->rol === 'docente', fn (Builder $query): Builder => $query->whereHas('asignaciones', fn (Builder $assignment): Builder => $assignment
                ->where('revisor_id', $actor->id)
                ->where('destino', 'docente')))
            ->when(($filters['desde'] ?? '') !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', $filters['desde'].' 00:00:00'))
            ->when(($filters['hasta'] ?? '') !== '', fn (Builder $query): Builder => $query->where('created_at', '<=', $filters['hasta'].' 23:59:59'))
            ->when(($filters['clasificacion'] ?? '') !== '', fn (Builder $query): Builder => $query->where('clasificacion', $filters['clasificacion']))
            ->when(($filters['tipo'] ?? '') !== '', fn (Builder $query): Builder => $query->where('tipo_documento', $filters['tipo']))
            ->when(($filters['estado'] ?? '') !== '', fn (Builder $query): Builder => $query->where('estado', $filters['estado']))
            ->when(($filters['revisor'] ?? '') !== '', fn (Builder $query): Builder => $query->whereHas('asignaciones', fn (Builder $assignment): Builder => $assignment
                ->where('revisor_id', (int) $filters['revisor'])
                ->where('activa', true)))
            ->when(($filters['medio'] ?? '') !== '', fn (Builder $query): Builder => $query->whereHas('entregaActual', fn (Builder $delivery): Builder => $delivery
                ->where('medio_entrega_id', (int) $filters['medio'])));
    }
}
