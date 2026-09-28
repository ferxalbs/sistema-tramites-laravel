<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\TramiteBorrador;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramiteRondaRevision;
use App\Models\TramiteSerieDocumental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TramiteNumeracionDocumental>
 */
class TramiteNumeracionDocumentalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serie_id' => TramiteSerieDocumental::factory(),
            'tramite_id' => Tramite::factory()->state(['estado' => 'aprobado']),
            'borrador_id' => TramiteBorrador::factory(),
            'ronda_revision_id' => TramiteRondaRevision::factory(),
            'anio' => (int) now()->format('Y'),
            'correlativo' => fake()->unique()->numberBetween(1, 999999),
            'numero_completo' => 'PRU-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'estado' => 'reservada',
            'reservada_por' => User::factory()->state(['rol' => 'asistente', 'activo' => true]),
            'error_generacion' => null,
        ];
    }
}
