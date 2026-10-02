<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TramiteAsignacion>
 */
class TramiteAsignacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tramite_id' => Tramite::factory()->state(['estado' => 'pendiente_asignacion']),
            'destino' => 'docente',
            'revisor_id' => User::factory()->state(['rol' => 'docente', 'activo' => true]),
            'rol_revisor' => 'docente',
            'asignado_por' => User::factory()->state(['rol' => 'administrador', 'activo' => true]),
            'motivo' => fake()->sentence(8),
            'instrucciones_revision' => fake()->sentence(),
            'fecha_esperada' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'estado' => 'activa',
            'activa' => true,
        ];
    }
}
