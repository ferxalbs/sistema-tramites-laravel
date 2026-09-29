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
        Schema::create('tipos_documento_salida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('permite_modalidad_multiple')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();
            $table->index(['activo', 'orden']);
        });

        $now = now();
        DB::table('tipos_documento_salida')->insert([
            [
                'codigo' => 'informe',
                'nombre' => 'Informe',
                'descripcion' => 'Formato institucional de informe que se completará en una fase posterior.',
                'permite_modalidad_multiple' => false,
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo' => 'memorando',
                'nombre' => 'Memorando',
                'descripcion' => 'Formato institucional de memorando simple o múltiple.',
                'permite_modalidad_multiple' => true,
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_documento_salida');
    }
};
