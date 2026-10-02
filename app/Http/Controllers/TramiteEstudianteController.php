<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\TramiteEvento;
use App\Models\TramiteObservacionRevision;
use App\Models\TramiteRondaRevision;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $puedeDescargarDocumento = $documentoFinal !== null
            && $entrega !== null
            && (int) $entrega->documento_final_id === (int) $documentoFinal->id
            && in_array($entrega->estado, ['registrada', 'confirmada'], true);
        $informeCierre = $tramite->informeCierre()->first();

        $hitosPublicos = [
            'recepcion' => ['Expediente recibido', 'La solicitud fue recibida en la oficina.'],
            'digitalizacion' => ['Documento digitalizado', 'El documento recibido fue digitalizado.'],
            'documento_final_emitido' => ['Documento final generado', 'Se generó el documento final oficial.'],
            'firma_registrada' => ['Firma registrada', 'La firma del documento fue registrada.'],
            'entrega_registrada' => ['Entrega registrada', 'El documento quedó registrado para entrega.'],
            'recepcion_confirmada' => ['Recepción confirmada', 'La recepción del documento fue confirmada.'],
            'expediente_cerrado' => ['Expediente cerrado', 'El expediente fue cerrado después de completar la entrega.'],
        ];
        $estadosPublicos = config('tramites.estados', []);
        $historial = [];
        $hayRecepcion = false;
        $firmasSinFirma = DB::table('tramite_firmas')->where('tramite_id', $tramite->id)
            ->where('no_requiere_firma', true)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach ($tramite->eventos()->orderBy('created_at')->orderBy('id')->get(['id', 'accion', 'estado_anterior', 'estado_nuevo', 'metadatos', 'created_at']) as $evento) {
            $accion = (string) $evento->getAttribute('accion');
            $hito = $hitosPublicos[$accion] ?? null;
            if ($accion === 'firma_registrada'
                && in_array((int) ($evento->metadatos['firma_id'] ?? 0), $firmasSinFirma, true)) {
                $hito = ['Firma no requerida', 'El documento quedó habilitado sin firma física.'];
            }
            $estado = $evento->estado_nuevo;

            if ($hito === null && (! is_string($estado) || ! isset($estadosPublicos[$estado]) || $estado === $evento->estado_anterior)) {
                continue;
            }

            $hayRecepcion = $hayRecepcion || $accion === 'recepcion';
            $titulo = $hito[0] ?? $estadosPublicos[$estado];
            $historial[] = [
                'estado' => $estado ?? $accion,
                'label' => $titulo,
                'descripcion' => $hito[1] ?? 'El expediente pasó a '.$titulo.'.',
                'fecha' => $evento->created_at?->toIso8601String(),
                'orden' => 10,
                'id' => $evento->id,
            ];
        }

        if (! $hayRecepcion) {
            $fechaLlegada = $tramite->getRawOriginal('fecha_llegada_oficina');
            $historial[] = [
                'estado' => 'recibido_oficina',
                'label' => 'Expediente recibido',
                'descripcion' => 'La solicitud fue recibida en la oficina.',
                'fecha' => is_string($fechaLlegada)
                    ? Carbon::parse($fechaLlegada)->toIso8601String()
                    : $tramite->fecha_recepcion->startOfDay()->toIso8601String(),
                'orden' => 1,
                'id' => 0,
            ];
        }

        foreach (TramiteObservacionRevision::query()
            ->where('tramite_id', $tramite->id)
            ->where('visible_para_interesado', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'descripcion', 'created_at']) as $observacion) {
            $historial[] = [
                'estado' => 'observacion',
                'label' => 'Observación de revisión',
                'descripcion' => $observacion->descripcion,
                'fecha' => $observacion->created_at?->toIso8601String(),
                'orden' => 30,
                'id' => $observacion->id,
            ];
        }

        usort($historial, static fn (array $a, array $b): int => [$a['fecha'], $a['orden'], $a['id']] <=> [$b['fecha'], $b['orden'], $b['id']]);
        $historial = array_map(static fn (array $hito): array => array_intersect_key($hito, array_flip(['estado', 'label', 'descripcion', 'fecha'])), $historial);

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
                ...($puedeDescargarDocumento ? [
                    'url_descarga' => route('tramites.documento-final.descargar', [$tramite->id, $documentoFinal->id]),
                ] : []),
            ],
            'documentos_recepcion' => $tramite->documentos()->orderBy('id')->get(['id', 'nombre_original', 'categoria', 'version', 'vigente'])
                ->map(fn ($documento): array => [
                    'id' => $documento->id,
                    'nombre' => $documento->nombre_original,
                    'categoria' => $documento->categoria,
                    'version' => $documento->version,
                    'vigente' => $documento->vigente,
                ])->all(),
            'entrega' => $entrega === null ? null : [
                'medio' => $entrega->medio->nombre,
                'receptor_nombre' => $entrega->receptor_nombre,
                'receptor_documento' => $entrega->receptor_documento,
                'receptor_tipo' => $entrega->receptor_tipo,
                'fecha_entrega' => $entrega->fecha_entrega?->toIso8601String(),
                'confirmado' => $entrega->confirmado,
            ],
            'puede_confirmar_entrega' => $tramite->estado === 'listo_entrega'
                && $entrega !== null
                && $entrega->estado === 'registrada'
                && ! $entrega->confirmado,
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
            'historial' => $historial,
        ]);
    }
}
