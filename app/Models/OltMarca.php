<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class OltMarca extends Model
{
    use Auditable;

    protected $table = 'olt_marcas';

    protected $primaryKey = 'olt_marca_id';

    protected $fillable = [
        'nombre',
        'estado',
        'notas',
    ];

    public function getRouteKeyName(): string
    {
        return 'olt_marca_id';
    }
}
