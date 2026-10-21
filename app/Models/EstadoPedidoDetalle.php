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
     * Delete/update must include estado_id; otherwise Eloquent borra todo el pedido.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where($this->getTable().'.pedido_id', $this->getAttribute('pedido_id'))
            ->where($this->getTable().'.estado_id', $this->getAttribute('estado_id'));
    }

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
        'pool_id',
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

    public function pool(): BelongsTo
    {
        return $this->belongsTo(RouterIpPool::class, 'pool_id', 'pool_id');
    }

    /**
     * IDs de estado a borrar al eliminar uno del historial (ese y los posteriores).
     * Null si no existe o si es el primer estado del pedido.
     *
     * @param  list<int>  $estadoIdsDelPedido
     * @return list<int>|null
     */
    public static function idsABorrarDesde(array $estadoIdsDelPedido, int $estadoId): ?array
    {
        $ids = array_values(array_unique(array_map('intval', $estadoIdsDelPedido)));
        if ($ids === [] || ! in_array($estadoId, $ids, true)) {
            return null;
        }
        if ($estadoId === min($ids)) {
            return null;
        }

        $borrar = array_values(array_filter($ids, fn (int $id) => $id >= $estadoId));
        sort($borrar);

        return $borrar;
    }
}
