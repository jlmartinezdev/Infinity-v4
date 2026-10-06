<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\MikroTikPppoeEventoScriptService;
use Illuminate\Console\Command;

class MikrotikPppoeEventoScriptCommand extends Command
{
    protected $signature = 'mikrotik:pppoe-eventos-script
                            {router_id? : ID del router (omitir = todos)}
                            {--dry-run : Solo informar, no escribir en el MikroTik}
                            {--force : Reescribir scripts aunque ya existan}';

    protected $description = 'Revisa scripts infinity-pppoe-up/down en los routers y los agrega si faltan.';

    public function handle(MikroTikPppoeEventoScriptService $service): int
    {
        $routerId = $this->argument('router_id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $routers = $routerId
            ? Router::query()->where('router_id', (int) $routerId)->get()
            : Router::query()->orderBy('nombre')->get();

        if ($routers->isEmpty()) {
            $this->error('No se encontró ningún router.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Modo consulta: no se escribe en los routers.');
        }

        $filas = [];
        $faltantes = 0;
        $agregados = 0;
        $errores = 0;

        foreach ($routers as $router) {
            $this->line($router->nombre.' ('.$router->ip.')…');
            $r = $service->asegurar($router, $dryRun, $force);
            if ($r['error']) {
                $errores++;
            }
            if (in_array($r['script_up'], ['faltante', 'agregado'], true)
                || in_array($r['script_down'], ['faltante', 'agregado'], true)) {
                $faltantes++;
            }
            if (in_array($r['script_up'], ['agregado', 'actualizado'], true)
                || in_array($r['script_down'], ['agregado', 'actualizado'], true)) {
                $agregados++;
            }
            $filas[] = [
                $r['nombre'],
                $r['ip'] ?? '',
                $r['script_up'],
                $r['script_down'],
                $r['perfiles_ok'] === [] ? '—' : implode(', ', $r['perfiles_ok']),
                $r['error'] ?: ($r['token_generado'] ? 'token nuevo' : ''),
            ];
        }

        $this->newLine();
        $this->table(
            ['Router', 'IP', 'script up', 'script down', 'Perfiles PPP', 'Notas'],
            $filas
        );
        $this->line("Routers: {$routers->count()} · sin script o recién agregados: {$faltantes} · escritos: {$agregados} · errores: {$errores}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
