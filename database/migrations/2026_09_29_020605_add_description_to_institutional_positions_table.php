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
        Schema::table('cargos_institucionales', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable();
        });

        foreach ([
            'director_general' => 'Cargo institucional; no concede permisos del sistema.',
            'coordinador_academico' => 'Cargo institucional; no concede permisos del sistema.',
            'docente' => 'Cargo institucional; independiente del rol del sistema.',
            'asistente_laboratorio' => 'Cargo institucional; independiente del rol del sistema.',
            'otro' => 'Cargo institucional configurable.',
        ] as $code => $description) {
            DB::table('cargos_institucionales')->where('codigo', $code)->update(['descripcion' => $description]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cargos_institucionales', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
