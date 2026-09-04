<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensaje extends Model
{
    protected $table = 'whatsapp_mensajes';

    public const DIRECCION_ENTRADA = 'entrada';

    public const DIRECCION_SALIDA = 'salida';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_LEIDO = 'leido';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_RECIBIDO = 'recibido';

    /** Conversaciones inventadas del playground Test n8n (no salen a Meta). */
    public const CONTEXTO_TEST_N8N = 'test_n8n';

    public const TELEFONO_SANDBOX_PREFIX = '595000';

    protected $fillable = [
        'direccion',
        'telefono',
        'contacto_nombre',
        'tipo',
        'cuerpo',
        'template_name',
        'template_language',
        'wamid',
        'estado',
        'error_code',
        'error_message',
        'cliente_id',
        'ticket_id',
        'contexto_tipo',
        'contexto_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'contexto_id' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function esEntrada(): bool
    {
        return $this->direccion === self::DIRECCION_ENTRADA;
    }

    public function esSalida(): bool
    {
        return $this->direccion === self::DIRECCION_SALIDA;
    }

    public function esFallido(): bool
    {
        return $this->estado === self::ESTADO_FALLIDO;
    }

    public function scopeExcluirSandboxN8n($query)
    {
        return $query->where('telefono', 'not like', self::TELEFONO_SANDBOX_PREFIX.'%');
    }

    /**
     * Detalle legible de un fallo Meta / local.
     *
     * @return array{
     *   codigo:?string,
     *   titulo:?string,
     *   mensaje:?string,
     *   detalle:?string,
     *   tip:?string,
     *   href_doc:?string
     * }
     */
    public function detalleFallo(): array
    {
        $codigo = $this->error_code ? (string) $this->error_code : null;
        $titulo = null;
        $mensaje = $this->error_message ? (string) $this->error_message : null;
        $detalle = null;

        $errors = data_get($this->payload, 'status.errors')
            ?? data_get($this->payload, 'response.error.error_data')
            ?? null;

        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $err = $errors[0];
            $codigo = $codigo ?: (isset($err['code']) ? (string) $err['code'] : null);
            $titulo = isset($err['title']) ? (string) $err['title'] : null;
            $mensaje = $mensaje ?: (isset($err['message']) ? (string) $err['message'] : null);
            $detalle = data_get($err, 'error_data.details')
                ?? data_get($err, 'error_data.detail')
                ?? null;
            if (is_string($detalle)) {
                $detalle = trim($detalle);
            } else {
                $detalle = null;
            }
        } else {
            $apiError = data_get($this->payload, 'response.error');
            if (is_array($apiError)) {
                $codigo = $codigo ?: (isset($apiError['code']) ? (string) $apiError['code'] : null);
                $titulo = $titulo ?: (isset($apiError['error_user_title']) ? (string) $apiError['error_user_title'] : null)
                    ?: (isset($apiError['type']) ? (string) $apiError['type'] : null);
                $mensaje = $mensaje ?: (isset($apiError['error_user_msg']) ? (string) $apiError['error_user_msg'] : null)
                    ?: (isset($apiError['message']) ? (string) $apiError['message'] : null);
                $detalle = $detalle ?: (isset($apiError['error_data']['details']) ? (string) $apiError['error_data']['details'] : null);
            }
        }

        $tips = [
            '131047' => 'Fuera de la ventana de 24 h: el cliente no te escribió recientemente. Usá una plantilla APPROVED.',
            '131042' => 'Cuenta de WhatsApp Business con problema de pago o elegibilidad en Meta.',
            '131026' => 'Mensaje no entregable (número inválido, sin WhatsApp o bloqueado).',
            '131031' => 'El destinatario no es un usuario válido de WhatsApp.',
            '131051' => 'Tipo de mensaje no soportado para este destinatario.',
            '132000' => 'Cantidad de parámetros de plantilla incorrecta.',
            '132001' => 'Plantilla no existe o no está aprobada para este idioma.',
            '132015' => 'Plantilla pausada o limitada por Meta.',
            '132016' => 'Plantilla deshabilitada.',
            '100' => 'Parámetro inválido en la solicitud a Meta.',
            '190' => 'Token de acceso inválido o vencido. Renová WHATSAPP_TOKEN.',
            '368' => 'Cuenta o app temporalmente bloqueada por Meta.',
        ];

        $tip = $codigo && isset($tips[$codigo]) ? $tips[$codigo] : null;
        if (! $tip && $mensaje && stripos($mensaje, 'Re-engagement') !== false) {
            $tip = $tips['131047'];
        }

        return [
            'codigo' => $codigo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'detalle' => $detalle,
            'tip' => $tip,
            'href_doc' => $codigo
                ? 'https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/'
                : null,
        ];
    }
}
