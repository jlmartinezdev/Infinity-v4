<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Huawei\HuaweiOnuService;
use Illuminate\Support\Str;

class WifiSoporteService
{
    public function __construct(
        private readonly GenieAcsService $genie,
        private readonly HuaweiOnuService $huawei,
    ) {}

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
