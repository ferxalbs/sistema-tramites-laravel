<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipos_tramite', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 140)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('clasificacion_sugerida', 50)->nullable();
            $table->boolean('es_demostracion')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'nombre']);
        });

        $now = now();
        $types = [
            ['FUT', 'FUT', null, 'Tipo de trámite demostrativo y modificable.'],
            ['JUSTIFICACION', 'Justificación', 'estudiantil', 'Tipo de trámite demostrativo y modificable.'],
            ['CONSTANCIA_PRACTICA', 'Constancia de práctica', null, 'Tipo de trámite demostrativo y modificable.'],
            ['SOLICITUD_GENERAL', 'Solicitud general', null, 'Tipo de trámite demostrativo y modificable.'],
            ['JUSTIFICACION_TARDANZA', 'Justificación por tardanza', 'estudiantil', 'Tipo provisional y configurable.'],
            ['AUTORIZACION_INGRESO', 'Autorización de ingreso', 'administrativo', 'Tipo provisional y configurable.'],
            ['REQUERIMIENTO_EQUIPAMIENTO', 'Requerimiento de equipamiento', 'institucional', 'Tipo provisional y configurable.'],
            ['COMUNICACION_ADMINISTRATIVA', 'Comunicación administrativa', 'administrativo', 'Tipo provisional y configurable.'],
        ];
        DB::table('tipos_tramite')->insert(array_map(fn (array $type): array => [
            'codigo' => $type[0],
            'nombre' => $type[1],
            'clasificacion_sugerida' => $type[2],
            'descripcion' => $type[3],
            'es_demostracion' => true,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $types));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_tramite');
    }
};
