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
        return DB::table('clasificaciones_expediente')->pluck('nombre', 'codigo')->all();
    }

    /**
     * @return array<string, string>
     */
    public static function activeLabels(): array
    {
        return DB::table('clasificaciones_expediente')->where('activo', true)
            ->orderBy('orden')->pluck('nombre', 'codigo')->all();
    }
}
