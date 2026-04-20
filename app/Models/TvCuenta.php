<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvCuenta extends Model
{
    public const MAX_ASIGNACIONES = 3;

    protected $table = 'tv_cuentas';

    protected $fillable = [
        'nombre',
        'usuario_app',
        'password',
        'vencimiento_pago',
        'dia_aviso_vencimiento',
        'perfil_1',
        'precio_perfil_1',
        'perfil_2',
        'precio_perfil_2',
        'perfil_3',
        'precio_perfil_3',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'vencimiento_pago' => 'date',
            'password' => 'encrypted',
            'dia_aviso_vencimiento' => 'integer',
            'precio_perfil_1' => 'decimal:2',
            'precio_perfil_2' => 'decimal:2',
            'precio_perfil_3' => 'decimal:2',
        ];
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(TvCuentaAsignacion::class, 'tv_cuenta_id');
    }

    public function cuposLibres(): int
    {
        return max(0, self::MAX_ASIGNACIONES - $this->asignaciones()->count());
    }
}
