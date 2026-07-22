<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\CobroController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MikrotikWebhookController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ServicioController;
use App\Http\Controllers\Api\V1\SolicitudAccesoController;
use App\Http\Controllers\Api\V1\TareaController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API móvil v1
|--------------------------------------------------------------------------
| Auth: Bearer token (Sanctum)
| Staff: email + contraseña
| Cliente: documento + clave PLUS**** (o documento legacy)
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    // Onboarding público (sin token)
    Route::post('/portal/solicitud-alta', [SolicitudAccesoController::class, 'store']);

    // Webhooks MikroTik (auth por webhook_token del router, no Sanctum)
    Route::post('/webhooks/mikrotik/pppoe', [MikrotikWebhookController::class, 'pppoe']);

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
            Route::get('/tickets', [PortalController::class, 'tickets'])
                ->middleware('permiso:portal.tickets.ver');
            Route::post('/tickets', [PortalController::class, 'crearTicket'])
                ->middleware('permiso:portal.tickets.crear');
            Route::get('/ticket-asuntos', [PortalController::class, 'asuntosTicket'])
                ->middleware('permiso:portal.tickets.ver');
        });

        // Personal / staff
        Route::middleware('api.staff')->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
                ->middleware('permiso:dashboard.ver');

            Route::middleware('permiso:clientes.ver')->prefix('staff')->group(function () {
                Route::get('/solicitudes', [SolicitudAccesoController::class, 'index']);
                Route::get('/solicitudes/{id}', [SolicitudAccesoController::class, 'show'])
                    ->whereNumber('id');
            });
            Route::post('/staff/solicitudes/{id}/aprobar', [SolicitudAccesoController::class, 'aprobar'])
                ->whereNumber('id')
                ->middleware('permiso:clientes.editar');

            Route::get('/staff/auditoria', [SolicitudAccesoController::class, 'auditoria'])
                ->middleware('admin');

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
