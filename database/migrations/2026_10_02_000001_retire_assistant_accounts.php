<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('users')->where('rol', 'asistente')->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        DB::table('users')->whereIn('id', $ids)->update([
            'activo' => false,
            'estado_cuenta' => 'inactivo',
            'motivo_inactivacion' => 'Rol Asistente retirado. La administración asumió sus funciones.',
            'sesion_version' => DB::raw('sesion_version + 1'),
            'remember_token' => null,
            'updated_at' => now(),
        ]);

        DB::table('sessions')->whereIn('user_id', $ids)->delete();
    }

    public function down(): void
    {
        // Do not reactivate historical accounts when rolling back code.
    }
};
