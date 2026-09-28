<?php

namespace Database\Seeders;

use App\Models\TramiteMedioEntrega;
use Illuminate\Database\Seeder;

class TramiteMediosEntregaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['codigo' => 'presencial', 'nombre' => 'Presencial', 'tipo' => 'presencial', 'requiere_evidencia' => true],
            ['codigo' => 'correo_electronico', 'nombre' => 'Correo electrónico', 'tipo' => 'digital', 'requiere_evidencia' => false],
            ['codigo' => 'descarga_sistema', 'nombre' => 'Descarga desde el sistema', 'tipo' => 'digital', 'requiere_evidencia' => false],
            ['codigo' => 'otro_medio', 'nombre' => 'Otro medio autorizado', 'tipo' => 'otro', 'requiere_evidencia' => true],
        ] as $medio) {
            TramiteMedioEntrega::query()->updateOrCreate(
                ['codigo' => $medio['codigo']],
                [...$medio, 'activo' => true],
            );
        }
    }
}
