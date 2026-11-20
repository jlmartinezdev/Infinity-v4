<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodoApWirelessCaida extends Model
{
    protected $table = 'nodo_ap_wireless_caidas';

    protected $primaryKey = 'caida_id';

    public $timestamps = false;

    protected $fillable = [
        'ap_id',
        'inicio',
        'fin',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function ap(): BelongsTo
    {
        return $this->belongsTo(NodoApWireless::class, 'ap_id', 'ap_id');
    }
}
