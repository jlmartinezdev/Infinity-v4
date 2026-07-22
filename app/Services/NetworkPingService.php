<?php

namespace App\Services;

use RuntimeException;

class NetworkPingService
{
    /**
     * @return array{success: bool, alive: bool, ip: string, packets: int, output: string, message: string}
     */
    public function ping(string $ip, int $packets = 4): array
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('La dirección IP no es válida.');
        }

        $packets = max(1, min(10, $packets));
        $isWindows = PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            // -n count, -w timeout ms por respuesta
            $cmd = sprintf('ping -n %d -w 2000 %s', $packets, escapeshellarg($ip));
        } else {
            // -c count, -W timeout segundos
            $cmd = sprintf('ping -c %d -W 2 %s', $packets, escapeshellarg($ip));
        }

        $output = [];
        $exitCode = 1;
        @exec($cmd.' 2>&1', $output, $exitCode);

        $text = trim(implode("\n", $output));
        $alive = $this->detectAlive($text, $isWindows) || $exitCode === 0;

        return [
            'success' => true,
            'alive' => $alive,
            'ip' => $ip,
            'packets' => $packets,
            'output' => $text !== '' ? $text : '(sin salida del comando ping)',
            'message' => $alive
                ? "El equipo {$ip} responde al ping."
                : "El equipo {$ip} no respondió al ping.",
        ];
    }

    private function detectAlive(string $output, bool $isWindows): bool
    {
        if ($output === '') {
            return false;
        }

        if (preg_match('/\bTTL[=:\s]\d+/i', $output)) {
            return true;
        }

        if ($isWindows) {
            return (bool) preg_match('/respuesta desde|reply from/i', $output)
                && ! preg_match('/100%\s*perdidos|100%\s*loss|agotado el tiempo|request timed out/i', $output);
        }

        return (bool) preg_match('/\d+\s+bytes from/i', $output);
    }
}
