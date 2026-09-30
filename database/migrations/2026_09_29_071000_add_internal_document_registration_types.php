<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_tramite', function (Blueprint $table): void {
            $table->boolean('requiere_solicitante')->default(true);
            $table->string('modalidad_documento_sugerida', 50)->nullable();
        });

        Schema::table('tramites', function (Blueprint $table): void {
            $table->string('persona_nombre')->nullable()->change();
        });

        DB::transaction(function (): void {
            $now = now();

            DB::table('tipos_tramite')->where('codigo', 'REQUERIMIENTO_EQUIPAMIENTO')->update([
                'activo' => false,
                'updated_at' => $now,
            ]);

            foreach ([
                [
                    'codigo' => 'INFORME',
                    'nombre' => 'Informe',
                    'descripcion' => 'Registro de un documento institucional de tipo informe.',
                    'tipo_documento_salida_sugerido' => 'informe',
                    'modalidad_documento_sugerida' => null,
                    'requiere_destinatarios_multiples' => false,
                ],
                [
                    'codigo' => 'MEMORANDO_SIMPLE',
                    'nombre' => 'Memorando simple',
                    'descripcion' => 'Registro de un Memorando con un destinatario principal.',
                    'tipo_documento_salida_sugerido' => 'memorando',
                    'modalidad_documento_sugerida' => 'simple',
                    'requiere_destinatarios_multiples' => false,
                ],
                [
                    'codigo' => 'MEMORANDO_MULTIPLE',
                    'nombre' => 'Memorando múltiple',
                    'descripcion' => 'Registro de un Memorando dirigido a varios destinatarios.',
                    'tipo_documento_salida_sugerido' => 'memorando',
                    'modalidad_documento_sugerida' => 'multiple',
                    'requiere_destinatarios_multiples' => true,
                ],
            ] as $type) {
                DB::table('tipos_tramite')->updateOrInsert(
                    ['codigo' => $type['codigo']],
                    [
                        'nombre' => $type['nombre'],
                        'descripcion' => $type['descripcion'],
                        'clasificacion_sugerida' => 'administrativo',
                        'es_demostracion' => false,
                        'activo' => true,
                        'requiere_solicitante' => false,
                        'tipo_documento_salida_sugerido' => $type['tipo_documento_salida_sugerido'],
                        'modalidad_documento_sugerida' => $type['modalidad_documento_sugerida'],
                        'requiere_destinatarios_multiples' => $type['requiere_destinatarios_multiples'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('tramites')
            ->whereIn('tipo_documento', ['INFORME', 'MEMORANDO_SIMPLE', 'MEMORANDO_MULTIPLE'])
            ->whereNull('persona_nombre')
            ->update(['persona_nombre' => 'Documento institucional']);

        DB::table('tipos_tramite')->whereIn('codigo', ['INFORME', 'MEMORANDO_SIMPLE', 'MEMORANDO_MULTIPLE'])
            ->update(['activo' => false, 'updated_at' => now()]);
        DB::table('tipos_tramite')->where('codigo', 'REQUERIMIENTO_EQUIPAMIENTO')
            ->update(['activo' => true, 'updated_at' => now()]);

        Schema::table('tramites', function (Blueprint $table): void {
            $table->string('persona_nombre')->nullable(false)->change();
        });

        Schema::table('tipos_tramite', function (Blueprint $table): void {
            $table->dropColumn(['requiere_solicitante', 'modalidad_documento_sugerida']);
        });
    }
};
