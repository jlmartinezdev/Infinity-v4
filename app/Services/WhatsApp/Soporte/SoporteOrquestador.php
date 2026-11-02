<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppAgentService;
use App\Support\SoporteAdminConfig;

/**
 * Decide la respuesta de soporte antes de n8n. El modelo no mide ni elige el ping.
 */
class SoporteOrquestador
{
    public function __construct(
        private readonly CuentaWhatsappService $cuentas,
        private readonly DialogoSoporte $dialogo,
        private readonly EvaluadorDiagnostico $evaluador,
        private readonly RecolectorDiagnostico $recolector,
        private readonly IncidenteZonaService $incidentes,
        private readonly CasoSelector $casos,
        private readonly OfertaCierreService $ofertas,
        private readonly WifiSoporteService $wifi,
        private readonly ImpresoraSoporteService $impresoras,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function intentar(WhatsappMensaje $mensaje, bool $sandbox): ?array
    {
        $telefono = $this->telefonoCuenta($mensaje);
        $texto = trim((string) $mensaje->cuerpo);
        $cuenta = $this->cuentas->buscar($telefono);
        if (! $cuenta) {
            $cuenta = $this->cuentas->buscarEnTexto($texto);
        }
        $claveDialogo = $telefono !== '' ? $telefono : (string) ($cuenta['whatsapp'] ?? $mensaje->telefono);
        $clavesDialogo = array_values(array_unique(array_filter([
            $claveDialogo,
            (string) $mensaje->telefono,
            (string) ($cuenta['whatsapp'] ?? ''),
        ])));
        $estadoDialogo = [];
        foreach ($clavesDialogo as $clave) {
            $estadoDialogo = $this->dialogo->leer($clave);
            if ($estadoDialogo !== []) {
                break;
            }
        }
        $paso = isset($estadoDialogo['paso']) ? (string) $estadoDialogo['paso'] : '';

        $parecePago = WhatsAppAgentService::textoParecePago($texto);
        $tipoImagen = (string) data_get($mensaje->payload, 'wa_vision.tipo', '');
        $pareceComprobante = WhatsAppAgentService::detectarComprobante(
            (string) ($mensaje->tipo ?: 'text'),
            (string) $mensaje->cuerpo,
            [],
            $tipoImagen !== '' ? $tipoImagen : null,
        );
        $pareceFalla = SoporteTexto::pareceFalla($texto) && ! WhatsAppAgentService::textoPareceInstalacion($texto);
        $pareceWifi = SoporteTexto::pareceCambioWifi($texto);
        $pareceImpresora = SoporteTexto::pareceImpresora($texto);
        $identificando = $paso === 'identificar';
        $soloIdentificador = SoporteTexto::esSoloIdentificador($texto);
        if ($cuenta && ($identificando || ($soloIdentificador && $pareceFalla === false && $paso !== ''))) {
            $pareceFalla = true;
        }
        if ($cuenta && $soloIdentificador && $identificando) {
            $pareceFalla = true;
        }
        $suspendido = $cuenta && in_array($cuenta['estado'] ?? '', ['suspendido', 'cortado'], true);

        $entra = $paso !== ''
            || $pareceFalla
            || $pareceWifi
            || $pareceImpresora
            || $pareceComprobante
            || ($cuenta && $soloIdentificador && $identificando)
            || ($suspendido && ! $parecePago && ! $pareceComprobante);

        if (! $entra) {
            return null;
        }

        if (! $cuenta && ($pareceFalla || $identificando || $pareceImpresora || $pareceWifi)) {
            $this->dialogo->guardar($claveDialogo, [
                'paso' => 'identificar',
                'medicion' => [],
                'cliente_id' => null,
            ]);

            return $this->paquete(FraseCliente::SIN_CUENTA, false, null, null, 'sin_cuenta', null, null);
        }

        $medicion = is_array($estadoDialogo['medicion'] ?? null) ? $estadoDialogo['medicion'] : [];
        $necesitaMedir = $pareceFalla || $paso === 'cable' || ($paso === 'sitio' && SoporteTexto::sitio($texto));
        $servicio = $cuenta ? $this->cuentas->servicioDe($cuenta['servicio_id'] ?? null) : null;

        if ($servicio && $necesitaMedir && ! $suspendido && ! $pareceWifi) {
            $sitio = $paso === 'sitio' ? SoporteTexto::sitio($texto) : null;
            $medicion = $this->recolector->medir($servicio, $sitio);
        }

        $decision = $this->evaluador->decidir([
            'estado' => $cuenta['estado'] ?? null,
            'saldo' => $cuenta['saldo'] ?? 0,
            'parece_pago' => $parecePago,
            'parece_comprobante' => $pareceComprobante,
            'parece_falla' => $pareceFalla,
            'parece_wifi' => $pareceWifi,
            'parece_impresora' => $pareceImpresora,
            'texto' => $texto,
            'paso' => $paso,
            'incidente' => $this->incidentes->hayAbierto($cuenta['router_id'] ?? null),
            'medicion' => $medicion,
        ]);

        $extra = [];
        if ($decision['tipo'] === 'wifi' && $servicio && $cuenta) {
            $cambio = $this->wifi->preparar(
                $servicio,
                $texto,
                ! $sandbox && (bool) config('whatsapp.agent.wifi_auto', false),
            );
            $decision['reply'] = $cambio['reply'];
            $extra['wifi'] = [
                'aplicado' => $cambio['aplicado'],
                'ssid' => $cambio['ssid'],
            ];
        }

        if ($decision['tipo'] === 'impresora' && $servicio && $cuenta) {
            if ($medicion === []) {
                $medicion = $this->recolector->medir($servicio, null);
            }
            $persistir = ! $sandbox;
            $impresora = $this->impresoras->localizar(
                $servicio,
                is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [],
                $persistir && (bool) config('whatsapp.agent.impresora_auto', false),
                (int) $cuenta['cliente_id'],
                $persistir,
            );
            $decision['reply'] = $impresora['reply'];
            $decision['escalate'] = false;
            $extra['impresora'] = $impresora;
        }

        $nombrePila = $cuenta['nombre_pila'] ?? $cuenta['nombre'] ?? null;
        $apellido = $cuenta['apellido'] ?? null;
        if ($decision['tipo'] === 'escalado') {
            $decision['reply'] = FraseCliente::escalado($cuenta['cedula'] ?? null, is_string($nombrePila) ? $nombrePila : null, is_string($apellido) ? $apellido : null);
        } elseif ($decision['tipo'] === 'zona') {
            $decision['reply'] = FraseCliente::zona(
                is_string($nombrePila) ? $nombrePila : null,
                is_string($apellido) ? $apellido : null,
            );
            $this->incidentes->registrarAviso($cuenta ?? [], (string) $mensaje->telefono, $sandbox);
        } elseif ($decision['tipo'] === 'comprobante') {
            $decision['reply'] = FraseCliente::comprobante(
                is_string($nombrePila) ? $nombrePila : null,
                is_string($apellido) ? $apellido : null,
                WhatsAppAgentService::horarioLaboral(),
            );
        } elseif ($decision['tipo'] === 'cobranza') {
            $decision['reply'] = FraseCliente::cobranza(
                (float) ($cuenta['saldo'] ?? 0),
                is_string($nombrePila) ? $nombrePila : null,
                is_string($apellido) ? $apellido : null,
            );
        } elseif ($decision['tipo'] === 'resuelto' && ($decision['etiqueta'] ?? '') === 'resuelto') {
            $decision['reply'] = FraseCliente::resuelto(
                is_string($nombrePila) ? $nombrePila : null,
                is_string($apellido) ? $apellido : null,
            );
        }

        $reply = FraseCliente::filtrar(
            (string) $decision['reply'],
            $this->fallback($decision['tipo'], $cuenta['cedula'] ?? null, is_string($nombrePila) ? $nombrePila : null, is_string($apellido) ? $apellido : null)
        );
        $caso = $this->casos->para($decision['etiqueta'] ?? null, $texto, 1);
        if ($caso !== [] && ! in_array($decision['tipo'], ['wifi', 'escalado', 'zona', 'comprobante'], true)) {
            $anexo = trim((string) $caso[0]['texto']);
            if ($anexo !== '' && ! str_contains($reply, $anexo)) {
                $reply = rtrim($reply)."\n\n".$anexo;
            }
        }

        $bloquearOferta = $decision['escalate']
            || in_array($decision['tipo'], ['cobranza', 'pregunta', 'accion', 'zona', 'wifi', 'escalado', 'comprobante'], true)
            || $paso !== '' && $decision['paso'] !== null;
        if ($decision['tipo'] === 'resuelto') {
            $oferta = $this->ofertas->sugerir($cuenta, false);
            if ($oferta) {
                $reply = rtrim($reply)."\n\n".$oferta;
            }
        } elseif ($bloquearOferta) {
            // sin oferta durante la falla
        }

        $guardarEn = array_merge($clavesDialogo, [
            (string) ($cuenta['whatsapp'] ?? ''),
        ]);
        if ($decision['paso']) {
            $estado = [
                'paso' => $decision['paso'],
                'medicion' => $this->medicionSinClaves($medicion),
                'cliente_id' => $cuenta['cliente_id'] ?? null,
            ];
            foreach (array_unique(array_filter($guardarEn)) as $clave) {
                $this->dialogo->guardar($clave, $estado);
            }
        } else {
            foreach (array_unique(array_filter($guardarEn)) as $clave) {
                $this->dialogo->olvidar($clave);
            }
        }

        $resumen = $this->resumen($cuenta, $medicion, $decision, $extra);
        $diagnostico = [
            'tipo' => $decision['tipo'],
            'motivo' => $decision['motivo'],
            'medicion' => $medicion,
            'extra' => $extra,
            'vigilar_puerto' => $decision['tipo'] === 'accion' && ($decision['etiqueta'] ?? '') === 'cable',
            'servicio_id' => $cuenta['servicio_id'] ?? null,
            'cliente_id' => $cuenta['cliente_id'] ?? null,
            'asignado_id' => SoporteAdminConfig::usuarioId(),
            'roaming' => ($decision['etiqueta'] ?? '') === 'senal' ? 'pedir_acercarse' : null,
            'canal' => 'sin_cambio',
        ];

        return $this->paquete(
            $reply,
            (bool) $decision['escalate'],
            $decision['motivo'],
            $cuenta['cliente_id'] ?? null,
            'soporte_'.$decision['tipo'],
            $resumen,
            $diagnostico,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function contextoParaN8n(WhatsappMensaje $mensaje): array
    {
        $cuenta = $this->cuentas->buscar($this->telefonoCuenta($mensaje));
        $casos = $this->casos->para(null, (string) $mensaje->cuerpo, 3);

        $estilo = app(EstiloInterplus::class)->texto();

        return [
            'estado_servicio' => $cuenta['estado'] ?? null,
            'saldo_pendiente' => $cuenta['saldo'] ?? null,
            'dias_mora' => $cuenta['dias_mora'] ?? null,
            'contexto_cuenta' => $this->textoCuenta($cuenta),
            'trato' => $cuenta['trato'] ?? null,
            'vocativo' => $cuenta['vocativo'] ?? null,
            'conocimiento' => $casos,
            'estilo_interplus' => $estilo,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cuentaDe(WhatsappMensaje $mensaje): ?array
    {
        return $this->cuentas->buscar($this->telefonoCuenta($mensaje));
    }

    /**
     * Respuesta fija de comprobante (formal). Evita el texto informal de n8n.
     *
     * @return array<string, mixed>
     */
    public function paqueteComprobante(WhatsappMensaje $mensaje): array
    {
        $cuenta = $this->cuentaDe($mensaje);
        $nombrePila = is_string($cuenta['nombre_pila'] ?? null) ? $cuenta['nombre_pila'] : (is_string($cuenta['nombre'] ?? null) ? $cuenta['nombre'] : null);
        $apellido = is_string($cuenta['apellido'] ?? null) ? $cuenta['apellido'] : null;
        $reply = FraseCliente::comprobante(
            $nombrePila,
            $apellido,
            WhatsAppAgentService::horarioLaboral(),
        );

        return $this->paquete(
            $reply,
            true,
            'comprobante',
            isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
            'soporte_comprobante',
            'Comprobante recibido. Pendiente de registro por asesor.',
            [
                'tipo' => 'comprobante',
                'motivo' => 'comprobante',
                'cliente_id' => $cuenta['cliente_id'] ?? null,
                'asignado_id' => SoporteAdminConfig::usuarioId(),
            ],
        );
    }

    public function telefonoCuenta(WhatsappMensaje $mensaje): string
    {
        $guardado = data_get($mensaje->payload, 'telefono_cuenta');
        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }

        return (string) $mensaje->telefono;
    }

    /**
     * @param  array<string, mixed>|null  $cuenta
     */
    private function textoCuenta(?array $cuenta): string
    {
        if ($cuenta === null) {
            return 'Sin cliente identificado.';
        }

        return sprintf(
            'Estado del servicio: %s. Saldo pendiente: %s. Días de mora: %d.',
            (string) ($cuenta['estado'] ?? 'desconocido'),
            number_format((float) ($cuenta['saldo'] ?? 0), 0, ',', '.'),
            (int) ($cuenta['dias_mora'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>|null  $cuenta
     * @param  array<string, mixed>  $medicion
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $extra
     */
    private function resumen(?array $cuenta, array $medicion, array $decision, array $extra): string
    {
        $lineas = [
            'Soporte WhatsApp',
            'Cliente: '.($cuenta['nombre'] ?? 'sin identificar'),
            'Estado de cuenta: '.($cuenta['estado'] ?? '-'),
            'Resultado: '.$decision['tipo'].($decision['motivo'] ? ' ('.$decision['motivo'].')' : ''),
        ];
        if (array_key_exists('sesion_online', $medicion)) {
            $lineas[] = 'Sesión: '.var_export($medicion['sesion_online'], true);
        }
        if (array_key_exists('ping_general_ok', $medicion)) {
            $lineas[] = 'Ping 1.1.1.1: '.var_export($medicion['ping_general_ok'], true)
                .' '.($medicion['ping_general_ms'] ?? '-').' ms';
        }
        if (! empty($medicion['sitio'])) {
            $lineas[] = 'Sitio '.$medicion['sitio'].': '.var_export($medicion['sitio_ok'] ?? null, true)
                .' '.($medicion['sitio_ms'] ?? '-').' ms';
        }
        if (isset($medicion['senal_antena'])) {
            $lineas[] = 'Señal antena: '.($medicion['senal_antena'] ?? '-').' dBm, delta cadenas: '.($medicion['chain_delta'] ?? '-');
        }
        if (isset($medicion['lan_speed']) || isset($medicion['lan_conectado'])) {
            $lineas[] = 'Puerto LAN: '.($medicion['lan_speed'] ?? '-').' conectado='.var_export($medicion['lan_conectado'] ?? null, true);
        }
        $equipos = is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [];
        foreach ($equipos as $equipo) {
            $lineas[] = 'Equipo '.($equipo['nombre'] ?? '-').' rssi='.($equipo['rssi'] ?? '-');
        }
        if (isset($extra['impresora']['ip'])) {
            $lineas[] = 'Impresora IP '.$extra['impresora']['ip'].' lease_fijo='.var_export($extra['impresora']['lease_fijo'] ?? false, true);
            if (! empty($extra['impresora']['reserva_id'])) {
                $lineas[] = 'Archivo: /whatsapp/soporte/impresoras/'.$extra['impresora']['reserva_id'].'/bat';
            }
        }
        if (($decision['etiqueta'] ?? '') === 'senal') {
            $lineas[] = 'Roaming: se pidió acercarse al router. No se desconectó ningún equipo.';
        }

        return implode("\n", $lineas);
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return array<string, mixed>
     */
    private function medicionSinClaves(array $medicion): array
    {
        return $medicion;
    }

    private function fallback(string $tipo, ?string $cedula = null, ?string $nombre = null, ?string $apellido = null): string
    {
        return match ($tipo) {
            'cobranza' => FraseCliente::cobranza(0, $nombre, $apellido),
            'pregunta' => FraseCliente::PREGUNTA_SINTOMA,
            'accion' => FraseCliente::CABLE,
            'resuelto' => FraseCliente::resuelto($nombre, $apellido),
            'zona' => FraseCliente::zona($nombre, $apellido),
            'comprobante' => FraseCliente::comprobante($nombre, $apellido),
            'escalado' => FraseCliente::escalado($cedula, $nombre, $apellido),
            default => FraseCliente::escalado($cedula, $nombre, $apellido),
        };
    }

    /**
     * @param  array<string, mixed>|null  $diagnostico
     * @return array<string, mixed>
     */
    private function paquete(
        string $reply,
        bool $escalate,
        ?string $motivo,
        ?int $clienteId,
        string $modo,
        ?string $resumen,
        ?array $diagnostico,
    ): array {
        return [
            'reply' => $reply,
            'escalate' => $escalate,
            'cliente_id' => $clienteId,
            'motivo_escalado' => $motivo,
            'n8n_latency_ms' => 0,
            'error' => null,
            'modo' => $modo,
            'resumen_admin' => $resumen,
            'diagnostico' => $diagnostico,
        ];
    }
}
