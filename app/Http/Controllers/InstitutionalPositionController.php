<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InstitutionalPositionController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('cargos-institucionales', [
            'cargos' => DB::table('cargos_institucionales')->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'activo']),
        ]);
    }

    public function update(Request $request, int $position): RedirectResponse
    {
        $request->merge([
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
        ]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:140'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $position, $data): void {
            $before = DB::table('cargos_institucionales')->where('id', $position)
                ->first(['nombre', 'descripcion', 'activo']);
            abort_if($before === null, 404);

            $changed = DB::table('cargos_institucionales')->where('id', $position)
                ->where('nombre', $before->nombre)
                ->where('descripcion', $before->descripcion)
                ->where('activo', $before->activo)
                ->update([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?: null,
                    'activo' => (bool) $data['activo'],
                    'updated_at' => now(),
                ]);
            abort_unless($changed === 1, 409);

            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'edicion_catalogo_documental',
                'entidad' => 'cargo_institucional',
                'entidad_id' => $position,
                'valor_anterior' => json_encode($before, JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?: null,
                    'activo' => (bool) $data['activo'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.positions.index')->with('success', 'Cargo institucional actualizado.');
    }
}
