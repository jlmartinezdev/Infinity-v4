<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\CobroController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MikrotikWebhookController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\PortalLoyaltyController;
use App\Http\Controllers\Api\V1\PortalV1Controller;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\ServicioController;
use App\Http\Controllers\Api\V1\SolicitudAccesoController;
use App\Http\Controllers\Api\V1\StaffAvisoEnCaminoController;
use App\Http\Controllers\Api\V1\StaffConfigController;
use App\Http\Controllers\Api\V1\StaffPedidoInstalacionController;
use App\Http\Controllers\Api\V1\StaffUbicacionController;
use App\Http\Controllers\Api\V1\StaffVisitaController;
use App\Http\Controllers\Api\V1\TareaController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TpagoWebhookController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API mÃ³vil v1
|--------------------------------------------------------------------------
| Auth: Bearer token (Sanctum)
| Staff: email + contraseÃ±a
| Cliente: documento + clave PLUS**** (o documento legacy)
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/login', [AuthController::class, 'login']); // alias N8N (JSON viejo)

    // Onboarding pÃºblico (sin token)
    Route::post('/portal/solicitud-alta', [SolicitudAccesoController::class, 'store']);

    // Webhooks MikroTik (auth por webhook_token del router, no Sanctum)
    Route::post('/webhooks/mikrotik/pppoe', [MikrotikWebhookController::class, 'pppoe']);
    Route::post('/webhooks/mikrotik/isp-failover', [MikrotikWebhookController::class, 'ispFailover']);

    // Webhooks WhatsApp Cloud API (verify_token + firma App Secret)
    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

    // Confirmación de pagos TPago / Bancard (URL a configurar en el portal TPago)
    Route::post('/webhooks/tpago', [TpagoWebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Portal del cliente (su cuenta)
        Route::middleware('api.cliente')->prefix('portal')->group(function () {
            Route::get('/resumen', [PortalController::class, 'resumen'])
                ->middleware('permiso:portal.cuenta.ver');
            Route::get('/facturas', [PortalController::class, 'facturas'])
                ->middleware('permiso:portal.facturas.ver');
            Route::get('/cobros', [PortalController::class, 'cobros'])
                ->middleware('permiso:portal.cobros.ver');
            Route::get('/cobros/{cobro}', [PortalController::class, 'cobro'])
                ->whereNumber('cobro')
                ->middleware('permiso:portal.cobros.ver');
            Route::get('/tickets', [PortalController::class, 'tickets'])
                ->middleware('permiso:portal.tickets.ver');
            Route::post('/tickets', [PortalController::class, 'crearTicket'])
                ->middleware('permiso:portal.tickets.crear');
            Route::get('/ticket-asuntos', [PortalController::class, 'asuntosTicket'])
                ->middleware('permiso:portal.tickets.ver');
            Route::post('/save-push-token', [AuthController::class, 'savePushToken']);

            // Loyalty / CMS
            Route::get('/novedades', [PortalLoyaltyController::class, 'novedades'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::get('/puntos', [PortalLoyaltyController::class, 'puntos'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::get('/premios', [PortalLoyaltyController::class, 'premios'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::get('/reglas-puntos', [PortalLoyaltyController::class, 'reglasPuntos'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::get('/canjes', [PortalLoyaltyController::class, 'canjesIndex'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::post('/canjes', [PortalLoyaltyController::class, 'canjesStore'])
                ->middleware('permiso:portal.loyalty.canjear');
            Route::get('/planes-upsell', [PortalLoyaltyController::class, 'planesUpsell'])
                ->middleware('permiso:portal.loyalty.ver');
            Route::post('/solicitud-cambio-plan', [PortalLoyaltyController::class, 'solicitudCambioPlan'])
                ->middleware('permiso:portal.loyalty.upsell');

            // Home Interplus Clientes 3.2 (feature flags + Fase 3)
            Route::prefix('v1')->group(function () {
                Route::get('/feature-flags', [PortalV1Controller::class, 'featureFlags']);
                Route::get('/insights', [PortalV1Controller::class, 'insights'])
                    ->middleware('permiso:portal.cuenta.ver');
                Route::get('/referidos', [PortalV1Controller::class, 'referidos'])
                    ->middleware('permiso:portal.cuenta.ver');
                Route::post('/referidos/canjear', [PortalV1Controller::class, 'referidosCanjear'])
                    ->middleware('permiso:portal.cuenta.ver');
                Route::match(['get', 'post'], '/pago-online', [PortalV1Controller::class, 'pagoOnline'])
                    ->middleware('permiso:portal.cuenta.ver');
                Route::get('/faqs', [PortalV1Controller::class, 'faqs'])
                    ->middleware('permiso:portal.cuenta.ver');
                Route::get('/cpe/dhcp-clients', [PortalV1Controller::class, 'cpeDhcpClients'])
                    ->middleware('permiso:portal.cuenta.ver');
            });
        });

        // Personal / staff
        Route::middleware('api.staff')->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
                ->middleware('permiso:dashboard.ver');

            Route::middleware('permiso:solicitudes-acceso.ver')->prefix('staff')->group(function () {
                Route::get('/solicitudes', [SolicitudAccesoController::class, 'index']);
                Route::get('/solicitudes/{id}', [SolicitudAccesoController::class, 'show'])
                    ->whereNumber('id');
                Route::get('/clientes/resumen', [SolicitudAccesoController::class, 'clientesResumen']);
                Route::get('/clientes/buscar', [SolicitudAccesoController::class, 'buscarClientes']);
            });
            Route::post('/staff/solicitudes/{id}/aprobar', [SolicitudAccesoController::class, 'aprobar'])
                ->whereNumber('id')
                ->middleware('permiso:solicitudes-acceso.editar');
            Route::post('/staff/solicitudes/{id}/rechazar', [SolicitudAccesoController::class, 'rechazar'])
                ->whereNumber('id')
                ->middleware('permiso:solicitudes-acceso.editar');

            Route::post('/staff/save-push-token', [AuthController::class, 'savePushToken']);

            Route::get('/staff/auditoria', [SolicitudAccesoController::class, 'auditoria'])
                ->middleware('admin');

            // Flota GPS + visitas (app ISP Staff)
            Route::post('/staff/ubicacion', [StaffUbicacionController::class, 'store']);
            Route::get('/staff/ubicaciones', [StaffUbicacionController::class, 'index']);
            Route::get('/staff/ubicaciones/stream', [StaffUbicacionController::class, 'stream']);
            Route::get('/staff/visitas', [StaffVisitaController::class, 'index'])
                ->middleware('permiso:tickets.ver');
            Route::get('/staff/visitas/{id}', [StaffVisitaController::class, 'show'])
                ->whereNumber('id')
                ->middleware('permiso:tickets.ver');
            Route::post('/staff/visitas/{id}/actualizar', [StaffVisitaController::class, 'actualizar'])
                ->whereNumber('id')
                ->middleware('permiso:tickets.crear');
            Route::post('/staff/visitas/{id}/estado', [StaffVisitaController::class, 'actualizar'])
                ->whereNumber('id')
                ->middleware('permiso:tickets.crear');
            Route::patch('/staff/visitas/{id}', [StaffVisitaController::class, 'actualizar'])
                ->whereNumber('id')
                ->middleware('permiso:tickets.crear');

            // Aviso WhatsApp "técnico en camino" (Cloud API, número oficial)
            Route::post('/staff/avisos/en-camino', [StaffAvisoEnCaminoController::class, 'store']);

            // Config runtime (Maps JS key, etc.) — app Staff WebView
            Route::get('/staff/config/maps', [StaffConfigController::class, 'maps']);
            Route::get('/staff/config', [StaffConfigController::class, 'index']);

            // Pedidos de instalación (mismo proceso que web /pedidos)
            Route::get('/staff/me', [StaffPedidoInstalacionController::class, 'me']);
            Route::get('/staff/pedidos-instalacion', [StaffPedidoInstalacionController::class, 'index'])
                ->middleware('permiso:pedidos.ver');
            Route::get('/staff/pedidos-instalacion/opciones-aprobacion', [StaffPedidoInstalacionController::class, 'opcionesAprobacion'])
                ->middleware('permiso:pedidos.editar');
            Route::get('/staff/pedidos-instalacion/nodos/{nodoId}/opciones', [StaffPedidoInstalacionController::class, 'opcionesNodo'])
                ->whereNumber('nodoId')
                ->middleware('permiso:pedidos.editar');
            Route::get('/staff/pedidos-instalacion/{id}', [StaffPedidoInstalacionController::class, 'show'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.ver');
            Route::post('/staff/pedidos-instalacion', [StaffPedidoInstalacionController::class, 'store'])
                ->middleware('permiso:pedidos.crear');
            Route::post('/staff/pedidos-instalacion/{id}/actualizar', [StaffPedidoInstalacionController::class, 'actualizar'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.editar');
            Route::post('/staff/pedidos-instalacion/{id}/aprobar-estado', [StaffPedidoInstalacionController::class, 'aprobarEstado'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.editar');
            Route::post('/staff/pedidos-instalacion/{id}/descartar-estado', [StaffPedidoInstalacionController::class, 'descartarEstado'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.editar');
            Route::post('/staff/pedidos-instalacion/{id}/reabrir-estado', [StaffPedidoInstalacionController::class, 'reabrirEstado'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.editar');
            Route::post('/staff/pedidos-instalacion/{id}/finalizar', [StaffPedidoInstalacionController::class, 'finalizar'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.finalizar');
            Route::post('/staff/pedidos-instalacion/{id}/pppoe/generar', [StaffPedidoInstalacionController::class, 'generarPppoe'])
                ->whereNumber('id')
                ->middleware('permiso:pedidos.editar');

            Route::get('/clientes/por-telefono', [ClienteController::class, 'porTelefono'])
                ->middleware('permiso:clientes.ver,pagos-pendientes.ver,cobros.ver,factura-interna.ver');

            Route::middleware('permiso:clientes.ver')->group(function () {
                Route::get('/clientes/buscar', [ClienteController::class, 'buscar']);
                Route::get('/clientes', [ClienteController::class, 'index']);
                Route::get('/clientes/{cliente}', [ClienteController::class, 'show']);
            });

            Route::middleware('permiso:servicios.ver')->group(function () {
                Route::get('/servicios', [ServicioController::class, 'index']);
                Route::get('/servicios/{servicio}', [ServicioController::class, 'show']);
            });

            Route::middleware('permiso:cobros.ver')->group(function () {
                Route::get('/cobros', [CobroController::class, 'index']);
                Route::get('/cobros/facturas-pendientes', [CobroController::class, 'facturasPendientes']);
                Route::get('/cobros/{cobro}', [CobroController::class, 'show']);
            });
            Route::post('/cobros', [CobroController::class, 'store'])
                ->middleware('permiso:cobros.crear');

            // Reportes para N8N / automatizaciones (misma lógica de saldo que pendientes de pago)
            Route::get('/reportes/morosos', [ReporteController::class, 'morosos'])
                ->middleware('permiso:pagos-pendientes.ver,cobros.ver,factura-interna.ver');

            Route::middleware('permiso:tickets.ver')->group(function () {
                Route::get('/tickets', [TicketController::class, 'index']);
                Route::get('/tickets/asuntos', [TicketController::class, 'asuntos']);
                Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
            });
            Route::post('/tickets', [TicketController::class, 'store'])
                ->middleware('permiso:tickets.crear');
            Route::patch('/tickets/{ticket}/estado', [TicketController::class, 'updateEstado'])
                ->middleware('permiso:tickets.crear');

            Route::middleware('permiso:tareas.ver')->group(function () {
                Route::get('/tareas', [TareaController::class, 'index']);
            });
            Route::post('/tareas', [TareaController::class, 'store'])
                ->middleware('permiso:tareas.crear');
            Route::put('/tareas/{tarea}', [TareaController::class, 'update'])
                ->middleware('permiso:tareas.crear');
            Route::post('/tareas/{tarea}/move', [TareaController::class, 'move'])
                ->middleware('permiso:tareas.crear');
        });
    });
});
