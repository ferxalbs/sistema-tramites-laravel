<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS tramite_plantillas_unica_activa');
        DB::statement('CREATE UNIQUE INDEX tramite_plantillas_unica_activa ON tramite_plantillas (codigo) WHERE activa = 1');
    }

    public function down(): void
    {
        // Keep the uniqueness of the active version, including when reverting application code.
    }
};
