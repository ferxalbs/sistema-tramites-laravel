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
        DB::statement("ALTER TABLE users ADD COLUMN rol TEXT NOT NULL DEFAULT 'estudiante'");
        DB::statement('ALTER TABLE users ADD COLUMN activo INTEGER NOT NULL DEFAULT 1');
        DB::statement('CREATE INDEX users_rol_index ON users (rol)');
        DB::statement('CREATE INDEX users_activo_index ON users (activo)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_rol_index');
        DB::statement('DROP INDEX IF EXISTS users_activo_index');
        DB::statement('ALTER TABLE users DROP COLUMN activo');
        DB::statement('ALTER TABLE users DROP COLUMN rol');
    }
};
