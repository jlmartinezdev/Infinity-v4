<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacturaElectronicaLista extends Model
{
    public const MAX_CLIENTES = 300;

    protected $table = 'factura_electronica_listas';

    protected $fillable = [
        'nombre',
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Cliente::class,
            'factura_electronica_lista_cliente',
            'lista_id',
            'cliente_id'
        )->withTimestamps()->orderByPivot('id');
    }

    /**
     * @return list<int>
     */
    public function clienteIdsOrdenados(): array
    {
        return $this->clientes()
            ->orderByPivot('id')
            ->pluck('clientes.cliente_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
