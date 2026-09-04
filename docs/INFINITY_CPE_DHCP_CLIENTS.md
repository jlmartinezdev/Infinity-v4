# Infinity — CPE DHCP clients (portal)

Contrato para Interplus Clientes: listar dispositivos de la LAN del CPE del cliente logueado.

Base: `/api/v1`  
Auth: `Authorization: Bearer {token}` (portal)  
Envelope: `{ "success": true|false, "message": "...", "data": ... }`

La app **no** distingue tecnología. El mismo GET; Infinity elige la fuente.

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

JSON **igual** en wireless y FTTH. Solo cambia `source`.

```json
{
  "success": true,
  "message": "7 dispositivos DHCP",
  "data": {
    "source": "tr069_acs",
    "collected_at": "2026-08-28T22:00:00+00:00",
    "gateway_ip": "10.0.8.20",
    "servicio_id": 110,
    "clients": [
      {
        "ip": "192.168.1.50",
        "mac": "aa:bb:cc:dd:ee:ff",
        "hostname": "android-phone",
        "online": null,
        "lease_expires_at": null
      }
    ]
  }
}
```

| Campo | Notas |
|-------|--------|
| `source` | `tr069_acs` (GenieACS hosts, misma tabla del panel) · `ubnt_dhcpd_leases` (SSH antena) · `null` en soft-fail |
| `collected_at` | ISO8601 UTC; `null` si no hubo lectura |
| `gateway_ip` | IP del servicio (CPE) |
| `servicio_id` | Servicio elegido (también en soft-fail si se resolvió) |
| `clients[].ip` | Obligatorio (hosts ACS sin IP se omiten) |
| `clients[].mac` | Obligatorio, minúsculas `aa:bb:…` |
| `clients[].hostname` | Prioridad para UI; puede ser `null` |
| `clients[].online` | Ubnt: lease vigente. TR-069: `null` (no hay expiry) |
| `clients[].lease_expires_at` | Ubnt ISO8601 UTC; TR-069 `null` |

Orden: primero con hostname, luego online, luego IP.

No se mandan `rssi` / origen LAN-WiFi (el panel puede mostrarlos; la app no los necesita).

---

## Soft-fail

La app **sigue con escaneo local** si:

- `success: true` + `clients: []`
- o `success: false` / 404

Casos (siempre **200** + `clients: []`, `source`/`collected_at` `null`):

- Cliente sin servicio
- ACS sin Inform / sin hosts parseables (FTTH)
- SSH a la antena falla o timeout
- Leases vacíos / no parseables
- Fibra **sin** ACS (ONU bridge, V-SOL solo OLT)

Si se resolvió el servicio: viene `servicio_id` (y `gateway_ip` si hay IP). Ejemplo: `?servicio_id=110` no debe devolver `servicio_id: null`.

---

## Origen de datos (Infinity)

1. Resuelve servicio (ACS preferido, luego wireless con IP, no cancelado).
2. **Si el servicio usa ACS (TR-069)** → `GenieAcsService::hosts()` (misma fuente que Herramientas de red → Hosts LAN). `source: tr069_acs`.
3. **Si no es fibra** → SSH Ubiquiti `cat /tmp/dhcpd.leases`. `source: ubnt_dhcpd_leases`.
4. Fibra sin ACS → vacío.

Misma fuente que el panel:

- FTTH Huawei / Iuron / TP-Link ACS → **TR-069 hosts**
- LiteBeam / antena Ubnt → **DHCP antena**

---

## QA

1. Cliente FTTH con ACS e Inform (p. ej. servicio 110, EG8145V5) → `source: tr069_acs`, `servicio_id` del servicio, `clients` con los mismos IP/MAC/hostname que el panel.
2. `?servicio_id=110` → no viene `servicio_id: null`.
3. Cliente solo LiteBeam → `source: ubnt_dhcpd_leases` como antes.
4. ACS sin Inform / sin hosts → `clients: []`, sin 5xx; app usa scan local.
5. Matchear en app: `hostname` + `mac` normalizada.
