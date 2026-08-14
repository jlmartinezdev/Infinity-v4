#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Detiene y elimina el servicio InfinitySchedule (NSSM).
#>
$ErrorActionPreference = 'Stop'

$ServiceName = 'InfinitySchedule'
$NssmExe = Join-Path $PSScriptRoot 'nssm\nssm-2.24\win64\nssm.exe'

$id = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($id)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecutar este script como Administrador.'
}

if (-not (Test-Path $NssmExe)) {
    throw "No se encontro NSSM en: $NssmExe"
}

$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if (-not $existing) {
    Write-Host "El servicio $ServiceName no existe."
    exit 0
}

if ($existing.Status -eq 'Running') {
    Write-Host "Deteniendo $ServiceName..."
    & $NssmExe stop $ServiceName
    Start-Sleep -Seconds 2
}

Write-Host "Eliminando $ServiceName..."
& $NssmExe remove $ServiceName confirm
Write-Host 'Servicio eliminado.'
Write-Host 'Nota: las tareas del Programador siguen desactivadas; reactivalas manualmente si hace falta.'
