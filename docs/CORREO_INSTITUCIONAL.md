# Correo institucional pendiente de activación

## Pruebas locales disponibles — 14 de octubre de 2026

Mailpit recibe los mensajes de la instalación local en <http://127.0.0.1:8025>, mediante SMTP en `127.0.0.1:1025`. Se comprobó la recepción de un enlace de recuperación. La verificación, el reenvío y los fallos de transporte tienen pruebas automatizadas. Ninguno de estos mensajes se envía a Internet.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_SCHEME=null
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_TEST_INBOX=true
```

`MAIL_TEST_INBOX` solo es válido en local o pruebas. En el servidor debe ser `false`. Las pantallas distinguen entre un mensaje capturado localmente, aceptado por SMTP y no enviado; no declaran éxito cuando el transporte falla. Registro y solicitud docente conservan su cuenta pendiente si no se pudo enviar el enlace.

## Configuración externa pendiente

La verificación de cuenta y la recuperación de contraseña usan el correo configurado en Laravel. La entrega de un PDF por el medio «Correo electrónico» también envía el archivo adjunto y registra el envío solo cuando el transporte acepta el mensaje. En modo `log` o `array`, el sistema bloquea esa entrega para no afirmar que envió un correo real.

Para activar el correo institucional, la institución debe proporcionar el servidor SMTP, el puerto, el esquema de seguridad y una cuenta remitente autorizada. Configure estos valores **solo en el entorno del servidor y en el `.env` local**, nunca en Git:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=servidor.smtp.institucional
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=cuenta-autorizada@seoane.edu.pe
MAIL_PASSWORD=clave-configurada-en-el-entorno
MAIL_FROM_ADDRESS=cuenta-autorizada@seoane.edu.pe
MAIL_FROM_NAME="Sistema de Trámites"
```

Los valores de host, puerto y esquema son ejemplos hasta que los confirme el responsable del correo. Este transporte acepta esquemas `smtp` y `smtps`, no `tls`: para STARTTLS suele utilizarse `smtp` con el puerto indicado por el proveedor; para TLS implícito, `smtps`. No desactivar la verificación del certificado. Tras configurar los valores autorizados, limpiar la caché de configuración y probar registro/verificación, reenvío, recuperación y entrega del PDF con una cuenta de prueba. Verificar también el archivo recibido, el remitente y la fecha registrada. Repetir la prueba en Railway con sus propios secretos.

Si falla o queda indeterminado un envío de PDF, el registro permanece pendiente de revisión y no se puede confirmar la recepción. El administrador debe revisar el proveedor antes de anularlo y repetirlo, pues un error de red puede producir una respuesta ambigua.
