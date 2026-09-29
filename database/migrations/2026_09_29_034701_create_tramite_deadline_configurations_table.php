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
        Schema::create('configuracion_plazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->unique()->constrained('tipos_tramite')->restrictOnDelete();
            $table->unsignedSmallInteger('dias_estimados');
            $table->unsignedSmallInteger('dias_maximos');
            $table->string('tipo_dias', 12)->default('calendario');
            $table->unsignedSmallInteger('dias_anticipacion_recordatorio')->default(2);
            $table->boolean('es_plazo_oficial')->default(false);
            $table->boolean('activo')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $rows = DB::table('tipos_tramite')->get(['id'])->map(fn (object $type): array => [
            'tipo_tramite_id' => $type->id,
            'dias_estimados' => 5,
            'dias_maximos' => 10,
            'tipo_dias' => 'habiles',
            'dias_anticipacion_recordatorio' => 2,
            'es_plazo_oficial' => false,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        DB::table('configuracion_plazos')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_plazos');
    }
};
