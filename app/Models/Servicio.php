<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Servicio extends Model
{
    use Auditable;

    protected $table = 'servicios';

    protected $primaryKey = 'servicio_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'pool_id',
        'plan_id',
        'pedido_id',
        'ip',
        'usuario_pppoe',
        'password_pppoe',
        'fecha_instalacion',
        'fecha_cancelacion',
        'estado',
        'fecha_suspension',
        'motivo_suspension',
        'mac_address',
        'pppoe_status',
        'pppoe_synced',
        'estado_pago',
        'saldo_a_favor',
        'app_tv',
        'cantidad_perfil_app',
        'precio_app',
    ];

    const ESTADO_ACTIVO = 'A';

    const ESTADO_SUSPENDIDO = 'S';

    const ESTADO_CORTADO = 'C';

    const ESTADO_CANCELADO = 'X';

    public static function estadosDisponibles(): array
    {
        return [
            self::ESTADO_ACTIVO => 'Activo',
            self::ESTADO_SUSPENDIDO => 'Suspendido',
            self::ESTADO_CORTADO => 'Cortado',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
            'fecha_cancelacion' => 'date',
            'fecha_suspension' => 'date',
            'pppoe_synced' => 'datetime',
            'saldo_a_favor' => 'decimal:2',
            'app_tv' => 'boolean',
            'cantidad_perfil_app' => 'integer',
            'precio_app' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'pedido_id');
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(RouterIpPool::class, 'pool_id', 'pool_id');
    }

    public function servicioHotspot(): HasOne
    {
        return $this->hasOne(ServicioHotspot::class, 'servicio_id', 'servicio_id');
    }

    /** Puerto FTTH en caja NAP (si el servicio está empalado en fibra). */
    public function cajaNapPuertoActivo(): HasOne
    {
        return $this->hasOne(CajaNapPuertoActivo::class, 'servicio_id', 'servicio_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaSuspendido(): bool
    {
        return $this->estado === self::ESTADO_SUSPENDIDO;
    }

    /**
     * Suspender servicio (por falta de pago u otro motivo).
     */
    public function suspender(string $motivo = 'Falta de pago'): void
    {
        $this->update([
            'estado' => self::ESTADO_SUSPENDIDO,
            'fecha_suspension' => now()->toDateString(),
            'motivo_suspension' => $motivo,
        ]);
    }

    /**
     * Reactivar servicio (pago recibido o manual).
     */
    public function activar(): void
    {
        $this->update([
            'estado' => self::ESTADO_ACTIVO,
            'fecha_suspension' => null,
            'motivo_suspension' => null,
        ]);
    }
}
