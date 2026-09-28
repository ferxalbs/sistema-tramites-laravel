# Changelog

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
- Aún no se migraron procesos documentales ni se implementó la numeración de expedientes; esos flujos siguen pendientes.

## Fase 0 — Evaluación de viabilidad Turso/libSQL (2026-09-27)

- Se comprobó conexión remota, lectura, escritura preparada, claves foráneas, IDs autogenerados, rollback transaccional y escrituras concurrentes con el SDK PHP instalado.
- Se confirmó que el esquema remoto está basado en SQLite/libSQL y que la conexión probada no usa una réplica local.
- Esta fase no implementa los flujos de gestión documentaria. La réplica funcional y sus procesos por rol siguen pendientes.
