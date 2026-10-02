<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\TramiteBorrador;
use App\Models\TramitePlantilla;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TramiteBorrador>
 */
class TramiteBorradorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tramite_id' => Tramite::factory(),
            'plantilla_id' => TramitePlantilla::factory(),
            'version_plantilla' => 1,
            'version' => 1,
            'remitente_id' => User::factory()->state(['rol' => 'administrador', 'activo' => true]),
            'firmante_id' => User::factory()->state(['rol' => 'docente', 'activo' => true]),
            'fecha_documento' => now()->toDateString(),
            'lugar' => 'Lima',
            'asunto' => fake()->sentence(6),
            'introduccion' => fake()->paragraph(),
            'contenido_principal' => fake()->paragraphs(2, true),
            'cierre' => 'Atentamente.',
            'destinatarios' => [['nombres' => fake()->name(), 'principal' => true]],
            'personas_mencionadas' => [],
            'adjuntos' => [],
            'estado' => 'preparado_asignacion',
            'es_actual' => true,
            'preparado_en' => now(),
            'creado_por' => User::factory()->state(['rol' => 'administrador', 'activo' => true]),
        ];
    }
}
