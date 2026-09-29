<?php

namespace App\Console\Commands;

use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteNumeracionDocumental;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

#[Signature('documents:reconcile-reservation {documento : ID del documento oficial} {--fail : Marcar una reserva inconclusa como fallida} {--reason= : Motivo operativo obligatorio con --fail}')]
#[Description('Inspecciona o cierra una reserva inconclusa sin reutilizar su correlativo')]
class ReconcileDocumentReservation extends Command
{
    public function handle(): int
    {
        $id = (string) $this->argument('documento');

        if (! ctype_digit($id) || (int) $id < 1) {
            $this->error('Indique un ID de documento válido.');

            return self::FAILURE;
        }

        $documento = TramiteDocumentoFinal::query()->find((int) $id);
        $numeracion = $documento === null ? null : TramiteNumeracionDocumental::query()->find($documento->numeracion_id);

        if ($documento === null || $numeracion === null || (int) $numeracion->tramite_id !== (int) $documento->tramite_id
            || $numeracion->numero_completo !== $documento->numero_documento) {
            $this->error('El documento y su reserva no forman un par válido.');

            return self::FAILURE;
        }

        $this->line('Documento '.$documento->id.' · número '.$numeracion->numero_completo.' · documento '.$documento->estado.' · reserva '.$numeracion->estado.'.');

        if (! $this->option('fail')) {
            return self::SUCCESS;
        }

        if ($documento->estado === 'fallido' && ! $documento->activo && $numeracion->estado === 'fallida') {
            $this->info('La reserva ya fue cerrada como fallida.');

            return self::SUCCESS;
        }

        if ($documento->estado !== 'generando' || ! $documento->activo || $numeracion->estado !== 'reservada') {
            $this->error('Solo se puede cerrar un documento generando con reserva activa.');

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));

        if ($reason === '' || mb_strlen($reason) > 500) {
            $this->error('Use --reason con un motivo de entre 1 y 500 caracteres.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($documento, $numeracion, $reason): void {
                $ahora = now();
                $updatedDocument = DB::table('tramite_documentos_finales')
                    ->where('id', $documento->id)
                    ->where('estado', 'generando')
                    ->where('activo', true)
                    ->update([
                        'estado' => 'fallido',
                        'activo' => false,
                        'error_generacion' => 'Reconciliación CLI: '.$reason,
                        'updated_at' => $ahora,
                    ]);
                $updatedNumber = DB::table('tramite_numeraciones_documentales')
                    ->where('id', $numeracion->id)
                    ->where('estado', 'reservada')
                    ->update([
                        'estado' => 'fallida',
                        'error_generacion' => 'Reconciliación CLI: '.$reason,
                        'updated_at' => $ahora,
                    ]);

                if ($updatedDocument !== 1 || $updatedNumber !== 1) {
                    throw new RuntimeException('La reserva cambió durante la reconciliación.');
                }

                TramiteEvento::query()->create([
                    'tramite_id' => $documento->tramite_id,
                    'usuario_id' => null,
                    'accion' => 'reserva_reconciliada_cli',
                    'descripcion' => 'La reserva oficial inconclusa se cerró como fallida desde CLI; el número permanece consumido.',
                    'metadatos' => [
                        'documento_id' => $documento->id,
                        'numeracion_id' => $numeracion->id,
                    ],
                ]);
            });
        } catch (Throwable) {
            $this->error('No se pudo confirmar el resultado. Ejecute de nuevo el comando sin --fail para inspeccionar el estado; no reintente automáticamente la escritura.');

            return self::FAILURE;
        }

        $this->info('Reserva cerrada como fallida. El correlativo no se reutilizará.');

        return self::SUCCESS;
    }
}
