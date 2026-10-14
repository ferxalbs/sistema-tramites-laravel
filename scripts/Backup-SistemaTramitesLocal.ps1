$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$packagesRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
$candidates = @()
if (Test-Path -LiteralPath $packagesRoot) {
    $candidates += Get-ChildItem -LiteralPath $packagesRoot -Directory -Filter 'PHP.PHP.8.*' |
        Sort-Object Name -Descending |
        ForEach-Object { Join-Path $_.FullName 'php.exe' }
}
$pathPhp = Get-Command php -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
if ($pathPhp) { $candidates += $pathPhp.Source }
$php = $null
foreach ($candidate in ($candidates | Select-Object -Unique)) {
    if (Test-Path -LiteralPath $candidate) {
        $version = & $candidate -r 'echo PHP_VERSION_ID;'
        if ($LASTEXITCODE -eq 0 -and [int] $version -ge 80300) { $php = $candidate; break }
    }
}
if (-not $php) { throw 'No se encontró PHP 8.3 o superior.' }
Set-Location -LiteralPath $projectRoot
$logFolder = Join-Path $env:LOCALAPPDATA 'SistemaTramites\logs'
New-Item -ItemType Directory -Path $logFolder -Force | Out-Null
$log = Join-Path $logFolder ('backup-' + (Get-Date -Format 'yyyy-MM-dd') + '.log')
& $php artisan system:backup *>> $log
if ($LASTEXITCODE -ne 0) { throw ('El respaldo falló. Revisa ' + $log) }
