<?php

namespace App\Console\Commands;

use App\Models\Tramite;
use App\Services\Tramites\CalculateTramiteDeadline;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('tramites:generar-recordatorios')]
#[Description('Genera una vez los avisos de plazos referenciales para expedientes institucionales activos.')]
class GenerateTramiteDeadlineReminders extends Command
{
    public function handle(CalculateTramiteDeadline $calculator): int
    {
        $created = 0;
        $skipped = 0;
        $today = new DateTimeImmutable(now()->toDateString());
        $query = Tramite::query()
            ->whereIn('tipo_documento', DB::table('tipos_tramite')->where('es_demostracion', false)->select('codigo'))
            ->whereNotIn('estado', ['entregado', 'cerrado']);

        $lastId = 0;
        do {
            $batch = (clone $query)->where('id', '>', $lastId)->orderBy('id')->limit(100)->get();
            foreach ($batch as $tramite) {
                $lastId = $tramite->getKey();
                $ownerId = $tramite->getRawOriginal('propietario_id');
                $deadline = $calculator->forTramite($tramite, $today);
                if ($deadline === null || ! in_array($deadline['alerta'], ['proximo', 'hoy', 'vencido'], true)) {
                    continue;
                }

                $type = $deadline['alerta'] === 'vencido' ? 'plazo_vencido' : 'plazo_proximo';
                $reviewerId = DB::table('tramite_asignaciones')->where('tramite_id', $tramite->id)
                    ->where('activa', true)->where('destino', 'docente')->value('revisor_id');
                $recipients = DB::table('users')->where('activo', true)->where('estado_cuenta', 'activo')
                    ->where(function ($users) use ($ownerId, $reviewerId): void {
                        $users->whereIn('rol', ['asistente', 'administrador'])
                            ->orWhere(function ($owner) use ($ownerId): void {
                                $owner->where('rol', 'estudiante')->where('id', $ownerId);
                            })
                            ->orWhere(function ($reviewer) use ($reviewerId): void {
                                $reviewer->where('rol', 'docente')->where('id', $reviewerId);
                            });
                    })->pluck('id');

                if ($recipients->isEmpty()) {
                    continue;
                }

                DB::transaction(function () use ($tramite, $type, $deadline, $recipients, &$created, &$skipped): void {
                    $key = 'plazo:'.$tramite->id.':'.$type.':'.$deadline['fecha_maxima'];
                    DB::table('tramite_eventos')->insertOrIgnore([
                        'tramite_id' => $tramite->id,
                        'usuario_id' => null,
                        'accion' => $type,
                        'descripcion' => $type === 'plazo_vencido' ? 'El plazo referencial venció.' : 'El plazo referencial se aproxima.',
                        'clave_dedupe' => $key,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $eventId = DB::table('tramite_eventos')->where('clave_dedupe', $key)->value('id');
                    if ($eventId === null) {
                        throw new RuntimeException('No se pudo reconciliar el recordatorio.');
                    }

                    foreach ($recipients as $recipientId) {
                        $inserted = DB::table('tramite_notificaciones')->insertOrIgnore([
                            'usuario_id' => $recipientId,
                            'tramite_id' => $tramite->id,
                            'evento_id' => $eventId,
                            'tipo' => $type,
                            'titulo' => $type === 'plazo_vencido' ? 'Plazo vencido' : 'Plazo próximo',
                            'mensaje' => 'El expediente '.$tramite->codigo.' requiere atención.',
                            'prioridad' => $type === 'plazo_vencido' ? 'urgente' : 'alta',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $created += (int) $inserted;
                        $skipped += $inserted ? 0 : 1;
                    }
                });
            }
        } while ($batch->count() === 100);

        $this->line(json_encode(['creados' => $created, 'omitidos' => $skipped], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
