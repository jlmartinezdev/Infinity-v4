<?php

namespace App\Support;

use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Services\PedidoNodoOpcionesService;

class HerramientasRedPayload
{
    public static function relaciones(): array
    {
        return [
            'cliente',
            'pool.olt',
            'pool.router.nodo',
            'plan.tipoTecnologia',
            'cajaNapPuertoActivo.cajaNap.salidaPon.olt',
        ];
    }

    public static function fromServicio(Servicio $servicio): array
    {
        $servicio->loadMissing(self::relaciones());

        $clienteNombre = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));
        $router = $servicio->pool?->router;
        $esFibra = self::esFibra($servicio);
        $esAntena = self::esAntena($servicio);

        $conexionEventos = ServicioConexionEvento::query()
            ->where('servicio_id', $servicio->servicio_id)
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->limit(30)
            ->get();

        $timeline = PppoeTimeline12h::construir($servicio->servicio_id);

        $ultimaOptica = ServicioConexionEvento::query()
            ->where('servicio_id', $servicio->servicio_id)
            ->where('tipo', ServicioConexionEvento::TIPO_SENAL_OPTICA)
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->first();

        $ultimaAntena = ServicioConexionEvento::query()
            ->where('servicio_id', $servicio->servicio_id)
            ->where('tipo', ServicioConexionEvento::TIPO_SENAL_ANTENA)
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->first();

        return [
            'servicio' => [
                'servicio_id' => (int) $servicio->servicio_id,
                'ip' => $servicio->ip,
                'usuario_pppoe' => $servicio->usuario_pppoe,
                'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : 'Servicio #'.$servicio->servicio_id,
                'cliente_id' => $servicio->cliente_id ? (int) $servicio->cliente_id : null,
                'cliente_url' => $servicio->cliente_id ? route('clientes.detalle', $servicio->cliente_id) : null,
                'edit_url' => route('servicios.edit', $servicio->servicio_id),
                'router_nombre' => $router?->nombre ?? $router?->ip,
                'nodo' => $router?->nodo?->descripcion,
                'desc_onu' => $servicio->usuario_pppoe ?: ($clienteNombre !== '' ? $clienteNombre : ''),
                'tr069_serial' => $servicio->tr069_serial,
                'tr069_product_class' => $servicio->tr069_product_class,
                'mac_address' => $servicio->mac_address,
                'cpe_acceso' => $servicio->cpe_acceso,
                'equipo_resumen' => CpeInventario::resumen($servicio),
                'tecnologia' => $esFibra ? 'gpon' : ($esAntena ? 'wireless' : null),
                'tecnologia_label' => $servicio->plan?->tipoTecnologia?->descripcion,
            ],
            'urls' => [
                'ping' => route('servicios.ping', $servicio->servicio_id),
                'mikrotik' => route('servicios.herramientas-red.mikrotik', $servicio->servicio_id),
                'antena' => route('servicios.herramientas-red.antena', $servicio->servicio_id),
                'antena_dhcp' => route('servicios.herramientas-red.antena-dhcp', $servicio->servicio_id),
                'olt' => route('servicios.herramientas-red.olt', $servicio->servicio_id),
                'olt_desc' => route('servicios.herramientas-red.olt-desc', $servicio->servicio_id),
                'tr069' => route('servicios.herramientas-red.tr069', $servicio->servicio_id),
                'tr069_hosts' => route('servicios.herramientas-red.tr069-hosts', $servicio->servicio_id),
                'tr069_reboot' => route('servicios.herramientas-red.tr069-reboot', $servicio->servicio_id),
                'tr069_refresh' => route('servicios.herramientas-red.tr069-refresh', $servicio->servicio_id),
                'tr069_password' => route('servicios.herramientas-red.tr069-password', $servicio->servicio_id),
                'servicios_index' => route('servicios.index'),
            ],
            'csrf' => csrf_token(),
            'es_fibra' => $esFibra,
            'es_antena' => $esAntena,
            'tiene_router' => (bool) $router,
            'tr069_enabled' => (bool) config('genieacs.enabled')
                && filled(config('genieacs.nbi_url'))
                && CpeInventario::usaAcs($servicio),
            'cpe_ssh' => CpeInventario::usaSshCpe($servicio),
            'ultima_optica' => self::serializarOptica($ultimaOptica),
            'ultima_antena' => self::serializarAntena($ultimaAntena),
            'timeline' => self::serializarTimeline($timeline),
            'eventos' => $conexionEventos->map(fn (ServicioConexionEvento $ev) => self::serializarEvento($ev))->values()->all(),
        ];
    }

    public static function opcionServicio(Servicio $servicio): array
    {
        $parts = ['#'.$servicio->servicio_id];
        if (trim((string) ($servicio->ip ?? '')) !== '') {
            $parts[] = $servicio->ip;
        }
        if (trim((string) ($servicio->usuario_pppoe ?? '')) !== '') {
            $parts[] = $servicio->usuario_pppoe;
        }

        return [
            'servicio_id' => (int) $servicio->servicio_id,
            'label' => implode(' · ', $parts),
            'datos_url' => route('servicios.herramientas-red.datos', $servicio->servicio_id),
        ];
    }

    public static function esFibra(Servicio $servicio): bool
    {
        $servicio->loadMissing(self::relaciones());

        $techDesc = $servicio->plan?->tipoTecnologia?->descripcion;
        if (PedidoNodoOpcionesService::descripcionEsGpon($techDesc)) {
            return true;
        }
        if (PedidoNodoOpcionesService::descripcionEsWireless($techDesc)) {
            return false;
        }
        if (filled($servicio->cpe_onu)) {
            return true;
        }
        if (filled($servicio->cpe_antena)) {
            return false;
        }

        if ($servicio->cajaNapPuertoActivo) {
            return true;
        }
        if ($servicio->pool?->olt_id) {
            return true;
        }
        if ($servicio->pool?->router?->nodo?->manejaGpon()) {
            return true;
        }
        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));

        return str_contains($planNombre, 'fibra')
            || str_contains($planNombre, 'gpon')
            || str_contains($planNombre, 'ftth');
    }

    public static function esAntena(Servicio $servicio): bool
    {
        $servicio->loadMissing(self::relaciones());

        $techDesc = $servicio->plan?->tipoTecnologia?->descripcion;
        if (PedidoNodoOpcionesService::descripcionEsWireless($techDesc)) {
            return true;
        }
        if (PedidoNodoOpcionesService::descripcionEsGpon($techDesc)) {
            return false;
        }
        if (filled($servicio->cpe_antena)) {
            return true;
        }
        if (filled($servicio->cpe_onu)) {
            return false;
        }
        if (self::esFibra($servicio)) {
            return false;
        }

        $nodo = $servicio->pool?->router?->nodo;
        if ($nodo && $nodo->manejaWireless() && ! $nodo->manejaGpon()) {
            return true;
        }
        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));
        if (str_contains($planNombre, 'wireless') || str_contains($planNombre, 'antena') || str_contains($planNombre, 'radio')) {
            return true;
        }

        return false;
    }

    private static function serializarOptica(?ServicioConexionEvento $ev): ?array
    {
        if (! $ev) {
            return null;
        }

        return [
            'tx_power_dbm' => $ev->tx_power_dbm,
            'rx_power_dbm' => $ev->rx_power_dbm,
            'pon_port' => $ev->pon_port,
            'onu_index' => $ev->onu_index,
            'onu_estado' => $ev->onu_estado,
            'ocurrio_at' => $ev->ocurrio_at?->format('d/m/Y H:i'),
        ];
    }

    private static function serializarAntena(?ServicioConexionEvento $ev): ?array
    {
        if (! $ev) {
            return null;
        }

        $payload = is_array($ev->payload) ? $ev->payload : [];

        return [
            'antena_signal_dbm' => $ev->antena_signal_dbm !== null ? (float) $ev->antena_signal_dbm : null,
            'antena_snr_db' => $ev->antena_snr_db,
            'noise_floor_dbm' => $payload['noise_floor_dbm'] ?? null,
            'ccq' => $payload['ccq'] ?? null,
            'ocurrio_at' => $ev->ocurrio_at?->format('d/m/Y H:i'),
        ];
    }

    private static function serializarTimeline(array $timeline): array
    {
        $segmentos = [];
        foreach ($timeline['segmentos'] ?? [] as $seg) {
            $estado = $seg['estado'] ?? 'unknown';
            $segmentos[] = [
                'estado' => $estado,
                'etiqueta' => PppoeTimeline12h::etiquetaEstado($estado),
                'left_pct' => $seg['left_pct'],
                'width_pct' => $seg['width_pct'],
                'title' => PppoeTimeline12h::etiquetaEstado($estado)
                    .' · '.$seg['inicio']->format('H:i:s').' – '.$seg['fin']->format('H:i:s')
                    .' · '.PppoeTimeline12h::formatearDuracion((int) $seg['duracion_segundos']),
            ];
        }

        return [
            'inicio' => $timeline['inicio']->format('d/m H:i'),
            'fin' => $timeline['fin']->format('d/m H:i'),
            'estado_actual' => $timeline['estado_actual'] ?? 'unknown',
            'conectado_humano' => PppoeTimeline12h::formatearDuracion((int) ($timeline['conectado_segundos'] ?? 0)),
            'desconectado_humano' => PppoeTimeline12h::formatearDuracion((int) ($timeline['desconectado_segundos'] ?? 0)),
            'sin_datos_humano' => PppoeTimeline12h::formatearDuracion((int) ($timeline['sin_datos_segundos'] ?? 0)),
            'conectado_pct' => $timeline['conectado_pct'] ?? 0,
            'desconectado_pct' => $timeline['desconectado_pct'] ?? 0,
            'sin_datos_pct' => $timeline['sin_datos_pct'] ?? 0,
            'segmentos' => $segmentos,
            'marcas' => $timeline['marcas'] ?? [],
        ];
    }

    private static function serializarEvento(ServicioConexionEvento $ev): array
    {
        $badge = match ($ev->tipo) {
            ServicioConexionEvento::TIPO_SENAL_OPTICA => 'olt',
            ServicioConexionEvento::TIPO_SENAL_ANTENA => 'wifi',
            ServicioConexionEvento::TIPO_PPPOE_UP => 'up',
            ServicioConexionEvento::TIPO_PPPOE_DOWN => 'down',
            default => null,
        };

        $badgeLabel = match ($ev->tipo) {
            ServicioConexionEvento::TIPO_SENAL_OPTICA => 'ONU_SIG',
            ServicioConexionEvento::TIPO_SENAL_ANTENA => 'WIFI',
            ServicioConexionEvento::TIPO_PPPOE_UP => 'PPPoE UP',
            ServicioConexionEvento::TIPO_PPPOE_DOWN => 'PPPoE DOWN',
            default => $ev->etiquetaTipo(),
        };

        return [
            'ocurrio_at' => $ev->ocurrio_at?->format('d/m/Y H:i:s'),
            'tipo' => $ev->tipo,
            'badge' => $badge,
            'badge_label' => $badgeLabel,
            'detalle' => self::eventoDetalle($ev),
            'fuente' => $ev->fuente ?: '—',
        ];
    }

    private static function eventoDetalle(ServicioConexionEvento $ev): string
    {
        if ($ev->tipo === ServicioConexionEvento::TIPO_SENAL_OPTICA) {
            $parts = [];
            if ($ev->pon_port !== null && $ev->onu_index !== null) {
                $parts[] = 'PON '.$ev->pon_port.':'.$ev->onu_index;
            }
            if ($ev->rx_power_dbm !== null) {
                $parts[] = 'RX '.$ev->rx_power_dbm.' dBm';
            }
            if ($ev->onu_estado) {
                $parts[] = $ev->onu_estado;
            }
            if ($ev->onu_descripcion) {
                $parts[] = $ev->onu_descripcion;
            }

            return implode(' · ', $parts) ?: '—';
        }

        if ($ev->tipo === ServicioConexionEvento::TIPO_SENAL_ANTENA) {
            $parts = [];
            if ($ev->antena_signal_dbm !== null) {
                $parts[] = 'Señal '.$ev->antena_signal_dbm.' dBm';
            }
            if ($ev->antena_snr_db !== null) {
                $parts[] = 'SNR '.$ev->antena_snr_db.' dB';
            }
            $payload = is_array($ev->payload) ? $ev->payload : [];
            foreach (['noise_floor_dbm' => 'Noise', 'ccq' => 'CCQ', 'tx_rx_rate' => 'TX/RX', 'capacity' => 'Cap.', 'distance' => 'Dist.', 'ap_name' => 'AP', 'mac_remota' => 'MAC'] as $key => $label) {
                if (! empty($payload[$key])) {
                    $suf = $key === 'noise_floor_dbm' ? ' dBm' : ($key === 'ccq' ? '%' : '');
                    $parts[] = $label.' '.$payload[$key].$suf;
                }
            }
            if ($ev->antena_radio_iface) {
                $parts[] = $ev->antena_radio_iface;
            }

            return implode(' · ', $parts) ?: '—';
        }

        $parts = [$ev->pppoe_estado === 'up' ? 'Online' : 'Offline'];
        if ($ev->usuario_pppoe) {
            $parts[] = $ev->usuario_pppoe;
        }
        if ($ev->uptime) {
            $parts[] = 'uptime '.$ev->uptime;
        }
        if ($ev->mac_address) {
            $parts[] = $ev->mac_address;
        }

        return implode(' · ', $parts);
    }
}
