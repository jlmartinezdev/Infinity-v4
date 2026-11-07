<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinCuenta extends Model
{
    use Auditable;

    protected $table = 'fin_cuentas';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'activa', 'orden'];

    protected $casts = ['activa' => 'boolean'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(FinMovimiento::class, 'cuenta_id');
    }
}
