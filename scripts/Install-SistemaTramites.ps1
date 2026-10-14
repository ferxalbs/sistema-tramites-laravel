# Instala el Sistema de Trámites en una PC Windows con una base SQLite nueva y lo deja abierto.
# Se puede ejecutar varias veces: los pasos ya completados se omiten.
param(
    [int] $Puerto = 8000,
    [switch] $SoloIniciar,
    [switch] $SinIniciar
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot

$requiredExtensions = @('curl', 'ffi', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sodium', 'sqlite3', 'zip')

function Write-Step([string] $message) {
    Write-Host ''
    Write-Host "==> $message" -ForegroundColor Cyan
}

function Stop-Install([string] $message) {
    Write-Host ''
    Write-Host "ERROR: $message" -ForegroundColor Red
    Write-Host ''
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}

function Get-PhpVersionId([string] $phpExe) {
    try {
        $versionId = & $phpExe -n -r 'echo PHP_VERSION_ID;' 2>$null
        if ($LASTEXITCODE -eq 0) {
            return [int] $versionId
        }
    } catch {
    }

    return 0
}

function Find-CompatiblePhp {
    $candidates = [System.Collections.Generic.List[string]]::new()

    $packagesRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path -LiteralPath $packagesRoot) {
        Get-ChildItem -LiteralPath $packagesRoot -Directory -Filter 'PHP.PHP.8.*' |
            Sort-Object Name -Descending |
            ForEach-Object { $candidates.Add((Join-Path $_.FullName 'php.exe')) }
    }

    Get-Command php -All -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandType -eq 'Application' } |
        ForEach-Object { $candidates.Add($_.Source) }

    foreach ($candidate in $candidates) {
        if ((Test-Path -LiteralPath $candidate) -and (Get-PhpVersionId $candidate) -ge 80300) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    return $null
}

function Install-Php {
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Stop-Install 'No se encontró PHP 8.3 o superior ni winget para instalarlo. Instala PHP 8.5 desde https://windows.php.net/download y vuelve a ejecutar INSTALAR.cmd.'
    }

    Write-Host 'No se encontró PHP 8.3 o superior. Se instalará PHP 8.5 con winget (puede pedir permiso de Windows).'
    & winget install --id PHP.PHP.8.5 --exact --source winget --accept-package-agreements --accept-source-agreements
    $php = Find-CompatiblePhp
    if (-not $php) {
        Stop-Install 'PHP se instaló pero no se pudo localizar. Cierra esta ventana y vuelve a ejecutar INSTALAR.cmd.'
    }

    return $php
}

function Enable-PhpExtensions([string] $phpExe) {
    $loaded = (& $phpExe -m) | ForEach-Object { $_.Trim().ToLowerInvariant() }
    $missing = $requiredExtensions | Where-Object { $loaded -notcontains $_ }
    $ffiEnabled = (& $phpExe -r 'echo ini_get("ffi.enable");') -in @('1', 'true', 'On')

    if (-not $missing -and $ffiEnabled) {
        return
    }

    $phpDir = Split-Path -Parent $phpExe
    $iniPath = Join-Path $phpDir 'php.ini'
    if (-not (Test-Path -LiteralPath $iniPath)) {
        $template = Join-Path $phpDir 'php.ini-production'
        if (-not (Test-Path -LiteralPath $template)) {
            Stop-Install "No existe php.ini en $phpDir. Actívale las extensiones: $($requiredExtensions -join ', ')."
        }
        Copy-Item -LiteralPath $template -Destination $iniPath
    }

    Write-Host "Activando extensiones de PHP en $iniPath"
    $lines = [System.Collections.Generic.List[string]] (Get-Content -LiteralPath $iniPath)
    $extDir = (Join-Path $phpDir 'ext') -replace '\\', '/'

    $extDirIndex = $lines.FindIndex({ param($l) $l -match '^\s*;?\s*extension_dir\s*=\s*"ext"' })
    if (-not ($lines | Where-Object { $_ -match '^\s*extension_dir\s*=' })) {
        if ($extDirIndex -ge 0) {
            $lines[$extDirIndex] = "extension_dir = `"$extDir`""
        } else {
            $lines.Add("extension_dir = `"$extDir`"")
        }
    }

    foreach ($extension in $missing) {
        $index = $lines.FindIndex({ param($l) $l -match "^\s*;\s*extension\s*=\s*(php_)?$extension(\.dll)?\s*$" })
        if ($index -ge 0) {
            $lines[$index] = "extension=$extension"
        } else {
            $lines.Add("extension=$extension")
        }
    }

    if (-not $ffiEnabled) {
        $index = $lines.FindIndex({ param($l) $l -match '^\s*;?\s*ffi\.enable\s*=' })
        if ($index -ge 0) {
            $lines[$index] = 'ffi.enable=true'
        } else {
            $lines.Add('ffi.enable=true')
        }
    }

    [System.IO.File]::WriteAllLines($iniPath, $lines)

    $loaded = (& $phpExe -m) | ForEach-Object { $_.Trim().ToLowerInvariant() }
    $stillMissing = $requiredExtensions | Where-Object { $loaded -notcontains $_ }
    if ($stillMissing) {
        Stop-Install "PHP no pudo cargar estas extensiones: $($stillMissing -join ', '). Revisa $iniPath."
    }
}

function Get-ComposerPhar([string] $phpExe) {
    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if ($composer) {
        $phar = Join-Path (Split-Path -Parent $composer.Source) 'composer.phar'
        if (Test-Path -LiteralPath $phar) {
            return $phar
        }
    }

    $toolsDir = Join-Path $env:LOCALAPPDATA 'SistemaTramites'
    $phar = Join-Path $toolsDir 'composer.phar'
    if (-not (Test-Path -LiteralPath $phar)) {
        Write-Host 'Descargando Composer desde getcomposer.org...'
        New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $phar -UseBasicParsing
    }

    return $phar
}

function Invoke-Php([string[]] $arguments) {
    & $script:php -d ffi.enable=true @arguments
    if ($LASTEXITCODE -ne 0) {
        Stop-Install "Falló: php $($arguments -join ' ')"
    }
}

function Set-EnvValue([string] $content, [string] $key, [string] $value) {
    if ($content -match "(?m)^$key=") {
        return $content -replace "(?m)^$key=.*$", "$key=$value"
    }

    return $content.TrimEnd() + "`r`n$key=$value`r`n"
}

function Test-PortInUse([int] $port) {
    return [bool] (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

function Start-App {
    if ($Puerto -eq 8000) {
        & (Join-Path $PSScriptRoot 'Start-SistemaTramitesLocal.ps1') -Launch
        return
    }

    $url = "http://127.0.0.1:$Puerto/login"
    if (Test-PortInUse $Puerto) {
        Write-Host "El puerto $Puerto ya está en uso; se abre el navegador en $url" -ForegroundColor Yellow
        Start-Process $url
        return
    }

    Write-Host ''
    Write-Host '==========================================================' -ForegroundColor Green
    Write-Host " Sistema de Trámites: $url" -ForegroundColor Green
    Write-Host ' Mantén esta ventana abierta mientras usas el sistema.' -ForegroundColor Green
    Write-Host ' Para apagarlo, cierra la ventana o presiona Ctrl+C.' -ForegroundColor Green
    Write-Host '==========================================================' -ForegroundColor Green
    Start-Job -ScriptBlock { param($u) Start-Sleep -Seconds 3; Start-Process $u } -ArgumentList $url | Out-Null
    & $script:php -d ffi.enable=true artisan serve --host=127.0.0.1 --port=$Puerto
}

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    Stop-Install "No se encontró 'artisan' en $projectRoot. Extrae el ZIP completo y ejecuta INSTALAR.cmd desde su carpeta."
}

Write-Step 'Buscando PHP 8.3 o superior'
$script:php = Find-CompatiblePhp
if (-not $script:php) {
    if ($SoloIniciar) {
        Stop-Install 'No se encontró PHP 8.3 o superior. Ejecuta primero INSTALAR.cmd.'
    }
    $script:php = Install-Php
}
Write-Host "PHP: $script:php ($(& $script:php -n -r 'echo PHP_VERSION;'))"

if ($SoloIniciar) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor\autoload.php'))) {
        Stop-Install 'El sistema aún no está instalado. Ejecuta primero INSTALAR.cmd.'
    }
    Start-App
    exit 0
}

Write-Step 'Revisando extensiones de PHP'
Enable-PhpExtensions $script:php

Write-Step 'Preparando el archivo .env'
$envPath = Join-Path $projectRoot '.env'
if (Test-Path -LiteralPath $envPath) {
    Write-Host '.env ya existe; se conserva sin cambios.'
} else {
    $envContent = Get-Content -LiteralPath (Join-Path $projectRoot '.env.example') -Raw -Encoding UTF8
    $envContent = Set-EnvValue $envContent 'DB_CONNECTION' 'sqlite'
    $envContent = Set-EnvValue $envContent 'APP_URL' "http://127.0.0.1:$Puerto"
    [System.IO.File]::WriteAllText($envPath, $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host '.env creado con base SQLite local y correo de prueba (MAIL_MAILER=array).'
}

Write-Step 'Instalando dependencias de PHP (Composer); puede tardar unos minutos'
$composerPhar = Get-ComposerPhar $script:php
Invoke-Php @($composerPhar, 'install', '--no-interaction', '--prefer-dist', '--no-progress')

Write-Step 'Generando la clave de la aplicación'
if ((Get-Content -LiteralPath $envPath -Raw) -match '(?m)^APP_KEY=\s*$') {
    Invoke-Php @('artisan', 'key:generate', '--force', '--no-interaction')
} else {
    Write-Host 'La clave ya existe.'
}

Write-Step 'Creando la base de datos SQLite'
$dbPath = Join-Path $projectRoot 'database\database.sqlite'
$newDatabase = -not (Test-Path -LiteralPath $dbPath)
if ($newDatabase) {
    New-Item -ItemType File -Path $dbPath -Force | Out-Null
}
Invoke-Php @('artisan', 'migrate', '--force', '--no-interaction')
if ($newDatabase) {
    Invoke-Php @('artisan', 'db:seed', '--force', '--no-interaction')
} else {
    Write-Host 'La base ya existía; no se vuelven a cargar los datos iniciales.'
}

$userCount = & $script:php -d ffi.enable=true artisan tinker --execute="echo DB::table('users')->count();" 2>$null | Select-Object -Last 1
if ("$userCount".Trim() -eq '0') {
    Write-Step 'Creando el primer administrador'
    Write-Host 'El correo debe terminar en @seoane.edu.pe'
    do {
        $nombres = Read-Host 'Nombres'
        $apellidos = Read-Host 'Apellidos'
        $email = Read-Host 'Correo institucional'
        & $script:php -d ffi.enable=true artisan accounts:bootstrap-admin "--nombres=$nombres" "--apellidos=$apellidos" "--email=$email"
        $ok = $LASTEXITCODE -eq 0
        if (-not $ok) {
            Write-Host 'Corrige los datos e inténtalo de nuevo.' -ForegroundColor Yellow
        }
    } until ($ok)
    Write-Host ''
    Write-Host 'COPIA AHORA la contraseña temporal de arriba: no se volverá a mostrar.' -ForegroundColor Yellow
    Read-Host 'Cuando la hayas guardado, presiona Enter para abrir el sistema'
} else {
    Write-Host ''
    Write-Host 'La base ya tiene usuarios; no se crea otro administrador.'
}

Write-Host ''
Write-Host 'Instalación completa. Para abrir el sistema otro día usa INICIAR_LOCAL.cmd.' -ForegroundColor Green

if (-not $SinIniciar) {
    Start-App
}
