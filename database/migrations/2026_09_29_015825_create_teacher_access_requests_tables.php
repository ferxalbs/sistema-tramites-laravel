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
        Schema::create('cargos_institucionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 140);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('cargos_institucionales')->insert([
            ['codigo' => 'director_general', 'nombre' => 'Director General', 'activo' => true],
            ['codigo' => 'coordinador_academico', 'nombre' => 'Coordinador Académico', 'activo' => true],
            ['codigo' => 'docente', 'nombre' => 'Docente', 'activo' => true],
            ['codigo' => 'asistente_laboratorio', 'nombre' => 'Asistente de laboratorio', 'activo' => true],
            ['codigo' => 'otro', 'nombre' => 'Otro', 'activo' => true],
        ]);

        Schema::create('teacher_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('cargo_institucional_id')->constrained('cargos_institucionales')->restrictOnDelete();
            $table->string('motivo', 500);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_access_requests');
        Schema::dropIfExists('cargos_institucionales');
    }
};
