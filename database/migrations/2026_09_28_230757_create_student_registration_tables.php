<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('dni', 8)->nullable()->unique();
            $table->string('celular', 20)->nullable();
            $table->string('correo_alternativo', 190)->nullable()->unique();
        });

        Schema::create('programas_estudio', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 160)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('perfiles_estudiante', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('programa_estudio_id')->constrained('programas_estudio');
            $table->string('codigo_estudiante', 40)->nullable()->unique();
            $table->string('condicion_academica', 20);
            $table->unsignedTinyInteger('ciclo_actual')->nullable();
            $table->unsignedSmallInteger('anio_egreso')->nullable();
            $table->string('direccion_residencia', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfiles_estudiante');
        Schema::dropIfExists('programas_estudio');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['nombres', 'apellidos', 'dni', 'celular', 'correo_alternativo']);
        });
    }
};
