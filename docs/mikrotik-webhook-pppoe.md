# Webhook MikroTik → Infinity (PPPoE up/down)

Cuando un cliente PPPoE se conecta o desconecta, el MikroTik puede avisar al sistema para registrar el historial.

## 1. En Infinity

1. Ir a **Sistema → Routers → Editar** el router.
2. Marcar **Generar nuevo token al guardar** y guardar.
3. Copiar el **Token webhook PPPoE**.

## 2. Endpoint

```http
POST /api/v1/webhooks/mikrotik/pppoe
Authorization: Bearer {webhook_token}
Content-Type: application/x-www-form-urlencoded
```

Parámetros:

| Campo | Valores |
|-------|---------|
| `evento` | `up` o `down` |
| `usuario` | usuario PPPoE |
| `ip` | IP remota (opcional) |
| `mac` | caller-id (opcional) |
| `uptime` | uptime (opcional, en down a veces vacío) |

Ejemplo local XAMPP:

`http://TU-IP-SERVIDOR/infinity-v4/public/api/v1/webhooks/mikrotik/pppoe`

> El MikroTik debe poder alcanzar esa URL (misma LAN o IP pública). `localhost` desde el router **no** apunta al PC.

## 3. Scripts en MikroTik (RouterOS)

Crear dos scripts y asignarlos al profile PPP (o al profile default).

### Script `infinity-pppoe-up`

```routeros
# Reemplazar TOKEN y URL
:local token "PEGAR_TOKEN_AQUI"
:local url "http://TU-SERVIDOR/infinity-v4/public/api/v1/webhooks/mikrotik/pppoe"
:local u $"user"
:local ip $"remote-address"
:local mac $"caller-id"
:local data ("evento=up&usuario=" . $u . "&ip=" . $ip . "&mac=" . $mac)
/tool fetch url=$url http-method=post http-header-field=("Authorization: Bearer " . $token . ",Content-Type: application/x-www-form-urlencoded") http-data=$data keep-result=no
```

### Script `infinity-pppoe-down`

```routeros
:local token "PEGAR_TOKEN_AQUI"
:local url "http://TU-SERVIDOR/infinity-v4/public/api/v1/webhooks/mikrotik/pppoe"
:local u $"user"
:local ip $"remote-address"
:local mac $"caller-id"
:local data ("evento=down&usuario=" . $u . "&ip=" . $ip . "&mac=" . $mac)
/tool fetch url=$url http-method=post http-header-field=("Authorization: Bearer " . $token . ",Content-Type: application/x-www-form-urlencoded") http-data=$data keep-result=no
```

### Asignar al profile PPP

Winbox → **PPP → Profiles** → editar el profile que usan los clientes:

- **On Up:** `infinity-pppoe-up`
- **On Down:** `infinity-pppoe-down`

Por consola:

```routeros
/ppp profile set [find name="default"] on-up=infinity-pppoe-up on-down=infinity-pppoe-down
```

(Ajustá el `name` del profile real.)

## 4. Probar

Desde una PC (no desde el MikroTik):

```bash
curl -X POST "http://localhost/infinity-v4/public/api/v1/webhooks/mikrotik/pppoe" ^
  -H "Authorization: Bearer TOKEN" ^
  -H "Content-Type: application/x-www-form-urlencoded" ^
  -d "evento=up&usuario=PEDRO_CIBILS&ip=10.0.0.10&mac=AA:BB:CC:DD:EE:FF"
```

Luego en **Servicios → Herramientas de red** debería aparecer el evento en el historial.

## Notas

- Solo se guarda si el estado **cambia** (evita duplicados).
- El usuario PPPoE debe existir en un servicio del sistema.
- Si el router no tiene DNS/acceso a internet hacia el servidor, el `fetch` fallará en silencio: revisá Log del MikroTik.
