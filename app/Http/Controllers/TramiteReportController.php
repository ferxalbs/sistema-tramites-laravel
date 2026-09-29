<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TramiteReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->filters($request);
        $rows = $this->query($filters)->orderByDesc('t.created_at')->orderByDesc('t.id')
            ->paginate(25)->through(fn (stdClass $row): array => $this->reportRow($row))->withQueryString();

        return Inertia::render('reportes', [
            'rows' => $rows,
            'filters' => [
                'desde' => $filters['desde'] ?? '',
                'hasta' => $filters['hasta'] ?? '',
                'programa' => (string) ($filters['programa'] ?? ''),
                'clasificacion' => $filters['clasificacion'] ?? '',
                'tipo' => $filters['tipo'] ?? '',
                'estado' => $filters['estado'] ?? '',
                'revisor' => (string) ($filters['revisor'] ?? ''),
                'medio' => (string) ($filters['medio'] ?? ''),
            ],
            'catalogs' => [
                'programas' => DB::table('programas_estudio')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'clasificaciones' => config('tramites.clasificaciones'),
                'tipos' => $this->documentTypes(),
                'estados' => config('tramites.estados'),
                'revisores' => DB::table('users')->whereIn('rol', ['docente', 'administrador'])->where('activo', true)->orderBy('name')->get(['id', 'name']),
                'medios' => DB::table('tramite_medios_entrega')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'canExport' => $request->user()->rol === 'administrador',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->query($filters)->orderByDesc('t.created_at')->orderByDesc('t.id')
            ->limit(10000)->get()->map(fn (stdClass $row): array => $this->reportRow($row));

        DB::table('tramite_report_export_events')->insert([
            'actor_id' => $request->user()->id,
            'filtros' => json_encode($filters, JSON_THROW_ON_ERROR),
            'filas' => $rows->count(),
            'created_at' => now(),
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('No se pudo abrir la salida CSV.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Código', 'Asunto', 'Tipo de documento', 'Programa', 'Clasificación', 'Estado', 'Revisor', 'Recepción', 'Actualización'], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->csvSafe(...), array_values($row)), ';', '"', '');
            }

            fclose($output);
        }, 'reporte-expedientes-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'programa' => ['nullable', 'integer', 'exists:programas_estudio,id'],
            'clasificacion' => ['nullable', Rule::in(array_keys(config('tramites.clasificaciones')))],
            'tipo' => ['nullable', Rule::in(array_keys($this->documentTypes()))],
            'estado' => ['nullable', Rule::in(array_keys(config('tramites.estados')))],
            'revisor' => ['nullable', 'integer', 'exists:users,id'],
            'medio' => ['nullable', 'integer', 'exists:tramite_medios_entrega,id'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function documentTypes(): array
    {
        $types = [];

        foreach (config('tramites.tipos_documento', []) as $classificationTypes) {
            $types = array_merge($types, $classificationTypes);
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        return DB::table('tramites as t')
            ->leftJoin('programas_estudio as p', 'p.id', '=', 't.programa_estudio_id')
            ->leftJoin('tramite_asignaciones as a', function ($join): void {
                $join->on('a.tramite_id', '=', 't.id')->where('a.activa', true);
            })
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'a.revisor_id')
            ->leftJoin('tramite_entregas as delivery', function ($join): void {
                $join->on('delivery.tramite_id', '=', 't.id')->where('delivery.activa', true);
            })
            ->leftJoin('tramite_medios_entrega as medium', 'medium.id', '=', 'delivery.medio_entrega_id')
            ->select([
                't.id', 't.codigo', 't.asunto', 't.tipo_documento', 't.clasificacion', 't.estado',
                't.fecha_llegada_oficina', 't.fecha_recepcion', 't.updated_at',
                'p.nombre as programa', 'reviewer.name as revisor', 'medium.nombre as medio',
            ])
            ->when(! empty($filters['desde']), fn (Builder $query): Builder => $query->where('t.created_at', '>=', $filters['desde'].' 00:00:00'))
            ->when(! empty($filters['hasta']), fn (Builder $query): Builder => $query->where('t.created_at', '<=', $filters['hasta'].' 23:59:59'))
            ->when(! empty($filters['programa']), fn (Builder $query): Builder => $query->where('t.programa_estudio_id', $filters['programa']))
            ->when(! empty($filters['clasificacion']), fn (Builder $query): Builder => $query->where('t.clasificacion', $filters['clasificacion']))
            ->when(! empty($filters['tipo']), fn (Builder $query): Builder => $query->where('t.tipo_documento', $filters['tipo']))
            ->when(! empty($filters['estado']), fn (Builder $query): Builder => $query->where('t.estado', $filters['estado']))
            ->when(! empty($filters['revisor']), fn (Builder $query): Builder => $query->where('a.revisor_id', $filters['revisor']))
            ->when(! empty($filters['medio']), fn (Builder $query): Builder => $query->where('delivery.medio_entrega_id', $filters['medio']));
    }

    /**
     * @return array<string, string>
     */
    private function reportRow(stdClass $row): array
    {
        return [
            'codigo' => (string) $row->codigo,
            'asunto' => (string) $row->asunto,
            'tipo' => (string) config('tramites.tipos_documento.'.$row->clasificacion.'.'.$row->tipo_documento, $row->tipo_documento),
            'programa' => (string) ($row->programa ?? ''),
            'clasificacion' => (string) config('tramites.clasificaciones.'.$row->clasificacion, $row->clasificacion),
            'estado' => (string) config('tramites.estados.'.$row->estado, $row->estado),
            'revisor' => (string) ($row->revisor ?? ''),
            'recepcion' => (string) ($row->fecha_llegada_oficina ?? $row->fecha_recepcion),
            'actualizacion' => (string) $row->updated_at,
        ];
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', ltrim($value)) === 1 ? "'".$value : $value;
    }
}
