<?php

namespace App\Services\Ubnt;

class WstalistParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $jsonStations = $this->parseJson($raw);
        if ($jsonStations !== null) {
            return $jsonStations;
        }

        $stations = [];
        foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $this->esEncabezado($line)) {
                continue;
            }

            $parsed = $this->parseLineTexto($line);
            if ($parsed !== null) {
                $stations[] = $parsed;
            }
        }

        return $stations;
    }

    /**
     * @param  list<array<string, mixed>>  $stations
     */
    public function seleccionarActiva(array $stations, ?string $macEsperada = null): ?array
    {
        if ($stations === []) {
            return null;
        }

        if ($macEsperada !== null) {
            $macNorm = $this->normalizarMac($macEsperada);
            foreach ($stations as $station) {
                foreach (['mac_remota', 'mac', 'ap_mac'] as $key) {
                    if ($macNorm !== null && isset($station[$key]) && $this->normalizarMac((string) $station[$key]) === $macNorm) {
                        return $station;
                    }
                }
            }
        }

        foreach ($stations as $station) {
            if (! empty($station['selected'])) {
                return $station;
            }
        }

        return $stations[0];
    }

    /**
     * AirOS 8+ devuelve JSON (array de estaciones o un objeto).
     *
     * @return list<array<string, mixed>>|null
     */
    private function parseJson(string $raw): ?array
    {
        $json = $this->extraerJson($raw);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        if ($this->pareceEstacionJson($data)) {
            $parsed = $this->parseJsonStation($data);

            return $parsed !== null ? [$parsed] : null;
        }

        $stations = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parsed = $this->parseJsonStation($row);
            if ($parsed !== null) {
                $stations[] = $parsed;
            }
        }

        return $stations !== [] ? $stations : null;
    }

    private function extraerJson(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if ($raw[0] === '[' || $raw[0] === '{') {
            return $raw;
        }

        if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/', $raw, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function pareceEstacionJson(array $data): bool
    {
        return isset($data['mac']) || isset($data['signal']) || isset($data['noisefloor']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonStation(array $row): ?array
    {
        $signal = isset($row['signal']) ? (float) $row['signal'] : null;
        $noise = isset($row['noisefloor']) ? (float) $row['noisefloor'] : null;

        if ($signal === null && isset($row['remote']['signal'])) {
            $signal = (float) $row['remote']['signal'];
        }
        if ($noise === null && isset($row['remote']['noisefloor'])) {
            $noise = (float) $row['remote']['noisefloor'];
        }

        $tx = $row['tx'] ?? null;
        $rx = $row['rx'] ?? null;
        $txRx = null;
        if ($tx !== null || $rx !== null) {
            $txRx = round((float) ($tx ?? 0), 1).' / '.round((float) ($rx ?? 0), 1).' Mbps';
        }

        $airmax = is_array($row['airmax'] ?? null) ? $row['airmax'] : [];
        $capacity = null;
        if (isset($airmax['cb_capacity'])) {
            $capacity = $this->formatCapacityKbps((float) $airmax['cb_capacity']);
        } elseif (isset($row['cb_capacity_expect'])) {
            $capacity = $this->formatCapacityKbps((float) $row['cb_capacity_expect']);
        } elseif (isset($airmax['dl_capacity'])) {
            $capacity = $this->formatCapacityKbps((float) $airmax['dl_capacity']).' DL';
        }

        $ccq = null;
        if (isset($row['ccq'])) {
            $ccq = (int) round((float) $row['ccq']);
        } elseif (isset($row['dl_linkscore'])) {
            $ccq = (int) round((float) $row['dl_linkscore']);
        } elseif (isset($airmax['rx']['cinr'])) {
            $ccq = (int) round((float) $airmax['rx']['cinr']);
        }

        $distanceRaw = $row['distance'] ?? ($row['remote']['distance'] ?? null);
        $distance = $this->formatDistance($distanceRaw);

        $macRemota = isset($row['mac']) ? strtoupper((string) $row['mac']) : null;
        $remote = is_array($row['remote'] ?? null) ? $row['remote'] : [];
        $apName = $row['name'] ?? ($remote['hostname'] ?? null);

        $snr = ($signal !== null && $noise !== null) ? round($signal - $noise, 1) : null;
        if ($snr === null && isset($airmax['rx']['cinr'])) {
            $snr = round((float) $airmax['rx']['cinr'], 1);
        }

        $chains = $this->calcularCadenasSenal($row, $signal);
        $chainDelta = $this->calcularDeltaCadenas($chains);

        return array_filter([
            'selected' => true,
            'mac_remota' => $macRemota,
            'ap_mac' => $macRemota,
            'mac' => $macRemota,
            'ap_name' => $apName,
            'signal_dbm' => $signal,
            'noise_floor_dbm' => $noise,
            'snr_db' => $snr,
            'ccq' => $ccq,
            'tx_rx_rate' => $txRx,
            'capacity' => $capacity,
            'distance' => $distance,
            'signal_chains' => $chains !== [] ? $chains : null,
            'chain_delta' => $chainDelta,
            'lastip' => $row['lastip'] ?? null,
            'uptime' => isset($row['uptime']) ? (int) $row['uptime'] : null,
            'dl_linkscore' => isset($row['dl_linkscore']) ? (int) round((float) $row['dl_linkscore']) : null,
            'ul_linkscore' => isset($row['ul_linkscore']) ? (int) round((float) $row['ul_linkscore']) : null,
            'remote_platform' => $remote['platform'] ?? null,
            'remote_version' => $remote['version'] ?? null,
            'formato' => 'json',
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Convierte chainrssi + signal + rssi interno a dBm por cadena (como UI Ubiquiti).
     *
     * @return list<array{chain: int, signal_dbm: float, rssi_raw: int}>
     */
    private function calcularCadenasSenal(array $row, ?float $signal): array
    {
        $chainRssi = $row['chainrssi'] ?? null;
        if (! is_array($chainRssi) || $signal === null) {
            return [];
        }

        $rssiInterno = isset($row['rssi']) ? (float) $row['rssi'] : null;
        $activos = array_values(array_filter($chainRssi, fn ($v) => (int) $v > 0));
        $maxChain = $activos !== [] ? max($activos) : null;

        $chains = [];
        foreach ($chainRssi as $idx => $valor) {
            $valor = (int) $valor;
            if ($valor <= 0) {
                continue;
            }

            if ($rssiInterno !== null) {
                $dbm = round($signal + $valor - $rssiInterno);
            } elseif ($maxChain !== null) {
                $dbm = round($signal - ($maxChain - $valor));
            } else {
                continue;
            }

            $chains[] = [
                'chain' => (int) $idx,
                'signal_dbm' => (float) $dbm,
                'rssi_raw' => $valor,
            ];
        }

        return $chains;
    }

    /**
     * @param  list<array{chain: int, signal_dbm: float}>  $chains
     */
    private function calcularDeltaCadenas(array $chains): ?int
    {
        if (count($chains) < 2) {
            return null;
        }

        return (int) abs(round($chains[0]['signal_dbm'] - $chains[1]['signal_dbm']));
    }

    private function formatCapacityKbps(float $kbps): string
    {
        if ($kbps >= 1000) {
            return round($kbps / 1000, 1).' Mbps';
        }

        return round($kbps).' Kbps';
    }

    private function formatDistance(mixed $metros): ?string
    {
        if ($metros === null || $metros === '') {
            return null;
        }

        $m = (float) $metros;
        if ($m >= 1000) {
            return round($m / 1000, 2).' km';
        }

        return round($m).' m';
    }

    private function esEncabezado(string $line): bool
    {
        $lower = strtolower($line);

        return str_contains($lower, 'mac')
            && (str_contains($lower, 'signal') || str_contains($lower, 'noise') || str_contains($lower, 'ccq'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLineTexto(string $line): ?array
    {
        $selected = str_starts_with($line, '*');
        $line = ltrim($line, '* ');

        if (! preg_match_all('/([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})/', $line, $macMatches)) {
            return null;
        }

        $macs = array_values(array_unique(array_map('strtoupper', $macMatches[1])));
        $macRemota = $macs[0] ?? null;
        $apMac = $macs[1] ?? null;

        $txRx = null;
        if (preg_match('/(\d+\s*\/\s*\d+\s*(?:Mbps|M)?|\d+\s*Mbps\s*\/\s*\d+\s*Mbps)/i', $line, $rateMatch)) {
            $txRx = trim($rateMatch[1]);
        }

        $capacity = null;
        if (preg_match('/\b(\d+\s*M)\b/i', $line, $capMatch) && ($txRx === null || ! str_contains($capMatch[1], '/'))) {
            $capacity = strtoupper(str_replace(' ', '', $capMatch[1]));
        }

        $distance = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(km|m)\b/i', $line, $distMatch)) {
            $distance = $distMatch[1].' '.strtolower($distMatch[2]);
        }

        $signal = null;
        $noise = null;
        $ccq = null;

        if (preg_match_all('/-?\d+(?:\.\d+)?/', $line, $numMatches)) {
            $numeros = array_map('floatval', $numMatches[0]);
            foreach ($numeros as $num) {
                if ($num >= -120 && $num <= -20 && $signal === null) {
                    $signal = $num;

                    continue;
                }
                if ($num >= -120 && $num <= -20 && $noise === null && $signal !== null) {
                    $noise = $num;

                    continue;
                }
                if ($num >= 0 && $num <= 100 && $ccq === null && ($signal !== null || $noise !== null)) {
                    $ccq = (int) round($num);
                }
            }
        }

        $snr = ($signal !== null && $noise !== null) ? round($signal - $noise, 1) : null;

        return array_filter([
            'selected' => $selected,
            'mac_remota' => $macRemota,
            'ap_mac' => $apMac,
            'mac' => $macRemota,
            'signal_dbm' => $signal,
            'noise_floor_dbm' => $noise,
            'snr_db' => $snr,
            'ccq' => $ccq,
            'tx_rx_rate' => $txRx,
            'capacity' => $capacity,
            'distance' => $distance,
            'formato' => 'texto',
            'raw_line' => $line,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function normalizarMac(?string $mac): ?string
    {
        if ($mac === null || trim($mac) === '') {
            return null;
        }

        $limpio = strtoupper(preg_replace('/[^0-9A-F]/', '', $mac) ?? '');
        if (strlen($limpio) !== 12) {
            return strtoupper(trim($mac));
        }

        return implode(':', str_split($limpio, 2));
    }
}
