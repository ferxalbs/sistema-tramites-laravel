<?php

namespace App\Services\Tramites;

use App\Models\TramiteEvento;
use Illuminate\Support\Facades\DB;

class CreateTramiteNotifications
{
    /**
     * @var array<string, array{string, string, string}>
     */
    private const EVENTS = [
        'recepcion' => ['registro', 'Expediente registrado', 'normal'],
        'digitalizacion' => ['digitalizacion', 'Documento digitalizado', 'normal'],
        'borrador_preparado' => ['borrador_preparado', 'Borrador listo para asignación', 'normal'],
        'asignacion_creada' => ['asignacion', 'Expediente asignado', 'normal'],
        'asignacion_reasignada' => ['reasignacion', 'Expediente reasignado', 'alta'],
        'observacion' => ['observacion', 'Expediente observado', 'alta'],
        'correccion_reenvio' => ['correccion', 'Corrección recibida', 'normal'],
        'revision_aprobada' => ['aprobacion', 'Expediente aprobado', 'alta'],
        'revision_rechazada' => ['rechazo', 'Expediente rechazado', 'alta'],
        'documento_final_emitido' => ['documento_final', 'Documento final generado', 'alta'],
        'entrega_registrada' => ['listo_entrega', 'Entrega registrada', 'alta'],
        'recepcion_confirmada' => ['entregado', 'Recepción confirmada', 'normal'],
        'expediente_cerrado' => ['cerrado', 'Expediente cerrado', 'normal'],
    ];

    public function forEvent(TramiteEvento $evento): void
    {
        $definition = self::EVENTS[$evento->accion] ?? null;

        if ($definition === null) {
            return;
        }

        $tramite = DB::table('tramites')->where('id', $evento->tramite_id)->first(['codigo', 'propietario_id']);

        if ($tramite === null) {
            return;
        }

        $recipients = DB::table('users')
            ->where('activo', true)
            ->where('estado_cuenta', 'activo')
            ->where(function ($query) use ($tramite, $evento): void {
                $query->where('rol', 'administrador')
                    ->orWhere(function ($owner) use ($tramite): void {
                        $owner->where('rol', 'estudiante')->where('id', $tramite->propietario_id);
                    })
                    ->orWhere(function ($reviewer) use ($evento): void {
                        $reviewer->where('rol', 'docente')
                            ->whereIn('id', DB::table('tramite_asignaciones')
                                ->where('tramite_id', $evento->tramite_id)
                                ->where('activa', true)
                                ->select('revisor_id'));
                    });
            })
            ->pluck('id');

        foreach ($recipients as $recipientId) {
            DB::table('tramite_notificaciones')->insertOrIgnore([
                'usuario_id' => $recipientId,
                'tramite_id' => $evento->tramite_id,
                'evento_id' => $evento->id,
                'tipo' => $definition[0],
                'titulo' => $definition[1],
                'mensaje' => $definition[1].' para el expediente '.$tramite->codigo.'.',
                'prioridad' => $definition[2],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
