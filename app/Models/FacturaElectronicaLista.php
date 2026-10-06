<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param  list<int>  $clienteIds
     * @return array<int, string> cliente_id => nombre de lista
     */
    public static function ocupacionDeClientes(array $clienteIds, ?int $exceptListaId = null): array
    {
        $ids = [];
        foreach ($clienteIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }

        $query = DB::table('factura_electronica_lista_cliente as p')
            ->join('factura_electronica_listas as l', 'l.id', '=', 'p.lista_id')
            ->whereIn('p.cliente_id', $ids)
            ->select('p.cliente_id', 'l.nombre');
        if ($exceptListaId !== null) {
            $query->where('p.lista_id', '!=', $exceptListaId);
        }

        $map = [];
        foreach ($query->get() as $row) {
            $cid = (int) $row->cliente_id;
            if (! isset($map[$cid])) {
                $map[$cid] = (string) $row->nombre;
            }
        }

        return $map;
    }
}
