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

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $this->asegurarPathWindows($isWindows);

        $bin = self::binario($isWindows);
        if ($isWindows) {
            $cmd = sprintf('"%s" -n 1 -w %d -4 %s', $bin, $timeoutMs, $ip);
        } else {
            $waitSec = max(1, (int) ceil($timeoutMs / 1000));
            $cmd = sprintf('%s -c 1 -W %d %s', $bin, $waitSec, $ip);
        }

        $output = [];
        $exitCode = 1;
        @exec($cmd.' 2>&1', $output, $exitCode);
        $text = implode("\n", $output);

        $ok = $exitCode === 0 || $this->detectaRespuesta($text, $isWindows);
        if (! $ok) {
            return [
                'ok' => false,
                'latency_ms' => null,
                'error' => $this->resumirErrorPing($text),
            ];
        }

        return [
            'ok' => true,
            'latency_ms' => $this->parseLatenciaMs($text, $isWindows),
            'error' => null,
        ];
    }

    /**
     * Ruta al ejecutable ping. En Windows no depende del PATH del servicio.
     */
    public static function binario(bool $isWindows = true): string
    {
        if (! $isWindows) {
            return 'ping';
        }

        $root = rtrim((string) (getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows'), '\\/');
        $candidates = [
            $root.'\\System32\\ping.exe',
            $root.'\\Sysnative\\ping.exe',
            $root.'\\SysWOW64\\ping.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'ping';
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

    /**
     * Los servicios Windows a veces arrancan sin System32 en PATH (ping no se encuentra).
     */
    private function asegurarPathWindows(bool $isWindows): void
    {
        if (! $isWindows) {
            return;
        }

        $root = rtrim((string) (getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows'), '\\/');
        $system32 = $root.'\\System32';
        $path = (string) getenv('PATH');
        if (stripos($path, $system32) !== false) {
            return;
        }

        $nuevo = $system32.';'.$root.';'.$path;
        putenv('PATH='.$nuevo);
        $_ENV['PATH'] = $nuevo;
    }

    private function detectaRespuesta(string $text, bool $isWindows): bool
    {
        if ($text === '') {
            return false;
        }
        if (preg_match('/\bTTL[=:\s]\d+/i', $text)) {
            return true;
        }
        if (! $isWindows) {
            return (bool) preg_match('/\d+\s+bytes from/i', $text);
        }

        return (bool) preg_match('/respuesta desde|reply from/i', $text)
            && ! preg_match('/100%\s*perdidos|100%\s*loss|agotado el tiempo|request timed out/i', $text);
    }

    private function parseLatenciaMs(string $text, bool $isWindows): ?int
    {
        if (preg_match('/tiempo\s*[=<]\s*([\d.]+)\s*m/i', $text, $m)) {
            return (int) round((float) $m[1]);
        }
        if (preg_match('/time[=<]\s*([\d.]+)\s*m/i', $text, $m)) {
            return (int) round((float) $m[1]);
        }

        if ($isWindows && preg_match('/(?:Promedio|Average|Media)\s*=\s*([\d.]+)\s*ms/i', $text, $m)) {
            return (int) round((float) $m[1]);
        }

        return null;
    }

    private function resumirErrorPing(string $text): string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'no se reconoce') || str_contains($lower, 'not recognized') || str_contains($lower, 'not found')) {
            return 'ping.exe no encontrado (PATH)';
        }
        if (str_contains($lower, 'general failure') || str_contains($lower, 'error general')) {
            return 'Error general (interfaz de red)';
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'expir') || str_contains($lower, 'timeout')) {
            return 'Sin respuesta (timeout)';
        }
        if (str_contains($lower, 'host de destino no disponible') || str_contains($lower, 'destination host unreachable')) {
            return 'Host inalcanzable';
        }
        if (str_contains($lower, 'could not find host') || str_contains($lower, 'could not resolve')) {
            return 'Host no encontrado';
        }

        return $text !== '' ? 'Sin respuesta' : 'Sin respuesta (sin salida de ping)';
    }
}
