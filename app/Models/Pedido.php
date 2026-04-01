<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use Auditable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'pedido_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cliente_id',
        'fecha_pedido',
        'ubicacion',
        'maps_gps',
        'lat',
        'lon',
        'plan_id',
        'prioridad_instalacion',
        'estado_instalado',
        'usuario_pppoe_creado',
        'descripcion',
        'observaciones',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_pedido' => 'date',
            'lat' => 'float',
            'lon' => 'float',
            'estado_instalado' => 'boolean',
            'usuario_pppoe_creado' => 'boolean',
        ];
    }

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    /**
     * Relación con EstadoPedidoDetalle (hasMany)
     */
    public function estadoPedidoDetalles(): HasMany
    {
        return $this->hasMany(EstadoPedidoDetalle::class, 'pedido_id', 'pedido_id');
    }

    /**
     * Obtener el estado actual del pedido (el más reciente aprobado, o el más reciente si no hay aprobado)
     */
    public function estadoActual()
    {
        // Primero intentar obtener el estado aprobado más reciente
        $estadoAprobado = $this->estadoPedidoDetalles()
            ->where('estado', 'A')
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Si hay un estado aprobado, retornarlo
        if ($estadoAprobado) {
            return $estadoAprobado;
        }
        
        // Si no hay aprobado, retornar el más reciente (pendiente)
        return $this->estadoPedidoDetalles()
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Relación con Plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    /**
     * Relación con Agenda (hasMany)
     */
    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class, 'pedido_id', 'pedido_id');
    }

    /**
     * Etiqueta de prioridad de instalación
     */
    public static function prioridadLabel(int $prioridad): string
    {
        return match ($prioridad) {
            1 => 'Alta',
            2 => 'Media',
            3 => 'Baja',
            default => 'Media',
        };
    }
}
