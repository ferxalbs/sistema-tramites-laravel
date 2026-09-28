<?php

namespace Database\Seeders;

use App\Models\TramiteSerieDocumental;
use Illuminate\Database\Seeder;

class TramiteSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('tramites.series_documentales', []) as $serie) {
            TramiteSerieDocumental::query()->firstOrCreate(
                [
                    'tipo_documento_salida' => $serie['tipo_documento_salida'],
                    'modalidad' => $serie['modalidad'],
                    'anio' => (int) now()->format('Y'),
                ],
                [
                    'codigo' => $serie['codigo'],
                    'prefijo' => $serie['prefijo'],
                    'ultimo_correlativo' => 0,
                    'activa' => true,
                ],
            );
        }
    }
}
