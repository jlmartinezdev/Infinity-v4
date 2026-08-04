<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Ticket;
use App\Observers\ClienteObserver;
use App\Observers\TicketObserver;
use App\Services\MikroTikService;
use App\Session\CustomDatabaseSessionHandler;
use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MikroTikService::class, function () {
            return new MikroTikService(
                timeout: (int) config('mikrotik.timeout', 30),
                socketTimeout: (int) config('mikrotik.socket_timeout', 60),
                ssl: (bool) config('mikrotik.ssl', false)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
        Cliente::observe(ClienteObserver::class);

        $appUrl = (string) config('app.url', '');
        // En vhost local (infinity.local por HTTP) no forzar HTTPS: rompe CSS/JS y cookies Secure.
        $esLocalHttp = app()->environment('local')
            && ! request()->secure()
            && str_ends_with((string) request()->getHost(), '.local');
        if ($esLocalHttp) {
            config(['session.secure' => false]);
        }
        if ($appUrl !== '' && str_starts_with($appUrl, 'https://') && ! $esLocalHttp) {
            URL::forceScheme('https');
            // Si no fijaste SESSION_SECURE_COOKIE, marcar cookie de sesión Secure en HTTPS real.
            if (config('session.secure') === null) {
                config(['session.secure' => true]);
            }
            // CookieJar se instancia con los valores de session al primer resolve(); si ya existía
            // con secure=null, las cookies encoladas (p. ej. remember) podrían no llevar Secure.
            if ($this->app->resolved('cookie')) {
                $session = $this->app['config']->get('session');
                $this->app->make(CookieJar::class)->setDefaultPathAndDomain(
                    $session['path'],
                    $session['domain'],
                    $session['secure'],
                    $session['same_site'] ?? null,
                );
            }
        }

        // Usar manejador de sesión compatible con tablas sin columna user_id
        $this->app->make('session')->extend('database', function ($app) {
            $table = $app['config']['session.table'];
            $lifetime = $app['config']['session.lifetime'];
            $connection = $app['config']['session.connection'] ?? null;

            return new CustomDatabaseSessionHandler(
                $app['db']->connection($connection),
                $table,
                $lifetime,
                $app
            );
        });

        Gate::before(function ($user, $ability) {
            if ($user && method_exists($user, 'tienePermiso')) {
                return $user->tienePermiso($ability) ? true : null;
            }
            return null;
        });
    }
}
