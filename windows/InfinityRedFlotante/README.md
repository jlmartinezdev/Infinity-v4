# Red Infinity en la esquina (Windows)

Ventana chica, sin bordes y siempre encima, que se queda en una esquina de la pantalla (por defecto abajo a la derecha, sobre el reloj). Muestra lo mismo que el widget de Win+W, con gráficos:

- Anillo con el porcentaje de equipos que responden (verde en línea, rojo caídos, gris sin dato).
- Una barra por tipo (AP, Router, OLT) con en línea / caídos / sin dato.
- Los equipos caídos (hasta 3) y hace cuánto.
- Latencia: promedio de AP y de routers, y los 4 equipos más lentos (verde < 50 ms, amarillo < 150 ms, rojo más).

Consulta `GET {apiBaseUrl}/api/v1/widget/red` cada 60 segundos mientras está visible. Los gráficos son del estado actual; no hay historial.

## Uso

- Arrastrá la barra de arriba para moverla. Cerca de un borde se pega sola. La posición queda guardada.
- **–** la achica a solo el estado y el anillo; **+** la vuelve a agrandar.
- **×** la oculta. Sigue en el área de notificación (junto al reloj): clic para mostrar u ocultar.
- Clic derecho en el icono: actualizar ahora, mover a una esquina, iniciar con Windows, salir.

## Configuración

Usa la misma URL y el mismo token que el widget de Win+W (`WIDGET_RED_TOKEN` del `.env` del panel), en este orden:

1. `INFINITY_WIDGET_API_BASE` y `INFINITY_WIDGET_TOKEN`
2. `settings.json` junto al ejecutable
3. `%LocalAppData%\InfinityRedWidget\settings.json`

```json
{
  "apiBaseUrl": "https://panel.ejemplo.com",
  "token": "el valor de WIDGET_RED_TOKEN"
}
```

`configurar.ps1` crea ese archivo en `%LocalAppData%\InfinityRedWidget` con un token nuevo, lo copia al portapapeles y prueba la API. Si el archivo ya existe no lo pisa (con `-Nuevo` genera otro token):

```powershell
powershell -ExecutionPolicy Bypass -File windows\InfinityRedFlotante\configurar.ps1
```

El mismo token va en el `.env` del servidor como `WIDGET_RED_TOKEN`, seguido de `php artisan config:cache`.

La posición y el modo chico se guardan en `%LocalAppData%\InfinityRedWidget\flotante.json`.

## Compilar

Hace falta el SDK de .NET 8 y el runtime de WebView2 (viene con Windows 11 y con Edge).

```powershell
dotnet publish windows/InfinityRedFlotante -c Release -r win-x64 --self-contained false -o publish/InfinityRedFlotante
```

Copiá la carpeta `publish/InfinityRedFlotante` a la PC, poné el `settings.json` al lado de `InfinityRedFlotante.exe` y abrilo. No necesita instalador ni certificado. La PC necesita el runtime de escritorio de .NET 8 o uno más nuevo (corre también con el 9).

## Cambiar el diseño

Todo lo visual está en `wwwroot/mini.html`. Para verlo sin compilar, abrilo en un navegador con `window.DEMO` definido antes del script, con un JSON como el de la API (o `{"error": "Sin conexión"}`).
