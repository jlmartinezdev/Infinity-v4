<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadoPedidoDetalle extends Model
{
    use Auditable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'estado_pedido_detalles';

    /**
     * Indicates if the model has an auto-incrementing primary key.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key for the model.
     * Nota: Laravel no soporta claves primarias compuestas nativamente.
     * Usamos un campo temporal para evitar errores, pero siempre usamos where() para operaciones.
     *
     * @var string
     */
    protected $primaryKey = 'pedido_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pedido_id',
        'estado_id',
        'usuario_id',
        'fecha',
        'estado',
        'notas',
        'nodo_id',
        'tecnologia_id',
        'plan_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    /**
     * Relación con Pedido
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'pedido_id');
    }

    /**
     * Relación con EstadoPedido
     */
    public function estadoPedido(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'estado_id', 'estado_id');
    }

    /**
     * Relación con User
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Relación con Nodo (parámetro de aprobación)
     */
    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    /**
     * Relación con TipoTecnologia (parámetro de aprobación)
     */
    public function tipoTecnologia(): BelongsTo
    {
        return $this->belongsTo(TipoTecnologia::class, 'tecnologia_id', 'tecnologia_id');
    }

    /**
     * Relación con Plan (parámetro de aprobación)
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }
}
