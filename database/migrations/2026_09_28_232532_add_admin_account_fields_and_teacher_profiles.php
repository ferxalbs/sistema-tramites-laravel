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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('debe_cambiar_password')->default(false);
        });

        Schema::create('perfiles_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('programa_estudio_id')->nullable()->constrained('programas_estudio')->nullOnDelete();
            $table->string('codigo_docente', 40)->nullable()->unique();
            $table->string('especialidad', 160)->nullable();
            $table->string('condicion_laboral', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfiles_docente');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
