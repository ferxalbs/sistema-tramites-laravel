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
     * @param  array{
     *     institucion: string,
     *     tipo_documento: string,
     *     numero: string,
     *     codigo_expediente: string,
     *     fecha_documento: string,
     *     asunto: string,
     *     destinatarios: list<string>,
     *     remitente: string,
     *     introduccion: ?string,
     *     contenido_principal: ?string,
     *     cierre: ?string,
     *     personas: list<string>,
     *     firmante: string,
     *     decision: string,
     *     conclusion: ?string,
     *     comentario_publico: ?string,
     *     codigo_verificacion: string,
     *     version_borrador: int
     * }  $documento
     * @return array{bytes: string, paginas: int}
     */
    public function generate(array $documento): array
    {
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

        $this->agregarParrafo($lineas, 'Atentamente,', 10, false, 22);
        $this->agregarParrafo($lineas, '________________________________________', 10, false, 4);
        $this->agregarParrafo($lineas, $documento['firmante'], 10, true, 2);
        $this->agregarParrafo($lineas, 'Versión revisada del borrador: '.$documento['version_borrador'], 8, false, 0);

        $qr = $this->matrizVerificacion($documento['codigo_verificacion']);
        $paginas = $this->distribuirEnPaginas($lineas, $documento['codigo_expediente'], $documento['codigo_verificacion'], $qr !== null);

        return [
            'bytes' => $this->construirPdf($paginas, $documento['numero'], $documento['institucion'], $qr),
            'paginas' => count($paginas),
        ];
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
     * @param  list<array{text: string, size: int, bold: bool, gap: int, center: bool}>  $lineas
     * @return list<list<array{text: string, size: int, bold: bool, y: float, center: bool}>>
     */
    private function distribuirEnPaginas(array $lineas, string $expediente, string $codigo, bool $conQr = false): array
    {
        $paginas = [[]];
        $paginaActual = 0;
        $y = 786.0;

        foreach ($lineas as $linea) {
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
    private function construirPdf(array $paginas, string $numero, string $institucion, ?ByteMatrix $qr = null): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $idsPaginas = [];

        foreach ($paginas as $indice => $lineas) {
            $idPagina = 5 + ($indice * 2);
            $idContenido = $idPagina + 1;
            $idsPaginas[] = $idPagina.' 0 R';
            $contenido = $this->contenidoPagina($lineas, $qr);
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$idContenido.' 0 R >>';
            $objetos[$idContenido] = '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido."\nendstream";
        }

        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $idsPaginas).'] /Count '.count($paginas).' >>';
        $idInfo = 5 + (count($paginas) * 2);
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
     * @param  list<array{text: string, size: int, bold: bool, y: float, center: bool}>  $lineas
     */
    private function contenidoPagina(array $lineas, ?ByteMatrix $qr = null): string
    {
        $contenido = "0.18 0.18 0.18 rg\n";

        foreach ($lineas as $linea) {
            $fuente = $linea['bold'] ? 'F2' : 'F1';
            $x = $linea['center']
                ? max(50, (595 - ($linea['size'] * mb_strwidth($linea['text']) * 0.52)) / 2)
                : 50;
            $contenido .= 'BT /'.$fuente.' '.$linea['size'].' Tf 1 0 0 1 '.number_format($x, 2, '.', '').' '.number_format($linea['y'], 2, '.', '').' Tm '.$this->pdfTexto($linea['text'])." Tj ET\n";

            if ($linea['text'] === '' && $linea['y'] > 730) {
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
