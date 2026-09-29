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
        Schema::table('tramite_notificaciones', function (Blueprint $table) {
            $table->foreignId('tramite_id')->nullable()->change();
            $table->foreignId('evento_id')->nullable()->change();
            $table->foreignId('account_event_id')->nullable()->constrained('user_account_events')->cascadeOnDelete();
            $table->unique(['account_event_id', 'usuario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tramite_notificaciones')->whereNotNull('account_event_id')->exists()) {
            throw new RuntimeException('Existen avisos de cuenta; no se puede revertir esta migración sin perderlos.');
        }

        Schema::table('tramite_notificaciones', function (Blueprint $table) {
            $table->dropUnique(['account_event_id', 'usuario_id']);
            $table->dropConstrainedForeignId('account_event_id');
            $table->foreignId('tramite_id')->nullable(false)->change();
            $table->foreignId('evento_id')->nullable(false)->change();
        });
    }
};
