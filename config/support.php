<?php

return [
    'contact' => [
        'email' => env('SUPPORT_EMAIL', ''),
        'phone' => env('SUPPORT_PHONE', ''),
        'whatsapp' => env('SUPPORT_WHATSAPP', ''),
        'hours' => env('SUPPORT_HOURS', ''),
        'address' => env('SUPPORT_ADDRESS', ''),
    ],

    'messages' => [
        'public' => 'Hola, necesito orientación sobre el Sistema de Gestión Documentaria.',
        'login' => 'Hola, necesito ayuda para acceder al Sistema de Gestión Documentaria.',
        'student' => 'Hola, necesito ayuda con el seguimiento de un trámite.',
        'dashboard' => 'Hola, necesito orientación para usar el Sistema de Gestión Documentaria.',
    ],

    'topics' => [
        'consultar-tramite' => [
            'question' => '¿Cómo consulto mi trámite?',
            'answer' => 'Inicie sesión, abra “Mis trámites” y seleccione el expediente que desea consultar. Solo verá los trámites vinculados con su cuenta.',
            'assistant' => true,
            'faq' => true,
        ],
        'estado-observado' => [
            'question' => '¿Qué significa Observado?',
            'answer' => 'El revisor encontró puntos que deben atenderse antes de continuar. Revise las observaciones visibles y coordine la subsanación presencial con Mesa de Partes.',
            'assistant' => true,
            'faq' => true,
        ],
        'estado-revision' => [
            'question' => '¿Qué significa En revisión?',
            'answer' => 'El expediente fue asignado y está siendo evaluado por el responsable correspondiente. No necesita volver a registrarlo.',
            'assistant' => true,
            'faq' => true,
        ],
        'recibir-documento' => [
            'question' => '¿Cómo recibo mi documento?',
            'answer' => 'Cuando el documento esté autorizado, consulte la entrega en su expediente. La descarga y confirmación se realizan desde el expediente autorizado.',
            'assistant' => true,
            'faq' => true,
        ],
        'password' => [
            'question' => 'Olvidé mi contraseña',
            'answer' => 'Use la recuperación segura de contraseña. Si su cuenta cumple las condiciones, recibirá un enlace temporal en el correo registrado. El sistema no confirma públicamente si una cuenta existe.',
            'assistant' => true,
            'faq' => true,
        ],
        'login' => [
            'question' => 'No puedo iniciar sesión',
            'answer' => 'Compruebe su correo y contraseña, y verifique que su cuenta haya sido aprobada. Si hizo varios intentos, espere antes de probar otra vez o use la recuperación de contraseña.',
            'assistant' => true,
            'faq' => true,
        ],
        'requisitos' => [
            'question' => '¿Qué documentos necesito?',
            'answer' => 'Los requisitos dependen del tipo de trámite y deben ser confirmados por la institución. No envíe documentos personales por canales no autorizados.',
            'assistant' => true,
            'faq' => true,
        ],
        'corregir-observacion' => [
            'question' => '¿Cómo corrijo una observación?',
            'answer' => 'Revise los puntos visibles y entregue la subsanación físicamente en Mesa de Partes. La administración de la oficina registra la corrección; el estudiante no adjunta archivos desde este portal.',
            'assistant' => true,
            'faq' => true,
        ],
        'whatsapp' => [
            'question' => 'Contactar por WhatsApp',
            'answer' => 'Si la institución ha configurado WhatsApp, puede abrir el canal desde este asistente. El mensaje de orientación no incluirá automáticamente datos personales ni información del expediente.',
            'assistant' => true,
            'faq' => true,
        ],
        'privacidad-expedientes' => [
            'question' => '¿Puedo ver expedientes de otra persona?',
            'answer' => 'No. El acceso se limita por propiedad, asignación y rol.',
            'assistant' => false,
            'faq' => true,
        ],
        'activacion-cuenta' => [
            'question' => '¿La cuenta queda activa al registrarme?',
            'answer' => 'La cuenta pública queda pendiente de validación administrativa.',
            'assistant' => false,
            'faq' => true,
        ],
        'canales-notificacion' => [
            'question' => '¿Se envían correos o mensajes de WhatsApp sobre mis trámites?',
            'answer' => 'Las notificaciones del trámite se muestran dentro del sistema. El WhatsApp configurado se usa únicamente como canal de orientación y no envía datos privados del expediente.',
            'assistant' => false,
            'faq' => true,
        ],
        'password-compartida' => [
            'question' => '¿Puedo compartir mi contraseña?',
            'answer' => 'No. La contraseña es personal y no debe compartirse.',
            'assistant' => false,
            'faq' => true,
        ],
    ],

    'tutorials' => [
        ['title' => 'Cómo registrarse', 'steps' => ['Abra “Registrarme”.', 'Complete los datos requeridos.', 'Espere la validación administrativa.']],
        ['title' => 'Cómo iniciar sesión', 'steps' => ['Use su correo autorizado.', 'Ingrese su contraseña sin compartirla.', 'Revise el panel de su rol.']],
        ['title' => 'Cómo consultar el estado', 'steps' => ['Abra Mis trámites.', 'Seleccione el expediente.', 'Revise su estado y la información disponible.']],
        ['title' => 'Cómo leer una observación', 'steps' => ['Abra la notificación.', 'Revise cada punto visible.', 'Siga las indicaciones comunicadas por el personal responsable.']],
        ['title' => 'Cómo descargar un documento', 'steps' => ['Abra el expediente autorizado.', 'Seleccione el documento final.', 'Use la descarga protegida.']],
        ['title' => 'Cómo confirmar una entrega digital', 'steps' => ['Abra la entrega pendiente.', 'Verifique el documento.', 'Confirme la recepción.']],
    ],

    'role_guides' => [
        'estudiante' => [
            ['title' => 'Perfil', 'description' => 'Revise sus datos académicos y de contacto.'],
            ['title' => 'Mis trámites', 'description' => 'Consulte únicamente sus expedientes.'],
            ['title' => 'Notificaciones', 'description' => 'Lea cambios y alertas importantes.'],
            ['title' => 'Descargas', 'description' => 'Obtenga sus documentos autorizados.'],
        ],
        'docente' => [
            ['title' => 'Asignaciones', 'description' => 'Revise su carga asignada.'],
            ['title' => 'Revisión', 'description' => 'Inicie y documente cada ronda.'],
            ['title' => 'Observaciones', 'description' => 'Registre indicaciones claras.'],
            ['title' => 'Decisiones', 'description' => 'Apruebe o rechace con fundamento.'],
        ],
        'administrador' => [
            ['title' => 'Usuarios', 'description' => 'Administre cuentas y roles.'],
            ['title' => 'Recepción', 'description' => 'Digitalice en la oficina los documentos recibidos en Mesa de Partes.'],
            ['title' => 'Asignaciones', 'description' => 'Derive expedientes a los docentes revisores.'],
            ['title' => 'Documentos', 'description' => 'Prepare los PDF institucionales y registre su entrega.'],
            ['title' => 'Catálogos', 'description' => 'Mantenga configuraciones vigentes.'],
            ['title' => 'Reportes', 'description' => 'Consulte indicadores reales.'],
        ],
    ],
];
