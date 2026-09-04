# Infinity — Wi‑Fi del CPE (portal)

Contrato para Interplus Clientes: **ver SSIDs**, **cambiar la clave** y **cambiar el nombre (SSID)** del router de casa, sin que la app sepa si es Huawei, Iuron, TP-Link, etc.

Base: `/api/v1`  
Auth: `Authorization: Bearer {token}` (portal)  
Envelope: `{ "success": true|false, "message": "...", "data": ... }`  
Middleware: Sanctum + `api.cliente` + `portal.cuenta.ver`

La clave **nunca** se lee (el ACS no la expone). El nombre sí: viene en el GET.

---

## Cuándo aplica

Misma herramienta que el panel: **Servicios → Herramientas de red → TR-069**.

Fuente: GenieACS (`GenieAcsService::setWifi`).

| Equipo | `can_change` / `can_rename` | Qué hace la app |
|--------|-----------------------------|-----------------|
| Router/ONT con ACS (Huawei ACS, Iuron, TP-Link, TCL, …) | `true` | Formulario nombre y/o clave |
| Solo antena Ubiquiti | `false` `no_acs` | FAQ `192.168.1.1` |
| ONU V-SOL en bridge (sin router ACS) | `false` `no_acs` | FAQ |
| Huawei marcado acceso SSH | `false` `ssh_cpe` | FAQ |
| CPE aún no hizo Inform | `false` `cpe_not_found` | Retry / FAQ |

No hay cambio por SSH Ubnt ni por OLT V-SOL.

No hay PIN/OTP: alcanza el cliente autenticado. Límite: 5 cambios/hora por cliente (nombre y clave comparten el cupo).

---

## GET `/portal/v1/cpe/wifi`

Query opcional: `servicio_id`.

Llamar **solo** al abrir la pantalla de Wi‑Fi (pega al ACS, ~20 s).

```json
{
  "success": true,
  "message": "Wi‑Fi del CPE",
  "data": {
    "can_change": true,
    "can_rename": true,
    "source": "tr069_acs",
    "servicio_id": 1234,
    "password_readable": false,
    "pending_inform": false,
    "ssids": [
      { "id": "ap-1", "ssid": "Interplus-5G", "enabled": true, "band": "5GHz" },
      { "id": "ap-6", "ssid": "Interplus-24", "enabled": true, "band": "2.4GHz" }
    ],
    "reason": null,
    "hint": null
  }
}
```

Si `can_change` / `can_rename` son `false`: no mostrar form; usar `hint` (FAQ 192.168.1.1).

| Campo | Notas |
|-------|--------|
| `can_change` | Se puede POST `password` |
| `can_rename` | Se puede POST `ssid` (mismo ACS) |
| `ssids[].id` | Mandar en POST `wifi_id` para una sola banda; omitir / `all` = todas las activas |
| `ssids[].ssid` | Nombre actual visible |

---

## POST `/portal/v1/cpe/wifi`

Hace falta **al menos uno** de `password` o `ssid`. Se pueden mandar los dos.

```json
{ "ssid": "Casa Miguel", "password": "NuevaClave8+", "wifi_id": "all", "servicio_id": 110 }
```

Solo nombre (2.4 GHz):

```json
{ "ssid": "Casa-24", "wifi_id": "wlan-1-1" }
```

| Body | Uso |
|------|-----|
| `password` | Opcional si hay `ssid`. 8–63 caracteres (WPA2) |
| `ssid` | Opcional si hay `password`. 1–32 caracteres, sin controles |
| `wifi_id` | Opcional. `all` (default) o un `id` del GET |
| `servicio_id` | Opcional |

Con `wifi_id: all` el **mismo nombre** se aplica a 2.4 y 5 GHz. Si el cliente quiere nombres distintos, un POST por `wifi_id`.

Tras cambiar el nombre, los celulares dejan de ver la red vieja: hay que reconectarse (y si también cambió la clave, con la nueva).

Éxito: `queued: true`, `ssid` el nombre pedido (o `null` si solo cambió clave). Si `pending_inform: true`, se aplica en el próximo Inform.

Errores: 422 (validación / `can_change: false`), 429 (5/h), 502 (ACS).

No hay cambio de clave **admin** del router en este contrato.

---

## App (QA)

1. FTTH ACS (p. ej. EG8145V5) → GET `can_rename: true` → POST `{ "ssid": "Casa" }` → la red cambia de nombre.
2. POST `{ "password": "…" }` sigue igual (solo clave).
3. Cliente solo LiteBeam → `can_rename: false` → FAQ.
4. Avisar: “vas a tener que volver a conectar los dispositivos a la red nueva”.
