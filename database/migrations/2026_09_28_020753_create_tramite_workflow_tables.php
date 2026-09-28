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
        Schema::create('tramite_secuencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('clasificacion');
            $table->string('tipo_documento');
            $table->string('persona_nombre');
            $table->string('persona_identificador')->nullable();
            $table->string('destino_tipo');
            $table->string('destino_nombre');
            $table->string('asunto');
            $table->text('descripcion')->nullable();
            $table->string('prioridad')->default('normal');
            $table->date('fecha_recepcion');
            $table->unsignedSmallInteger('folios')->nullable();
            $table->string('estado')->default('recibido_oficina');
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_recepcion']);
            $table->index(['clasificacion', 'tipo_documento']);
        });

        Schema::create('tramite_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('categoria')->default('documento_original');
            $table->string('disco')->default('local');
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime_type');
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('sha256', 64);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('vigente')->default(true);
            $table->foreignId('cargado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tramite_id', 'vigente']);
        });

        Schema::create('tramite_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion');
            $table->text('descripcion');
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->json('metadatos')->nullable();
            $table->timestamps();

            $table->index(['tramite_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_eventos');
        Schema::dropIfExists('tramite_documentos');
        Schema::dropIfExists('tramites');
        Schema::dropIfExists('tramite_secuencias');
    }
};
