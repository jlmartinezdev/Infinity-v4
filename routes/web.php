<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaNapController;
use App\Http\Controllers\CategoriaGastoController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteDashboardController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\CobroRendicionController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\SifenConfiguracionController;
use App\Http\Controllers\SifenPruebaController;
use App\Http\Controllers\CorteServicioController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\EstadoPedidoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FacturacionDashboardController;
use App\Http\Controllers\FacturaInternaController;
use App\Http\Controllers\FacturaInternaNotaCreditoController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\HotspotPerfilController;
use App\Http\Controllers\ImportarCsvClientesController;
use App\Http\Controllers\LineaCableController;
use App\Http\Controllers\MikrotikPendienteController;
use App\Http\Controllers\NodoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\OltMarcaController;
use App\Http\Controllers\OltModeloController;
use App\Http\Controllers\OltPuertoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PerfilPppoeController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PoolIpAsignadaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromesaPagoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\RouterIpPoolController;
use App\Http\Controllers\RouterModeloController;
use App\Http\Controllers\SalidaPonController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ServicioPppoeEventoController;
use App\Http\Controllers\SolicitudAccesoWebController;
use App\Http\Controllers\AvisoPushWebController;
use App\Http\Controllers\WhatsAppAsuntoController;
use App\Http\Controllers\WhatsAppWebController;
use App\Http\Controllers\SplitterPrimarioController;
use App\Http\Controllers\SplitterSecundarioController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\TareaPeriodicaController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketAsuntoController;
use App\Http\Controllers\TipoTecnologiaController;
use App\Http\Controllers\TvCuentaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\UsuarioSesionController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\WispHubImportController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

// Rutas API de autenticación (usando web para sesiones)
Route::prefix('api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth');
    Route::get('/dashboard/stats', [HomeController::class, 'stats'])->middleware(['auth', 'permiso:dashboard.ver']);
});

// Notificaciones (solo administradores)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'markAsRead'])->name('notificaciones.leer');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'markAllAsRead'])->name('notificaciones.leer-todas');
});

// Panel secundario (solo enlaces, sin datos): cualquier usuario autenticado
Route::get('/inicio', [HomeController::class, 'inicio'])->middleware('auth')->name('inicio');

// Dashboard principal: estadísticas y actividad
Route::get('/', [HomeController::class, 'index'])->middleware(['auth', 'permiso:dashboard.ver'])->name('home');

// Dashboard de tareas (Kanban estilo Trello)
Route::middleware(['auth', 'permiso:tareas.ver'])->group(function () {
    Route::get('/tareas', [TareaController::class, 'index'])->name('tareas.index');
});
Route::middleware(['auth', 'permiso:tareas.crear'])->group(function () {
    Route::post('/tareas', [TareaController::class, 'store'])->name('tareas.store');
    Route::put('/tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.update');
    Route::post('/tareas/{tarea}/move', [TareaController::class, 'move'])->name('tareas.move');
});
Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy')->middleware(['auth', 'permiso:tareas.eliminar']);

// Configuración: índice e impresión para cualquier usuario autenticado; el resto requiere configuracion.ver
Route::middleware('auth')->group(function () {
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::get('/configuracion/impresion', [ConfiguracionController::class, 'impresion'])->name('configuracion.impresion');
});

Route::middleware(['auth', 'permiso:configuracion.ver'])->group(function () {
    Route::get('/configuracion/ajustes', [ConfiguracionController::class, 'ajustes'])->name('configuracion.ajustes');
    Route::post('/configuracion/ajustes', [ConfiguracionController::class, 'storeAjustes'])->name('configuracion.ajustes.store');
    Route::get('/configuracion/facturacion', [ConfiguracionController::class, 'facturacion'])->name('configuracion.facturacion');
    Route::post('/configuracion/facturacion', [ConfiguracionController::class, 'storeFacturacion'])->name('configuracion.facturacion.store');
    Route::get('/configuracion/sifen', [SifenConfiguracionController::class, 'edit'])->name('configuracion.sifen');
    Route::post('/configuracion/sifen', [SifenConfiguracionController::class, 'update'])->name('configuracion.sifen.store');
    Route::get('/configuracion/sifen/prueba', [SifenPruebaController::class, 'index'])->name('configuracion.sifen.prueba');
    Route::post('/configuracion/sifen/prueba/factura', [SifenPruebaController::class, 'crearFactura'])->name('configuracion.sifen.prueba.factura');
    Route::post('/configuracion/sifen/prueba/{factura}/preparar', [SifenPruebaController::class, 'preparar'])->name('configuracion.sifen.prueba.preparar');
    Route::post('/configuracion/sifen/prueba/{factura}/emitir-local', [SifenPruebaController::class, 'emitirLocal'])->name('configuracion.sifen.prueba.emitir-local');
    Route::post('/configuracion/sifen/prueba/tls', [SifenPruebaController::class, 'probarTls'])->name('configuracion.sifen.prueba.tls');
    Route::post('/configuracion/sifen/prueba/api', [SifenPruebaController::class, 'probarApi'])->name('configuracion.sifen.prueba.api');
    Route::post('/configuracion/sifen/prueba/api/tls', [SifenPruebaController::class, 'probarApiTls'])->name('configuracion.sifen.prueba.api.tls');
    Route::post('/configuracion/sifen/prueba/{factura}/emitir', [SifenPruebaController::class, 'emitir'])->name('configuracion.sifen.prueba.emitir');
    Route::post('/configuracion/sifen/prueba/limpiar', [SifenPruebaController::class, 'limpiarPruebas'])->name('configuracion.sifen.prueba.limpiar');
    Route::get('/configuracion/tareas-periodicas', [TareaPeriodicaController::class, 'index'])->name('tareas-periodicas.index');
    Route::get('/configuracion/tareas-periodicas/create', [TareaPeriodicaController::class, 'create'])->name('tareas-periodicas.create');
    Route::post('/configuracion/tareas-periodicas', [TareaPeriodicaController::class, 'store'])->name('tareas-periodicas.store');
    Route::get('/configuracion/tareas-periodicas/{tareaPeriodica}/edit', [TareaPeriodicaController::class, 'edit'])->name('tareas-periodicas.edit');
    Route::put('/configuracion/tareas-periodicas/{tareaPeriodica}', [TareaPeriodicaController::class, 'update'])->name('tareas-periodicas.update');
    Route::delete('/configuracion/tareas-periodicas/{tareaPeriodica}', [TareaPeriodicaController::class, 'destroy'])->name('tareas-periodicas.destroy');
    Route::get('/configuracion/backup-bd', [DatabaseBackupController::class, 'index'])->name('configuracion.backup');
    Route::post('/configuracion/backup-bd/descargar', [DatabaseBackupController::class, 'download'])->name('configuracion.backup.download');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/corte-servicio', [CorteServicioController::class, 'index'])->name('admin.corte-servicio.index');
    Route::post('/corte-servicio/todos', [CorteServicioController::class, 'ejecutarTodos'])->name('admin.corte-servicio.todos');
    Route::post('/corte-servicio/nodo', [CorteServicioController::class, 'ejecutarNodo'])->name('admin.corte-servicio.nodo');
});

// Clientes (CRUD)
Route::middleware(['auth', 'permiso:clientes.ver'])->group(function () {
    Route::get('/clientes/dashboard', [ClienteDashboardController::class, 'index'])->name('clientes.dashboard');
    Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
    Route::get('/clientes/buscar-temp', [ClienteController::class, 'buscarTemp'])->name('clientes.buscar-temp');
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/mapa-activos', [ClienteController::class, 'mapaActivos'])->name('clientes.mapa-activos');
    Route::get('/clientes/mapa-activos/ping-estados', [ClienteController::class, 'mapaActivosPingEstados'])->name('clientes.mapa-activos.ping-estados');
    Route::post('/clientes/mapa-activos/ejecutar-ping', [ClienteController::class, 'mapaActivosEjecutarPing'])->name('clientes.mapa-activos.ejecutar-ping');
    Route::get('/clientes/{cliente}/detalle', [ClienteController::class, 'detalle'])->name('clientes.detalle');
    Route::put('/clientes/{cliente}/saldo-a-favor', [ClienteController::class, 'actualizarSaldoAFavor'])->name('clientes.actualizar-saldo-a-favor');
    Route::get('/clientes/{cliente}/acciones', [ClienteController::class, 'acciones'])->name('clientes.acciones');
    Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create')->middleware('permiso:clientes.crear');
});
Route::middleware(['auth', 'permiso:clientes.crear'])->group(function () {
    Route::post('/clientes/verificar-cedula', [ClienteController::class, 'verificarCedula'])->name('clientes.verificar-cedula');
    Route::post('/clientes/crear-desde-padron', [ClienteController::class, 'crearDesdePadron'])->name('clientes.crear-desde-padron');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/importar-csv', [ImportarCsvClientesController::class, 'index'])->name('clientes.importar-csv.index');
    Route::post('/clientes/importar-csv', [ImportarCsvClientesController::class, 'store'])->name('clientes.importar-csv.store');
});
Route::middleware(['auth', 'permiso:clientes.editar'])->group(function () {
    Route::get('/clientes/{cliente}/edit', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::post('/clientes/{cliente}/actualizar-desde-temp', [ClienteController::class, 'actualizarDesdeTemp'])->name('clientes.actualizar-desde-temp');
    Route::post('/clientes/{cliente}/consultar-ruc', [ClienteController::class, 'consultarRuc'])->name('clientes.consultar-ruc');
});
Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy')->middleware(['auth', 'permiso:clientes.eliminar']);
Route::get('/clientes/mapas-pedidos', [PedidoController::class, 'mapasPedidos'])->name('clientes.mapas-pedidos')->middleware(['auth', 'permiso:pedidos.ver']);

// Solicitudes de acceso (App móvil)
Route::middleware(['auth', 'permiso:clientes.ver'])->group(function () {
    Route::get('/solicitudes-acceso', [SolicitudAccesoWebController::class, 'index'])->name('solicitudes-acceso.index');
    Route::get('/solicitudes-acceso/{solicitud}', [SolicitudAccesoWebController::class, 'show'])->name('solicitudes-acceso.show');
});
Route::middleware(['auth', 'permiso:clientes.editar'])->group(function () {
    Route::post('/solicitudes-acceso/{solicitud}/aprobar', [SolicitudAccesoWebController::class, 'aprobar'])->name('solicitudes-acceso.aprobar');
    Route::post('/solicitudes-acceso/{solicitud}/rechazar', [SolicitudAccesoWebController::class, 'rechazar'])->name('solicitudes-acceso.rechazar');
    Route::post('/solicitudes-acceso/{solicitud}/reenviar-clave', [SolicitudAccesoWebController::class, 'reenviarClave'])->name('solicitudes-acceso.reenviar-clave');
});

// WhatsApp Cloud API (panel)
Route::middleware(['auth', 'permiso:whatsapp.ver'])->group(function () {
    Route::get('/whatsapp', [WhatsAppWebController::class, 'index'])->name('whatsapp.index');
    Route::get('/whatsapp/mensajes', [WhatsAppWebController::class, 'mensajes'])->name('whatsapp.mensajes');
    Route::get('/whatsapp/conversaciones', [WhatsAppWebController::class, 'conversacionesJson'])->name('whatsapp.conversaciones');
    Route::get('/whatsapp/hilo', [WhatsAppWebController::class, 'hiloJson'])->name('whatsapp.hilo');
    Route::get('/whatsapp/mensajes/{mensaje}/media', [WhatsAppWebController::class, 'media'])->name('whatsapp.media');
    Route::get('/whatsapp/contactos', [WhatsAppWebController::class, 'contactos'])->name('whatsapp.contactos');
    Route::get('/whatsapp/asuntos', [WhatsAppAsuntoController::class, 'index'])->name('whatsapp.asuntos.index');
    Route::get('/whatsapp/asuntos-json', [WhatsAppWebController::class, 'asuntosJson'])->name('whatsapp.asuntos.json');
});
Route::middleware(['auth', 'permiso:whatsapp.editar'])->group(function () {
    Route::get('/whatsapp/enviar', [WhatsAppWebController::class, 'enviarForm'])->name('whatsapp.enviar');
    Route::post('/whatsapp/enviar', [WhatsAppWebController::class, 'enviar'])->name('whatsapp.enviar.store');
    Route::post('/whatsapp/mensajes/{mensaje}/reintentar', [WhatsAppWebController::class, 'reintentar'])->name('whatsapp.reintentar');
    Route::post('/whatsapp/marcar-leidos', [WhatsAppWebController::class, 'marcarLeidos'])->name('whatsapp.marcar-leidos');
    Route::post('/whatsapp/conversacion/asunto', [WhatsAppWebController::class, 'asignarAsunto'])->name('whatsapp.asignar-asunto');
    Route::get('/whatsapp/asuntos/create', [WhatsAppAsuntoController::class, 'create'])->name('whatsapp.asuntos.create');
    Route::post('/whatsapp/asuntos', [WhatsAppAsuntoController::class, 'store'])->name('whatsapp.asuntos.store');
    Route::get('/whatsapp/asuntos/{asunto}/edit', [WhatsAppAsuntoController::class, 'edit'])->name('whatsapp.asuntos.edit');
    Route::put('/whatsapp/asuntos/{asunto}', [WhatsAppAsuntoController::class, 'update'])->name('whatsapp.asuntos.update');
    Route::delete('/whatsapp/asuntos/{asunto}', [WhatsAppAsuntoController::class, 'destroy'])->name('whatsapp.asuntos.destroy');
});

// Avisos push voluntarios a app cliente
Route::middleware(['auth', 'permiso:clientes.ver'])->group(function () {
    Route::get('/avisos-push', [AvisoPushWebController::class, 'index'])->name('avisos-push.index');
    Route::get('/avisos-push/buscar', [AvisoPushWebController::class, 'buscar'])->name('avisos-push.buscar');
});
Route::middleware(['auth', 'permiso:clientes.editar'])->group(function () {
    Route::post('/avisos-push', [AvisoPushWebController::class, 'store'])->name('avisos-push.store');
    Route::post('/avisos-push/{aviso}/reenviar', [AvisoPushWebController::class, 'reenviar'])->name('avisos-push.reenviar');
    Route::delete('/avisos-push/{aviso}', [AvisoPushWebController::class, 'destroy'])->name('avisos-push.destroy');
});

// Gestión avanzada de acceso app (solo Administrador) — sobre solicitudes de la app
Route::middleware(['auth', 'admin'])->group(function () {
    Route::put('/solicitudes-acceso/{solicitud}/acceso', [SolicitudAccesoWebController::class, 'actualizarAcceso'])->name('solicitudes-acceso.actualizar-acceso');
    Route::post('/solicitudes-acceso/{solicitud}/suspender-acceso', [SolicitudAccesoWebController::class, 'suspenderAcceso'])->name('solicitudes-acceso.suspender-acceso');
    Route::post('/solicitudes-acceso/{solicitud}/reactivar-acceso', [SolicitudAccesoWebController::class, 'reactivarAcceso'])->name('solicitudes-acceso.reactivar-acceso');
    Route::delete('/solicitudes-acceso/{solicitud}/acceso', [SolicitudAccesoWebController::class, 'eliminarAcceso'])->name('solicitudes-acceso.eliminar-acceso');
    Route::delete('/solicitudes-acceso/{solicitud}', [SolicitudAccesoWebController::class, 'destroy'])->name('solicitudes-acceso.destroy');
});

// Estados de pedidos, tipos tecnologías, perfiles PPPoE, nodos, roles (Referenciales)
Route::middleware(['auth', 'permiso:referenciales.ver'])->group(function () {
    Route::get('/estados-pedidos', [EstadoPedidoController::class, 'index'])->name('estados-pedidos.index');
    Route::get('/estados-pedidos/create', [EstadoPedidoController::class, 'create'])->name('estados-pedidos.create')->middleware('permiso:referenciales.editar');
    Route::get('/tipos-tecnologias', [TipoTecnologiaController::class, 'index'])->name('tipos-tecnologias.index');
    Route::get('/tipos-tecnologias/create', [TipoTecnologiaController::class, 'create'])->name('tipos-tecnologias.create')->middleware('permiso:referenciales.editar');
    Route::get('/perfiles-pppoe', [PerfilPppoeController::class, 'index'])->name('perfiles-pppoe.index');
    Route::get('/perfiles-pppoe/create', [PerfilPppoeController::class, 'create'])->name('perfiles-pppoe.create')->middleware('permiso:referenciales.editar');
    Route::get('/nodos', [NodoController::class, 'index'])->name('nodos.index');
    Route::get('/nodos/create', [NodoController::class, 'create'])->name('nodos.create')->middleware('permiso:referenciales.editar');
    Route::get('/nodos/{nodo}/migrar-pppoe', [NodoController::class, 'migrarPppoe'])->name('nodos.migrar-pppoe');
    Route::get('/nodos/{nodo}/migrar-pppoe/datos', [NodoController::class, 'migrarPppoeDatos'])->name('nodos.migrar-pppoe.datos');
    Route::get('/nodos/{nodo}/migrar-pppoe/servicios', [NodoController::class, 'migrarPppoeServicios'])->name('nodos.migrar-pppoe.servicios');
    Route::get('/nodos/{nodo}/migrar-pppoe/pools', [NodoController::class, 'migrarPppoePools'])->name('nodos.migrar-pppoe.pools');
    Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RolController::class, 'create'])->name('roles.create')->middleware('permiso:referenciales.editar');
    Route::get('/ticket-asuntos', [TicketAsuntoController::class, 'index'])->name('ticket-asuntos.index');
    Route::get('/ticket-asuntos/create', [TicketAsuntoController::class, 'create'])->name('ticket-asuntos.create')->middleware('permiso:referenciales.editar');
});
Route::middleware(['auth', 'permiso:referenciales.editar'])->group(function () {
    Route::post('/estados-pedidos', [EstadoPedidoController::class, 'store'])->name('estados-pedidos.store');
    Route::get('/estados-pedidos/{estado_pedido}/edit', [EstadoPedidoController::class, 'edit'])->name('estados-pedidos.edit');
    Route::put('/estados-pedidos/{estado_pedido}', [EstadoPedidoController::class, 'update'])->name('estados-pedidos.update');
    Route::delete('/estados-pedidos/{estado_pedido}', [EstadoPedidoController::class, 'destroy'])->name('estados-pedidos.destroy');
    Route::post('/tipos-tecnologias', [TipoTecnologiaController::class, 'store'])->name('tipos-tecnologias.store');
    Route::get('/tipos-tecnologias/{tipo_tecnologia}/edit', [TipoTecnologiaController::class, 'edit'])->name('tipos-tecnologias.edit');
    Route::put('/tipos-tecnologias/{tipo_tecnologia}', [TipoTecnologiaController::class, 'update'])->name('tipos-tecnologias.update');
    Route::delete('/tipos-tecnologias/{tipo_tecnologia}', [TipoTecnologiaController::class, 'destroy'])->name('tipos-tecnologias.destroy');
    Route::post('/perfiles-pppoe', [PerfilPppoeController::class, 'store'])->name('perfiles-pppoe.store');
    Route::post('/perfiles-pppoe/sync-mikrotik', [PerfilPppoeController::class, 'syncMikrotik'])->name('perfiles-pppoe.sync-mikrotik');
    Route::get('/perfiles-pppoe/{perfil_pppoe}/edit', [PerfilPppoeController::class, 'edit'])->name('perfiles-pppoe.edit');
    Route::put('/perfiles-pppoe/{perfil_pppoe}', [PerfilPppoeController::class, 'update'])->name('perfiles-pppoe.update');
    Route::delete('/perfiles-pppoe/{perfil_pppoe}', [PerfilPppoeController::class, 'destroy'])->name('perfiles-pppoe.destroy');
    Route::post('/nodos', [NodoController::class, 'store'])->name('nodos.store');
    Route::post('/nodos/{nodo}/migrar-pppoe', [NodoController::class, 'migrarPppoeEjecutar'])->name('nodos.migrar-pppoe.ejecutar');
    Route::get('/nodos/{nodo}/edit', [NodoController::class, 'edit'])->name('nodos.edit');
    Route::put('/nodos/{nodo}', [NodoController::class, 'update'])->name('nodos.update');
    Route::delete('/nodos/{nodo}', [NodoController::class, 'destroy'])->name('nodos.destroy');
    Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
    Route::get('/roles/{rol}/edit', [RolController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{rol}', [RolController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy');
    Route::post('/ticket-asuntos', [TicketAsuntoController::class, 'store'])->name('ticket-asuntos.store');
    Route::get('/ticket-asuntos/{ticket_asunto}/edit', [TicketAsuntoController::class, 'edit'])->name('ticket-asuntos.edit');
    Route::put('/ticket-asuntos/{ticket_asunto}', [TicketAsuntoController::class, 'update'])->name('ticket-asuntos.update');
    Route::delete('/ticket-asuntos/{ticket_asunto}', [TicketAsuntoController::class, 'destroy'])->name('ticket-asuntos.destroy');
});

// Planes (CRUD)
Route::middleware(['auth', 'permiso:planes.ver'])->group(function () {
    Route::get('/planes', [PlanController::class, 'index'])->name('planes.index');
    Route::get('/planes/create', [PlanController::class, 'create'])->name('planes.create')->middleware('permiso:planes.crear');
});
Route::middleware(['auth', 'permiso:planes.crear'])->group(function () {
    Route::post('/planes', [PlanController::class, 'store'])->name('planes.store');
});
Route::middleware(['auth', 'permiso:planes.crear'])->group(function () {
    Route::get('/planes/{plan}/edit', [PlanController::class, 'edit'])->name('planes.edit');
    Route::put('/planes/{plan}', [PlanController::class, 'update'])->name('planes.update');
});
Route::delete('/planes/{plan}', [PlanController::class, 'destroy'])->name('planes.destroy')->middleware(['auth', 'permiso:planes.eliminar']);

// Pedidos (CRUD)
Route::middleware(['auth', 'permiso:pedidos.ver'])->group(function () {
    Route::get('/pedidos/exportar-excel', [PedidoController::class, 'exportarExcel'])->name('pedidos.exportar-excel');
    Route::get('/pedidos/resoluciones-hoy', [PedidoController::class, 'resolucionesHoy'])->name('pedidos.resoluciones-hoy');
    Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/create', [PedidoController::class, 'create'])->name('pedidos.create')->middleware('permiso:pedidos.crear');
});
Route::middleware(['auth', 'permiso:pedidos.crear'])->group(function () {
    Route::get('/pedidos/cedula-temporal', [PedidoController::class, 'cedulaTemporal'])->name('pedidos.cedula-temporal');
    Route::post('/pedidos/buscar-cliente', [PedidoController::class, 'buscarCliente'])->name('pedidos.buscar-cliente');
    Route::post('/pedidos/verificar-telefono', [PedidoController::class, 'verificarTelefonoPedido'])->name('pedidos.verificar-telefono');
    Route::post('/pedidos/consultar-padron', [PedidoController::class, 'consultarPadron'])->name('pedidos.consultar-padron');
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
});
Route::middleware(['auth', 'permiso:pedidos.editar'])->group(function () {
    Route::get('/pedidos/{pedido}/edit', [PedidoController::class, 'edit'])->name('pedidos.edit');
    Route::put('/pedidos/{pedido}', [PedidoController::class, 'update'])->name('pedidos.update');
    Route::post('/pedidos/{pedido}/agregar-estado', [PedidoController::class, 'agregarEstado'])->name('pedidos.agregar-estado');
    Route::get('/pedidos/nodos/{nodo_id}/opciones-aprobacion', [PedidoController::class, 'opcionesNodoAprobacion'])->name('pedidos.nodos.opciones-aprobacion');
    Route::post('/pedidos/{pedido}/aprobar-estado', [PedidoController::class, 'aprobarEstado'])->name('pedidos.aprobar-estado');
    Route::post('/pedidos/{pedido}/descartar-estado', [PedidoController::class, 'descartarEstado'])->name('pedidos.descartar-estado');
    Route::post('/pedidos/{pedido}/reabrir-estado', [PedidoController::class, 'reabrirEstado'])->name('pedidos.reabrir-estado');
    Route::post('/pedidos/{pedido}/espera-ampliacion-red', [PedidoController::class, 'esperaAmpliacionRed'])->name('pedidos.espera-ampliacion-red');
    Route::get('/pedidos/{pedido}/crear-usuario-pppoe', [PedidoController::class, 'crearUsuarioPppoe'])->name('pedidos.crear-usuario-pppoe');
    Route::get('/pedidos/{pedido}/crear-agenda', [AgendaController::class, 'createFromPedido'])->name('pedidos.crear-agenda');
});
Route::post('/pedidos/{pedido}/finalizar', [PedidoController::class, 'finalizar'])->name('pedidos.finalizar')->middleware(['auth', 'permiso:pedidos.finalizar']);
Route::delete('/pedidos/{pedido}', [PedidoController::class, 'destroy'])->name('pedidos.destroy')->middleware(['auth', 'permiso:pedidos.eliminar']);

// Agenda (CRUD citas de instalación)
Route::middleware(['auth', 'permiso:agenda.ver'])->group(function () {
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/agenda/create', [AgendaController::class, 'create'])->name('agenda.create')->middleware('permiso:agenda.crear');
});
Route::middleware(['auth', 'permiso:agenda.crear'])->group(function () {
    Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
});
Route::middleware(['auth', 'permiso:agenda.crear'])->group(function () {
    Route::get('/agenda/{agenda}/edit', [AgendaController::class, 'edit'])->name('agenda.edit');
    Route::put('/agenda/{agenda}', [AgendaController::class, 'update'])->name('agenda.update');
});
Route::delete('/agenda/{agenda}', [AgendaController::class, 'destroy'])->name('agenda.destroy')->middleware(['auth', 'permiso:agenda.eliminar']);

// Facturación (Paraguay, preparado para factura electrónica)
Route::middleware(['auth', 'permiso:facturas.ver'])->group(function () {
    Route::get('/facturas', [FacturaController::class, 'index'])->name('facturas.index');
    Route::get('/facturas/generar-interna', [FacturaController::class, 'generarInterna'])->name('facturas.generar-interna');
    Route::get('/facturas/create', [FacturaController::class, 'create'])->name('facturas.create')->middleware('permiso:facturas.crear');
    Route::get('/facturas/create/manual', [FacturaController::class, 'createManual'])->name('facturas.create-manual')->middleware('permiso:facturas.crear');
    Route::get('/facturas/create/cliente/{cliente}', [FacturaController::class, 'createParaCliente'])->name('facturas.create-cliente')->middleware('permiso:facturas.crear');
});
Route::get('/facturacion/dashboard', [FacturacionDashboardController::class, 'index'])
    ->name('facturacion.dashboard')
    ->middleware(['auth', 'permiso:facturacion.ver']);
Route::get('/facturacion/cobros-saldo-favor', [FacturacionDashboardController::class, 'cobrosSaldoFavor'])
    ->name('facturacion.cobros-saldo-favor')
    ->middleware(['auth', 'permiso:facturacion.ver']);
Route::middleware(['auth', 'permiso:facturas.crear'])->group(function () {
    Route::post('/facturas/consultar-padron-receptor', [FacturaController::class, 'consultarPadronReceptor'])->name('facturas.consultar-padron-receptor');
    Route::post('/facturas/verificar-receptor-documento', [FacturaController::class, 'verificarReceptorDocumento'])->name('facturas.verificar-receptor-documento');
    Route::post('/facturas', [FacturaController::class, 'store'])->name('facturas.store');
    Route::post('/facturas/masivo', [FacturaController::class, 'storeMasivo'])->name('facturas.store-masivo');
    Route::post('/facturas/generar-interna', [FacturaController::class, 'storeGenerarInterna'])->name('facturas.store-generar-interna');
    Route::post('/facturas/preparar-interna-desde-servicios', [FacturaController::class, 'prepararInternaDesdeServicios'])->name('facturas.preparar-interna-desde-servicios');
    Route::get('/facturas/generar-interna-desde-servicios', [FacturaController::class, 'generarInternaDesdeServicios'])->name('facturas.generar-interna-desde-servicios');
    Route::post('/facturas/generar-interna-desde-servicios', [FacturaController::class, 'storeGenerarInternaDesdeServicios'])->name('facturas.store-generar-interna-desde-servicios');
    Route::get('/facturas/crear-interna-servicio/{servicio}', [FacturaController::class, 'crearInternaDesdeServicio'])->name('facturas.crear-interna-servicio');
    Route::post('/facturas/crear-interna-servicio/{servicio}', [FacturaController::class, 'storeCrearInternaDesdeServicio'])->name('facturas.store-crear-interna-servicio');
    Route::get('/facturas/crear-interna-servicio-especial/{servicio}', [FacturaController::class, 'crearInternaServicioEspecial'])->name('facturas.crear-interna-servicio-especial');
    Route::post('/facturas/crear-interna-servicio-especial/{servicio}', [FacturaController::class, 'storeCrearInternaServicioEspecial'])->name('facturas.store-crear-interna-servicio-especial');
    Route::get('/facturas/crear-interna-servicio-fraccion-deuda/{servicio}', [FacturaController::class, 'crearInternaFraccionDeudaServicio'])->name('facturas.crear-interna-servicio-fraccion-deuda');
    Route::post('/facturas/crear-interna-servicio-fraccion-deuda/{servicio}', [FacturaController::class, 'storeInternaFraccionDeudaServicio'])->name('facturas.store-interna-servicio-fraccion-deuda');
    Route::post('/facturas/suspender-falta-pago', [FacturaController::class, 'suspenderFaltaPago'])->name('facturas.suspender-falta-pago');
    Route::post('/facturas/{factura}/emitir', [FacturaController::class, 'emitir'])->name('facturas.emitir');
    Route::post('/facturas/{factura}/consultar-lote', [FacturaController::class, 'consultarLote'])->name('facturas.consultar-lote');
    Route::post('/facturas/consultar-lotes', [FacturaController::class, 'consultarLotesPendientes'])->name('facturas.consultar-lotes');
});
Route::middleware(['auth', 'permiso:facturas.crear'])->group(function () {
    Route::get('/facturas/{factura}/kude', [FacturaController::class, 'descargarKude'])->name('facturas.kude');
    Route::get('/facturas/{factura}/kude-pos', [FacturaController::class, 'verKudePos'])->name('facturas.kude-pos');
    Route::get('/facturas/{factura}/xml', [FacturaController::class, 'descargarXml'])->name('facturas.xml');
    Route::get('/facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');
    Route::get('/facturas/{factura}/edit', [FacturaController::class, 'edit'])->name('facturas.edit');
    Route::put('/facturas/{factura}', [FacturaController::class, 'update'])->name('facturas.update');
});
Route::delete('/facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy')->middleware(['auth', 'permiso:facturas.eliminar']);

// Cobros y recibos de pago (orden: rutas específicas antes de {cobro} para evitar que "create" coincida con el parámetro)
Route::middleware(['auth', 'permiso:cobros.crear'])->group(function () {
    Route::get('/cobros/create', [CobroController::class, 'create'])->name('cobros.create');
    Route::get('/cobros/multicobro', [CobroController::class, 'multicobro'])->name('cobros.multicobro');
    Route::post('/cobros', [CobroController::class, 'store'])->name('cobros.store');
    Route::post('/cobros/multicobro', [CobroController::class, 'storeMulticobro'])->name('cobros.store-multicobro');
});
Route::middleware(['auth', 'permiso:cobros.ver'])->group(function () {
    Route::get('/cobros', [CobroController::class, 'index'])->name('cobros.index');
    Route::get('/cobros/servicios', [CobroController::class, 'servicios'])->name('cobros.servicios');
    Route::get('/cobros/pdf-resumen', [CobroController::class, 'pdfResumen'])->name('cobros.pdf-resumen');
    Route::get('/cobros/exportar-excel', [CobroController::class, 'exportarExcel'])->name('cobros.exportar-excel');
    Route::get('/cobros/multicobro/result', [CobroController::class, 'multicobroResult'])->name('cobros.multicobro-result');
});
Route::middleware(['auth', 'permiso:cobros-rendicion.ver'])->group(function () {
    Route::get('/cobros/rendiciones', [CobroRendicionController::class, 'index'])->name('cobros-rendiciones.index');
    Route::get('/cobros/rendiciones/pendientes/{usuario}', [CobroRendicionController::class, 'pendientesUsuario'])->name('cobros-rendiciones.pendientes-usuario');
    Route::get('/cobros/rendiciones/{cobro_rendicion}', [CobroRendicionController::class, 'show'])->name('cobros-rendiciones.show');
});
Route::post('/cobros/rendiciones', [CobroRendicionController::class, 'store'])->name('cobros-rendiciones.store')->middleware(['auth', 'permiso:cobros-rendicion.crear']);
Route::middleware(['auth', 'permiso:cobros.ver'])->group(function () {
    Route::get('/cobros/{cobro}/pdf', [CobroController::class, 'reciboPdf'])->name('cobros.recibo-pdf');
    Route::get('/cobros/{cobro}', [CobroController::class, 'show'])->name('cobros.show');
});
Route::delete('/cobros/{cobro}', [CobroController::class, 'destroy'])->name('cobros.destroy')->middleware(['auth', 'permiso:cobros.eliminar']);

// Facturas internas (listado, ver, editar)
Route::get('/factura-internas/pendientes', [FacturaInternaController::class, 'pendientes'])->name('factura-internas.pendientes')->middleware(['auth', 'permiso:pagos-pendientes.ver']);
Route::get('/factura-internas/pendientes/list', [FacturaInternaController::class, 'pendientesList'])->name('factura-internas.pendientes.list')->middleware(['auth', 'permiso:pagos-pendientes.ver']);
Route::get('/factura-internas/pendientes/mapa-puntos', [FacturaInternaController::class, 'pendientesMapaPuntos'])->name('factura-internas.pendientes.mapa-puntos')->middleware(['auth', 'permiso:pagos-pendientes.ver']);
Route::get('/factura-internas/pendientes/exportar-excel', [FacturaInternaController::class, 'exportarPendientesExcel'])->name('factura-internas.pendientes.exportar-excel')->middleware(['auth', 'permiso:pagos-pendientes.ver']);
Route::get('/factura-internas/pendientes/pdf-cliente/{cliente}', [FacturaInternaController::class, 'pdfPendientesPorCliente'])->name('factura-internas.pendientes.pdf-cliente')->middleware(['auth', 'permiso:pagos-pendientes.ver']);
Route::middleware(['auth', 'permiso:pagos-pendientes.ver'])->group(function () {
    Route::get('/promesas-pago', [PromesaPagoController::class, 'index'])->name('promesas-pago.index');
});
Route::middleware(['auth', 'permiso:cobros.crear'])->group(function () {
    Route::get('/factura-internas/{factura_interna}/promesa-pago', [PromesaPagoController::class, 'create'])->name('promesas-pago.create');
    Route::post('/factura-internas/{factura_interna}/promesa-pago', [PromesaPagoController::class, 'store'])->name('promesas-pago.store');
});
Route::middleware(['auth', 'permiso:factura-interna.ver'])->group(function () {
    Route::get('/factura-internas/notas-credito/list', [FacturaInternaNotaCreditoController::class, 'list'])->name('factura-internas.notas-credito.list');
    Route::get('/factura-internas/notas-credito', [FacturaInternaNotaCreditoController::class, 'index'])->name('factura-internas.notas-credito.index');
    Route::get('/factura-internas/list', [FacturaInternaController::class, 'list'])->name('factura-internas.list');
    Route::get('/factura-internas', [FacturaInternaController::class, 'index'])->name('factura-internas.index');
    Route::get('/factura-internas/{factura_interna}/pdf', [FacturaInternaController::class, 'pdf'])->name('factura-internas.pdf');
    Route::get('/factura-internas/{factura_interna}', [FacturaInternaController::class, 'show'])->name('factura-internas.show');
});
Route::middleware(['auth', 'permiso:factura-interna.crear'])->group(function () {
    Route::post('/factura-internas/ejecutar-crear-factura-internas', [FacturaInternaController::class, 'ejecutarCrearFacturaInternas'])->name('factura-internas.ejecutar-crear-factura-internas');
    Route::get('/factura-internas/{factura_interna}/edit', [FacturaInternaController::class, 'edit'])->name('factura-internas.edit');
    Route::put('/factura-internas/{factura_interna}', [FacturaInternaController::class, 'update'])->name('factura-internas.update');
    Route::post('/factura-internas/{factura_interna}/nota-credito', [FacturaInternaController::class, 'emitirNotaCredito'])->name('factura-internas.nota-credito');
});
Route::delete('/factura-internas/{factura_interna}', [FacturaInternaController::class, 'destroy'])->name('factura-internas.destroy')->middleware(['auth', 'permiso:factura-interna.eliminar']);

// Tickets (CRUD)
Route::middleware(['auth', 'permiso:tickets.ver'])->group(function () {
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create')->middleware('permiso:tickets.crear');
});
Route::middleware(['auth', 'permiso:tickets.crear'])->group(function () {
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
});
Route::middleware(['auth', 'permiso:tickets.crear'])->group(function () {
    Route::get('/tickets/{ticket}/crear-agenda', [TicketController::class, 'crearAgenda'])->name('tickets.crear-agenda');
    Route::patch('/tickets/{ticket}/estado', [TicketController::class, 'updateEstado'])->name('tickets.update-estado');
    Route::get('/tickets/{ticket}/edit', [TicketController::class, 'edit'])->name('tickets.edit');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
});
Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy')->middleware(['auth', 'permiso:tickets.eliminar']);
Route::post('/tickets/{ticket}/facturar', [TicketController::class, 'facturar'])->name('tickets.facturar')->middleware(['auth', 'permiso:factura-interna.crear']);

// Servicios (CRUD, servicio_id auto-increment como PK)
Route::middleware(['auth', 'permiso:servicios.ver'])->group(function () {
    Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');
    Route::get('/servicios/pppoe-eventos', [ServicioPppoeEventoController::class, 'index'])->name('servicios.pppoe-eventos.index');
    Route::get('/servicios/create', [ServicioController::class, 'create'])->name('servicios.create')->middleware('permiso:servicios.crear');
    Route::get('/servicios/ips-disponibles', [ServicioController::class, 'ipsDisponibles'])->name('servicios.ips-disponibles')->middleware('permiso:servicios.crear');
    Route::post('/servicios/{servicio_id}/ping', [ServicioController::class, 'ping'])->name('servicios.ping');
    Route::get('/servicios/{servicio_id}/herramientas-red', [ServicioController::class, 'herramientasRed'])->name('servicios.herramientas-red');
    Route::post('/servicios/{servicio_id}/herramientas-red/mikrotik', [ServicioController::class, 'herramientasRedMikrotik'])->name('servicios.herramientas-red.mikrotik');
    Route::post('/servicios/{servicio_id}/herramientas-red/antena', [ServicioController::class, 'herramientasRedAntena'])->name('servicios.herramientas-red.antena');
    Route::post('/servicios/{servicio_id}/herramientas-red/antena-dhcp', [ServicioController::class, 'herramientasRedAntenaDhcp'])->name('servicios.herramientas-red.antena-dhcp');
    Route::post('/servicios/{servicio_id}/herramientas-red/olt', [ServicioController::class, 'herramientasRedOlt'])->name('servicios.herramientas-red.olt');
    Route::post('/servicios/{servicio_id}/herramientas-red/olt-desc', [ServicioController::class, 'herramientasRedOltDesc'])->name('servicios.herramientas-red.olt-desc');
});
Route::middleware(['auth', 'permiso:servicios.crear'])->group(function () {
    Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
});
Route::middleware(['auth', 'permiso:servicios.crear'])->group(function () {
    Route::get('/servicios/{servicio_id}/edit', [ServicioController::class, 'edit'])->name('servicios.edit');
    Route::get('/servicios/{servicio_id}/migrar', [ServicioController::class, 'migrarForm'])->name('servicios.migrar');
    Route::put('/servicios/{servicio_id}', [ServicioController::class, 'update'])->name('servicios.update');
    Route::post('/servicios/{servicio_id}/activar', [ServicioController::class, 'activar'])->name('servicios.activar');
    Route::post('/servicios/{servicio_id}/suspender', [ServicioController::class, 'suspender'])->name('servicios.suspender');
    Route::post('/servicios/{servicio_id}/cancelar', [ServicioController::class, 'cancelar'])->name('servicios.cancelar');
    Route::post('/servicios/{servicio_id}/dar-baja', [ServicioController::class, 'darBaja'])->name('servicios.dar-baja');
    Route::post('/servicios/{servicio_id}/sync-pppoe', [ServicioController::class, 'syncPppoe'])->name('servicios.sync-pppoe');
    Route::post('/servicios/{servicio_id}/migrar', [ServicioController::class, 'migrarStore'])->name('servicios.migrar-store');
});
Route::delete('/servicios/{servicio_id}', [ServicioController::class, 'destroy'])->name('servicios.destroy')->middleware(['auth', 'permiso:servicios.eliminar']);

// Cuentas TV (app streaming: hasta 3 clientes / dispositivos por cuenta)
Route::middleware(['auth', 'permiso:tv.ver'])->group(function () {
    Route::get('/tv-cuentas/dashboard', [TvCuentaController::class, 'dashboard'])->name('tv-cuentas.dashboard');
    Route::get('/tv-cuentas/exportar-excel', [TvCuentaController::class, 'exportarExcel'])->name('tv-cuentas.exportar-excel');
    Route::get('/tv-cuentas', [TvCuentaController::class, 'index'])->name('tv-cuentas.index');
});
Route::middleware(['auth', 'admin'])->group(function () {
    Route::put('/tv-cuentas/aviso-config', [TvCuentaController::class, 'updateAvisoConfig'])->name('tv-cuentas.aviso-config');
});
Route::middleware(['auth', 'permiso:tv.editar'])->group(function () {
    Route::get('/tv-cuentas/create', [TvCuentaController::class, 'create'])->name('tv-cuentas.create');
    Route::post('/tv-cuentas', [TvCuentaController::class, 'store'])->name('tv-cuentas.store');
    Route::get('/tv-cuentas/{tv_cuenta}/edit', [TvCuentaController::class, 'edit'])->name('tv-cuentas.edit');
    Route::put('/tv-cuentas/{tv_cuenta}', [TvCuentaController::class, 'update'])->name('tv-cuentas.update');
    Route::post('/tv-cuentas/{tv_cuenta}/renovar', [TvCuentaController::class, 'renovar'])->name('tv-cuentas.renovar');
    Route::delete('/tv-cuentas/{tv_cuenta}', [TvCuentaController::class, 'destroy'])->name('tv-cuentas.destroy');
    Route::post('/tv-cuentas/{tv_cuenta}/asignaciones', [TvCuentaController::class, 'storeAsignacion'])->name('tv-cuentas.asignaciones.store');
    Route::delete('/tv-cuentas/{tv_cuenta}/asignaciones/{asignacion}', [TvCuentaController::class, 'destroyAsignacion'])->name('tv-cuentas.asignaciones.destroy');
});

// Hotspot MikroTik (asociado a servicio_id)
Route::middleware(['auth', 'permiso:servicios.ver'])->prefix('hotspot')->name('hotspot.')->group(function () {
    Route::get('/dashboard', [HotspotController::class, 'dashboard'])->name('dashboard');
    Route::get('/', [HotspotController::class, 'index'])->name('index');
    Route::get('/create', [HotspotController::class, 'create'])->name('create');
    Route::post('/', [HotspotController::class, 'store'])->name('store');
    Route::post('/perfiles/sync-mikrotik', [HotspotPerfilController::class, 'syncMikrotik'])->name('perfiles.sync-mikrotik');
    Route::get('/perfiles', [HotspotPerfilController::class, 'index'])->name('perfiles.index');
    Route::get('/perfiles/create', [HotspotPerfilController::class, 'create'])->name('perfiles.create');
    Route::post('/perfiles', [HotspotPerfilController::class, 'store'])->name('perfiles.store');
    Route::get('/perfiles/{perfil}/edit', [HotspotPerfilController::class, 'edit'])->name('perfiles.edit');
    Route::put('/perfiles/{perfil}', [HotspotPerfilController::class, 'update'])->name('perfiles.update');
    Route::delete('/perfiles/{perfil}', [HotspotPerfilController::class, 'destroy'])->name('perfiles.destroy');
    Route::post('/{servicioHotspot}/sync', [HotspotController::class, 'sync'])->name('sync');
});

// Inventario / Ventas / Compras / Gastos
Route::middleware(['auth', 'permiso:inventario.ver'])->group(function () {
    // Proveedores
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::get('/proveedores/create', [ProveedorController::class, 'create'])->name('proveedores.create');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::get('/proveedores/{proveedor}/edit', [ProveedorController::class, 'edit'])->name('proveedores.edit');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy');

    // Categorías de producto
    Route::get('/categorias-producto', [CategoriaProductoController::class, 'index'])->name('categorias-producto.index');
    Route::get('/categorias-producto/create', [CategoriaProductoController::class, 'create'])->name('categorias-producto.create');
    Route::post('/categorias-producto', [CategoriaProductoController::class, 'store'])->name('categorias-producto.store');
    Route::get('/categorias-producto/{categoriaProducto}/edit', [CategoriaProductoController::class, 'edit'])->name('categorias-producto.edit');
    Route::put('/categorias-producto/{categoriaProducto}', [CategoriaProductoController::class, 'update'])->name('categorias-producto.update');
    Route::delete('/categorias-producto/{categoriaProducto}', [CategoriaProductoController::class, 'destroy'])->name('categorias-producto.destroy');

    // Categorías de gasto
    Route::get('/categorias-gasto', [CategoriaGastoController::class, 'index'])->name('categorias-gasto.index');
    Route::get('/categorias-gasto/create', [CategoriaGastoController::class, 'create'])->name('categorias-gasto.create');
    Route::post('/categorias-gasto', [CategoriaGastoController::class, 'store'])->name('categorias-gasto.store');
    Route::get('/categorias-gasto/{categoriaGasto}/edit', [CategoriaGastoController::class, 'edit'])->name('categorias-gasto.edit');
    Route::put('/categorias-gasto/{categoriaGasto}', [CategoriaGastoController::class, 'update'])->name('categorias-gasto.update');
    Route::delete('/categorias-gasto/{categoriaGasto}', [CategoriaGastoController::class, 'destroy'])->name('categorias-gasto.destroy');

    // Productos
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/create', [ProductoController::class, 'create'])->name('productos.create');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::get('/productos/{producto}/edit', [ProductoController::class, 'edit'])->name('productos.edit');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy');

    // Compras
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/create', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
    Route::get('/compras/{compra}/edit', [CompraController::class, 'edit'])->name('compras.edit');
    Route::put('/compras/{compra}', [CompraController::class, 'update'])->name('compras.update');
    Route::delete('/compras/{compra}', [CompraController::class, 'destroy'])->name('compras.destroy');

    // Ventas
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/create', [VentaController::class, 'create'])->name('ventas.create');
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    Route::get('/ventas/{venta}/edit', [VentaController::class, 'edit'])->name('ventas.edit');
    Route::put('/ventas/{venta}', [VentaController::class, 'update'])->name('ventas.update');
    Route::delete('/ventas/{venta}', [VentaController::class, 'destroy'])->name('ventas.destroy');

    // Gastos
    Route::get('/gastos', [GastoController::class, 'index'])->name('gastos.index');
    Route::get('/gastos/exportar-excel', [GastoController::class, 'exportarExcel'])->name('gastos.exportar-excel');
    Route::get('/gastos/create', [GastoController::class, 'create'])->name('gastos.create');
    Route::post('/gastos', [GastoController::class, 'store'])->name('gastos.store');
    Route::get('/gastos/{gasto}/pagar', [GastoController::class, 'pagar'])->name('gastos.pagar');
    Route::get('/gastos/{gasto}/edit', [GastoController::class, 'edit'])->name('gastos.edit');
    Route::put('/gastos/{gasto}', [GastoController::class, 'update'])->name('gastos.update');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->name('gastos.destroy');

    // Pagos (registrar pago para compra o gasto)
    Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
});

// Usuarios (CRUD y gestión de permisos)
Route::middleware(['auth', 'permiso:usuarios.ver'])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/sesiones', [UsuarioSesionController::class, 'index'])->name('usuarios.sesiones');
    Route::delete('/usuarios/sesiones/{session}', [UsuarioSesionController::class, 'destroy'])->name('usuarios.sesiones.destroy')->middleware('permiso:usuarios.editar');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store')->middleware('permiso:usuarios.crear');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update')->middleware('permiso:usuarios.editar');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy')->middleware('permiso:usuarios.eliminar');
    Route::get('/usuarios/{usuario}/edit-data', [UsuarioController::class, 'editData'])->name('usuarios.edit-data')->middleware('permiso:usuarios.editar');
    Route::post('/usuarios/{usuario}/permisos', [UsuarioController::class, 'updatePermisos'])->name('usuarios.update-permisos')->middleware('permiso:usuarios.permisos');
    Route::post('/usuarios/permisos-clientes', [UsuarioController::class, 'updatePermisosClientes'])->name('usuarios.update-permisos-clientes')->middleware('permiso:usuarios.permisos');
    Route::post('/usuarios/{usuario}/aprobar', [UsuarioController::class, 'aprobar'])->name('usuarios.aprobar');
});

// Sistema (routers, pools de IP, IPs asignadas, importar WispHub)
Route::prefix('sistema')->name('sistema.')->middleware(['auth', 'permiso:sistema.ver'])->group(function () {
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('mikrotik-pendientes', [MikrotikPendienteController::class, 'index'])->name('mikrotik-pendientes.index');
    Route::post('mikrotik-pendientes/reintentar-todos', [MikrotikPendienteController::class, 'reintentarTodos'])->name('mikrotik-pendientes.reintentar-todos');
    Route::post('mikrotik-pendientes/{id}/reintentar', [MikrotikPendienteController::class, 'reintentar'])->whereNumber('id')->name('mikrotik-pendientes.reintentar');
    Route::delete('mikrotik-pendientes/{id}', [MikrotikPendienteController::class, 'descartar'])->whereNumber('id')->name('mikrotik-pendientes.descartar');
    Route::get('importar-wisphub', [WispHubImportController::class, 'index'])->name('importar-wisphub.index');
    Route::post('importar-wisphub/run', [WispHubImportController::class, 'run'])->name('importar-wisphub.run');
    Route::get('importar-wisphub/exportar-excel', [WispHubImportController::class, 'exportarExcel'])->name('importar-wisphub.exportar-excel');
    Route::post('routers/{router}/test-connection', [RouterController::class, 'testConnection'])->name('routers.test-connection');
    Route::post('routers/{router}/sync-pppoe', [RouterController::class, 'syncPppoe'])->name('routers.sync-pppoe');
    Route::get('routers/{router}/export-pppoe-script', [RouterController::class, 'exportPppoeScript'])->name('routers.export-pppoe-script');
    Route::resource('routers', RouterController::class)->except(['show']);
    Route::resource('router-modelos', RouterModeloController::class)->except(['show']);
    Route::resource('router-ip-pools', RouterIpPoolController::class)->except(['show']);
    Route::get('pool-ip-asignadas', [PoolIpAsignadaController::class, 'index'])->name('pool-ip-asignadas.index');
    Route::get('pool-ip-asignadas/create', [PoolIpAsignadaController::class, 'create'])->name('pool-ip-asignadas.create');
    Route::post('pool-ip-asignadas', [PoolIpAsignadaController::class, 'store'])->name('pool-ip-asignadas.store');
    Route::post('pool-ip-asignadas/rango', [PoolIpAsignadaController::class, 'storeRango'])->name('pool-ip-asignadas.store-rango');
    Route::get('pool-ip-asignadas/{pool_id}/{ip}/edit', [PoolIpAsignadaController::class, 'edit'])->name('pool-ip-asignadas.edit');
    Route::put('pool-ip-asignadas/{pool_id}/{ip}', [PoolIpAsignadaController::class, 'update'])->name('pool-ip-asignadas.update');
    Route::delete('pool-ip-asignadas/{pool_id}/{ip}', [PoolIpAsignadaController::class, 'destroy'])->name('pool-ip-asignadas.destroy');

    // Cajas NAP e infraestructura óptica
    Route::get('cajas-nap/mapa', [CajaNapController::class, 'mapa'])->name('cajas-nap.mapa');
    Route::get('cajas-nap/mapa/data', [CajaNapController::class, 'mapaData'])->name('cajas-nap.mapa.data');
    Route::get('cajas-nap/{cajaNap}/servicios-por-cliente', [CajaNapController::class, 'serviciosPorCliente'])
        ->name('cajas-nap.servicios-por-cliente')
        ->middleware('permiso:sistema.editar');
    Route::resource('cajas-nap', CajaNapController::class)
        ->parameters(['cajas-nap' => 'cajaNap']);
    Route::post('cajas-nap/{cajaNap}/puertos-activos/{puertoActivo}/asignar', [CajaNapController::class, 'asignarPuertoActivo'])->name('cajas-nap.puertos-activos.asignar');
    Route::delete('cajas-nap/{cajaNap}/puertos-activos/{puertoActivo}/liberar', [CajaNapController::class, 'liberarPuertoActivo'])->name('cajas-nap.puertos-activos.liberar');
    Route::get('cajas-nap/{cajaNap}/splitters-primarios/create', [SplitterPrimarioController::class, 'create'])->name('splitters-primarios.create');
    Route::post('cajas-nap/{cajaNap}/splitters-primarios', [SplitterPrimarioController::class, 'store'])->name('splitters-primarios.store');
    Route::get('splitters-primarios/{splitterPrimario}/edit', [SplitterPrimarioController::class, 'edit'])->name('splitters-primarios.edit');
    Route::put('splitters-primarios/{splitterPrimario}', [SplitterPrimarioController::class, 'update'])->name('splitters-primarios.update');
    Route::delete('splitters-primarios/{splitterPrimario}', [SplitterPrimarioController::class, 'destroy'])->name('splitters-primarios.destroy');
    Route::get('cajas-nap/{cajaNap}/splitters-secundarios/create', [SplitterSecundarioController::class, 'create'])->name('splitters-secundarios.create');
    Route::post('cajas-nap/{cajaNap}/splitters-secundarios', [SplitterSecundarioController::class, 'store'])->name('splitters-secundarios.store');
    Route::get('splitters-secundarios/{splitterSecundario}/edit', [SplitterSecundarioController::class, 'edit'])->name('splitters-secundarios.edit');
    Route::put('splitters-secundarios/{splitterSecundario}', [SplitterSecundarioController::class, 'update'])->name('splitters-secundarios.update');
    Route::delete('splitters-secundarios/{splitterSecundario}', [SplitterSecundarioController::class, 'destroy'])->name('splitters-secundarios.destroy');
    Route::resource('lineas-cable', LineaCableController::class)->except(['show']);
    Route::resource('salida-pons', SalidaPonController::class)->except(['show']);
    Route::resource('olt-marcas', OltMarcaController::class);
    Route::resource('olt-modelos', OltModeloController::class)->except(['show']);
    Route::resource('olts', OltController::class);
    Route::get('olts/{olt}/pon/{ponPort}', [OltController::class, 'showPonOnus'])->whereNumber('ponPort')->name('olts.pon-onus');
    Route::post('olts/{olt}/sync-vista', [OltController::class, 'syncVista'])->name('olts.sync-vista');
    Route::post('olts/{olt}/test-gestion', [OltController::class, 'testGestion'])->name('olts.test-gestion')->middleware('permiso:ftth-olts.editar');
    Route::post('olts/{olt}/import-onus', [OltController::class, 'importOnus'])->name('olts.import-onus')->middleware('permiso:ftth-olts.editar');
    Route::post('olts/{olt}/refresh-onu-detalles', [OltController::class, 'refreshOnuDetalles'])->name('olts.refresh-onu-detalles')->middleware('permiso:ftth-olts.editar');
    Route::post('olts/{olt}/pon/{ponPort}/refresh-onu-detalles', [OltController::class, 'refreshOnuDetallesPon'])->whereNumber('ponPort')->name('olts.refresh-onu-detalles-pon')->middleware('permiso:ftth-olts.editar');
    Route::get('olts/{olt}/olt-puertos/create', [OltPuertoController::class, 'create'])->name('olt-puertos.create');
    Route::post('olts/{olt}/olt-puertos', [OltPuertoController::class, 'store'])->name('olt-puertos.store');
    Route::get('olt-puertos/{oltPuerto}/edit', [OltPuertoController::class, 'edit'])->name('olt-puertos.edit');
    Route::put('olt-puertos/{oltPuerto}', [OltPuertoController::class, 'update'])->name('olt-puertos.update');
    Route::delete('olt-puertos/{oltPuerto}', [OltPuertoController::class, 'destroy'])->name('olt-puertos.destroy');
});
