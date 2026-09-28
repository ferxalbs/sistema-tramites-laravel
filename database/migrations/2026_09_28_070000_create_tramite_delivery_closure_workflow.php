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
        Schema::create('tramite_medios_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 100);
            $table->string('tipo', 20);
            $table->boolean('requiere_evidencia')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tramite_firmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_final_id')->unique()->constrained('tramite_documentos_finales')->restrictOnDelete();
            $table->foreignId('firmante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('no_requiere_firma')->default(false);
            $table->timestamp('fecha_firma')->nullable();
            $table->text('observacion')->nullable();
            $table->string('disco', 30)->nullable();
            $table->string('ruta')->nullable();
            $table->string('nombre_original')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['tramite_id', 'created_at']);
        });

        Schema::create('tramite_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_final_id')->constrained('tramite_documentos_finales')->restrictOnDelete();
            $table->foreignId('medio_entrega_id')->constrained('tramite_medios_entrega')->restrictOnDelete();
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receptor_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receptor_nombre', 160);
            $table->string('receptor_documento', 20)->nullable();
            $table->string('receptor_tipo', 40);
            $table->string('receptor_relacion', 160)->nullable();
            $table->string('correo_destino', 255)->nullable();
            $table->string('medio_utilizado', 255)->nullable();
            $table->timestamp('fecha_entrega');
            $table->boolean('confirmado_por_estudiante')->default(false);
            $table->boolean('confirmado')->default(false);
            $table->string('codigo_confirmacion', 24)->unique();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('registrada');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['tramite_id', 'estado', 'activa']);
            $table->index(['fecha_entrega', 'confirmado']);
        });

        DB::statement('CREATE UNIQUE INDEX tramite_entregas_una_activa ON tramite_entregas (tramite_id) WHERE activa = 1');

        Schema::create('tramite_evidencias_entrega', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('tramite_entregas')->restrictOnDelete();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('documento_final_id')->constrained('tramite_documentos_finales')->restrictOnDelete();
            $table->string('tipo_evidencia', 60);
            $table->string('nombre_original')->nullable();
            $table->string('disco', 30)->nullable();
            $table->string('ruta')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('codigo_confirmacion', 24)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['entrega_id', 'activa']);
        });

        Schema::create('tramite_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->restrictOnDelete();
            $table->foreignId('entrega_id')->unique()->constrained('tramite_entregas')->restrictOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resumen', 2000);
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_cierre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tramite_informes_cierre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('cierre_id')->unique()->constrained('tramite_cierres')->restrictOnDelete();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disco', 30)->default('local');
            $table->string('ruta');
            $table->string('nombre_archivo');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('tamano_bytes');
            $table->unsignedSmallInteger('numero_paginas');
            $table->string('codigo_verificacion', 24)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tramite_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_informes_cierre');
        Schema::dropIfExists('tramite_cierres');
        Schema::dropIfExists('tramite_evidencias_entrega');
        Schema::dropIfExists('tramite_entregas');
        DB::statement('DROP INDEX IF EXISTS tramite_entregas_una_activa');
        Schema::dropIfExists('tramite_firmas');
        Schema::dropIfExists('tramite_medios_entrega');
    }
};
