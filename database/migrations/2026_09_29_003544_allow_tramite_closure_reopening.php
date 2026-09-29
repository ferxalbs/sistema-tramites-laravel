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
        Schema::table('tramite_cierres', function (Blueprint $table): void {
            $table->dropUnique(['tramite_id']);
            $table->dropUnique(['entrega_id']);
            $table->boolean('reabierto')->default(false);
            $table->text('motivo_reapertura')->nullable();
            $table->foreignId('reabierto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_reapertura')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX tramite_cierres_uno_activo ON tramite_cierres (tramite_id) WHERE activo = 1 AND reabierto = 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tramite_cierres_uno_activo');

        Schema::table('tramite_cierres', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reabierto_por');
            $table->dropColumn(['reabierto', 'motivo_reapertura', 'fecha_reapertura']);
            $table->unique('tramite_id');
            $table->unique('entrega_id');
        });
    }
};
