<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tramite>
 */
class TramiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'TRM-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'clasificacion' => 'estudiantil',
            'tipo_documento' => 'FUT',
            'persona_nombre' => fake()->name(),
            'persona_identificador' => fake()->numerify('########'),
            'propietario_id' => User::factory()->state(['rol' => 'estudiante', 'activo' => true]),
            'destino_tipo' => 'oficina',
            'destino_nombre' => 'Secretaría Académica',
            'asunto' => fake()->sentence(6),
            'descripcion' => fake()->paragraph(),
            'prioridad' => 'normal',
            'fecha_recepcion' => now()->toDateString(),
            'folios' => fake()->numberBetween(1, 20),
            'estado' => 'digitalizado',
            'recibido_por' => User::factory()->state(['rol' => 'asistente', 'activo' => true]),
        ];
    }
}
