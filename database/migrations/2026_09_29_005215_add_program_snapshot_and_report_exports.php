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
            $table->foreignId('programa_estudio_id')->nullable()->constrained('programas_estudio')->nullOnDelete();
        });

        Schema::create('tramite_report_export_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filtros');
            $table->unsignedInteger('filas');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_report_export_events');

        Schema::table('tramites', function (Blueprint $table): void {
            $table->dropForeign(['programa_estudio_id']);
            $table->dropColumn('programa_estudio_id');
        });
    }
};
