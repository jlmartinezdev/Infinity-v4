<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\PortalClienteAccion;
use App\Models\Servicio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Historial breve de acciones del cliente en la app (portal).
 * La clave Wi‑Fi se guarda cifrada como backup para el staff; no se expone a la API portal.
 */
class PortalClienteAccionService
{
    public const MAX_POR_CLIENTE = 40;

    /**
     * @param  array{ssid?: string|null, wifi_id?: string|null, password?: string|null, source?: string|null}  $wifi
     */
    public function registrarWifi(Cliente $cliente, Servicio $servicio, array $wifi): void
    {
        $ssid = isset($wifi['ssid']) ? trim((string) $wifi['ssid']) : '';
        $ssid = $ssid !== '' ? mb_substr($ssid, 0, 32) : null;
        $password = isset($wifi['password']) && is_string($wifi['password']) && $wifi['password'] !== ''
            ? $wifi['password']
            : null;
        $cambiaSsid = $ssid !== null;
        $cambiaClave = $password !== null;
        if (! $cambiaSsid && ! $cambiaClave) {
            return;
        }

        $request = function_exists('request') ? request() : null;
        $ua = $request?->userAgent();
        if (is_string($ua) && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }

        try {
            PortalClienteAccion::query()->create([
                'cliente_id' => (int) $cliente->cliente_id,
                'servicio_id' => (int) $servicio->servicio_id,
                'tipo' => self::tipoWifi($cambiaSsid, $cambiaClave),
                'origen' => PortalClienteAccion::ORIGEN_APP,
                'source' => isset($wifi['source']) ? mb_substr((string) $wifi['source'], 0, 32) : null,
                'titulo' => self::tituloWifi($cambiaSsid, $cambiaClave, $ssid),
                'ssid' => $ssid,
                'wifi_id' => isset($wifi['wifi_id']) ? mb_substr((string) $wifi['wifi_id'], 0, 64) : null,
                'wifi_password' => $password,
                'ip_address' => $request?->ip(),
                'user_agent' => $ua,
                'created_at' => now(),
            ]);
            $this->podar((int) $cliente->cliente_id);
        } catch (Throwable $e) {
            Log::warning('[Portal acciones] no se pudo guardar historial Wi‑Fi', [
                'cliente_id' => $cliente->cliente_id,
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, PortalClienteAccion>
     */
    public function paraCliente(int $clienteId, int $limit = 15): Collection
    {
        return PortalClienteAccion::query()
            ->where('cliente_id', $clienteId)
            ->orderByDesc('id')
            ->limit(max(1, min(40, $limit)))
            ->get();
    }

    /**
     * Último backup de clave Wi‑Fi puesto desde la app (para el staff).
     *
     * @return array{ssid: string|null, password: string, changed_at: string, source: string|null}|null
     */
    public function backupWifiParaServicio(int $servicioId): ?array
    {
        $row = PortalClienteAccion::query()
            ->where('servicio_id', $servicioId)
            ->whereNotNull('wifi_password')
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return null;
        }

        $password = (string) $row->wifi_password;
        if ($password === '') {
            return null;
        }

        return [
            'ssid' => $row->ssid,
            'password' => $password,
            'changed_at' => $row->created_at?->format('d/m/Y H:i'),
            'source' => $row->source,
        ];
    }

    public static function tipoWifi(bool $cambiaSsid, bool $cambiaClave): string
    {
        if ($cambiaSsid && $cambiaClave) {
            return PortalClienteAccion::TIPO_WIFI_SSID_PASSWORD;
        }
        if ($cambiaSsid) {
            return PortalClienteAccion::TIPO_WIFI_SSID;
        }

        return PortalClienteAccion::TIPO_WIFI_PASSWORD;
    }

    public static function tituloWifi(bool $cambiaSsid, bool $cambiaClave, ?string $ssid): string
    {
        $nombre = $ssid ? ' «'.$ssid.'»' : '';
        if ($cambiaSsid && $cambiaClave) {
            return 'Cambió nombre y clave Wi‑Fi'.$nombre;
        }
        if ($cambiaSsid) {
            return 'Cambió nombre Wi‑Fi'.$nombre;
        }

        return 'Cambió clave Wi‑Fi'.$nombre;
    }

    private function podar(int $clienteId): void
    {
        $ids = PortalClienteAccion::query()
            ->where('cliente_id', $clienteId)
            ->orderByDesc('id')
            ->skip(self::MAX_POR_CLIENTE)
            ->take(200)
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        PortalClienteAccion::query()->whereIn('id', $ids)->delete();
    }
}
