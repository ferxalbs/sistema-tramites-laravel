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
        Schema::table('tramite_documentos', function (Blueprint $table): void {
            $table->foreignId('documento_anterior_id')->nullable()->unique()->constrained('tramite_documentos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tramite_documentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('documento_anterior_id');
        });
    }
};
