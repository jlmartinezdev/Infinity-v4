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
     * Consulta descripción y potencia óptica ONU por ONU (interface gpon 0/X → show onu N desc / optical_info).
     *
     * @param  list<array{pon_slot:int,pon_port:int,onu_index:int}>  $onuItems
     * @return array<string, array{desc:string,optical:string}>
     */
    public function fetchDetallesOnus(Olt $olt, array $onuItems): array
    {
        if ($onuItems === []) {
            return [];
        }

        $session = $this->openSession($olt);
        $timeout = max(8, (int) config('olt.vsol.onu_detail_timeout', 12));
        $resultados = [];

        try {
            $porPuerto = [];
            foreach ($onuItems as $item) {
                $port = (int) $item['pon_port'];
                $porPuerto[$port][] = [
                    'slot' => (int) ($item['pon_slot'] ?? 0),
                    'index' => (int) $item['onu_index'],
                ];
            }

            foreach ($porPuerto as $port => $grupo) {
                $session->exec("interface gpon 0/{$port}", 15);

                foreach ($grupo as $onu) {
                    $idx = $onu['index'];
                    $slot = $onu['slot'];
                    $key = $slot.'/'.$port.':'.$idx;

                    $resultados[$key] = [
                        'desc' => $session->exec("show onu {$idx} desc", $timeout),
                        'optical' => $session->exec("show onu {$idx} optical_info", $timeout),
                    ];
                }

                $session->exec('exit', 10);
            }
        } finally {
            $session->disconnect();
        }

        return $resultados;
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
