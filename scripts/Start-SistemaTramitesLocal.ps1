# Mantiene disponible el servidor local de Laravel sin depender de XAMPP/MySQL.
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$logPath = Join-Path $projectRoot 'storage\logs\local-server-supervisor-v2.log'
$mutex = [System.Threading.Mutex]::new($false, 'Local\SistemaTramitesLaravel8000')
$hasLock = $false

function Write-SupervisorLog([string] $message) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message
    Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
}

function Find-CompatiblePhp {
    $packagesRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (-not (Test-Path -LiteralPath $packagesRoot)) {
        return $null
    }

    $packages = Get-ChildItem -LiteralPath $packagesRoot -Directory -Filter 'PHP.PHP.8.*' |
        Sort-Object Name -Descending

    foreach ($package in $packages) {
        $candidate = Join-Path $package.FullName 'php.exe'
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
