<?php

namespace App\Services;

use RuntimeException;

class NetworkPingService
{
    /**
     * @return array{
     *     success: bool,
     *     alive: bool,
     *     ip: string,
     *     packets: int,
     *     sent: int,
     *     received: int,
     *     lost: int,
     *     loss_pct: int,
     *     min_ms: int|null,
     *     max_ms: int|null,
     *     avg_ms: int|null,
     *     calidad: string,
     *     output: string,
     *     message: string
     * }
     */
    public function ping(string $ip, int $packets = 4): array
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('La dirección IP no es válida.');
        }

        $packets = max(1, min(10, $packets));
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $bin = \App\Services\Monitoreo\PingExecutor::binario($isWindows);

        if ($isWindows) {
            $cmd = sprintf('"%s" -n %d -w 2000 -4 %s', $bin, $packets, $ip);
        } else {
            $cmd = sprintf('%s -c %d -W 2 %s', $bin, $packets, escapeshellarg($ip));
        }

        $output = [];
        $exitCode = 1;
        @exec($cmd.' 2>&1', $output, $exitCode);

        $text = trim(implode("\n", $output));
        $alive = $this->detectAlive($text, $isWindows) || $exitCode === 0;
        $stats = $this->parseEstadisticas($text, $packets, $alive);

        return [
            'success' => true,
            'alive' => $alive,
            'ip' => $ip,
            'packets' => $packets,
            'sent' => $stats['sent'],
            'received' => $stats['received'],
            'lost' => $stats['lost'],
            'loss_pct' => $stats['loss_pct'],
            'min_ms' => $stats['min_ms'],
            'max_ms' => $stats['max_ms'],
            'avg_ms' => $stats['avg_ms'],
            'calidad' => $this->textoCalidad($alive, $stats),
            'output' => $text !== '' ? $text : '(sin salida del comando ping)',
            'message' => $alive
                ? "El equipo {$ip} responde."
                : "El equipo {$ip} no respondió.",
        ];
    }

    /**
     * @return array{sent: int, received: int, lost: int, loss_pct: int, min_ms: int|null, max_ms: int|null, avg_ms: int|null}
     */
    private function parseEstadisticas(string $text, int $packets, bool $alive): array
    {
        $sent = $packets;
        $received = null;
        $lost = null;
        $lossPct = null;
        $min = $max = $avg = null;

        if (preg_match('/(?:Sent|enviados)\s*=\s*(\d+)/i', $text, $m)) {
            $sent = (int) $m[1];
        }
        if (preg_match('/(?:Received|recibidos)\s*=\s*(\d+)/i', $text, $m)) {
            $received = (int) $m[1];
        }
        if (preg_match('/(?:Lost|perdidos)\s*=\s*(\d+)/i', $text, $m)) {
            $lost = (int) $m[1];
        }
        if (preg_match('/\((\d+)\s*%\s*(?:loss|perdidos|p[eé]rdida)/i', $text, $m)) {
            $lossPct = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+packets transmitted,\s+(\d+)\s+(?:received|packets received)/i', $text, $m)) {
            $sent = (int) $m[1];
            $received = (int) $m[2];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*%\s+packet loss/i', $text, $m)) {
            $lossPct = (int) round((float) $m[1]);
        }
        if (preg_match('/(?:Minimum|M[ií]nimo)\s*=\s*([\d.]+)\s*ms/i', $text, $m)) {
            $min = (int) round((float) $m[1]);
        }
        if (preg_match('/(?:Maximum|M[aá]ximo)\s*=\s*([\d.]+)\s*ms/i', $text, $m)) {
            $max = (int) round((float) $m[1]);
        }
        if (preg_match('/(?:Average|Media|Promedio)\s*=\s*([\d.]+)\s*ms/i', $text, $m)) {
            $avg = (int) round((float) $m[1]);
        }
        if (preg_match('/rtt min\/avg\/max(?:\/[a-z]+)?\s*=\s*([\d.]+)\/([\d.]+)\/([\d.]+)/i', $text, $m)) {
            $min = (int) round((float) $m[1]);
            $avg = (int) round((float) $m[2]);
            $max = (int) round((float) $m[3]);
        }

        if ($received === null) {
            $received = preg_match_all('/(?:Reply from|Respuesta desde|\d+\s+bytes from)/i', $text) ?: 0;
            if (! $alive) {
                $received = 0;
            }
        }
        if ($lost === null) {
            $lost = max(0, $sent - $received);
        }
        if ($lossPct === null) {
            $lossPct = $sent > 0 ? (int) round(($lost / $sent) * 100) : ($alive ? 0 : 100);
        }

        return [
            'sent' => $sent,
            'received' => $received,
            'lost' => $lost,
            'loss_pct' => $lossPct,
            'min_ms' => $min,
            'max_ms' => $max,
            'avg_ms' => $avg,
        ];
    }

    private function textoCalidad(bool $alive, array $stats): string
    {
        if (! $alive || (int) $stats['received'] === 0) {
            return 'No hay respuesta. Revisá si el equipo está encendido, la IP o si el servicio está cortado.';
        }
        $loss = (int) $stats['loss_pct'];
        $avg = $stats['avg_ms'];
        if ($loss >= 50) {
            return 'Responde, pero se pierde más de la mitad de los paquetes. La conexión está inestable.';
        }
        if ($loss > 0) {
            return 'Responde con pérdida de paquetes. Puede verse cortes o lentitud.';
        }
        if ($avg === null) {
            return 'Responde bien: llegaron todos los paquetes.';
        }
        if ($avg <= 15) {
            return 'Responde bien y la latencia es baja.';
        }
        if ($avg <= 40) {
            return 'Responde bien. Latencia normal para el enlace.';
        }
        if ($avg <= 80) {
            return 'Responde, pero la latencia es alta.';
        }

        return 'Responde, pero la latencia es muy alta. Conviene revisar el enlace.';
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
