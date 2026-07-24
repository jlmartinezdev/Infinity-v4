<?php

namespace App\Support;

/**
 * Presenta datos_diagnostico de tickets creados desde la app cliente.
 */
class TicketDiagnosticoPresenter
{
    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>|null  $datos
     */
    public function __construct(?array $datos)
    {
        $this->datos = is_array($datos) ? $datos : [];
    }

    public function tieneDatos(): bool
    {
        return $this->datos !== [];
    }

    /**
     * Vista estructurada para Blade / JSON del modal de tickets.
     *
     * @return list<array<string, mixed>>
     */
    public function secciones(): array
    {
        if (! $this->tieneDatos()) {
            return [];
        }

        $secciones = [];

        $resumen = $this->metricas([
            'speed' => ['Velocidad medida', $this->formatMbps($this->datos['speed'] ?? null)],
            'isWifiConnected' => ['WiFi conectado', $this->formatBool($this->datos['isWifiConnected'] ?? null)],
            'isVpnActive' => ['VPN activa', $this->formatBool($this->datos['isVpnActive'] ?? null)],
        ]);
        if ($resumen !== []) {
            $secciones[] = ['titulo' => 'Resumen', 'tipo' => 'metricas', 'items' => $resumen];
        }

        $wifi = $this->metricas([
            'ssid' => ['Red WiFi (SSID)', $this->formatTexto($this->datos['ssid'] ?? null)],
            'rssi' => ['Señal RSSI', $this->formatRssi($this->datos['rssi'] ?? null)],
            'level' => ['Nivel de señal', $this->formatPorcentaje($this->datos['level'] ?? null)],
            'linkSpeed' => ['Velocidad enlace WiFi', $this->formatMbps($this->datos['linkSpeed'] ?? null)],
            'frequency' => ['Frecuencia', $this->formatFrecuencia($this->datos['frequency'] ?? null)],
        ]);
        if ($wifi !== []) {
            $secciones[] = ['titulo' => 'WiFi', 'tipo' => 'metricas', 'items' => $wifi];
        }

        $redLocal = $this->metricas([
            'localIp' => ['IP local', $this->formatTexto($this->datos['localIp'] ?? null)],
            'gatewayIp' => ['Gateway', $this->formatTexto($this->datos['gatewayIp'] ?? null)],
            'dnsServers' => ['Servidores DNS', $this->formatLista($this->datos['dnsServers'] ?? null)],
            'dnsResolveTime' => ['Resolución DNS', $this->formatMs($this->datos['dnsResolveTime'] ?? null)],
        ]);
        if ($redLocal !== []) {
            $secciones[] = ['titulo' => 'Red local', 'tipo' => 'metricas', 'items' => $redLocal];
        }

        $localPing = $this->formatPing('Ping al gateway (red local)', $this->datos['localPing'] ?? null);
        if ($localPing !== null) {
            $secciones[] = $localPing;
        }

        $wanPing = $this->formatPing('Ping a Internet (WAN)', $this->datos['wanPing'] ?? null);
        if ($wanPing !== null) {
            $secciones[] = $wanPing;
        }

        $traceroute = $this->formatTraceroute($this->datos['traceroute'] ?? null);
        if ($traceroute !== null) {
            $secciones[] = $traceroute;
        }

        $dispositivo = $this->metricas([
            'deviceBrand' => ['Marca', $this->formatTexto($this->datos['deviceBrand'] ?? null)],
            'deviceModel' => ['Modelo', $this->formatTexto($this->datos['deviceModel'] ?? null)],
            'androidVersion' => ['Android', $this->formatTexto($this->datos['androidVersion'] ?? null)],
            'batteryLevel' => ['Batería', $this->formatPorcentaje($this->datos['batteryLevel'] ?? null)],
            'thermalStatus' => ['Estado térmico', $this->formatTermico($this->datos['thermalStatus'] ?? null)],
        ]);
        if ($dispositivo !== []) {
            $secciones[] = ['titulo' => 'Dispositivo', 'tipo' => 'metricas', 'items' => $dispositivo];
        }

        $ubicacion = $this->formatUbicacion(
            $this->datos['latitude'] ?? null,
            $this->datos['longitude'] ?? null
        );
        if ($ubicacion !== null) {
            $secciones[] = $ubicacion;
        }

        $resto = $this->metricasRestantes($secciones);
        if ($resto !== []) {
            $secciones[] = ['titulo' => 'Otros datos', 'tipo' => 'metricas', 'items' => $resto];
        }

        return $secciones;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'secciones' => $this->secciones(),
            'json' => $this->datos,
        ];
    }

    /**
     * @param  array<string, array{0: string, 1: ?array{value: string, tone?: string}}>  $mapa
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private function metricas(array $mapa): array
    {
        $items = [];
        foreach ($mapa as $dato) {
            [$label, $formatted] = $dato;
            if ($formatted === null) {
                continue;
            }
            $item = ['label' => $label, 'value' => $formatted['value']];
            if (! empty($formatted['tone'])) {
                $item['tone'] = $formatted['tone'];
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $secciones
     * @return list<array{label: string, value: string}>
     */
    private function metricasRestantes(array $secciones): array
    {
        $conocidas = [
            'ssid', 'level', 'rssi', 'gatewayIp', 'localIp', 'linkSpeed', 'frequency',
            'isVpnActive', 'isWifiConnected', 'dnsServers', 'dnsResolveTime',
            'deviceBrand', 'deviceModel', 'androidVersion', 'batteryLevel', 'thermalStatus',
            'localPing', 'wanPing', 'traceroute', 'latitude', 'longitude', 'speed',
            'download', 'upload', 'ping', 'latency', 'ip', 'gateway', 'dns', 'mac', 'bssid',
            'link_speed', 'connected', 'timestamp', 'captured_at',
        ];

        $items = [];
        foreach ($this->datos as $clave => $valor) {
            if (in_array((string) $clave, $conocidas, true)) {
                continue;
            }
            if (is_array($valor)) {
                $items[] = [
                    'label' => ucfirst(str_replace('_', ' ', (string) $clave)),
                    'value' => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];

                continue;
            }
            $items[] = [
                'label' => ucfirst(str_replace('_', ' ', (string) $clave)),
                'value' => is_bool($valor) ? ($valor ? 'Sí' : 'No') : (string) $valor,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $ping
     * @return array<string, mixed>|null
     */
    private function formatPing(string $titulo, mixed $ping): ?array
    {
        if (! is_array($ping) || $ping === []) {
            return null;
        }

        return [
            'titulo' => $titulo,
            'tipo' => 'ping',
            'items' => $this->metricas([
                'avg' => ['Promedio', $this->formatMs($ping['avg'] ?? null)],
                'min' => ['Mínimo', $this->formatMs($ping['min'] ?? null)],
                'max' => ['Máximo', $this->formatMs($ping['max'] ?? null)],
                'jitter' => ['Jitter', $this->formatMs($ping['jitter'] ?? null)],
                'loss' => ['Pérdida de paquetes', $this->formatPorcentaje($ping['loss'] ?? null)],
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $saltos
     * @return array<string, mixed>|null
     */
    private function formatTraceroute(mixed $saltos): ?array
    {
        if (! is_array($saltos) || $saltos === []) {
            return null;
        }

        $filas = [];
        foreach ($saltos as $salto) {
            if (! is_array($salto)) {
                continue;
            }
            $hostname = trim((string) ($salto['hostname'] ?? ''));
            $ip = trim((string) ($salto['ip'] ?? ''));
            $destino = $hostname !== '' && $hostname !== $ip
                ? $hostname.' ('.$ip.')'
                : ($ip !== '' ? $ip : '—');

            $filas[] = [
                'ttl' => (string) ($salto['ttl'] ?? '—'),
                'destino' => $destino,
                'latencia' => $this->formatMs($salto['latency'] ?? null)['value'] ?? '—',
                'marca' => trim((string) ($salto['brand'] ?? '')) ?: '—',
                'alcanzado' => ! empty($salto['reached']),
            ];
        }

        if ($filas === []) {
            return null;
        }

        return [
            'titulo' => 'Traceroute',
            'tipo' => 'traceroute',
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatUbicacion(mixed $lat, mixed $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        return [
            'titulo' => 'Ubicación',
            'tipo' => 'ubicacion',
            'lat' => $latF,
            'lng' => $lngF,
            'texto' => number_format($latF, 6, '.', '').', '.number_format($lngF, 6, '.', ''),
            'maps_url' => 'https://www.google.com/maps?q='.rawurlencode($latF.','.$lngF),
        ];
    }

    /**
     * @return array{value: string, tone?: string}|null
     */
    private function formatRssi(mixed $valor): ?array
    {
        if (! is_numeric($valor)) {
            return null;
        }

        $rssi = (float) $valor;
        $tone = 'neutral';
        if ($rssi >= -55) {
            $tone = 'good';
        } elseif ($rssi >= -70) {
            $tone = 'ok';
        } elseif ($rssi >= -80) {
            $tone = 'warn';
        } else {
            $tone = 'bad';
        }

        return ['value' => number_format($rssi, 0, '.', '').' dBm', 'tone' => $tone];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatMbps(mixed $valor): ?array
    {
        if (! is_numeric($valor)) {
            return null;
        }

        return ['value' => number_format((float) $valor, 2, '.', '').' Mbps'];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatMs(mixed $valor): ?array
    {
        if (! is_numeric($valor)) {
            return null;
        }

        $num = (float) $valor;
        $decimals = fmod($num, 1.0) === 0.0 ? 0 : 1;

        return ['value' => number_format($num, $decimals, '.', '').' ms'];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatPorcentaje(mixed $valor): ?array
    {
        if (! is_numeric($valor)) {
            return null;
        }

        return ['value' => number_format((float) $valor, 0, '.', '').'%'];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatFrecuencia(mixed $valor): ?array
    {
        if (! is_numeric($valor)) {
            return null;
        }

        $mhz = (float) $valor;
        $banda = $mhz >= 5000 ? '5 GHz' : '2.4 GHz';

        return ['value' => number_format($mhz, 0, '.', '').' MHz ('.$banda.')'];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatBool(mixed $valor): ?array
    {
        if (! is_bool($valor)) {
            return null;
        }

        return ['value' => $valor ? 'Sí' : 'No'];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatTexto(mixed $valor): ?array
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return ['value' => $texto];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatLista(mixed $valor): ?array
    {
        if (! is_array($valor) || $valor === []) {
            return null;
        }

        $items = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $valor
        ), fn ($v) => $v !== ''));

        if ($items === []) {
            return null;
        }

        return ['value' => implode(', ', $items)];
    }

    /**
     * @return array{value: string}|null
     */
    private function formatTermico(mixed $valor): ?array
    {
        $texto = $this->formatTexto($valor);
        if ($texto === null) {
            return null;
        }

        $mapa = [
            'FRIO' => 'Frío',
            'TIBIO' => 'Tibio',
            'CALIENTE' => 'Caliente',
            'CRITICO' => 'Crítico',
            'CRÍTICO' => 'Crítico',
        ];
        $key = mb_strtoupper($texto['value']);

        return ['value' => $mapa[$key] ?? ucfirst(mb_strtolower($texto['value']))];
    }
}
