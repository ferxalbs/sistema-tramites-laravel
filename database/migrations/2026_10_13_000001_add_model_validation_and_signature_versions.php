<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite rebuilds this table to add a foreign key. Preserve the predicate explicitly.
        DB::statement('DROP INDEX IF EXISTS tramite_plantillas_unica_activa');
        Schema::table('tramite_plantillas', function (Blueprint $table): void {
            $table->string('tipo_tramite', 80)->nullable()->index();
            $table->timestamp('validada_en')->nullable();
            $table->foreignId('validada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fuente_modelo', 255)->nullable();
            $table->boolean('modelo_propuesto')->default(false);
        });
        DB::statement('CREATE UNIQUE INDEX tramite_plantillas_unica_activa ON tramite_plantillas (codigo) WHERE activa = 1');
        Schema::table('users', function (Blueprint $table): void {
            $table->string('firma_perfil_ruta')->nullable();
            $table->string('firma_perfil_sha256', 64)->nullable();
            $table->timestamp('firma_perfil_actualizada_en')->nullable();
        });
        foreach (['CONSTANCIA_MODALIDAD_TITULACION', 'INFORME', 'MEMORANDO_SIMPLE', 'MEMORANDO_MULTIPLE', 'CONSTANCIA_PRACTICA', 'JUSTIFICACION_TARDANZA'] as $type) {
            $code = $type === 'INFORME' ? 'INFORME_INSTITUCIONAL' : $type;
            DB::table('tramite_plantillas')->whereIn('codigo', [$code, $code.'_REFERENCIAL'])->update(['tipo_tramite' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('tramite_plantillas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('validada_por');
            $table->dropColumn(['tipo_tramite', 'validada_en', 'fuente_modelo', 'modelo_propuesto']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['firma_perfil_ruta', 'firma_perfil_sha256', 'firma_perfil_actualizada_en']);
        });
    }
};
