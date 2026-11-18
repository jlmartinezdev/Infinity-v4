# Crea %LocalAppData%\InfinityRedWidget\settings.json para el widget y la ventana flotante.
# Si ya existe, no lo pisa: solo copia el token al portapapeles.
# Uso: powershell -ExecutionPolicy Bypass -File windows\InfinityRedFlotante\configurar.ps1 [-Url https://panel] [-Nuevo]
param(
    [string]$Url = 'https://infinityisppro.net',
    [switch]$Nuevo
)

$ErrorActionPreference = 'Stop'
$carpeta = Join-Path $env:LOCALAPPDATA 'InfinityRedWidget'
$archivo = Join-Path $carpeta 'settings.json'

New-Item -ItemType Directory -Force -Path $carpeta | Out-Null

if ((Test-Path $archivo) -and -not $Nuevo) {
    $ajustes = Get-Content $archivo -Raw | ConvertFrom-Json
    Write-Host "Ya existe: $archivo"
} else {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $token = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    $ajustes = [ordered]@{ apiBaseUrl = $Url.TrimEnd('/'); token = $token }
    [IO.File]::WriteAllText($archivo, ($ajustes | ConvertTo-Json), (New-Object Text.UTF8Encoding($false)))
    $ajustes = [pscustomobject]$ajustes
    Write-Host "Creado: $archivo"
}

Set-Clipboard -Value $ajustes.token
$sha = [System.Security.Cryptography.SHA256]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes($ajustes.token))
$huella = (-join ($sha | ForEach-Object { $_.ToString('x2') })).Substring(0, 12)
Write-Host "URL: $($ajustes.apiBaseUrl)"
Write-Host "Huella del token: $huella (largo $($ajustes.token.Length))"
Write-Host 'El token quedo copiado al portapapeles. En el .env del servidor:'
Write-Host '  WIDGET_RED_TOKEN=<pegar>'
Write-Host 'y despues: php artisan config:cache'

try {
    $r = Invoke-WebRequest -UseBasicParsing -Uri ($ajustes.apiBaseUrl + '/api/v1/widget/red') -Headers @{ Authorization = 'Bearer ' + $ajustes.token; Accept = 'application/json' }
    Write-Host "Prueba de la API: $($r.StatusCode) (el servidor ya acepta el token)"
} catch {
    $codigo = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 'sin respuesta' }
    Write-Host "Prueba de la API: $codigo (401 = el servidor todavia no tiene este token)"
}
