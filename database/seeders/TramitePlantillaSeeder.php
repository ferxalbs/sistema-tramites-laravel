<?php

namespace Database\Seeders;

use App\Models\TramitePlantilla;
use Illuminate\Database\Seeder;

class TramitePlantillaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            TramitePlantilla::query()->firstOrCreate(
                ['codigo' => $plantilla['codigo'], 'version' => $plantilla['version']],
                $plantilla,
            );
        }
    }
}
