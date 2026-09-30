<?php

namespace App\Services\Tramites;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TramiteTypeCatalog
{
    /** @var list<string> */
    private const EXCLUDED_CODES = [
        'FUT',
        'AUTORIZACION_INGRESO',
        'COMUNICACION_ADMINISTRATIVA',
        'SOLICITUD_GENERAL',
        'JUSTIFICACION',
    ];

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
            ->whereNotIn('codigo', self::EXCLUDED_CODES)
            ->orderBy('nombre')->get(['codigo', 'nombre', 'clasificacion_sugerida']);
        $byClassification = [];

        foreach (array_keys(TramiteClassificationCatalog::activeLabels()) as $classification) {
            $byClassification[$classification] = [];
            foreach ($types as $type) {
                if (! is_string($type->codigo) || ! is_string($type->nombre)) {
                    throw new RuntimeException('Tipo de trámite inválido.');
                }

                $suggestedClassification = $type->clasificacion_sugerida === 'institucional'
                    ? 'administrativo'
                    : $type->clasificacion_sugerida;

                if ($suggestedClassification === null || $suggestedClassification === $classification) {
                    $byClassification[$classification][$type->codigo] = $type->nombre;
                }
            }
        }

        return $byClassification;
    }

    /** @return array<string, string> */
    public static function activeLabels(): array
    {
        return DB::table('tipos_tramite')->where('activo', true)
            ->whereNotIn('codigo', self::EXCLUDED_CODES)
            ->orderBy('nombre')->pluck('nombre', 'codigo')->all();
    }

    /** @return list<string> */
    public static function excludedCodes(): array
    {
        return self::EXCLUDED_CODES;
    }
}
