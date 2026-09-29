<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('ALTER TABLE tramite_documentos_finales ADD COLUMN documento_anterior_id INTEGER REFERENCES tramite_documentos_finales(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE tramite_documentos_finales ADD COLUMN motivo_anulacion TEXT');
            DB::statement('ALTER TABLE tramite_documentos_finales ADD COLUMN fecha_anulacion DATETIME');
            DB::statement('ALTER TABLE tramite_documentos_finales ADD COLUMN anulado_por INTEGER REFERENCES users(id) ON DELETE SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tramite_documentos_finales')
            ->whereNotNull('documento_anterior_id')
            ->orWhereNotNull('fecha_anulacion')
            ->exists()) {
            throw new RuntimeException('No se puede retirar el historial de anulación o sustitución de documentos oficiales.');
        }

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE tramite_documentos_finales DROP COLUMN anulado_por');
            DB::statement('ALTER TABLE tramite_documentos_finales DROP COLUMN fecha_anulacion');
            DB::statement('ALTER TABLE tramite_documentos_finales DROP COLUMN motivo_anulacion');
            DB::statement('ALTER TABLE tramite_documentos_finales DROP COLUMN documento_anterior_id');
        });
    }
};
