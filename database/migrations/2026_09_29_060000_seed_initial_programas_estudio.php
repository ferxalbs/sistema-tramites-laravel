<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $programas = [
            'COMPUTACION_INFORMATICA' => 'Computación e Informática',
            'DESARROLLO_SISTEMAS_INFORMACION' => 'Desarrollo de Sistemas de Información',
        ];

        foreach ($programas as $codigo => $nombre) {
            if (DB::table('programas_estudio')->where('codigo', $codigo)->orWhere('nombre', $nombre)->exists()) {
                continue;
            }

            DB::table('programas_estudio')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => 'Programa inicial configurable.',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Conservar programas que puedan estar asociados a perfiles existentes.
    }
};
