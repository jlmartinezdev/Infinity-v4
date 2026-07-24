<?php

namespace App\Services\Ubnt;

class DhcpLeasesParser
{
    /**
     * Parsea salida de cat /tmp/dhcpd.leases (Ubiquiti AirOS).
     *
     * @return list<array{expires_at: int|null, expires_human: string|null, mac: string, ip: string, hostname: string|null, client_id: string|null}>
     */
    public function parse(string $raw): array
    {
        $leases = [];

        foreach (preg_split('/\R/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $lease = $this->parseLine($line);
            if ($lease !== null) {
                $leases[] = $lease;
            }
        }

        return $leases;
    }

    /**
     * @return array{expires_at: int|null, expires_human: string|null, mac: string, ip: string, hostname: string|null, client_id: string|null}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! preg_match('/^(\d+)\s+([0-9a-f:]{11,17})\s+(\d+\.\d+\.\d+\.\d+)\s+(\S+)(?:\s+(\S+))?/i', $line, $m)) {
            return null;
        }

        $expires = (int) $m[1];
        $hostname = $m[4];
        if ($hostname === '*' || strtoupper($hostname) === 'UNKNOWN') {
            $hostname = null;
        }

        return [
            'expires_at' => $expires > 0 ? $expires : null,
            'expires_human' => $this->formatExpires($expires),
            'mac' => strtolower($m[2]),
            'ip' => $m[3],
            'hostname' => $hostname,
            'client_id' => $m[5] ?? null,
        ];
    }

    private function formatExpires(int $timestamp): ?string
    {
        if ($timestamp <= 0) {
            return null;
        }

        return date('d/m/Y H:i:s', $timestamp);
    }
}
