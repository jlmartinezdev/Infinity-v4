<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\Olt\VsolGponClient;
use App\Services\Ubnt\UbntAntenaService;
use App\Support\CpeInventario;
use App\Support\HerramientasRedPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistroSenalInstalacionService
{
    public function __construct(
        private readonly MikroTikService $mikrotik,
        private readonly VsolGponClient $vsol,
        private readonly UbntAntenaService $ubnt,
        private readonly HuaweiOnuService $huawei,
    ) {}

    /**
     * Lee y registra potencia (fibra) o intensidad (antena) de cada servicio del pedido.
     *
     * @return array{mensajes: list<string>, warnings: list<string>, lecturas: list<array<string, mixed>>}
     */
    public function capturarParaPedido(Pedido $pedido): array
    {
        $servicios = Servicio::with(HerramientasRedPayload::relaciones())
            ->where('pedido_id', $pedido->pedido_id)
            ->get();

        $mensajes = [];
        $warnings = [];
        $lecturas = [];

        foreach ($servicios as $servicio) {
            $lectura = $this->capturarParaServicio($servicio);
            $lecturas[] = $lectura;
            if (! empty($lectura['omitido'])) {
                continue;
            }
            if ($lectura['ok'] && filled($lectura['mensaje'] ?? null)) {
                $mensajes[] = $lectura['mensaje'];
            } elseif (filled($lectura['mensaje'] ?? null)) {
                $warnings[] = $lectura['mensaje'];
            }
        }

        return [
            'mensajes' => $mensajes,
            'warnings' => $warnings,
            'lecturas' => $lecturas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function capturarParaServicio(Servicio $servicio): array
    {
        $lectura = $this->leerParaServicio($servicio);
        $this->registrarLectura($servicio, $lectura);

        return $lectura;
    }

    /**
     * @return array<string, mixed>
     */
    public function leerParaServicio(Servicio $servicio): array
    {
        $servicio->loadMissing(HerramientasRedPayload::relaciones());

        if (HerramientasRedPayload::esFibra($servicio)) {
            return $this->leerFibra($servicio);
        }

        if (HerramientasRedPayload::esAntena($servicio)) {
            return $this->leerAntena($servicio);
        }

        return [
            'ok' => false,
            'omitido' => true,
            'tipo' => null,
            'valor' => null,
            'mensaje' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $lectura
     */
    public function registrarLectura(Servicio $servicio, array $lectura): void
    {
        if (! ($lectura['ok'] ?? false)) {
            return;
        }

        try {
            if (($lectura['tipo'] ?? null) === ServicioConexionEvento::TIPO_SENAL_OPTICA) {
                ServicioConexionEvento::registrarSenalOptica(
                    $servicio,
                    [
                        'mac' => $lectura['mac'] ?? $servicio->mac_address,
                        'olt_id' => $lectura['olt_id'] ?? null,
                        'pon_port' => $lectura['pon_port'] ?? null,
                        'onu_index' => $lectura['onu_index'] ?? null,
                        'rx_power_dbm' => $lectura['rx_power_dbm'] ?? $lectura['valor'] ?? null,
                        'tx_power_dbm' => $lectura['tx_power_dbm'] ?? null,
                        'estado' => $lectura['onu_estado'] ?? null,
                        'descripcion' => $lectura['onu_descripcion'] ?? null,
                        'payload' => array_merge($lectura['payload'] ?? [], [
                            'origen' => 'finalizar_instalacion',
                        ]),
                    ],
                    ServicioConexionEvento::FUENTE_INSTALACION
                );

                $rx = $lectura['rx_power_dbm'] ?? $lectura['valor'] ?? null;
                if ($rx !== null && $servicio->cajaNapPuertoActivo) {
                    $servicio->cajaNapPuertoActivo->update([
                        'potencia_cliente' => $rx,
                    ]);
                }

                return;
            }

            if (($lectura['tipo'] ?? null) === ServicioConexionEvento::TIPO_SENAL_ANTENA) {
                ServicioConexionEvento::registrarSenalAntena(
                    $servicio,
                    [
                        'ip' => $lectura['ip'] ?? $servicio->ip,
                        'mac' => $lectura['mac'] ?? $servicio->mac_address,
                        'router_id' => $lectura['router_id'] ?? $servicio->pool?->router_id,
                        'antena_signal_dbm' => $lectura['antena_signal_dbm'] ?? $lectura['valor'] ?? null,
                        'antena_snr_db' => $lectura['antena_snr_db'] ?? null,
                        'antena_radio_iface' => $lectura['antena_radio_iface'] ?? 'wstalist',
                        'payload' => array_merge($lectura['payload'] ?? [], [
                            'origen' => 'finalizar_instalacion',
                        ]),
                    ],
                    ServicioConexionEvento::FUENTE_INSTALACION
                );
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar señal de instalación', [
                'servicio_id' => $servicio->servicio_id,
                'tipo' => $lectura['tipo'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function textoResumenPedido(array $captura): string
    {
        $partes = [];
        if (! empty($captura['mensajes'])) {
            $partes[] = implode(' ', $captura['mensajes']);
        }
        if (! empty($captura['warnings'])) {
            $partes[] = implode(' ', $captura['warnings']);
        }

        return implode(' ', $partes);
    }

    /**
     * @return array<string, mixed>
     */
    private function leerFibra(Servicio $servicio): array
    {
        @set_time_limit(180);

        $olt = $this->consultarOlt($servicio);
        if ($olt['ok']) {
            return $olt;
        }

        $onu = $this->consultarOnuHuawei($servicio);
        if ($onu['ok']) {
            return $onu;
        }

        $mensaje = $olt['mensaje'] ?? $onu['mensaje'] ?? 'No se pudo leer la potencia óptica.';

        return [
            'ok' => false,
            'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
            'valor' => null,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultarOlt(Servicio $servicio): array
    {
        $macInfo = $this->resolverMac($servicio);
        $mac = $macInfo['mac'];
        if (! $mac) {
            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                'valor' => null,
                'mensaje' => 'No hay MAC para leer potencia óptica en la OLT. Cargá la MAC del servicio o verificá PPPoE.',
            ];
        }

        $olts = $this->oltsCandidatos($servicio);
        if ($olts->isEmpty()) {
            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                'valor' => null,
                'mensaje' => 'No hay OLT con credenciales para leer la potencia óptica.',
            ];
        }

        $ultimoError = null;
        foreach ($olts as $olt) {
            try {
                $r = $this->vsol->localizarMacYConsultarOnu($olt, $mac, true);
                if ($r['success'] && ($r['pon_port'] ?? null) !== null && ($r['onu_index'] ?? null) !== null) {
                    $rx = $r['rx_power_dbm'] ?? null;
                    if ($rx === null) {
                        $ultimoError = 'ONU localizada en OLT pero sin potencia RX.';

                        continue;
                    }

                    return [
                        'ok' => true,
                        'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                        'valor' => $rx,
                        'rx_power_dbm' => $rx,
                        'tx_power_dbm' => $r['tx_power_dbm'] ?? null,
                        'mac' => $mac,
                        'olt_id' => $olt->olt_id,
                        'pon_port' => $r['pon_port'],
                        'onu_index' => $r['onu_index'],
                        'onu_estado' => $r['estado'] ?? null,
                        'onu_descripcion' => $r['descripcion'] ?? null,
                        'payload' => [
                            'mac_fuente' => $macInfo['fuente'],
                            'via' => 'olt',
                            'vlan' => $r['vlan'] ?? null,
                            'comando' => $r['comando'] ?? null,
                        ],
                        'mensaje' => 'Potencia óptica RX '.$this->formatoDbm($rx).' registrada.',
                    ];
                }
                $ultimoError = $r['message'] ?? 'MAC no encontrada en la OLT.';
            } catch (Throwable $e) {
                $ultimoError = $e->getMessage();
                Log::warning('Lectura OLT al finalizar instalación falló', [
                    'servicio_id' => $servicio->servicio_id,
                    'olt_id' => $olt->olt_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ok' => false,
            'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
            'valor' => null,
            'mensaje' => $ultimoError ?: 'No se pudo leer la potencia óptica en la OLT.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultarOnuHuawei(Servicio $servicio): array
    {
        try {
            if (! CpeInventario::esHuaweiOnu($servicio) && ! $this->huawei->puedeOperar($servicio)) {
                return [
                    'ok' => false,
                    'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                    'valor' => null,
                    'mensaje' => null,
                ];
            }

            $r = $this->huawei->leerOptica($servicio);
            $rx = $r['rx_power_dbm'] ?? null;
            if (! ($r['success'] ?? false) || $rx === null) {
                return [
                    'ok' => false,
                    'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                    'valor' => null,
                    'mensaje' => $r['message'] ?? 'No se pudo leer la potencia óptica de la ONU.',
                ];
            }

            return [
                'ok' => true,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                'valor' => $rx,
                'rx_power_dbm' => $rx,
                'tx_power_dbm' => $r['tx_power_dbm'] ?? null,
                'mac' => $servicio->mac_address,
                'payload' => [
                    'via' => $r['via'] ?? 'onu_huawei',
                ],
                'mensaje' => 'Potencia óptica RX '.$this->formatoDbm($rx).' registrada.',
            ];
        } catch (Throwable $e) {
            Log::warning('Lectura ONU Huawei al finalizar instalación falló', [
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_OPTICA,
                'valor' => null,
                'mensaje' => 'No se pudo leer la potencia óptica de la ONU.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function leerAntena(Servicio $servicio): array
    {
        @set_time_limit(60);

        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '') {
            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_ANTENA,
                'valor' => null,
                'mensaje' => 'El servicio no tiene IP para leer la intensidad de la antena.',
            ];
        }

        $macEsperada = null;
        $router = $servicio->pool?->router;
        if ($router) {
            try {
                $mk = $this->mikrotik->consultarClienteRed($router, $servicio->ip, $servicio->usuario_pppoe);
                if (! empty($mk['mac'])) {
                    $macEsperada = $mk['mac'];
                }
            } catch (Throwable) {
                // Seguir con MAC del servicio
            }
        }
        if (! $macEsperada && filled($servicio->mac_address)) {
            $macEsperada = strtoupper(str_replace('-', ':', (string) $servicio->mac_address));
        }

        try {
            $result = $this->ubnt->consultarWstalist($ip, $macEsperada);
        } catch (Throwable $e) {
            Log::warning('Lectura antena al finalizar instalación falló', [
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_ANTENA,
                'valor' => null,
                'mensaje' => 'No se pudo leer la intensidad de la antena.',
            ];
        }

        $signal = $result['signal_dbm'] ?? null;
        if (! ($result['success'] ?? false) || $signal === null) {
            return [
                'ok' => false,
                'tipo' => ServicioConexionEvento::TIPO_SENAL_ANTENA,
                'valor' => null,
                'mensaje' => $result['message'] ?? 'No se pudo leer la intensidad de la antena.',
            ];
        }

        return [
            'ok' => true,
            'tipo' => ServicioConexionEvento::TIPO_SENAL_ANTENA,
            'valor' => $signal,
            'antena_signal_dbm' => $signal,
            'antena_snr_db' => $result['snr_db'] ?? null,
            'antena_radio_iface' => 'wstalist',
            'ip' => $ip,
            'mac' => $result['mac_remota'] ?? $macEsperada,
            'router_id' => $router?->router_id,
            'payload' => [
                'host' => $ip,
                'comando' => $result['comando'] ?? 'wstalist',
                'noise_floor_dbm' => $result['noise_floor_dbm'] ?? null,
                'ccq' => $result['ccq'] ?? null,
                'via' => 'ubnt_ssh',
            ],
            'mensaje' => 'Intensidad de antena '.$this->formatoDbm($signal).' registrada.',
        ];
    }

    /**
     * @return array{mac: ?string, fuente: ?string}
     */
    private function resolverMac(Servicio $servicio): array
    {
        $router = $servicio->pool?->router;
        if ($router) {
            try {
                $mk = $this->mikrotik->consultarClienteRed($router, $servicio->ip, $servicio->usuario_pppoe);
                if (! empty($mk['mac'])) {
                    return [
                        'mac' => $mk['mac'],
                        'fuente' => $mk['mac_fuente'] ?? 'mikrotik',
                    ];
                }
            } catch (Throwable) {
                // Seguir con MAC del servicio
            }
        }

        if (filled($servicio->mac_address)) {
            return [
                'mac' => strtoupper(str_replace('-', ':', (string) $servicio->mac_address)),
                'fuente' => 'sistema',
            ];
        }

        return ['mac' => null, 'fuente' => null];
    }

    /**
     * Prioridad: caja NAP → OLT del pool → OLTs del nodo (solo si el pool no tiene OLT).
     *
     * @return Collection<int, Olt>
     */
    private function oltsCandidatos(Servicio $servicio): Collection
    {
        $olts = collect();

        $oltCaja = $servicio->cajaNapPuertoActivo?->cajaNap?->salidaPon?->olt;
        if ($oltCaja && $oltCaja->tieneCredencialesGestion()) {
            $olts->push($oltCaja);
        }

        $oltPool = $servicio->pool?->olt;
        if ($oltPool && $oltPool->tieneCredencialesGestion() && ! $olts->contains('olt_id', $oltPool->olt_id)) {
            $olts->push($oltPool);
        }

        if ($servicio->pool?->olt_id) {
            return $olts->values();
        }

        $nodoId = $servicio->pool?->router?->nodo_id
            ?? $servicio->cajaNapPuertoActivo?->cajaNap?->nodo_id;

        if ($nodoId) {
            Olt::query()
                ->where('nodo_id', $nodoId)
                ->orderBy('codigo')
                ->get()
                ->each(function ($olt) use ($olts) {
                    if ($olt->tieneCredencialesGestion() && ! $olts->contains('olt_id', $olt->olt_id)) {
                        $olts->push($olt);
                    }
                });
        }

        return $olts->values();
    }

    private function formatoDbm(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '').' dBm';
    }
}
