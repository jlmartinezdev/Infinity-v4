# TR-069 / GenieACS (CPE de cliente)

Infinity **no** implementa CWMP. El ACS es GenieACS; el panel consulta su API NBI y opera el CPE en **Herramientas de red**.

Las antenas Ubiquiti siguen por SSH. TR-069 cubre routers/ONT de casa (Huawei, ZTE, TP-Link, Nokia, etc.) sin SSH.

```
CPE  --Inform HTTPS-->  GenieACS :7547 (CWMP)
Infinity Laravel  ----REST---->  GenieACS :7557 (NBI)
Staff (Herramientas de red) --> Infinity
```

## 1. Levantar GenieACS

En el servidor (Docker):

```bash
docker compose -f docker-compose.genieacs.yml up -d
```

| Puerto | Uso |
|--------|-----|
| 7547 | CWMP — URL que se carga en el CPE |
| 7557 | API NBI — solo LAN/VPN; la usa Laravel |
| 7567 | File server (firmware, opcional) |
| 3000 | UI GenieACS (debug) |

UI: `http://ACS:3000` (primera vez suele ser `admin` / `admin`). Cambiá la contraseña.

El host **7547 debe ser alcanzable desde la red de clientes** (IP pública, DNAT o VPN). Si el CPE no puede abrir esa URL, nunca aparece en el ACS.

## 2. Infinity (`.env`)

```
GENIEACS_ENABLED=true
GENIEACS_NBI_URL=http://127.0.0.1:7557
GENIEACS_NBI_USER=
GENIEACS_NBI_PASSWORD=
GENIEACS_TIMEOUT=20
GENIEACS_ONLINE_GRACE_SECONDS=900
```

Si el NBI está en otro servidor, usá esa URL (no expongas 7557 a Internet).

## 3. Provisionar el CPE

En el router/ONT (fábrica, script o una vez a mano):

- ACS URL: `http://IP-O-DOMINIO-DEL-ACS:7547`
- Usuario / clave CWMP (si el ACS los exige)
- Periodic Inform: 5–15 minutos

Sin Inform, Infinity no puede leer ni reiniciar el equipo. El CPE puede estar en NAT: **él llama al ACS**, Infinity no entra por SSH.

## 4. Vincular al servicio

En **Servicios → Editar → Equipo en casa del cliente**:

- **Acceso remoto**: `SSH` (Huawei) o `ACS (TR-069)` (Iuron, TP-Link, etc.)
- **ONU / Router WiFi / Antena**: qué hay instalado (p. ej. LiteBeam + TP-Link 840, o Huawei solo, o Huawei + Iuron)
- **Serial TR-069**: obligatorio si el acceso es ACS (`_deviceId._SerialNumber`)
- **Product class** (opcional)

Herramientas de red muestra TR-069 solo con acceso ACS (o si hay serial y el acceso no es SSH). Con SSH no se envían comandos al ACS.

La antena Ubnt se opera siempre por SSH (tarjeta Antena), independiente del ACS.

## 5. Operar

**Servicios → Herramientas de red → TR-069**:

- Consultar: último Inform, modelo, firmware, WAN IP, SSID, online
- Hosts LAN: dispositivos detrás del CPE
- Refresh: pide parámetros actualizados (en cola hasta el próximo Inform o Connection Request)
- Reboot: tarea asíncrona en el ACS
- **Cambiar clave**: WiFi (todos los SSID activos o uno) o clave del panel del router. El ACS **no lee** la clave actual; solo escribe `SetParameterValues` (`KeyPassphrase` / `LANConfigSecurity.ConfigPassword`). WPA2: 8–63 caracteres.

## 6. Probar

1. Un CPE de prueba con ACS URL apuntando al GenieACS.
2. En la UI de GenieACS debe aparecer el device y un `_lastInform` reciente.
3. Copiar el serial al servicio en Infinity.
4. Herramientas de red → Consultar.

Si “no se encontró el dispositivo”: el Inform no llegó, el serial no coincide, o `GENIEACS_NBI_URL` no apunta al ACS correcto.

## 7. Iuron / TCL AC1200 (TR-181 `Device.`)

Este modelo **no** usa `InternetGatewayDevice`. Identidad típica:

| Campo ACS | Valor de ejemplo |
|-----------|------------------|
| `_id` | `7433A6-DEVICE-7433a60afd05` |
| Serial | `7433a60afd05` (MAC WAN sin `:`) |
| Product class | `DEVICE` |
| Manufacturer | TCL |
| Model | AC1200 |

En el servicio de Infinity: **Serial TR-069** = `7433a60afd05` (product class opcional `DEVICE`).

Qué lee Infinity:

- WAN IP: host de `Device.ManagementServer.ConnectionRequestURL` (este CPE no publica IP WAN en PPP/`IP.Interface.1`; ese path es el LAN `br0`).
- WAN MAC: `Device.Ethernet.Link` con tráfico (suele ser `eth1`).
- SSID: `Device.WiFi.SSID.{i}` con `Enable=true` (p. ej. 2.4 y 5 GHz).
- Clientes: no hay `Device.Hosts`. Se usan `Device.WiFi.AccessPoint.{i}.AssociatedDevice` (MAC + RSSI).

Si Connection Request es `http://x.x.x.x:0/tr069`, el ACS no puede llamar al CPE: reboot/refresh/cambio de clave esperan el Inform periódico (p. ej. 300 s). En el CPE, poné un puerto CR distinto de 0 si el firmware lo permite.

Cambio de clave WiFi en este modelo: solo `Device.WiFi.AccessPoint.{i}.Security.KeyPassphrase` (AP.1 = 5 GHz, AP.6 = 2.4 GHz). No usar `PreSharedKey`: en TR-181 es un PSK hex de 64 dígitos y el CPE responde 9007. Clave del router: `Device.LANConfigSecurity.ConfigPassword`.
