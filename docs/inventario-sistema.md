# Infinity ISP v4 — Inventario del sistema

Documento de referencia: menús, vistas y funciones principales del panel web y servicios asociados.

> **Última revisión:** junio 2026  
> **Fuente:** `config/menu.php`, `routes/web.php`, `routes/api.php`, vistas en `resources/views/` y componentes Vue en `resources/js/components/`.

---

## Índice

1. [Menú lateral](#menú-lateral)
2. [Vistas por módulo](#vistas-por-módulo)
3. [Funciones del sistema](#funciones-del-sistema)
4. [Integraciones externas](#integraciones-externas)
5. [API móvil v1](#api-móvil-v1)
6. [Tareas automáticas (Artisan)](#tareas-automáticas-artisan)
7. [Permisos y visibilidad](#permisos-y-visibilidad)

---

## Menú lateral

El menú se define en `config/menu.php` y se filtra por permisos del usuario en `App\Support\MenuUsuario`. Solo aparecen ítems autorizados.

| Sección | Ruta base | Submenús / ítems |
|---------|-----------|------------------|
| **Inicio** | `/` | Dashboard principal con estadísticas |
| **Corte de servicio** *(admin)* | `/admin/corte-servicio` | Suspensión masiva por nodo |
| **Tareas** | `/tareas` | Tablero Kanban |
| **Clientes** | — | Dashboard · Lista clientes · Solicitudes app · Avisos push · Pedidos · Agenda · Mapas de pedidos |
| **WhatsApp** | — | Estado · Mensajes · Contactos · Asuntos · Enviar |
| **Servicios** | — | Lista servicios · Eventos PPPoE MikroTik · Hotspot (dashboard, usuarios, perfiles) |
| **TV streaming** | `/tv-cuentas` | Cuentas TV y asignaciones |
| **Inventario** | — | Productos · Compras · Ventas · Gastos · Proveedores · Categorías producto/gasto |
| **Facturación** | — | Dashboard · Facturas electrónicas · Facturas internas · Notas de crédito · Pendientes · Promesas · Cobros · Rendición |
| **Tickets** | `/tickets` | Gestión de soporte |
| **Usuarios** | — | Personal y clientes · Sesiones activas |
| **Configuración** | `/configuracion` | Impresión · Ajustes · Facturación/servicios · SIFEN · Prueba SIFEN · Tareas periódicas · Backup BD |
| **FTTH** | `/sistema/...` | OLTs · Catálogo OLT · Marcas OLT · Cajas NAP · Mapa óptico · Salidas PON · Líneas de cable |
| **Sistema** | `/sistema/...` | Auditoría · Routers · Catálogo MikroTik · Pools IP · Importar WispHub · MikroTik pendientes |
| **Referenciales** | — | Estados pedidos · Planes · Tipos tecnología · Perfiles PPPoE · Nodos · Roles · Asuntos tickets |
| **Más** | — | Ayuda · Reportes *(placeholders)* |

### Detalle de rutas del menú

#### Clientes
| Ítem | Ruta | Permiso |
|------|------|---------|
| Dashboard | `/clientes/dashboard` | `clientes-dashboard.ver` |
| Lista clientes | `/clientes` | `clientes-lista.ver` |
| Solicitudes app | `/solicitudes-acceso` | `clientes-lista.ver` |
| Avisos push | `/avisos-push` | `clientes-lista.ver` |
| Lista pedidos | `/pedidos` | `clientes-pedidos.ver` |
| Agenda | `/agenda` | `clientes-agenda.ver` |
| Mapas de pedidos | `/clientes/mapas-pedidos` | `clientes-mapa-pedidos.ver` |

#### WhatsApp
| Ítem | Ruta | Permiso |
|------|------|---------|
| Estado | `/whatsapp` | `whatsapp.ver` |
| Mensajes | `/whatsapp/mensajes` | `whatsapp.ver` |
| Contactos | `/whatsapp/contactos` | `whatsapp.ver` |
| Asuntos | `/whatsapp/asuntos` | `whatsapp.ver` |
| Enviar | `/whatsapp/enviar` | `whatsapp.editar` |

#### Servicios
| Ítem | Ruta | Permiso |
|------|------|---------|
| Lista servicios | `/servicios` | `servicios-lista.ver` |
| Eventos PPPoE MikroTik | `/servicios/pppoe-eventos` | `servicios-lista.ver` |
| Hotspot - Clientes activos | `/hotspot/dashboard` | `servicios-hotspot.ver` |
| Usuarios Hotspot | `/hotspot` | `servicios-hotspot-usuarios.ver` |
| Perfiles Hotspot | `/hotspot/perfiles` | `servicios-hotspot-perfiles.ver` |

#### Inventario
| Ítem | Ruta |
|------|------|
| Productos / Equipos | `/productos` |
| Compras | `/compras` |
| Ventas | `/ventas` |
| Gastos | `/gastos` |
| Proveedores | `/proveedores` |
| Categorías producto | `/categorias-producto` |
| Categorías gasto | `/categorias-gasto` |

#### Facturación
| Ítem | Ruta |
|------|------|
| Dashboard | `/facturacion/dashboard` *(admin)* |
| Facturas electrónicas | `/facturas` |
| Facturas internas | `/factura-internas` |
| Notas de crédito | `/factura-internas/notas-credito` |
| Pendiente de pago | `/factura-internas/pendientes` |
| Promesas de pago | `/promesas-pago` |
| Cobros | `/cobros/servicios` |
| Cobros y recibos | `/cobros` |
| Rendición de efectivo | `/cobros/rendiciones` |

#### Configuración
| Ítem | Ruta |
|------|------|
| Impresión | `/configuracion/impresion` |
| Ajustes generales | `/configuracion/ajustes` |
| Facturación y servicios | `/configuracion/facturacion` |
| SIFEN e-Kuatia | `/configuracion/sifen` |
| Prueba SIFEN | `/configuracion/sifen/prueba` |
| Tareas periódicas | `/configuracion/tareas-periodicas` |
| Backup base de datos | `/configuracion/backup-bd` |

#### FTTH
| Ítem | Ruta |
|------|------|
| OLTs | `/sistema/olts` |
| Catálogo OLT | `/sistema/olt-modelos` |
| Marcas OLT | `/sistema/olt-marcas` |
| Cajas NAP | `/sistema/cajas-nap` |
| Mapa infraestructura óptica | `/sistema/cajas-nap/mapa` |
| Salidas PON | `/sistema/salida-pons` |
| Líneas de cable | `/sistema/lineas-cable` |

#### Sistema
| Ítem | Ruta |
|------|------|
| Auditoría | `/sistema/auditoria` |
| Routers | `/sistema/routers` |
| Catálogo MikroTik | `/sistema/router-modelos` |
| Pools de IP | `/sistema/router-ip-pools` |
| Importar WispHub | `/sistema/importar-wisphub` |
| MikroTik pendientes | `/sistema/mikrotik-pendientes` |

#### Referenciales
| Ítem | Ruta |
|------|------|
| Estados de pedidos | `/estados-pedidos` |
| Planes | `/planes` |
| Tipos de tecnologías | `/tipos-tecnologias` |
| Perfiles PPPoE | `/perfiles-pppoe` |
| Nodos | `/nodos` |
| Roles | `/roles` |
| Asuntos de tickets | `/ticket-asuntos` |

---

## Vistas por módulo

### Autenticación
| Vista / componente | Descripción |
|--------------------|-------------|
| `Login.vue`, `LoginPage.vue` | Inicio de sesión |
| `Register.vue`, `RegisterPage.vue` | Registro |
| `layouts/guest.blade.php` | Layout invitado |

### Dashboard
| Ruta | Vista / componente |
|------|-------------------|
| `/` | `Home.vue` — estadísticas y actividad |
| `/inicio` | Panel secundario de accesos rápidos |

### Clientes
| Ruta | Vista / componente |
|------|-------------------|
| `/clientes` | `clientes/index` + `ClientesList.vue` |
| `/clientes/dashboard` | `clientes/dashboard` |
| `/clientes/{id}/detalle` | `clientes/detalle` |
| `/clientes/mapa-activos` | `MapaClientesActivos.vue` |
| `/clientes/importar-csv` | Importación masiva CSV |
| `/solicitudes-acceso` | Solicitudes app móvil |
| `/avisos-push` | Avisos push FCM |

### Pedidos y agenda
| Ruta | Vista / componente |
|------|-------------------|
| `/pedidos` | `PedidosList.vue`, create/edit |
| `/clientes/mapas-pedidos` | `MapasPedidos.vue` |
| `/agenda` | Citas de instalación |

### Servicios e infraestructura de red
| Ruta | Vista / componente |
|------|-------------------|
| `/servicios` | `ServiciosIndex.vue` |
| `/servicios/{id}/edit` | `servicios/edit`, `_form` |
| `/servicios/{id}/herramientas-red` | NOC: ping, MAC, ONU, antena, timeline PPPoE |
| `/servicios/pppoe-eventos` | Eventos PPPoE agrupados por cliente |
| `/hotspot/*` | Hotspot MikroTik |
| `/servicios/{id}/migrar` | Migración de servicio |

### FTTH / Sistema de red
| Ruta | Vista / componente |
|------|-------------------|
| `/sistema/olts` | OLTs, sync ONUs VSOL |
| `/sistema/olts/{olt}/pon/{pon}` | ONUs por puerto PON |
| `/sistema/cajas-nap` | Cajas NAP, splitters, puertos |
| `/sistema/cajas-nap/mapa` | `MapaNap.vue` |
| `/sistema/routers` | Routers MikroTik |
| `/sistema/router-ip-pools` | Pools de IP |
| `/sistema/pool-ip-asignadas` | IPs asignadas |
| `/sistema/auditoria` | Log de auditoría |
| `/sistema/mikrotik-pendientes` | Cola de operaciones MikroTik |
| `/sistema/importar-wisphub` | Importación WispHub |

### Facturación
| Ruta | Vista / componente |
|------|-------------------|
| `/facturacion/dashboard` | Dashboard facturación |
| `/facturacion/cobros-saldo-favor` | Cobros con saldo a favor |
| `/facturas` | Facturas electrónicas SIFEN |
| `/facturas/{id}` | Detalle, KUDE PDF/POS, XML |
| `/factura-internas` | `FacturasInternasIndex.vue` |
| `/factura-internas/pendientes` | `PendientesPago.vue` |
| `/factura-internas/notas-credito` | `NotasCreditoIndex.vue` |
| `/cobros` | Cobros y recibos |
| `/cobros/servicios` | `CobrosServiciosList.vue` |
| `/cobros/rendiciones` | Rendición de efectivo |
| `/promesas-pago` | Promesas de pago |

### Soporte y operaciones
| Ruta | Vista / componente |
|------|-------------------|
| `/tickets` | Tickets de soporte |
| `/tareas` | `TareasKanban.vue` |
| `/usuarios` | `UsuarioManagement.vue` |
| `/usuarios/sesiones` | Sesiones activas |

### WhatsApp
| Ruta | Vista / componente |
|------|-------------------|
| `/whatsapp` | Estado de conexión |
| `/whatsapp/mensajes` | `WhatsAppChat.vue` |
| `/whatsapp/contactos` | Contactos |
| `/whatsapp/asuntos` | Asuntos de conversación |
| `/whatsapp/enviar` | Envío manual |

### TV streaming
| Ruta | Vista / componente |
|------|-------------------|
| `/tv-cuentas/dashboard` | Dashboard TV |
| `/tv-cuentas` | Listado y gestión de cuentas |

### Inventario
| Ruta | Módulo |
|------|--------|
| `/productos` | Productos y equipos |
| `/compras` | Compras (+ `ComprasCreatePanel.vue`) |
| `/ventas` | Ventas |
| `/gastos` | Gastos |
| `/proveedores` | Proveedores |
| `/categorias-producto` | Categorías de producto |
| `/categorias-gasto` | Categorías de gasto |

### Configuración y admin
| Ruta | Descripción |
|------|-------------|
| `/configuracion` | Índice de configuración |
| `/configuracion/*` | Impresión, ajustes, SIFEN, backup, etc. |
| `/admin/corte-servicio` | Corte masivo *(admin)* |
| `/notificaciones` | Notificaciones internas *(admin)* |

---

## Funciones del sistema

### Gestión comercial
- CRUD de clientes (padrón, RUC, saldo a favor, calificación de pago)
- Pedidos con flujo de estados, aprobación y finalización → alta de servicio
- Agenda de instalaciones vinculada a pedidos/tickets
- Planes, nodos y tipos de tecnología

### Servicios de internet
- Alta, edición, activación, suspensión, cancelación y baja
- Asignación de IP desde pools del router
- Sincronización PPPoE con MikroTik
- Migración de servicio entre nodos/pools
- Registro de eventos: PPPoE up/down, señal óptica ONU, señal antena
- **Herramientas de red (NOC)** por servicio:
  - Ping ICMP desde el servidor
  - Consulta MAC / tráfico sesión PPPoE (MikroTik)
  - Señal ONU vía OLT (RX/TX, PON, descripción)
  - Señal antena Ubiquiti vía SSH (`wstalist`, noise, SNR, CCQ)
  - DHCP leases del CPE (`/tmp/dhcpd.leases`)
  - Timeline PPPoE últimas 12 horas
  - Historial de eventos de conexión y señal
- Hotspot MikroTik asociado a servicios
- Monitoreo ping de servicios activos

### FTTH
- Gestión de OLT (credenciales, sync ONUs, panel por PON)
- Infraestructura: cajas NAP, splitters primarios/secundarios, salidas PON, líneas de cable
- Mapa de infraestructura óptica
- Asignación de puertos NAP a servicios

### Facturación (Paraguay)
- Facturas electrónicas vía **SIFEN e-Kuatia** (XML, firma digital, lote, KUDE, QR)
- Facturas internas (automáticas y manuales)
- Notas de crédito internas
- Cobros simples y multicobro
- Rendición de efectivo entre cobradores
- Promesas de pago con vencimiento
- Suspensión automática por falta de pago
- Dashboard de facturación y resumen de cobros

### Soporte
- Tickets con estados, asuntos configurables y facturación desde ticket
- Enlace a herramientas de red desde tickets
- Tablero Kanban de tareas internas

### Comunicación
- **WhatsApp Cloud API**: webhook, conversaciones, envío, reintentos, asuntos
- **FCM**: avisos push a app cliente y staff
- Portal app: solicitudes de acceso, aprobación/rechazo, reenvío de claves

### TV streaming
- Cuentas compartidas (hasta 3 clientes/dispositivos por cuenta)
- Renovación, asignaciones y avisos de vencimiento

### Inventario
- Control de stock, compras, ventas, gastos
- Proveedores y categorías
- Registro de pagos a compras/gastos

### Sistema y seguridad
- Usuarios staff y cliente con roles y permisos granulares
- Gestión de sesiones activas
- Auditoría de acciones
- Cola de operaciones MikroTik pendientes (reintentos automáticos)
- Importación desde WispHub
- Backup de base de datos (MySQL/MariaDB o SQLite)
- Tareas periódicas configurables desde el panel

---

## Integraciones externas

| Integración | Uso principal |
|-------------|---------------|
| **MikroTik RouterOS** | PPPoE, MAC, tráfico, hotspot, webhooks, sync perfiles |
| **OLT VSOL** | Consulta ONUs, señal RX/TX, descripción, sync tabla GPON |
| **Ubiquiti (SSH)** | Señal wireless (`wstalist`), DHCP leases CPE |
| **SIFEN / e-Kuatia** | Facturación electrónica Paraguay |
| **WhatsApp Cloud API** | Mensajería con clientes |
| **FCM (Firebase)** | Notificaciones push app móvil |
| **WispHub** | Importación de datos legacy |

---

## API móvil v1

Base: `/api/v1` — autenticación Bearer (Laravel Sanctum).

### Público / webhooks
- `POST /login` — staff (email) o cliente (documento + clave)
- `POST /portal/solicitud-alta` — solicitud de acceso app
- `POST /webhooks/mikrotik/pppoe` — eventos PPPoE desde MikroTik
- `GET|POST /webhooks/whatsapp` — webhook WhatsApp Cloud API

### Portal cliente (`auth:sanctum` + `api.cliente`)
- Resumen de cuenta, facturas, cobros, tickets
- Crear tickets, asuntos, guardar token push

### Staff (`auth:sanctum` + `api.staff`)
- Dashboard stats, solicitudes de acceso, clientes
- Cobros, servicios, tareas, tickets
- Auditoría *(admin)*

Documentación adicional: [`docs/api-v1-movil.md`](api-v1-movil.md)

---

## Tareas automáticas (Artisan)

Comandos programables vía cron o tareas periódicas del panel:

| Comando | Función |
|---------|---------|
| `CrearFacturasInternasAutomaticasCommand` | Generación mensual de facturas internas |
| `ServiciosCorteAutomaticoCommand` | Corte/suspensión por falta de pago |
| `SyncMikroTikPppoeCommand` | Sync masivo PPPoE |
| `ProcesarMikrotikPendientesCommand` | Reintento cola MikroTik |
| `ProcesarPromesasVencidasCommand` | Promesas de pago vencidas |
| `MonitoreoPingServiciosCommand` / `MonitoreoPingDaemonCommand` | Ping a servicios activos |
| `SifenPrepararDeCommand` / `SifenEmitirDeCommand` | Flujo SIFEN |
| `ImportarOltOnusCommand` | Sync ONUs desde OLT |
| `WhatsAppEnviarCommand` / `WhatsAppEstadoCommand` | Cola WhatsApp |
| `FcmAvisarFacturasPorVencerCommand` | Push facturas por vencer |
| `TvAvisarVencimientosCommand` | Avisos vencimiento TV |
| `PortalAvisarAccesoAprobadoCommand` | Notificación acceso app aprobado |
| `ImportarWispHubClientesCommand` | Importación WispHub |
| Varios `Auditar*` | Auditorías de cobros, facturas, saldos |

---

## Permisos y visibilidad

- El menú lateral se construye en `App\Support\MenuUsuario::itemsFiltrados()`.
- Cada ítem puede tener `permiso`, `admin_only` o ambos.
- Los permisos disponibles para asignar a roles se derivan de `config/menu.php`.
- Algunas rutas web usan middleware `permiso:*` directamente en `routes/web.php`.
- Vistas mixtas: **Blade** tradicional + **Vue 3** embebido en layouts Blade (`layouts/app.blade.php` + `Sidebar.vue`).

---

## Archivos de referencia

| Archivo | Contenido |
|---------|-----------|
| `config/menu.php` | Definición del menú lateral |
| `routes/web.php` | Rutas web del panel |
| `routes/api.php` | API móvil v1 |
| `app/Support/MenuUsuario.php` | Filtrado de menú por permisos |
| `resources/js/components/Sidebar.vue` | Render del menú |
| `docs/api-v1-movil.md` | API móvil |
| `docs/mikrotik-webhook-pppoe.md` | Webhook PPPoE MikroTik |
