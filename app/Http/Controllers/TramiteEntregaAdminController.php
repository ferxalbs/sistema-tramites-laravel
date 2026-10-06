<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\TramiteMedioEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteEntregaAdminController extends Controller
{
    public function index(): InertiaResponse
    {
        $tramites = Tramite::query()
            ->whereHas('documentoFinalActual', fn (Builder $documentos): Builder => $documentos->where('estado', 'emitido'))
            ->with(['documentoFinalActual', 'entregaActual.medio'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Tramite $tramite): array => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'documento' => $tramite->documentoFinalActual->numero_documento,
                'estado' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'medio' => $tramite->entregaActual?->medio?->nombre,
                'entrega_id' => $tramite->entregaActual?->id,
                'confirmado' => $tramite->entregaActual?->confirmado,
            ]);

        return Inertia::render('tramites/entregas-admin', [
            'tramites' => $tramites,
            'medios' => TramiteMedioEntrega::query()
                ->whereIn('codigo', TramiteMedioEntrega::CODIGOS_DISPONIBLES)
                ->orderBy('id')
                ->get([
                    'id', 'codigo', 'nombre', 'tipo', 'requiere_evidencia',
                ]),
        ]);
    }

    public function updateMedium(Request $request, TramiteMedioEntrega $medio): RedirectResponse
    {
        abort_unless(in_array($medio->codigo, TramiteMedioEntrega::CODIGOS_DISPONIBLES, true), 404);
        $datos = $request->validate([
            'requiere_evidencia' => ['required', 'boolean'],
        ]);
        $nuevo = ['requiere_evidencia' => (bool) $datos['requiere_evidencia']];

        DB::transaction(function () use ($request, $medio, $nuevo): void {
            $actual = DB::table('tramite_medios_entrega')->where('id', $medio->id)->first(['requiere_evidencia']);
            abort_unless($actual !== null, 404);
            $anterior = ['requiere_evidencia' => (bool) $actual->requiere_evidencia];

            if ($anterior === $nuevo) {
                return;
            }

            $actualizado = DB::table('tramite_medios_entrega')
                ->where('id', $medio->id)
                ->where('requiere_evidencia', $anterior['requiere_evidencia'])
                ->update([...$nuevo, 'updated_at' => now()]);
            abort_unless($actualizado === 1, 409);

            DB::table('tramite_config_events')->insert([
                'actor_id' => $request->user()->id,
                'accion' => 'configurar_medio',
                'entidad' => 'medio_entrega',
                'entidad_id' => $medio->id,
                'valor_anterior' => json_encode($anterior, JSON_THROW_ON_ERROR),
                'valor_nuevo' => json_encode($nuevo, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('admin.deliveries.index')->with('success', 'Medio de entrega actualizado.');
    }
}
