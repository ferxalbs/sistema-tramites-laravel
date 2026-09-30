<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OutputDocumentTypeController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('formatos-salida', [
            'formatos' => DB::table('tipos_documento_salida')->orderBy('orden')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'permite_modalidad_multiple', 'activo']),
            'plantillasFinales' => DB::table('tramite_plantillas')
                ->where('activa', true)
                ->where('estado', 'publicada')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'version', 'tipo_documento_salida', 'modalidad']),
        ]);
    }

    public function update(Request $request, int $format): RedirectResponse
    {
        abort_unless(DB::table('tipos_documento_salida')->where('id', $format)->exists(), 404);

        $request->merge([
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
        ]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120', Rule::unique('tipos_documento_salida', 'nombre')->ignore($format)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $format, $data): void {
            $before = DB::table('tipos_documento_salida')->where('id', $format)
                ->first(['nombre', 'descripcion', 'activo']);
            abort_if($before === null, 404);

            $changed = DB::table('tipos_documento_salida')->where('id', $format)
                ->where('nombre', $before->nombre)
                ->where('descripcion', $before->descripcion)
                ->where('activo', $before->activo)
                ->update([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?: null,
                    'activo' => true,
                    'updated_at' => now(),
                ]);
            abort_unless($changed === 1, 409);

            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'edicion_catalogo_documental',
                'entidad' => 'tipo_documento_salida',
                'entidad_id' => $format,
                'valor_anterior' => json_encode($before, JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?: null,
                    'activo' => true,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.output-formats.index')->with('success', 'Formato de salida actualizado.');
    }
}
