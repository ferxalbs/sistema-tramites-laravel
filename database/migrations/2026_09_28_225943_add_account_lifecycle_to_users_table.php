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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('estado_cuenta', 20)->default('activo')->index();
            $table->unsignedInteger('sesion_version')->default(0);
            $table->string('motivo_inactivacion', 500)->nullable();
        });

        DB::table('users')->where('activo', false)->update(['estado_cuenta' => 'inactivo']);

        Schema::create('user_account_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('accion', 20);
            $table->string('estado_anterior', 20);
            $table->string('estado_nuevo', 20);
            $table->string('motivo', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_account_events');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['estado_cuenta', 'sesion_version', 'motivo_inactivacion']);
        });
    }
};
