#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Instala InfinitySchedule: servicio Windows con `php artisan schedule:work` (NSSM).
  Desactiva las tareas del Programador que abrían un CMD cada minuto.
#>
$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PhpExe = 'C:\xampp\php\php.exe'
$ServiceName = 'InfinitySchedule'
$NssmExe = Join-Path $PSScriptRoot 'nssm\nssm-2.24\win64\nssm.exe'
$LogDir = Join-Path $ProjectRoot 'storage\logs'
$StdoutLog = Join-Path $LogDir 'schedule-work.log'
$StderrLog = Join-Path $LogDir 'schedule-work-error.log'

$TasksToDisable = @(
    'Infinity Laravel Schedule',
    'Infinity Schedule Run',
    'CRON PHP'
)

function Assert-Admin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($id)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Ejecutar este script como Administrador.'
    }
}

Assert-Admin

if (-not (Test-Path $PhpExe)) {
    throw "No se encontro PHP en: $PhpExe"
}
if (-not (Test-Path (Join-Path $ProjectRoot 'artisan'))) {
    throw "No se encontro artisan en: $ProjectRoot"
}
if (-not (Test-Path $NssmExe)) {
    throw "No se encontro NSSM en: $NssmExe"
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

Write-Host '==> Desactivando tareas programadas duplicadas...'
foreach ($taskName in $TasksToDisable) {
    $exists = schtasks /Query /TN $taskName 2>$null
    if ($LASTEXITCODE -eq 0) {
        schtasks /Change /TN $taskName /Disable | Out-Null
        Write-Host "    Desactivada: $taskName"
    }
    else {
        Write-Host "    No existe (ok): $taskName"
    }
}

$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "==> Servicio $ServiceName ya existe; se reinicia configuracion..."
    if ($existing.Status -eq 'Running') {
        & $NssmExe stop $ServiceName
        Start-Sleep -Seconds 2
    }
    & $NssmExe remove $ServiceName confirm
    Start-Sleep -Seconds 1
}

Write-Host "==> Instalando servicio $ServiceName..."
& $NssmExe install $ServiceName $PhpExe
& $NssmExe set $ServiceName AppDirectory $ProjectRoot
& $NssmExe set $ServiceName AppParameters 'artisan schedule:work'
& $NssmExe set $ServiceName DisplayName 'Infinity Laravel Schedule Worker'
& $NssmExe set $ServiceName Description 'Proceso permanente: php artisan schedule:work (Infinity v4)'
& $NssmExe set $ServiceName Start SERVICE_AUTO_START
& $NssmExe set $ServiceName AppStdout $StdoutLog
& $NssmExe set $ServiceName AppStderr $StderrLog
& $NssmExe set $ServiceName AppRotateFiles 1
& $NssmExe set $ServiceName AppRotateBytes 2097152
& $NssmExe set $ServiceName AppExit Default Restart
& $NssmExe set $ServiceName AppRestartDelay 5000
& $NssmExe set $ServiceName AppStopMethodSkip 0
& $NssmExe set $ServiceName AppStopMethodConsole 15000
$system32 = Join-Path $env:SystemRoot 'System32'
& $NssmExe set $ServiceName AppEnvironmentExtra "PATH=C:\xampp\php;C:\xampp\mysql\bin;$system32;$env:SystemRoot;$env:PATH"

Write-Host "==> Iniciando $ServiceName..."
& $NssmExe start $ServiceName
Start-Sleep -Seconds 2

$svc = Get-Service -Name $ServiceName
Write-Host ""
Write-Host "Servicio: $($svc.Name) | Estado: $($svc.Status) | Inicio: $($svc.StartType)"
Write-Host "Logs: $StdoutLog"
Write-Host "      $StderrLog"
Write-Host ""
Write-Host 'Listo. Ya no deberia abrirse un CMD cada minuto.'
