<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinatarios_institucionales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('cargo', 180);
            $table->string('correo', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('destinatarios_institucionales')->insert([
            'codigo' => 'director-general',
            'nombres' => 'Mg. RAUL WILLIAM',
            'apellidos' => 'LOPEZ REYNA',
            'cargo' => 'Director General del I.E.S.T.P. “Manuel Seoane Corrales”',
            'correo' => null,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('destinatarios_institucionales');
    }
};
