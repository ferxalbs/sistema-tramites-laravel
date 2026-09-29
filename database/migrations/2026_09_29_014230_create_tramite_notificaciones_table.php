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
        Schema::create('tramite_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('evento_id')->constrained('tramite_eventos')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('titulo', 160);
            $table->string('mensaje', 500);
            $table->string('prioridad', 10)->default('normal');
            $table->boolean('leida')->default(false);
            $table->timestamp('fecha_lectura')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'usuario_id']);
            $table->index(['usuario_id', 'leida', 'created_at']);
        });

        Schema::create('tramite_notificacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacion_id')->nullable()->constrained('tramite_notificaciones')->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 30);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_notificacion_eventos');
        Schema::dropIfExists('tramite_notificaciones');
    }
};
