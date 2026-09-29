<?php

namespace Database\Seeders;

use App\Models\TramitePlantilla;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TramitePlantillaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campos = [
            ['NUMERO_DOCUMENTO_PREVIO', 'Número provisional', 'Datos generales', 'texto_corto', 'BORRADOR SIN NUMERACIÓN OFICIAL', 'borrador', 1, 0, 0, 1, 80],
            ['DESTINATARIO_NOMBRE', 'Destinatario', 'Destinatarios', 'lista_destinatarios', null, 'destinatarios', 0, 1, 0, 10, 180],
            ['DESTINATARIO_CARGO', 'Cargo del destinatario', 'Destinatarios', 'cargo_institucional', null, 'destinatarios', 0, 1, 0, 11, 160],
            ['LISTA_DESTINATARIOS', 'Lista de destinatarios', 'Destinatarios', 'lista_destinatarios', null, 'destinatarios', 0, 1, 1, 12, 4000],
            ['REMITENTE_NOMBRE', 'Remitente', 'Remitente y firmante', 'usuario', null, 'remitente', 1, 1, 0, 20, 180],
            ['REMITENTE_CARGO', 'Cargo del remitente', 'Remitente y firmante', 'cargo_institucional', null, 'remitente_cargo', 1, 1, 0, 21, 160],
            ['FIRMANTE_NOMBRE', 'Firmante propuesto', 'Remitente y firmante', 'usuario', null, 'firmante', 1, 1, 0, 22, 180],
            ['FIRMANTE_CARGO', 'Cargo del firmante', 'Remitente y firmante', 'cargo_institucional', null, 'firmante_cargo', 1, 1, 0, 23, 160],
            ['PROGRAMA_ESTUDIO', 'Programa de estudios', 'Datos académicos', 'programa_estudios', null, 'expediente_programa', 0, 1, 0, 30, 180],
            ['ESTUDIANTE_NOMBRE', 'Estudiante/Egresado', 'Datos académicos', 'texto_corto', null, 'estudiante_nombre', 0, 1, 0, 31, 180],
            ['DNI', 'DNI', 'Datos académicos', 'texto_corto', null, 'estudiante_dni', 0, 1, 0, 32, 8],
            ['CODIGO_ESTUDIANTE', 'Código de estudiante', 'Datos académicos', 'texto_corto', null, 'codigo_estudiante', 0, 1, 0, 33, 40],
            ['CICLO', 'Ciclo', 'Datos académicos', 'numero', null, 'ciclo', 0, 1, 0, 34, 2],
            ['TURNO', 'Turno', 'Datos académicos', 'select', null, null, 0, 0, 0, 35, 30],
            ['ANIO_EGRESO', 'Año de egreso', 'Datos académicos', 'numero', null, 'anio_egreso', 0, 1, 0, 36, 4],
            ['ASUNTO', 'Asunto', 'Contenido', 'texto_corto', null, 'expediente_asunto', 1, 1, 0, 40, 255],
            ['LUGAR', 'Lugar', 'Contenido', 'texto_corto', 'Lima', null, 1, 0, 0, 41, 120],
            ['FECHA', 'Fecha', 'Contenido', 'fecha', null, 'fecha_actual', 1, 0, 0, 42, 10],
            ['LUGAR_FECHA', 'Lugar y fecha', 'Contenido', 'texto_corto', null, 'lugar_fecha', 1, 1, 0, 43, 180],
            ['INTRODUCCION', 'Introducción', 'Contenido', 'texto_enriquecido', null, null, 0, 0, 1, 44, 3000],
            ['CONTENIDO_PRINCIPAL', 'Contenido principal', 'Contenido', 'texto_enriquecido', null, null, 1, 0, 1, 45, 12000],
            ['DOCUMENTOS_ADJUNTOS', 'Documentos adjuntos', 'Adjuntos', 'texto_largo', null, 'adjuntos', 0, 1, 1, 50, 3000],
            ['LISTA_PERSONAS_MENCIONADAS', 'Personas mencionadas', 'Personas', 'lista_personas', null, 'personas_mencionadas', 0, 1, 1, 51, 6000],
            ['DATOS_ESTUDIANTE_OPCIONALES', 'Datos académicos opcionales', 'Datos académicos', 'texto_largo', null, 'datos_estudiante', 0, 1, 1, 52, 2000],
            ['CIERRE', 'Cierre', 'Contenido', 'texto_enriquecido', '<p>Sin otro particular, quedo de usted.</p>', null, 0, 0, 1, 53, 2000],
            ['ENCABEZADO_INSTITUCIONAL', 'Encabezado institucional', 'Institución', 'texto_largo', 'Instituto de Educación Superior Tecnológico Público Manuel Seoane Corrales', null, 1, 0, 0, 60, 255],
        ];

        foreach ([
            [
                'codigo' => 'INFORME_INSTITUCIONAL',
                'version' => 1,
                'nombre' => 'Informe institucional',
                'descripcion' => 'Documento institucional con un destinatario principal.',
                'tipo_documento_salida' => 'informe',
                'modalidad' => null,
                'contenido' => "INFORME\n\nA: {{DESTINATARIO}}\nDe: {{REMITENTE}}\nAsunto: {{ASUNTO}}\nFecha: {{FECHA}}\n\n{{INTRODUCCION}}\n\n{{CONTENIDO}}\n\n{{CIERRE}}\n\n{{FIRMANTE}}",
                'requiere_firma_fisica' => true,
                'permite_no_firma' => false,
                'estado' => 'publicada',
                'activa' => true,
            ],
            [
                'codigo' => 'MEMORANDO_SIMPLE',
                'version' => 1,
                'nombre' => 'Memorando simple',
                'descripcion' => 'Memorando institucional dirigido a una persona.',
                'tipo_documento_salida' => 'memorando',
                'modalidad' => 'simple',
                'contenido' => "MEMORANDO\n\nA: {{DESTINATARIO}}\nDe: {{REMITENTE}}\nAsunto: {{ASUNTO}}\nFecha: {{FECHA}}\n\n{{INTRODUCCION}}\n\n{{CONTENIDO}}\n\n{{CIERRE}}\n\n{{FIRMANTE}}",
                'requiere_firma_fisica' => false,
                'permite_no_firma' => true,
                'estado' => 'publicada',
                'activa' => true,
            ],
            [
                'codigo' => 'MEMORANDO_MULTIPLE',
                'version' => 1,
                'nombre' => 'Memorando múltiple',
                'descripcion' => 'Memorando institucional para dos o más destinatarios.',
                'tipo_documento_salida' => 'memorando',
                'modalidad' => 'multiple',
                'contenido' => "MEMORANDO MÚLTIPLE\n\nA: {{DESTINATARIOS}}\nDe: {{REMITENTE}}\nAsunto: {{ASUNTO}}\nFecha: {{FECHA}}\n\n{{INTRODUCCION}}\n\n{{CONTENIDO}}\n\n{{CIERRE}}\n\n{{FIRMANTE}}",
                'requiere_firma_fisica' => false,
                'permite_no_firma' => true,
                'estado' => 'publicada',
                'activa' => true,
            ],
        ] as $plantilla) {
            $modelo = TramitePlantilla::query()->firstOrCreate(
                ['codigo' => $plantilla['codigo'], 'version' => $plantilla['version']],
                $plantilla,
            );

            foreach ($campos as [$clave, $etiqueta, $grupo, $tipo, $predeterminado, $fuente, $obligatorio, $confirmacion, $html, $orden, $maximo]) {
                DB::table('tramite_plantilla_campos')->insertOrIgnore([
                    'plantilla_id' => $modelo->id,
                    'clave_variable' => $clave,
                    'etiqueta' => $etiqueta,
                    'grupo' => $grupo,
                    'tipo_campo' => $tipo,
                    'valor_predeterminado' => $predeterminado,
                    'fuente_automatica' => $fuente,
                    'obligatorio' => $obligatorio,
                    'requiere_confirmacion' => $confirmacion,
                    'permite_html' => $html,
                    'orden' => $orden,
                    'longitud_maxima' => $maximo,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
