<?php

namespace App\Providers;

use App\Services\MikroTikService;
use App\Session\CustomDatabaseSessionHandler;
use Illuminate\Support\Facades\Gate;
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
