$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
foreach ($name in @('SistemaTramites-RespaldoLocal','SistemaTramites-CorreoLocal','SistemaTramites-ServidorLocal')) {
    $existingTask = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    if ($existingTask -and (($existingTask.Actions | Where-Object { $_.Arguments -notlike ('*' + $projectRoot + '*') }).Count -gt 0)) {
        throw ('La tarea existente ' + $name + ' pertenece a otra carpeta. Revísala antes de instalar.')
    }
}
$options = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
$backupScript = Join-Path $PSScriptRoot 'Backup-SistemaTramitesLocal.ps1'
$backupAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $backupScript + '"') -WorkingDirectory $projectRoot
$backupTrigger = New-ScheduledTaskTrigger -Daily -At '03:00'
Register-ScheduledTask -TaskName 'SistemaTramites-RespaldoLocal' -Action $backupAction -Trigger $backupTrigger -Settings $options -Principal $principal -Description 'Respaldo diario cifrado de SQLite y archivos privados. La clave se configura en .env.' -Force | Out-Null
$mailScript = Join-Path $PSScriptRoot 'Start-MailboxLocal.ps1'
$mailAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $mailScript + '" -Install') -WorkingDirectory $projectRoot
$mailTrigger = New-ScheduledTaskTrigger -AtLogOn -User $identity
Register-ScheduledTask -TaskName 'SistemaTramites-CorreoLocal' -Action $mailAction -Trigger $mailTrigger -Settings $options -Principal $principal -Description 'Inicia la bandeja de correo de pruebas, accesible solo desde esta PC.' -Force | Out-Null
$serverScript = Join-Path $PSScriptRoot 'Start-SistemaTramitesLocal.ps1'
$serverAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy RemoteSigned -WindowStyle Hidden -File "' + $serverScript + '"') -WorkingDirectory $projectRoot
$serverOptions = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero)
Register-ScheduledTask -TaskName 'SistemaTramites-ServidorLocal' -Action $serverAction -Trigger $mailTrigger -Settings $serverOptions -Principal $principal -Description 'Mantiene Laravel disponible solo en esta PC, sin depender de XAMPP.' -Force | Out-Null
Write-Output 'Inicio local, respaldo diario y bandeja de pruebas configurados.'
