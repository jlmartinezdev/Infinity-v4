<?php

namespace App\Services\Olt;

use App\Models\Olt;
use RuntimeException;

class VsolGponClient
{
    public function testConnection(Olt $olt): string
    {
        $session = $this->openSession($olt);
        try {
            $output = $session->exec('show version', 20);

            return mb_substr($output, 0, 500);
        } finally {
            $session->disconnect();
        }
    }

    /**
     * @return array{onu_info: string, onu_state: string}
     */
    public function fetchOnuList(Olt $olt): array
    {
        $session = $this->openSession($olt);
        try {
            $timeout = (int) config('olt.vsol.command_timeout', 90);
            $infoChunks = [];
            $stateChunks = [];

            $infoChunks[] = $session->exec('show onu info', $timeout);
            $stateChunks[] = $session->exec('show onu state', $timeout);

            $maxPort = max(1, min((int) ($olt->cantidad_puerto ?: 8), 16));
            for ($port = 1; $port <= $maxPort; $port++) {
                $session->exec("interface gpon 0/{$port}", 15);
                $infoOut = $session->exec('show onu info', min($timeout, 45));
                $stateOut = $session->exec('show onu state', min($timeout, 45));
                $session->exec('exit', 10);

                if ($this->salidaInvalida($infoOut)) {
                    $infoOut = $session->exec("show onu info {$port}", min($timeout, 45));
                }
                if ($this->salidaInvalida($stateOut)) {
                    $stateOut = $session->exec("show onu state {$port}", min($timeout, 45));
                }

                $infoChunks[] = "PON 0/{$port}\n".$infoOut;
                $stateChunks[] = "PON 0/{$port}\n".$stateOut;
            }

            $onuInfo = trim(implode("\n\n", array_filter($infoChunks)));
            $onuState = trim(implode("\n\n", array_filter($stateChunks)));

            if ($onuInfo === '' && $onuState === '') {
                throw new RuntimeException('El OLT no devolvió datos de ONUs. Verifique Telnet y permisos CLI.');
            }

            return [
                'onu_info' => $onuInfo,
                'onu_state' => $onuState,
            ];
        } finally {
            $session->disconnect();
        }
    }

    public function fetchOnuInfo(Olt $olt): string
    {
        $session = $this->openSession($olt);
        try {
            $timeout = (int) config('olt.vsol.command_timeout', 90);
            $infoChunks = [];

            $infoChunks[] = $session->exec('show onu info', min($timeout, 60));

            $maxPort = max(1, min((int) ($olt->cantidad_puerto ?: 8), 16));
            for ($port = 1; $port <= $maxPort; $port++) {
                $session->exec("interface gpon 0/{$port}", 15);
                $infoOut = $session->exec('show onu info', min($timeout, 45));
                $session->exec('exit', 10);

                if ($this->salidaInvalida($infoOut)) {
                    $infoOut = $session->exec("show onu info {$port}", min($timeout, 45));
                }

                $infoChunks[] = "PON 0/{$port}\n".$infoOut;
            }

            return trim(implode("\n\n", array_filter($infoChunks)));
        } finally {
            $session->disconnect();
        }
    }

    public function fetchOnuOpticalDiag(Olt $olt): string
    {
        $session = $this->openSession($olt);
        try {
            $timeout = (int) config('olt.vsol.command_timeout', 90);
            $chunks = [];

            $global = $session->exec('show onu opm-diag all', min($timeout, 60));
            if (! $this->salidaInvalida($global)) {
                $chunks[] = $global;
            }

            $maxPort = max(1, min((int) ($olt->cantidad_puerto ?: 8), 16));
            for ($port = 1; $port <= $maxPort; $port++) {
                $session->exec("interface gpon 0/{$port}", 15);
                $out = $session->exec('show onu opm-diag', min($timeout, 45));
                $session->exec('exit', 10);

                if ($this->salidaInvalida($out)) {
                    $out = $session->exec("show onu opm-diag {$port}", min($timeout, 45));
                }

                if (! $this->salidaInvalida($out)) {
                    $chunks[] = "PON 0/{$port}\n".$out;
                }
            }

            return trim(implode("\n\n", array_filter($chunks)));
        } finally {
            $session->disconnect();
        }
    }

    public function fetchOnuStates(Olt $olt): string
    {
        $session = $this->openSession($olt);
        try {
            $timeout = (int) config('olt.vsol.command_timeout', 90);
            $stateChunks = [];

            $stateChunks[] = $session->exec('show onu state', min($timeout, 60));

            $maxPort = max(1, min((int) ($olt->cantidad_puerto ?: 8), 16));
            for ($port = 1; $port <= $maxPort; $port++) {
                $session->exec("interface gpon 0/{$port}", 15);
                $stateOut = $session->exec('show onu state', min($timeout, 45));
                $session->exec('exit', 10);

                if ($this->salidaInvalida($stateOut)) {
                    $stateOut = $session->exec("show onu state {$port}", min($timeout, 45));
                }

                $stateChunks[] = "PON 0/{$port}\n".$stateOut;
            }

            $onuState = trim(implode("\n\n", array_filter($stateChunks)));
            if ($onuState === '') {
                throw new RuntimeException('El OLT no devolvió estados de ONUs. Verifique Telnet y permisos CLI.');
            }

            return $onuState;
        } finally {
            $session->disconnect();
        }
    }

    /**
     * @return array{onu_info: string, onu_state: string}
     */
    public function fetchOnuDataForPort(Olt $olt, int $port): array
    {
        $session = $this->openSession($olt);
        try {
            $timeout = min(45, (int) config('olt.vsol.command_timeout', 90));

            $session->exec("interface gpon 0/{$port}", 15);
            $infoOut = $session->exec('show onu info', $timeout);
            $stateOut = $session->exec('show onu state', $timeout);
            $session->exec('exit', 10);

            if ($this->salidaInvalida($infoOut)) {
                $infoOut = $session->exec("show onu info {$port}", $timeout);
            }
            if ($this->salidaInvalida($stateOut)) {
                $stateOut = $session->exec("show onu state {$port}", $timeout);
            }

            return [
                'onu_info' => "PON 0/{$port}\n".$infoOut,
                'onu_state' => "PON 0/{$port}\n".$stateOut,
            ];
        } finally {
            $session->disconnect();
        }
    }

    /**
     * Consulta descripción y potencia óptica ONU por ONU (interface gpon 0/X).
     * Prueba sintaxis índice-primero (show onu N desc) y comando-primero (show onu desc N).
     *
     * @param  list<array{pon_slot:int,pon_port:int,onu_index:int}>  $onuItems
     * @return array<string, array{desc:string,optical:string,opm_diag?:string}>
     */
    public function fetchDetallesOnus(Olt $olt, array $onuItems): array
    {
        if ($onuItems === []) {
            return [];
        }

        $timeout = max(10, (int) config('olt.vsol.onu_detail_timeout', 12));
        $resultados = [];

        $porPuerto = [];
        foreach ($onuItems as $item) {
            $port = (int) $item['pon_port'];
            $porPuerto[$port][] = [
                'slot' => (int) ($item['pon_slot'] ?? 0),
                'index' => (int) $item['onu_index'],
            ];
        }

        $session = $this->openSession($olt);

        try {
            foreach ($porPuerto as $port => $grupo) {
                $chunks = array_chunk($grupo, max(1, (int) config('olt.vsol.onu_detail_reconnect_every', 8)));
                foreach ($chunks as $subGrupo) {
                    try {
                        $this->consultarDetallesPuerto($session, $port, $subGrupo, $timeout, $resultados);
                    } catch (RuntimeException $e) {
                        if (! $this->esCorteDeConexion($e)) {
                            throw $e;
                        }

                        $session->disconnect();
                        usleep(600000);
                        $session = $this->openSession($olt);
                        $this->consultarDetallesPuerto($session, $port, $subGrupo, $timeout, $resultados);
                    }
                }
            }
        } finally {
            $session->disconnect();
        }

        return $resultados;
    }

    /**
     * @param  list<array{slot:int,index:int}>  $grupo
     * @param  array<string, array{desc:string,optical:string,opm_diag?:string,onu_info?:string}>  $resultados
     */
    private function consultarDetallesPuerto(
        VsolTelnetSession $session,
        int $port,
        array $grupo,
        int $timeout,
        array &$resultados
    ): void {
        $session->exec("interface gpon 0/{$port}", 15);
        // Calentar el contexto: el 1.er comando tras interface a veces falla/sale ambiguo (ONU #1).
        $infoWarmup = $session->exec('show onu info', min($timeout, 30));
        if ($this->salidaInvalida($infoWarmup)) {
            $infoWarmup = '';
        }

        foreach ($grupo as $onu) {
            $idx = $onu['index'];
            $slot = $onu['slot'];
            $key = $slot.'/'.$port.':'.$idx;

            $resultados[$key] = [
                'desc' => $this->execPrimeraSalidaValida($session, $this->comandosShowOnuDesc($idx), $timeout),
                'optical' => $this->execPrimeraSalidaValida($session, $this->comandosShowOnuOptical($idx), $timeout),
                'onu_info' => $infoWarmup !== '' ? "PON 0/{$port}\n".$infoWarmup : '',
            ];
            usleep(50000);
        }

        // Respaldo de RX para ONUs que no respondieron optical_info (p. ej. índice 1).
        $opm = $session->exec('show onu opm-diag', min($timeout, 45));
        if ($this->salidaInvalida($opm)) {
            $opm = $session->exec("show onu opm-diag {$port}", min($timeout, 45));
        }
        if (! $this->salidaInvalida($opm)) {
            foreach ($grupo as $onu) {
                $key = $onu['slot'].'/'.$port.':'.$onu['index'];
                if (isset($resultados[$key])) {
                    $resultados[$key]['opm_diag'] = "PON 0/{$port}\n".$opm;
                }
            }
        }

        $session->exec('exit', 10);
    }

    private function esCorteDeConexion(RuntimeException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'se interrumpió')
            || str_contains($msg, 'sesión telnet cerrada')
            || str_contains($msg, '10053')
            || str_contains($msg, '10054')
            || str_contains($msg, 'broken pipe')
            || str_contains($msg, 'connection reset')
            || str_contains($msg, 'fwrite');
    }

    /**
     * Localiza una MAC en la tabla PON MAC del OLT y opcionalmente consulta desc/RX de esa ONU.
     * Estrategia principal (genérica entre firmwares): barrer cada PON con el comando de tabla
     * (show mac address-table gpon 0/{pon}) hasta hallar GPON0/X:Y.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   mac?: string,
     *   pon_port?: int|null,
     *   onu_index?: int|null,
     *   vlan?: int|null,
     *   descripcion?: string|null,
     *   rx_power_dbm?: float|null,
     *   estado?: string|null,
     *   comando?: string,
     *   raw_match?: string|null
     * }
     */
    public function localizarMacYConsultarOnu(Olt $olt, string $mac, bool $conDetalle = true): array
    {
        $macNorm = $this->normalizarMacColon($mac);
        $macVsol = $this->macFormatoVsol($mac);
        $cmds = $olt->macCliComandosEfectivos();
        $session = $this->openSession($olt);
        $timeout = max(30, (int) config('olt.vsol.command_timeout', 90));
        $maxPort = max(1, min((int) ($olt->cantidad_puerto ?: 8), 32));

        try {
            $parser = new VsolOnuOutputParser;
            $blob = '';
            $comandoUsado = '';
            $hit = null;

            try {
                $session->exec('terminal length 0', 6);
            } catch (\Throwable) {
            }

            // 1) Principal: tabla MAC por cada PON hasta encontrar MAC → GPON0/X:Y
            $scan = $this->barrerMacPorPones($session, $parser, $cmds['pon'], $macNorm, $macVsol, $maxPort, $timeout);
            if ($scan['hit'] !== null) {
                $hit = $scan['hit'];
                $comandoUsado = $scan['comando'];
                $blob = $scan['raw'];
            }

            // 2) Opcional: lookup directo por MAC (si está configurado en el OLT)
            if ($hit === null || (($hit['onu_index'] ?? null) === null)) {
                foreach ($cmds['address'] as $tpl) {
                    $cmd = $this->expandirPlantillaMac($tpl, $macNorm, $macVsol);
                    $out = $session->exec($cmd, min($timeout, 45));
                    if ($this->salidaInvalida($out)) {
                        continue;
                    }
                    $hitAddr = $parser->parseMacAddressLookup($out, $macNorm);
                    if ($hitAddr === null) {
                        $hitAddr = $this->buscarMacEnSalidaTabla($parser, $out, $macNorm);
                    }
                    if ($hitAddr === null) {
                        continue;
                    }
                    $comandoUsado = ($comandoUsado !== '' ? $comandoUsado.' → ' : '').$cmd;
                    $blob = $out;
                    // Si solo trajo PON sin ONU, barrer ese PON
                    if (($hitAddr['onu_index'] ?? null) === null && ($hitAddr['pon_port'] ?? null) !== null) {
                        $ponSolo = (int) $hitAddr['pon_port'];
                        $scanUno = $this->barrerMacPorPones(
                            $session,
                            $parser,
                            $cmds['pon'],
                            $macNorm,
                            $macVsol,
                            $ponSolo,
                            $timeout,
                            $ponSolo
                        );
                        if ($scanUno['hit'] !== null) {
                            $hit = $scanUno['hit'];
                            $comandoUsado .= ' → '.$scanUno['comando'];
                            $blob = $scanUno['raw'];
                        } else {
                            $hit = $hitAddr;
                        }
                    } else {
                        $hit = $hitAddr;
                    }
                    break;
                }
            }

            // 3) Tabla global (si está configurada)
            if ($hit === null) {
                foreach ($cmds['tabla'] as $tpl) {
                    $cmd = $this->expandirPlantillaMac($tpl, $macNorm, $macVsol);
                    $out = $session->exec($cmd, $timeout);
                    if ($this->salidaInvalida($out)) {
                        continue;
                    }
                    $hitTabla = $this->buscarMacEnSalidaTabla($parser, $out, $macNorm);
                    if ($hitTabla !== null) {
                        $hit = $hitTabla;
                        $comandoUsado = ($comandoUsado !== '' ? $comandoUsado.' → ' : '').$cmd;
                        $blob = $out;
                        break;
                    }
                }
            }

            if ($hit === null) {
                $ej = $this->expandirPlantillaMac(
                    $cmds['pon'][0] ?? 'show mac address-table gpon 0/{pon}',
                    $macNorm,
                    $macVsol,
                    1
                );

                return [
                    'success' => false,
                    'message' => 'MAC '.$macNorm.' no encontrada en ningún PON (1..'.$maxPort.'). Ej.: '.$ej,
                    'mac' => $macNorm,
                    'comando' => $comandoUsado !== '' ? $comandoUsado : $ej,
                    'raw_match' => $blob !== '' ? mb_substr($blob, 0, 400) : null,
                ];
            }

            $ponPort = $hit['pon_port'] ?? null;
            $onuIndex = $hit['onu_index'] ?? null;

            $result = [
                'success' => true,
                'message' => ($ponPort !== null && $onuIndex !== null)
                    ? "MAC localizada en PON {$ponPort} ONU {$onuIndex}."
                    : 'MAC encontrada, pero no se pudo interpretar Port/ONU ID.',
                'mac' => $macNorm,
                'pon_port' => $ponPort,
                'onu_index' => $onuIndex,
                'vlan' => $hit['vlan'] ?? null,
                'comando' => $comandoUsado,
                'raw_match' => $hit['raw'] ?? $blob,
                'descripcion' => null,
                'rx_power_dbm' => null,
                'estado' => null,
            ];

            if ($conDetalle && $ponPort !== null && $onuIndex !== null) {
                $session->exec("interface gpon 0/{$ponPort}", 15);
                $descOut = $this->execPrimeraSalidaValida($session, $this->comandosShowOnuDesc($onuIndex), 15);
                $optOut = $this->execPrimeraSalidaValida($session, $this->comandosShowOnuOptical($onuIndex), 15);
                $stateOut = $session->exec('show onu state', 30);
                $session->exec('exit', 10);

                $desc = $parser->parseOnuDescOutput($descOut);
                $optInfo = $parser->parseOnuOpticalInfoOutput($optOut);
                $rx = $optInfo['rx_power_dbm'] ?? null;

                $estado = null;
                foreach ($parser->parse('', $stateOut) as $row) {
                    if ((int) ($row['pon_port'] ?? $ponPort) === $ponPort
                        && (int) ($row['onu_index'] ?? 0) === $onuIndex) {
                        $estado = $row['estado'] ?? null;
                        break;
                    }
                }

                $result['descripcion'] = $desc;
                $result['rx_power_dbm'] = $rx !== null ? (float) $rx : null;
                $result['estado'] = $estado;
                if ($desc || $rx !== null) {
                    $result['message'] .= ' Detalle ONU consultado.';
                }
            }

            return $result;
        } finally {
            $session->disconnect();
        }
    }

    /**
     * Barre puertos PON con los comandos de tabla configurados hasta hallar la MAC.
     *
     * @param  list<string>  $plantillasPon
     * @return array{hit: ?array, comando: string, raw: string}
     */
    private function barrerMacPorPones(
        VsolTelnetSession $session,
        VsolOnuOutputParser $parser,
        array $plantillasPon,
        string $macNorm,
        string $macVsol,
        int $maxPort,
        int $timeout,
        ?int $soloPuerto = null
    ): array {
        $plantillasPon = $plantillasPon !== []
            ? $plantillasPon
            : ['show mac address-table gpon 0/{pon}'];

        $from = $soloPuerto ?? 1;
        $to = $soloPuerto ?? $maxPort;
        $tplUsada = null;

        for ($port = $from; $port <= $to; $port++) {
            $tpls = $tplUsada !== null ? [$tplUsada] : $plantillasPon;
            foreach ($tpls as $tpl) {
                $cmd = $this->expandirPlantillaMac($tpl, $macNorm, $macVsol, $port);
                $out = $session->exec($cmd, min($timeout, 90));
                if ($this->salidaInvalida($out)) {
                    continue;
                }
                // Recordar el comando que este firmware acepta (para el resto de PONes)
                $tplUsada = $tpl;
                $hit = $this->buscarMacEnSalidaTabla($parser, $out, $macNorm);
                if ($hit !== null && ($hit['onu_index'] ?? null) !== null) {
                    return [
                        'hit' => $hit,
                        'comando' => $cmd,
                        'raw' => $out,
                    ];
                }
                // Comando válido pero MAC no está en este PON → siguiente puerto
                break;
            }
        }

        return ['hit' => null, 'comando' => '', 'raw' => ''];
    }

    /**
     * Escribe description/desc de una ONU en la OLT (modo config).
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   pon_port: int,
     *   onu_index: int,
     *   descripcion: string,
     *   descripcion_leida?: string|null,
     *   comando?: string,
     *   raw?: string|null
     * }
     */
    public function configurarOnuDescripcion(Olt $olt, int $ponPort, int $onuIndex, string $descripcion): array
    {
        $desc = $this->sanitizarDescripcionOnu($descripcion);
        if ($desc === '') {
            return [
                'success' => false,
                'message' => 'La descripción está vacía o no es válida para la OLT.',
                'pon_port' => $ponPort,
                'onu_index' => $onuIndex,
                'descripcion' => $descripcion,
            ];
        }

        $session = $this->openSession($olt);
        $parser = new VsolOnuOutputParser;

        try {
            $session->exec("interface gpon 0/{$ponPort}", 15);

            // Preferir sintaxis en una línea (evita quedar en contexto ONU incorrecto).
            // Índice-primero y comando-primero según firmware.
            $comandosEscritura = [
                "onu {$onuIndex} description {$desc}",
                "onu {$onuIndex} desc {$desc}",
                "onu description {$onuIndex} {$desc}",
                "onu desc {$onuIndex} {$desc}",
                "onu {$onuIndex}", // contexto + description
            ];

            $comandoUsado = null;
            $rawWrite = '';
            foreach ($comandosEscritura as $cmd) {
                $rawWrite = $session->exec($cmd, 15);
                if ($cmd === "onu {$onuIndex}") {
                    if ($this->salidaInvalida($rawWrite)) {
                        continue;
                    }
                    foreach (["description {$desc}", "desc {$desc}"] as $cmdInner) {
                        $rawWrite = $session->exec($cmdInner, 15);
                        if (! $this->salidaInvalida($rawWrite)) {
                            $comandoUsado = $cmd.' → '.$cmdInner;
                            $session->exec('exit', 8); // salir contexto ONU
                            break 2;
                        }
                    }
                    $session->exec('exit', 8);
                    continue;
                }
                if (! $this->salidaInvalida($rawWrite)) {
                    $comandoUsado = $cmd;
                    break;
                }
            }

            $descOut = $this->execPrimeraSalidaValida($session, $this->comandosShowOnuDesc($onuIndex), 15);
            $session->exec('exit', 10);

            $leida = $parser->parseOnuDescOutput($descOut);
            $ok = $leida !== null && strcasecmp($leida, $desc) === 0;

            if (! $ok && $leida !== null && str_contains(strtoupper($leida), strtoupper($desc))) {
                $ok = true;
            }

            return [
                'success' => $ok || $comandoUsado !== null,
                'message' => $ok
                    ? "Descripción ONU {$ponPort}:{$onuIndex} actualizada a {$desc}."
                    : ($comandoUsado
                        ? "Se envió el comando, pero la OLT leyó «".($leida ?: 'vacío')."». Verificá en el CLI."
                        : 'No se pudo aplicar la descripción en la OLT (comando rechazado).'),
                'pon_port' => $ponPort,
                'onu_index' => $onuIndex,
                'descripcion' => $desc,
                'descripcion_leida' => $leida,
                'comando' => $comandoUsado,
                'raw' => mb_substr(trim($rawWrite."\n".$descOut), 0, 800),
            ];
        } finally {
            $session->disconnect();
        }
    }

    public function sanitizarDescripcionOnu(string $descripcion): string
    {
        $desc = str_replace(['ñ', 'Ñ'], 'n', trim($descripcion));
        $desc = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::ascii($desc));
        $desc = preg_replace('/\s+/', '_', $desc) ?? '';
        $desc = preg_replace('/[^A-Z0-9._-]/', '', $desc) ?? '';

        return mb_substr($desc, 0, 64);
    }

    /**
     * @return array{mac: string, vlan: ?int, pon_port: ?int, onu_index: ?int, type: ?string, raw: string}|null
     */
    private function buscarMacEnSalidaTabla(VsolOnuOutputParser $parser, string $output, string $macNorm): ?array
    {
        if (trim($output) === '') {
            return null;
        }

        $filas = $parser->parseMacAddressTable($output);
        $hit = $parser->buscarMacEnTabla($filas, $macNorm);
        if ($hit !== null && ($hit['onu_index'] ?? null) !== null) {
            return $hit;
        }

        // Respaldo: buscar GPON0/X:Y en la misma línea que la MAC (puntos o dos puntos)
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $macNorm) ?? '');
        if (strlen($hex) !== 12) {
            return null;
        }
        $macDot = strtolower(substr($hex, 0, 4).'.'.substr($hex, 4, 4).'.'.substr($hex, 8, 4));
        $macColon = strtolower(substr($hex, 0, 4).':'.substr($hex, 4, 4).':'.substr($hex, 8, 4));
        $macCisco = strtolower(implode(':', str_split($hex, 2)));

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $lineLower = strtolower($line);
            if (! str_contains($lineLower, $macDot)
                && ! str_contains($lineLower, $macColon)
                && ! str_contains($lineLower, $macCisco)) {
                continue;
            }
            if (preg_match('/GPON\s*0\s*\/\s*0*(\d+)\s*:\s*(\d+)/i', $line, $m)) {
                return [
                    'mac' => $macNorm,
                    'vlan' => null,
                    'pon_port' => (int) $m[1],
                    'onu_index' => (int) $m[2],
                    'type' => null,
                    'raw' => trim($line),
                ];
            }
        }

        return null;
    }

    /** FC:1B:D1:C2:8C:15 → FC1B:D1C2:8C15 (formato VSOL address). */
    private function macFormatoVsol(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
        if (strlen($hex) !== 12) {
            return strtoupper(str_replace('-', ':', $mac));
        }

        return substr($hex, 0, 4).':'.substr($hex, 4, 4).':'.substr($hex, 8, 4);
    }

    /**
     * Expande placeholders de plantillas CLI MAC.
     * {mac}/{mac_colon} FC:1B:…  {mac_vsol} FC1B:D1CC:35F0  {mac_dot} fc1b.d1cc.35f0
     * {pon} 1  {pon2} 01
     */
    private function expandirPlantillaMac(string $plantilla, string $macNorm, string $macVsol, ?int $pon = null): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $macNorm) ?? '');
        $macDot = strlen($hex) === 12
            ? strtolower(substr($hex, 0, 4).'.'.substr($hex, 4, 4).'.'.substr($hex, 8, 4))
            : strtolower(str_replace(':', '.', $macNorm));

        $map = [
            '{mac}' => $macNorm,
            '{mac_colon}' => $macNorm,
            '{mac_vsol}' => $macVsol,
            '{mac_dot}' => $macDot,
            '{pon}' => $pon !== null ? (string) $pon : '',
            '{pon2}' => $pon !== null ? sprintf('%02d', $pon) : '',
        ];

        return str_replace(array_keys($map), array_values($map), $plantilla);
    }

    private function normalizarMacColon(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
        if (strlen($hex) === 12) {
            return implode(':', str_split($hex, 2));
        }

        return strtoupper(str_replace('-', ':', $mac));
    }

    /**
     * show onu N desc | show onu desc N (según firmware).
     *
     * @return list<string>
     */
    private function comandosShowOnuDesc(int $onuIndex): array
    {
        return [
            "show onu {$onuIndex} desc",
            "show onu {$onuIndex} description",
            "show onu desc {$onuIndex}",
            "show onu description {$onuIndex}",
        ];
    }

    /**
     * show onu N optical_info | show onu optical_info N (según firmware).
     *
     * @return list<string>
     */
    private function comandosShowOnuOptical(int $onuIndex): array
    {
        return [
            "show onu {$onuIndex} optical_info",
            "show onu {$onuIndex} optical-info",
            "show onu optical_info {$onuIndex}",
            "show onu optical-info {$onuIndex}",
        ];
    }

    /**
     * @param  list<string>  $comandos
     */
    private function execPrimeraSalidaValida(VsolTelnetSession $session, array $comandos, int $timeout): string
    {
        $ultima = '';

        foreach ($comandos as $comando) {
            $out = $session->exec($comando, $timeout);
            $ultima = $out;
            if (! $this->salidaInvalida($out) && ! $this->salidaSoloEcoComando($out, $comando)) {
                return $out;
            }
        }

        return $ultima;
    }

    private function salidaSoloEcoComando(string $output, string $comando): bool
    {
        $norm = strtolower(trim(preg_replace('/\s+/', ' ', $output) ?? ''));
        $cmd = strtolower(trim(preg_replace('/\s+/', ' ', $comando) ?? ''));

        return $norm === '' || $norm === $cmd;
    }

    public function salidaEsInvalida(string $output): bool
    {
        return $this->salidaInvalida($output);
    }

    private function openSession(Olt $olt): VsolTelnetSession
    {
        if (! filled($olt->ip)) {
            throw new RuntimeException('El OLT no tiene IP de gestión configurada.');
        }

        $protocol = strtolower((string) ($olt->gestion_protocolo ?: 'telnet'));
        if ($protocol !== 'telnet') {
            throw new RuntimeException('Por ahora solo está soportado Telnet. Configure gestión_protocolo=telnet en el OLT.');
        }

        $session = new VsolTelnetSession(
            (string) $olt->ip,
            $olt->gestionPuertoEfectivo(),
            (int) config('olt.vsol.connect_timeout', 15),
            (int) config('olt.vsol.command_pause_ms', 100),
        );

        $session->connect();
        $session->login(
            $olt->gestionUsuarioEfectivo(),
            (string) $olt->gestion_password,
            $olt->gestion_enable_password ?: null,
        );

        return $session;
    }

    private function salidaInvalida(string $output): bool
    {
        $trim = trim($output);

        return $trim === ''
            || stripos($trim, 'invalid') !== false
            || stripos($trim, 'unknown command') !== false
            || stripos($trim, 'ambiguous') !== false;
    }
}
