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
        DB::statement('ALTER TABLE tramites ADD COLUMN propietario_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX tramites_propietario_estado_index ON tramites (propietario_id, estado)');

        Schema::create('tramite_rondas_revision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('asignacion_id')->constrained('tramite_asignaciones')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_ronda');
            $table->foreignId('revisor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('borrador_id')->constrained('tramite_borradores')->restrictOnDelete();
            $table->string('estado', 30)->default('en_revision');
            $table->boolean('activa')->default(true);
            $table->timestamp('iniciada_en');
            $table->timestamp('cerrada_en')->nullable();
            $table->text('resumen_observacion')->nullable();
            $table->text('resumen_correccion')->nullable();
            $table->text('conclusion')->nullable();
            $table->text('comentario_publico')->nullable();
            $table->text('comentario_interno')->nullable();
            $table->timestamps();

            $table->unique(['tramite_id', 'numero_ronda']);
            $table->index(['revisor_id', 'activa']);
        });

        DB::statement('CREATE UNIQUE INDEX tramite_rondas_revision_una_activa ON tramite_rondas_revision (tramite_id) WHERE activa = 1');

        Schema::create('tramite_observaciones_revision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ronda_id')->constrained('tramite_rondas_revision')->restrictOnDelete();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('revisor_id')->constrained('users')->restrictOnDelete();
            $table->string('categoria', 60);
            $table->string('titulo', 160);
            $table->text('descripcion');
            $table->string('seccion', 160)->nullable();
            $table->boolean('obligatoria')->default(true);
            $table->boolean('visible_para_interesado')->default(false);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['tramite_id', 'ronda_id']);
        });

        Schema::create('tramite_respuestas_observacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observacion_id')->constrained('tramite_observaciones_revision')->restrictOnDelete();
            $table->foreignId('borrador_id')->constrained('tramite_borradores')->restrictOnDelete();
            $table->foreignId('asistente_id')->constrained('users')->restrictOnDelete();
            $table->text('respuesta');
            $table->timestamps();

            $table->unique('observacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_respuestas_observacion');
        Schema::dropIfExists('tramite_observaciones_revision');
        Schema::dropIfExists('tramite_rondas_revision');
        DB::statement('DROP INDEX IF EXISTS tramites_propietario_estado_index');
        DB::statement('ALTER TABLE tramites DROP COLUMN propietario_id');
    }
};
