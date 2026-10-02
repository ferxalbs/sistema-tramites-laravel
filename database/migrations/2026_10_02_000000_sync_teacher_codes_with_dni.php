<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear legacy codes first so the unique index cannot collide with another teacher's DNI.
        DB::table('perfiles_docente')->update(['codigo_docente' => null]);

        foreach (DB::table('perfiles_docente')
            ->join('users', 'users.id', '=', 'perfiles_docente.user_id')
            ->select('perfiles_docente.id', 'users.dni')
            ->orderBy('perfiles_docente.id')
            ->get() as $profile) {
            if (is_string($profile->dni) && preg_match('/^[0-9]{8}$/', $profile->dni) === 1) {
                DB::table('perfiles_docente')->where('id', $profile->id)
                    ->update(['codigo_docente' => $profile->dni]);
            }
        }
    }

    public function down(): void
    {
        // Previous independent codes cannot be reconstructed after normalization.
    }
};
