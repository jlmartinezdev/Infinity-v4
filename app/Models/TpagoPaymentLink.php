<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TpagoPaymentLink extends Model
{
    protected $table = 'tpago_payment_links';

    protected $fillable = [
        'factura_interna_id',
        'cliente_id',
        'cobro_id',
        'amount',
        'description',
        'reference_id',
        'link_alias',
        'link_url',
        'tpago_link_id',
        'status',
        'ticket_number',
        'authorization_code',
        'response_code',
        'request_payload',
        'callback_payload',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'request_payload' => 'array',
            'callback_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class, 'cobro_id');
    }

    public function estaPagado(): bool
    {
        return in_array($this->status, ['confirmed', 'paid', 'approved'], true)
            || $this->cobro_id !== null;
    }

    /** @return array<string, string> */
    public static function estados(): array
    {
        return [
            'creating' => 'Creando',
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'paid' => 'Pagado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'unavailable' => 'No disponible',
            'error' => 'Error',
            'expired' => 'Vencido',
        ];
    }

    public function etiquetaEstado(): string
    {
        if ($this->status === 'pending' && $this->expires_at && $this->expires_at->isPast()) {
            return self::estados()['expired'];
        }

        return self::estados()[$this->status] ?? $this->status;
    }

    public function colorEstado(): string
    {
        if ($this->estaPagado()) {
            return 'green';
        }
        if (in_array($this->status, ['rejected', 'error', 'unavailable'], true)) {
            return 'red';
        }
        if ($this->status === 'pending' && $this->expires_at && $this->expires_at->isPast()) {
            return 'gray';
        }
        if (in_array($this->status, ['pending', 'creating'], true)) {
            return 'amber';
        }

        return 'gray';
    }
}
