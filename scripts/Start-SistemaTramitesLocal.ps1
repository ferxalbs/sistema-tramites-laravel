# Mantiene disponible el servidor local de Laravel sin depender de XAMPP/MySQL.
param([switch] $Launch, [switch] $NoBrowser)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
if ($Launch) {
    Import-Module ScheduledTasks
    $serverScript = Join-Path $PSScriptRoot 'Start-SistemaTramitesLocal.ps1'
    $taskName = 'SistemaTramites-ServidorLocal'
    $serverTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    if ($serverTask -and (@($serverTask.Actions).Count -ne 1 -or -not $serverTask.Actions[0].Arguments.Contains('"' + $serverScript + '"'))) {
        throw 'La tarea de servidor existente pertenece a otra carpeta. Revisala antes de iniciar.'
    }
    $hasRecoveryTrigger = $serverTask -and @($serverTask.Triggers | Where-Object { $_.Repetition.Interval -eq 'PT1M' }).Count -gt 0
    if (-not $serverTask -or -not $hasRecoveryTrigger -or -not $serverTask.Settings.Enabled) {
        & (Join-Path $PSScriptRoot 'Install-LocalOperations.ps1') -ServerOnly
    }
    # Windows crea el proceso fuera del terminal y de la aplicacion que abrio el lanzador.
    Start-ScheduledTask -TaskName $taskName
    $mailTask = Get-ScheduledTask -TaskName 'SistemaTramites-CorreoLocal' -ErrorAction SilentlyContinue
    $mailScript = Join-Path $PSScriptRoot 'Start-MailboxLocal.ps1'
    if ($mailTask -and @($mailTask.Actions).Count -eq 1 -and $mailTask.Actions[0].Arguments.Contains('"' + $mailScript + '"')) {
        Start-ScheduledTask -TaskName $mailTask.TaskName
    }
    $available = $false
    $deadline = (Get-Date).AddSeconds(45)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login' -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) { $available = $true; break }
        } catch {
            Start-Sleep -Milliseconds 300
        }
    }
    if (-not $available) { throw 'No se pudo iniciar el sistema. Revisa storage\logs\local-server-supervisor-v2.log.' }
    Write-Output 'Sistema disponible: http://127.0.0.1:8000/login. Windows mantiene el servidor en segundo plano.'
    if (-not $NoBrowser) { Start-Process 'http://127.0.0.1:8000/login' }
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
