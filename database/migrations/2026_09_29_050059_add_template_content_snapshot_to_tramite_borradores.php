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
        Schema::table('tramite_borradores', function (Blueprint $table) {
            $table->text('contenido_plantilla_snapshot')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX tramite_plantillas_unica_activa ON tramite_plantillas (codigo) WHERE activa = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tramite_plantillas_unica_activa');

        Schema::table('tramite_borradores', function (Blueprint $table) {
            $table->dropColumn('contenido_plantilla_snapshot');
        });
    }
};
