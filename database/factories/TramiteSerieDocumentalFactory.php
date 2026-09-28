<?php

namespace Database\Factories;

use App\Models\TramiteSerieDocumental;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TramiteSerieDocumental>
 */
class TramiteSerieDocumentalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_documento_salida' => 'fixture-'.Str::lower(Str::random(8)),
            'modalidad' => 'unica',
            'anio' => (int) now()->format('Y'),
            'codigo' => 'PRUEBA-'.Str::upper(Str::random(8)),
            'prefijo' => 'PRU',
            'ultimo_correlativo' => 0,
            'activa' => true,
        ];
    }
}
