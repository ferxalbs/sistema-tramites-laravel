<?php

namespace Database\Factories;

use App\Models\TramitePlantilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TramitePlantilla>
 */
class TramitePlantillaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PRUEBA-####'),
            'version' => 1,
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'tipo_documento_salida' => 'informe',
            'modalidad' => null,
            'contenido' => 'Asunto: {{ASUNTO}}. {{CONTENIDO}}',
            'requiere_firma_fisica' => false,
            'permite_no_firma' => true,
            'estado' => 'publicada',
            'activa' => true,
        ];
    }
}
