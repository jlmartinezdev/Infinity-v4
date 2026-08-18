# Recibo Interplus — guía para la app (diseño + API)

Documento para armar en **Interplus Clientes** el mismo ticket que Infinity muestra al copiar imagen del recibo (modo `con_grafico`).

Infinity **no manda un PNG**. Manda JSON. La app pinta el ticket y, si hace falta, lo captura como imagen para compartir.

Base API: `{BASE}/api/v1`  
Auth: `Authorization: Bearer {token_portal}`

---

## 1. Qué tiene que verse

Ticket tipo papel térmico / comprobante: **fondo blanco**, texto oscuro, logo arriba, líneas grises, TOTAL destacado, pie centrado.

```
┌─────────────────────────────────┐
│                                 │
│           [LOGO]                │
│     Direccion / TEL / web       │
│     ───────────────────────     │
│     17/08/2026 14:32            │
│     RECIBO: #001-001-0000140    │
│     ───────────────────────     │
│     CLIENTE: JUAN PEREZ         │
│     CEDULA: 1234567             │
│     DIRECCION:          Calle X │
│     FACTURA INTERNA:            │
│       │ #41254      150.000 PYG │
│       │ PERIODO: 01/08/2026 -   │
│       │          31/08/2026     │
│     ───────────────────────     │
│     TOTAL:          150.000 PYG │
│     SALDO A FAVOR:   (si hay)   │
│     ───────────────────────     │
│     FORMA DE PAGO:     Efectivo │
│     REF:               (si hay) │
│     CAJERO:               Maria │
│     ───────────────────────     │
│        GRACIAS POR SU PAGO      │
│        VALIDO COMO COMPROBANTE  │
│           #001-001-0000140      │
│                                 │
└─────────────────────────────────┘
```

No usar tema oscuro en el ticket (aunque la app esté en dark). Siempre papel blanco.

Textos ya vienen **sin tildes** (ASCII), igual que el recibo impreso: `CEDULA`, `DIRECCION`, `VALIDO`.

---

## 2. Estilo (tokens)

Valores también vienen en `data.estilo`. Si falta algún campo, usar estos defaults.

| Token | Valor | Uso |
|--------|--------|-----|
| Fondo | `#FFFFFF` | Card del recibo |
| Texto | `#111827` | Casi todo |
| Texto muted | `#6B7280` | Contacto, período, número del pie |
| Línea | `#9CA3AF` | Separadores 1 px |
| Borde factura | `#D1D5DB` | Barra izquierda del bloque factura (2–3 dp) |
| Ancho | 320 dp | Card; en pantalla puede ser `match_parent` con max 360 |
| Padding | 16 dp | Interior del ticket |
| Logo alto | 40 dp | Ancho automático, `contain`, centrado |
| Cuerpo | 12 sp | Regular |
| Título empresa | 16 sp | Bold, MAYÚSCULAS, centrado |
| TOTAL | 16 sp | Bold |
| Radio card | 12 dp | Solo en pantalla; al exportar PNG puede ser 0 o 12 |
| Sombra | ninguna al exportar | En pantalla, sombra suave opcional |

Tipografía: sans del sistema (Roboto / Sans). **No** usar mono salvo que el bloque lo pida (este modo no es matricial).

Montos: ya formateados (`150.000 PYG`). No volver a formatear.

Fecha: ya formateada (`17/08/2026 14:32`).

---

## 3. Pantallas

### 3.1 Historial de pagos (tab Pagos)

Lista de cobros del cliente.

`GET /api/v1/portal/cobros?page=1&per_page=20`

Cada ítem:

| Campo | Ejemplo | UI |
|--------|---------|-----|
| `id` | `3857` | Para el detalle |
| `numero_recibo` | `001-001-0000140` | Título o chip |
| `fecha_pago_formato` | `17/08/2026 14:32` | Subtítulo |
| `monto_formato` | `150.000 PYG` | Derecha, destacado |
| `forma_pago_label` | `Efectivo` | Badge chico |
| `concepto` | texto o `null` | Opcional, 1 línea |

Al tocar una fila → detalle con `{id}`.

### 3.2 Detalle / recibo

`GET /api/v1/portal/cobros/{id}`

Pintar **solo** `data.layout` de arriba hacia abajo. No armar el orden a mano: Infinity ya manda los bloques en el orden correcto. Bloques que no apliquen (sin logo, sin dirección, sin saldo a favor) **no vienen**.

Acciones sugeridas:

- **Compartir imagen** — capturar el composable/view del ticket (fondo blanco) → PNG. Nombre: `data.archivo_nombre`.
- **Compartir texto** — `data.compartir_texto` (WhatsApp / copiar).
- **Ver PDF** — abrir `data.pdf_url` (no requiere Bearer).

---

## 4. Cómo pintar `layout[]`

Recorrer el array. Cada ítem tiene `tipo`.

### `logo`

```json
{ "tipo": "logo", "url": "https://…/storage/logo.png" }
```

Imagen centrada, alto 40 dp, `scaleType = fitCenter`. Si falla la carga, mostrar `empresa.nombre` como `titulo`.

### `titulo`

```json
{ "tipo": "titulo", "texto": "INTERPLUS", "align": "center" }
```

Solo llega si **no** hay logo. 16 sp bold, MAYÚSCULAS, centrado.

### `contacto`

```json
{ "tipo": "contacto", "lineas": ["Av. …", "TEL: 021 …", "info@…", "www.…"], "align": "center" }
```

Cada string = una línea. 12 sp, color muted, centrado, interlineado ~1.2.

### `separador`

```json
{ "tipo": "separador" }
```

Línea full-width, 1 px, color `#9CA3AF`. Margin vertical ~8–10 dp.

### `texto`

```json
{ "tipo": "texto", "texto": "CLIENTE: JUAN PEREZ", "bold": true }
```

Una línea, align start. `bold` opcional (default false). Color texto.

### `fila`

```json
{ "tipo": "fila", "izq": "TOTAL:", "der": "150.000 PYG", "destacado": true, "bold": true }
```

| Campo | Notas |
|--------|--------|
| `izq` | Siempre hay |
| `der` | Puede ser `null` → no pintar derecha |
| `bold` | Ambos lados |
| `destacado` | TOTAL: `der` a 16 sp bold |

Layout: `SpaceBetween`, baseline aligned.

### `factura`

```json
{
  "tipo": "factura",
  "id": 41254,
  "izq": "#41254",
  "der": "150.000 PYG",
  "periodo": "PERIODO: 01/08/2026 - 31/08/2026"
}
```

Card interna:

- Padding left 8 dp
- Border left 2–3 dp `#D1D5DB`
- Fila `izq` / `der` (12 sp)
- Debajo `periodo` en muted, 11–12 sp
- Margin bottom ~8 dp entre facturas

### `pie`

```json
{
  "tipo": "pie",
  "lineas": ["GRACIAS POR SU PAGO", "VALIDO COMO COMPROBANTE"],
  "numero": "#001-001-0000140",
  "align": "center"
}
```

- `lineas[0]`: 12 sp bold MAYÚSCULAS, centrado  
- `lineas[1]`: 12 sp MAYÚSCULAS, centrado, ~6 dp abajo  
- `numero`: muted, centrado, ~10 dp abajo  

---

## 5. Ejemplo de respuesta (detalle)

`GET /api/v1/portal/cobros/3857`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "estilo": {
      "version": 1,
      "modo": "con_grafico",
      "fondo": "#FFFFFF",
      "texto": "#111827",
      "texto_muted": "#6B7280",
      "linea": "#9CA3AF",
      "borde_factura": "#D1D5DB",
      "ancho_dp": 320,
      "padding_dp": 16,
      "logo_alto_dp": 40,
      "texto_sp": 12,
      "titulo_sp": 16,
      "total_sp": 16
    },
    "empresa": {
      "nombre": "Interplus",
      "direccion": "…",
      "telefono": "…",
      "email": "…",
      "sitio_web": "…",
      "logo_url": "https://dominio/storage/…"
    },
    "cliente": {
      "cliente_id": 140,
      "nombre": "JUAN",
      "apellido": "PEREZ",
      "nombre_completo": "JUAN PEREZ",
      "cedula": "1234567",
      "direccion": "Calle X"
    },
    "recibo": {
      "id": 3857,
      "numero_recibo": "001-001-0000140",
      "fecha_pago": "2026-08-17T14:32:00-03:00",
      "fecha_pago_formato": "17/08/2026 14:32",
      "monto": 150000,
      "monto_formato": "150.000 PYG",
      "forma_pago": "efectivo",
      "forma_pago_label": "Efectivo",
      "referencia": null,
      "concepto": null,
      "observaciones": null,
      "cajero": "Maria",
      "saldo_a_favor": 0,
      "saldo_a_favor_formato": null
    },
    "facturas": [
      {
        "id": 41254,
        "monto": 150000,
        "monto_formato": "150.000 PYG",
        "periodo_desde": "01/08/2026",
        "periodo_hasta": "31/08/2026",
        "periodo": "PERIODO: 01/08/2026 - 31/08/2026"
      }
    ],
    "layout": [
      { "tipo": "logo", "url": "https://…" },
      { "tipo": "contacto", "lineas": ["…"], "align": "center" },
      { "tipo": "separador" },
      { "tipo": "texto", "texto": "17/08/2026 14:32" },
      { "tipo": "fila", "izq": "RECIBO: #001-001-0000140", "der": null },
      { "tipo": "separador" },
      { "tipo": "texto", "texto": "CLIENTE: JUAN PEREZ", "bold": true },
      { "tipo": "texto", "texto": "CEDULA: 1234567" },
      { "tipo": "fila", "izq": "DIRECCION:", "der": "Calle X" },
      { "tipo": "texto", "texto": "FACTURA INTERNA:", "bold": true },
      {
        "tipo": "factura",
        "id": 41254,
        "izq": "#41254",
        "der": "150.000 PYG",
        "periodo": "PERIODO: 01/08/2026 - 31/08/2026"
      },
      { "tipo": "separador" },
      {
        "tipo": "fila",
        "izq": "TOTAL:",
        "der": "150.000 PYG",
        "destacado": true,
        "bold": true
      },
      { "tipo": "separador" },
      { "tipo": "fila", "izq": "FORMA DE PAGO:", "der": "Efectivo" },
      { "tipo": "fila", "izq": "CAJERO:", "der": "Maria" },
      { "tipo": "separador" },
      {
        "tipo": "pie",
        "lineas": ["GRACIAS POR SU PAGO", "VALIDO COMO COMPROBANTE"],
        "numero": "#001-001-0000140",
        "align": "center"
      }
    ],
    "compartir_texto": "INTERPLUS\n…",
    "pdf_url": "https://dominio/recibo/3857/{token}",
    "archivo_nombre": "recibo-001-001-0000140.png"
  }
}
```

`empresa`, `cliente`, `recibo` y `facturas` son por si hace falta analytics o un rediseño. **La fuente de verdad visual es `layout`.**

---

## 6. Exportar imagen (compartir)

1. Renderizar el ticket en un contenedor de fondo `#FFFFFF` (no recortar sombras ni botones de la app).
2. Capturar ese view (Compose `drawToBitmap` / `View.draw`) a PNG.
3. Share sheet con `archivo_nombre`.
4. Fallback si no se puede imagen: compartir `compartir_texto`.

No incluir en la captura: app bar, botones Compartir/PDF, tab bar.

---

## 7. Errores / vacíos

| Caso | Qué hacer |
|------|-----------|
| 404 | Recibo de otro cliente o id inválido → “Recibo no encontrado” |
| `logo` no carga | Mostrar `empresa.nombre` como título |
| `layout` vacío | No debería pasar; si pasa, no inventar UI |
| `der: null` en `fila` | Solo texto izquierdo |
| `saldo_a_favor_formato: null` | No pintar esa fila (ya no viene en layout) |
| Sin facturas | No hay bloques `factura` |

---

## 8. Checklist diseño

- [ ] Ticket siempre blanco, también en dark mode
- [ ] Recorre `layout[]` en orden; no hardcodear bloques
- [ ] Logo 40 dp centrado; fallback a nombre empresa
- [ ] Separadores 1 px `#9CA3AF`
- [ ] Factura con barra izquierda `#D1D5DB`
- [ ] TOTAL más grande (`destacado`)
- [ ] Pie centrado + número muted
- [ ] Montos y fechas tal cual el JSON (no reformat)
- [ ] Compartir PNG recorta solo el ticket
- [ ] Historial usa `monto_formato` + `fecha_pago_formato` + `forma_pago_label`
