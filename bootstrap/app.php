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

        if (config('monitoreo.habilitado', true)) {
            $schedule->command('monitoreo:ping-servicios')
                ->everyFiveMinutes()
                ->withoutOverlapping(10)
                ->before(function () {
                    Log::info('Tarea iniciado: monitoreo:ping-servicios');
                });
        }

        // Avisos WhatsApp vencimiento cuentas TV (hora configurable en panel TV)
        $horaTvAviso = \App\Support\TvAvisoConfig::hora();
        $schedule->command('tv:avisar-vencimientos')
            ->dailyAt($horaTvAviso)
            ->withoutOverlapping()
            ->before(function () {
                Log::info('Tarea iniciado: tv:avisar-vencimientos');
            });

        // Push FCM: facturas internas por vencer (días = notificacion_dias_antes)
        $schedule->command('fcm:avisar-facturas-por-vencer')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->before(function () {
                Log::info('Tarea iniciado: fcm:avisar-facturas-por-vencer');
            });

        // Backup BD → Google Drive (diario 02:30)
        if (config('backup.drive.enabled')) {
            $schedule->command('backup:drive')
                ->dailyAt('02:30')
                ->withoutOverlapping()
                ->before(function () {
                    Log::info('Tarea iniciado: backup:drive');
                });
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
