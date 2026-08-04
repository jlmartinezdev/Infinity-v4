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
        'datos_diagnostico',
        'estado',
        'prioridad',
        'reportado_desde',
        'usuario_id',
        'asignado_id',
        'actualizado_por_id',
        'observaciones',
        'nota_tecnico',
        'detalle_tecnico',
        'imagen',
        'fecha_cierre',
        'factura_interna_id',
        'monto_cobro_ticket',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
            'monto_cobro_ticket' => 'decimal:2',
            'datos_diagnostico' => 'array',
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

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id', 'usuario_id');
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id');
    }

    public static function estados(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'en_camino' => 'En camino',
            'en_proceso' => 'En proceso',
            'resuelto' => 'Resuelto',
            'no_realizado' => 'No realizado',
            'cerrado' => 'Cerrado',
            'cancelado' => 'Cancelado',
        ];
    }

    /** Estados que la app staff puede elegir al actualizar una visita. */
    public static function estadosStaffDisponibles(): array
    {
        return ['pendiente', 'en_camino', 'en_proceso', 'resuelto', 'no_realizado'];
    }

    /** Estados que ya no listan en GET /staff/visitas. */
    public static function estadosStaffCerrados(): array
    {
        return ['resuelto', 'cerrado', 'cancelado', 'no_realizado'];
    }

    /**
     * Normaliza key o label de estado a la clave interna (ej. "En camino" → en_camino).
     */
    public static function normalizarEstado(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $raw = trim($valor);
        if ($raw === '') {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', $raw));
        $mapa = self::estados();
        if (isset($mapa[$key])) {
            return $key;
        }

        foreach ($mapa as $codigo => $label) {
            if (strcasecmp($label, $raw) === 0) {
                return $codigo;
            }
        }

        // Aliases app
        $aliases = [
            'enproceso' => 'en_proceso',
            'encamino' => 'en_camino',
            'norealizado' => 'no_realizado',
            'en_camino' => 'en_camino',
            'no_realizado' => 'no_realizado',
        ];

        return $aliases[$key] ?? null;
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

    /**
     * Cobros facturados como servicio especial (sin período ni vencimiento), p. ej. cambio de contraseña.
     */
    public function esFacturaServicioEspecial(): bool
    {
        $this->loadMissing('ticketAsunto');
        $nombre = mb_strtolower(trim($this->ticketAsunto?->nombre ?? ''));

        if ($nombre === '') {
            return false;
        }

        return str_contains($nombre, 'contraseña')
            || str_contains($nombre, 'contrasena')
            || str_contains($nombre, 'password');
    }
}
