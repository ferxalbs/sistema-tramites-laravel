<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\TramiteEvento;
use App\Models\TramiteRondaRevision;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TramiteEstudianteController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $tramites = Tramite::query()
            ->where('propietario_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Tramite $tramite): array => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'actualizado_en' => $tramite->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('tramites/estudiante-index', [
            'tramites' => $tramites,
        ]);
    }

    public function show(Request $request, Tramite $tramite): InertiaResponse
    {
        if ((int) $tramite->propietario_id !== (int) $request->user()->id) {
            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $request->user()->id,
                'accion' => 'acceso_tramite_no_autorizado',
                'descripcion' => 'Se rechazó un acceso a un trámite que pertenece a otra cuenta.',
            ]);

            abort(403);
        }

        $ultimaRonda = TramiteRondaRevision::query()
            ->with(['observaciones' => fn ($query) => $query->where('visible_para_interesado', true)])
            ->where('tramite_id', $tramite->id)
            ->whereIn('estado', ['observada', 'corregida'])
            ->whereHas('observaciones', fn ($query) => $query->where('visible_para_interesado', true))
            ->orderByDesc('numero_ronda')
            ->first();
        $documentoFinal = $tramite->documentoFinalActual()->where('estado', 'emitido')->first();
        $entrega = $tramite->entregaActual()->with('medio')->first();
        $informeCierre = $tramite->informeCierre()->first();

        $eventos = $tramite->eventos()
            ->whereNotNull('estado_nuevo')
            ->orderBy('id')
            ->get(['estado_nuevo', 'created_at'])
            ->map(fn (TramiteEvento $evento): array => [
                'estado' => $evento->estado_nuevo,
                'label' => config('tramites.estados.'.$evento->estado_nuevo, 'Trámite actualizado'),
                'fecha' => $evento->created_at?->toIso8601String(),
            ])->all();

        return Inertia::render('tramites/estudiante-show', [
            'tramite' => [
                'id' => $tramite->id,
                'codigo' => $tramite->codigo,
                'asunto' => $tramite->asunto,
                'fecha_recepcion' => $tramite->fecha_recepcion->toDateString(),
                'estado' => $tramite->estado,
                'estado_label' => config('tramites.estados.'.$tramite->estado, $tramite->estado),
                'actualizado_en' => $tramite->updated_at?->toIso8601String(),
            ],
            'documento_final' => $documentoFinal === null ? null : [
                'numero' => $documentoFinal->numero_documento,
                'url_descarga' => route('tramites.documento-final.descargar', [$tramite->id, $documentoFinal->id]),
            ],
            'entrega' => $entrega === null ? null : [
                'medio' => $entrega->medio->nombre,
                'receptor_nombre' => $entrega->receptor_nombre,
                'receptor_documento' => $entrega->receptor_documento,
                'receptor_tipo' => $entrega->receptor_tipo,
                'fecha_entrega' => $entrega->fecha_entrega?->toIso8601String(),
                'confirmado' => $entrega->confirmado,
            ],
            'puede_confirmar_entrega' => $tramite->estado === 'listo_entrega' && $entrega !== null && ! $entrega->confirmado,
            'informe_cierre' => $informeCierre === null ? null : [
                'numero_paginas' => $informeCierre->numero_paginas,
                'url_descarga' => route('tramites.informes-cierre.descargar', [$tramite->id, $informeCierre->id]),
            ],
            'comentario_publico' => TramiteRondaRevision::query()
                ->where('tramite_id', $tramite->id)
                ->whereNotNull('comentario_publico')
                ->orderByDesc('numero_ronda')
                ->value('comentario_publico'),
            'observaciones_visibles' => $ultimaRonda?->observaciones->map(fn ($observacion): array => [
                'categoria' => $observacion->categoria,
                'titulo' => $observacion->titulo,
                'descripcion' => $observacion->descripcion,
                'seccion' => $observacion->seccion,
                'obligatoria' => $observacion->obligatoria,
            ])->all() ?? [],
            'historial' => $eventos,
        ]);
    }
}
