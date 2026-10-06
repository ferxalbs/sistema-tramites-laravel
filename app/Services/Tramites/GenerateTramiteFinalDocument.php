<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteBorrador;
use App\Models\TramiteDocumentoFinal;
use App\Models\TramiteEvento;
use App\Models\TramiteNumeracionDocumental;
use App\Models\TramitePlantilla;
use App\Models\TramiteRondaRevision;
use App\Models\TramiteSerieDocumental;
use App\Models\User;
use App\Services\PdfDocumentGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class GenerateTramiteFinalDocument
{
    /**
     * Create a new class instance.
     */
    public function __construct(private PdfDocumentGenerator $pdfDocumentGenerator) {}

    public function execute(Tramite $tramite, User $actor): TramiteDocumentoFinal
    {
        abort_unless($actor->activo && $actor->rol === 'administrador', 403);
        if (in_array($tramite->tipo_documento, config('tramites.modelos_oficiales_pendientes', []), true)) {
            throw ValidationException::withMessages([
                'documento' => 'Falta el modelo institucional aprobado para este tipo de trámite. Se puede registrar y revisar el expediente, pero aún no emitir su PDF oficial.',
            ]);
        }
        $reserva = $this->reservarNumeracion($tramite, $actor);
        $ruta = null;

        try {
            $pdf = $this->pdfDocumentGenerator->generate([
                ...$reserva['snapshot'],
                'firma_imagen' => $reserva['firma_imagen'],
            ]);

            if (strlen($pdf['bytes']) < 500 || ! str_starts_with($pdf['bytes'], '%PDF-') || ! str_contains($pdf['bytes'], '%%EOF')) {
                throw new RuntimeException('El generador no produjo un PDF válido.');
            }

            $ruta = $this->rutaPrivada($reserva['snapshot']['codigo_expediente'], $reserva['numero']);
            $disco = Storage::disk('local');

            if (! $disco->put($ruta, $pdf['bytes'])) {
                throw new RuntimeException('No fue posible almacenar el documento oficial.');
            }

            $raiz = realpath((string) config('filesystems.disks.local.root'));
            $archivo = realpath($disco->path($ruta));

            if (! is_string($raiz) || ! is_string($archivo) || ! str_starts_with($archivo, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('El documento oficial quedó fuera del almacenamiento privado.');
            }

            $hash = hash_file('sha256', $archivo);
            $tamano = filesize($archivo);

            if (! is_string($hash) || ! is_int($tamano) || $tamano < 500) {
                throw new RuntimeException('No fue posible verificar la integridad del documento oficial.');
            }

        } catch (HttpExceptionInterface $exception) {
            $this->limpiarArchivo($ruta);
            $this->marcarFallo($reserva, $actor, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->limpiarArchivo($ruta);
            $this->marcarFallo($reserva, $actor, $exception);

            throw new RuntimeException('No se pudo generar el documento oficial. El correlativo quedó consumido.', previous: $exception);
        }

        try {
            return DB::transaction(function () use ($reserva, $ruta, $hash, $tamano, $pdf, $actor): TramiteDocumentoFinal {
                $actualizado = DB::table('tramite_documentos_finales')
                    ->where('id', $reserva['documento_id'])
                    ->where('estado', 'generando')
                    ->update([
                        'estado' => 'emitido',
                        'ruta' => $ruta,
                        'nombre_archivo' => basename($ruta),
                        'sha256' => $hash,
                        'tamano_bytes' => $tamano,
                        'numero_paginas' => $pdf['paginas'],
                        'fecha_emision' => now(),
                        'updated_at' => now(),
                    ]);

                abort_unless($actualizado === 1, 409);

                $actualizado = DB::table('tramite_numeraciones_documentales')
                    ->where('id', $reserva['numeracion_id'])
                    ->where('estado', 'reservada')
                    ->update(['estado' => 'emitida', 'updated_at' => now()]);

                abort_unless($actualizado === 1, 409);

                $actualizado = DB::table('tramites')
                    ->where('id', $reserva['tramite_id'])
                    ->where('estado', $reserva['estado_decision'])
                    ->update(['estado' => 'documento_final_generado', 'updated_at' => now()]);

                abort_unless($actualizado === 1, 409);

                TramiteEvento::query()->create([
                    'tramite_id' => $reserva['tramite_id'],
                    'usuario_id' => $actor->id,
                    'accion' => 'documento_final_emitido',
                    'descripcion' => 'Se emitió el documento oficial '.$reserva['numero'].'.',
                    'estado_anterior' => $reserva['estado_decision'],
                    'estado_nuevo' => 'documento_final_generado',
                    'metadatos' => [
                        'documento_id' => $reserva['documento_id'],
                        'numeracion_id' => $reserva['numeracion_id'],
                        'version_borrador' => $reserva['version_borrador'],
                        'sha256' => $hash,
                    ],
                ]);

                return TramiteDocumentoFinal::query()->findOrFail($reserva['documento_id']);
            });
        } catch (Throwable $exception) {
            try {
                $documento = TramiteDocumentoFinal::query()->find($reserva['documento_id']);
                $numeroEmitido = TramiteNumeracionDocumental::query()
                    ->whereKey($reserva['numeracion_id'])
                    ->where('estado', 'emitida')
                    ->exists();
                $archivo = Storage::disk('local')->path($ruta);

                if ($documento?->estado === 'emitido'
                    && $documento->activo
                    && $documento->ruta === $ruta
                    && $documento->sha256 === $hash
                    && $numeroEmitido
                    && is_file($archivo)
                    && hash_equals($hash, (string) hash_file('sha256', $archivo))) {
                    return $documento;
                }
            } catch (Throwable) {
                // Una lectura fallida tampoco autoriza borrar el archivo ni liberar la reserva.
            }

            throw new RuntimeException('No se pudo confirmar la emisión. Consulte el expediente antes de reintentar: la reserva y el archivo se conservaron para reconciliación.', previous: $exception);
        }
    }

    /**
     * Render an existing draft for review without reserving a document number,
     * creating a history record, or changing any workflow state.
     *
     * @return array{bytes: string, paginas: int}
     */
    public function preview(Tramite $tramite, TramiteBorrador $borrador, User $actor): array
    {
        abort_unless($actor->activo && $actor->rol === 'administrador', 403);
        abort_unless((int) $borrador->tramite_id === (int) $tramite->id, 404);

        $borrador->loadMissing(['plantilla', 'remitente', 'firmante']);
        $plantilla = $borrador->plantilla;
        abort_unless($plantilla instanceof TramitePlantilla, 409);

        $remitente = $borrador->remitente ?? $actor;
        $firmante = $borrador->firmante ?? $actor;
        $rutaFirma = 'firmas-perfil/'.$firmante->id.'.jpg';
        $firmaImagen = Storage::disk('local')->exists($rutaFirma)
            ? Storage::disk('local')->get($rutaFirma)
            : null;
        $snapshot = $this->crearSnapshot(
            $tramite,
            $borrador,
            null,
            $remitente,
            $firmante,
            'VISTA PREVIA (SIN NUMERAR)',
            '',
            hash('sha256', $firmaImagen ?? ''),
            false,
        );
        $pdf = $this->pdfDocumentGenerator->generate([
            ...$snapshot,
            'firma_imagen' => $firmaImagen,
        ]);

        if (strlen($pdf['bytes']) < 500 || ! str_starts_with($pdf['bytes'], '%PDF-') || ! str_contains($pdf['bytes'], '%%EOF')) {
            throw new RuntimeException('El generador no produjo un PDF válido para la vista previa.');
        }

        return $pdf;
    }

    /**
     * @return array{
     *     tramite_id: int,
     *     documento_id: int,
     *     numeracion_id: int,
     *     numero: string,
     *     version_borrador: int,
     *     estado_decision: string,
     *     firma_imagen: string,
     *     snapshot: array{
     *         institucion: string,
     *         tipo_documento: string,
     *         numero: string,
     *         codigo_expediente: string,
     *         fecha_documento: string,
     *         asunto: string,
     *         destinatarios: list<string>,
     *         remitente: string,
     *         introduccion: ?string,
     *         contenido_principal: ?string,
     *         cierre: ?string,
     *         personas: list<string>,
     *         firmante: string,
     *         decision: string,
     *         conclusion: ?string,
     *         comentario_publico: ?string,
     *         codigo_verificacion: string,
     *         version_borrador: int
     *     }
     * }
     */
    private function reservarNumeracion(Tramite $tramite, User $actor): array
    {
        return DB::transaction(function () use ($tramite, $actor): array {
            $registro = Tramite::query()->whereKey($tramite->id)->firstOrFail();
            abort_unless(in_array($registro->estado, ['aprobado', 'rechazado'], true), 409);

            $tieneDocumentoActivo = DB::table('tramite_documentos_finales')
                ->where('tramite_id', $registro->id)
                ->where('activo', true)
                ->whereIn('estado', ['generando', 'emitido'])
                ->exists();

            abort_unless(! $tieneDocumentoActivo, 409);

            $ronda = TramiteRondaRevision::query()
                ->where('tramite_id', $registro->id)
                ->where('estado', $registro->estado)
                ->where('activa', false)
                ->orderByDesc('numero_ronda')
                ->first();

            abort_unless($ronda !== null, 409);

            $borrador = TramiteBorrador::query()
                ->with(['plantilla', 'remitente', 'firmante'])
                ->whereKey($ronda->borrador_id)
                ->where('tramite_id', $registro->id)
                ->where('estado', 'preparado_asignacion')
                ->first();

            abort_unless($borrador !== null, 409);
            $plantilla = $borrador->plantilla;
            $remitente = $borrador->remitente;
            $firmante = $borrador->firmante;

            abort_unless($plantilla !== null && $remitente !== null && $firmante !== null, 409);

            $rutaFirma = 'firmas-perfil/'.$firmante->id.'.jpg';

            if (! Storage::disk('local')->exists($rutaFirma)) {
                throw ValidationException::withMessages([
                    'firmante_id' => 'El firmante debe registrar su firma escaneada desde Mi perfil antes de emitir el documento.',
                ]);
            }

            $firmaImagen = Storage::disk('local')->get($rutaFirma);

            if ($ronda->estado === 'rechazado' && trim((string) $ronda->comentario_publico) === '') {
                throw ValidationException::withMessages(['documento' => 'El rechazo necesita un fundamento público antes de emitir el documento.']);
            }

            $modalidad = $plantilla->modalidad ?? 'unica';
            $configuracion = collect(config('tramites.series_documentales', []))->first(
                fn (array $serie): bool => $serie['tipo_documento_salida'] === $plantilla->tipo_documento_salida
                    && $serie['modalidad'] === $modalidad,
            );

            if (! is_array($configuracion)) {
                throw ValidationException::withMessages(['documento' => 'La plantilla no tiene una serie documental configurada.']);
            }

            $anio = (int) now()->format('Y');
            $ahora = now()->toDateTimeString();

            DB::table('tramite_series_documentales')->insertOrIgnore([
                'tipo_documento_salida' => $configuracion['tipo_documento_salida'],
                'modalidad' => $configuracion['modalidad'],
                'anio' => $anio,
                'codigo' => $configuracion['codigo'],
                'prefijo' => $configuracion['prefijo'],
                'ultimo_correlativo' => 0,
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $serie = TramiteSerieDocumental::query()
                ->where('tipo_documento_salida', $plantilla->tipo_documento_salida)
                ->where('modalidad', $modalidad)
                ->where('anio', $anio)
                ->where('activa', true)
                ->first();

            abort_unless($serie !== null, 409);

            $secuencia = DB::selectOne(
                'UPDATE tramite_series_documentales SET ultimo_correlativo = ultimo_correlativo + 1, updated_at = ? WHERE id = ? AND activa = 1 RETURNING ultimo_correlativo',
                [$ahora, $serie->id],
            );

            if ($secuencia === null) {
                throw new RuntimeException('No se pudo reservar el correlativo oficial.');
            }

            $correlativo = (int) $secuencia->ultimo_correlativo;
            $numero = $this->formatearNumero($serie, $configuracion, $anio, $correlativo);
            $codigoVerificacion = implode('-', str_split(Str::upper(bin2hex(random_bytes(8))), 4));
            $snapshot = $this->crearSnapshot($registro, $borrador, $ronda, $remitente, $firmante, $numero, $codigoVerificacion, hash('sha256', $firmaImagen));

            $numeracion = TramiteNumeracionDocumental::query()->create([
                'serie_id' => $serie->id,
                'tramite_id' => $registro->id,
                'borrador_id' => $borrador->id,
                'ronda_revision_id' => $ronda->id,
                'anio' => $anio,
                'correlativo' => $correlativo,
                'numero_completo' => $numero,
                'estado' => 'reservada',
                'reservada_por' => $actor->id,
            ]);

            $version = ((int) TramiteDocumentoFinal::query()->where('tramite_id', $registro->id)->max('version')) + 1;
            $documentoAnteriorId = TramiteDocumentoFinal::query()
                ->where('tramite_id', $registro->id)
                ->orderByDesc('version')
                ->value('id');
            $documento = TramiteDocumentoFinal::query()->create([
                'tramite_id' => $registro->id,
                'numeracion_id' => $numeracion->id,
                'documento_anterior_id' => $documentoAnteriorId,
                'borrador_id' => $borrador->id,
                'ronda_revision_id' => $ronda->id,
                'version' => $version,
                'tipo_documento' => $plantilla->tipo_documento_salida,
                'numero_documento' => $numero,
                'codigo_verificacion' => $codigoVerificacion,
                'estado' => 'generando',
                'activo' => true,
                'contenido_snapshot' => $snapshot,
                'generado_por' => $actor->id,
            ]);

            TramiteEvento::query()->create([
                'tramite_id' => $registro->id,
                'usuario_id' => $actor->id,
                'accion' => 'numero_reservado',
                'descripcion' => 'Se reservó el número oficial '.$numero.'.',
                'metadatos' => [
                    'numeracion_id' => $numeracion->id,
                    'documento_id' => $documento->id,
                    'correlativo' => $correlativo,
                ],
            ]);

            return [
                'tramite_id' => (int) $registro->id,
                'documento_id' => (int) $documento->id,
                'numeracion_id' => (int) $numeracion->id,
                'numero' => $numero,
                'version_borrador' => (int) $borrador->version,
                'estado_decision' => $registro->estado,
                'snapshot' => $snapshot,
                'firma_imagen' => $firmaImagen,
            ];
        });
    }

    /**
     * @return array{
     *     institucion: string,
     *     tipo_documento: string,
     *     tipo_documento_salida: string,
     *     modalidad_documento: ?string,
     *     numero: string,
     *     codigo_expediente: string,
     *     fecha_documento: string,
     *     lugar?: string,
     *     asunto: string,
     *     destinatarios: list<string>,
     *     destinatarios_detalle?: list<array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>,
     *     remitente_nombre?: string,
     *     remitente_cargo?: ?string,
     *     remitente: string,
     *     introduccion: ?string,
     *     contenido_principal: ?string,
     *     cierre: ?string,
     *     personas: list<string>,
     *     personas_detalle?: list<array{nombres: string, apellidos: ?string, cargo: ?string, dni: ?string}>,
     *     firmante_nombre?: string,
     *     firmante_cargo?: ?string,
     *     firmante: string,
     *     firmante_id: int,
     *     firma_perfil_sha256: string,
     *     decision: string,
     *     conclusion: ?string,
     *     comentario_publico: ?string,
     *     codigo_verificacion: string,
     *     version_borrador: int,
     *     contenido_renderizado: ?string,
     *     requiere_firma_fisica: bool,
     *     plantilla: string,
     *     personas_titulo: string,
     *     es_vista_previa: bool
     * }
     */
    private function crearSnapshot(Tramite $tramite, TramiteBorrador $borrador, ?TramiteRondaRevision $ronda, User $remitente, User $firmante, string $numero, string $codigoVerificacion, string $firmaHash, bool $requiereDestinatarios = true): array
    {
        $destinatarios = array_values(array_filter(array_map(
            static fn (array $destinatario): string => trim(implode(' ', array_filter([
                $destinatario['nombres'] ?? null,
                $destinatario['apellidos'] ?? null,
                $destinatario['cargo'] ?? $destinatario['cargo_texto'] ?? null,
            ]))),
            $borrador->destinatarios ?? [],
        )));

        if ($destinatarios === [] && $requiereDestinatarios) {
            throw ValidationException::withMessages(['documento' => 'El borrador no contiene destinatarios para el documento oficial.']);
        }

        if ($destinatarios === []) {
            $destinatarios = ['Destinatario pendiente'];
        }

        $personas = array_values(array_filter(array_map(
            static fn (array $persona): string => trim(implode(' ', array_filter([
                $persona['nombres'] ?? null,
                $persona['apellidos'] ?? null,
                $persona['cargo'] ?? null,
                isset($persona['dni']) && $persona['dni'] !== '' ? 'DNI: '.$persona['dni'] : null,
            ]))),
            $borrador->personas_mencionadas ?? [],
        )));
        $destinatariosDetalle = array_map(
            static fn (array $destinatario): array => [
                'nombres' => trim((string) ($destinatario['nombres'] ?? '')),
                'apellidos' => isset($destinatario['apellidos']) ? trim((string) $destinatario['apellidos']) ?: null : null,
                'cargo' => isset($destinatario['cargo']) ? trim((string) $destinatario['cargo']) ?: null : null,
                'correo' => isset($destinatario['correo']) ? trim((string) $destinatario['correo']) ?: null : null,
                'principal' => (bool) ($destinatario['principal'] ?? false),
            ],
            $borrador->destinatarios ?? [],
        );
        $destinatariosDetalle = array_values(array_filter(
            $destinatariosDetalle,
            static fn (array $destinatario): bool => $destinatario['nombres'] !== '',
        ));
        $personasDetalle = array_map(
            static fn (array $persona): array => [
                'nombres' => trim((string) ($persona['nombres'] ?? '')),
                'apellidos' => isset($persona['apellidos']) ? trim((string) $persona['apellidos']) ?: null : null,
                'cargo' => isset($persona['cargo']) ? trim((string) $persona['cargo']) ?: null : null,
                'dni' => isset($persona['dni']) ? trim((string) $persona['dni']) ?: null : null,
            ],
            $borrador->personas_mencionadas ?? [],
        );
        $personasDetalle = array_values(array_filter(
            $personasDetalle,
            static fn (array $persona): bool => $persona['nombres'] !== '',
        ));
        $cargos = DB::table('cargos_institucionales')
            ->whereIn('id', array_filter([$remitente->cargo_institucional_id, $firmante->cargo_institucional_id]))
            ->pluck('nombre', 'id');
        $valoresGuardados = DB::table('tramite_borrador_valores as valor')
            ->join('tramite_plantilla_campos as campo', 'campo.id', '=', 'valor.campo_id')
            ->where('valor.borrador_id', $borrador->id)
            ->whereIn('campo.clave_variable', ['REMITENTE_NOMBRE', 'FIRMANTE_NOMBRE', 'REMITENTE_CARGO', 'FIRMANTE_CARGO'])
            ->pluck('valor.valor', 'campo.clave_variable');
        $remitenteNombre = trim((string) $valoresGuardados->get('REMITENTE_NOMBRE', $remitente->name));
        $firmanteNombre = trim((string) $valoresGuardados->get('FIRMANTE_NOMBRE', $firmante->name));
        $remitenteCargo = $valoresGuardados->get('REMITENTE_CARGO', $cargos->get($remitente->cargo_institucional_id));
        $firmanteCargo = $valoresGuardados->get('FIRMANTE_CARGO', $cargos->get($firmante->cargo_institucional_id));
        $roles = [
            'administrador' => 'Administración',
            'asistente' => 'Asistente de oficina',
            'docente' => 'Docente',
        ];

        return [
            'institucion' => (string) config('app.name', 'Sistema de Gestión Documentaria'),
            'tipo_documento' => $borrador->plantilla->nombre,
            'tipo_documento_salida' => $borrador->plantilla->tipo_documento_salida,
            'modalidad_documento' => $borrador->plantilla->modalidad,
            'numero' => $numero,
            'codigo_expediente' => $tramite->codigo,
            'fecha_documento' => $borrador->fecha_documento->format('d/m/Y'),
            'lugar' => $borrador->lugar,
            'asunto' => $borrador->asunto,
            'destinatarios' => $destinatarios,
            'destinatarios_detalle' => $destinatariosDetalle,
            'remitente_nombre' => $remitenteNombre,
            'remitente_cargo' => $remitenteCargo,
            'remitente' => $remitenteNombre.' · '.($roles[$remitente->rol] ?? $remitente->rol),
            'introduccion' => $borrador->introduccion,
            'contenido_principal' => $borrador->contenido_principal,
            'cierre' => $borrador->cierre,
            'personas' => $personas,
            'personas_detalle' => $personasDetalle,
            'firmante_nombre' => $firmanteNombre,
            'firmante_cargo' => $firmanteCargo,
            'firmante' => $firmanteNombre.' · '.($roles[$firmante->rol] ?? $firmante->rol),
            'firmante_id' => (int) $firmante->id,
            'firma_perfil_sha256' => $firmaHash,
            'decision' => $ronda?->estado ?? 'previsualizacion',
            'conclusion' => $ronda?->conclusion,
            'comentario_publico' => $ronda?->comentario_publico,
            'codigo_verificacion' => $codigoVerificacion,
            'version_borrador' => (int) $borrador->version,
            'contenido_renderizado' => $borrador->contenido_renderizado,
            'borrador_renderizado_sha256' => $borrador->contenido_renderizado === null
                ? null : hash('sha256', $borrador->contenido_renderizado),
            'requiere_firma_fisica' => false,
            'plantilla' => $borrador->plantilla->nombre,
            'personas_titulo' => str_contains(mb_strtolower($borrador->asunto), 'ingres')
                ? 'Las personas que ingresarán son:'
                : 'Personas relacionadas:',
            'es_vista_previa' => $ronda === null,
        ];
    }

    private function rutaPrivada(string $codigoExpediente, string $numero): string
    {
        $nombreSeguro = Str::slug($codigoExpediente.'-'.$numero).'-'.Str::lower(Str::random(12)).'.pdf';

        return 'documentos-finales/'.now()->format('Y').'/'.$nombreSeguro;
    }

    /**
     * Keep the legacy prefix-year-correlative format as the fallback for
     * existing and custom series while allowing new institutional documents
     * to use a configured number format.
     *
     * @param  array<string, mixed>  $configuracion
     */
    private function formatearNumero(object $serie, array $configuracion, int $anio, int $correlativo): string
    {
        $formato = $configuracion['numero_formato'] ?? null;

        if (! is_string($formato) || trim($formato) === '') {
            return $serie->prefijo.'-'.$anio.'-'.str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
        }

        $relleno = max(1, (int) ($configuracion['correlativo_relleno'] ?? 6));
        $valorCorrelativo = str_pad((string) $correlativo, $relleno, '0', STR_PAD_LEFT);

        return trim(strtr($formato, [
            '{prefijo}' => (string) $serie->prefijo,
            '{correlativo}' => $valorCorrelativo,
            '{anio}' => (string) $anio,
            '{codigo_institucional}' => trim((string) ($configuracion['codigo_institucional'] ?? '')),
        ]));
    }

    private function limpiarArchivo(?string $ruta): void
    {
        if ($ruta === null) {
            return;
        }

        try {
            Storage::disk('local')->delete($ruta);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $reserva */
    private function marcarFallo(array $reserva, User $actor, Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($reserva, $actor, $exception): void {
                $mensaje = Str::limit($exception::class.': '.$exception->getMessage(), 1000);
                $ahora = now();

                DB::table('tramite_numeraciones_documentales')
                    ->where('id', $reserva['numeracion_id'])
                    ->where('estado', 'reservada')
                    ->update(['estado' => 'fallida', 'error_generacion' => $mensaje, 'updated_at' => $ahora]);

                $actualizado = DB::table('tramite_documentos_finales')
                    ->where('id', $reserva['documento_id'])
                    ->where('estado', 'generando')
                    ->update([
                        'estado' => 'fallido',
                        'activo' => false,
                        'error_generacion' => $mensaje,
                        'updated_at' => $ahora,
                    ]);

                if ($actualizado === 1) {
                    TramiteEvento::query()->create([
                        'tramite_id' => $reserva['tramite_id'],
                        'usuario_id' => $actor->id,
                        'accion' => 'emision_pdf_fallida',
                        'descripcion' => 'No se pudo generar el documento oficial; el correlativo quedó consumido.',
                        'metadatos' => [
                            'documento_id' => $reserva['documento_id'],
                            'numeracion_id' => $reserva['numeracion_id'],
                        ],
                    ]);
                }
            });
        } catch (Throwable $persistException) {
            report($persistException);
        }
    }
}
