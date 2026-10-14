param([switch] $Install)

$ErrorActionPreference = 'Stop'
trap {
    $failureFolder = Join-Path $env:LOCALAPPDATA 'SistemaTramites\logs'
    New-Item -ItemType Directory -Path $failureFolder -Force | Out-Null
    Add-Content -LiteralPath (Join-Path $failureFolder 'mailbox-startup.log') -Value ((Get-Date -Format 's') + ' ' + $_.Exception.Message) -Encoding UTF8
    exit 1
}
$stateRoot = Join-Path $env:LOCALAPPDATA 'SistemaTramites'
$toolFolder = Join-Path $stateRoot 'tools\mailpit'
$mailFolder = Join-Path $stateRoot 'mailpit'
$binary = Join-Path $toolFolder 'mailpit.exe'

if (-not (Test-Path -LiteralPath $binary)) {
    if (-not $Install) { throw ('Falta Mailpit en ' + $binary + '. Ejecuta este script con -Install para preparar la bandeja local.') }
    New-Item -ItemType Directory -Path $toolFolder -Force | Out-Null
    $archive = Join-Path $toolFolder 'mailpit-v1.31.5.zip'
    Invoke-WebRequest -Uri 'https://github.com/axllent/mailpit/releases/download/v1.31.5/mailpit-windows-amd64.zip' -OutFile $archive
    if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne '9637e21c02b33185fe69268ba05317152a5d8ac7877115e8a70e29b7e199e8dc') {
        throw 'La descarga no coincide con el archivo oficial de Mailpit.'
    }
    Expand-Archive -LiteralPath $archive -DestinationPath $toolFolder -Force
}

if (-not (Get-NetTCPConnection -LocalPort 8025 -State Listen -ErrorAction SilentlyContinue)) {
    New-Item -ItemType Directory -Path $mailFolder -Force | Out-Null
    $databaseArgument = '"' + (Join-Path $mailFolder 'mailpit.db') + '"'
    Start-Process -FilePath $binary -ArgumentList @('--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--database',$databaseArgument,'--disable-version-check','--label','"Sistema de Tramites - correo de prueba"') -WorkingDirectory $mailFolder -WindowStyle Hidden -RedirectStandardOutput (Join-Path $mailFolder 'output.log') -RedirectStandardError (Join-Path $mailFolder 'error.log')
}
Write-Output 'Bandeja local: http://127.0.0.1:8025. Captura mensajes de prueba y no los envía a Internet.'
