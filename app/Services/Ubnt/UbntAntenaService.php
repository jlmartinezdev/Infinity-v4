<?php

namespace App\Services\Ubnt;

use Illuminate\Support\Facades\Log;
use Throwable;

class UbntAntenaService
{
    public function __construct(
        private WstalistParser $parser,
        private DhcpLeasesParser $dhcpParser,
    ) {}

    /**
     * Conecta por SSH a la antena/CPE Ubiquiti y ejecuta wstalist.
     *
     * @return array<string, mixed>
     */
    public function consultarWstalist(string $host, ?string $macEsperada = null): array
    {
        $host = trim($host);
        if ($host === '') {
            return [
                'success' => false,
                'message' => 'No hay IP para conectar por SSH a la antena.',
            ];
        }

        $user = (string) config('ubnt.ssh_user', 'ubnt');
        $password = (string) config('ubnt.ssh_password', '');
        $port = (int) config('ubnt.ssh_port', 22);
        $timeout = (int) config('ubnt.connect_timeout', 15);
        $command = (string) config('ubnt.command', 'wstalist');

        try {
            $session = new UbntSshSession($host, $port, $user, $password, $timeout);
            $raw = $session->exec($command);

            if ($raw === '') {
                return [
                    'success' => false,
                    'message' => 'La antena respondió vacío a wstalist (¿sin enlace wireless?).',
                    'host' => $host,
                    'comando' => $command,
                    'raw' => '',
                ];
            }

            $stations = $this->parser->parse($raw);
            $activa = $this->parser->seleccionarActiva($stations, $macEsperada);

            if ($activa === null) {
                return [
                    'success' => false,
                    'message' => 'No se pudo interpretar la salida de wstalist.',
                    'host' => $host,
                    'comando' => $command,
                    'raw' => mb_substr($raw, 0, 8000),
                    'stations' => $stations,
                ];
            }

            return [
                'success' => true,
                'message' => 'Consulta wstalist OK.',
                'host' => $host,
                'comando' => $command,
                'signal_dbm' => $activa['signal_dbm'] ?? null,
                'noise_floor_dbm' => $activa['noise_floor_dbm'] ?? null,
                'snr_db' => $activa['snr_db'] ?? null,
                'ccq' => $activa['ccq'] ?? null,
                'tx_rx_rate' => $activa['tx_rx_rate'] ?? null,
                'capacity' => $activa['capacity'] ?? null,
                'distance' => $activa['distance'] ?? null,
                'mac_remota' => $activa['mac_remota'] ?? null,
                'ap_mac' => $activa['ap_mac'] ?? null,
                'ap_name' => $activa['ap_name'] ?? null,
                'signal_chains' => $activa['signal_chains'] ?? null,
                'chain_delta' => $activa['chain_delta'] ?? null,
                'stations' => $stations,
                'raw' => mb_substr($raw, 0, 8000),
            ];
        } catch (Throwable $e) {
            Log::warning('[Ubnt] wstalist failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error SSH/wstalist: '.$e->getMessage(),
                'host' => $host,
                'comando' => $command ?? 'wstalist',
            ];
        }
    }

    /**
     * Conecta por SSH y lee leases DHCP locales de la antena/CPE (cat /tmp/dhcpd.leases).
     *
     * @return array<string, mixed>
     */
    public function consultarDhcpLeases(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            return [
                'success' => false,
                'message' => 'No hay IP para conectar por SSH a la antena.',
            ];
        }

        $user = (string) config('ubnt.ssh_user', 'ubnt');
        $password = (string) config('ubnt.ssh_password', '');
        $port = (int) config('ubnt.ssh_port', 22);
        $timeout = (int) config('ubnt.connect_timeout', 15);
        $command = (string) config('ubnt.dhcp_leases_command', 'cat /tmp/dhcpd.leases');

        try {
            $session = new UbntSshSession($host, $port, $user, $password, $timeout);
            $raw = $session->exec($command);
            $leases = $this->dhcpParser->parse($raw);

            if ($raw === '' && $leases === []) {
                return [
                    'success' => true,
                    'message' => 'No hay leases DHCP activos en la antena.',
                    'host' => $host,
                    'comando' => $command,
                    'leases' => [],
                    'raw' => '',
                ];
            }

            if ($raw !== '' && $leases === []) {
                return [
                    'success' => false,
                    'message' => 'No se pudo interpretar la salida de dhcpd.leases.',
                    'host' => $host,
                    'comando' => $command,
                    'leases' => [],
                    'raw' => mb_substr($raw, 0, 8000),
                ];
            }

            return [
                'success' => true,
                'message' => count($leases) === 1
                    ? '1 lease DHCP encontrado.'
                    : count($leases).' leases DHCP encontrados.',
                'host' => $host,
                'comando' => $command,
                'leases' => $leases,
                'raw' => mb_substr($raw, 0, 8000),
            ];
        } catch (Throwable $e) {
            Log::warning('[Ubnt] dhcp leases failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error SSH/dhcp leases: '.$e->getMessage(),
                'host' => $host,
                'comando' => $command ?? 'cat /tmp/dhcpd.leases',
            ];
        }
    }
}
