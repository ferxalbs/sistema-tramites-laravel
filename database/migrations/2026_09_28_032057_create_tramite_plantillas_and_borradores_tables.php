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
        Schema::create('tramite_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('nombre', 160);
            $table->text('descripcion')->nullable();
            $table->string('tipo_documento_salida', 40);
            $table->string('modalidad', 40)->nullable();
            $table->text('contenido');
            $table->boolean('requiere_firma_fisica')->default(false);
            $table->boolean('permite_no_firma')->default(false);
            $table->string('estado')->default('publicada');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['codigo', 'version']);
            $table->index(['estado', 'activa', 'tipo_documento_salida', 'modalidad']);
        });

        Schema::create('tramite_borrador_secuencias', function (Blueprint $table) {
            $table->foreignId('tramite_id')->primary()->constrained('tramites')->cascadeOnDelete();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('tramite_borradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('plantilla_id')->constrained('tramite_plantillas');
            $table->unsignedSmallInteger('version_plantilla');
            $table->unsignedInteger('version');
            $table->foreignId('remitente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('firmante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_documento');
            $table->string('lugar', 80)->default('Lima');
            $table->string('asunto', 255);
            $table->text('introduccion')->nullable();
            $table->text('contenido_principal')->nullable();
            $table->text('cierre')->nullable();
            $table->json('destinatarios')->nullable();
            $table->json('personas_mencionadas')->nullable();
            $table->json('adjuntos')->nullable();
            $table->string('estado', 40)->default('incompleto');
            $table->boolean('es_actual')->default(true);
            $table->timestamp('preparado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tramite_id', 'version']);
            $table->index(['tramite_id', 'es_actual']);
        });

        DB::statement('CREATE UNIQUE INDEX tramite_borradores_una_version_actual ON tramite_borradores (tramite_id) WHERE es_actual = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_borradores');
        Schema::dropIfExists('tramite_borrador_secuencias');
        Schema::dropIfExists('tramite_plantillas');
    }
};
