param([switch] $ServerOnly)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
$taskNames = @('SistemaTramites-ServidorLocal')
if (-not $ServerOnly) { $taskNames += @('SistemaTramites-RespaldoLocal','SistemaTramites-CorreoLocal') }
foreach ($name in $taskNames) {
    $existingTask = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    if ($existingTask -and (($existingTask.Actions | Where-Object { $_.Arguments -notlike ('*' + $projectRoot + '*') }).Count -gt 0)) {
        throw ('La tarea existente ' + $name + ' pertenece a otra carpeta. Revísala antes de instalar.')
    }
}
$logonTrigger = New-ScheduledTaskTrigger -AtLogOn -User $identity
if (-not $ServerOnly) {
    $options = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
    $backupScript = Join-Path $PSScriptRoot 'Backup-SistemaTramitesLocal.ps1'
    $backupAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $backupScript + '"') -WorkingDirectory $projectRoot
    $backupTrigger = New-ScheduledTaskTrigger -Daily -At '03:00'
    Register-ScheduledTask -TaskName 'SistemaTramites-RespaldoLocal' -Action $backupAction -Trigger $backupTrigger -Settings $options -Principal $principal -Description 'Respaldo diario cifrado de SQLite y archivos privados. La clave se configura en .env.' -Force | Out-Null
    $mailScript = Join-Path $PSScriptRoot 'Start-MailboxLocal.ps1'
    $mailAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $mailScript + '" -Install') -WorkingDirectory $projectRoot
    Register-ScheduledTask -TaskName 'SistemaTramites-CorreoLocal' -Action $mailAction -Trigger $logonTrigger -Settings $options -Principal $principal -Description 'Inicia la bandeja de correo de pruebas, accesible solo desde esta PC.' -Force | Out-Null
}
$serverScript = Join-Path $PSScriptRoot 'Start-SistemaTramitesLocal.ps1'
$serverAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $serverScript + '"') -WorkingDirectory $projectRoot
$serverOptions = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
# Este disparador tambien recupera una salida manual o normal, que no activa RestartCount.
$recoveryTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$recoveryTrigger.Repetition.StopAtDurationEnd = $false
Register-ScheduledTask -TaskName 'SistemaTramites-ServidorLocal' -Action $serverAction -Trigger @($logonTrigger, $recoveryTrigger) -Settings $serverOptions -Principal $principal -Description 'Mantiene Laravel en esta PC y recupera su supervisor cada minuto, independientemente de la aplicacion de escritorio.' -Force | Out-Null
if ($ServerOnly) {
    Write-Output 'Servidor local independiente configurado, con recuperacion automatica cada minuto.'
} else {
    Write-Output 'Inicio local, respaldo diario y bandeja de pruebas configurados.'
}
