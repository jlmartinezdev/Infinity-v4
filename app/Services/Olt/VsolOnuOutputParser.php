<?php

namespace App\Services\Olt;

/**
 * Parsea salidas CLI VSOL GPON (show onu info / show onu state / tablas por PON).
 */
class VsolOnuOutputParser
{
    /** @var array<int, string> */
    private const ESTADOS_ONLINE = ['working', 'work', 'online', 'up', 'active', 'normal'];

    /** @var array<int, string> */
    private const ESTADOS_OFFLINE = ['offline', 'down', 'deactive', 'deactivated', 'deactivate', 'deregister', 'deregistered', 'registering'];

    /** @var array<int, string> */
    private const ESTADOS_ALARMA = ['los', 'dyinggasp', 'dying-gasp', 'auth-fail', 'authfail', 'config-fail'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $onuInfoOutput, string $onuStateOutput = ''): array
    {
        $onus = [];
        $combined = $this->normalizeCliBlob($onuInfoOutput."\n".$onuStateOutput);

        foreach ([$onuInfoOutput, $onuStateOutput, $combined] as $block) {
            if (trim($block) === '') {
                continue;
            }
            $this->parseBlock($this->normalizeCliBlob($block), $onus);
        }

        if ($onus === []) {
            $this->parseGponBlobFallback($combined, $onus);
        }

        return array_values($onus);
    }

    public function parseOnuDescOutput(string $output): ?string
    {
        $output = $this->stripAnsi($output);
        if (trim($output) === '') {
            return null;
        }

        if (preg_match('/(?:description|desc(?:ription)?)\s*[:=]\s*(.+)$/im', $output, $m)) {
            $desc = $this->limpiarDescripcion(trim($m[1]));
            if ($desc !== null) {
                return $desc;
            }
        }

        // VSOL suele responder: "desc PEDRO_CIBILS" (sin ':')
        if (preg_match('/^\s*desc(?:ription)?\s+(.+)$/im', $output, $m)) {
            $desc = $this->limpiarDescripcion(trim($m[1]));
            if ($desc !== null) {
                return $desc;
            }
        }

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $this->isSeparatorLine($line)) {
                continue;
            }
            if (preg_match('/^(show\s+onu|gpon-olt|configure|interface)\b/i', $line)) {
                continue;
            }
            if (preg_match('/^\s*desc(?:ription)?\s+(.+)$/i', $line, $m)) {
                $desc = $this->limpiarDescripcion(trim($m[1]));
                if ($desc !== null) {
                    return $desc;
                }
                continue;
            }
            if (preg_match('/^(none|null|n\/a|empty|desc(?:ription)?)$/i', $line)) {
                continue;
            }

            // Una sola palabra/línea de descripción (evitar tokens de comando: show, onu…)
            if ($this->tokenPareceDescripcion($line) || str_contains($line, ' ') || str_contains($line, '_')) {
                $desc = $this->limpiarDescripcion($line);
                if ($desc !== null && ! preg_match('/^(show|onu|optical|info|interface|gpon)\b/i', $desc)) {
                    return $desc;
                }
            }
        }

        return null;
    }

    /**
     * @return array{rx_power_dbm?: float, tx_power_dbm?: float}
     */
    public function parseOnuOpticalInfoOutput(string $output): array
    {
        $output = $this->stripAnsi($output);
        $data = [];

        if (preg_match('/rx[^:\n]*[:=]\s*(-?\d+(?:\.\d+)?)/i', $output, $m)) {
            $rx = $this->normalizeRxDbm((float) $m[1]);
            if ($rx !== null) {
                $data['rx_power_dbm'] = $rx;
            }
        } elseif ($rx = $this->extractRxPower($output)) {
            $data['rx_power_dbm'] = $rx;
        }

        if (preg_match('/tx[^:\n]*[:=]\s*(-?\d+(?:\.\d+)?)/i', $output, $m)) {
            $tx = $this->normalizeTxDbm((float) $m[1]);
            if ($tx !== null) {
                $data['tx_power_dbm'] = $tx;
            }
        } elseif ($tx = $this->extractTxPower($output)) {
            $data['tx_power_dbm'] = $tx;
        }

        return $data;
    }

    /**
     * Una fila parseada representa una ONU registrada en el OLT (no un slot vacío).
     *
     * @param  array<string, mixed>  $row
     */
    public function filaEsOnuRegistrada(array $row): bool
    {
        if (! empty($row['serial'])) {
            return true;
        }

        if (! empty($row['modelo'])) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseOptical(string $output): array
    {
        if (trim($output) === '') {
            return [];
        }

        $onus = [];
        $this->parseBlock($this->normalizeCliBlob($output), $onus);

        return array_values($onus);
    }

    /**
     * @param  array<string, array<string, mixed>>  $onus
     */
    private function parseBlock(string $output, array &$onus): void
    {
        if (trim($output) === '') {
            return;
        }

        $currentSlot = 0;
        $currentPort = null;

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $rawLine) {
            foreach ($this->expandOnuRecordSegments($rawLine) as $line) {
                $line = trim($line);
                if ($line === '' || $this->isSeparatorLine($line)) {
                    continue;
                }

                if ($pon = $this->extractPonFromLine($line)) {
                    [$currentSlot, $currentPort] = $pon;
                }

                if ($this->isNoiseLine($line)) {
                    continue;
                }

                if ($this->parseGponOnuTokenLine($line, $currentSlot, $currentPort, $onus)) {
                    continue;
                }

                if ($currentPort === null) {
                    continue;
                }

                $this->parseTableRow($line, $currentSlot, $currentPort, $onus);
            }
        }
    }

    /**
     * VSOL concatena registros: GPON0/1:1 ... o 1/1/1:1 ...
     *
     * @return array<int, string>
     */
    private function expandOnuRecordSegments(string $rawLine): array
    {
        $line = trim($rawLine);
        if ($line === '') {
            return [$line];
        }

        if (! preg_match_all('/(?:GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+)/i', $line, $found) || count($found[0]) < 2) {
            return [$line];
        }

        $parts = preg_split('/(?=GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+)/i', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || count($parts) < 2) {
            return [$line];
        }

        return array_values(array_filter(
            array_map('trim', $parts),
            fn ($p) => preg_match('/(?:GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+)/i', $p)
        ));
    }

    /**
     * @deprecated Usar expandOnuRecordSegments()
     *
     * @return array<int, string>
     */
    private function expandGponSegments(string $rawLine): array
    {
        return $this->expandOnuRecordSegments($rawLine);
    }

    /**
     * Escaneo directo del blob completo cuando el parser línea a línea no encuentra nada.
     *
     * @param  array<string, array<string, mixed>>  $onus
     */
    private function parseGponBlobFallback(string $blob, array &$onus): void
    {
        $blob = $this->normalizeCliBlob($blob);

        $contextSlot = 0;
        $contextPort = null;
        if (preg_match('/PON\s+(\d+)\/(\d+)/i', $blob, $ponMatch)) {
            $contextSlot = (int) $ponMatch[1];
            $contextPort = (int) $ponMatch[2];
        }

        if (preg_match_all(
            '/GPON(\d+)\/(\d+):(\d+)\s+(.*?)(?=GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+|PON\s+\d+\/\d+|$)/is',
            $blob,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $segment = 'GPON'.$m[1].'/'.$m[2].':'.$m[3].' '.trim($m[4]);
                $this->ingestGponSegment($segment, (int) $m[1], (int) $m[2], (int) $m[3], $onus);
            }
        }

        if (preg_match_all(
            '/(\d+)\/(\d+)\/(\d+):(\d+)\s+(.*?)(?=\d+\/\d+\/\d+:\d+|GPON\d+\/\d+:\d+|PON\s+\d+\/\d+|$)/is',
            $blob,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $slot = $contextPort !== null ? $contextSlot : (int) $m[1];
                $port = $contextPort ?? (int) $m[3];
                $segment = $m[1].'/'.$m[2].'/'.$m[3].':'.$m[4].' '.trim($m[5]);
                $this->ingestGponSegment($segment, $slot, $port, (int) $m[4], $onus);
            }
        }
    }

    private function normalizeCliBlob(string $output): string
    {
        $output = $this->stripAnsi($output);
        $output = str_replace("\r", "\n", $output);
        $output = preg_replace('/\bconfigure terminal\b/i', "\n", $output) ?? $output;
        $output = preg_replace('/(?=PON\s+\d+\/\d+\b)/i', "\n", $output) ?? $output;
        $output = preg_replace('/(?=GPON\d+\/\d+:\d+)/i', "\n", $output) ?? $output;
        $output = preg_replace('/(?=\d+\/\d+\/\d+:\d+)/', "\n", $output) ?? $output;
        $output = preg_replace('/\s+-{5,}\s*/', "\n", $output) ?? $output;
        $output = preg_replace('/\bshow onu state\s+OnuIndex\b[^\n]*/i', "\n", $output) ?? $output;
        $output = preg_replace('/\bshow onu info\s+Onuindex\b[^\n]*/i', "\n", $output) ?? $output;
        $output = preg_replace('/\bshow onu opm-diag\b[^\n]*/i', "\n", $output) ?? $output;

        return $output;
    }

    /**
     * @param  array<string, array<string, mixed>>  $onus
     */
    private function ingestGponSegment(string $line, int $slot, int $port, int $onuIndex, array &$onus): void
    {
        $tokens = $this->tokenize($line);
        $serial = $this->extractSerial($line);
        $modelo = $this->extractModelo($line);

        $data = array_filter([
            'serial' => $serial,
            'modelo' => $modelo,
            'descripcion' => $this->extractDescriptionFromTokens($tokens, $serial, $modelo),
            'estado' => $this->extractEstadoFromTokens($tokens),
            'rx_power_dbm' => $this->extractRxPower($line),
            'tx_power_dbm' => $this->extractTxPower($line),
        ], fn ($v) => $v !== null && $v !== '');

        if ($data === [] || (! isset($data['serial']) && ! isset($data['estado']) && ! isset($data['modelo']) && ! isset($data['rx_power_dbm']) && ! isset($data['tx_power_dbm']))) {
            return;
        }

        $this->upsertOnu($onus, $slot, $port, $onuIndex, $data);
    }

    /**
     * @param  array<string, array<string, mixed>>  $onus
     */
    private function parseGponOnuTokenLine(string $line, int $slot, ?int $port, array &$onus): bool
    {
        if (! preg_match('/(?:^|\s)(GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+|\d+\/\d+:\d+)/i', $line, $m)) {
            return false;
        }

        $parsed = $this->parseOnuIndexFromToken($m[1], $slot, $port);
        if ($parsed === null) {
            return false;
        }

        $this->ingestGponSegment($line, $parsed['slot'], $parsed['port'], $parsed['onu_index'], $onus);

        return true;
    }

    /**
     * @return array{slot:int,port:int,onu_index:int}|null
     */
    private function parseOnuIndexFromToken(string $token, int $contextSlot = 0, ?int $contextPort = null): ?array
    {
        $token = trim($token);

        if (preg_match('/^GPON(\d+)\/(\d+):(\d+)$/i', $token, $m)) {
            return [
                'slot' => (int) $m[1],
                'port' => (int) $m[2],
                'onu_index' => (int) $m[3],
            ];
        }

        if (preg_match('/^(\d+)\/(\d+)\/(\d+):(\d+)$/', $token, $m)) {
            return [
                'slot' => $contextPort !== null ? $contextSlot : (int) $m[1],
                'port' => $contextPort ?? (int) $m[3],
                'onu_index' => (int) $m[4],
            ];
        }

        if (preg_match('/^(\d+)\/(\d+):(\d+)$/', $token, $m)) {
            return [
                'slot' => $contextPort !== null ? $contextSlot : (int) $m[1],
                'port' => $contextPort ?? (int) $m[2],
                'onu_index' => (int) $m[3],
            ];
        }

        if (preg_match('/^\d+$/', $token)) {
            if ($contextPort === null) {
                return null;
            }

            return [
                'slot' => $contextSlot,
                'port' => $contextPort,
                'onu_index' => (int) $token,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $onus
     */
    private function parseTableRow(string $line, int $slot, int $port, array &$onus): void
    {
        if ($this->isHeaderLine($line)) {
            return;
        }

        $tokens = $this->tokenize($line);
        if ($tokens === []) {
            return;
        }

        $parsed = $this->parseOnuIndexFromToken($tokens[0], $slot, $port);
        if ($parsed === null) {
            return;
        }

        $onuIndex = $parsed['onu_index'];
        if ($onuIndex < 1 || $onuIndex > 128) {
            return;
        }

        $slot = $parsed['slot'];
        $port = $parsed['port'];

        $serial = $this->extractSerial($line);
        $modelo = $this->extractModelo($line);
        $estado = $this->extractEstadoFromTokens($tokens);

        $data = array_filter([
            'serial' => $serial,
            'modelo' => $modelo,
            'descripcion' => $this->extractDescriptionFromTokens($tokens, $serial, $modelo),
            'estado' => $estado,
            'rx_power_dbm' => $this->extractRxPower($line),
        ], fn ($v) => $v !== null && $v !== '');

        if ($data === [] || (! isset($data['serial']) && ! isset($data['estado']) && ! isset($data['modelo']) && ! isset($data['rx_power_dbm']) && ! isset($data['tx_power_dbm']))) {
            return;
        }

        $this->upsertOnu($onus, $slot, $port, $onuIndex, $data);
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private function extractPonFromLine(string $line): ?array
    {
        // GPON0/1:3 es una ONU, no un encabezado de puerto
        if (preg_match('/GPON\d+\/\d+:\d+/i', $line)) {
            return null;
        }

        if (preg_match('/GPON(\d+)\/(\d+)/i', $line, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/PON(?:\s+port)?\s*:?\s*(\d+)\/(\d+)/i', $line, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/^(\d+)\/(\d+)\s*:?\s*$/', $line, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/^port\s+(\d+)\/(\d+)/i', $line, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $line): array
    {
        $line = preg_replace('/\s{2,}/', ' ', $line) ?? $line;

        return array_values(array_filter(preg_split('/\s+/', trim($line)) ?: [], fn ($t) => $t !== ''));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function extractEstadoFromTokens(array $tokens): ?string
    {
        $found = null;
        foreach ($tokens as $token) {
            if ($this->tokenEsEstado($token)) {
                $found = $this->normalizeEstado($token);
            }
        }

        return $found;
    }

    private function tokenEsEstado(string $token): bool
    {
        $t = strtolower(str_replace('_', '-', trim($token)));

        return in_array($t, array_merge(self::ESTADOS_ONLINE, self::ESTADOS_OFFLINE, self::ESTADOS_ALARMA), true);
    }

    private function isSeparatorLine(string $line): bool
    {
        return (bool) preg_match('/^-{3,}$|^={3,}$/', $line);
    }

    private function isHeaderLine(string $line): bool
    {
        if (preg_match('/^show\s+onu\s+(info|state|opm-diag)\b/i', $line)) {
            return true;
        }

        return (bool) preg_match(
            '/^(onu\s*index|onuindex|onuid|index|sn|serial|state|phase|phase\s*state|admin|omcc|omt|desc|description|config|channel|model|profile|mode|authinfo|pon\s*port|----)/i',
            $line
        );
    }

    private function isNoiseLine(string $line): bool
    {
        if (preg_match('/^configure terminal\b/i', $line)) {
            return true;
        }

        if (preg_match('/^show\s+onu\s+(info|state|opm-diag)\b/i', $line)) {
            return true;
        }

        if (preg_match('/^(onuindex|onuindex admin|model|profile|mode|authinfo)(\s|$)/i', $line)) {
            return true;
        }

        if (preg_match('/^pon\s+\d+\/\d+\s+show\s+onu\s+(info|state)\b/i', $line)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $onus
     * @param  array<string, mixed>  $data
     */
    private function upsertOnu(array &$onus, int $slot, int $port, int $onuIndex, array $data): void
    {
        $key = $slot.'/'.$port.':'.$onuIndex;
        $existing = $onus[$key] ?? [
            'pon_slot' => $slot,
            'pon_port' => $port,
            'pon_key' => $slot.'/'.$port,
            'onu_index' => $onuIndex,
            'estado' => 'unknown',
        ];

        foreach ($data as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'estado' && $value === 'unknown' && $existing['estado'] !== 'unknown') {
                continue;
            }
            $existing[$field] = $value;
        }

        $onus[$key] = $existing;
    }

    private function extractSerial(string $line): ?string
    {
        if (preg_match('/\bsn\s+([A-Z0-9]{8,16})\b/i', $line, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\b(GPON[A-F0-9]{8,16}|VSOL[A-F0-9]{8,16}|HWTC[A-F0-9]{8,16})\b/i', $line, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\b([A-Z]{4}[A-F0-9]{8,12})\b/i', $line, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\b([A-F0-9]{12,16})\b/i', $line, $m) && ! preg_match('/^\d+(\.\d+)?$/', $m[1])) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\bSN\s*[:=]\s*([A-Z0-9]{8,16})\b/i', $line, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function extractModelo(string $line): ?string
    {
        if (! preg_match('/(?:GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+)\s+(.+)$/i', $line, $m)) {
            return null;
        }

        foreach ($this->tokenize($m[1]) as $token) {
            if (preg_match('/^(default|sn|enable|disable|profile|mode|authinfo)$/i', $token)) {
                break;
            }
            if (preg_match('/^(EG\d+|HS\d+|HG\d+|AN\d+|EchoLife|Huawei)/i', $token)) {
                return $token;
            }
            if (preg_match('/^[A-Z]{2,4}\d+[A-Z0-9-]*$/i', $token)) {
                return $token;
            }
        }

        return null;
    }

    private function extractDescription(string $line): ?string
    {
        if (preg_match('/(?:name|desc(?:ription)?)\s*[:=]\s*([^,|]+)/i', $line, $m)) {
            return $this->limpiarDescripcion(trim($m[1]));
        }

        $serial = $this->extractSerial($line);
        $modelo = $this->extractModelo($line);

        return $this->extractDescriptionFromTokens($this->tokenize($line), $serial, $modelo);
    }

    /**
     * Busca descripción en tokens (formato web/CLI: Status Descriptions Model Profile Mode AuthInfo).
     * Ej: GPON0/1:1 Online PEDRO_CIBILS AN5506-01-A default Sn FHTT...
     *
     * @param  array<int, string>  $tokens
     */
    private function extractDescriptionFromTokens(array $tokens, ?string $serial, ?string $modelo): ?string
    {
        foreach ($tokens as $i => $token) {
            if ($i === 0 && preg_match('/(?:GPON|\d+\/\d+)/i', $token)) {
                continue;
            }
            if ($serial !== null && strcasecmp($token, $serial) === 0) {
                continue;
            }
            if ($modelo !== null && strcasecmp($token, $modelo) === 0) {
                continue;
            }
            if ($this->tokenEsEstado($token)) {
                continue;
            }
            if ($this->tokenEsRuidoDescripcion($token)) {
                continue;
            }
            if (! $this->tokenPareceDescripcion($token)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private function tokenEsRuidoDescripcion(string $token): bool
    {
        return (bool) preg_match(
            '/^(enable|disable|enabled|disabled|succeeded|failed|initial|match|mismatch|default|sn|profile|mode|authinfo|onu_profile(_\d+)?|show|onu|optical|info|interface|gpon|desc|description|\d+\(gpon\))$/i',
            $token
        );
    }

    private function tokenPareceDescripcion(string $token): bool
    {
        if (strlen($token) < 3 || preg_match('/^\d+(\.\d+)?$/', $token)) {
            return false;
        }
        if ($this->tokenEsRuidoDescripcion($token) || $this->tokenEsEstado($token)) {
            return false;
        }

        // Nombres tipo PEDRO_CIBILS / Cliente_X
        if (preg_match('/^[A-Z0-9][A-Z0-9_-]{2,}$/i', $token) && (str_contains($token, '_') || str_contains($token, '-'))) {
            // Evitar confundir con modelos AN5506-01-A / EG8145V5
            if (preg_match('/^(EG|HS|HG|AN|MA|V)\d+/i', $token)) {
                return false;
            }

            return true;
        }

        // Descripción alfanumérica sin guion bajo (menos fiable): exigir longitud mayor
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{5,}$/', $token) && ! preg_match('/^(EG|HS|HG|AN|MA|V)\d+/i', $token)) {
            return true;
        }

        return false;
    }

    private function limpiarDescripcion(string $desc): ?string
    {
        $desc = trim($desc, " \t\"'");
        if ($desc === '' || preg_match('/^(none|null|n\/a|empty)$/i', $desc)) {
            return null;
        }
        if ($this->tokenEsEstado($desc) || $this->tokenEsRuidoDescripcion($desc)) {
            return null;
        }

        return $desc;
    }

    private function extractRxPower(string $line): ?float
    {
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*dBm/i', $line, $m)) {
            return $this->normalizeRxDbm((float) $m[1]);
        }

        if (preg_match('/(?:rx|receive)(?:\s*power)?\s*[:=\s]+(-?\d+(?:\.\d+)?)/i', $line, $m)) {
            return $this->normalizeRxDbm((float) $m[1]);
        }

        return $this->extractOpticalPowerFromTokens($this->tokenize($line), true);
    }

    private function extractTxPower(string $line): ?float
    {
        if (preg_match('/(?:tx|transmit)(?:\s*power)?\s*[:=\s]+(-?\d+(?:\.\d+)?)/i', $line, $m)) {
            return $this->normalizeTxDbm((float) $m[1]);
        }

        $tokens = $this->tokenize($line);
        $rx = $this->extractOpticalPowerFromTokens($tokens, true);
        $foundRx = false;
        foreach ($tokens as $token) {
            if (! preg_match('/^(-?\d+(?:\.\d+)?)$/', $token, $m)) {
                continue;
            }
            $val = (float) $m[1];
            $tx = $this->normalizeTxDbm($val);
            if ($tx === null) {
                continue;
            }
            if ($foundRx && $rx !== null && abs($tx - $rx) > 0.001) {
                return $tx;
            }
            $foundRx = true;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function extractOpticalPowerFromTokens(array $tokens, bool $rxOnly = false): ?float
    {
        foreach ($tokens as $i => $token) {
            if ($i === 0 && preg_match('/(?:GPON|\d+\/)/i', $token)) {
                continue;
            }
            if (preg_match('/^(show|onu|optical|info|interface|gpon|desc)$/i', $token)) {
                continue;
            }
            if (! preg_match('/^(-?\d+(?:\.\d+)?)$/', $token, $m)) {
                continue;
            }
            $val = (float) $m[1];
            $rx = $this->normalizeRxDbm($val);
            if ($rx !== null) {
                return $rx;
            }
            if (! $rxOnly) {
                $tx = $this->normalizeTxDbm($val);
                if ($tx !== null) {
                    return $tx;
                }
            }
        }

        return null;
    }

    private function normalizeRxDbm(float $val): ?float
    {
        // Potencia RX en el OLT es casi siempre negativa; evita confundir con el índice ONU (1, 2, 10…).
        if ($val > 0 || $val < -50) {
            return null;
        }

        return $val;
    }

    private function normalizeTxDbm(float $val): ?float
    {
        if ($val < -10 || $val > 10) {
            return null;
        }

        return $val;
    }

    private function normalizeEstado(?string $estado): string
    {
        $e = strtolower(str_replace('_', '-', trim((string) $estado)));

        if (in_array($e, self::ESTADOS_ONLINE, true)) {
            return 'working';
        }
        if (in_array($e, self::ESTADOS_OFFLINE, true)) {
            return 'offline';
        }
        if ($e === 'dying-gasp') {
            return 'dyinggasp';
        }
        if (in_array($e, self::ESTADOS_ALARMA, true)) {
            return $e === 'authfail' ? 'auth-fail' : $e;
        }

        return $e !== '' ? $e : 'unknown';
    }

    /**
     * Parsea salida de: show mac address-table address FC1B:D1C2:8C15
     *
     * VLAN: 199
     * MAC Address: fc1b:d1c2:8c15
     * Type: Dynamic
     * Port: GPON0/07
     * ONU ID: 7
     *
     * @return array{mac: string, vlan: ?int, pon_port: ?int, onu_index: ?int, type: ?string, raw: string}|null
     */
    public function parseMacAddressLookup(string $output, ?string $macEsperada = null): ?array
    {
        $output = $this->stripAnsi($output);
        if (trim($output) === '') {
            return null;
        }

        // Espacios unicode / NBSP / tabs → espacio; dos puntos fullwidth → :
        $normalized = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\t]+/u', ' ', $output) ?? $output;
        $normalized = str_replace(["\xEF\xBC\x9A", '：'], ':', $normalized);
        // Solo colapsar relleno INMEDIATAMENTE antes de ':': "ONU ID         : 7" → "ONU ID: 7"
        // (no usar espacios como separador suelto: rompería "ONU ID" → "ONU: ID")
        $normalized = preg_replace('/^(\s*[A-Za-z][A-Za-z0-9 ]*?)\s*[.\s]*:\s*/m', '$1: ', $normalized) ?? $normalized;

        $vlan = null;
        $mac = null;
        $type = null;
        $ponPort = null;
        $onuIndex = null;

        if (preg_match('/VLAN\s*:\s*(\d+)/i', $normalized, $m)) {
            $vlan = (int) $m[1];
        }
        if (preg_match('/MAC\s*Address\s*:\s*([0-9A-Fa-f:.\-]+)/i', $normalized, $m)) {
            $mac = $this->normalizarMacHex($m[1]);
        } elseif (preg_match('/\b([0-9A-Fa-f]{4}:[0-9A-Fa-f]{4}:[0-9A-Fa-f]{4})\b/', $normalized, $m)) {
            $mac = $this->normalizarMacHex($m[1]);
        } elseif (preg_match('/([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}/', $normalized, $m)) {
            $mac = $this->normalizarMacHex($m[0]);
        }
        if (preg_match('/Type\s*:\s*(\S+)/i', $normalized, $m)) {
            $type = $m[1];
        }

        // Port … GPON0/1:29  (algunas firmwares incluyen ONU en Port)
        if (preg_match('/Port[^\n\r]*?GPON\s*0\s*[\/]\s*0*(\d+)\s*:\s*(\d+)/i', $normalized, $m)
            || preg_match('/\bGPON\s*0\s*[\/]\s*0*(\d+)\s*:\s*(\d+)\b/i', $normalized, $m)) {
            $ponPort = (int) $m[1];
            $onuIndex = (int) $m[2];
        } elseif (preg_match('/Port[^\n\r]*?GPON\s*0\s*[\/]\s*0*(\d+)/i', $normalized, $m)
            || preg_match('/\bGPON\s*0\s*[\/]\s*0*(\d+)\b/i', $normalized, $m)
            || preg_match('/Port\s*:\s*0\s*[\/]\s*0*(\d+)/i', $normalized, $m)) {
            // Port: GPON0/1  (sin ONU ID — firmwares viejos)
            $ponPort = (int) $m[1];
        }

        // ONU ID … 7 (no exigir ':' exacto: tolerar basura entre etiqueta y número)
        if ($onuIndex === null) {
            if (preg_match('/ONU\s*ID[^\d\n\r]{0,40}(\d+)/i', $normalized, $m)
                || preg_match('/Onu\s*Id[^\d\n\r]{0,40}(\d+)/i', $normalized, $m)) {
                $onuIndex = (int) $m[1];
            }
        }

        // Fila tabular: … GPON0/07  7
        if (($ponPort === null || $onuIndex === null)
            && preg_match('/GPON\s*0\s*[\/]\s*0*(\d+)\s+(\d+)\b/i', $normalized, $mTab)) {
            $ponPort = $ponPort ?? (int) $mTab[1];
            $onuIndex = $onuIndex ?? (int) $mTab[2];
        }

        if ($mac === null && $macEsperada !== null) {
            $mac = $this->normalizarMacHex($macEsperada);
        }

        if ($ponPort === null && $onuIndex === null) {
            return null;
        }

        return [
            'mac' => $mac ?? ($macEsperada ? $this->normalizarMacHex($macEsperada) : ''),
            'vlan' => $vlan,
            'pon_port' => $ponPort,
            'onu_index' => $onuIndex,
            'type' => $type,
            'raw' => trim($output),
        ];
    }

    public function normalizarMacHex(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
        if (strlen($hex) === 12) {
            return implode(':', str_split($hex, 2));
        }

        return strtoupper(str_replace(['-', '.'], ':', $mac));
    }

    /**
     * Parsea "show mac address-table" / PON MAC table (listado completo).
     * Soporta fila en una línea o Telnet que parte columnas en varias líneas:
     *   fc1b.d1cc.35f0
     *   199
     *   Dynamic
     *   GPON0/1:2
     *
     * @return array<int, array{mac: string, vlan: ?int, pon_port: ?int, onu_index: ?int, type: ?string, raw: string}>
     */
    public function parseMacAddressTable(string $output): array
    {
        $lookup = $this->parseMacAddressLookup($output);
        if ($lookup !== null && ($lookup['pon_port'] !== null || $lookup['onu_index'] !== null)
            && preg_match('/ONU\s*ID/i', $output)) {
            return [$lookup];
        }

        $output = $this->stripAnsi($output);
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $filas = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match('/^(vlan|mac\s*address|type|port|onu\s*id|mac address table|address[\s-]*table|----)/i', $line)
                && ! preg_match('/GPON\s*0\s*\/\s*\d+/i', $line)
                && ! $this->extraerMacDeLinea($line)) {
                continue;
            }
            if (preg_match('/^(mac|index|total|pon\s*mac|gem_|addresses\s+of|the\s+pon\s+found)/i', $line)
                && ! $this->extraerMacDeLinea($line)) {
                continue;
            }

            $macMatch = $this->extraerMacDeLinea($line);
            if ($macMatch === null) {
                continue;
            }

            $mac = $this->normalizarMacHex($macMatch);
            $ventana = $line;
            $rawParts = [$line];
            for ($j = 1; $j <= 8 && ($i + $j) < $count; $j++) {
                $next = $lines[$i + $j];
                if ($this->extraerMacDeLinea($next) !== null) {
                    break;
                }
                $ventana .= ' '.$next;
                $rawParts[] = $next;
            }

            $vlan = null;
            $ponPort = null;
            $onuIndex = null;
            $type = null;

            if (preg_match('/GPON\s*0\s*\/\s*0*(\d+)\s*:\s*(\d+)/i', $ventana, $mG)) {
                $ponPort = (int) $mG[1];
                $onuIndex = (int) $mG[2];
            } elseif (preg_match('/GPON\s*0\s*\/\s*0*(\d+)\s+(\d+)\b/i', $ventana, $mG)) {
                // GPON0/1 2 (show address-table — ONU en columna aparte)
                $ponPort = (int) $mG[1];
                $onuIndex = (int) $mG[2];
            } elseif (preg_match('/GPON\s*0\s*\/\s*0*(\d+)/i', $ventana, $mG)) {
                $ponPort = (int) $mG[1];
            }

            if (preg_match('/\b(dynamic|static|secure)\b/i', $ventana, $mType)) {
                $type = ucfirst(strtolower($mType[1]));
            }

            // VLAN: número entre la MAC y Dynamic/Static (evitar hex de la propia MAC, p.ej. …1632)
            if (preg_match(
                '/(?:[0-9A-Fa-f]{4}[.:][0-9A-Fa-f]{4}[.:][0-9A-Fa-f]{4}|(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2})\s+(\d{1,4})\s+(?:dynamic|static|secure)\b/i',
                $ventana,
                $mVlan
            )) {
                $vlan = (int) $mVlan[1];
            } elseif (preg_match('/\bvlan\s*[:=]?\s*(\d{1,4})\b/i', $ventana, $mVlan)) {
                $vlan = (int) $mVlan[1];
            }

            if ($onuIndex === null && preg_match('/ONU\s*(?:ID)?\s*[:=]?\s*(\d+)/i', $ventana, $mO)) {
                $onuIndex = (int) $mO[1];
            }

            if ($ponPort !== null && $onuIndex === null) {
                foreach ($rawParts as $part) {
                    $part = trim($part);
                    if (preg_match('/^\d{1,3}$/', $part)) {
                        $onuIndex = (int) $part;
                        break;
                    }
                }
            }

            // Sin PON/ONU no es una fila útil de tabla (evita confundir "MAC Address: xx" del lookup).
            if ($ponPort === null && $onuIndex === null) {
                continue;
            }

            // Solo PON sin ONU: fila incompleta para localizar ONU
            if ($ponPort !== null && $onuIndex === null) {
                continue;
            }

            $filas[] = [
                'mac' => $mac,
                'vlan' => $vlan,
                'pon_port' => $ponPort,
                'onu_index' => $onuIndex,
                'type' => $type,
                'raw' => implode(' | ', $rawParts),
            ];
        }

        return $filas;
    }

    private function extraerMacDeLinea(string $line): ?string
    {
        if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}/', $line, $mMac)) {
            return $mMac[0];
        }
        if (preg_match('/\b([0-9A-Fa-f]{4}[.:][0-9A-Fa-f]{4}[.:][0-9A-Fa-f]{4})\b/', $line, $mMac)) {
            return $mMac[1];
        }

        return null;
    }

    /**
     * @param  array<int, array{mac: string, vlan: ?int, pon_port: ?int, onu_index: ?int, type: ?string, raw: string}>  $filas
     * @return array{mac: string, vlan: ?int, pon_port: ?int, onu_index: ?int, type: ?string, raw: string}|null
     */
    public function buscarMacEnTabla(array $filas, string $mac): ?array
    {
        $objetivo = $this->normalizarMacHex($mac);

        foreach ($filas as $fila) {
            if (strcasecmp($fila['mac'], $objetivo) === 0) {
                return $fila;
            }
        }

        return null;
    }

    private function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $text) ?? $text;
    }
}
