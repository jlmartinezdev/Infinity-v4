<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    protected $primaryKey = 'auditoria_id';

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'tabla',
        'accion',
        'registro_id',
        'registro_key',
        'detalles',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Acciones hechas por staff del panel (omite Sistema, cron y portal).
     */
    public function scopeDeStaff(Builder $query): Builder
    {
        return $query->whereNotNull('usuario_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.usuario_id', 'auditoria.usuario_id')
                    ->whereNotNull('users.cliente_id');
            });
    }

    /**
     * Obtener detalles como array (JSON decodificado).
     */
    public function getDetallesDecodedAttribute(): ?array
    {
        if (empty($this->detalles)) {
            return null;
        }
        $decoded = json_decode($this->detalles, true);
        return is_array($decoded) ? $decoded : null;
    }
}
