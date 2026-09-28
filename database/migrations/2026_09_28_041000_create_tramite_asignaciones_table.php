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
        Schema::create('tramite_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('destino', 20);
            $table->foreignId('revisor_id')->constrained('users')->restrictOnDelete();
            $table->string('rol_revisor', 40);
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 255);
            $table->text('instrucciones_revision')->nullable();
            $table->date('fecha_esperada')->nullable();
            $table->string('estado', 30)->default('activa');
            $table->boolean('activa')->default(true);
            $table->timestamp('fecha_inicio_revision')->nullable();
            $table->timestamp('fecha_finalizacion')->nullable();
            $table->text('motivo_finalizacion')->nullable();
            $table->timestamps();

            $table->index(['revisor_id', 'activa', 'destino']);
            $table->index(['tramite_id', 'created_at']);
        });

        DB::statement('CREATE UNIQUE INDEX tramite_asignaciones_una_activa ON tramite_asignaciones (tramite_id) WHERE activa = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_asignaciones');
    }
};
