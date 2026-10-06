<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;
use RuntimeException;
use Throwable;

class PdfDocumentGenerator
{
    /**
     * @param  array{
     *     institucion: string,
     *     codigo_expediente: string,
     *     documento_oficial: string,
     *     clasificacion: string,
     *     tipo_tramite: string,
     *     interesado: string,
     *     recibido_en: string,
     *     revisor: string,
     *     resultado_revision: string,
     *     medio_entrega: string,
     *     receptor: string,
     *     fecha_entrega: string,
     *     evidencias: list<string>,
     *     resumen_cierre: string,
     *     observacion_cierre: ?string,
     *     cerrado_por: string,
     *     fecha_cierre: string,
     *     linea_tiempo: list<array{fecha: string, estado_anterior: string, estado_nuevo: string}>,
     *     codigo_verificacion: string
     * }  $informe
     * @return array{bytes: string, paginas: int}
     */
    public function generateClosureReport(array $informe): array
    {
        $lineas = [];
        $this->agregarParrafo($lineas, $informe['institucion'], 10, true, 4, true);
        $this->agregarParrafo($lineas, 'GESTIÓN DOCUMENTARIA INSTITUCIONAL', 9, false, 18, true);
        $this->agregarParrafo($lineas, 'INFORME FINAL ADMINISTRATIVO DEL EXPEDIENTE', 14, true, 8, true);
        $this->agregarParrafo($lineas, $informe['codigo_expediente'], 11, true, 16, true);
        $this->agregarParrafo($lineas, 'IDENTIFICACIÓN', 11, true, 4);
        $this->agregarParrafo($lineas, 'Documento oficial: '.$informe['documento_oficial']);
        $this->agregarParrafo($lineas, 'Clasificación: '.$informe['clasificacion']);
        $this->agregarParrafo($lineas, 'Tipo de trámite: '.$informe['tipo_tramite']);
        $this->agregarParrafo($lineas, 'Interesado: '.$informe['interesado'], 10, false, 12);
        $this->agregarParrafo($lineas, 'TRAMITACIÓN Y REVISIÓN', 11, true, 4);
        $this->agregarParrafo($lineas, 'Recepción: '.$informe['recibido_en']);
        $this->agregarParrafo($lineas, 'Revisor: '.$informe['revisor']);
        $this->agregarParrafo($lineas, 'Resultado de revisión: '.$informe['resultado_revision'], 10, false, 12);
        $this->agregarParrafo($lineas, 'ENTREGA Y CIERRE', 11, true, 4);
        $this->agregarParrafo($lineas, 'Medio: '.$informe['medio_entrega']);
        $this->agregarParrafo($lineas, 'Receptor: '.$informe['receptor']);
        $this->agregarParrafo($lineas, 'Fecha de entrega: '.$informe['fecha_entrega']);
        $this->agregarParrafo($lineas, 'Evidencias: '.($informe['evidencias'] === [] ? 'Sin archivo adicional' : implode(', ', $informe['evidencias'])), 10, false, 12);
        $this->agregarParrafo($lineas, 'Resumen de cierre', 11, true, 4);
        $this->agregarParrafo($lineas, $informe['resumen_cierre'], 10, false, 8);
        $this->agregarSeccion($lineas, 'Observación de cierre', $informe['observacion_cierre']);
        $this->agregarParrafo($lineas, 'Cerrado por: '.$informe['cerrado_por']);
        $this->agregarParrafo($lineas, 'Fecha de cierre: '.$informe['fecha_cierre'], 10, false, 12);
        $this->agregarParrafo($lineas, 'LÍNEA DE TIEMPO RESUMIDA', 11, true, 4);

        foreach ($informe['linea_tiempo'] as $evento) {
            $this->agregarParrafo(
                $lineas,
                $evento['fecha'].' · '.($evento['estado_anterior'] === '' ? 'Inicio' : $evento['estado_anterior']).' → '.$evento['estado_nuevo'],
                9,
                false,
                2,
            );
        }

        $this->agregarParrafo($lineas, 'Código de verificación: '.$informe['codigo_verificacion'], 9, true, 0);
        $paginas = $this->distribuirEnPaginas($lineas, $informe['codigo_expediente'], $informe['codigo_verificacion']);

        return [
            'bytes' => $this->construirPdf($paginas, 'CIERRE-'.$informe['codigo_expediente'], $informe['institucion']),
            'paginas' => count($paginas),
        ];
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array{bytes: string, paginas: int}
     */
    public function generate(array $documento): array
    {
        $firmaImagen = is_string($documento['firma_imagen'] ?? null) ? $documento['firma_imagen'] : null;

        if ($this->esMemorando($documento)) {
            return $this->generarMemorando($documento, $firmaImagen);
        }

        if (($documento['tipo_tramite'] ?? null) === 'CONSTANCIA_MODALIDAD_TITULACION'
            && ($documento['tipo_documento_salida'] ?? null) === 'constancia'
            && filled($documento['contenido_renderizado'] ?? null)) {
            return $this->generarConstanciaTitulacion($documento, $firmaImagen);
        }

        if (filled($documento['contenido_renderizado'] ?? null)) {
            return $this->generarDesdePlantilla($documento, $firmaImagen);
        }

        $lineas = [];
        $this->agregarParrafo($lineas, $documento['institucion'], 10, true, 4, true);
        $this->agregarParrafo($lineas, 'GESTIÓN DOCUMENTARIA INSTITUCIONAL', 9, false, 18, true);
        $this->agregarParrafo($lineas, mb_strtoupper($documento['tipo_documento']), 15, true, 4, true);
        $this->agregarParrafo($lineas, $documento['numero'], 12, true, 18, true);
        $this->agregarParrafo($lineas, 'Expediente: '.$documento['codigo_expediente']);
        $this->agregarParrafo($lineas, 'Fecha: '.$documento['fecha_documento']);
        $this->agregarParrafo($lineas, 'Asunto: '.$documento['asunto'], 10, false, 12);

        foreach ($documento['destinatarios'] as $destinatario) {
            $this->agregarParrafo($lineas, 'A: '.$destinatario);
        }

        $this->agregarParrafo($lineas, 'De: '.$documento['remitente'], 10, false, 12);
        $this->agregarSeccion($lineas, 'Introducción', $documento['introduccion']);
        $this->agregarSeccion($lineas, 'Contenido', $documento['contenido_principal']);
        $this->agregarSeccion($lineas, 'Cierre', $documento['cierre']);

        if ($documento['personas'] !== []) {
            $this->agregarParrafo($lineas, 'Personas mencionadas', 11, true, 5);

            foreach ($documento['personas'] as $persona) {
                $this->agregarParrafo($lineas, '• '.$persona, 9, false, 2);
            }

            $this->agregarParrafo($lineas, '', 9, false, 8);
        }

        if ($documento['decision'] === 'rechazado') {
            $this->agregarSeccion($lineas, 'Resultado de la evaluación', 'Rechazado');
            $this->agregarSeccion($lineas, 'Fundamento', $documento['conclusion']);
            $this->agregarSeccion($lineas, 'Comunicación al interesado', $documento['comentario_publico']);
        }

        $this->agregarParrafo($lineas, 'Atentamente,', 10, false, $firmaImagen === null ? 22 : 4);

        if ($firmaImagen !== null) {
            $lineas[] = ['firma' => true, 'alto' => 42];
        }

        $this->agregarParrafo($lineas, '________________________________________', 10, false, 4);
        $this->agregarParrafo($lineas, $documento['firmante'], 10, true, 2);
        $this->agregarParrafo($lineas, 'Versión revisada del borrador: '.$documento['version_borrador'], 8, false, 0);

        $qr = $this->matrizVerificacion($documento['codigo_verificacion']);
        $paginas = $this->distribuirEnPaginas($lineas, $documento['codigo_expediente'], $documento['codigo_verificacion'], $qr !== null);

        return [
            'bytes' => $this->construirPdf($paginas, $documento['numero'], $documento['institucion'], $qr, $firmaImagen),
            'paginas' => count($paginas),
        ];
    }

    /**
     * The approved draft is the source of truth for non-memorandum documents.
     * Keep its resolved text in the issued PDF instead of rebuilding a generic body.
     *
     * @param  array<string, mixed>  $documento
     * @return array{bytes: string, paginas: int}
     */
    private function generarDesdePlantilla(array $documento, ?string $firmaImagen): array
    {
        $lineas = [];
        $this->agregarParrafo($lineas, (string) $documento['institucion'], 10, true, 4, true);
        $this->agregarParrafo($lineas, (string) $documento['numero'], 12, true, 10, true);

        $contenido = trim((string) $documento['contenido_renderizado']);
        $firmante = trim((string) ($documento['firmante_nombre'] ?? $documento['firmante'] ?? ''));
        if ($firmante !== '') {
            $contenido = preg_replace('/\R\s*'.preg_quote($firmante, '/').'\s*\z/u', '', $contenido) ?? $contenido;
        }

        foreach (preg_split('/\R/u', trim($contenido)) ?: [] as $indice => $parrafo) {
            $this->agregarParrafo($lineas, $parrafo, $indice === 0 ? 14 : 10, $indice === 0, $parrafo === '' ? 6 : 4, $indice === 0);
        }

        if (($documento['decision'] ?? null) === 'rechazado') {
            $this->agregarSeccion($lineas, 'Resultado de la evaluación', 'Rechazado');
            $this->agregarSeccion($lineas, 'Fundamento', $documento['conclusion'] ?? null);
            $this->agregarSeccion($lineas, 'Comunicación al interesado', $documento['comentario_publico'] ?? null);
        }

        if ($firmaImagen !== null) {
            $lineas[] = ['firma' => true, 'alto' => 42];
        }
        $this->agregarParrafo($lineas, '________________________________________', 10, false, 4);
        $this->agregarParrafo($lineas, $firmante, 10, true, 2);
        if (filled($documento['firmante_cargo'] ?? null)) {
            $this->agregarParrafo($lineas, (string) $documento['firmante_cargo'], 9, false, 4);
        }

        $qr = $this->matrizVerificacion((string) $documento['codigo_verificacion']);
        $paginas = $this->distribuirEnPaginas($lineas, (string) $documento['codigo_expediente'], (string) $documento['codigo_verificacion'], true);

        return [
            'bytes' => $this->construirPdf($paginas, (string) $documento['numero'], (string) $documento['institucion'], $qr, $firmaImagen),
            'paginas' => count($paginas),
        ];
    }

    private function esMemorando(array $documento): bool
    {
        $tipoSalida = trim((string) ($documento['tipo_documento_salida'] ?? ''));

        if ($tipoSalida !== '') {
            return mb_strtolower($tipoSalida) === 'memorando';
        }

        return str_contains(mb_strtolower((string) ($documento['tipo_documento'] ?? '')), 'memorando');
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array{bytes: string, paginas: int}
     */
    private function generarConstanciaTitulacion(array $documento, ?string $firmaImagen): array
    {
        $items = [];
        $contenido = trim((string) $documento['contenido_renderizado']);
        $firmante = trim((string) ($documento['firmante_nombre'] ?? $documento['firmante'] ?? ''));
        if ($firmante !== '') {
            $contenido = preg_replace('/\R\s*'.preg_quote($firmante, '/').'\s*\z/u', '', $contenido) ?? $contenido;
        }

        $parrafos = preg_split('/\R/u', trim($contenido)) ?: [];
        $fechaConFirma = false;
        foreach ($parrafos as $indice => $parrafo) {
            $titulo = $indice === 0;
            $esFecha = $indice === array_key_last($parrafos)
                && filled($documento['lugar'] ?? null)
                && str_starts_with($parrafo, $documento['lugar'].',');
            if ($esFecha) {
                $items[] = ['tipo' => 'empujar_cierre', 'alto' => 0];
                $fechaConFirma = true;
            }
            $inicio = count($items);
            $this->agregarMemoTexto(
                $items, $parrafo, $titulo ? 14 : 12,
                $titulo || trim($parrafo) === 'HACE CONSTAR:', $titulo,
                $titulo ? 20 : ($parrafo === '' ? 8 : 10), 495, $titulo, $titulo ? 18 : 20,
            );
            if ($esFecha) {
                for ($posicion = $inicio; $posicion < count($items); $posicion++) {
                    $items[$posicion]['alineacion'] = 'derecha';
                }
            }
        }

        if (($documento['decision'] ?? null) === 'rechazado') {
            foreach (['Resultado de la evaluación' => 'Rechazado', 'Fundamento' => $documento['conclusion'] ?? null, 'Comunicación al interesado' => $documento['comentario_publico'] ?? null] as $titulo => $texto) {
                if (filled($texto)) {
                    $this->agregarMemoTexto($items, $titulo, 12, true, false, 4, 495);
                    $this->agregarMemoTexto($items, (string) $texto, 12, false, false, 10, 495, false, 20);
                }
            }
        }

        if (! $fechaConFirma) {
            $items[] = ['tipo' => 'empujar_cierre', 'alto' => 0];
        }
        if ($firmaImagen !== null) {
            $items[] = ['tipo' => 'firma', 'alto' => 58];
        }
        $this->agregarMemoTexto($items, '________________________________________', 10, false, true, 4, 495);
        $this->agregarMemoTexto($items, $firmante, 12, true, true, 4, 495);
        if (filled($documento['firmante_cargo'] ?? null)) {
            $this->agregarMemoTexto($items, (string) $documento['firmante_cargo'], 10, false, true, 4, 495);
        }
        $this->agregarMemoTexto($items, (string) $documento['numero'], 8, false, true, 0, 495);

        $qr = $this->matrizVerificacion((string) $documento['codigo_verificacion']);
        $paginas = $this->distribuirMemorando($items, (string) $documento['codigo_expediente'], (string) $documento['codigo_verificacion'], true);

        return [
            'bytes' => $this->construirPdfMemorando($paginas, (string) $documento['numero'], (string) $documento['institucion'], $qr, $firmaImagen, $this->leerEncabezadoInstitucional('simple')),
            'paginas' => count($paginas),
        ];
    }

    /**
     * Render the institutional memorandum layout while keeping the legacy
     * renderer for other output document types.
     *
     * @param  array<string, mixed>  $documento
     * @return array{bytes: string, paginas: int}
     */
    private function generarMemorando(array $documento, ?string $firmaImagen): array
    {
        $items = [];
        $numero = trim((string) ($documento['numero'] ?? ''));
        $tipo = mb_strtoupper(trim((string) ($documento['tipo_documento'] ?? 'MEMORANDO')));
        $modalidad = mb_strtolower(trim((string) ($documento['modalidad_documento'] ?? '')));
        $esMultiple = $modalidad === 'multiple'
            || ($modalidad === '' && (str_contains($tipo, 'MULTIPLE') || str_contains($tipo, 'MÚLTIPLE')));
        $tamanoCampo = $esMultiple ? 10 : 11;
        $tamanoCuerpo = $esMultiple ? 10 : 12;
        $altoCuerpo = $tamanoCuerpo + 3;
        $titulo = $esMultiple
            ? 'MEMORANDO MÚLTIPLE'
            : 'MEMORANDUM';
        $this->agregarMemoTexto($items, $titulo.' Nº '.$numero, 10, true, true, 8, 495, true);

        $destinatariosDetalle = is_array($documento['destinatarios_detalle'] ?? null)
            ? $documento['destinatarios_detalle']
            : [];
        $destinatarios = $destinatariosDetalle === []
            ? array_values(array_filter(array_map('strval', (array) ($documento['destinatarios'] ?? []))))
            : array_values(array_filter(array_map(
                fn (array $destinatario): string => trim(implode(' ', array_filter([
                    $destinatario['nombres'] ?? null,
                    $destinatario['apellidos'] ?? null,
                    $destinatario['cargo'] ?? null,
                ]))),
                $destinatariosDetalle,
            )));
        $remitente = trim((string) ($documento['remitente_nombre'] ?? $documento['remitente'] ?? ''));
        $remitenteCargo = trim((string) ($documento['remitente_cargo'] ?? ''));
        $remitenteDetalle = [
            'nombres' => $remitente,
            'apellidos' => null,
            'cargo' => $remitenteCargo,
        ];
        if ($esMultiple) {
            $this->agregarMemoPersonaCampo($items, 'De', $remitenteDetalle, $tamanoCampo);
        }
        if ($destinatariosDetalle !== []) {
            foreach ($destinatariosDetalle as $indice => $destinatario) {
                $this->agregarMemoPersonaCampo($items, $indice === 0 ? 'A' : '', $destinatario, $tamanoCampo);
            }
            $items[] = ['tipo' => 'espacio', 'alto' => 8];
        } else {
            $this->agregarMemoCampo($items, 'A', implode('; ', $destinatarios), 8, true, $tamanoCampo);
        }
        if (! $esMultiple) {
            $this->agregarMemoPersonaCampo($items, 'De', $remitenteDetalle, $tamanoCampo);
        }
        $this->agregarMemoCampo($items, 'Asunto', (string) ($documento['asunto'] ?? ''), 8, true, $tamanoCampo);
        $this->agregarMemoCampo($items, 'Fecha', $this->fechaMemorando($documento), 12, false, $tamanoCampo);
        $items[] = ['tipo' => 'linea', 'alto' => 16];

        foreach ([$documento['introduccion'] ?? null, $documento['contenido_principal'] ?? null] as $contenido) {
            foreach ($this->separarTexto((string) ($contenido ?? '')) as $parrafo) {
                $this->agregarMemoTexto($items, $parrafo, $tamanoCuerpo, false, false, 8, 495, false, $altoCuerpo);
            }
        }

        if (($documento['decision'] ?? null) === 'rechazado') {
            foreach ([
                'Resultado de la evaluación' => 'Rechazado',
                'Fundamento' => $documento['conclusion'] ?? null,
                'Comunicación al interesado' => $documento['comentario_publico'] ?? null,
            ] as $etiqueta => $contenido) {
                if (filled($contenido)) {
                    $this->agregarMemoTexto($items, $etiqueta, $tamanoCuerpo, true, false, 4, 495, false, $altoCuerpo);
                    foreach ($this->separarTexto((string) $contenido) as $parrafo) {
                        $this->agregarMemoTexto($items, $parrafo, $tamanoCuerpo, false, false, 8, 495, false, $altoCuerpo);
                    }
                }
            }
        }

        $personasDetalle = is_array($documento['personas_detalle'] ?? null)
            ? $documento['personas_detalle']
            : [];
        $personas = $personasDetalle === []
            ? array_values(array_filter(array_map('strval', (array) ($documento['personas'] ?? []))))
            : array_values(array_filter(array_map(
                static fn (array $persona): string => trim(implode(' ', array_filter([
                    $persona['nombres'] ?? null,
                    $persona['apellidos'] ?? null,
                    $persona['cargo'] ?? null,
                    ! empty($persona['dni']) ? 'DNI: '.$persona['dni'] : null,
                ]))),
                $personasDetalle,
            )));

        if ($personas !== []) {
            $this->agregarMemoTexto(
                $items,
                (string) ($documento['personas_titulo'] ?? 'Personas mencionadas:'),
                $tamanoCuerpo,
                false,
                false,
                6,
                495,
                false,
                $altoCuerpo,
            );
            $this->agregarMemoColumnas($items, $personasDetalle === [] ? $personas : $personasDetalle, 8, $tamanoCuerpo);
        }

        $items[] = ['tipo' => 'empujar_cierre', 'alto' => 0];
        foreach ($this->separarTexto((string) ($documento['cierre'] ?? '')) as $parrafo) {
            $this->agregarMemoTexto($items, $parrafo, $tamanoCuerpo, false, ! $esMultiple, 8, 495, false, $altoCuerpo);
        }

        $this->agregarMemoTexto($items, 'Atentamente', $tamanoCuerpo, false, true, 5, 495, false, $altoCuerpo);
        $items[] = ['tipo' => 'firma', 'alto' => 52];
        $firmante = trim((string) ($documento['firmante_nombre'] ?? $documento['firmante'] ?? ''));
        $firmanteCargo = trim((string) ($documento['firmante_cargo'] ?? ''));
        $this->agregarMemoTexto($items, '________________________________________', 9, false, true, 2, 250, false, 12);
        $this->agregarMemoTexto($items, $firmante, $tamanoCuerpo, true, true, 1, 300, false, $altoCuerpo);
        if ($firmanteCargo !== '') {
            $this->agregarMemoTexto($items, $firmanteCargo, $tamanoCuerpo, false, true, 0, 300, false, $altoCuerpo);
        }

        $codigo = (string) ($documento['codigo_verificacion'] ?? '');
        $qr = $this->matrizVerificacion($codigo);
        $paginas = $this->distribuirMemorando(
            $items,
            (string) ($documento['codigo_expediente'] ?? ''),
            $codigo,
            true,
        );
        $encabezado = $this->leerEncabezadoInstitucional($esMultiple ? 'multiple' : 'simple');

        return [
            'bytes' => $this->construirPdfMemorando($paginas, $numero, (string) ($documento['institucion'] ?? ''), $qr, $firmaImagen, $encabezado),
            'paginas' => count($paginas),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function agregarMemoTexto(array &$items, string $texto, int $tamano, bool $negrita, bool $centrado, int $espacio, int $anchoPuntos, bool $subrayado = false, int $altoLinea = 13): void
    {
        $texto = trim($this->textoPlano($texto));

        if ($texto === '') {
            return;
        }

        foreach ($this->ajustarMemoLinea($texto, $tamano, $anchoPuntos, $negrita) as $linea) {
            $items[] = [
                'tipo' => 'texto',
                'texto' => $linea,
                'tamano' => $tamano,
                'negrita' => $negrita,
                'centrado' => $centrado,
                'subrayado' => $subrayado,
                'alto' => $altoLinea,
            ];
        }

        if ($espacio > 0) {
            $items[] = ['tipo' => 'espacio', 'alto' => $espacio];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function agregarMemoCampo(array &$items, string $etiqueta, string $valor, int $espacio, bool $valorNegrita, int $tamano): void
    {
        $ancho = 409 - $this->anchoMemoTextoPuntos(': ', $tamano, $valorNegrita);
        $lineas = $this->ajustarMemoLinea(trim($this->textoPlano($valor)), $tamano, $ancho, $valorNegrita);

        if ($lineas === []) {
            $lineas = [''];
        }

        foreach ($lineas as $indice => $linea) {
            $items[] = [
                'tipo' => 'campo',
                'etiqueta' => $indice === 0 ? $etiqueta : '',
                'texto' => $linea,
                'valor_negrita' => $valorNegrita,
                'tamano' => $tamano,
                'alto' => $tamano + 5,
            ];
        }

        $items[] = ['tipo' => 'espacio', 'alto' => $espacio];
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private function agregarMemoPersonaCampo(array &$items, string $etiqueta, array $persona, int $tamano): void
    {
        $nombre = trim(implode(' ', array_filter([
            $persona['nombres'] ?? null,
            $persona['apellidos'] ?? null,
        ])));
        $cargo = trim((string) ($persona['cargo'] ?? ''));

        if ($nombre !== '') {
            $this->agregarMemoCampo($items, $etiqueta, $nombre, 0, true, $tamano);
        }

        if ($cargo !== '') {
            $this->agregarMemoCampo($items, '', $cargo, 8, false, $tamano);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string|array<string, mixed>>  $personas
     */
    private function agregarMemoColumnas(array &$items, array $personas, int $espacio, int $tamano): void
    {
        foreach ($personas as $persona) {
            if (is_array($persona)) {
                $izquierda = $this->ajustarMemoLinea(trim(implode(' ', array_filter([
                    $persona['nombres'] ?? null,
                    $persona['apellidos'] ?? null,
                ]))), $tamano, 180);
                $derecha = $this->ajustarMemoLinea(trim(implode(' · ', array_filter([
                    $persona['cargo'] ?? null,
                    ! empty($persona['dni']) ? 'DNI: '.$persona['dni'] : null,
                ]))), $tamano, 235);
            } else {
                $izquierda = $this->ajustarMemoLinea($this->textoPlano($persona), $tamano, 180);
                $derecha = [];
            }

            $items[] = [
                'tipo' => 'columnas',
                'izquierda' => $izquierda,
                'derecha' => $derecha,
                'tamano' => $tamano,
                'alto' => max(count($izquierda), count($derecha), 1) * ($tamano + 3),
            ];
        }

        if ($espacio > 0) {
            $items[] = ['tipo' => 'espacio', 'alto' => $espacio];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<list<array<string, mixed>>>
     */
    private function distribuirMemorando(array $items, string $expediente, string $codigo, bool $conQr): array
    {
        $paginas = [[]];
        $pagina = 0;
        $y = 728.0;
        $limite = $conQr ? 156.0 : 68.0;

        foreach ($items as $indice => $item) {
            $tipo = (string) ($item['tipo'] ?? 'texto');
            $alto = (float) ($item['alto'] ?? 13);

            if ($tipo === 'empujar_cierre') {
                // Keep the user closing and signature block together near the footer.
                $altoBloque = 0.0;

                foreach (array_slice($items, $indice + 1) as $restante) {
                    $altoBloque += (float) ($restante['alto'] ?? 13);
                }

                if ($y - $altoBloque < $limite) {
                    $paginas[] = [];
                    $pagina++;
                    $y = 728.0;
                }

                $y = min($y, max(275.0, $limite + $altoBloque));

                continue;
            }

            if ($y - $alto < $limite) {
                $paginas[] = [];
                $pagina++;
                $y = 728.0;
            }

            $item['y'] = $y;
            $paginas[$pagina][] = $item;
            $y -= $alto;
        }

        foreach ($paginas as $indice => &$lineas) {
            $codigoTexto = trim($codigo) === ''
                ? 'Borrador sin numeración oficial'
                : 'Código de verificación '.$codigo;
            $lineas[] = [
                'tipo' => 'pie',
                'texto' => $expediente.' · '.$codigoTexto.' · Página '.($indice + 1).' de '.count($paginas),
                'y' => 38.0,
                'alto' => 0,
            ];
        }
        unset($lineas);

        return $paginas;
    }

    private function fechaMemorando(array $documento): string
    {
        $fecha = trim((string) ($documento['fecha_documento'] ?? ''));
        $partes = preg_split('~[-/]~', $fecha) ?: [];

        if (count($partes) !== 3) {
            return trim(implode(', ', array_filter([(string) ($documento['lugar'] ?? ''), $fecha])));
        }

        if (strlen($partes[0]) === 4) {
            [$anio, $mes, $dia] = $partes;
        } else {
            [$dia, $mes, $anio] = $partes;
        }

        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $fechaTexto = (int) $dia.' de '.($meses[(int) $mes] ?? $mes).' de '.$anio;

        return trim(implode(', ', array_filter([(string) ($documento['lugar'] ?? ''), $fechaTexto])));
    }

    /**
     * @return array{tipo: 'simple'|'multiple', bytes: string, formato: 'png'|'jpeg'}|null
     */
    private function leerEncabezadoInstitucional(string $modalidad): ?array
    {
        $esMultiple = $modalidad === 'multiple';
        $formato = $esMultiple ? 'jpeg' : 'png';
        $ruta = base_path($esMultiple
            ? 'resources/images/institucion/encabezado-memorando-multiple.jpeg'
            : 'resources/images/institucion/encabezado-institucional.png');

        if (! is_file($ruta) && ! $esMultiple) {
            $ruta = base_path('resources/images/institucion/encabezado-institucional.jpg');
            $formato = 'jpeg';
        }

        if (! is_file($ruta)) {
            return null;
        }

        $bytes = file_get_contents($ruta);

        return is_string($bytes) && $bytes !== '' ? [
            'tipo' => $esMultiple ? 'multiple' : 'simple',
            'bytes' => $bytes,
            'formato' => $formato,
        ] : null;
    }

    /**
     * @param  list<array{text: string, size: int, bold: bool, gap: int, center: bool}>  $lineas
     */
    private function agregarSeccion(array &$lineas, string $titulo, ?string $contenido): void
    {
        if (trim((string) $contenido) === '') {
            return;
        }

        $this->agregarParrafo($lineas, $titulo, 11, true, 4);

        foreach ($this->separarTexto((string) $contenido) as $parrafo) {
            $this->agregarParrafo($lineas, $parrafo, 10, false, 8);
        }
    }

    /**
     * @param  list<array{text: string, size: int, bold: bool, gap: int, center: bool}>  $lineas
     */
    private function agregarParrafo(array &$lineas, string $texto, int $size = 10, bool $bold = false, int $gap = 4, bool $center = false): void
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $this->textoPlano($texto)) ?? '');
        $anchoMaximo = $size >= 14 ? 58 : ($size >= 11 ? 82 : 98);

        foreach ($this->ajustarLinea($texto, $anchoMaximo) as $linea) {
            $lineas[] = [
                'text' => $linea,
                'size' => $size,
                'bold' => $bold,
                'gap' => 0,
                'center' => $center,
            ];
        }

        if ($gap > 0) {
            $lineas[] = ['text' => '', 'size' => 10, 'bold' => false, 'gap' => $gap, 'center' => false];
        }
    }

    /**
     * @return list<string>
     */
    private function separarTexto(string $html): array
    {
        $texto = preg_replace('~<\s*(?:br\s*/?|/p|/div|/li)\s*>~i', "\n", $html) ?? $html;
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parrafos = preg_split('/\n\s*\n+/', str_replace(["\r\n", "\r"], "\n", $texto)) ?: [];

        return array_values(array_filter(array_map('trim', $parrafos), static fn (string $parrafo): bool => $parrafo !== ''));
    }

    private function textoPlano(string $texto): string
    {
        return trim(html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return list<string>
     */
    private function ajustarLinea(string $texto, int $anchoMaximo): array
    {
        if ($texto === '') {
            return [''];
        }

        $lineas = [];
        $linea = '';

        foreach (preg_split('/\s+/u', $texto) ?: [] as $palabra) {
            $candidata = $linea === '' ? $palabra : $linea.' '.$palabra;

            if ($linea !== '' && mb_strwidth($candidata) > $anchoMaximo) {
                $lineas[] = $linea;
                $linea = $palabra;
            } else {
                $linea = $candidata;
            }
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    /**
     * Wrap memorandum text using the standard Helvetica glyph widths in PDF points.
     * Character counts are insufficient for wide glyphs such as W and M.
     *
     * @return list<string>
     */
    private function ajustarMemoLinea(string $texto, int $tamano, float $anchoMaximo, bool $negrita = false): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $this->textoPlano($texto)) ?? '');

        if ($texto === '') {
            return [''];
        }

        $lineas = [];
        $linea = '';

        foreach (preg_split('/\s+/u', $texto) ?: [] as $palabra) {
            if ($linea !== '' && $this->anchoMemoTextoPuntos($linea.' '.$palabra, $tamano, $negrita) > $anchoMaximo) {
                $lineas[] = $linea;
                $linea = '';
            }

            if ($this->anchoMemoTextoPuntos($palabra, $tamano, $negrita) <= $anchoMaximo) {
                $linea = $linea === '' ? $palabra : $linea.' '.$palabra;

                continue;
            }

            if ($linea !== '') {
                $lineas[] = $linea;
                $linea = '';
            }

            foreach (mb_str_split($palabra) as $caracter) {
                if ($linea !== '' && $this->anchoMemoTextoPuntos($linea.$caracter, $tamano, $negrita) > $anchoMaximo) {
                    $lineas[] = $linea;
                    $linea = '';
                }

                $linea .= $caracter;
            }
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    private function anchoMemoTextoPuntos(string $texto, int $tamano, bool $negrita = false): float
    {
        $unidades = 0;
        $metricas = $this->metricasMemo($negrita);

        foreach (mb_str_split($texto) as $caracter) {
            $base = $caracter;

            if (! isset($metricas[$base])) {
                $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $caracter);
                $base = is_string($transliterado) && $transliterado !== ''
                    ? mb_substr($transliterado, 0, 1)
                    : '?';
            }

            $unidades += $metricas[$base] ?? 600;
        }

        return $unidades * ($tamano / 1000);
    }

    /** @return array<string, int> */
    private function metricasMemo(bool $negrita = false): array
    {
        static $metricas = [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667,
            "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333,
            '.' => 278, '/' => 278, '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
            '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556, ':' => 278, ';' => 278,
            '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667,
            'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278,
            'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
            'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
            'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469,
            '_' => 556, '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556,
            'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222,
            'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500,
            't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
            '{' => 334, '|' => 260, '}' => 334, '~' => 584, '—' => 1000, '–' => 500, '“' => 444,
            '”' => 444, '¿' => 556, '¡' => 278,
        ];

        if (! $negrita) {
            return $metricas;
        }

        return array_replace($metricas, [
            '!' => 333, '"' => 474, '&' => 722, "'" => 238,
            ':' => 333, ';' => 333, '?' => 611, '@' => 975,
            'A' => 722, 'B' => 722, 'J' => 556, 'K' => 722, 'L' => 611,
            '[' => 333, ']' => 333, '^' => 584, 'b' => 611, 'c' => 556, 'd' => 611,
            'f' => 333, 'g' => 611, 'h' => 611, 'i' => 278, 'j' => 278,
            'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611,
            'p' => 611, 'q' => 611, 'r' => 389, 's' => 556, 't' => 333,
            'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556, 'y' => 556,
            '{' => 389, '|' => 280, '}' => 389,
            '“' => 500, '”' => 500,
        ]);
    }

    /**
     * @param  list<array{text?: string, size?: int, bold?: bool, gap?: int, center?: bool, firma?: bool, alto?: int}>  $lineas
     * @return list<list<array{text?: string, size?: int, bold?: bool, y: float, center?: bool, firma?: bool, alto?: int}>>
     */
    private function distribuirEnPaginas(array $lineas, string $expediente, string $codigo, bool $conQr = false): array
    {
        $paginas = [[]];
        $paginaActual = 0;
        $y = 786.0;

        foreach ($lineas as $linea) {
            if (($linea['firma'] ?? false) === true) {
                $altoFirma = (float) ($linea['alto'] ?? 42);

                if ($y - $altoFirma - 8 < ($conQr ? 155 : 65)) {
                    $paginas[] = [];
                    $paginaActual++;
                    $y = 786.0;
                }

                $paginas[$paginaActual][] = ['firma' => true, 'y' => $y - $altoFirma, 'alto' => (int) $altoFirma];
                $y -= $altoFirma + 8;

                continue;
            }

            if ($linea['text'] === '' && $linea['gap'] > 0) {
                $y -= $linea['gap'];

                continue;
            }

            $alto = $linea['size'] + 5;

            if ($y - $alto < ($conQr ? 155 : 65)) {
                $paginas[] = [];
                $paginaActual++;
                $y = 786.0;
            }

            $paginas[$paginaActual][] = [
                'text' => $linea['text'],
                'size' => $linea['size'],
                'bold' => $linea['bold'],
                'y' => $y,
                'center' => $linea['center'],
            ];
            $y -= $alto;
        }

        foreach ($paginas as $indice => &$pagina) {
            $pagina[] = [
                'text' => $expediente.' · Código de verificación '.$codigo.' · Página '.($indice + 1).' de '.count($paginas),
                'size' => 7,
                'bold' => false,
                'y' => 38.0,
                'center' => false,
            ];
        }
        unset($pagina);

        return $paginas;
    }

    /**
     * @param  list<list<array{text: string, size: int, bold: bool, y: float, center: bool}>>  $paginas
     */
    private function construirPdf(array $paginas, string $numero, string $institucion, ?ByteMatrix $qr = null, ?string $firmaImagen = null): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $idsPaginas = [];
        $imagen = $firmaImagen === null ? null : @getimagesizefromstring($firmaImagen);

        if ($firmaImagen !== null && (! is_array($imagen) || ($imagen[2] ?? null) !== IMAGETYPE_JPEG)) {
            throw new RuntimeException('La firma del perfil no está en un formato de imagen válido.');
        }

        $idImagenFirma = $firmaImagen === null ? null : 5;

        if ($firmaImagen !== null && is_array($imagen)) {
            $objetos[$idImagenFirma] = '<< /Type /XObject /Subtype /Image /Width '.(int) $imagen[0].' /Height '.(int) $imagen[1]
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($firmaImagen)." >>\nstream\n"
                .$firmaImagen."\nendstream";
        }

        $primerIdPagina = $idImagenFirma === null ? 5 : 6;

        foreach ($paginas as $indice => $lineas) {
            $idPagina = $primerIdPagina + ($indice * 2);
            $idContenido = $idPagina + 1;
            $idsPaginas[] = $idPagina.' 0 R';
            $contenido = $this->contenidoPagina($lineas, $qr, $imagen);
            $recursosImagen = $idImagenFirma === null ? '' : ' /XObject << /Firma '.$idImagenFirma.' 0 R >>';
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$recursosImagen.' >> /Contents '.$idContenido.' 0 R >>';
            $objetos[$idContenido] = '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido."\nendstream";
        }

        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $idsPaginas).'] /Count '.count($paginas).' >>';
        $idInfo = $primerIdPagina + (count($paginas) * 2);
        $objetos[$idInfo] = '<< /Title '.$this->pdfTexto($numero).' /Author '.$this->pdfTexto($institucion).' /Creator '.$this->pdfTexto('Sistema de Gestión Documentaria Laravel').' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $desplazamientos = [0];

        foreach ($objetos as $id => $objeto) {
            $desplazamientos[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$objeto."\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $cantidadObjetos = max(array_keys($objetos)) + 1;
        $pdf .= "xref\n0 ".$cantidadObjetos."\n0000000000 65535 f \n";

        for ($id = 1; $id < $cantidadObjetos; $id++) {
            $pdf .= str_pad((string) ($desplazamientos[$id] ?? 0), 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".$cantidadObjetos.' /Root 1 0 R /Info '.$idInfo." 0 R >>\nstartxref\n".$inicioXref."\n%%EOF";
    }

    /**
     * @param  list<array{text?: string, size?: int, bold?: bool, y: float, center?: bool, firma?: bool, alto?: int}>  $lineas
     */
    private function contenidoPagina(array $lineas, ?ByteMatrix $qr = null, ?array $imagenFirma = null): string
    {
        $contenido = "0.18 0.18 0.18 rg\n";

        foreach ($lineas as $linea) {
            if (($linea['firma'] ?? false) === true && is_array($imagenFirma)) {
                $escala = min(150 / (int) $imagenFirma[0], 42 / (int) $imagenFirma[1]);
                $ancho = (int) round((int) $imagenFirma[0] * $escala, 2);
                $alto = (int) round((int) $imagenFirma[1] * $escala, 2);
                $contenido .= 'q '.$ancho.' 0 0 '.$alto.' 50 '.number_format($linea['y'], 2, '.', '')." cm /Firma Do Q\n";

                continue;
            }

            $fuente = ($linea['bold'] ?? false) ? 'F2' : 'F1';
            $texto = (string) ($linea['text'] ?? '');
            $size = (int) ($linea['size'] ?? 10);
            $x = ($linea['center'] ?? false)
                ? max(50, (595 - ($size * mb_strwidth($texto) * 0.52)) / 2)
                : 50;
            $contenido .= 'BT /'.$fuente.' '.$size.' Tf 1 0 0 1 '.number_format($x, 2, '.', '').' '.number_format($linea['y'], 2, '.', '').' Tm '.$this->pdfTexto($texto)." Tj ET\n";

            if ($texto === '' && $linea['y'] > 730) {
                $contenido .= "q 0.42 0.08 0.18 RG 1.2 w 50 755 m 545 755 l S Q\n";
            }
        }

        $contenido .= "q 0.65 0.65 0.65 RG 0.5 w 50 54 m 545 54 l S Q\n";

        if ($qr !== null) {
            $contenido .= $this->contenidoQr($qr);
        }

        return $contenido;
    }

    private function matrizVerificacion(string $codigo): ?ByteMatrix
    {
        $base = trim((string) config('app.url'));
        $partes = parse_url($base);

        if (preg_match('/^[A-F0-9]{4}(?:-[A-F0-9]{4}){3}$/', $codigo) !== 1
            || ! is_array($partes)
            || filter_var($base, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) ($partes['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($partes['host'] ?? null)
            || isset($partes['user'])
            || isset($partes['pass'])
            || isset($partes['query'])
            || isset($partes['fragment'])
            || (app()->isProduction() && in_array(strtolower($partes['host']), ['localhost', '127.0.0.1', '::1'], true))) {
            return null;
        }

        try {
            $url = rtrim($base, '/').route('documentos.verificar', ['codigo' => $codigo], false);

            return Encoder::encode($url, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        } catch (Throwable) {
            return null;
        }
    }

    private function contenidoQr(ByteMatrix $qr): string
    {
        $dimension = $qr->getWidth();
        $modulo = 82 / ($dimension + 8);
        $contenido = "q 1 1 1 rg 463 62 82 82 re f 0 0 0 rg\n";

        for ($fila = 0; $fila < $dimension; $fila++) {
            for ($columna = 0; $columna < $dimension; $columna++) {
                if ($qr->get($columna, $fila) !== 1) {
                    continue;
                }

                $x = 463 + (($columna + 4) * $modulo);
                $y = 62 + (($dimension + 3 - $fila) * $modulo);
                $contenido .= number_format($x, 3, '.', '').' '.number_format($y, 3, '.', '').' '
                    .number_format($modulo, 3, '.', '').' '.number_format($modulo, 3, '.', '')." re f\n";
            }
        }

        return $contenido."Q\n";
    }

    /**
     * @param  list<list<array<string, mixed>>>  $paginas
     * @param  array{tipo: 'simple'|'multiple', bytes: string, formato: 'png'|'jpeg'}|null  $encabezado
     */
    private function construirPdfMemorando(array $paginas, string $numero, string $institucion, ?ByteMatrix $qr, ?string $firmaImagen, ?array $encabezado): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $idsPaginas = [];
        $idEncabezado = null;
        $idEncabezadoMascara = null;
        $idFirma = null;
        $siguienteIdImagen = 5;

        if ($encabezado !== null) {
            if (($encabezado['formato'] ?? null) === 'png') {
                $imagenPng = $this->prepararImagenPngParaPdf($encabezado['bytes']);

                if ($imagenPng !== null) {
                    $idEncabezado = $siguienteIdImagen++;
                    $idEncabezadoMascara = $siguienteIdImagen++;
                    $objetos[$idEncabezadoMascara] = '<< /Type /XObject /Subtype /Image /Width '.$imagenPng['ancho'].' /Height '.$imagenPng['alto']
                        .' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length '.strlen($imagenPng['mascara'])." >>\nstream\n"
                        .$imagenPng['mascara']."\nendstream";
                    $objetos[$idEncabezado] = '<< /Type /XObject /Subtype /Image /Width '.$imagenPng['ancho'].' /Height '.$imagenPng['alto']
                        .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /SMask '.$idEncabezadoMascara.' 0 R /Length '.strlen($imagenPng['rgb'])." >>\nstream\n"
                        .$imagenPng['rgb']."\nendstream";
                }
            } else {
                $medidas = @getimagesizefromstring($encabezado['bytes']);

                if (is_array($medidas) && ($medidas[2] ?? null) === IMAGETYPE_JPEG) {
                    $idEncabezado = $siguienteIdImagen++;
                    $objetos[$idEncabezado] = '<< /Type /XObject /Subtype /Image /Width '.(int) $medidas[0].' /Height '.(int) $medidas[1]
                        .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($encabezado['bytes'])." >>\nstream\n"
                        .$encabezado['bytes']."\nendstream";
                }
            }
        }

        if ($firmaImagen !== null) {
            $medidas = @getimagesizefromstring($firmaImagen);

            if (! is_array($medidas) || ($medidas[2] ?? null) !== IMAGETYPE_JPEG) {
                throw new RuntimeException('La firma del perfil no está en un formato de imagen válido.');
            }

            $idFirma = $siguienteIdImagen++;
            $objetos[$idFirma] = '<< /Type /XObject /Subtype /Image /Width '.(int) $medidas[0].' /Height '.(int) $medidas[1]
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($firmaImagen)." >>\nstream\n"
                .$firmaImagen."\nendstream";
        }

        $primerIdPagina = max(5, $siguienteIdImagen);

        foreach ($paginas as $indice => $lineas) {
            $idPagina = $primerIdPagina + ($indice * 2);
            $idContenido = $idPagina + 1;
            $idsPaginas[] = $idPagina.' 0 R';
            $contenido = $this->contenidoPaginaMemorando($lineas, $qr, $encabezado, $idEncabezado !== null, $idFirma !== null);
            $xobjects = [];

            if ($idEncabezado !== null) {
                $xobjects[] = '/Encabezado '.$idEncabezado.' 0 R';
            }

            if ($idFirma !== null) {
                $xobjects[] = '/Firma '.$idFirma.' 0 R';
            }

            $recursosImagen = $xobjects === [] ? '' : ' /XObject << '.implode(' ', $xobjects).' >>';
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$recursosImagen.' >> /Contents '.$idContenido.' 0 R >>';
            $objetos[$idContenido] = '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido."\nendstream";
        }

        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $idsPaginas).'] /Count '.count($paginas).' >>';
        $idInfo = $primerIdPagina + (count($paginas) * 2);
        $objetos[$idInfo] = '<< /Title '.$this->pdfTexto($numero).' /Author '.$this->pdfTexto($institucion).' /Creator '.$this->pdfTexto('Sistema de Gestión Documentaria Laravel').' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $desplazamientos = [0];

        foreach ($objetos as $id => $objeto) {
            $desplazamientos[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$objeto."\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $cantidadObjetos = max(array_keys($objetos)) + 1;
        $pdf .= "xref\n0 ".$cantidadObjetos."\n0000000000 65535 f \n";

        for ($id = 1; $id < $cantidadObjetos; $id++) {
            $pdf .= str_pad((string) ($desplazamientos[$id] ?? 0), 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".$cantidadObjetos.' /Root 1 0 R /Info '.$idInfo." 0 R >>\nstartxref\n".$inicioXref."\n%%EOF";
    }

    /**
     * @return array{ancho: int, alto: int, rgb: string, mascara: string}|null
     */
    private function prepararImagenPngParaPdf(string $bytes): ?array
    {
        if (! str_starts_with($bytes, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return null;
        }

        $offset = 8;
        $ancho = null;
        $alto = null;
        $bitDepth = null;
        $colorType = null;
        $datos = '';

        while ($offset + 8 <= strlen($bytes)) {
            $longitud = unpack('N', substr($bytes, $offset, 4))[1] ?? 0;
            $tipo = substr($bytes, $offset + 4, 4);
            $contenido = substr($bytes, $offset + 8, $longitud);
            $offset += 12 + $longitud;

            if ($tipo === 'IHDR' && strlen($contenido) >= 13) {
                $cabecera = unpack('Nancho/Nalto/Cbit_depth/Ccolor_type', $contenido);
                $ancho = (int) ($cabecera['ancho'] ?? 0);
                $alto = (int) ($cabecera['alto'] ?? 0);
                $bitDepth = (int) ($cabecera['bit_depth'] ?? 0);
                $colorType = (int) ($cabecera['color_type'] ?? 0);
            }

            if ($tipo === 'IDAT') {
                $datos .= $contenido;
            }

            if ($tipo === 'IEND') {
                break;
            }
        }

        if ($ancho === null || $alto === null || $ancho < 1 || $alto < 1 || $bitDepth !== 8 || $colorType !== 6) {
            return null;
        }

        $descomprimido = @gzuncompress($datos);

        if (! is_string($descomprimido) || strlen($descomprimido) !== $alto * (($ancho * 4) + 1)) {
            return null;
        }

        $filaAnterior = array_fill(0, $ancho * 4, 0);
        $rgb = '';
        $mascara = '';
        $posicion = 0;

        for ($fila = 0; $fila < $alto; $fila++) {
            $filtro = ord($descomprimido[$posicion++]);
            $filaCodificada = array_values(unpack('C*', substr($descomprimido, $posicion, $ancho * 4)) ?: []);
            $posicion += $ancho * 4;
            $filaActual = [];

            for ($indice = 0; $indice < $ancho * 4; $indice++) {
                $izquierda = $indice >= 4 ? $filaActual[$indice - 4] : 0;
                $arriba = $filaAnterior[$indice] ?? 0;
                $diagonal = $indice >= 4 ? ($filaAnterior[$indice - 4] ?? 0) : 0;
                $valor = $filaCodificada[$indice] ?? 0;

                $filaActual[$indice] = match ($filtro) {
                    0 => $valor,
                    1 => ($valor + $izquierda) & 255,
                    2 => ($valor + $arriba) & 255,
                    3 => ($valor + (int) floor(($izquierda + $arriba) / 2)) & 255,
                    4 => ($valor + $this->paethPredictor($izquierda, $arriba, $diagonal)) & 255,
                    default => throw new RuntimeException('El encabezado PNG usa un filtro no compatible.'),
                };
            }

            for ($indice = 0; $indice < $ancho; $indice++) {
                $rgb .= chr($filaActual[$indice * 4])
                    .chr($filaActual[$indice * 4 + 1])
                    .chr($filaActual[$indice * 4 + 2]);
                $mascara .= chr($filaActual[$indice * 4 + 3]);
            }

            $filaAnterior = $filaActual;
        }

        $rgbComprimido = gzcompress($rgb);
        $mascaraComprimida = gzcompress($mascara);

        if (! is_string($rgbComprimido) || ! is_string($mascaraComprimida)) {
            return null;
        }

        return [
            'ancho' => $ancho,
            'alto' => $alto,
            'rgb' => $rgbComprimido,
            'mascara' => $mascaraComprimida,
        ];
    }

    private function paethPredictor(int $izquierda, int $arriba, int $diagonal): int
    {
        $p = $izquierda + $arriba - $diagonal;
        $pa = abs($p - $izquierda);
        $pb = abs($p - $arriba);
        $pc = abs($p - $diagonal);

        if ($pa <= $pb && $pa <= $pc) {
            return $izquierda;
        }

        return $pb <= $pc ? $arriba : $diagonal;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array{tipo: 'simple'|'multiple', bytes: string, formato: 'png'|'jpeg'}|null  $encabezado
     */
    private function contenidoPaginaMemorando(array $lineas, ?ByteMatrix $qr, ?array $encabezado, bool $tieneEncabezado, bool $tieneFirma): string
    {
        $contenido = "0.15 0.15 0.15 rg\n";

        if ($tieneEncabezado && ($encabezado['tipo'] ?? null) === 'simple') {
            $contenido .= "q 495 0 0 46 50 784 cm /Encabezado Do Q\n";
        }

        if ($tieneEncabezado && ($encabezado['tipo'] ?? null) === 'multiple') {
            $contenido .= "q 60 0 0 42 55 783 cm /Encabezado Do Q\n";
            $contenido .= $this->textoCentradoPdf('INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO', 10, 817, true);
            $contenido .= $this->textoCentradoPdf('“MANUEL SEOANE CORRALES”', 10, 802, true);
            $contenido .= $this->textoCentradoPdf('SAN JUAN DE LURIGANCHO', 8, 788, true);
        }

        foreach ($lineas as $linea) {
            $tipo = (string) ($linea['tipo'] ?? 'texto');
            $y = (float) ($linea['y'] ?? 0);

            if ($tipo === 'pie') {
                $contenido .= "q 0.65 0.65 0.65 RG 0.5 w 50 54 m 545 54 l S Q\n";
                $contenido .= 'BT /F1 7 Tf 1 0 0 1 50 38 Tm '.$this->pdfTexto((string) ($linea['texto'] ?? ''))." Tj ET\n";

                continue;
            }

            if ($tipo === 'linea') {
                $contenido .= 'q 0.30 0.30 0.30 RG 0.7 w 50 '.number_format($y, 2, '.', '').' m 545 '.number_format($y, 2, '.', '')." l S Q\n";

                continue;
            }

            if ($tipo === 'columnas') {
                $izquierda = $linea['izquierda'] ?? [];
                $derecha = $linea['derecha'] ?? [];
                $tamano = (int) $linea['tamano'];
                $altoLinea = $tamano + 3;

                foreach ($izquierda as $indice => $texto) {
                    $contenido .= 'BT /F1 '.$tamano.' Tf 1 0 0 1 105 '.number_format($y - ($indice * $altoLinea), 2, '.', '').' Tm '.$this->pdfTexto((string) $texto)." Tj ET\n";
                }

                foreach ($derecha as $indice => $texto) {
                    $contenido .= 'BT /F1 '.$tamano.' Tf 1 0 0 1 300 '.number_format($y - ($indice * $altoLinea), 2, '.', '').' Tm '.$this->pdfTexto((string) $texto)." Tj ET\n";
                }

                continue;
            }

            if ($tipo === 'firma') {
                if ($tieneFirma) {
                    $contenido .= 'q 150 0 0 52 223 '.number_format($y - 4, 2, '.', '')." cm /Firma Do Q\n";
                }

                continue;
            }

            if ($tipo === 'campo') {
                $etiqueta = (string) ($linea['etiqueta'] ?? '');
                $texto = (string) ($linea['texto'] ?? '');
                $tamano = (int) $linea['tamano'];
                $contenido .= 'BT /F2 '.$tamano.' Tf 1 0 0 1 58 '.number_format($y, 2, '.', '').' Tm '.$this->pdfTexto($etiqueta)." Tj ET\n";
                $prefijo = $etiqueta === '' ? '  ' : ': ';
                $fuenteValor = ($linea['valor_negrita'] ?? false) ? 'F2' : 'F1';
                $contenido .= 'BT /'.$fuenteValor.' '.$tamano.' Tf 1 0 0 1 136 '.number_format($y, 2, '.', '').' Tm '.$this->pdfTexto($prefijo.$texto)." Tj ET\n";

                continue;
            }

            $texto = (string) ($linea['texto'] ?? '');
            $tamano = (int) ($linea['tamano'] ?? 10);
            $fuente = ($linea['negrita'] ?? false) ? 'F2' : 'F1';
            $centrado = (bool) ($linea['centrado'] ?? false);
            $x = $centrado
                ? max(50, (595 - $this->anchoMemoTextoPuntos($texto, $tamano, (bool) ($linea['negrita'] ?? false))) / 2)
                : 50;
            if (($linea['alineacion'] ?? null) === 'derecha') {
                $x = max(50, 545 - $this->anchoMemoTextoPuntos($texto, $tamano, (bool) ($linea['negrita'] ?? false)));
            }
            $contenido .= 'BT /'.$fuente.' '.$tamano.' Tf 1 0 0 1 '.number_format($x, 2, '.', '').' '.number_format($y, 2, '.', '').' Tm '.$this->pdfTexto($texto)." Tj ET\n";

            if (($linea['subrayado'] ?? false) === true) {
                $ancho = max(20, $this->anchoMemoTextoPuntos($texto, $tamano, (bool) ($linea['negrita'] ?? false)));
                $contenido .= 'q 0.15 0.15 0.15 RG 0.6 w '.number_format($x, 2, '.', '').' '.number_format($y - 2, 2, '.', '').' m '.number_format($x + $ancho, 2, '.', '').' '.number_format($y - 2, 2, '.', '')." l S Q\n";
            }
        }

        if ($qr !== null) {
            $contenido .= $this->contenidoQr($qr);
        }

        return $contenido;
    }

    private function textoCentradoPdf(string $texto, int $tamano, float $y, bool $negrita = false): string
    {
        $fuente = $negrita ? 'F2' : 'F1';
        $x = max(50, (595 - $this->anchoMemoTextoPuntos($texto, $tamano, $negrita)) / 2);

        return 'BT /'.$fuente.' '.$tamano.' Tf 1 0 0 1 '.number_format($x, 2, '.', '').' '
            .number_format($y, 2, '.', '').' Tm '.$this->pdfTexto($texto)." Tj ET\n";
    }

    private function pdfTexto(string $texto): string
    {
        $codificado = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);

        if (! is_string($codificado)) {
            throw new RuntimeException('El contenido tiene caracteres que no se pudieron codificar en el PDF.');
        }

        $codificado = preg_replace('/[\x00-\x1F\x7F]/', ' ', $codificado) ?? $codificado;

        return '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $codificado).')';
    }
}
