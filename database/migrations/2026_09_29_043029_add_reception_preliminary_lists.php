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
        Schema::table('tipos_tramite', function (Blueprint $table) {
            $table->boolean('requiere_personas_relacionadas')->default(false);
            $table->boolean('requiere_destinatarios_multiples')->default(false);
            $table->boolean('requiere_documento_original')->default(false);
        });

        DB::table('tipos_tramite')->where('codigo', 'AUTORIZACION_INGRESO')
            ->update(['requiere_personas_relacionadas' => true]);
        DB::table('tipos_tramite')->where('codigo', 'COMUNICACION_ADMINISTRATIVA')
            ->update(['requiere_destinatarios_multiples' => true]);

        Schema::create('personas_relacionadas_expediente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->string('dni', 8)->nullable();
            $table->string('cargo_funcion', 160)->nullable();
            $table->string('tipo_relacion', 60);
            $table->unsignedSmallInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tramite_id', 'activo', 'orden']);
        });

        Schema::create('documento_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->foreignId('cargo_institucional_id')->nullable()->constrained('cargos_institucionales')->nullOnDelete();
            $table->string('cargo_texto', 160)->nullable();
            $table->string('correo_institucional', 190)->nullable();
            $table->boolean('es_destinatario_principal')->default(false);
            $table->unsignedSmallInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tramite_id', 'activo', 'orden']);
        });

        Schema::create('documento_personas_mencionadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->string('dni', 8)->nullable();
            $table->string('cargo_funcion', 160)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tramite_id', 'activo', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_personas_mencionadas');
        Schema::dropIfExists('documento_destinatarios');
        Schema::dropIfExists('personas_relacionadas_expediente');
        Schema::table('tipos_tramite', function (Blueprint $table) {
            $table->dropColumn([
                'requiere_personas_relacionadas',
                'requiere_destinatarios_multiples',
                'requiere_documento_original',
            ]);
        });
    }
};
