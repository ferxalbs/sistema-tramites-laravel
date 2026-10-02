<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteEvento;
use App\Models\User;

class LinkApplicantAccount
{
    public function execute(User $student, User $actor): void
    {
        if ($student->rol !== 'estudiante' || ! $student->activo || $student->estado_cuenta !== 'activo' || ! $student->dni) {
            return;
        }

        Tramite::query()
            ->where('clasificacion', 'estudiantil')
            ->where('persona_identificador', $student->dni)
            ->whereNull('propietario_id')
            ->orderBy('id')
            ->chunkById(100, function ($tramites) use ($student, $actor): void {
                foreach ($tramites as $tramite) {
                    $updated = Tramite::query()->whereKey($tramite->id)->whereNull('propietario_id')
                        ->update(['propietario_id' => $student->id]);

                    if ($updated === 1) {
                        TramiteEvento::query()->create([
                            'tramite_id' => $tramite->id,
                            'usuario_id' => $actor->id,
                            'accion' => 'solicitante_vinculado',
                            'descripcion' => 'La cuenta estudiantil aprobada se vinculó al expediente por DNI.',
                            'metadatos' => ['propietario_id' => $student->id],
                        ]);
                    }
                }
            });
    }
}
