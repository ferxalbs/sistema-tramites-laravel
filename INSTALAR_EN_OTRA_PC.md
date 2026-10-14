# Instalar el Sistema de Trámites en otra PC

Este paquete contiene el código, las plantillas, las migraciones, las pruebas, el logo y los recursos web compilados tal como están en esta copia local. La instalación descrita aquí crea una **base SQLite nueva** para probar el sistema en otra computadora.

No se incluyen el archivo `.env`, contraseñas, tokens, la base institucional ni firmas, adjuntos o PDF privados. Para conservar los usuarios y expedientes existentes hace falta una transferencia autorizada y separada de la base y del almacenamiento privado.

## Requisitos en Windows

- PHP **8.3 o superior**; se recomienda PHP 8.5. Habilitar al menos `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `curl`, `gd`, `intl` y `zip` en `php.ini`.
- Composer 2.
- Para volver a compilar la interfaz: Node.js 22 y pnpm 12.6.0. El ZIP ya incluye `public/build`, por lo que este paso puede posponerse si solo se desea abrir la aplicación.
- No hace falta iniciar Apache ni MySQL de XAMPP para la instalación SQLite descrita aquí.

Comprueba que el terminal encuentra las versiones correctas:

```powershell
php --version
composer --version
php -m
```

Si aparece una versión antigua de PHP, ajusta `PATH` para que el ejecutable de PHP 8.3 o superior sea el primero. Todas las órdenes siguientes deben usar esa misma versión.

## Instalación local

1. Extrae el ZIP, abre PowerShell y entra en la carpeta `sistema-tramites-laravel` que contiene `artisan`.
2. Crea el archivo de configuración:

    ```powershell
    Copy-Item .env.example .env
    notepad .env
    ```

3. En `.env`, conserva `DB_CONNECTION=sqlite`. Deja sin definir `DB_DATABASE`: la aplicación usará `database/database.sqlite`. Usa `APP_URL=http://127.0.0.1:8000`. Para una prueba sin servidor de correo, conserva `MAIL_MAILER=array`; los mensajes no llegarán a una bandeja real. La captura de correo con Mailpit se explica en `README.md`.
4. Instala las dependencias, crea la base local y prepara el sistema:

    ```powershell
    composer install --no-interaction
    php artisan key:generate
    if (-not (Test-Path database/database.sqlite)) { New-Item -ItemType File -Path database/database.sqlite }
    php artisan migrate --no-interaction
    php artisan db:seed --no-interaction
    ```

    Estos pasos son para una instalación nueva: no regeneres `APP_KEY` ni reemplaces `.env` sobre una instalación con datos existentes.

5. Crea el primer administrador **solo en esta base nueva**. Sustituye los datos del ejemplo por los autorizados; el correo debe terminar en `@seoane.edu.pe`:

    ```powershell
    php artisan accounts:bootstrap-admin --nombres="Nombre" --apellidos="Apellido" --email="nombre@seoane.edu.pe"
    ```

    Guarda la contraseña temporal que aparece una sola vez y cámbiala al iniciar sesión. El comando se bloquea si la base ya contiene usuarios.

6. Ejecuta **`INICIAR_LOCAL.cmd`**. Configura el servidor en el Programador de tareas de Windows y abre la página. Puedes cerrar la ventana del lanzador y la aplicación de escritorio; Windows mantiene el servidor mientras la PC esté encendida y tu sesión permanezca abierta.

    Para iniciarlo desde PowerShell:

    ```powershell
    .\scripts\Start-SistemaTramitesLocal.ps1 -Launch
    ```

    Abre <http://127.0.0.1:8000/login>. Si el supervisor se detiene, se recupera en el siguiente intervalo de un minuto, más el tiempo de arranque de Laravel. El comando manual `php artisan serve` depende de mantener abierta su ventana; utiliza el lanzador para el uso cotidiano.

## Si vas a modificar la interfaz

Instala Node.js 22 y activa pnpm 12.6.0. Con PHP 8.3 o superior disponible en el mismo terminal, ejecuta:

```powershell
corepack enable
corepack prepare pnpm@12.6.0 --activate
pnpm install --frozen-lockfile
pnpm run build
```

El proyecto ya trae `composer.lock` y `pnpm-lock.yaml`; utiliza esos archivos para instalar versiones reproducibles.

## Comprobación y límites actuales

- `php artisan migrate:status` muestra las migraciones aplicadas.
- `php artisan test --compact` ejecuta las pruebas locales con una base de pruebas separada. Las pruebas remotas de Turso requieren un entorno autorizado y desechable; no uses la base institucional para ellas.
- La verificación por correo, la recuperación de contraseña y la entrega por correo pueden probarse localmente con Mailpit. La recepción externa requiere SMTP autorizado. Sus valores se configuran en `.env`, nunca en el ZIP o en Git.
- Justificación de tardanza y constancia de prácticas tienen modelos propuestos para pruebas locales; la emisión oficial necesita validación institucional de su versión y reglas. Consulta `docs/MODELOS_OFICIALES_PENDIENTES.md`.
- Para trasladar expedientes existentes hace falta, además del código, la base de datos correspondiente y los archivos de `storage/app/private` con un respaldo verificado. No copies solo el ZIP y supongas que esos datos aparecen en la nueva PC.
