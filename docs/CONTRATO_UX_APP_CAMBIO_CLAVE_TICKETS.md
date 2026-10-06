# Contrato UX — App Interplus Clientes  
## Cambio de claves + historial en Tickets + Hotspot

Documento para **diseñador / producto de la app cliente**.  
No es código: describe pantallas, estados, textos y qué ve el usuario en el historial.

---

## Idea central

Cuando el cliente cambia una clave **desde la app**, Infinity deja un **ticket resuelto** en su cuenta, con el **tema correcto**, fecha y origen “app”. Así el historial de soporte es igual que cualquier otro ticket (solo que ya viene cerrado).

Hay **dos claves distintas** (no mezclar en UI):

| Clave | Qué es | Tema del ticket | Pantalla sugerida |
|-------|--------|-----------------|-------------------|
| **Clave de ingreso a la app** | La que reemplaza `PLUS****` al entrar a Interplus | *Cambio de Contraseña App* | Seguridad / Cuenta |
| **Clave Wi‑Fi del router** | Contraseña de la red de casa (2.4 / 5 GHz) | *Cambio de Contraseña en Router Wifi* | Wi‑Fi / Router |

Ambos tickets quedan:

- Estado: **Resuelto**
- Origen: **App**
- Con **fecha de cierre**
- Visibles en la lista de tickets del cliente (historial)

---

## 1. Feature flags (panel Infinity)

Ops tilda en: `https://infinityisppro.net/loyalty/app-config`

| Flag | Si “Visible” | Si “Pronto” | Si “Oculto” |
|------|--------------|-------------|-------------|
| **Cambio de clave app** | Mostrar flujo Seguridad | Chip Pronto, sin formulario | No mostrar |
| **Slots Hotspot** | Mostrar módulo Hotspot | Chip Pronto | No mostrar |
| **Notificaciones push** (ya existe) | Master de pushes | — | — |

La app debe leer `GET /portal/v1/feature-flags` y no inventar el módulo si no está `enabled`.

---

## 2. Clave de ingreso a la app (`PLUS****` → propia)

### Entrada

- Ajustes → **Seguridad** → “Cambiar clave de ingreso”.
- Si `debe_cambiar_clave_app = true` (le dieron PLUS temporal): banner suave  
  *“Te recomendamos cambiar la clave temporal por una de tu elección.”*

### Formulario

1. Clave actual  
2. Clave nueva (6–64 caracteres)  
3. Confirmar clave nueva  
4. CTA primario: **Guardar**

### Switch (preferencia por cliente)

- Label: **Avisarme cuando se cambie mi clave de ingreso**
- Controla push solo de este evento (`push_cambio_clave`)
- Independiente del permiso de notificaciones del sistema (si el SO bloquea, el switch puede quedar on pero no llega FCM)

### Tras éxito

- Toast / sheet: *“Clave actualizada”*
- Si el switch está on → push: *“Tu contraseña de ingreso a la app se cambió correctamente.”*
- En **Tickets / Historial** aparece un ítem resuelto con tema *Cambio de Contraseña App* y la fecha.

### Errores UX

- Clave actual incorrecta  
- Confirmación no coincide  
- Función desactivada en panel → mensaje amable “Pronto disponible”

---

## 3. Clave Wi‑Fi del router de casa

Misma idea de historial: al cambiar la clave (o el nombre) Wi‑Fi desde la app, se **autogenera un ticket resuelto** con tema *Cambio de Contraseña en Router Wifi*.

### Entrada

- Home / Router → **Wi‑Fi** (solo si `GET /cpe/wifi` dice `can_change` / `can_rename`).

### Formulario

- Ver SSIDs (2.4 / 5)  
- Cambiar **nombre** y/o **clave** (8–63 caracteres)  
- Aviso: *“Vas a tener que volver a conectar los dispositivos a la red.”*

### Tras éxito

- Confirmación en pantalla  
- En **Tickets** aparece el ticket resuelto con el tema Wi‑Fi y la fecha  
- La respuesta API incluye `data.ticket` (`id`, `estado: resuelto`, `asunto`, `fecha_cierre`) por si querés deep-link al detalle del ticket

### No confundir con

- Clave de ingreso a la app (sección 2)  
- PIN de Hotspot (4 dígitos, otra pantalla)

---

## 4. Lista de tickets (historial)

Tratar estos tickets **igual** que el resto:

| Campo UI | Valor típico |
|----------|--------------|
| Tema / asunto | *Cambio de Contraseña App* **o** *Cambio de Contraseña en Router Wifi* |
| Estado | Badge **Resuelto** (verde / neutro) |
| Origen | App (si ya muestran origen) |
| Fecha | Fecha/hora del cambio (= cierre) |
| Descripción | Texto automático del sistema (no editar en app) |

Filtros: si hay “Abiertos / Cerrados”, estos van en **Cerrados / Resueltos**.  
No pedir al usuario que “cierre” nada: ya viene resuelto.

---

## 5. Hotspot (hasta 3 usuarios)

Flag: **Slots Hotspot**.

- Lista de slots (username = documento / documento-2 / documento-3)  
- PIN de **4 dígitos**  
- Crear / cambiar PIN / eliminar  
- Cupo máximo **3**  
- El PIN en claro solo al crear o al regenerar (después, enmascarado)

*(Hotspot no genera ticket de clave Wi‑Fi ni de app; es otro producto.)*

---

## 6. Mapa de pantallas (resumen)

```
Home
├── Seguridad / Cuenta
│   ├── Cambiar clave de ingreso (PLUS → propia)  → ticket "Cambio de Contraseña App"
│   └── Switch: avisarme al cambiar clave ingreso
├── Wi‑Fi / Router
│   └── Cambiar nombre / clave Wi‑Fi              → ticket "Cambio de Contraseña en Router Wifi"
├── Hotspot (si flag)
│   └── Slots 1–3 (PIN 4 dígitos)
└── Tickets / Soporte
    └── Historial (incluye los resueltos de arriba)
```

---

## 7. Copy sugerido (ES)

| Contexto | Texto |
|----------|--------|
| Banner PLUS | Te recomendamos cambiar la clave temporal por una de tu elección. |
| Éxito app | Clave de ingreso actualizada. |
| Push app | Tu contraseña de ingreso a la app se cambió correctamente. |
| Switch | Avisarme cuando se cambie mi clave de ingreso |
| Aviso Wi‑Fi | Los dispositivos van a tener que volver a conectarse a la red. |
| Éxito Wi‑Fi | Wi‑Fi actualizado. |
| Ticket vacío | Todavía no tenés reclamos. Los cambios de clave desde la app también quedan acá como historial. |

---

## 8. Referencias técnicas (dev)

Contrato API completo: [`API_PORTAL_CAMBIO_CLAVE_HOTSPOT.md`](API_PORTAL_CAMBIO_CLAVE_HOTSPOT.md)  
Wi‑Fi CPE: [`INFINITY_CPE_WIFI.md`](INFINITY_CPE_WIFI.md)

Endpoints clave:

- `POST /portal/v1/cambiar-clave` → ticket app  
- `POST /portal/v1/cpe/wifi` → ticket Wi‑Fi (`data.ticket`)  
- `GET /portal/tickets` → historial  
- `GET/PATCH /portal/v1/preferencias` → switch push  
- `GET /portal/v1/feature-flags` → módulos visibles
