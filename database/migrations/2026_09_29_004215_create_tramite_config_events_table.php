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
        Schema::create('tramite_config_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 60);
            $table->string('entidad', 60);
            $table->unsignedBigInteger('entidad_id');
            $table->json('valor_anterior');
            $table->json('valor_nuevo');
            $table->timestamps();

            $table->index(['entidad', 'entidad_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_config_events');
    }
};
