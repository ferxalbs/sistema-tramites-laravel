<?php

namespace App\Http\Controllers;

use App\Services\Tramites\TramiteTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteTypeController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('tipos-tramite', [
            'tipos' => DB::table('tipos_tramite')
                ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'descripcion', 'clasificacion_sugerida', 'es_demostracion', 'activo'])
                ->map(function (object $type): object {
                    if ($type->clasificacion_sugerida === 'institucional') {
                        $type->clasificacion_sugerida = 'administrativo';
                    }

                    return $type;
                }),
        ]);
    }

    public function update(Request $request, int $type): RedirectResponse
    {
        abort_unless(DB::table('tipos_tramite')->where('id', $type)
            ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())->exists(), 404);

        $request->merge([
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')),
        ]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:140', Rule::unique('tipos_tramite', 'nombre')->ignore($type)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $type, $data): void {
            $before = DB::table('tipos_tramite')->where('id', $type)
                ->first(['nombre', 'descripcion', 'activo']);
            abort_if($before === null, 404);

            $changed = DB::table('tipos_tramite')->where('id', $type)
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
                'entidad' => 'tipo_tramite',
                'entidad_id' => $type,
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

        return to_route('admin.types.index')->with('success', 'Tipo de trámite actualizado.');
    }
}
