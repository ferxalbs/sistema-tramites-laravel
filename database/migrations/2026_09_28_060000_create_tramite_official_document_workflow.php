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
        Schema::create('tramite_series_documentales', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento_salida', 40);
            $table->string('modalidad', 20);
            $table->unsignedSmallInteger('anio');
            $table->string('codigo', 40);
            $table->string('prefijo', 40);
            $table->unsignedInteger('ultimo_correlativo')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['tipo_documento_salida', 'modalidad', 'anio']);
            $table->index(['anio', 'activa']);
        });

        Schema::create('tramite_numeraciones_documentales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serie_id')->constrained('tramite_series_documentales')->restrictOnDelete();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('borrador_id')->constrained('tramite_borradores')->restrictOnDelete();
            $table->foreignId('ronda_revision_id')->constrained('tramite_rondas_revision')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('correlativo');
            $table->string('numero_completo', 120)->unique();
            $table->string('estado', 20)->default('reservada');
            $table->foreignId('reservada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_generacion')->nullable();
            $table->timestamps();

            $table->unique(['serie_id', 'anio', 'correlativo']);
            $table->index(['tramite_id', 'estado']);
        });

        Schema::create('tramite_documentos_finales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('numeracion_id')->unique()->constrained('tramite_numeraciones_documentales')->restrictOnDelete();
            $table->foreignId('borrador_id')->constrained('tramite_borradores')->restrictOnDelete();
            $table->foreignId('ronda_revision_id')->constrained('tramite_rondas_revision')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('tipo_documento', 40);
            $table->string('numero_documento', 120)->unique();
            $table->string('codigo_verificacion', 24)->unique();
            $table->string('estado', 20)->default('generando');
            $table->boolean('activo')->default(true);
            $table->string('disco', 30)->default('local');
            $table->string('ruta')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->string('mime_type', 100)->default('application/pdf');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->unsignedSmallInteger('numero_paginas')->nullable();
            $table->json('contenido_snapshot');
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_emision')->nullable();
            $table->text('error_generacion')->nullable();
            $table->timestamps();

            $table->unique(['tramite_id', 'version']);
            $table->index(['tramite_id', 'estado', 'activo']);
        });

        DB::statement("CREATE UNIQUE INDEX tramite_documentos_finales_una_vigente ON tramite_documentos_finales (tramite_id) WHERE activo = 1 AND estado IN ('generando', 'emitido')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_documentos_finales');
        Schema::dropIfExists('tramite_numeraciones_documentales');
        Schema::dropIfExists('tramite_series_documentales');
    }
};
