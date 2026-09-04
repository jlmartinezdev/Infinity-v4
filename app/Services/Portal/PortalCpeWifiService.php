<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Support\CpeInventario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Clave Wi‑Fi del CPE del cliente portal — vía GenieACS (TR-069).
 * No lee la clave actual. Ubiquiti / OLT V-SOL no aplican.
 */
class PortalCpeWifiService
{
    public const REASON_NO_SERVICE = 'no_service';

    public const REASON_NO_ACS = 'no_acs';

    public const REASON_SSH_CPE = 'ssh_cpe';

    public const REASON_ACS_NOT_CONFIGURED = 'acs_not_configured';

    public const REASON_CPE_NOT_FOUND = 'cpe_not_found';

    public const REASON_ACS_UNREACHABLE = 'acs_unreachable';

    public const REASON_NO_SSID = 'no_ssid';

    public const REASON_RATE_LIMITED = 'rate_limited';

    public const HINT_MANUAL = 'Entrá al router en 192.168.1.1 para cambiar la clave Wi‑Fi.';

    public function __construct(
        private readonly GenieAcsService $acs,
    ) {}

    /**
     * @return array{
     *   can_change: bool,
     *   source: string|null,
     *   servicio_id: int|null,
     *   password_readable: false,
     *   pending_inform: bool,
     *   ssids: list<array{id: string, ssid: string, enabled: bool, band: string|null}>,
     *   reason: string|null,
     *   hint: string|null
     * }
     */
    public function estado(Cliente $cliente, ?int $servicioId = null): array
    {
        try {
            @set_time_limit(45);

            $servicio = $this->resolverServicio($cliente, $servicioId);
            if (! $servicio) {
                return $this->noSoportado(self::REASON_NO_SERVICE, null);
            }

            $gate = $this->motivoNoAcs($servicio);
            if ($gate !== null) {
                return $this->noSoportado($gate, (int) $servicio->servicio_id);
            }

            $resumen = $this->acs->resumen($servicio);
            if (! ($resumen['success'] ?? false)) {
                return $this->noSoportado(
                    $this->reasonDesdeAcs($resumen['message'] ?? ''),
                    (int) $servicio->servicio_id
                );
            }

            $ssids = self::mapWifiForPortal($resumen['wifi'] ?? []);
            $enabled = array_values(array_filter($ssids, fn (array $n) => (bool) ($n['enabled'] ?? false)));
            if ($enabled === []) {
                return $this->noSoportado(self::REASON_NO_SSID, (int) $servicio->servicio_id);
            }

            $crOk = (bool) ($resumen['connection_request_ok'] ?? true);

            return [
                'can_change' => true,
                'can_rename' => true,
                'source' => 'tr069_acs',
                'servicio_id' => (int) $servicio->servicio_id,
                'password_readable' => false,
                'pending_inform' => ! $crOk,
                'ssids' => $ssids,
                'reason' => null,
                'hint' => $crOk
                    ? null
                    : 'El cambio se aplica cuando el router se reporte al ACS (puede tardar unos minutos).',
            ];
        } catch (Throwable $e) {
            Log::warning('[Portal CPE WiFi] estado', [
                'cliente_id' => $cliente->cliente_id,
                'error' => $e->getMessage(),
            ]);

            return $this->noSoportado(self::REASON_ACS_UNREACHABLE, null);
        }
    }

    /**
     * @return array{success: bool, http: int, message: string, data: array<string, mixed>}
     */
    public function cambiar(
        Cliente $cliente,
        ?string $password = null,
        ?string $wifiId = null,
        ?int $servicioId = null,
        ?string $ssid = null
    ): array {
        $password = $password !== null && $password !== '' ? (string) $password : null;
        $ssid = $ssid !== null ? trim($ssid) : null;
        $ssid = ($ssid !== null && $ssid !== '') ? $ssid : null;

        if ($password === null && $ssid === null) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'Indicá una clave o un nombre de red Wi‑Fi.',
                'data' => ['reason' => 'invalid_input'],
            ];
        }

        if ($password !== null && (strlen($password) < 8 || strlen($password) > 63)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'La clave Wi‑Fi debe tener entre 8 y 63 caracteres.',
                'data' => ['reason' => 'invalid_password'],
            ];
        }

        if ($ssid !== null) {
            $ssidError = self::validarSsid($ssid);
            if ($ssidError !== null) {
                return [
                    'success' => false,
                    'http' => 422,
                    'message' => $ssidError,
                    'data' => ['reason' => 'invalid_ssid'],
                ];
            }
        }

        $rateKey = 'portal-cpe-wifi:'.$cliente->cliente_id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return [
                'success' => false,
                'http' => 429,
                'message' => 'Demasiados cambios de Wi‑Fi. Probá de nuevo en unos minutos.',
                'data' => [
                    'reason' => self::REASON_RATE_LIMITED,
                    'retry_after_seconds' => $seconds,
                ],
            ];
        }

        $estado = $this->estado($cliente, $servicioId);
        if (! ($estado['can_change'] ?? false)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => $estado['hint'] ?: 'No se puede cambiar el Wi‑Fi desde la app.',
                'data' => $estado,
            ];
        }

        $servicio = Servicio::query()->find($estado['servicio_id']);
        if (! $servicio || (int) $servicio->cliente_id !== (int) $cliente->cliente_id) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'Servicio no encontrado.',
                'data' => $this->noSoportado(self::REASON_NO_SERVICE, null),
            ];
        }

        $wifiId = ($wifiId !== null && $wifiId !== '') ? $wifiId : 'all';

        try {
            @set_time_limit(45);
            $result = $this->acs->setWifi($servicio, $password, $ssid, $wifiId);
        } catch (Throwable $e) {
            Log::warning('[Portal CPE WiFi] cambiar', [
                'cliente_id' => $cliente->cliente_id,
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'http' => 502,
                'message' => 'No se pudo contactar el ACS para cambiar el Wi‑Fi.',
                'data' => array_merge($estado, [
                    'can_change' => false,
                    'reason' => self::REASON_ACS_UNREACHABLE,
                ]),
            ];
        }

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => (string) ($result['message'] ?? 'No se pudo encolar el cambio de Wi‑Fi.'),
                'data' => array_merge($estado, [
                    'reason' => self::REASON_NO_SSID,
                ]),
            ];
        }

        RateLimiter::hit($rateKey, 3600);

        Log::info('[Portal CPE WiFi] cambio encolado', [
            'cliente_id' => $cliente->cliente_id,
            'servicio_id' => $servicio->servicio_id,
            'wifi_id' => $wifiId,
            'cambia_clave' => $password !== null,
            'cambia_ssid' => $ssid !== null,
        ]);

        $ssidsOut = $estado['ssids'];
        if ($ssid !== null) {
            $ssidsOut = array_map(static function (array $n) use ($ssid, $wifiId): array {
                if ($wifiId === 'all' || ($n['id'] ?? '') === $wifiId) {
                    $n['ssid'] = $ssid;
                }

                return $n;
            }, $ssidsOut);
        }

        return [
            'success' => true,
            'http' => 200,
            'message' => (string) ($result['message'] ?? 'Cambio de Wi‑Fi encolado.'),
            'data' => [
                'queued' => true,
                'applied_now' => ! ($estado['pending_inform'] ?? false),
                'source' => 'tr069_acs',
                'servicio_id' => (int) $servicio->servicio_id,
                'wifi_id' => $wifiId,
                'ssid' => $ssid,
                'ssids' => $ssidsOut,
                'pending_inform' => (bool) ($estado['pending_inform'] ?? false),
            ],
        ];
    }

    public static function validarSsid(string $ssid): ?string
    {
        $len = mb_strlen($ssid);
        if ($len < 1 || $len > 32) {
            return 'El nombre Wi‑Fi debe tener entre 1 y 32 caracteres.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $ssid) === 1) {
            return 'El nombre Wi‑Fi no puede tener caracteres de control.';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $wifi
     * @return list<array{id: string, ssid: string, enabled: bool, band: string|null}>
     */
    public static function mapWifiForPortal(array $wifi): array
    {
        $out = [];
        foreach ($wifi as $net) {
            $id = trim((string) ($net['id'] ?? ''));
            $ssid = trim((string) ($net['ssid'] ?? ''));
            if ($id === '' || $ssid === '') {
                continue;
            }
            $band = $net['band'] ?? null;
            $out[] = [
                'id' => $id,
                'ssid' => $ssid,
                'enabled' => (bool) ($net['enabled'] ?? false),
                'band' => is_string($band) && $band !== '' ? $band : null,
            ];
        }

        return $out;
    }

    public static function hintPara(string $reason): string
    {
        return match ($reason) {
            self::REASON_NO_ACS, self::REASON_SSH_CPE, self::REASON_NO_SERVICE => self::HINT_MANUAL,
            self::REASON_NO_SSID => 'El router no reportó redes Wi‑Fi activas. Probá más tarde o cambiá la clave en 192.168.1.1.',
            self::REASON_CPE_NOT_FOUND => 'El router todavía no se reportó al sistema. Probá más tarde o usá 192.168.1.1.',
            self::REASON_ACS_UNREACHABLE, self::REASON_ACS_NOT_CONFIGURED => 'No se pudo consultar el router ahora. Probá más tarde o usá 192.168.1.1.',
            self::REASON_RATE_LIMITED => 'Demasiados cambios. Esperá un rato.',
            default => self::HINT_MANUAL,
        };
    }

    private function resolverServicio(Cliente $cliente, ?int $servicioId): ?Servicio
    {
        $base = Servicio::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO);

        if ($servicioId !== null && $servicioId > 0) {
            return (clone $base)->where('servicio_id', $servicioId)->first();
        }

        $servicios = $base
            ->orderByRaw("CASE estado WHEN 'A' THEN 0 WHEN 'S' THEN 1 WHEN 'C' THEN 2 ELSE 3 END")
            ->orderBy('servicio_id')
            ->get();

        $acs = $servicios->first(fn (Servicio $s) => CpeInventario::usaAcs($s));
        if ($acs) {
            return $acs;
        }

        return $servicios->first();
    }

    private function motivoNoAcs(Servicio $servicio): ?string
    {
        if (CpeInventario::usaSshCpe($servicio)) {
            return self::REASON_SSH_CPE;
        }
        if (! $this->acs->configured()) {
            return self::REASON_ACS_NOT_CONFIGURED;
        }
        if (! CpeInventario::usaAcs($servicio)) {
            return self::REASON_NO_ACS;
        }

        return null;
    }

    private function reasonDesdeAcs(string $message): string
    {
        $m = strtolower($message);
        if (str_contains($m, 'ssh')) {
            return self::REASON_SSH_CPE;
        }
        if (str_contains($m, 'no está configurado') || str_contains($m, 'genieacs no')) {
            return self::REASON_ACS_NOT_CONFIGURED;
        }
        if (str_contains($m, 'contactar') || str_contains($m, 'nbi')) {
            return self::REASON_ACS_UNREACHABLE;
        }

        return self::REASON_CPE_NOT_FOUND;
    }

    /**
     * @return array{
     *   can_change: false,
     *   source: null,
     *   servicio_id: int|null,
     *   password_readable: false,
     *   pending_inform: false,
     *   ssids: list<empty>,
     *   reason: string,
     *   hint: string
     * }
     */
    private function noSoportado(string $reason, ?int $servicioId): array
    {
        return [
            'can_change' => false,
            'can_rename' => false,
            'source' => null,
            'servicio_id' => $servicioId,
            'password_readable' => false,
            'pending_inform' => false,
            'ssids' => [],
            'reason' => $reason,
            'hint' => self::hintPara($reason),
        ];
    }
}
