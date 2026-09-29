<?php

namespace Database\Seeders;

use App\Models\ProgramaEstudio;
use Illuminate\Database\Seeder;

class ProgramaEstudioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'COMPUTACION_INFORMATICA' => 'Computación e Informática',
            'DESARROLLO_SISTEMAS_INFORMACION' => 'Desarrollo de Sistemas de Información',
        ] as $codigo => $nombre) {
            ProgramaEstudio::query()->updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'descripcion' => 'Programa administrable inicial.',
                'activo' => true,
            ]);
        }
    }
}
