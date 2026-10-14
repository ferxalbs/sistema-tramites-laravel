<?php

namespace App\Services\Tramites;

use App\Models\TramiteObservacionRevision;
use App\Models\TramiteRondaRevision;

final class TramiteReviewSummary
{
    /** @return array<string, mixed> */
    public static function make(TramiteRondaRevision $ronda): array
    {
        return [
            'numero' => $ronda->numero_ronda,
            'estado' => $ronda->estado,
            'revisor' => $ronda->revisor?->name,
            'version' => $ronda->versionBorrador?->version,
            'resumen_observacion' => $ronda->resumen_observacion,
            'resumen_correccion' => $ronda->resumen_correccion,
            'conclusion' => $ronda->conclusion,
            'comentario_publico' => $ronda->comentario_publico,
            'comentario_interno' => $ronda->comentario_interno,
            'observaciones' => $ronda->observaciones->map(self::observation(...))->all(),
        ];
    }

    /** @return array{id: int, categoria: string, titulo: string, descripcion: string, seccion: ?string, obligatoria: bool, visible_para_interesado: bool, respuesta: ?string} */
    private static function observation(TramiteObservacionRevision $observacion): array
    {
        return [
            'id' => $observacion->id,
            'categoria' => $observacion->categoria,
            'titulo' => $observacion->titulo,
            'descripcion' => $observacion->descripcion,
            'seccion' => $observacion->seccion,
            'obligatoria' => $observacion->obligatoria,
            'visible_para_interesado' => $observacion->visible_para_interesado,
            'respuesta' => $observacion->respuesta?->respuesta,
        ];
    }
}
