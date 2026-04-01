<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use Auditable;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'ruc',
        'email',
        'telefono',
        'direccion',
        'notas',
        'estado',
    ];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'proveedor_id');
    }
}
