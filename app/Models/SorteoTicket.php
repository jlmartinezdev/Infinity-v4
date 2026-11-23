<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SorteoTicket extends Model
{
    public const ORIGEN_PAGO = 'pago';

    public const ORIGEN_APP = 'app';

    public const ORIGEN_RESENA = 'resena';

    public const ACTIVO = 'activo';

    public const ANULADO = 'anulado';

    protected $table = 'sorteo_tickets';

    protected $fillable = [
        'campana_id',
        'cliente_id',
        'numero',
        'origen',
        'cobro_id',
        'estado',
        'created_by',
        'anulado_por',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'anulado_at' => 'datetime',
            'numero' => 'integer',
        ];
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(SorteoCampana::class, 'campana_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function numeroFormateado(): string
    {
        return str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }
}
