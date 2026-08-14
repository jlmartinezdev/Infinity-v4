<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permiso' => \App\Http\Middleware\CheckPermiso::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'flota.staff' => \App\Http\Middleware\EnsurePuedeVerFlotaStaff::class,
            'api.staff' => \App\Http\Middleware\EnsureStaffApi::class,
            'api.cliente' => \App\Http\Middleware\EnsureClientePortalApi::class,
        ]);
        // Usuario ya autenticado que visita /login → inicio según permisos
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();
            if ($user && $user->esClientePortal()) {
                return '/login';
            }
            if ($user && $user->tienePermiso('dashboard.ver')) {
                return '/';
            }

            return '/inicio';
        });
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/login';
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Creación automática de facturas internas: clientes activos con servicio; líneas A/S/C (día configurable)
        $diaFactura = (int) \App\Models\FacturacionParametro::obtener('dia_creacion_factura_automatica', 1);
        $horaFactura = '01:00'; // Ejecuta a la 1:00 AM el día configurado
        $schedule->command('facturas:crear-internas-automaticas')
            ->monthlyOn($diaFactura, (string) $horaFactura)
            ->before(function () {
                Log::info('Tarea iniciado: facturas:crear-internas-automaticas');
            });

        // Corte automático por falta de pago (día y hora configurables en Configuración > Facturación)
        $hora = \App\Models\FacturacionParametro::obtener('hora_corte_automatico', '00:01');
        $diaCorte = (int) \App\Models\FacturacionParametro::obtener('dia_corte', 6);
        $schedule->command('servicios:corte-automatico')
            ->monthlyOn($diaCorte, (string) $hora)
            ->before(function () {
                Log::info('Tarea iniciado: servicios:corte-automatico');
            });

        // Promesas de pago vencidas (fecha/hora acordada superada → suspender si sigue el saldo)
        $schedule->command('promesas:procesar-vencidas')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->before(function () {
                Log::info('Tarea iniciado: promesas:procesar-vencidas');
            });

        $schedule->command('mikrotik:procesar-pendientes')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->before(function () {
                Log::info('Tarea iniciado: mikrotik:procesar-pendientes');
            });

        // Ping ICMP a IP de gestión de routers (cada 60 s; sin auditoría/notificaciones)
        $schedule->command('mikrotik:ping-routers')
            ->everyMinute()
            ->withoutOverlapping(1)
            ->before(function () {
                Log::info('Tarea iniciado: mikrotik:ping-routers');
            });

        $schedule->command('mikrotik:check-isp-salida')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->before(function () {
                Log::info('Tarea iniciado: mikrotik:check-isp-salida');
            });

        if (config('monitoreo.habilitado', true)) {
            $schedule->command('monitoreo:ping-servicios')
                ->cron('*/7 * * * *')
                ->withoutOverlapping(10)
                ->before(function () {
                    Log::info('Tarea iniciado: monitoreo:ping-servicios');
                });
        }

        // Avisos WhatsApp vencimiento cuentas TV (hora del panel; si el PC estaba apagado, corre al encender)
        $schedule->command('tv:avisar-vencimientos')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when(fn () => \App\Support\TvAvisoConfig::enabled()
                && \App\Support\ScheduleOnceAfter::due('tv-avisar', \App\Support\TvAvisoConfig::hora()))
            ->before(function () {
                Log::info('Tarea iniciado: tv:avisar-vencimientos');
            });

        // Push FCM: facturas internas por vencer (días = notificacion_dias_antes)
        $schedule->command('fcm:avisar-facturas-por-vencer')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when(fn () => \App\Support\ScheduleOnceAfter::due('fcm-facturas', '09:00'))
            ->before(function () {
                Log::info('Tarea iniciado: fcm:avisar-facturas-por-vencer');
            });

        // Loyalty: vencer lotes de puntos (FIFO)
        $schedule->command('loyalty:expirar-puntos')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when(fn () => \App\Support\ScheduleOnceAfter::due('loyalty-expirar', '00:15'))
            ->before(function () {
                Log::info('Tarea iniciado: loyalty:expirar-puntos');
            });

        // Backup BD → Google Drive (hora configurable; reintenta si falló)
        $schedule->command('backup:drive')
            ->everyFifteenMinutes()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/schedule-backup-drive.log'))
            ->when(function () {
                if (! app(\App\Services\GoogleDriveUploader::class)->isConfigured()) {
                    return false;
                }

                return \App\Support\ScheduleOnceAfter::due(
                    'backup-drive',
                    \App\Support\BackupScheduleConfig::hora()
                );
            })
            ->before(function () {
                Log::info('Tarea iniciado: backup:drive');
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
