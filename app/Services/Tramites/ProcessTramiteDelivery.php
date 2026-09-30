<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteCierre;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEntrega;
use App\Models\TramiteEvento;
use App\Models\TramiteEvidenciaEntrega;
use App\Models\TramiteFirma;
use App\Models\TramiteInformeCierre;
use App\Models\TramiteMedioEntrega;
use App\Models\User;
use App\Services\PdfDocumentGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

use function abort;
use function abort_unless;

class ProcessTramiteDelivery
{
    public function __construct(private PdfDocumentGenerator $pdfDocumentGenerator) {}

    public function prepare(Tramite $tramite, User $actor): string
    {
        $this->authorizeStaff($actor);

        return DB::transaction(function () use ($tramite, $actor): string {
            $registro = Tramite::query()->findOrFail($tramite->id);
            abort_unless($registro->estado === 'documento_final_generado', 409);

            $documento = $this->documentoFinalEmitido($registro);
            $snapshot = $documento->contenido_snapshot;
            $requiereFirma = (bool) ($snapshot['requiere_firma_fisica'] ?? false);
            $firmaPerfilIncluida = is_string($snapshot['firma_perfil_sha256'] ?? null);
            $estadoNuevo = $requiereFirma ? 'pendiente_firma' : 'listo_entrega';
            $actualizado = DB::table('tramites')
                ->where('id', $registro->id)
                ->where('estado', 'documento_final_generado')
                ->update(['estado' => $estadoNuevo, 'updated_at' => now()]);

            abort_unless($actualizado === 1, 409);

            if (! $requiereFirma) {
                TramiteFirma::query()->create([
                    'tramite_id' => $registro->id,
                    'documento_final_id' => $documento->id,
                    'firmante_id' => $documento->borrador?->firmante_id,
                    'registrado_por' => $actor->id,
                    'no_requiere_firma' => ! $firmaPerfilIncluida,
                    'fecha_firma' => $firmaPerfilIncluida ? now() : null,
                    'observacion' => $firmaPerfilIncluida
                        ? 'Se insertó en el PDF la firma escaneada guardada en el perfil del firmante.'
                        : 'La plantilla no requiere firma física.',
                ]);
            }

            $this->registrarEvento(
                $registro->id,
                $actor->id,
                'entrega_preparada',
                $requiereFirma ? 'El documento oficial fue enviado a firma física.' : ($firmaPerfilIncluida
                    ? 'El documento oficial quedó listo para entregar con la firma escaneada del perfil.'
                    : 'El documento oficial quedó listo para entregar sin firma física.'),
                'documento_final_generado',
                $estadoNuevo,
                ['documento_final_id' => $documento->id],
            );

            return $estadoNuevo;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registerSignature(Tramite $tramite, User $actor, array $datos, ?UploadedFile $evidencia): TramiteFirma
    {
        $this->authorizeStaff($actor);
        $noRequiereFirma = filter_var($datos['no_requiere_firma'] ?? false, FILTER_VALIDATE_BOOL);

        if ($noRequiereFirma && $evidencia !== null) {
            throw ValidationException::withMessages(['evidencia' => 'No adjunte evidencia cuando se registra que la firma no es aplicable.']);
        }

        $archivo = $evidencia === null ? null : $this->storePrivateFile($evidencia, $tramite->id, 'firmas');

        try {
            return DB::transaction(function () use ($tramite, $actor, $datos, $noRequiereFirma, $archivo): TramiteFirma {
                $registro = Tramite::query()->findOrFail($tramite->id);
                abort_unless($registro->estado === 'pendiente_firma', 409);

                $documento = $this->documentoFinalEmitido($registro);
                $snapshot = $documento->contenido_snapshot;
                abort_unless(! $noRequiereFirma || (bool) ($snapshot['permite_no_firma'] ?? false), 409);

                $fechaFirma = $noRequiereFirma ? null : CarbonImmutable::parse((string) $datos['fecha_firma']);
                $firma = TramiteFirma::query()->create([
                    'tramite_id' => $registro->id,
                    'documento_final_id' => $documento->id,
                    'firmante_id' => $documento->borrador?->firmante_id,
                    'registrado_por' => $actor->id,
                    'no_requiere_firma' => $noRequiereFirma,
                    'fecha_firma' => $fechaFirma,
                    'observacion' => $datos['observacion'] ?? null,
                    'disco' => $archivo['disco'] ?? null,
                    'ruta' => $archivo['ruta'] ?? null,
                    'nombre_original' => $archivo['nombre_original'] ?? null,
                    'mime_type' => $archivo['mime_type'] ?? null,
                    'tamano_bytes' => $archivo['tamano_bytes'] ?? null,
                    'sha256' => $archivo['sha256'] ?? null,
                ]);

                $actualizado = DB::table('tramites')
                    ->where('id', $registro->id)
                    ->where('estado', 'pendiente_firma')
                    ->update(['estado' => 'listo_entrega', 'updated_at' => now()]);

                abort_unless($actualizado === 1, 409);

                $this->registrarEvento(
                    $registro->id,
                    $actor->id,
                    'firma_registrada',
                    $noRequiereFirma ? 'La plantilla permite continuar sin firma física.' : 'Se registró la firma física del documento oficial.',
                    'pendiente_firma',
                    'listo_entrega',
                    ['firma_id' => $firma->id, 'evidencia' => $archivo !== null],
                );

                return $firma;
            });
        } catch (Throwable $exception) {
            $this->deleteUnpersistedFile($archivo, TramiteFirma::class);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registerDelivery(Tramite $tramite, User $actor, array $datos, ?UploadedFile $evidencia): TramiteEntrega
    {
        $this->authorizeStaff($actor);
        $archivo = $evidencia === null ? null : $this->storePrivateFile($evidencia, $tramite->id, 'evidencias-entrega');

        try {
            return DB::transaction(function () use ($tramite, $actor, $datos, $archivo): TramiteEntrega {
                $registro = Tramite::query()->findOrFail($tramite->id);
                abort_unless($registro->estado === 'listo_entrega', 409);
                abort_if(
                    TramiteEntrega::query()->where('tramite_id', $registro->id)->where('activa', true)->exists(),
                    409,
                );

                $documento = $this->documentoFinalEmitido($registro);
                $medio = TramiteMedioEntrega::query()
                    ->whereKey((int) $datos['medio_entrega_id'])
                    ->whereIn('codigo', TramiteMedioEntrega::CODIGOS_DISPONIBLES)
                    ->first();
                abort_unless($medio !== null, 422);

                if ($medio->codigo === 'correo_electronico' && blank($datos['correo_destino'] ?? null)) {
                    throw ValidationException::withMessages(['correo_destino' => 'Indique el correo de destino para la entrega digital.']);
                }

                if ($medio->tipo === 'digital' && blank($datos['correo_destino'] ?? null) && blank($datos['medio_utilizado'] ?? null)) {
                    throw ValidationException::withMessages(['medio_utilizado' => 'Indique el medio digital utilizado.']);
                }

                $tipoEvidencia = (string) ($datos['tipo_evidencia'] ?? '');

                if ($medio->requiere_evidencia && $archivo === null && ! in_array($tipoEvidencia, ['Código de confirmación', 'Confirmación manual'], true)) {
                    throw ValidationException::withMessages(['evidencia' => 'Este medio de entrega requiere una evidencia.']);
                }

                $entrega = TramiteEntrega::query()->create([
                    'tramite_id' => $registro->id,
                    'documento_final_id' => $documento->id,
                    'medio_entrega_id' => $medio->id,
                    'entregado_por' => $actor->id,
                    'receptor_usuario_id' => (int) $registro->propietario_id > 0 ? $registro->propietario_id : null,
                    'receptor_nombre' => trim((string) $datos['receptor_nombre']),
                    'receptor_documento' => $this->enmascararDocumento((string) ($datos['receptor_documento'] ?? '')),
                    'receptor_tipo' => $datos['receptor_tipo'],
                    'receptor_relacion' => $datos['receptor_relacion'] ?? null,
                    'correo_destino' => $datos['correo_destino'] ?? null,
                    'medio_utilizado' => $datos['medio_utilizado'] ?? ($medio->codigo === 'descarga_sistema' ? $medio->nombre : null),
                    'fecha_entrega' => CarbonImmutable::parse((string) $datos['fecha_entrega']),
                    'confirmado_por_estudiante' => false,
                    'confirmado' => false,
                    'codigo_confirmacion' => $this->codigoVerificacion(),
                    'observaciones' => $datos['observacion'] ?? null,
                    'estado' => 'registrada',
                    'activa' => true,
                ]);

                if ($archivo !== null || $tipoEvidencia !== '') {
                    $tipoEvidencia = $tipoEvidencia !== ''
                        ? $tipoEvidencia
                        : ($archivo['mime_type'] === 'application/pdf' ? 'Archivo PDF' : 'Imagen');

                    TramiteEvidenciaEntrega::query()->create([
                        'entrega_id' => $entrega->id,
                        'tramite_id' => $registro->id,
                        'documento_final_id' => $documento->id,
                        'tipo_evidencia' => $tipoEvidencia,
                        'nombre_original' => $archivo['nombre_original'] ?? null,
                        'disco' => $archivo['disco'] ?? null,
                        'ruta' => $archivo['ruta'] ?? null,
                        'mime_type' => $archivo['mime_type'] ?? null,
                        'tamano_bytes' => $archivo['tamano_bytes'] ?? null,
                        'sha256' => $archivo['sha256'] ?? null,
                        'codigo_confirmacion' => in_array($tipoEvidencia, ['Código de confirmación', 'Confirmación manual'], true)
                            ? $entrega->codigo_confirmacion
                            : null,
                        'registrado_por' => $actor->id,
                        'observacion' => $datos['observacion'] ?? null,
                        'activa' => true,
                    ]);
                }

                $this->registrarEvento(
                    $registro->id,
                    $actor->id,
                    'entrega_registrada',
                    'Se registró el medio de entrega; la recepción queda pendiente de confirmación.',
                    'listo_entrega',
                    'listo_entrega',
                    ['entrega_id' => $entrega->id, 'medio' => $medio->codigo, 'evidencia' => $archivo !== null || $tipoEvidencia !== ''],
                );

                return $entrega;
            });
        } catch (Throwable $exception) {
            $this->deleteUnpersistedFile($archivo, TramiteEvidenciaEntrega::class);

            throw $exception;
        }
    }

    public function confirmDelivery(Tramite $tramite, User $actor, ?string $observacion = null): TramiteEntrega
    {
        abort_unless($actor->activo && in_array($actor->rol, ['asistente', 'administrador', 'estudiante'], true), 403);

        return DB::transaction(function () use ($tramite, $actor, $observacion): TramiteEntrega {
            $registro = Tramite::query()->findOrFail($tramite->id);
            $esEstudiante = $actor->rol === 'estudiante';

            if ($esEstudiante && (int) $registro->propietario_id !== (int) $actor->id) {
                abort(403);
            }

            abort_unless($registro->estado === 'listo_entrega', 409);

            $entrega = TramiteEntrega::query()
                ->where('tramite_id', $registro->id)
                ->where('activa', true)
                ->first();
            abort_unless($entrega !== null && ! $entrega->confirmado, 409);

            $actualizado = DB::table('tramite_entregas')
                ->where('id', $entrega->id)
                ->where('activa', true)
                ->where('confirmado', false)
                ->update([
                    'confirmado' => true,
                    'confirmado_por_estudiante' => $esEstudiante,
                    'estado' => 'confirmada',
                    'updated_at' => now(),
                ]);
            abort_unless($actualizado === 1, 409);

            TramiteEvidenciaEntrega::query()->create([
                'entrega_id' => $entrega->id,
                'tramite_id' => $registro->id,
                'documento_final_id' => $entrega->documento_final_id,
                'tipo_evidencia' => 'Confirmación manual',
                'codigo_confirmacion' => $entrega->codigo_confirmacion,
                'registrado_por' => $actor->id,
                'observacion' => $observacion,
                'activa' => true,
            ]);

            $actualizado = DB::table('tramites')
                ->where('id', $registro->id)
                ->where('estado', 'listo_entrega')
                ->update(['estado' => 'entregado', 'updated_at' => now()]);
            abort_unless($actualizado === 1, 409);

            $this->registrarEvento(
                $registro->id,
                $actor->id,
                'recepcion_confirmada',
                'La recepción del documento oficial fue confirmada'.($esEstudiante ? ' por la persona interesada.' : ' por personal autorizado.'),
                'listo_entrega',
                'entregado',
                ['entrega_id' => $entrega->id, 'confirmado_por_interesado' => $esEstudiante],
            );

            return $entrega->refresh();
        });
    }

    public function annulDelivery(Tramite $tramite, TramiteEntrega $entrega, User $actor, string $motivo): void
    {
        abort_unless($actor->activo && $actor->rol === 'administrador', 403);
        abort_unless((int) $entrega->tramite_id === (int) $tramite->id, 404);

        DB::transaction(function () use ($tramite, $entrega, $actor, $motivo): void {
            abort_unless(Tramite::query()->whereKey($tramite->id)->where('estado', 'listo_entrega')->exists(), 409);

            $observaciones = trim((string) $entrega->observaciones);
            $observaciones .= ($observaciones === '' ? '' : ' | ').'Anulación: '.$motivo;
            $actualizado = DB::table('tramite_entregas')
                ->where('id', $entrega->id)
                ->where('tramite_id', $tramite->id)
                ->where('activa', true)
                ->where('confirmado', false)
                ->update([
                    'activa' => false,
                    'estado' => 'anulada',
                    'observaciones' => $observaciones,
                    'updated_at' => now(),
                ]);
            abort_unless($actualizado === 1, 409);

            DB::table('tramite_evidencias_entrega')
                ->where('entrega_id', $entrega->id)
                ->where('activa', true)
                ->update(['activa' => false, 'updated_at' => now()]);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'entrega_anulada',
                'Se anuló una entrega pendiente de confirmación.',
                'listo_entrega',
                'listo_entrega',
                ['entrega_id' => $entrega->id, 'motivo' => $motivo],
            );
        });
    }

    public function reopen(Tramite $tramite, User $actor, string $motivo): void
    {
        abort_unless($actor->activo && $actor->rol === 'administrador', 403);

        DB::transaction(function () use ($tramite, $actor, $motivo): void {
            abort_unless(Tramite::query()->whereKey($tramite->id)->where('estado', 'cerrado')->exists(), 409);

            $cierre = TramiteCierre::query()
                ->where('tramite_id', $tramite->id)
                ->where('activo', true)
                ->where('reabierto', false)
                ->first();
            abort_unless($cierre !== null, 409);

            $actualizado = DB::table('tramite_cierres')
                ->where('id', $cierre->id)
                ->where('activo', true)
                ->where('reabierto', false)
                ->update([
                    'activo' => false,
                    'reabierto' => true,
                    'motivo_reapertura' => $motivo,
                    'reabierto_por' => $actor->id,
                    'fecha_reapertura' => now(),
                    'updated_at' => now(),
                ]);
            abort_unless($actualizado === 1, 409);

            $informeActualizado = DB::table('tramite_informes_cierre')
                ->where('cierre_id', $cierre->id)
                ->where('activo', true)
                ->update(['activo' => false, 'updated_at' => now()]);
            abort_unless($informeActualizado === 1, 409);

            $tramiteActualizado = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'cerrado')
                ->update(['estado' => 'entregado', 'updated_at' => now()]);
            abort_unless($tramiteActualizado === 1, 409);

            $this->registrarEvento(
                $tramite->id,
                $actor->id,
                'expediente_reabierto',
                'El expediente fue reabierto por decisión administrativa.',
                'cerrado',
                'entregado',
                ['cierre_id' => $cierre->id, 'motivo' => $motivo],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function close(Tramite $tramite, User $actor, array $datos): TramiteInformeCierre
    {
        $this->authorizeStaff($actor);
        $ruta = null;

        try {
            return DB::transaction(function () use ($tramite, $actor, $datos, &$ruta): TramiteInformeCierre {
                $registro = Tramite::query()->findOrFail($tramite->id);
                abort_unless($registro->estado === 'entregado', 409);

                $entrega = TramiteEntrega::query()
                    ->with(['medio', 'evidencias'])
                    ->where('tramite_id', $registro->id)
                    ->where('activa', true)
                    ->where('confirmado', true)
                    ->first();
                abort_unless($entrega !== null, 409);

                $cierre = TramiteCierre::query()->create([
                    'tramite_id' => $registro->id,
                    'entrega_id' => $entrega->id,
                    'cerrado_por' => $actor->id,
                    'resumen' => trim((string) $datos['resumen']),
                    'observacion' => $datos['observacion'] ?? null,
                    'fecha_cierre' => now(),
                    'activo' => true,
                ]);

                $actualizado = DB::table('tramites')
                    ->where('id', $registro->id)
                    ->where('estado', 'entregado')
                    ->update(['estado' => 'cerrado', 'updated_at' => now()]);
                abort_unless($actualizado === 1, 409);

                $this->registrarEvento(
                    $registro->id,
                    $actor->id,
                    'expediente_cerrado',
                    'El expediente se cerró después de confirmar la recepción.',
                    'entregado',
                    'cerrado',
                    ['cierre_id' => $cierre->id, 'entrega_id' => $entrega->id],
                );

                $informeData = $this->datosInforme($registro, $entrega, $cierre, $actor);
                $pdf = $this->pdfDocumentGenerator->generateClosureReport($informeData);

                if (strlen($pdf['bytes']) < 500 || ! str_starts_with($pdf['bytes'], '%PDF-') || ! str_contains($pdf['bytes'], '%%EOF')) {
                    throw new RuntimeException('No se pudo generar el informe PDF de cierre.');
                }

                $ruta = 'informes-cierre/'.now()->format('Y').'/'.Str::slug($registro->codigo).'-'.Str::lower(Str::random(20)).'.pdf';
                $disk = Storage::disk('local');

                if (! $disk->put($ruta, $pdf['bytes'])) {
                    throw new RuntimeException('No se pudo almacenar el informe privado de cierre.');
                }

                $path = $this->verifiedPrivatePath($ruta, hash('sha256', $pdf['bytes']), true);
                $hash = hash_file('sha256', $path);
                $size = filesize($path);
                abort_unless(is_string($hash) && is_int($size), 500);

                $informe = TramiteInformeCierre::query()->create([
                    'tramite_id' => $registro->id,
                    'cierre_id' => $cierre->id,
                    'generado_por' => $actor->id,
                    'disco' => 'local',
                    'ruta' => $ruta,
                    'nombre_archivo' => basename($ruta),
                    'sha256' => $hash,
                    'tamano_bytes' => $size,
                    'numero_paginas' => $pdf['paginas'],
                    'codigo_verificacion' => $informeData['codigo_verificacion'],
                    'activo' => true,
                ]);

                $this->registrarEvento(
                    $registro->id,
                    $actor->id,
                    'informe_cierre_generado',
                    'Se generó y verificó el informe PDF de cierre.',
                    null,
                    null,
                    ['informe_id' => $informe->id, 'sha256' => $hash],
                );

                return $informe;
            });
        } catch (Throwable $exception) {
            if ($ruta !== null) {
                try {
                    $informeGuardado = TramiteInformeCierre::query()->where('ruta', $ruta)->exists();

                    if (! $informeGuardado) {
                        Storage::disk('local')->delete($ruta);
                    }
                } catch (Throwable) {
                    // Keep the file if Turso cannot confirm whether the transaction committed.
                }
            }

            throw $exception;
        }
    }

    public function verifiedPrivatePath(string $ruta, string $sha256, bool $pdf = false): string
    {
        $disk = Storage::disk('local');
        $root = realpath((string) config('filesystems.disks.local.root'));
        $path = realpath($disk->path($ruta));

        abort_unless(
            is_string($root)
                && is_string($path)
                && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                && is_file($path),
            404,
        );

        $hash = hash_file('sha256', $path);
        abort_unless(is_string($hash) && hash_equals($sha256, $hash), 404);

        if ($pdf) {
            $header = file_get_contents($path, false, null, 0, 5);
            $handle = fopen($path, 'rb');
            $handle !== false ? fseek($handle, -5, SEEK_END) : null;
            $tail = $handle === false ? false : fread($handle, 5);

            if ($handle !== false) {
                fclose($handle);
            }

            abort_unless($header === '%PDF-' && $tail === '%%EOF', 404);
        }

        return $path;
    }

    private function documentoFinalEmitido(Tramite $tramite): TramiteDocumentoFinal
    {
        $documento = $tramite->documentoFinalActual()
            ->where('estado', 'emitido')
            ->first();
        abort_unless($documento !== null && $documento->disco === 'local' && is_string($documento->ruta) && is_string($documento->sha256), 409);

        $this->verifiedPrivatePath($documento->ruta, $documento->sha256, true);

        return $documento->load('borrador');
    }

    /** @return array<string, mixed> */
    private function datosInforme(Tramite $tramite, TramiteEntrega $entrega, TramiteCierre $cierre, User $actor): array
    {
        $classificationLabels = TramiteClassificationCatalog::labels();
        $typeLabels = TramiteTypeCatalog::labels();
        $documento = TramiteDocumentoFinal::query()->with('rondaRevision.revisor')->findOrFail($entrega->documento_final_id);
        $ronda = $documento->rondaRevision;
        $eventos = TramiteEvento::query()
            ->where('tramite_id', $tramite->id)
            ->whereNotNull('estado_nuevo')
            ->orderBy('id')
            ->get(['estado_anterior', 'estado_nuevo', 'created_at']);

        return [
            'institucion' => (string) config('app.name', 'Sistema de Gestión Documentaria'),
            'codigo_expediente' => $tramite->codigo,
            'documento_oficial' => $documento->numero_documento,
            'clasificacion' => $classificationLabels[$tramite->clasificacion] ?? $tramite->clasificacion,
            'tipo_tramite' => $typeLabels[$tramite->tipo_documento] ?? $tramite->tipo_documento,
            'interesado' => $tramite->propietario?->name ?? $tramite->persona_nombre ?? 'No aplica',
            'recibido_en' => $tramite->fecha_recepcion->toDateString(),
            'revisor' => $ronda?->revisor?->name ?? 'No registrado',
            'resultado_revision' => config('tramites.estados.'.$ronda?->estado, $ronda?->estado ?? 'No registrado'),
            'medio_entrega' => $entrega->medio->nombre,
            'receptor' => $entrega->receptor_nombre.' · '.$entrega->receptor_tipo,
            'fecha_entrega' => $entrega->fecha_entrega->format('Y-m-d H:i'),
            'evidencias' => $entrega->evidencias->map(fn (TramiteEvidenciaEntrega $evidencia): string => $evidencia->tipo_evidencia)->unique()->values()->all(),
            'resumen_cierre' => $cierre->resumen,
            'observacion_cierre' => $cierre->observacion,
            'cerrado_por' => $actor->name,
            'fecha_cierre' => $cierre->fecha_cierre->format('Y-m-d H:i'),
            'linea_tiempo' => $eventos->map(fn (TramiteEvento $evento): array => [
                'fecha' => $evento->created_at?->format('Y-m-d H:i') ?? '',
                'estado_anterior' => $evento->estado_anterior === null
                    ? ''
                    : config('tramites.estados.'.$evento->estado_anterior, $evento->estado_anterior),
                'estado_nuevo' => config('tramites.estados.'.$evento->estado_nuevo, $evento->estado_nuevo),
            ])->all(),
            'codigo_verificacion' => $this->codigoVerificacion(),
        ];
    }

    /** @return array{disco: string, ruta: string, nombre_original: string, mime_type: string, tamano_bytes: int, sha256: string} */
    private function storePrivateFile(UploadedFile $file, int $tramiteId, string $section): array
    {
        $extension = $file->extension();
        abort_unless(is_string($extension) && $extension !== '', 422);

        $filename = Str::uuid().'.'.$extension;
        $ruta = $file->storeAs($section.'/'.now()->format('Y').'/'.$tramiteId, $filename, 'local');
        abort_unless(is_string($ruta), 500);

        try {
            $disk = Storage::disk('local');
            $root = realpath((string) config('filesystems.disks.local.root'));
            $path = realpath($disk->path($ruta));
            abort_unless(
                is_string($root)
                    && is_string($path)
                    && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                    && is_file($path),
                500,
            );

            $hash = hash_file('sha256', $path);
            $size = filesize($path);
            abort_unless(is_string($hash) && is_int($size) && $size > 0, 500);

            return [
                'disco' => 'local',
                'ruta' => $ruta,
                'nombre_original' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'tamano_bytes' => $size,
                'sha256' => $hash,
            ];
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($ruta);

            throw $exception;
        }
    }

    private function enmascararDocumento(string $documento): ?string
    {
        $digitos = preg_replace('/\D+/', '', $documento) ?? '';

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) > 12 || strlen($digitos) < 4) {
            throw ValidationException::withMessages(['receptor_documento' => 'El documento de identidad no es válido.']);
        }

        return str_repeat('*', max(0, strlen($digitos) - 3)).substr($digitos, -3);
    }

    private function codigoVerificacion(): string
    {
        return implode('-', str_split(Str::upper(bin2hex(random_bytes(8))), 4));
    }

    private function authorizeStaff(User $actor): void
    {
        abort_unless($actor->activo && in_array($actor->rol, ['asistente', 'administrador'], true), 403);
    }

    /** @param array<string, mixed>|null $metadatos */
    private function registrarEvento(int $tramiteId, int $actorId, string $accion, string $descripcion, ?string $estadoAnterior, ?string $estadoNuevo, ?array $metadatos = null): void
    {
        TramiteEvento::query()->create([
            'tramite_id' => $tramiteId,
            'usuario_id' => $actorId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'metadatos' => $metadatos,
        ]);
    }

    /** @param array<string, mixed>|null $archivo */
    private function deleteUnpersistedFile(?array $archivo, string $model): void
    {
        if ($archivo === null) {
            return;
        }

        try {
            $persistido = $model::query()->where('ruta', $archivo['ruta'])->exists();

            if (! $persistido) {
                Storage::disk('local')->delete($archivo['ruta']);
            }
        } catch (Throwable) {
            // Keep the file if Turso cannot confirm whether the transaction committed.
        }
    }
}
