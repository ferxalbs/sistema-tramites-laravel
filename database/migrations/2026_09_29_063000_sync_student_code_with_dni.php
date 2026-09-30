<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy each student's DNI to the legacy code column.
     *
     * The old values are cleared first because a legacy code may equal another
     * student's DNI and the column has a unique index.
     */
    public function up(): void
    {
        DB::table('perfiles_estudiante')->update(['codigo_estudiante' => null]);

        DB::table('perfiles_estudiante')
            ->join('users', 'users.id', '=', 'perfiles_estudiante.user_id')
            ->select('perfiles_estudiante.id as profile_id', 'users.dni as dni')
            ->orderBy('perfiles_estudiante.id')
            ->chunk(500, function ($profiles): void {
                foreach ($profiles as $profile) {
                    DB::table('perfiles_estudiante')
                        ->where('id', $profile->profile_id)
                        ->update(['codigo_estudiante' => $profile->dni]);
                }
            });
    }

    /**
     * The old independent codes are intentionally not restored.
     */
    public function down(): void
    {
        // This data migration cannot be reversed without the previous codes.
    }
};
