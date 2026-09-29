<?php

return [
    'destinos' => [
        'oficina' => 'Oficina',
        'docente' => 'Docente',
    ],

    'prioridades' => [
        'baja' => 'Baja',
        'normal' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ],

    'estados' => [
        'recibido_oficina' => 'Recibido en oficina',
        'digitalizado' => 'Digitalizado',
        'borrador_preparado' => 'Borrador preparado',
        'pendiente_asignacion' => 'Pendiente de asignación',
        'asignado' => 'Asignado',
        'en_revision' => 'En revisión',
        'observado' => 'Observado',
        'corregido' => 'Corregido',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
        'documento_final_generado' => 'Documento final generado',
        'pendiente_firma' => 'Pendiente de firma',
        'listo_entrega' => 'Listo para entregar',
        'entregado' => 'Entregado',
        'cerrado' => 'Cerrado',
    ],

    'series_documentales' => [
        ['tipo_documento_salida' => 'informe', 'modalidad' => 'unica', 'codigo' => 'INFORME', 'prefijo' => 'INF'],
        ['tipo_documento_salida' => 'memorando', 'modalidad' => 'simple', 'codigo' => 'MEM-SIMPLE', 'prefijo' => 'MEM-S'],
        ['tipo_documento_salida' => 'memorando', 'modalidad' => 'multiple', 'codigo' => 'MEM-MULTIPLE', 'prefijo' => 'MEM-M'],
    ],
];
