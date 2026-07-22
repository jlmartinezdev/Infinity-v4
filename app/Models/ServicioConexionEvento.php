<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioConexionEvento extends Model
{
    protected $table = 'servicio_conexion_eventos';

    protected $primaryKey = 'servicio_conexion_evento_id';

    public const TIPO_PPPOE_UP = 'pppoe_up';

    public const TIPO_PPPOE_DOWN = 'pppoe_down';

    public const TIPO_SENAL_OPTICA = 'senal_optica';

    public const TIPO_SENAL_ANTENA = 'senal_antena';

    public const TIPO_SNAPSHOT = 'snapshot';

    public const FUENTE_MIKROTIK_CONSULTA = 'mikrotik_consulta';

    public const FUENTE_OLT_CONSULTA = 'olt_consulta';

    public const FUENTE_CRON = 'cron';

    public const FUENTE_MANUAL = 'manual';

    public const FUENTE_WEBHOOK = 'webhook';

    protected $fillable = [
        'servicio_id',
        'tipo',
        'fuente',
        'ocurrio_at',
        'pppoe_estado',
        'usuario_pppoe',
        'ip',
        'mac_address',
        'router_id',
        'uptime',
        'olt_id',
        'pon_port',
        'onu_index',
        'rx_power_dbm',
        'tx_power_dbm',
        'onu_estado',
        'onu_descripcion',
        'antena_signal_dbm',
        'antena_snr_db',
        'antena_radio_iface',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'ocurrio_at' => 'datetime',
            'rx_power_dbm' => 'decimal:2',
            'tx_power_dbm' => 'decimal:2',
            'antena_signal_dbm' => 'decimal:2',
            'antena_snr_db' => 'decimal:2',
            'pon_port' => 'integer',
            'onu_index' => 'integer',
            'payload' => 'array',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id', 'router_id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'olt_id', 'olt_id');
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_PPPOE_UP => 'PPPoE conectado',
            self::TIPO_PPPOE_DOWN => 'PPPoE desconectado',
            self::TIPO_SENAL_OPTICA => 'Señal óptica ONU',
            self::TIPO_SENAL_ANTENA => 'Señal antena',
            self::TIPO_SNAPSHOT => 'Snapshot',
            default => $this->tipo,
        };
    }

    /**
     * Registra señal óptica ONU (herramientas-red / sync).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrarSenalOptica(Servicio $servicio, array $datos, string $fuente = self::FUENTE_OLT_CONSULTA): self
    {
        return self::create([
            'servicio_id' => $servicio->servicio_id,
            'tipo' => self::TIPO_SENAL_OPTICA,
            'fuente' => $fuente,
            'ocurrio_at' => now(),
            'mac_address' => $datos['mac'] ?? $servicio->mac_address,
            'olt_id' => $datos['olt_id'] ?? null,
            'pon_port' => $datos['pon_port'] ?? null,
            'onu_index' => $datos['onu_index'] ?? null,
            'rx_power_dbm' => $datos['rx_power_dbm'] ?? null,
            'tx_power_dbm' => $datos['tx_power_dbm'] ?? null,
            'onu_estado' => $datos['estado'] ?? null,
            'onu_descripcion' => $datos['descripcion'] ?? null,
            'payload' => $datos['payload'] ?? null,
        ]);
    }

    /**
     * Registra señal de antena (wireless).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrarSenalAntena(Servicio $servicio, array $datos, string $fuente = self::FUENTE_MIKROTIK_CONSULTA): self
    {
        return self::create([
            'servicio_id' => $servicio->servicio_id,
            'tipo' => self::TIPO_SENAL_ANTENA,
            'fuente' => $fuente,
            'ocurrio_at' => now(),
            'mac_address' => $datos['mac'] ?? $servicio->mac_address,
            'router_id' => $datos['router_id'] ?? $servicio->pool?->router_id,
            'ip' => $datos['ip'] ?? $servicio->ip,
            'antena_signal_dbm' => $datos['antena_signal_dbm'] ?? $datos['signal_dbm'] ?? null,
            'antena_snr_db' => $datos['antena_snr_db'] ?? $datos['snr_db'] ?? null,
            'antena_radio_iface' => $datos['antena_radio_iface'] ?? $datos['radio_iface'] ?? null,
            'payload' => $datos['payload'] ?? null,
        ]);
    }

    /**
     * Registra cambio de sesión PPPoE solo si cambió respecto al último evento up/down.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrarPppoeSiCambio(
        Servicio $servicio,
        bool $online,
        array $datos = [],
        string $fuente = self::FUENTE_MIKROTIK_CONSULTA
    ): ?self {
        $tipo = $online ? self::TIPO_PPPOE_UP : self::TIPO_PPPOE_DOWN;

        $ultimo = self::query()
            ->where('servicio_id', $servicio->servicio_id)
            ->whereIn('tipo', [self::TIPO_PPPOE_UP, self::TIPO_PPPOE_DOWN])
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->first();

        if ($ultimo && $ultimo->tipo === $tipo) {
            return null;
        }

        return self::create([
            'servicio_id' => $servicio->servicio_id,
            'tipo' => $tipo,
            'fuente' => $fuente,
            'ocurrio_at' => now(),
            'pppoe_estado' => $online ? 'up' : 'down',
            'usuario_pppoe' => $datos['usuario_pppoe'] ?? $servicio->usuario_pppoe,
            'ip' => $datos['ip'] ?? $servicio->ip,
            'mac_address' => $datos['mac'] ?? $servicio->mac_address,
            'router_id' => $datos['router_id'] ?? $servicio->pool?->router_id,
            'uptime' => $datos['uptime'] ?? null,
            'payload' => $datos['payload'] ?? null,
        ]);
    }
}
