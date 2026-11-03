<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\Ubnt\UbntAntenaService;
use Illuminate\Support\Str;

class WifiSoporteService
{
    public function __construct(
        private readonly GenieAcsService $genie,
        private readonly HuaweiOnuService $huawei,
        private readonly UbntAntenaService $ubnt,
    ) {}

    /**
     * SSIDs actuales del CPE (ACS). Vacío si no hay dato.
     *
     * @return list<string>
     */
    public function ssidsActuales(Servicio $servicio): array
    {
        $out = [];
        try {
            $resumen = $this->genie->resumen($servicio);
            $lista = ($resumen['success'] ?? false) && is_array($resumen['ssids'] ?? null) ? $resumen['ssids'] : [];
            foreach ($lista as $s) {
                $s = trim((string) $s);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
            if ($out === [] && filled($resumen['ssid'] ?? null)) {
                $out[] = trim((string) $resumen['ssid']);
            }
        } catch (\Throwable) {
            $out = [];
        }

        if ($out === []) {
            $out = $this->ssidPorAirOs($servicio);
        }

        return array_values(array_unique($out));
    }

    /**
     * Antenas con acceso SSH: el SSID está en airOS, no en GenieACS.
     *
     * @return list<string>
     */
    private function ssidPorAirOs(Servicio $servicio): array
    {
        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [];
        }

        try {
            $ap = $this->ubnt->consultarApAirOs($ip);
            $ssid = trim((string) ($ap['ssid'] ?? ''));
            if (($ap['success'] ?? false) === true && $ssid !== '') {
                return [$ssid];
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @return array{reply: string, aplicado: bool, clave: string, ssid: ?string}
     */
    public function preparar(Servicio $servicio, string $texto, bool $aplicar): array
    {
        $clave = SoporteTexto::claveWifi($texto) ?: Str::password(8, symbols: false);
        $ssid = SoporteTexto::nombreWifi($texto);

        if (! $aplicar) {
            return [
                'reply' => FraseCliente::wifiPendiente($clave, $ssid),
                'aplicado' => false,
                'clave' => $clave,
                'ssid' => $ssid,
            ];
        }

        $ok = false;
        try {
            $acs = $this->genie->setWifi($servicio, $clave, $ssid);
            $ok = (bool) ($acs['success'] ?? false);
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok && $ssid) {
            try {
                $hw = $this->huawei->cambiarWifi($servicio, $ssid, $clave);
                $ok = (bool) ($hw['success'] ?? false);
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return [
            'reply' => $ok
                ? FraseCliente::wifiAplicado($clave, $ssid)
                : FraseCliente::wifiPendiente($clave, $ssid),
            'aplicado' => $ok,
            'clave' => $clave,
            'ssid' => $ssid,
        ];
    }
}
