<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteClassificationController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('clasificaciones-expediente', [
            'clasificaciones' => DB::table('clasificaciones_expediente')
                ->where('codigo', '<>', 'institucional')
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'requiere_estudiante', 'activo']),
        ]);
    }

    public function update(Request $request, int $classification): RedirectResponse
    {
        abort_unless(DB::table('clasificaciones_expediente')->where('id', $classification)
            ->where('codigo', '<>', 'institucional')->exists(), 404);

        $request->merge([
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
        ]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120', Rule::unique('clasificaciones_expediente', 'nombre')->ignore($classification)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $classification, $data): void {
            $before = DB::table('clasificaciones_expediente')->where('id', $classification)
                ->first(['nombre', 'descripcion', 'activo']);
            abort_if($before === null, 404);

            $changed = DB::table('clasificaciones_expediente')->where('id', $classification)
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
                'entidad' => 'clasificacion_expediente',
                'entidad_id' => $classification,
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

        return to_route('admin.classifications.index')->with('success', 'Clasificación actualizada.');
    }
}
