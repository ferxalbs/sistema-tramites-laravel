<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteDeadlineController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('plazos', [
            'plazos' => DB::table('configuracion_plazos as c')
                ->join('tipos_tramite as t', 't.id', '=', 'c.tipo_tramite_id')
                ->orderBy('t.nombre')
                ->get(['c.id', 'c.dias_estimados', 'c.dias_maximos', 'c.tipo_dias', 'c.dias_anticipacion_recordatorio', 'c.activo', 't.codigo', 't.nombre as tipo']),
            'feriados' => DB::table('feriados')->where('activo', true)->orderBy('fecha')
                ->get(['fecha', 'nombre', 'es_demostracion']),
        ]);
    }

    public function update(Request $request, int $deadline): RedirectResponse
    {
        abort_unless(DB::table('configuracion_plazos')->where('id', $deadline)->exists(), 404);

        $data = $request->validate([
            'dias_estimados' => ['required', 'integer', 'min:1', 'max:365', 'lte:dias_maximos'],
            'dias_maximos' => ['required', 'integer', 'min:1', 'max:365'],
            'tipo_dias' => ['required', Rule::in(['calendario', 'habiles'])],
            'dias_anticipacion_recordatorio' => ['required', 'integer', 'min:0', 'lte:dias_maximos'],
        ]);

        DB::transaction(function () use ($request, $deadline, $data): void {
            $before = DB::table('configuracion_plazos')->where('id', $deadline)
                ->first(['dias_estimados', 'dias_maximos', 'tipo_dias', 'dias_anticipacion_recordatorio', 'es_plazo_oficial']);
            abort_if($before === null, 404);

            $changed = DB::table('configuracion_plazos')->where('id', $deadline)
                ->where('dias_estimados', $before->dias_estimados)
                ->where('dias_maximos', $before->dias_maximos)
                ->where('tipo_dias', $before->tipo_dias)
                ->where('dias_anticipacion_recordatorio', $before->dias_anticipacion_recordatorio)
                ->update([
                    ...$data,
                    'es_plazo_oficial' => false,
                    'actualizado_por' => $request->user()->id,
                    'updated_at' => now(),
                ]);
            abort_unless($changed === 1, 409);

            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'actualizar_plazo',
                'entidad' => 'configuracion_plazo',
                'entidad_id' => $deadline,
                'valor_anterior' => json_encode($before, JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode([...$data, 'es_plazo_oficial' => false], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.deadlines.index')->with('success', 'Plazo referencial actualizado.');
    }
}
