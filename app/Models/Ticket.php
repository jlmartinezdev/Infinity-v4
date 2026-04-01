<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use Auditable;

    protected $table = 'tickets';

    protected $fillable = [
        'cliente_id',
        'pedido_id',
        'ticket_asunto_id',
        'descripcion',
        'estado',
        'prioridad',
        'reportado_desde',
        'usuario_id',
        'asignado_id',
        'observaciones',
        'imagen',
        'fecha_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'pedido_id');
    }

    public function ticketAsunto(): BelongsTo
    {
        return $this->belongsTo(TicketAsunto::class, 'ticket_asunto_id', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_id', 'usuario_id');
    }

    public static function estados(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'en_proceso' => 'En proceso',
            'resuelto' => 'Resuelto',
            'cerrado' => 'Cerrado',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function prioridades(): array
    {
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ];
    }

    public static function reportadoDesdeOpciones(): array
    {
        return [
            'web' => 'Web',
            'whatsapp' => 'WhatsApp',
            'telefono' => 'Teléfono',
            'app' => 'App',
            'presencial' => 'Presencial',
            'otro' => 'Otro',
        ];
    }
}
