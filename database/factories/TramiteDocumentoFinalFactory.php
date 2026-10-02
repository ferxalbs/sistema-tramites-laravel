<?php

namespace Database\Factories;

use App\Models\Tramite;
use App\Models\TramiteBorrador;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TramiteDocumentoFinal>
 */
class TramiteDocumentoFinalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tramite_id' => Tramite::factory()->state(['estado' => 'aprobado']),
            'numeracion_id' => TramiteNumeracionDocumental::factory(),
            'borrador_id' => TramiteBorrador::factory(),
            'ronda_revision_id' => TramiteRondaRevision::factory(),
            'version' => 1,
            'tipo_documento' => 'informe',
            'numero_documento' => 'PRU-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'codigo_verificacion' => implode('-', str_split(Str::upper(Str::random(16)), 4)),
            'estado' => 'generando',
            'activo' => true,
            'disco' => 'local',
            'ruta' => null,
            'nombre_archivo' => null,
            'mime_type' => 'application/pdf',
            'sha256' => null,
            'tamano_bytes' => null,
            'numero_paginas' => null,
            'contenido_snapshot' => [],
            'generado_por' => User::factory()->state(['rol' => 'administrador', 'activo' => true]),
            'fecha_emision' => null,
            'error_generacion' => null,
        ];
    }
}
