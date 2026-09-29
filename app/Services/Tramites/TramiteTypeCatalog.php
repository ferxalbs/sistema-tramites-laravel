<?php

namespace App\Services\Tramites;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TramiteTypeCatalog
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return DB::table('tipos_tramite')->pluck('nombre', 'codigo')->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function activeByClassification(): array
    {
        $types = DB::table('tipos_tramite')->where('activo', true)
            ->orderBy('nombre')->get(['codigo', 'nombre', 'clasificacion_sugerida']);
        $byClassification = [];

        foreach (array_keys(TramiteClassificationCatalog::activeLabels()) as $classification) {
            $byClassification[$classification] = [];
            foreach ($types as $type) {
                if (! is_string($type->codigo) || ! is_string($type->nombre)) {
                    throw new RuntimeException('Tipo de trámite inválido.');
                }

                if ($type->clasificacion_sugerida === null || $type->clasificacion_sugerida === $classification) {
                    $byClassification[$classification][$type->codigo] = $type->nombre;
                }
            }
        }

        return $byClassification;
    }
}
