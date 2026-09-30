<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('tipos_tramite')->whereIn('codigo', [
                'FUT',
                'AUTORIZACION_INGRESO',
                'COMUNICACION_ADMINISTRATIVA',
                'SOLICITUD_GENERAL',
                'JUSTIFICACION',
            ])->update(['activo' => false, 'updated_at' => $now]);

            DB::table('tipos_tramite')->where('codigo', 'JUSTIFICACION_TARDANZA')->update([
                'nombre' => 'Justificación de tardanza',
                'updated_at' => $now,
            ]);

            $oldConstancia = DB::table('tipos_tramite')
                ->where('codigo', 'SOLICITUD_CONSTANCIA_MODALIDAD_TITULACION')
                ->first(['id']);

            DB::table('tipos_tramite')->where('codigo', 'CONSTANCIA_MODALIDAD_TITULACION')->update([
                'nombre' => 'Constancia de modalidad de examen de titulación',
                'updated_at' => $now,
            ]);

            if ($oldConstancia !== null) {
                $newConstanciaExists = DB::table('tipos_tramite')
                    ->where('codigo', 'CONSTANCIA_MODALIDAD_TITULACION')
                    ->exists();

                if (! $newConstanciaExists) {
                    DB::table('tipos_tramite')->where('id', $oldConstancia->id)->update([
                        'codigo' => 'CONSTANCIA_MODALIDAD_TITULACION',
                        'nombre' => 'Constancia de modalidad de examen de titulación',
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('tipos_tramite')->where('id', $oldConstancia->id)->update([
                        'activo' => false,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('tramites')
                    ->where('tipo_documento', 'SOLICITUD_CONSTANCIA_MODALIDAD_TITULACION')
                    ->update(['tipo_documento' => 'CONSTANCIA_MODALIDAD_TITULACION', 'updated_at' => $now]);
            }

            DB::table('tipos_tramite')->where('clasificacion_sugerida', 'institucional')->update([
                'clasificacion_sugerida' => 'administrativo',
                'updated_at' => $now,
            ]);
            DB::table('tramites')->where('clasificacion', 'institucional')->update([
                'clasificacion' => 'administrativo',
                'updated_at' => $now,
            ]);
            DB::table('clasificaciones_expediente')->where('codigo', 'institucional')->update([
                'activo' => false,
                'updated_at' => $now,
            ]);

            DB::table('tipos_documento_salida')->where('activo', false)->update([
                'activo' => true,
                'updated_at' => $now,
            ]);

            $mediosPermitidos = ['presencial', 'correo_electronico', 'descarga_sistema'];
            DB::table('tramite_medios_entrega')->whereIn('codigo', $mediosPermitidos)->update([
                'activo' => true,
                'updated_at' => $now,
            ]);
            DB::table('tramite_medios_entrega')->whereNotIn('codigo', $mediosPermitidos)->update([
                'activo' => false,
                'updated_at' => $now,
            ]);

            if (DB::table('configuracion_plazos')->exists()) {
                DB::table('configuracion_plazos')->where('activo', true)->update([
                    'activo' => false,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Keep catalog changes and historical records if this migration is rolled back.
    }
};
