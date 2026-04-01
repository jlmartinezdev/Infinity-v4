<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $fillable = ['codigo', 'nombre', 'categoria', 'orden'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id', 'id', 'rol_id');
    }

    /**
     * Permisos agrupados por categoría para la UI.
     */
    public static function porCategoria(): array
    {
        return static::orderBy('categoria')->orderBy('orden')->orderBy('nombre')
            ->get()
            ->groupBy('categoria')
            ->map(fn ($items) => $items->pluck('nombre', 'codigo')->toArray())
            ->toArray();
    }

    /**
     * Todos los códigos de permiso.
     */
    public static function codigos(): array
    {
        return static::pluck('codigo')->toArray();
    }
}
