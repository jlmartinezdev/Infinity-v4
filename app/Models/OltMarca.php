<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function olts(): HasMany
    {
        return $this->hasMany(Olt::class, 'olt_marca_id', 'olt_marca_id');
    }

    public function getRouteKeyName(): string
    {
        return 'olt_marca_id';
    }
}
