<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scripts MikroTik on-up/on-down que avisan a Infinity de sesiones PPPoE.
 */
class MikroTikPppoeEventoScriptService
{
    public const SCRIPT_UP = 'infinity-pppoe-up';

    public const SCRIPT_DOWN = 'infinity-pppoe-down';

    public function __construct(private MikroTikService $mikrotik) {}

    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/webhooks/mikrotik/pppoe';
    }

    public function source(string $evento, string $token, ?string $url = null): string
    {
        $evento = strtolower(trim($evento)) === 'down' ? 'down' : 'up';
        $url = $url ?? $this->webhookUrl();
        $token = trim($token);
        $checkCert = str_starts_with($url, 'https://') ? ' check-certificate=no' : '';

        return <<<RSC
:local u \$"user"
:local ip \$"remote-address"
:local mac \$"caller-id"
:local data ("evento={$evento}&usuario=" . \$u . "&ip=" . \$ip . "&mac=" . \$mac)
/tool fetch url="{$url}" http-method=post http-header-field="Authorization: Bearer {$token},Content-Type: application/x-www-form-urlencoded" http-data=\$data{$checkCert} keep-result=no
RSC;
    }

    /**
     * @return array{
     *   success: bool,
     *   router_id: int,
     *   nombre: string,
     *   ip: ?string,
     *   token_generado: bool,
     *   script_up: string,
     *   script_down: string,
     *   perfiles_ok: list<string>,
     *   perfiles_omitidos: list<string>,
     *   error: ?string
     * }
     */
    public function asegurar(Router $router, bool $dryRun = false, bool $forzarScripts = false): array
    {
        $base = [
            'success' => false,
            'router_id' => (int) $router->router_id,
            'nombre' => (string) $router->nombre,
            'ip' => $router->ip,
            'token_generado' => false,
            'script_up' => 'pendiente',
            'script_down' => 'pendiente',
            'perfiles_ok' => [],
            'perfiles_omitidos' => [],
            'error' => null,
        ];

        try {
            $scripts = $this->mikrotik->getSystemScripts($router);
            $nombres = [];
            foreach ($scripts as $s) {
                $n = trim((string) ($s['name'] ?? ''));
                if ($n !== '') {
                    $nombres[$n] = true;
                }
            }

            $tieneUp = isset($nombres[self::SCRIPT_UP]);
            $tieneDown = isset($nombres[self::SCRIPT_DOWN]);
            $base['script_up'] = $tieneUp ? 'presente' : 'faltante';
            $base['script_down'] = $tieneDown ? 'presente' : 'faltante';

            $perfiles = $this->mikrotik->getPppProfiles($router);
            $pendientesPerfil = [];
            foreach ($perfiles as $p) {
                $nombre = trim((string) ($p['name'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $id = (string) ($p['.id'] ?? '');
                $onUp = trim((string) ($p['on-up'] ?? ''));
                $onDown = trim((string) ($p['on-down'] ?? ''));
                $upOk = $onUp === '' || $this->esScriptInfinity($onUp);
                $downOk = $onDown === '' || $this->esScriptInfinity($onDown);
                if (! $upOk || ! $downOk) {
                    $base['perfiles_omitidos'][] = $nombre.($onUp !== '' ? " (on-up={$onUp})" : '');

                    continue;
                }
                if ($onUp === self::SCRIPT_UP && $onDown === self::SCRIPT_DOWN) {
                    $base['perfiles_ok'][] = $nombre;

                    continue;
                }
                $pendientesPerfil[] = ['id' => $id, 'nombre' => $nombre];
            }

            if ($dryRun) {
                $base['success'] = true;
                if ($pendientesPerfil !== []) {
                    $base['perfiles_ok'] = array_merge(
                        $base['perfiles_ok'],
                        array_map(static fn (array $p) => $p['nombre'].' (pendiente)', $pendientesPerfil)
                    );
                }

                return $base;
            }

            $token = trim((string) ($router->webhook_token ?? ''));
            $debeEscribirScripts = (! $tieneUp) || (! $tieneDown) || $forzarScripts;

            if ($debeEscribirScripts) {
                if ($token === '') {
                    $token = $this->nuevoWebhookToken();
                    $router->webhook_token = $token;
                    $router->save();
                    $base['token_generado'] = true;
                }
                if ($token === '') {
                    $base['error'] = 'No se pudo generar webhook_token.';

                    return $base;
                }

                if (! $tieneUp || $forzarScripts) {
                    $up = $this->mikrotik->upsertSystemScript(
                        $router,
                        self::SCRIPT_UP,
                        $this->source('up', $token),
                        null,
                        null,
                        true
                    );
                    if (! ($up['success'] ?? false)) {
                        $base['error'] = 'script up: '.($up['error'] ?? 'error');

                        return $base;
                    }
                    $base['script_up'] = ($up['action'] ?? 'added') === 'updated' ? 'actualizado' : 'agregado';
                }

                if (! $tieneDown || $forzarScripts) {
                    $down = $this->mikrotik->upsertSystemScript(
                        $router,
                        self::SCRIPT_DOWN,
                        $this->source('down', $token),
                        null,
                        null,
                        true
                    );
                    if (! ($down['success'] ?? false)) {
                        $base['error'] = 'script down: '.($down['error'] ?? 'error');

                        return $base;
                    }
                    $base['script_down'] = ($down['action'] ?? 'added') === 'updated' ? 'actualizado' : 'agregado';
                }
            }

            foreach ($pendientesPerfil as $p) {
                $set = $this->mikrotik->setPppProfile($router, $p['id'], [
                    'on-up' => self::SCRIPT_UP,
                    'on-down' => self::SCRIPT_DOWN,
                ]);
                if ($set['success'] ?? false) {
                    $base['perfiles_ok'][] = $p['nombre'];
                } else {
                    $base['perfiles_omitidos'][] = $p['nombre'].' (error al asignar)';
                }
            }

            $base['success'] = true;

            return $base;
        } catch (Throwable $e) {
            $base['error'] = $e->getMessage();

            return $base;
        }
    }

    private function esScriptInfinity(string $nombre): bool
    {
        $nombre = trim($nombre);

        return $nombre === self::SCRIPT_UP || $nombre === self::SCRIPT_DOWN;
    }

    private function nuevoWebhookToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (Router::where('webhook_token', $token)->exists());

        return $token;
    }
}
