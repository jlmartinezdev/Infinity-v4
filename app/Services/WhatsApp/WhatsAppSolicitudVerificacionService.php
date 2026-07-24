<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMensaje;

/**
 * Puente del webhook: OTP invertido de registro (antes de crear la solicitud).
 *
 * @deprecated Preferí WhatsAppRegistroOtpService; se mantiene el nombre por el webhook.
 */
class WhatsAppSolicitudVerificacionService
{
    public function __construct(
        private readonly WhatsAppRegistroOtpService $otp,
    ) {}

    /**
     * @return bool true si el mensaje era un pedido de código OTP
     */
    public function intentarVerificar(WhatsappMensaje $mensaje): bool
    {
        return $this->otp->intentarEmitirDesdeMensaje($mensaje);
    }
}
