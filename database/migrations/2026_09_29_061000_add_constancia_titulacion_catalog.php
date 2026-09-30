<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('tipos_documento_salida')->insertOrIgnore([
            'codigo' => 'constancia',
            'nombre' => 'Constancia',
            'descripcion' => 'Documento que acredita un hecho o condición tras la revisión del expediente.',
            'permite_modalidad_multiple' => false,
            'activo' => true,
            'orden' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('tipos_tramite')->insertOrIgnore([
            'codigo' => 'SOLICITUD_CONSTANCIA_MODALIDAD_TITULACION',
            'nombre' => 'Solicitud de constancia de modalidad de examen de titulación',
            'descripcion' => 'Solicitud de constancia de la modalidad elegida para el examen de titulación.',
            'clasificacion_sugerida' => 'estudiantil',
            'tipo_documento_salida_sugerido' => 'constancia',
            'es_demostracion' => false,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Conservar catálogos que puedan estar asociados a expedientes y documentos.
    }
};
