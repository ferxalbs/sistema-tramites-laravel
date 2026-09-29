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
        Schema::create('feriados', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('nombre', 160);
            $table->boolean('es_demostracion')->default(false);
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('feriado_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feriado_id')->constrained('feriados')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 40);
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feriado_eventos');
        Schema::dropIfExists('feriados');
    }
};
