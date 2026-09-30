<?php

namespace Database\Factories;

use App\Models\PerfilEstudiante;
use App\Models\ProgramaEstudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilEstudiante>
 */
class PerfilEstudianteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'programa_estudio_id' => ProgramaEstudio::factory(),
            'codigo_estudiante' => null,
            'condicion_academica' => 'Estudiante',
            'ciclo_actual' => fake()->numberBetween(1, 6),
            'anio_egreso' => null,
            'direccion_residencia' => null,
        ];
    }
}
