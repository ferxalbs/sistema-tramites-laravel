<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // New receptions use the evaluation report model. Historical cases and PDFs stay intact.
        DB::table('tipos_tramite')->where('codigo', 'JUSTIFICACION_TARDANZA')->update([
            'tipo_documento_salida_sugerido' => 'informe',
            'modalidad_documento_sugerida' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('tipos_tramite')->where('codigo', 'JUSTIFICACION_TARDANZA')->update([
            'tipo_documento_salida_sugerido' => 'memorando',
        ]);
    }
};
