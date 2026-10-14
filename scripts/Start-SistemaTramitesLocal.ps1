# Mantiene disponible el servidor local de Laravel sin depender de XAMPP/MySQL.
param([switch] $Launch)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
if ($Launch) {
    $serverScript = Join-Path $PSScriptRoot 'Start-SistemaTramitesLocal.ps1'
    $parameters = '-NoProfile -ExecutionPolicy RemoteSigned -File "' + $serverScript + '"'
    Start-Process -FilePath powershell.exe -ArgumentList $parameters -WorkingDirectory $projectRoot -WindowStyle Hidden
    if (Test-Path -LiteralPath (Join-Path $env:LOCALAPPDATA 'SistemaTramites\tools\mailpit\mailpit.exe')) {
        & (Join-Path $PSScriptRoot 'Start-MailboxLocal.ps1')
    }
    $available = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue) { $available = $true; break }
        Start-Sleep -Milliseconds 300
    }
    if (-not $available) { throw 'No se pudo iniciar el sistema. Revisa storage\logs\local-server-supervisor-v2.log.' }
    Start-Process 'http://127.0.0.1:8000/login'
    exit 0
}
$logPath = Join-Path $projectRoot 'storage\logs\local-server-supervisor-v2.log'
$mutex = [System.Threading.Mutex]::new($false, 'Local\SistemaTramitesLaravel8000')
$hasLock = $false

function Write-SupervisorLog([string] $message) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message
    Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
}

function Find-CompatiblePhp {
    $packagesRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    $candidates = @()
    if (Test-Path -LiteralPath $packagesRoot) {
        $candidates += Get-ChildItem -LiteralPath $packagesRoot -Directory -Filter 'PHP.PHP.8.*' |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName 'php.exe' }
    }
    $pathPhp = Get-Command php -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($pathPhp) { $candidates += $pathPhp.Source }

    foreach ($candidate in ($candidates | Select-Object -Unique)) {
        if (-not (Test-Path -LiteralPath $candidate)) {
            continue
        }

        $versionId = & $candidate -r 'echo PHP_VERSION_ID;' 2>$null
        if ($LASTEXITCODE -eq 0 -and [int]$versionId -ge 80300) {
            return $candidate
        }
    }

    return $null
}

try {
    try {
        $hasLock = $mutex.WaitOne(0)
    } catch [System.Threading.AbandonedMutexException] {
        $hasLock = $true
    }

    if (-not $hasLock) {
        exit 0
    }

    New-Item -ItemType Directory -Path (Split-Path -Parent $logPath) -Force | Out-Null
    Set-Location -LiteralPath $projectRoot
    Write-SupervisorLog 'Supervisor local iniciado.'

    while ($true) {
        # Si el usuario inició otro servidor en el mismo puerto, no lo interrumpimos.
        $listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
        if ($listener) {
            Start-Sleep -Seconds 5
            continue
        }

        $phpExe = Find-CompatiblePhp
        if (-not $phpExe) {
            Write-SupervisorLog 'No se encontro PHP 8.3 o superior; nuevo intento en un minuto.'
            Start-Sleep -Seconds 60
            continue
        }

        Write-SupervisorLog "Iniciando Laravel en http://127.0.0.1:8000/ con $phpExe"
        & $phpExe artisan serve --host=127.0.0.1 --port=8000 2>&1 |
            ForEach-Object {
                $serverLine = [string] $_
                if ($serverLine -match 'ERROR|Exception|Server running|Failed') {
                    Write-SupervisorLog $serverLine
                }
            }
        Write-SupervisorLog "Laravel se cerro con codigo $LASTEXITCODE; reinicio en cinco segundos."
        Start-Sleep -Seconds 5
    }
} finally {
    if ($hasLock) {
        $mutex.ReleaseMutex()
    }
    $mutex.Dispose()
}
