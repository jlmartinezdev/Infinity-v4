# Widget de red (Windows 11)

Proveedor para el tablero de Widgets de Windows 11. Muestra AP, Router y OLT con conteos en línea / caídos, y la lista corta de equipos caídos. El verde es en línea y el rojo es caído. Si un tipo no tiene señal en vivo (hoy los OLT), la fila dice **sin dato**.

Consulta `GET {apiBaseUrl}/api/v1/widget/red` con `Authorization: Bearer` cada 60 segundos mientras el widget está visible. No forma parte de Composer ni de npm, y el CI de Linux no lo compila.

## Configuración

El token es el mismo `WIDGET_RED_TOKEN` del `.env` del panel. No lo dejes en el repositorio.

El proveedor toma la URL y el token, en este orden (cada campo por separado; lo primero que tenga valor gana, salvo las variables de entorno, que pisan al archivo):

1. `INFINITY_WIDGET_API_BASE` y `INFINITY_WIDGET_TOKEN`
2. `settings.json` junto al ejecutable
3. `settings.json` en la carpeta local del paquete (app empaquetada)
4. `%LocalAppData%\InfinityRedWidget\settings.json`

Copiá `settings.example.json`:

```json
{
  "apiBaseUrl": "https://panel.ejemplo.com",
  "token": "el valor de WIDGET_RED_TOKEN"
}
```

`apiBaseUrl` es el origen del panel, sin `/api`. El cliente agrega `/api/v1/widget/red`.

## Empaquetar y registrar

Hace falta Windows 11, Visual Studio 2022 con la carga de trabajo de aplicaciones de Windows, el SDK de Windows y el runtime de Windows App SDK 1.6 (`winget install Microsoft.WindowsAppRuntime.1.6`).

1. Abrí `InfinityRedWidget.sln`.
2. En `Package.appxmanifest`, el `Publisher` (`CN=Infinity`) tiene que coincidir con el subject del certificado con el que firmás. El certificado de prueba de Visual Studio usa otro CN: cambialo en el asistente de empaquetado.
3. Compilá **Release | x64** (o ARM64).
4. Clic derecho en el proyecto → **Package and Publish** → **Create App Packages**. Elegí sideload.
5. Instalá el `.msix` (o el `.msixbundle`) que genera el asistente.
6. Win+W → agregar el widget **Red Infinity**. Podés fijar uno chico y otro mediano o grande: el mismo proveedor cambia la tarjeta según el tamaño. El chico es el resumen (AP, Router, OLT). El mediano y el grande agregan la lista de caídas.

`AllowMultiple` está en true para tener las dos tarjetas a la vez en el tablero.

La vista previa en el panel, sin Windows, está en **Sistema → Widget de red** (`/sistema/widget-red`), con el permiso de monitoreo de red.

Para tenerlo siempre a la vista en una esquina del escritorio (Win+W no lo permite), está la ventana flotante en `windows/InfinityRedFlotante`.
