<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class FinCategoria extends Model
{
    use Auditable;

    protected $table = 'fin_categorias';

    protected $fillable = ['nombre', 'clase', 'activa'];

    protected $casts = ['activa' => 'boolean'];
}
