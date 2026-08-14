# Infinity — CPE DHCP clients (portal)

Contrato para Interplus Clientes: listar dispositivos DHCP de la LAN del CPE del cliente logueado.

Base: `/api/v1`  
Auth: `Authorization: Bearer {token}` (portal)  
Envelope: `{ "success": true|false, "message": "...", "data": ... }`

---

## Endpoint

`GET /portal/v1/cpe/dhcp-clients`

Query opcional:

| Param | Uso |
|-------|-----|
| `servicio_id` | Forzar un servicio del cliente (si tiene varios) |

Middleware: Sanctum + `api.cliente` + `portal.cuenta.ver`

---

## Respuesta

```json
{
  "success": true,
  "message": "3 dispositivos DHCP",
  "data": {
    "source": "ubnt_dhcpd_leases",
    "collected_at": "2026-08-11T01:30:00+00:00",
    "gateway_ip": "10.20.30.40",
    "servicio_id": 1234,
    "clients": [
      {
        "ip": "192.168.1.50",
        "mac": "aa:bb:cc:dd:ee:ff",
        "hostname": "android-phone",
        "online": true,
        "lease_expires_at": "2026-08-11T05:00:00+00:00"
      }
    ]
  }
}
```

| Campo | Notas |
|-------|--------|
| `source` | `ubnt_dhcpd_leases` si se leyó el CPE; `null` en soft-fail |
| `collected_at` | ISO8601 UTC; `null` si no hubo lectura |
| `gateway_ip` | IP del servicio (CPE) usada por SSH |
| `servicio_id` | Servicio elegido |
| `clients[].ip` | Obligatorio |
| `clients[].mac` | Obligatorio, minúsculas `aa:bb:…` (match con scan del celular) |
| `clients[].hostname` | Prioridad para matchear; puede ser `null` |
| `clients[].online` | `true` si lease no venció; `false` vencido; `null` sin expiry |
| `clients[].lease_expires_at` | ISO8601 UTC o `null` |

Orden de `clients`: primero con hostname, luego online, luego IP.

---

## Soft-fail

La app **sigue con escaneo local** si:

- `success: true` + `clients: []` (caso habitual Infinity)
- o `success: false` / 404

Casos soft-fail Infinity (siempre **200** + `clients: []`):

- Cliente sin servicio / sin IP
- Servicio fibra/GPON (DHCP LAN del CPE Ubnt no aplica)
- SSH a la antena falla o timeout
- Archivo de leases vacío / no parseable

En soft-fail: `source` y `collected_at` son `null`. Puede venir `gateway_ip` / `servicio_id` si se resolvió el servicio pero falló la lectura.

---

## Origen de datos (Infinity)

1. Resuelve servicio del cliente (activo preferido, no cancelado) con IP.
2. Excluye fibra/GPON (NAP / OLT / nodo GPON / plan con “fibra”).
3. SSH al CPE Ubiquiti (`servicio.ip`) → `cat /tmp/dhcpd.leases` (`UbntAntenaService`).
4. Mapea leases → contrato app.

Misma fuente que el panel: **Servicios → Herramientas red → DHCP antena**.

Config SSH: `config/ubnt.php` (`UBNT_SSH_*`).

---

## QA

1. Cliente wireless con antena alcanzable → `clients` con `mac` + `hostname` cuando exista.
2. Cliente fibra → `clients: []`, app usa scan local.
3. Antena caída → soft-fail vacío, sin 5xx.
4. Matchear en app: priorizar `hostname` + `mac` normalizada.
