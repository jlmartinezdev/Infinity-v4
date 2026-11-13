<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRecorridoAjuste extends Model
{
    protected $table = 'staff_recorrido_ajustes';

    protected $fillable = [
        'usuario_id',
        'fecha',
        'traza',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'traza' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
