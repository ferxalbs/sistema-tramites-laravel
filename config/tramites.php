<?php

return [
    // La recepción y revisión pueden probarse, pero no se debe emitir un PDF
    // oficial de estos tipos hasta cargar y verificar el modelo institucional.
    'modelos_oficiales_pendientes' => [
        'JUSTIFICACION_TARDANZA',
        'CONSTANCIA_PRACTICA',
    ],

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
        [
            'tipo_documento_salida' => 'memorando',
            'modalidad' => 'simple',
            'codigo' => 'MEM-SIMPLE',
            'prefijo' => 'MEM-S',
            'numero_formato' => '{correlativo}-{codigo_institucional}-{anio}',
            'codigo_institucional' => 'DSI-HACH-IESTP”MSC”',
            'correlativo_relleno' => 3,
        ],
        [
            'tipo_documento_salida' => 'memorando',
            'modalidad' => 'multiple',
            'codigo' => 'MEM-MULTIPLE',
            'prefijo' => 'MEM-M',
            'numero_formato' => '{correlativo}/{codigo_institucional}-{anio}',
            'codigo_institucional' => 'DSI/HACH/IESTP “MSC”',
            'correlativo_relleno' => 3,
        ],
        ['tipo_documento_salida' => 'constancia', 'modalidad' => 'unica', 'codigo' => 'CONSTANCIA', 'prefijo' => 'CON'],
    ],
];
