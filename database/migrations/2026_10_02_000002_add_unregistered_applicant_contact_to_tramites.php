<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table): void {
            $table->string('solicitante_correo', 190)->nullable();
            $table->string('solicitante_celular', 20)->nullable();
            $table->index(['clasificacion', 'persona_identificador', 'propietario_id'], 'tramites_solicitante_dni_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table): void {
            $table->dropIndex('tramites_solicitante_dni_idx');
            $table->dropColumn(['solicitante_correo', 'solicitante_celular']);
        });
    }
};
