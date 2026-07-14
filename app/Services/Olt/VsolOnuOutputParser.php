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

        if ($desc = $this->extractDescription($output)) {
            return $desc;
        }

        if (preg_match('/(?:description|desc(?:ription)?)\s*[:=]\s*(.+)$/im', $output, $m)) {
            return trim($m[1]);
        }

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $this->isSeparatorLine($line)) {
                continue;
            }
            if (preg_match('/^(show onu|gpon-olt|configure|interface|\s*desc\b)/i', $line)) {
                continue;
            }
            if (preg_match('/^(none|null|n\/a|empty)$/i', $line)) {
                return null;
            }

            return $line;
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
        $data = array_filter([
            'serial' => $this->extractSerial($line),
            'modelo' => $this->extractModelo($line),
            'descripcion' => $this->extractDescription($line),
            'estado' => $this->extractEstadoFromTokens($this->tokenize($line)),
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
        $estado = $this->extractEstadoFromTokens($tokens);
        $descripcion = null;

        foreach ($tokens as $i => $token) {
            if ($i === 0) {
                continue;
            }
            if ($serial !== null && strcasecmp($token, $serial) === 0) {
                continue;
            }
            if ($this->tokenEsEstado($token)) {
                continue;
            }
            if (preg_match('/^(enable|disable|enabled|disabled|succeeded|failed|initial|match|mismatch|\d+\(gpon\))$/i', $token)) {
                continue;
            }
            if (strlen($token) >= 3 && ! preg_match('/^\d+(\.\d+)?$/', $token)) {
                $descripcion = $token;
                break;
            }
        }

        $data = array_filter([
            'serial' => $serial,
            'modelo' => $this->extractModelo($line),
            'descripcion' => $descripcion,
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
            return trim($m[1]);
        }

        if (preg_match('/(?:GPON\d+\/\d+:\d+|\d+\/\d+\/\d+:\d+)\s+(\S+)/i', $line, $m)) {
            $first = $m[1];
            if (preg_match('/^[A-Z0-9][A-Z0-9_]{2,}$/i', $first) && str_contains($first, '_')) {
                return $first;
            }
        }

        return null;
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

    private function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $text) ?? $text;
    }
}
