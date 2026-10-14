<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $contents = [
            'CONSTANCIA_PRACTICA' => "CONSTANCIA DE PRACTICAS\n\nSe deja constancia de la evaluacion de la documentacion presentada por {{ESTUDIANTE_NOMBRE}}, con DNI {{DNI}}, del programa de {{PROGRAMA_ESTUDIO}}.\n\nCentro de practicas: {{CENTRO_PRACTICAS}}\nModulo formativo: {{MODULO_PRACTICAS}}\nPeriodo: {{PERIODO_INICIO}} al {{PERIODO_FIN}}\nHoras declaradas y verificadas: {{HORAS_PRACTICAS}}\nDocumentos de sustento: {{DOCUMENTOS_ADJUNTOS}}\n\nResultado y alcance de la constancia:\n{{CONTENIDO_PRINCIPAL}}\n\n{{LUGAR_FECHA}}\n\n{{FIRMANTE}}",
            'JUSTIFICACION_TARDANZA' => "EVALUACION DE JUSTIFICACION DE TARDANZA\n\nA: {{DESTINATARIO}}\nDe: {{REMITENTE}}\nFecha: {{FECHA}}\n\nSolicitante: {{ESTUDIANTE_NOMBRE}}\nDNI: {{DNI}}\nPrograma: {{PROGRAMA_ESTUDIO}}\nUnidad didactica: {{UNIDAD_DIDACTICA}}\nFecha de tardanza: {{FECHA_TARDANZA}}\nMotivo presentado: {{MOTIVO_TARDANZA}}\nSustento revisado: {{DOCUMENTOS_ADJUNTOS}}\n\nResultado de la evaluacion y observaciones:\n{{CONTENIDO_PRINCIPAL}}\n\nLa decision se comunica al docente responsable para su registro segun las disposiciones academicas aplicables.\n\n{{LUGAR_FECHA}}\n\n{{FIRMANTE}}",
        ];
        foreach ($contents as $type => $content) {
            $source = DB::table('tramite_plantillas')->where('codigo', $type.'_REFERENCIAL')->first();
            if ($source === null) {
                continue;
            }
            $data = (array) $source;
            unset($data['id']);
            $data['codigo'] = $type.'_PROPUESTO';
            $data['nombre'] = $type === 'CONSTANCIA_PRACTICA' ? 'Constancia de prácticas - modelo propio' : 'Justificación de tardanza - modelo propio';
            $data['descripcion'] = 'Modelo propuesto. El uso oficial requiere revisión y validación de la institución.';
            $data['contenido'] = $content;
            $data['modelo_propuesto'] = true;
            $data['tipo_tramite'] = $type;
            $data['estado'] = 'publicada';
            $data['activa'] = true;
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table('tramite_plantillas')->insertGetId($data);
            foreach (DB::table('tramite_plantilla_campos')->where('plantilla_id', $source->id)->get() as $field) {
                $copy = (array) $field;
                unset($copy['id']);
                $copy['plantilla_id'] = $id;
                DB::table('tramite_plantilla_campos')->insert($copy);
            }
        }
    }

    public function down(): void
    {
        // Do not erase models that may already be referenced by a reviewed document.
    }
};
