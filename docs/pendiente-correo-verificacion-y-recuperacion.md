# Pendiente futuro: correo, verificación y recuperación

Este documento registra el estado revisado el 29 de septiembre de 2026. La configuración y las pruebas con un proveedor de correo quedan para una sesión futura.

## Estado actual del proyecto

- La verificación del correo ya está activada en Fortify. Al registrarse, la cuenta queda pendiente de verificar el correo y de aprobación administrativa. Existe un enlace firmado de verificación con vencimiento de 24 horas y una opción para reenviar el mensaje.
- Las secciones protegidas requieren una sesión iniciada y un correo verificado.
- La recuperación de contraseña ya está implementada. En **Iniciar sesión**, el enlace **¿Olvidaste tu contraseña?** abre `/forgot-password`, una página aparte donde se ingresa el correo. El enlace recibido abre el formulario para establecer una contraseña nueva. El token vence en 30 minutos.
- La recuperación solo envía enlace a cuentas activas que cumplen las condiciones del sistema. La respuesta de la página es neutral para no revelar si un correo está registrado.
- El `.env` local tiene `MAIL_MAILER=log`: Laravel deja los mensajes de verificación y recuperación en los logs de la aplicación; no los entrega a la bandeja de entrada. Por eso, las pantallas y rutas están implementadas, pero la entrega real de correo aún no está configurada.

## Próximos pasos cuando se retome

1. Elegir un remitente institucional y un proveedor SMTP autorizado. Preferir el SMTP institucional si está disponible; si no, evaluar un servicio transaccional como Brevo.
2. Configurar las variables SMTP solo en el `.env` local o en el administrador de secretos del servidor. No guardar contraseñas, claves SMTP ni tokens en este archivo, en Git o en el repositorio.
3. Probar el registro y la verificación del correo, el reenvío del enlace, la recuperación de contraseña y el cambio de contraseña con enlaces válidos, vencidos y ya usados.
4. Repetir las pruebas en el entorno publicado después de configurar allí sus propios secretos y el dominio de envío.

## Sobre la “llave de Google”

Una **passkey** de Google sirve para iniciar sesión en la cuenta de Google; no configura el envío SMTP de la aplicación. Para Gmail SMTP podría requerirse una **contraseña de aplicación**, con verificación en dos pasos y sujeta a las políticas de la cuenta. Google recomienda no usar contraseñas de aplicación salvo que sean necesarias. No crear ni compartir estas credenciales por chat.

Referencias oficiales: [Passkeys de Google](https://support.google.com/accounts/answer/13548313) y [contraseñas de aplicación de Gmail](https://support.google.com/mail/answer/185833).
