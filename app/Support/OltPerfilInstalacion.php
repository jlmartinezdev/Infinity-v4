<?php

namespace App\Support;

class OltPerfilInstalacion
{
    public static function normalizarSerial(string $serial): string
    {
        $serial = strtoupper(trim($serial));

        return preg_replace('/[^A-Z0-9]/', '', $serial) ?? '';
    }

    /**
     * @return array{label: string, detalle: string, line: string, srv: string}|null
     */
    public static function perfil(string $modo): ?array
    {
        $modos = config('olt.perfiles_instalacion', []);
        $perfil = $modos[$modo] ?? null;
        if (! is_array($perfil)) {
            return null;
        }

        $line = trim((string) ($perfil['line'] ?? ''));
        $srv = trim((string) ($perfil['srv'] ?? ''));
        if (! self::nombrePerfilValido($line) || ! self::nombrePerfilValido($srv)) {
            return null;
        }

        return [
            'label' => (string) ($perfil['label'] ?? $modo),
            'detalle' => (string) ($perfil['detalle'] ?? ''),
            'line' => $line,
            'srv' => $srv,
        ];
    }

    /**
     * @return list<array{modo: string, label: string, detalle: string, line: string, srv: string}>
     */
    public static function listar(): array
    {
        $out = [];
        foreach (array_keys(config('olt.perfiles_instalacion', [])) as $modo) {
            $perfil = self::perfil((string) $modo);
            if ($perfil === null) {
                continue;
            }
            $out[] = ['modo' => (string) $modo] + $perfil;
        }

        return $out;
    }

    /**
     * @return array{pon_port: int, onu_index: int, serial: string, modelo: ?string}|null
     */
    public static function buscarEnSalida(string $output, string $serial): ?array
    {
        $serial = self::normalizarSerial($serial);
        if (strlen($serial) < 8) {
            return null;
        }

        if (! preg_match_all(
            '/GPON\s*0\s*\/\s*(\d+)\s*:\s*(\d+)([\s\S]*?)(?=GPON\s*0\s*\/\s*\d+\s*:|$)/i',
            $output,
            $matches,
            PREG_SET_ORDER
        )) {
            return null;
        }

        foreach ($matches as $match) {
            $cuerpo = (string) $match[3];
            if (! preg_match('/\b'.preg_quote($serial, '/').'\b/i', $cuerpo)) {
                continue;
            }

            $modelo = null;
            if (preg_match('/\b([A-Z]{2,}[A-Z0-9.+-]{2,})\b/', $cuerpo, $modeloMatch)) {
                $candidato = $modeloMatch[1];
                if (! in_array(strtolower($candidato), ['sn', 'unknown', 'default'], true)
                    && ! str_starts_with(strtolower($candidato), 'onu_profile')) {
                    $modelo = $candidato;
                }
            }

            return [
                'pon_port' => (int) $match[1],
                'onu_index' => (int) $match[2],
                'serial' => $serial,
                'modelo' => $modelo,
            ];
        }

        return null;
    }

    /**
     * @param  array{line: string, srv: string}  $perfil
     * @return list<string>
     */
    public static function comandos(int $pon, int $onu, array $perfil): array
    {
        return [
            "interface gpon 0/{$pon}",
            "onu {$onu} profile line name {$perfil['line']}",
            "onu {$onu} profile srv name {$perfil['srv']}",
        ];
    }

    public static function nombrePerfilValido(string $nombre): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_+.\-]{1,63}$/', $nombre);
    }
}
