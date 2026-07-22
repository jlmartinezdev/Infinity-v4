<?php

namespace App\Services\Monitoreo;

class PingExecutor
{
    /**
     * Ejecuta un ping ICMP a una IPv4.
     *
     * @return array{ok: bool, latency_ms: int|null, error: string|null}
     */
    public function ping(string $ip, ?int $timeoutMs = null): array
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ok' => false, 'latency_ms' => null, 'error' => 'IP inválida'];
        }

        $timeoutMs = $timeoutMs ?? (int) config('monitoreo.timeout_ms', 2000);
        $timeoutMs = max(500, min($timeoutMs, 10000));

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $cmd = sprintf('ping -n 1 -w %d %s', $timeoutMs, $ip);
        } else {
            $waitSec = max(1, (int) ceil($timeoutMs / 1000));
            $cmd = sprintf('ping -c 1 -W %d %s', $waitSec, $ip);
        }

        $output = [];
        $exitCode = 1;
        @exec($cmd, $output, $exitCode);
        $text = implode("\n", $output);

        if ($exitCode !== 0) {
            return [
                'ok' => false,
                'latency_ms' => null,
                'error' => $this->resumirErrorPing($text),
            ];
        }

        $latency = $this->parseLatenciaMs($text, $isWindows);

        return [
            'ok' => true,
            'latency_ms' => $latency,
            'error' => null,
        ];
    }

    public function ipEsPinguable(?string $ip): bool
    {
        $ip = trim((string) $ip);
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (in_array($ip, ['0.0.0.0', '127.0.0.1', '255.255.255.255'], true)) {
            return false;
        }

        return true;
    }

    private function parseLatenciaMs(string $text, bool $isWindows): ?int
    {
        if (preg_match('/time[=<]\s*([\d.]+)\s*ms/i', $text, $m)) {
            return (int) round((float) $m[1]);
        }

        if ($isWindows && preg_match('/(?:Promedio|Average)\s*=\s*([\d.]+)\s*ms/i', $text, $m)) {
            return (int) round((float) $m[1]);
        }

        return null;
    }

    private function resumirErrorPing(string $text): string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'timed out') || str_contains($lower, 'expir') || str_contains($lower, 'timeout')) {
            return 'Sin respuesta (timeout)';
        }
        if (str_contains($lower, 'host de destino no disponible') || str_contains($lower, 'destination host unreachable')) {
            return 'Host inalcanzable';
        }
        if (str_contains($lower, 'could not find host') || str_contains($lower, 'could not resolve')) {
            return 'Host no encontrado';
        }

        return 'Sin respuesta';
    }
}
