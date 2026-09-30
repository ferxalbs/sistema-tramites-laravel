<?php

namespace App\Services\Tramites;

use Illuminate\Support\Facades\DB;

class TramiteClassificationCatalog
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = DB::table('clasificaciones_expediente')->pluck('nombre', 'codigo')->all();

        if (array_key_exists('institucional', $labels)) {
            $labels['institucional'] = $labels['administrativo'] ?? 'Administrativo';
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public static function activeLabels(): array
    {
        return DB::table('clasificaciones_expediente')->where('activo', true)
            ->where('codigo', '<>', 'institucional')
            ->orderBy('orden')->pluck('nombre', 'codigo')->all();
    }
}
