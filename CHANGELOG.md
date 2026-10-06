# Changelog

Registro de cambios del Sistema de Trámites. Actualizado el **6 de octubre de 2026** a partir del historial de Git, el código y las verificaciones realizadas. No contiene contraseñas, claves SMTP ni tokens.

## 2026-10-06 — Constancia institucional y almacenamiento publicado

- Se conservaron los cambios de los commits `4cc2f94` y `03ee98d` en `main`. El preset nuevo había retirado la compatibilidad de `Button render` con enlaces y añadido imports CSS duplicados; se restauró la compatibilidad y se corrigió el formato de sus archivos. Se conservaron sus fuentes y su diseño.
- La vista previa usa `setLayoutProps` de Inertia 3 para sus breadcrumbs. La función anterior de layout dejaba la página en blanco aunque TypeScript pasara; Chrome confirmó ahora el PDF privado, su advertencia provisional y la navegación del expediente sin errores de consola.
- `pnpm run check` pasó: 120 archivos con formato correcto y 103 archivos sin avisos de lint. `pnpm run types:check` y el build con PHP 8.5 en PATH pasaron. La suite local ejecutada en esta sesión terminó con 171 aprobadas, 6 remotas omitidas y 3235 aserciones (21,04 s). Pint y `bash tests/Deployment/entrypoint-failure.sh` también pasaron. Las omitidas no certifican Turso remoto.
- Antes de montar el volumen se respaldó Turso y se restauró la copia en SQLite desechable: 102 tablas, 49 migraciones, integridad correcta y cero violaciones de claves foráneas. El respaldo privado permanece fuera de Git. No había expedientes ni archivos privados documentales en el contenedor inspeccionado.
- Railway tiene un volumen montado en `/app/storage/app/private`; la consola confirmó el montaje ext4 y lectura/escritura mediante un marcador privado. El mismo marcador sobrevivió a los despliegues de `03ee98d`, `5abfa32` y `d7b7ebf`. Se descargó un respaldo manual del volumen y se restauró en una carpeta desechable con marcador idéntico. Esto acredita el marcador; todavía no acredita archivos documentales cargados en producción.
- Se verificó en el dominio publicado el acceso autenticado a Notificaciones y Bandeja, el registro de un Memorando ficticio expresamente marcado como prueba técnica y el borrador privado incompleto con PDF provisional descargable. No se emitió ni reservó numeración oficial de prueba. La carga de evidencia quedó bloqueada por el permiso de archivos locales de la extensión; la revisión y entrega requieren cuentas autorizadas de otros roles y responsables con cargo institucional activo. Emisión, QR y cierre publicados siguen pendientes. Los respaldos automáticos del volumen están restringidos al plan Pro; no se cambió el plan.
- La constancia de titulación usa ahora el encabezado oficial extraído del Word, título subrayado, texto aprobado completo y fecha junto a la firma en la última página. La selección depende del tipo canónico guardado en la instantánea, incluso si se renombra la plantilla; las otras constancias conservan su generador. Preview y emisión reservan el mismo espacio de QR. Se cotejó una muestra privada normal y se probó contenido largo; no se afirma identidad tipográfica con la fotografía. Los PDF históricos conservan sus bytes.
- En navegador local aislado se completó además el Memorando múltiple rechazado: revisión, emisión, descarga, entrega, confirmación y cierre. Preview y emisión tienen tres páginas e incluyen fundamento y comentario público; el comprobante de cierre tiene dos.
- El acceso usa `FieldGroup`, `Field`, `FieldLabel` y `FieldError` con validación accesible comprobada en Chrome. El borrador enlaza a su expediente real y avisa cuando faltan responsables con cargo activo. La emisión indica «Versión revisada» para incluir correctamente resultados rechazados. React Doctor sobre los cambios finales evaluados reportó 71/100 y «No issues found!»; esa puntuación no certifica ausencia de problemas en todo el proyecto. PHPStan conserva 533 hallazgos en la ejecución registrada.
- Los estados del 5 y 2 de octubre siguientes son evidencias históricas; el volumen y los controles de frontend pendientes entonces se verificaron el 6 de octubre como se detalla arriba.

- La validación publicada detectó un 500 en la ruta pública de verificación con una cuenta recordada: `EnsureAccountIsCurrent` intentaba acceder a una sesión que esa ruta excluye. Ahora la ruta mantiene contexto público sin resolver la cuenta recordada; no comparte usuario ni contador de notificaciones. La prueba existente cubre también acceso sin código desde una cuenta autenticada, sin cookie de sesión. Las tres pruebas de verificación pública pasaron con 101 aserciones.

## 2026-10-05 — Revisión local del recorrido

- Se aplicó en la base local la migración pendiente del catálogo de destinatarios institucionales tras verificar un respaldo de la base. La pantalla de inicio y el acceso de administración responden en `http://127.0.0.1:8000`; el editor de borradores abre sin error.
- El editor solo ofrece la plantilla de constancia de titulación para ese tipo de trámite. Informe y Memorandos muestran las plantillas de su formato y modalidad. Las demás solicitudes pueden usar borradores internos, pero deben elegir explícitamente una plantilla; el servidor rechaza una plantilla incompatible aunque se envíe fuera de la interfaz.
- Para justificación de tardanza y constancia de prácticas, el editor indica que falta el modelo oficial y que la emisión final permanece bloqueada. La corrección se verificó en el navegador local, con una prueba funcional específica y con la suite del flujo.
- El gráfico de usuarios ya no presenta el rol Asistente retirado; su cuenta histórica permanece inactiva y conservada en la base.
- Quedan pendientes los modelos oficiales de tardanza y prácticas, los datos SMTP institucionales, las firmas y cuentas de los docentes reales y la prueba de persistencia y migraciones en el alojamiento. No se modificó Railway.

## Estado actual — auditoría del 2026-10-05

- La auditoría partió de `bc0da38`, sin reconstruir los cambios publicados. Durante la sesión se añadió en `main` el commit ajeno `c00ee71`; se conservó su trabajo. No se crearon ramas ni worktrees.
- Los documentos internos solo ofrecen y aceptan plantillas de su formato y modalidad canónicos, incluso al guardar sin preparar. Se corrigió la selección inicial de una Constancia para un Memorando detectada en navegador.
- La vista previa del borrador ya revisado toma su última ronda cerrada de aprobación o rechazo. El Memorando emitido tras un rechazo ahora incluye el resultado, fundamento y comentario público de revisión; el generador específico los omitía, aunque la emisión los exigía. Los aprobados conservan su contenido.
- Los nombres y cargos de remitente y firmante se toman de los valores guardados del borrador; editar después el perfil no cambia el texto que se revisó. Los PDF históricos no se regeneran.
- Se compararon los Word oficiales: el simple conserva `A` antes de `De`; el múltiple usa `De` antes de `A` y su cierre queda alineado a la izquierda. El segundo Word contiene los memorandos 005 y 004 en una página horizontal. La aplicación genera cada documento por separado. Los encabezados conservan los recursos oficiales extraídos de los Word.
- Los tamaños se cotejaron con el XML original: campos de 11 puntos y cuerpo/filas de personas de 12 en el simple; campos y cuerpo de 10 en el múltiple. Se reemplazó el factor aproximado de negrita por anchos de Helvetica-Bold para los caracteres cubiertos. Los caracteres sin métrica conservan una aproximación; Helvetica no es idéntica a Arial/Calibri del original. La estructura se cotejó, pero no se certifica identidad tipográfica o de márgenes.
- La previsualización y emisión reservan el mismo espacio del QR, también para el contenido aprobado de Constancia e Informe. Los casos largos con el mismo texto conservan la cantidad de páginas; la vista previa posterior a la revisión incluye también la decisión y fundamento del rechazo para mantener ese mismo texto. Los memorandos de cotejo privado simple/múltiple tienen una página y el largo tres, tanto sin QR como con QR.
- El inicio, registro, asignación, borrador, entrega y confirmación estudiantil usan composición nativa de los componentes existentes shadcn/Base UI (`Select`, `Field`, `FieldGroup`, `Checkbox`, `Card`). La validación de asignación se comprobó en navegador con `aria-invalid` y descripción del error. No se añadieron dependencias.
- Chrome muestra el PDF privado del borrador guardado. Se retiró el `sandbox` que impedía al navegador cargar su visor PDF y se añadió una apertura directa del mismo PDF autenticado.
- Navegador local, SQLite desechable y almacenamiento privado separado: Memorando simple registrado, observado por docente, corregido en versión nueva, aprobado, emitido, descargado, entregado, confirmado y cerrado; Constancia de titulación con autocompletado por DNI, FUT ficticio privado, aprobación, emisión, entrega presencial con evidencia, confirmación por la cuenta egresada y cierre. La cuenta egresada recibió 403 al abrir un expediente ajeno.
- La Constancia final contiene el nombre, DNI, programa, modalidad y texto resuelto aprobado del egresado ficticio. Conserva un encabezado genérico; no reproduce todavía la presentación de la fotografía institucional. La instantánea conserva el texto y su huella.
- El QR del Memorando emitido se leyó con un lector independiente y abrió una verificación válida sin sesión local. El QR de la Constancia también se decodificó. Esto no certifica el QR publicado en Railway.
- Verificación final local: `PAO_DISABLE=1 vendor/bin/pest --display-all-issues` terminó con **171 pruebas aprobadas, 6 remotas omitidas y 3210 aserciones**; `bash tests/Deployment/entrypoint-failure.sh`, TypeScript, build y Pint de los PHP modificados pasaron. Las pruebas omitidas siguen sin demostrar integración ni concurrencia remota en Turso.
- `pnpm run check` quedó bloqueado por el formato de `package.json` y `skills-lock.json` del commit ajeno, conservado sin cambios. PHPStan con `--memory-limit=1G` reportó 533 hallazgos; no pasó. React Doctor reportó 66/100 y ocho avisos: cuatro de complejidad de páginas existentes, dos por `iframe` sin sandbox para el visor PDF y dos por anclas de composición `Button render`; Chrome confirmó los nombres accesibles de esos botones. No se suprimieron reglas.
- Railway: el despliegue `39224c7a-bffd-4920-b366-ec1cddf32d54` correspondiente a `bc0da38` figura activo/exitoso; las cuatro migraciones del 2 de octubre están aplicadas. El dominio público es `https://sistema-tramites-laravel-production.up.railway.app`.
- La consola de producción mostró `/app/storage/app/private` sobre `overlay`, **sin volumen persistente**, y cero archivos privados excluyendo `.gitignore`. Las tablas consultadas no tienen documentos, documentos finales, firmas de trámite ni informes de cierre con ruta. Falta aprobar el volumen, respaldar la base, comprobar el marcador después de desplegar y recorrer la versión publicada con acceso autorizado. No se certifica persistencia ni se atribuyen estas pruebas locales a producción.
- Auditoría funcional: `JUSTIFICACION_TARDANZA` no implementa destinatario de primera hora y no hay flujo `INASISTENCIA`; `CONSTANCIA_PRACTICA` es demostrativa, sin viabilidad, aforo o circuito de supervisor. La emisión de esos dos modelos permanece bloqueada. No se inventaron reglas institucionales ni se habilitó su emisión.

## Estado registrado — 2026-10-02

- El director y otros destinatarios institucionales ya tienen un catálogo administrable; las sugerencias del borrador leen ese catálogo y excluyen entradas inactivas. La ayuda delimita la revisión docente de la preparación y emisión administrativas.
- El arranque rechaza base de datos ausente, migración fallida y un volumen Railway declarado en ruta incorrecta. Se agregó una prueba de fallo de arranque y un marcador para comprobar persistencia entre despliegues.
- **Pendiente fuera del repositorio:** adjuntar el volumen a Railway, respaldar su contenido y Turso, ensayar migraciones en un entorno separado y verificar firmas/adjuntos/PDF tras un nuevo despliegue. Detalles en `docs/DESPLIEGUE_SEGURO.md`.
- Esta revisión reúne la unificación del código docente con el DNI, la retirada del rol Asistente y las correcciones de recepción, PDF y entrega descritas abajo.
- Las cuatro migraciones del 2 de octubre se aplicaron correctamente en SQLite de pruebas. Su aplicación en la base de producción debe verificarse después del despliegue.
- La suite completa terminó con **168 pruebas aprobadas, 6 omitidas y 3120 aserciones**. Las seis pruebas omitidas requieren una base Turso remota desechable.
- `pnpm run types:check` y `pnpm run build` pasaron. Las pruebas locales no demuestran por sí solas el funcionamiento completo de producción.
- La revisión estática PHPStan existente aún tiene 529 hallazgos en el proyecto, principalmente en el adaptador Turso y modelos Eloquent. Se mantiene como trabajo pendiente; la verificación continua ejecuta las pruebas funcionales y los chequeos de frontend que sí tienen una línea base limpia.
- El envío real de correos está implementado, pero no se puede activar ni comprobar con el correo institucional hasta conocer y configurar sus datos SMTP y su secreto en Railway.
- Los PDF oficiales de justificación de tardanza y constancia de prácticas están bloqueados hasta recibir los modelos aprobados. Sus FUT pueden registrarse y revisarse.
- El servidor local responde en `http://127.0.0.1:8000/login` (HTTP 200). Las cuentas de demostración Administrador, Docente y Estudiante conservan su contraseña local conocida; la cuenta histórica Asistente quedó inactiva.

## 2026-10-02 — Correcciones de recepción, PDF y correo

- Un FUT puede registrarse con DNI, nombre y datos de contacto aunque todavía no exista una cuenta estudiantil activa. Cuando la administración activa esa cuenta, los expedientes pendientes se vinculan por DNI y se deja un evento de seguimiento.
- Informe y Memorando simple/múltiple creados en la oficina comienzan directamente en estado digitalizado y pueden pasar al borrador sin adjuntar un archivo de solicitud inexistente.
- El PDF final de Informe y Constancia incorpora el contenido completo del borrador aprobado, además de la firma escaneada y el código de verificación. El diseño de Memorando conserva su maquetación específica. Falta comparar visualmente cada PDF con el modelo institucional definitivo.
- La entrega por correo adjunta el PDF verificado y registra el envío solo después de que el transporte lo acepte. Si el envío falla o es ambiguo, se conserva el registro para revisión. Los transportes de prueba `log` y `array` no permiten simular una entrega real.
- La verificación de correo y recuperación de contraseña usan el transporte de Laravel; para que salgan al exterior se necesitan los datos SMTP institucionales indicados en `docs/CORREO_INSTITUCIONAL.md`.
- La emisión oficial de justificación de tardanza y constancia de prácticas se detiene hasta recibir los modelos y reglas oficiales. Los requisitos están en `docs/MODELOS_OFICIALES_PENDIENTES.md`.
- El arranque en Railway ahora falla si una migración falla; así se evita que una versión nueva aparezca sana con el esquema anterior.
- El comprobante vuelve a mostrar el estado registrado al recibir el expediente, incluso después de cambios posteriores de estado.

## 2026-10-02 — Recepción en oficina y retiro del rol Asistente

- Las altas y ediciones administrativas de docentes guardan `codigo_docente` igual al DNI; el formulario ya no pide un segundo código y el perfil muestra un único identificador.
- La solicitud pública de acceso docente ahora exige un DNI único y lo usa también como código docente; antes creaba perfiles sin ese identificador.
- La migración `2026_10_02_000000_sync_teacher_codes_with_dni.php` normaliza perfiles existentes. Una prueba cubre códigos antiguos que podrían colisionar durante la actualización.
- El estudiante presenta físicamente sus solicitudes en Mesa de Partes. La oficina de Desarrollo de Sistemas de la Información las recibe y digitaliza. La cuenta estudiantil consulta el expediente y sus resultados; la presentación en línea queda para una etapa futura.
- El Administrador puede registrar y editar la recepción, cargar y corregir documentos, preparar borradores, asignar revisiones a docentes, emitir el PDF final y registrar la entrega. Informe y Memorando simple/múltiple pueden originarse en la propia oficina, sin DNI de solicitante.
- Se retiró Asistente de la creación de cuentas, la navegación y las rutas operativas. Una migración desactiva las cuentas históricas de ese rol y cierra sus sesiones; la administración puede reasignarles un rol vigente tras revisar cada cuenta. El docente mantiene su revisión de expedientes asignados.
- El formulario de borrador sugiere los destinatarios institucionales activos y a todos los docentes activos; sus nombres, cargos y correos se copian a campos editables. Los docentes de cursos complementarios pueden registrarse sin programa de estudios.
- Las pruebas focalizadas de cuentas, perfil, notificaciones, búsqueda, recepción, emisión y entrega se adaptaron al nuevo flujo. La comparación visual exacta de cada PDF con los modelos oficiales y las pruebas remotas Turso siguen pendientes.
- Para esta versión, la administración redacta y emite; el docente revisa únicamente expedientes asignados y su firma de perfil puede insertarse cuando es seleccionado como firmante. Cambiar ese reparto requiere confirmar un proceso institucional distinto.

## 2026-09-30 a 2026-10-01 — Auditoría local de PDF, memorandos y catálogos

- El formato canónico `tipo_documento_salida` determina si el generador usa el diseño de Memorando; el nombre de la plantilla solo se usa como compatibilidad cuando falta ese campo. La modalidad canónica determina Memorando simple o múltiple. La prueba local también cubre una plantilla renombrada y evita que un tipo `informe` se renderice como Memorando.
- El Memorando simple usa el encabezado institucional PNG `resources/images/institucion/encabezado-institucional.png` (SHA-256 `312b97ffc2e808582374668aedcd8ee3129f98c446f1bf7220dce6c0a0b6c56d`); el múltiple usa el escudo JPEG `resources/images/institucion/encabezado-memorando-multiple.jpeg` (SHA-256 `6e54e877c56408f4e507b339db7088b6894ed54be8b09215c8f28ba628c97a73`) y tres líneas institucionales seleccionables. La estructura actual conserva `A`, `De`, nombres, cargos, asunto, fecha, separador, cierre, firma y paginación larga; la equivalencia visual completa con los Word originales todavía no está certificada.
- El PDF usa las fuentes Type 1 Helvetica y Helvetica-Bold. La medición de Helvetica normal está implementada y la de negrita conserva aproximaciones; no se afirma identidad tipográfica con los originales. La reserva del pie deja el espacio del QR en la previsualización y la emisión: el test de Memorando largo conserva la misma cantidad de páginas con código válido o sin él.
- La previsualización de borradores es privada, no almacena número oficial ni muta numeración, expedientes, eventos o archivos. La URL pública definitiva y la lectura independiente del QR todavía no están verificadas en Railway.
- La auditoría funcional confirmó que `JUSTIFICACION_TARDANZA` sigue siendo un catálogo provisional sin lógica específica para el destinatario de primera hora; no existe un tipo o flujo `INASISTENCIA`. `CONSTANCIA_PRACTICA` permanece como catálogo demostrativo y no tiene reglas específicas de viabilidad, aforo, supervisor o aprobación de prácticas.
- `CONSTANCIA_MODALIDAD_TITULACION` existe como catálogo y plantilla publicada. En esta auditoría se detectó que el PDF final solo conservaba la huella del borrador; el problema se corrigió el 2 de octubre al incorporar también su texto renderizado completo.
- El servidor local de prueba quedó preparado en `http://127.0.0.1:18080` con base desechable, almacenamiento privado y cuatro usuarios ficticios; `/login` respondió 200. El recorrido completo mediante navegador todavía no se certifica.

## 2026-09-29 — Registro de Informe y Memorandos (`190669b`)

- Se retiró **Requerimiento de equipamiento** de los tipos disponibles para nuevos registros. El tipo quedó inactivo y los expedientes históricos se conservan.
- Se añadieron **Informe**, **Memorando simple** y **Memorando múltiple** como documentos institucionales de clasificación administrativa.
- Estos tres tipos se registran sin solicitud ciudadana, DNI, nombre de solicitante ni cuenta propietaria. El servidor valida esa diferencia y permite guardar el solicitante como nulo.
- El formato se fija según el tipo elegido: Informe usa `informe`; los Memorandos usan `memorando` y su modalidad simple o múltiple. El Memorando múltiple exige al menos dos destinatarios.
- **Iniciar registro** muestra los grupos **Solicitud** y **Documento institucional**. El DNI aparece cuando el tipo requiere solicitante y el botón cambia entre **Digitalizar solicitud** y **Registrar documento**.
- Se reemplazó el rótulo **Mesa de Partes** sobre **Iniciar registro** por **Registro documental**.
- El formulario de documentos institucionales utiliza **Asunto del documento** y **Contenido del documento**, y oculta los datos de solicitante y la selección redundante del formato.
- Se ajustaron bandejas, asignaciones, detalles, comprobantes e informe de cierre para mostrar documentos sin solicitante.
- Se añadió la migración `2026_09_29_071000_add_internal_document_registration_types.php` y se actualizaron pruebas de registro, catálogos y filtros del dashboard.
- La prueba específica de los tres tipos pasó con **79 aserciones**. La suite completa y la compilación pasaron después de actualizar el fixture del tipo retirado y terminar la generación de assets antes de repetir las pruebas.

## 2026-09-29 — Correcciones solicitadas y simplificación (`339e515`)

### Acceso, usuarios e identidad

- Se ajustaron el alta y la edición administrativa de usuarios, sus datos por rol, el cambio de estado y la invalidación de sesiones al cambiar información de acceso.
- La acción **Gestionar** se reemplazó por **Editar** y abre directamente el formulario. Allí se pueden actualizar datos, activar, desactivar y eliminar cuentas, con confirmación y notificación del resultado.
- La eliminación protege la cuenta propia, el último administrador activo y las cuentas con trámites o registros vinculados. Cuando existe historial, corresponde desactivar la cuenta.
- La tabla distingue **Estudiante** y **Egresado** según la condición académica del perfil, manteniendo el rol técnico común de acceso estudiantil.
- El código del estudiante se sincronizó con su DNI en los perfiles existentes y en las altas y actualizaciones posteriores. Se eliminó la necesidad de ingresar un código estudiantil separado.
- Se amplió la búsqueda por DNI en usuarios, alumnos, expedientes y búsquedas internas, respetando el alcance de cada rol.
- El docente de destino se selecciona de una lista de docentes activos que permite buscar por nombre o DNI.
- Se añadieron controles de caché y de historial del navegador después del cierre de sesión para impedir que volver con la flecha restaure una pantalla autenticada utilizable.

### Recepción y digitalización

- El registro comienza con DNI y tipo de trámite y abre el formulario correspondiente, en lugar de presentar todos los campos en una única pantalla inicial.
- El DNI vincula los datos ya guardados del alumno: nombre, correo, teléfono y programa de estudios, evitando transcribirlos otra vez.
- Se incorporaron la **sumilla o resumen de la solicitud**, la **fundamentación o detalle**, la fecha del documento, la fecha de recepción, la observación de recepción y los folios.
- Se distinguió la fecha escrita en el FUT de la fecha de recepción. El código interno del expediente se genera al guardar; no se exige copiar un código inexistente del FUT.
- Se retiró el campo visible **Oficina de destino** del formulario y se conservaron los datos necesarios para la asignación posterior.
- Las listas de personas relacionadas, destinatarios preliminares y personas mencionadas se pueden mostrar cuando corresponden; permanecen opcionales salvo una regla específica del tipo.

### Catálogos y navegación

- Se retiraron de los tipos disponibles **Autorización de ingreso**, **Comunicación administrativa**, **FUT**, **Solicitud general** y el tipo genérico **Justificación**.
- Se dejó **Justificación de tardanza** y se cambió `SOLICITUD_CONSTANCIA_MODALIDAD_TITULACION` a `CONSTANCIA_MODALIDAD_TITULACION`.
- Se añadió el catálogo y la plantilla de **Constancia de modalidad de examen de titulación**, tomando como referencia el documento proporcionado.
- Se retiró la clasificación **Institucional** y sus registros se trasladaron a **Administrativo**.
- Los formatos de salida permanecen activos y se eliminó su control de activación/desactivación. La configuración de modelos y versiones continúa en **Plantillas documentales**.
- Se retiraron las pantallas y rutas de **Plazos**, **Feriados** y **Auditoría**, así como el cálculo y los recordatorios de plazos. Los registros internos necesarios para conservar el historial continúan en el servidor.

### Perfil, firmas y entregas

- Se añadió acceso a **Mi perfil** junto a **Configuración**. Permite editar nombres y datos de contacto, además de los campos personales habilitados para cada rol.
- El DNI, el rol y los datos institucionales que controla la administración mantienen sus restricciones de edición.
- Cada usuario puede subir su firma escaneada desde el perfil mediante una imagen JPG o PNG, con validación y consentimiento para su uso.
- El estudiante sube una imagen recortada de la firma de su primera solicitud. La imagen se conserva de forma privada en su perfil.
- La firma del personal se inserta automáticamente en los PDF nuevos cuando su cuenta es la firmante. La emisión exige una firma registrada y conserva su huella en la instantánea del documento; los PDF ya emitidos no se regeneran al cambiar la firma.
- Se simplificó **Entregas y cierres**: **Presencial**, **Correo electrónico** y **Descarga desde el sistema** son los medios disponibles y permanecen activos. **Otro medio autorizado** quedó inactivo.
- Se retiró la configuración de exigencia de firma física por plantilla que resultaba redundante con la firma guardada en el perfil.
- La disponibilidad del medio **Correo electrónico** registra el canal de entrega; el envío automático real de correos necesita la configuración pendiente descrita más abajo.

### Ayuda y chatbot

- Se conservó **¿Necesitas ayuda?** y se añadió un chatbot con mensajes y respuestas sobre los temas de ayuda existentes.
- El chatbot reconoce preguntas mediante palabras clave y ofrece acceso a WhatsApp cuando el canal está configurado. No requiere un proveedor externo de inteligencia artificial para su funcionamiento actual.
- Se dejó el soporte de WhatsApp configurable mediante `SUPPORT_WHATSAPP`; el número solicitado fue **+51 906 259 059**.
- WhatsApp se abre por decisión del usuario. No constituye un envío automático de notificaciones privadas ni una integración con la API de WhatsApp Business.
- Se guardó el plan de verificación de correo y recuperación en [docs/pendiente-correo-verificacion-y-recuperacion.md](docs/pendiente-correo-verificacion-y-recuperacion.md), para retomarlo en una sesión futura.

## 2026-09-28 a 2026-09-29 — Ampliación posterior a las primeras siete fases

Estos cambios están en los commits posteriores a `8c44879` y anteriores a `339e515`. Complementan las fases históricas conservadas al final de este archivo.

- **Cuentas y autenticación:** ciclo de aprobación, rechazo, activación y desactivación; perfiles de estudiantes y docentes; solicitud pública de acceso docente; restablecimiento administrativo de contraseña; contraseñas temporales y cambio obligatorio; control de sesiones y notificaciones de cuenta.
- **Administrador inicial:** comando `accounts:bootstrap-admin`, restringido a una base sin usuarios, con contraseña temporal y protección contra altas iniciales concurrentes. Se documentó el alta autorizada y el acceso del primer administrador real a la app local conectada a Turso.
- **Dashboard y reportes:** indicadores por rol y filtros por estado, clasificación, tipo, revisor, programa, medio y fechas; reportes y exportaciones con el alcance autorizado.
- **Búsqueda y ayuda:** búsqueda global con permisos, vistas de documentos y verificación pública, preguntas frecuentes y orientación por rol.
- **Catálogos:** administración de cargos institucionales, clasificaciones, tipos de trámite, formatos de salida y reglas de recepción. Los módulos de plazos, feriados y la pantalla de auditoría que se añadieron entonces se retiraron después por solicitud del usuario.
- **Plantillas y borradores:** versiones de plantillas, alta y clonación, campos configurables, captura histórica de datos académicos y cargos, texto renderizado guardado, huella del borrador y consulta de versiones anteriores sin recalcularlas con el perfil actual.
- **Revisión:** asignación, observaciones visibles o internas, correcciones mediante nuevas versiones y decisiones vinculadas a la versión revisada.
- **Emisión y cierre:** numeración y reserva del documento oficial, integridad de archivos, verificación pública, anulación/sustitución documentada, gestión de entregas, confirmación, anulación de entregas sin confirmar y reapertura de expedientes con conservación del historial.
- **Notificaciones:** avisos internos de los eventos del flujo. La entrega externa por correo o WhatsApp no queda demostrada por estas notificaciones.
- **Turso:** adaptador HTTP, inspección de esquema y gramática compatible; migraciones incrementales documentadas. Se conservan brechas de validación de operaciones de negocio remotas.
- **Railway y build:** configuración de despliegue con PHP 8.5, extensión GD para procesar firmas, proxy HTTPS y generación Wayfinder durante la compilación con valores de base destinados exclusivamente al build.
- **Interfaz:** ajustes de componentes Base UI/shadcn, menús, páginas de autenticación y organización de los paneles.
- El detalle de alcance, evidencias y limitaciones de estas etapas está en [docs/MIGRACION_PARIDAD.md](docs/MIGRACION_PARIDAD.md).

## Pendientes actualizados al 2026-10-06

### Funcionamiento publicado y datos reales

- [ ] Integrar y desplegar los ajustes locales auditados después de preparar la persistencia privada; verificar el nuevo commit y el acceso al sistema publicado.
- [x] Comprobar las migraciones actuales en producción: `php artisan migrate:status --no-ansi` mostró aplicadas las migraciones hasta el 2 de octubre, incluida la de documentos internos del 29 de septiembre.
- [ ] Verificar que firmas, adjuntos y PDF privados persistan después de reinicios y despliegues del servicio.
- [ ] Recorrer en producción, con cuentas autorizadas y datos institucionales controlados, el flujo de cada rol: alta/edición, búsqueda por DNI, solicitud, Informe y Memorandos, revisión, firma, emisión, descarga, entrega y cierre.
- [ ] Probar el QR con un lector independiente y la URL pública definitiva; comprobar los permisos de descarga y los efectos de anulación o sustitución en el entorno publicado.
- [x] Recorrer localmente Memorando simple y Constancia de titulación hasta el cierre en `http://127.0.0.1:18081`, con tres cuentas ficticias, base desechable y almacenamiento privado separado. Rechazo y Memorando múltiple tienen pruebas automatizadas; sus recorridos completos no se certificaron en navegador.
- [ ] Completar la integración remota de Turso: transacciones de negocio, rollback, numeración concurrente, timeouts y respuestas de escritura de resultado ambiguo. Las seis pruebas remotas omitidas siguen sin aportar evidencia nueva.

### Correo: reservado para una sesión futura por solicitud del usuario

- [ ] Elegir el remitente institucional y el proveedor SMTP autorizado; configurar sus secretos en el entorno correspondiente.
- [ ] Verificar entrega real de confirmación de correo, reenvío y recuperación de contraseña, incluidos enlaces vencidos, usados y reemplazados.
- [ ] Repetir esas pruebas en Railway con su dominio y configuración de correo propios.
- La verificación y recuperación tienen rutas y lógica implementadas. La última configuración local revisada utiliza `MAIL_MAILER=log`, por lo que los mensajes quedan en los logs y no llegan a una bandeja de correo.
- El plan y las condiciones están en [docs/pendiente-correo-verificacion-y-recuperacion.md](docs/pendiente-correo-verificacion-y-recuperacion.md).

### Solicitudes y limitaciones aún abiertas

- [x] Unificar el identificador docente con el DNI en altas, ediciones y perfiles existentes mediante la migración local del 2 de octubre. Falta aplicar esa migración en el entorno publicado.
- [ ] Completar la maquetación comparativa de los PDF frente a los Word institucionales. Los encabezados exactos ya están identificados por hash y la estructura de Memorando está cubierta por pruebas locales, pero la equivalencia visual completa y la identidad tipográfica no están certificadas.
- [x] Hacer que la emisión de la constancia de titulación consuma el cuerpo completo de `contenido_renderizado` y conservarlo en la instantánea. El PDF del egresado ficticio se comprobó localmente; la fidelidad visual con la fotografía y el recorrido publicado siguen pendientes.
- [ ] Revisar las diferencias de presentación de plantillas respecto del sistema de origen: el renderizado conserva texto escapado, pero no toda su presentación HTML limitada. No reconstruir versiones antiguas sin instantánea a partir de datos actuales.
- [ ] Implementar y validar la lógica institucional que todavía no existe para destinatario de primera hora de tardanzas, inasistencias a todos los docentes correspondientes y revisión de viabilidad, aforo, supervisor, aprobación y constancia de prácticas.
- [ ] Clasificar los avisos de pruebas y los diagnósticos históricos de PHPStan/React Doctor que permanecen en páginas y servicios grandes. La última verificación de TypeScript pasó.
- [ ] Revisar los imports Radix/Base UI antes de retirar dependencias; evaluar la dependencia `turso/libsql` y su requisito de FFI, aunque el adaptador HTTP propio no lo use directamente.

## Historial de las primeras fases

Las notas siguientes se conservan como evidencia de cada etapa. Sus cifras de pruebas y pendientes describen el estado de esas fechas; cuando difieren del código actual, prevalecen las secciones actualizadas anteriores.

## Fase 7 — Firma, entrega y cierre (2026-09-28)

- Se corrigió el menú de perfil Base UI para que las páginas autenticadas rendericen sin error de contexto.
- Se añadió el registro de firma física o de que no aplica, preparación del documento emitido, catálogo de medios de entrega y evidencia privada con SHA-256. El número del receptor se guarda enmascarado.
- La entrega permanece pendiente hasta que la confirma la cuenta propietaria; solo entonces el personal puede cerrar el expediente y generar un informe PDF privado con resumen, cronología, evidencias y código de verificación.
- Se bloquearon las transiciones repetidas y el cierre anticipado; los accesos y descargas autorizadas quedan en la auditoría. No se implementaron reapertura, anulación ni notificaciones de entrega.
- `php -d ffi.enable=true artisan test --compact tests/Feature/TramiteWorkflowTest.php` pasó en Turso: 4 pruebas y 503 aserciones. `pnpm run build`, Pint y React Doctor (100/100) pasaron. `pnpm run types:check` mantiene errores de tipos del starter kit Base UI en props `asChild` y `delayDuration`.

## Fase 6 — Numeración y emisión del documento oficial (2026-09-28)

- Se reservó el correlativo documental en Turso con actualización atómica; una falla posterior de generación consume el número y evita reutilizarlo.
- Se emite el PDF privado desde la versión exacta del borrador aprobado y se registra número, emisor, contenido de snapshot, páginas y SHA-256. Las descargas comprueban autorización, ruta e integridad.
- La prueba de emisión en Turso verificó fallos, reserva no reutilizada, acceso e integridad. El generador PDF es propio y de texto; no reproduce el diseño gráfico de la plantilla del sistema de referencia.

## Fase 5 — Observaciones, correcciones y decisiones (2026-09-28)

- Se vinculó cada expediente estudiantil con su propietario y se añadió la consulta de "Mis trámites"; estudiantes ajenos reciben 403 y solo ven estados, comentarios y observaciones marcadas como visibles.
- Los revisores registran observaciones públicas o internas, obligatorias u opcionales; el asistente responde las obligatorias en una versión nueva del borrador y el mismo revisor puede aprobar o rechazar con conclusión y fundamento público.
- La ronda conserva el borrador que revisó, sus respuestas y su historial. Se bloquearon decisiones repetidas, correcciones sin respuesta obligatoria y accesos internos desde la vista del estudiante.
- La migración aditiva de propiedad y revisión se aplicó en Turso. `php -d ffi.enable=true artisan test tests/Feature/TramiteWorkflowTest.php --compact` pasó con 346 aserciones; `pnpm run build` y Pint pasaron. `pnpm run types:check` sigue señalando errores existentes del starter kit Base UI, sin errores en los archivos nuevos de esta fase.

## Fase 4 — Asignación de revisores (2026-09-28)

- Se implementó la bandeja de asignación para asistentes, con selección de revisores activos por rol y destino, carga de trabajo y fecha esperada.
- Se añadieron asignación, reasignación y cancelación con historial inmutable, una sola asignación activa y bloqueos al comenzar la revisión.
- Docentes y administradores asignados cuentan con bandejas y detalles propios; los expedientes ajenos responden 403 y la apertura y los accesos no autorizados quedan auditados.
- La migración aditiva de asignaciones se aplicó en Turso. `php -d ffi.enable=true artisan test tests/Feature/TramiteWorkflowTest.php --compact` pasó con 210 aserciones; `pnpm run build`, Pint y `git diff --check` pasaron. `pnpm run types:check` mantiene errores existentes del starter kit Base UI, sin errores en los archivos nuevos de esta fase.
- La prueba elimina expediente, asignaciones, borradores, plantilla y usuarios ficticios en `finally`; la numeración de recepción solo se revierte cuando el valor reservado sigue siendo el último.

## Fase 3 — Borradores y preparación para revisión (2026-09-28)

- Se añadieron plantillas institucionales iniciales y borradores con versiones guardadas en Turso, destinatarios, personas mencionadas y adjuntos vinculados al expediente.
- Se implementó la vista previa provisional, la preparación con validaciones de remitente, firmante, destinatarios y contenido, y el traspaso idempotente a la bandeja pendiente de asignación.
- Las versiones anteriores se conservan como obsoletas y el borrador actual se muestra en el detalle y su historial.
- `php artisan test tests/Feature/TramiteWorkflowTest.php --compact` pasó con 102 aserciones; `pnpm run build` y Pint pasaron. `pnpm run types:check` sigue fallando en componentes Base UI preexistentes, sin errores en los archivos de esta fase.
- La migración aditiva y las tres plantillas iniciales se aplicaron en Turso. La prueba eliminó los registros ficticios que creó.

## Fase 2 — Recepción y digitalización (2026-09-28)

- Se añadieron roles de usuario y control de cuentas activas para proteger la bandeja, el registro, el historial y las descargas.
- Se implementó la recepción de trámites con clasificación, catálogo inicial, persona solicitante, destino, prioridad, fecha y folios; el correlativo anual se reserva atómicamente.
- Se habilitó la carga opcional al recibir y la digitalización posterior, con validación de PDF/JPG/PNG hasta 10 MB, almacenamiento privado, nombre no predecible y verificación SHA-256 en cada descarga.
- Se añadieron consulta paginada, búsqueda, filtro por estado, detalle con línea de tiempo y auditoría de recepción, digitalización y descarga.
- Las migraciones de recepción se aplicaron incrementalmente en Turso, sin reiniciar la base. El formulario usa la fecha local de Lima para registros y correlativos.
- `php artisan test tests/Feature/TramiteWorkflowTest.php --compact` pasó con 66 aserciones y Turso; `pnpm run build` y Pint pasaron. Chrome verificó el formulario y la bandeja filtrada con estado y tipo traducidos.
- Chrome bloqueó la selección del archivo de prueba por el permiso de acceso a archivos locales de la extensión. La carga posterior y descarga se verificaron en la prueba HTTP con Turso y almacenamiento privado simulado. Los registros, usuario y sesión E2E se eliminaron al terminar; se conservó el correlativo reservado para no reutilizarlo.

## Fase 1 — Persistencia Laravel con Turso (2026-09-27)

- Se implementó una conexión Laravel sobre la API HTTP v3 de Turso con `TURSO_DATABASE_URL` / `TURSO_AUTH_TOKEN` y el cliente HTTP incluido en Laravel; el adaptador no usa FFI, aunque el autoload de la dependencia anterior todavía lo activa al iniciar PHP.
- Se añadió una conexión libSQL compatible con consultas preparadas, valores nulos y binarios, claves foráneas por stream, transacciones con savepoints e IDs autogenerados leídos en el mismo stream que el `INSERT`.
- Se aplicaron las migraciones existentes del starter kit a Turso. Las operaciones Artisan destructivas quedan bloqueadas mientras libSQL es la conexión predeterminada.
- Se verificaron en Turso rollback, claves foráneas, valores preparados, nulos, IDs autogenerados y 40 incrementos atómicos concurrentes desde cuatro procesos Laravel separados.
- La suite completa pasó con SQLite de prueba local y las pruebas de integración ejercitaron la conexión real de Turso. `migrate:status` confirmó las cinco migraciones existentes.
- La inspección visual confirmó que inicio, login y registro renderizan cuando el servidor PHP permite FFI; con la configuración normal, el arranque sigue bloqueado por el autoload del SDK antiguo. No se envió ningún formulario ni se creó una cuenta.

### Compatibilidad pendiente

- `turso/libsql` 0.2.5 continúa declarado en Composer. Aunque la conexión HTTP no lo usa, Composer lo carga automáticamente al arrancar PHP y requiere FFI. Retirarlo de `composer.json` y `composer.lock` requiere autorización para cambiar dependencias; está pendiente.
- Turso no acepta `SELECT ... FOR UPDATE`; los flujos deberán usar transacciones o actualizaciones atómicas compatibles con SQLite/libSQL. La conexión no reintenta escrituras HTTP ante fallos de red cuyo resultado pueda ser ambiguo.
- Los siete pasos principales del expediente están implementados. El PDF oficial y el informe de cierre conservan contenido e integridad, pero su maquetación de texto no reproduce el diseño de las plantillas PDF del sistema de referencia.

## Fase 0 — Evaluación de viabilidad Turso/libSQL (2026-09-27)

- Se comprobó conexión remota, lectura, escritura preparada, claves foráneas, IDs autogenerados, rollback transaccional y escrituras concurrentes con el SDK PHP instalado.
- Se confirmó que el esquema remoto está basado en SQLite/libSQL y que la conexión probada no usa una réplica local.
- Esta fase no implementa los flujos de gestión documentaria. La réplica funcional y sus procesos por rol siguen pendientes.
