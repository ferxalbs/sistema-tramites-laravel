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
        Schema::create('modalidades_documento', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento_salida', 50);
            $table->string('codigo', 50);
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();
            $table->foreign('tipo_documento_salida')->references('codigo')->on('tipos_documento_salida');
            $table->unique(['tipo_documento_salida', 'codigo']);
            $table->index(['tipo_documento_salida', 'activo', 'orden']);
        });

        $now = now();
        DB::table('modalidades_documento')->insert([
            [
                'tipo_documento_salida' => 'memorando',
                'codigo' => 'simple',
                'nombre' => 'Simple',
                'descripcion' => 'Memorando dirigido preliminarmente a un destinatario principal.',
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tipo_documento_salida' => 'memorando',
                'codigo' => 'multiple',
                'nombre' => 'Múltiple',
                'descripcion' => 'Memorando preparado para dos o más destinatarios.',
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('tipos_tramite', function (Blueprint $table) {
            $table->string('tipo_documento_salida_sugerido', 50)->nullable();
        });

        DB::table('tipos_tramite')
            ->whereIn('codigo', ['JUSTIFICACION', 'JUSTIFICACION_TARDANZA', 'AUTORIZACION_INGRESO', 'COMUNICACION_ADMINISTRATIVA'])
            ->update(['tipo_documento_salida_sugerido' => 'memorando']);
        DB::table('tipos_tramite')->where('codigo', 'REQUERIMIENTO_EQUIPAMIENTO')
            ->update(['tipo_documento_salida_sugerido' => 'informe']);

        Schema::table('tramites', function (Blueprint $table) {
            $table->string('formato_salida', 50)->nullable();
            $table->string('modalidad_documento', 50)->nullable();
            $table->foreign('formato_salida')->references('codigo')->on('tipos_documento_salida');
            $table->foreign(['formato_salida', 'modalidad_documento'])
                ->references(['tipo_documento_salida', 'codigo'])->on('modalidades_documento');
            $table->index(['formato_salida', 'modalidad_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->dropForeign(['formato_salida', 'modalidad_documento']);
            $table->dropForeign(['formato_salida']);
            $table->dropIndex(['formato_salida', 'modalidad_documento']);
            $table->dropColumn(['formato_salida', 'modalidad_documento']);
        });
        Schema::table('tipos_tramite', function (Blueprint $table) {
            $table->dropColumn('tipo_documento_salida_sugerido');
        });
        Schema::dropIfExists('modalidades_documento');
    }
};
