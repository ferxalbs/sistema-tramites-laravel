# Despliegue y conservación de documentos

## Estado de esta entrega — 14 de octubre de 2026

Las correcciones de esta entrega se verifican localmente. Las comprobaciones publicadas del 6 de octubre, conservadas abajo, no acreditan el despliegue del código actual. No se ha verificado esta versión de la aplicación en Railway.

El arranque actual exige un volumen real en `/app/storage/app/private` cuando detecta `RAILWAY_ENVIRONMENT_ID`, `RAILWAY_PROJECT_ID`, la variable histórica `RAILWAY_ENVIRONMENT` o `REQUIRE_PERSISTENT_STORAGE=1`. Una variable de ruta sin montaje real no basta. Un error de migración detiene el servidor.

La copia actual local utiliza SQLite. Si el servicio publicado también usa SQLite, `DB_DATABASE` debe apuntar a `/app/storage/app/private/database.sqlite` para que base y archivos sobrevivan al despliegue. No cambies el motor de un servicio existente sin migrar y verificar sus datos.

La importación de una copia local autorizada es únicamente para el primer arranque: verifica sus huellas y nunca reemplaza una base existente. `.deployment-seed`, bases, archivos privados, `.env` y respaldos se excluyen de nuevas subidas e imágenes. Para un nuevo traslado, transfiere esos datos por un canal privado autorizado; no los añadas al repositorio.

Ya existe respaldo integrado SQLite cifrado y una tarea diaria local. Falta configurar su equivalente periódico en el servidor, conservar una copia fuera del volumen y comprobar la restauración. El volumen por sí solo no constituye un respaldo.

## Lo que ya verifica el proyecto

- El arranque ejecuta `php artisan migrate --force --no-interaction` y termina con error si la migración falla, si falta `DB_CONNECTION` o si la base SQLite indicada no existe. La prueba `tests/Deployment/entrypoint-failure.sh` simula esos fallos y comprueba que el servidor no arranque.
- Firmas de perfil, solicitudes adjuntas, evidencias y PDF finales se guardan en el disco privado de Laravel, en `/app/storage/app/private` dentro del contenedor. Una instalación local conserva esos archivos en su disco; un contenedor de Railway necesita un volumen en **esa ruta exacta**.
- Si Railway declara `RAILWAY_VOLUME_MOUNT_PATH`, el arranque comprueba que el volumen está realmente montado en `/app/storage/app/private` y se detiene si no lo está. En Railway también se detiene si no se declaró ningún volumen. El código no puede crear un volumen ni activar sus copias de seguridad desde GitHub.

## Antes de publicar con documentos reales

1. Acceder al servicio de Railway y comprobar si existen firmas, adjuntos o PDF privados en el contenedor actual. Conservar una copia de esos archivos antes de montar un volumen: un montaje nuevo puede ocultar el contenido anterior del contenedor.
2. Crear y adjuntar un volumen persistente al servicio en `/app/storage/app/private`. Revisar que el servicio tenga permisos de lectura y escritura. Railway permite hacerlo desde el servicio o con `railway volume add --mount-path /app/storage/app/private` en un proyecto ya vinculado. [Volúmenes de Railway](https://docs.railway.com/volumes/reference).
3. Copiar al volumen los archivos privados existentes y cotejarlos con los expedientes y firmas almacenados en la base. No dar por migrados los documentos solo porque el servicio responde HTTP 200.
4. Activar copias de seguridad periódicas del volumen en la pestaña **Backups** y crear una copia manual antes de cada cambio de almacenamiento. [Copias de seguridad de Railway](https://docs.railway.com/volumes/backups).
5. Obtener un respaldo recuperable de la base Turso con las herramientas y permisos del proveedor. Ensayar la migración en una base de prueba independiente, nunca en la base real usada por los estudiantes. Confirmar el plan de restauración antes de aplicar migraciones de producción.
6. En el servicio actual, ejecutar `php artisan storage:verify-persistence --write` y guardar el marcador devuelto. Tras un nuevo despliegue, ejecutar `php artisan storage:verify-persistence --check=MARCADOR`. Un fallo prueba que el almacenamiento privado no sobrevivió. El marcador no contiene datos personales.
7. Confirmar `php artisan migrate:status` y descargar, con una cuenta autorizada, una firma, un adjunto y un PDF creados antes del despliegue. Registrar el resultado. La prueba del marcador sola no verifica que todos los archivos anteriores estén intactos.

## Estado histórico al 2 de octubre de 2026

La configuración del volumen, sus respaldos, el respaldo de Turso y la prueba de conservación entre dos despliegues **siguen pendientes** porque esta sesión solo tiene acceso al repositorio GitHub. El código y la prueba local de fallo de migración están listos, pero subir el commit no constituye una verificación de persistencia en Railway.

## Verificación del 6 de octubre de 2026

- Se obtuvo un respaldo privado de Turso antes del montaje y se restauró en una base SQLite desechable: `integrity_check` correcto, cero infracciones en `foreign_key_check`, 102 tablas y 49 migraciones. La copia queda fuera del repositorio, con permisos locales 0600.
- La inspección anterior al montaje encontró cero expedientes y cero archivos privados documentales; no se ocultaron PDF, firmas ni evidencias históricas existentes.
- Se adjuntó `sistema-tramites-laravel-volume` a `/app/storage/app/private`. `findmnt` confirmó el montaje ext4 en esa ruta. `storage:verify-persistence --write` escribió y leyó el marcador; el mismo marcador se comprobó correctamente tras los despliegues de `5abfa32` y `d7b7ebf`, en contenedores distintos.
- `migrate:status` mostró ejecutadas las migraciones actuales. El dominio publicado permitió acceder, con la sesión autorizada, a Notificaciones y Bandeja de trámites.
- Se descargó y restauró en almacenamiento local desechable un respaldo manual del volumen: el marcador restaurado coincide. La copia queda fuera de Git con permisos 0600.
- Railway mostró que crear respaldos automáticos y PITR requiere el plan Pro. No se modificó el plan. El respaldo manual de la base no reemplaza una política de copias de los archivos privados del volumen.
- Se registró un Memorando ficticio marcado como prueba técnica y se descargó su PDF provisional privado sin numeración oficial. La vista previa se genera al solicitarla; no demuestra persistencia de un PDF almacenado.
- Permanecen pendientes la carga y descarga de archivos documentales antes y después de desplegar y el recorrido completo publicado con QR. La extensión de Chrome requiere permiso de archivos locales para la carga; hacen falta cuentas autorizadas de los otros roles y responsables con cargo activo. No se inventaron cargos ni se emitieron documentos oficiales de prueba.
