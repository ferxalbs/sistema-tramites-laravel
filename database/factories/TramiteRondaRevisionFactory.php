<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TramiteRondaRevision>
 */
class TramiteRondaRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tramite_id' => Tramite::factory()->state(['estado' => 'en_revision']),
            'asignacion_id' => TramiteAsignacion::factory(),
            'numero_ronda' => 1,
            'revisor_id' => User::factory()->state(['rol' => 'docente', 'activo' => true]),
            'borrador_id' => TramiteBorrador::factory(),
            'estado' => 'en_revision',
            'activa' => true,
            'iniciada_en' => now(),
            'cerrada_en' => null,
            'resumen_observacion' => null,
            'resumen_correccion' => null,
            'conclusion' => null,
            'comentario_publico' => null,
            'comentario_interno' => null,
        ];
    }
}
