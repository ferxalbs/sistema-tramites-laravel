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
        Schema::table('tramite_eventos', function (Blueprint $table) {
            $table->string('clave_dedupe', 160)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tramite_eventos', function (Blueprint $table) {
            $table->dropUnique(['clave_dedupe']);
            $table->dropColumn('clave_dedupe');
        });
    }
};
