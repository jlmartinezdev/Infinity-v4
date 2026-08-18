<?php

namespace App\Services\Ubnt;

class McaStatusParser
{
    /**
     * Interpreta mca-status (y opcionalmente líneas de system.cfg) de airOS.
     *
     * @return array<string, mixed>
     */
    public function parse(string $mcaStatus, string $systemCfg = ''): array
    {
        $kv = array_merge(
            $this->parseKeyValues($mcaStatus),
            $this->parseKeyValues($systemCfg),
        );

        $ssid = $this->primero($kv, [
            'essid', 'ssid', 'wireless.1.ssid', 'wpasupplicant.device.1.ssid',
        ]);
        $hostname = $this->primero($kv, [
            'devicename', 'hostname', 'resolv.host.1.name',
        ]);
        $modo = $this->normalizarModo($this->primero($kv, [
            'wlanmode', 'mode', 'wireless.1.mode', 'radio.1.mode',
        ]));
        $freq = $this->primero($kv, ['freq', 'frequency', 'radio.1.freq']);
        $chanbw = $this->primero($kv, ['channelwidth', 'chanbw', 'radio.1.chanbw']);
        $firmware = $this->primero($kv, ['firmwareversion', 'firmware', 'version']);
        $modelo = $this->primero($kv, ['platform', 'boardname', 'board']);
        $mac = $this->primero($kv, ['wlanmacaddress', 'lanmacaddress', 'deviceid', 'mac']);
        $uptime = $this->entero($this->primero($kv, ['uptime']));
        $estaciones = $this->entero($this->primero($kv, [
            'wlanconnections', 'connections', 'sta.count',
        ]));

        $canal = $this->primero($kv, ['channel', 'wlanchannel']);
        if ($canal === null && $freq !== null && is_numeric($freq)) {
            $canal = $this->canalDesdeMhz((int) $freq);
        }

        return [
            'hostname' => $hostname,
            'ssid' => $ssid,
            'modo' => $modo,
            'frecuencia' => $freq,
            'canal' => $canal,
            'chanbw' => $chanbw,
            'firmware' => $firmware,
            'modelo' => $modelo,
            'mac' => $mac,
            'uptime_segundos' => $uptime,
            'estaciones' => $estaciones,
            'extra' => array_filter([
                'noise' => $this->primero($kv, ['noise', 'noisefloor']),
                'ccq' => $this->primero($kv, ['ccq']),
                'cpu' => $this->primero($kv, ['cpuload', 'cpu']),
                'signal' => $this->primero($kv, ['signal']),
                'lan_ip' => $this->primero($kv, ['lanipaddress', 'deviceip', 'ipaddr']),
                'ieee_mode' => $this->primero($kv, ['radio.1.ieee_mode', 'ieee_mode']),
                'airmax' => $this->primero($kv, ['airmax', 'airmaxenabled']),
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValues(string $raw): array
    {
        // airOS 8 (p. ej. Rocket Prism) entrega varios pares en una sola línea:
        // deviceName=AP,deviceId=AA:BB:...,firmwareVersion=XC.qca955x...,platform=Rocket Prism 5AC Gen2
        $normalized = preg_replace('/,(?=[A-Za-z0-9._-]+=)/', "\n", $raw) ?? $raw;

        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $normalized) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = strtolower(trim($key));
            if (str_starts_with($key, 'status.')) {
                $key = substr($key, 7);
            }
            $value = trim($value, " \t\"'");
            if ($key === '' || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $kv
     * @param  list<string>  $keys
     */
    private function primero(array $kv, array $keys): ?string
    {
        foreach ($keys as $key) {
            $k = strtolower($key);
            if (isset($kv[$k]) && $kv[$k] !== '') {
                return $kv[$k];
            }
        }

        return null;
    }

    private function entero(?string $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizarModo(?string $modo): ?string
    {
        if ($modo === null || $modo === '') {
            return null;
        }

        $m = strtolower($modo);

        return match (true) {
            in_array($m, ['master', 'ap', 'ap-ptmp', 'ap-ptp', 'access-point'], true) => 'AP',
            in_array($m, ['managed', 'sta', 'sta-ptmp', 'sta-ptp', 'station'], true) => 'Estación',
            default => $modo,
        };
    }

    private function canalDesdeMhz(int $mhz): ?string
    {
        if ($mhz >= 2400 && $mhz <= 2500) {
            return (string) (int) round(($mhz - 2407) / 5);
        }
        if ($mhz >= 4900 && $mhz <= 5900) {
            return (string) (int) round(($mhz - 5000) / 5);
        }

        return null;
    }
}
