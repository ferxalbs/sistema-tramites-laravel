<?php

namespace Database\Factories;

use App\Models\PerfilDocente;
use App\Models\ProgramaEstudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilDocente>
 */
class PerfilDocenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['rol' => 'docente']),
            'programa_estudio_id' => ProgramaEstudio::factory(),
            'codigo_docente' => null,
            'especialidad' => null,
            'condicion_laboral' => null,
        ];
    }
}
