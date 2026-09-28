<?php

namespace App\Services\Tramites;

use App\Models\TramiteBorrador;
use App\Models\TramiteEvento;
use Illuminate\Support\Facades\DB;

class PrepareTramiteForAssignment
{
    public function execute(int $tramiteId, int $actorId): void
    {
        DB::transaction(function () use ($tramiteId, $actorId): void {
            $tramite = DB::table('tramites')->where('id', $tramiteId)->first(['id', 'estado']);

            abort_unless($tramite !== null, 404);

            $borradorPreparado = TramiteBorrador::query()
                ->where('tramite_id', $tramiteId)
                ->where('es_actual', true)
                ->where('estado', 'preparado_asignacion')
                ->exists();

            if ($tramite->estado === 'pendiente_asignacion' && $borradorPreparado) {
                return;
            }

            abort_unless($tramite->estado === 'borrador_preparado' && $borradorPreparado, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramiteId)
                ->where('estado', 'borrador_preparado')
                ->update([
                    'estado' => 'pendiente_asignacion',
                    'updated_at' => now(),
                ]);

            abort_unless($actualizados === 1, 409);

            TramiteEvento::query()->create([
                'tramite_id' => $tramiteId,
                'usuario_id' => $actorId,
                'accion' => 'preparar_asignacion',
                'descripcion' => 'El trámite pasó a la bandeja pendiente de asignación.',
                'estado_anterior' => 'borrador_preparado',
                'estado_nuevo' => 'pendiente_asignacion',
                'metadatos' => ['accion' => 'preparar_asignacion'],
            ]);
        });
    }
}
