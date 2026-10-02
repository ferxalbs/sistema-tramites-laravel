# Correo institucional pendiente de activación

La verificación de cuenta y la recuperación de contraseña usan el correo configurado en Laravel. La entrega de un PDF por el medio «Correo electrónico» también envía el archivo adjunto y registra el envío solo cuando el transporte acepta el mensaje. En modo `log` o `array`, el sistema bloquea esa entrega para no afirmar que envió un correo real.

Para activar el correo institucional, la institución debe proporcionar el servidor SMTP, el puerto, el esquema de seguridad y una cuenta remitente autorizada. Configure estos valores **solo en el entorno del servidor y en el `.env` local**, nunca en Git:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=servidor.smtp.institucional
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=cuenta-autorizada@seoane.edu.pe
MAIL_PASSWORD=clave-configurada-en-el-entorno
MAIL_FROM_ADDRESS=cuenta-autorizada@seoane.edu.pe
MAIL_FROM_NAME="Sistema de Trámites"
```

Los valores de host, puerto y esquema son ejemplos hasta que los confirme el responsable del correo. Tras configurarlos, limpiar la caché de configuración y probar con una cuenta institucional de prueba: registro/verificación, reenvío, recuperación y entrega del PDF. Verificar también el archivo recibido, el remitente y la fecha registrada. Repetir la prueba en Railway con sus propios secretos.

Si falla o queda indeterminado un envío de PDF, el registro permanece pendiente de revisión y no se puede confirmar la recepción. El administrador debe revisar el proveedor antes de anularlo y repetirlo, pues un error de red puede producir una respuesta ambigua.
