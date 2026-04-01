<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FibraColor extends Model
{
    protected $table = 'fibra_colores';

    protected $primaryKey = 'fibra_color_id';

    protected $fillable = [
        'nombre',
        'codigo_hex',
        'codigo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function lineaCables(): HasMany
    {
        return $this->hasMany(LineaCable::class, 'fibra_color_id', 'fibra_color_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
