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
        Schema::table('tramites', function (Blueprint $table): void {
            $table->dateTime('fecha_llegada_oficina')->nullable();
            $table->date('fecha_presentacion_original')->nullable();
            $table->string('numero_expediente_externo', 80)->nullable();
            $table->string('area_procedencia', 160)->nullable();
            $table->string('persona_entrega_documento', 180)->nullable();
            $table->text('observacion_recepcion')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table): void {
            $table->dropColumn([
                'fecha_llegada_oficina',
                'fecha_presentacion_original',
                'numero_expediente_externo',
                'area_procedencia',
                'persona_entrega_documento',
                'observacion_recepcion',
            ]);
        });
    }
};
