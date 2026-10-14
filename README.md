# Sistema de Trámites de DSI

Aplicación de la oficina de Desarrollo de Sistemas de Información del I.E.S.T.P. Manuel Seoane Corrales. Digitaliza solicitudes recibidas, prepara documentos con plantillas, los deriva a revisión docente y registra su emisión, entrega y cierre.

## Abrir esta instalación local

1. Ejecuta **`INICIAR_LOCAL.cmd`** en la carpeta del proyecto.
2. Abre <http://127.0.0.1:8000/login> e ingresa con tu cuenta.
3. El correo de pruebas está en <http://127.0.0.1:8025>. Sus mensajes permanecen en esta PC; no llegan al correo institucional.

Esta copia utiliza **SQLite**, PHP 8.3 o superior, Laravel, React y TypeScript. No requiere iniciar MySQL ni Apache de XAMPP. La base local habitual es `database/database.sqlite`; firmas, adjuntos y PDF se conservan en `storage/app/private`. No se incluyen datos privados en GitHub.

Para instalar desde cero en otra PC, sigue [la guía de instalación](INSTALAR_EN_OTRA_PC.md). Una copia del código no incluye automáticamente los usuarios y expedientes de esta PC.

## Recorrido de una solicitud

1. **Administrador → Registrar trámite:** elige Solicitud, ingresa el DNI y selecciona su tipo. Si existe una cuenta, se recuperan sus datos; también puede recibirse un FUT de una persona sin cuenta. Transcribe la sumilla, la fundamentación y la fecha, y adjunta el FUT.
2. **Preparar borrador:** selecciona la plantilla del tipo correcto, completa los campos, destinatarios y responsables, y comprueba el PDF provisional. El firmante debe tener cargo activo y su firma registrada.
3. **Marcar preparado y asignar:** deriva el expediente a un docente o a revisión de oficina.
4. **Revisor → Asignaciones:** inicia la revisión y aprueba, observa o rechaza. Una observación vuelve a corrección y conserva las versiones anteriores.
5. **Administrador → Generar documento:** comprueba la decisión y emite el PDF. Se conserva el texto resuelto de la versión revisada, su numeración, firma y código de verificación.
6. **Entrega y cierre:** prepara la entrega, registra el medio, confirma la recepción y genera el informe de cierre. La descarga del estudiante se habilita en el paso correspondiente; no permite ver expedientes ajenos.

Informe, Memorando simple y Memorando múltiple se originan directamente en la oficina: no exigen un solicitante, DNI ni archivo de solicitud para comenzar.

El expediente antiguo incluido en esta copia contiene datos de recepción históricos. Revísalos con **Editar recepción** antes de utilizarlo como caso real. Las pruebas automatizadas usan datos ficticios separados y no modifican ese expediente.

## Modelos propuestos y firmas

- Prácticas y tardanza disponen de modelos propios para recorrer el flujo local con `TRAMITES_MODELOS_PRUEBA=true`. El PDF y su verificación dicen **documento de prueba, sin validación institucional**.
- En producción esa opción no habilita modelos sin validar. El administrador registra la autorización y el origen de la versión en **Plantillas documentales**, después de la revisión del responsable. Una nueva versión o un cambio de campos requiere nueva validación.
- Los modelos referenciales anteriores continúan disponibles solo para borradores. El detalle de lo pendiente está en [Modelos institucionales](docs/MODELOS_OFICIALES_PENDIENTES.md).
- La firma escaneada se carga en **Mi perfil**. Sus versiones se conservan para no alterar PDF históricos. La imagen de Docente Demo sirve únicamente para pruebas y no corresponde a un profesor real.

## Copias de seguridad y arranque en Windows

La instalación actual tiene tareas para iniciar el servidor y Mailpit al iniciar sesión en Windows, y un respaldo diario a las **03:00**. Las tareas permiten funcionar con batería. Requieren la sesión de Windows del propietario; esta PC no equivale a un servidor disponible las 24 horas.

Para configurar estas tareas en otra PC, con la aplicación y Mailpit ya preparados:

```powershell
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File .\scripts\Start-MailboxLocal.ps1 -Install
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File .\scripts\Install-LocalOperations.ps1
```

Configura `BACKUP_DIRECTORY` fuera de la carpeta pública y `BACKUP_PASSWORD` con una clave privada de al menos 20 caracteres. Conserva la clave fuera del repositorio y una copia del respaldo fuera de esta PC.

```powershell
php artisan system:backup
php artisan system:verify-backup "RUTA_DEL_RESPALDO.zip" --restore-to="CARPETA_VACIA_FUERA_DEL_PROYECTO"
```

El respaldo utiliza ZIP con AES-256, incluye una instantánea consistente de SQLite, archivos privados y la clave de aplicación, y verifica las huellas. La restauración de comprobación nunca reemplaza la base activa. No ejecutes `key:generate`, `migrate:fresh` ni reemplaces la base sobre datos existentes.

## Verificación técnica

Con las dependencias instaladas, ejecuta la compilación antes de las pruebas; no reconstruyas `public/build` mientras la suite lo está utilizando:

```powershell
pnpm run build
composer ci:check
```

`ci:check` comprueba Pint, PHPStan, formato/lint, TypeScript y las pruebas. El arranque del contenedor también tiene una prueba específica en `tests/Deployment/entrypoint-failure.sh`. El endpoint `/ready` comprueba la base y el almacenamiento sin publicar datos internos.

Consulta [Estado de hallazgos](docs/ESTADO_HALLAZGOS.md), [Changelog](CHANGELOG.md), [Correo](docs/CORREO_INSTITUCIONAL.md) y [Despliegue](docs/DESPLIEGUE_SEGURO.md). Los cambios se realizan exclusivamente en `main`, según `AGENTS.md`.

## Pendiente para uso institucional

- Validación de los textos, presentación, destinatarios y criterios de prácticas y tardanza por la oficina responsable.
- Firmas y cargos reales de los responsables; los datos de demostración no los sustituyen.
- SMTP institucional autorizado y prueba de recepción externa de verificación, recuperación y PDF.
- Copia periódica fuera de esta PC y comprobación de la versión publicada, sus archivos persistentes y su recuperación después de desplegar.

La entrega técnica local permite pruebas del proceso completo. Esas comprobaciones institucionales y de producción deben completarse antes de utilizarlo como servicio oficial.
