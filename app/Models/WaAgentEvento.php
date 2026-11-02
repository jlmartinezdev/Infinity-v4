<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaAgentEvento extends Model
{
    protected $table = 'wa_agent_eventos';

    public const TIPO_RESPUESTA = 'respuesta';

    public const TIPO_RESUELTO_AUTO = 'resuelto_auto';

    public const TIPO_ESCALADO_TICKET = 'escalado_ticket';

    public const TIPO_COMPROBANTE = 'comprobante';

    public const TIPO_ZONA_AVISO = 'zona_aviso';

    public const TIPO_ZONA_OK = 'zona_ok';

    public const TIPO_OFERTA_PLAN = 'oferta_plan';

    public const TIPO_VENTA_ATRIBUIDA = 'venta_atribuida';

    public const TIPO_CSAT = 'csat';

    public const TIPO_ERROR = 'error';

    public const TIPO_COBERTURA = 'cobertura';

    public const TIPO_INSTALACION = 'instalacion';

    protected $fillable = [
        'ocurrido_at',
        'telefono',
        'cliente_id',
        'mensaje_id',
        'ticket_id',
        'pedido_id',
        'cobro_id',
        'tipo',
        'modo',
        'latency_ms',
        'sandbox',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
            'sandbox' => 'boolean',
            'meta' => 'array',
            'latency_ms' => 'integer',
        ];
    }
}
