<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaGasto extends Model
{
    protected $table = 'categorias_gasto';

    protected $fillable = ['nombre', 'descripcion'];

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'categoria_gasto_id');
    }
}
