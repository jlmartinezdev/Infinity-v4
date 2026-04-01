<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use Auditable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'cliente_id';

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
        'cedula',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'url_ubicacion',
        'estado',
        'calificacion_pago',
    ];

    public const CALIFICACION_MALO = 'malo';
    public const CALIFICACION_BUENO = 'bueno';
    public const CALIFICACION_EXCELENTE = 'excelente';

    public static function calificacionesPago(): array
    {
        return [
            self::CALIFICACION_MALO => 'Malo',
            self::CALIFICACION_BUENO => 'Bueno',
            self::CALIFICACION_EXCELENTE => 'Excelente',
        ];
    }

    public function getCalificacionPagoLabelAttribute(): ?string
    {
        return $this->calificacion_pago
            ? (self::calificacionesPago()[$this->calificacion_pago] ?? $this->calificacion_pago)
            : null;
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'cliente_id', 'cliente_id');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'cliente_id', 'cliente_id');
    }

    public function facturaInternas(): HasMany
    {
        return $this->hasMany(FacturaInterna::class, 'cliente_id', 'cliente_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cliente_id', 'cliente_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'cliente_id', 'cliente_id');
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class, 'cliente_id', 'cliente_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'cliente_id', 'cliente_id');
    }
}
