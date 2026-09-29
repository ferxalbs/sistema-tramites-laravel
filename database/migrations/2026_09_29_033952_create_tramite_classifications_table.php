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
        Schema::create('clasificaciones_expediente', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('requiere_estudiante')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden');
            $table->timestamps();
            $table->index(['activo', 'orden']);
        });

        $now = now();
        DB::table('clasificaciones_expediente')->insert([
            ['codigo' => 'estudiantil', 'nombre' => 'Estudiantil', 'descripcion' => 'Expediente relacionado obligatoriamente con un estudiante o egresado.', 'requiere_estudiante' => true, 'activo' => true, 'orden' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'administrativo', 'nombre' => 'Administrativo', 'descripcion' => 'Acción administrativa u operativa que no exige estudiante.', 'requiere_estudiante' => false, 'activo' => true, 'orden' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'institucional', 'nombre' => 'Institucional', 'descripcion' => 'Requerimiento, proyecto o comunicación de alcance institucional.', 'requiere_estudiante' => false, 'activo' => true, 'orden' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificaciones_expediente');
    }
};
