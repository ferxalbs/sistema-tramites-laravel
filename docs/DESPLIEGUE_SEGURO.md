# Despliegue y conservación de documentos

## Lo que ya verifica el proyecto

- El arranque ejecuta `php artisan migrate --force --no-interaction` y termina con error si la migración falla, si falta `DB_CONNECTION` o si la base SQLite indicada no existe. La prueba `tests/Deployment/entrypoint-failure.sh` simula esos fallos y comprueba que el servidor no arranque.
- Firmas de perfil, solicitudes adjuntas, evidencias y PDF finales se guardan en el disco privado de Laravel, en `/app/storage/app/private` dentro del contenedor. Una instalación local conserva esos archivos en su disco; un contenedor de Railway necesita un volumen en **esa ruta exacta**.
- Si Railway declara `RAILWAY_VOLUME_MOUNT_PATH`, el arranque comprueba que el volumen está realmente montado en `/app/storage/app/private` y se detiene si no lo está. Si Railway no declara ningún volumen, el arranque advierte del riesgo en los registros. El código no puede crear un volumen ni activar sus copias de seguridad desde GitHub.

## Antes de publicar con documentos reales

1. Acceder al servicio de Railway y comprobar si existen firmas, adjuntos o PDF privados en el contenedor actual. Conservar una copia de esos archivos antes de montar un volumen: un montaje nuevo puede ocultar el contenido anterior del contenedor.
2. Crear y adjuntar un volumen persistente al servicio en `/app/storage/app/private`. Revisar que el servicio tenga permisos de lectura y escritura. Railway permite hacerlo desde el servicio o con `railway volume add --mount-path /app/storage/app/private` en un proyecto ya vinculado. [Volúmenes de Railway](https://docs.railway.com/volumes/reference).
3. Copiar al volumen los archivos privados existentes y cotejarlos con los expedientes y firmas almacenados en la base. No dar por migrados los documentos solo porque el servicio responde HTTP 200.
4. Activar copias de seguridad periódicas del volumen en la pestaña **Backups** y crear una copia manual antes de cada cambio de almacenamiento. [Copias de seguridad de Railway](https://docs.railway.com/volumes/backups).
5. Obtener un respaldo recuperable de la base Turso con las herramientas y permisos del proveedor. Ensayar la migración en una base de prueba independiente, nunca en la base real usada por los estudiantes. Confirmar el plan de restauración antes de aplicar migraciones de producción.
6. En el servicio actual, ejecutar `php artisan storage:verify-persistence --write` y guardar el marcador devuelto. Tras un nuevo despliegue, ejecutar `php artisan storage:verify-persistence --check=MARCADOR`. Un fallo prueba que el almacenamiento privado no sobrevivió. El marcador no contiene datos personales.
7. Confirmar `php artisan migrate:status` y descargar, con una cuenta autorizada, una firma, un adjunto y un PDF creados antes del despliegue. Registrar el resultado. La prueba del marcador sola no verifica que todos los archivos anteriores estén intactos.

## Estado al 2 de octubre de 2026

La configuración del volumen, sus respaldos, el respaldo de Turso y la prueba de conservación entre dos despliegues **siguen pendientes** porque esta sesión solo tiene acceso al repositorio GitHub. El código y la prueba local de fallo de migración están listos, pero subir el commit no constituye una verificación de persistencia en Railway.
