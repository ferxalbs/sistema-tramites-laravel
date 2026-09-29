<?php

namespace Database\Factories;

use App\Models\ProgramaEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramaEstudio>
 */
class ProgramaEstudioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PROGRAMA_##??'),
            'nombre' => fake()->unique()->words(3, true),
            'descripcion' => null,
            'activo' => true,
        ];
    }
}
