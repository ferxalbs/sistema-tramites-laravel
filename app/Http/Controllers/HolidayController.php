<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HolidayController extends Controller
{
    public function index(): InertiaResponse
    {
        $holidays = DB::table('feriados')
            ->where('activo', true)
            ->orderBy('fecha')
            ->get(['id', 'fecha', 'nombre', 'es_demostracion']);

        return Inertia::render('feriados', ['holidays' => $holidays]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            DB::table('feriados')->upsert([
                'fecha' => $validated['fecha'],
                'nombre' => trim($validated['nombre']),
                'es_demostracion' => false,
                'activo' => true,
                'creado_por' => $request->user()->id,
            ], ['fecha'], ['nombre', 'es_demostracion', 'activo']);

            $holiday = DB::table('feriados')->where('fecha', $validated['fecha'])->first(['id']);
            DB::table('feriado_eventos')->insert([
                'feriado_id' => $holiday->id,
                'actor_id' => $request->user()->id,
                'accion' => 'configurar_feriado',
            ]);
        });

        return to_route('admin.holidays.index')->with('success', 'Feriado confirmado guardado.');
    }

    public function deactivate(Request $request, int $holiday): RedirectResponse
    {
        DB::transaction(function () use ($request, $holiday): void {
            $changed = DB::table('feriados')->where('id', $holiday)->where('activo', true)->update(['activo' => false]);
            abort_if($changed === 0, 404);

            DB::table('feriado_eventos')->insert([
                'feriado_id' => $holiday,
                'actor_id' => $request->user()->id,
                'accion' => 'desactivar_feriado',
            ]);
        });

        return to_route('admin.holidays.index')->with('success', 'Feriado desactivado.');
    }
}
