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

        if (SoporteTexto::pareceYaFunciona($texto) || ($paso !== '' && SoporteTexto::pareceCierre($texto))) {
            foreach ($clavesDialogo as $clave) {
                $this->dialogo->olvidar($clave);
            }

            return $this->paquete(
                FraseCliente::YA_FUNCIONA,
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_cierre',
                'El cliente indicó que ya funciona',
                null,
            );
        }

        // Cuenta o cuánto abonar: antes de la falla del hilo y del seguimiento de zona.
        $pago = $this->respuestaPago($cuenta, $texto);
        if ($pago !== null) {
            return $pago;
        }

        $rafaga = app(WhatsAppAgentService::class)->rafagaClienteReciente(
            (string) $mensaje->telefono,
            $mensaje->id ? (int) $mensaje->id : null,
            120,
        );
        $textoHilo = $rafaga['unido'] !== '' ? $rafaga['unido'] : $texto;
        $parecePago = WhatsAppAgentService::textoParecePago($texto);
        $tipoImagen = (string) data_get($mensaje->payload, 'wa_vision.tipo', '');
        $pareceComprobante = WhatsAppAgentService::detectarComprobante(
            (string) ($mensaje->tipo ?: 'text'),
            (string) $mensaje->cuerpo,
            [],
            $tipoImagen !== '' ? $tipoImagen : null,
        );
        // Intenciones: solo el mensaje actual. El hilo unido dejaba el corte de zona tapando saldo, ping y WiFi.
        $ajena = SoporteTexto::preguntaAjenaAZona($texto) || $this->preguntaDeCuenta($texto);
        $fallaActual = SoporteTexto::pareceFalla($texto) && ! WhatsAppAgentService::textoPareceInstalacion($texto);
        $pareceFalla = $fallaActual
            || (! $ajena && SoporteTexto::pareceFalla($textoHilo) && ! WhatsAppAgentService::textoPareceInstalacion($textoHilo));
        $pareceWifi = SoporteTexto::pareceCambioWifi($texto);
        $pareceNombreWifi = SoporteTexto::pareceConsultaNombreWifi($texto);
        $pareceImpresora = SoporteTexto::pareceImpresora($texto);
        $pareceEquipos = SoporteTexto::pareceConsultaEquipos($texto);
        $pideVecinos = SoporteTexto::pideVecinosWifi($texto);
        $parecePing = SoporteTexto::pareceConsultaPing($texto);
        if ($pareceEquipos || $pideVecinos || $pareceNombreWifi) {
            $pareceFalla = true; // entra a soporte; no mandar a n8n a inventar
        }
        // Tras listar equipos, solo confirmación corta (no preguntas).
        if ($paso === 'wifi_sospecha' && SoporteTexto::pareceConfirmaCambioWifi($texto)) {
            $pareceWifi = true;
        } elseif ($paso === 'wifi_sospecha' && ! $pareceWifi && ! $pareceEquipos && ! $pideVecinos) {
            // Otra pregunta: salir del paso de sospecha para no quedar en bucle.
            foreach (array_unique(array_filter($clavesDialogo)) as $clave) {
                $this->dialogo->olvidar($clave);
            }
            $paso = '';
        }
        $identificando = $paso === 'identificar';
        $soloIdentificador = SoporteTexto::esSoloIdentificador($texto);
        if ($cuenta && ($identificando || ($soloIdentificador && $pareceFalla === false && $paso !== ''))) {
            $pareceFalla = true;
        }
        if ($cuenta && $soloIdentificador && $identificando) {
            $pareceFalla = true;
        }
        $suspendido = $cuenta && in_array($cuenta['estado'] ?? '', ['suspendido', 'cortado'], true);
        $zonaAbierta = ($cuenta && $this->incidentes->hayAbierto($cuenta['router_id'] ?? null))
            || $this->incidentes->telefonoConAvisoAbierto((string) $mensaje->telefono)
            || $this->incidentes->telefonoConAvisoAbierto($telefono);
        $seguimientoZona = SoporteTexto::esSeguimientoZona($zonaAbierta, $texto) && ! $this->preguntaDeCuenta($texto);

        // Saldo, plan, pago u otra consulta de cuenta: la responde el canned, aunque haya zona abierta.
        $accionDeRed = $pareceWifi || $pareceNombreWifi || $pareceImpresora || $pareceEquipos || $pideVecinos || $parecePing
            || SoporteTexto::pareceTelevision($texto) || SoporteTexto::pareceLuz($texto);
        if ($ajena && ! $accionDeRed && ! $pareceComprobante) {
            foreach (array_unique(array_filter($clavesDialogo)) as $clave) {
                $this->dialogo->olvidar($clave);
            }

            return null;
        }

        // Ya hay corte de zona: no mandar a n8n a reiniciar el router ni a saludar de nuevo.
        if ($seguimientoZona && $cuenta) {
            $nombrePila = is_string($cuenta['nombre_pila'] ?? null) ? $cuenta['nombre_pila'] : (is_string($cuenta['nombre'] ?? null) ? $cuenta['nombre'] : null);
            $apellido = is_string($cuenta['apellido'] ?? null) ? $cuenta['apellido'] : null;
            $this->incidentes->registrarAviso($cuenta, (string) ($cuenta['whatsapp'] ?? $mensaje->telefono), $sandbox);

            return $this->paquete(
                FraseCliente::zonaSeguimiento($nombrePila, $apellido),
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_zona',
                'Seguimiento de incidente de zona abierto',
                null,
            );
        }

        if (SoporteTexto::pareceTelevision($texto)) {
            foreach ($clavesDialogo as $clave) {
                $this->dialogo->olvidar($clave);
            }

            return $this->paquete(
                FraseCliente::TV,
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_tv',
                'Reclamo de televisor: HDMI, sin repetir el cable del router',
                null,
            );
        }

        if (SoporteTexto::pareceLuz($texto)) {
            foreach ($clavesDialogo as $clave) {
                $this->dialogo->olvidar($clave);
            }

            return $this->paquete(
                FraseCliente::LUZ,
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_luz',
                'Sin energía: no se pide el cable del router',
                null,
            );
        }

        $entra = $paso !== ''
            || $pareceFalla
            || $pareceWifi
            || $pareceNombreWifi
            || $pareceImpresora
            || $pareceEquipos
            || $pareceComprobante
            || ($cuenta && $soloIdentificador && $identificando)
            || ($suspendido && ! $parecePago && ! $pareceComprobante);

        if (! $entra) {
            return null;
        }

        if (! $cuenta && ($pareceFalla || $identificando || $pareceImpresora || $pareceWifi || $pareceNombreWifi || $pareceEquipos)) {
            $this->dialogo->guardar($claveDialogo, [
                'paso' => 'identificar',
                'medicion' => [],
                'cliente_id' => null,
            ]);

            return $this->paquete(FraseCliente::SIN_CUENTA, false, null, null, 'sin_cuenta', null, null);
        }

        $medicion = is_array($estadoDialogo['medicion'] ?? null) ? $estadoDialogo['medicion'] : [];
        // Medir en el primer reclamo de falla (y en pasos de diálogo), no solo cable/sitio.
        $necesitaMedir = $pareceFalla
            || $pareceImpresora
            || $pareceEquipos
            || $parecePing
            || $paso === 'cable'
            || $paso === 'equipo'
            || ($paso === 'sitio' && SoporteTexto::sitio($texto));
        $servicio = $cuenta ? $this->cuentas->servicioDe($cuenta['servicio_id'] ?? null) : null;

        if ($servicio && $necesitaMedir && ! $suspendido && ! $pareceWifi) {
            $sitio = $paso === 'sitio' ? SoporteTexto::sitio($texto) : null;
            $medicion = $this->recolector->medir($servicio, $sitio);
        }

        if ($pareceFalla && $servicio && $cuenta && $this->enlaceCaido($medicion)) {
            $this->incidentes->anotarServicioCaido(
                (int) $servicio->servicio_id,
                $cuenta,
                (string) ($cuenta['whatsapp'] ?? $mensaje->telefono),
                $sandbox || WhatsAppAgentService::esTelefonoTest((string) $mensaje->telefono),
            );
        }

        // Consulta del nombre WiFi actual (no es cambio de clave).
        if ($pareceNombreWifi && $servicio && ! $suspendido && ! $pareceWifi) {
            $ssids = $this->wifi->ssidsActuales($servicio);
            $sinAcceso = $ssids === [] ? InformeAccesoCpe::texto((int) $servicio->id) : '';
            if ($sinAcceso !== '') {
                $out = $this->paquete(
                    FraseCliente::escalado(
                        $cuenta['cedula'] ?? null,
                        is_string($cuenta['nombre_pila'] ?? null) ? $cuenta['nombre_pila'] : (is_string($cuenta['nombre'] ?? null) ? $cuenta['nombre'] : null),
                        is_string($cuenta['apellido'] ?? null) ? $cuenta['apellido'] : null,
                    ),
                    true,
                    'sin_acceso_cpe',
                    isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                    'soporte_escalado',
                    "Sin datos de WiFi. Equipos sin acceso:\n".$sinAcceso,
                    [
                        'tipo' => 'escalado',
                        'motivo' => 'sin_acceso_cpe',
                        'medicion' => $medicion,
                        'servicio_id' => $cuenta['servicio_id'] ?? null,
                        'cliente_id' => $cuenta['cliente_id'] ?? null,
                        'asignado_id' => SoporteAdminConfig::usuarioId(),
                    ],
                );
                $out['abrir_ticket'] = true;

                return $out;
            }
            $replySsid = FraseCliente::nombreWifiActual($ssids);
            $resumenSsid = $this->resumen($cuenta, $medicion, [
                'tipo' => 'wifi_nombre',
                'motivo' => 'consulta_ssid',
                'etiqueta' => 'wifi_nombre',
            ], ['ssids' => $ssids]);

            return $this->paquete(
                $replySsid,
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_wifi_nombre',
                $resumenSsid,
                [
                    'tipo' => 'wifi_nombre',
                    'motivo' => 'consulta_ssid',
                    'medicion' => $medicion,
                    'servicio_id' => $cuenta['servicio_id'] ?? null,
                    'cliente_id' => $cuenta['cliente_id'] ?? null,
                    'asignado_id' => SoporteAdminConfig::usuarioId(),
                ],
            );
        }

        if ($parecePing && $cuenta && ! $suspendido && ! $pareceWifi && ! $pareceEquipos && ! $pideVecinos) {
            if ($servicio && $medicion === []) {
                try {
                    $medicion = $this->recolector->medir($servicio, null);
                } catch (\Throwable) {
                    $medicion = [];
                }
            }

            return $this->paquete(
                FraseCliente::pingVisto($medicion),
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_ping',
                'Ping pedido por el cliente, sin repetir el corte de zona',
                [
                    'tipo' => 'ping',
                    'motivo' => 'consulta_ping',
                    'medicion' => $medicion,
                    'servicio_id' => $cuenta['servicio_id'] ?? null,
                    'cliente_id' => $cuenta['cliente_id'] ?? null,
                    'asignado_id' => SoporteAdminConfig::usuarioId(),
                ],
            );
        }

        // Listar equipos reales (ACS/DHCP). No ofrece cambio de clave si no lo pidió.
        if (($pareceEquipos || $pideVecinos) && $servicio && ! $suspendido && ! $pareceWifi) {
            if ($medicion === []) {
                $medicion = $this->recolector->medir($servicio, null);
            }
            $equipos = is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [];
            $nombres = [];
            foreach ($equipos as $equipo) {
                if (! is_array($equipo)) {
                    continue;
                }
                $n = trim((string) ($equipo['nombre'] ?? ''));
                if ($n !== '') {
                    $nombres[] = $n;
                }
            }
            $sinAccesoEquipos = ($nombres === [] && ($medicion['pudo_medir'] ?? false) !== true)
                ? InformeAccesoCpe::texto((int) $servicio->id)
                : '';
            if ($sinAccesoEquipos !== '') {
                $out = $this->paquete(
                    FraseCliente::redEnRevision(),
                    true,
                    'sin_acceso_cpe',
                    isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                    'soporte_escalado',
                    "Sin equipos medidos. Equipos sin acceso:\n".$sinAccesoEquipos,
                    [
                        'tipo' => 'escalado',
                        'motivo' => 'sin_acceso_cpe',
                        'medicion' => $medicion,
                        'servicio_id' => $cuenta['servicio_id'] ?? null,
                        'cliente_id' => $cuenta['cliente_id'] ?? null,
                        'asignado_id' => SoporteAdminConfig::usuarioId(),
                    ],
                );
                $out['abrir_ticket'] = true;

                return $out;
            }
            $replyEquipos = FraseCliente::listaEquiposVista($nombres);
            foreach (array_unique(array_filter($clavesDialogo)) as $clave) {
                $this->dialogo->guardar($clave, [
                    'paso' => 'wifi_sospecha',
                    'medicion' => $this->medicionSinClaves($medicion),
                    'cliente_id' => $cuenta['cliente_id'] ?? null,
                ]);
            }
            $resumenEquipos = $this->resumen($cuenta, $medicion, [
                'tipo' => 'equipos',
                'motivo' => 'consulta_equipos',
                'etiqueta' => 'equipos',
            ], []);

            return $this->paquete(
                $replyEquipos,
                false,
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'soporte_equipos',
                $resumenEquipos,
                [
                    'tipo' => 'equipos',
                    'motivo' => 'consulta_equipos',
                    'medicion' => $medicion,
                    'servicio_id' => $cuenta['servicio_id'] ?? null,
                    'cliente_id' => $cuenta['cliente_id'] ?? null,
                    'asignado_id' => SoporteAdminConfig::usuarioId(),
                ],
            );
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
            'pregunta_ajena' => $ajena,
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
        $casoIdUsado = null;
        $caso = $this->casos->para($decision['etiqueta'] ?? null, $texto, 1);
        // No anexar plantillas en medio del diagnóstico (pregunta/acción): el estilo/casos
        // no deben filtrarse al cliente. Solo casos reales con caso_id en cierres útiles.
        $noAnexar = ['wifi', 'escalado', 'zona', 'comprobante', 'pregunta', 'accion', 'impresora', 'cobranza'];
        if ($caso !== [] && ! in_array($decision['tipo'], $noAnexar, true)) {
            $candidato = $caso[0];
            $anexo = trim((string) ($candidato['texto'] ?? ''));
            $casoId = isset($candidato['caso_id']) ? (int) $candidato['caso_id'] : 0;
            $pareceEstilo = FraseCliente::pareceFugaLineamiento($anexo)
                || str_contains(mb_strtolower($anexo), 'estilo interplus')
                || str_contains(mb_strtolower($anexo), 'obligatorio')
                || str_contains(mb_strtolower($anexo), 'use esto solo');
            if ($casoId > 0 && $anexo !== '' && ! $pareceEstilo && ! str_contains($reply, $anexo)) {
                $reply = rtrim($reply)."\n\n".$anexo;
                $casoIdUsado = $casoId;
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

        $out = $this->paquete(
            $reply,
            (bool) $decision['escalate'],
            $decision['motivo'],
            $cuenta['cliente_id'] ?? null,
            'soporte_'.$decision['tipo'],
            $resumen,
            $diagnostico,
        );
        if ($casoIdUsado) {
            $out['caso_id'] = $casoIdUsado;
        }
        if (($decision['motivo'] ?? '') === 'sin_medicion') {
            $out['abrir_ticket'] = true;
            $out['escalate'] = true;
            $informe = InformeAccesoCpe::texto((int) ($cuenta['servicio_id'] ?? 0));
            if ($informe !== '') {
                $out['resumen_admin'] = rtrim((string) $out['resumen_admin'])."\n\nEquipos sin acceso:\n".$informe;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function contextoParaN8n(WhatsappMensaje $mensaje): array
    {
        $cuenta = $this->cuentas->buscar($this->telefonoCuenta($mensaje));
        $casos = $this->casos->para(null, (string) $mensaje->cuerpo, 3);

        $estilo = app(EstiloInterplus::class)->texto();

        $zonaAbierta = ($cuenta && $this->incidentes->hayAbierto($cuenta['router_id'] ?? null))
            || $this->incidentes->telefonoConAvisoAbierto((string) $mensaje->telefono);

        $hechos = $this->hechosParaN8n($mensaje, $cuenta, $zonaAbierta);

        return [
            'estado_servicio' => $cuenta['estado'] ?? null,
            'saldo_pendiente' => $cuenta['saldo'] ?? null,
            'dias_mora' => $cuenta['dias_mora'] ?? null,
            'plan_nombre' => $cuenta['plan_nombre'] ?? null,
            'plan_precio' => $cuenta['plan_precio'] ?? null,
            'cedula' => $cuenta['cedula'] ?? null,
            'es_antena' => (bool) ($cuenta['es_antena'] ?? false),
            'ticket_abierto' => (bool) ($cuenta['ticket_abierto'] ?? false),
            'contexto_cuenta' => $this->textoCuenta($cuenta),
            'trato' => $cuenta['trato'] ?? null,
            'vocativo' => $cuenta['vocativo'] ?? null,
            'conocimiento' => $casos,
            'estilo_interplus' => $estilo,
            'incidente_zona_abierto' => $zonaAbierta,
            'hechos' => $hechos,
            'instruccion_hilo' => 'No salude: el sistema agrega una sola vez “Muy buenos días”, “Muy buenas tardes” o “Muy buenas noches”, con Sr. o Sra. y el apellido si el trato viene cargado. No use el nombre de pila. No diga Señora si el trato es Sr. Si el trato está vacío, no invente Sr. ni Sra. No pida cédula ni la reformule. No invente clave WiFi. Si el cliente solo dice gracias, cierre sin ofrecer planes. No pida reinicio si el historial ya muestra un reclamo en curso. Analice el caso completo, no solo la última línea.',
            'instruccion_hechos' => 'Solo afirme lo que esté en hechos / planes_prompt / textos canned. Si piden cómo pagar o el alias, copie texto_transferencia tal cual y no pida el comprobante antes. No mencione ueno ni invente cuentas. No prometa la contraseña actual del WiFi: no la vemos. Si falta un dato, pregunte o escale. No invente reinicio, cable, canal, óptica, zona, clave WiFi ni precios. No diga mediciones (dBm, IP, ping) al cliente. Nunca copie instrucciones internas (Venta suave, Salida WhatsApp, prioridad N) al reply.',
        ];
    }

    /**
     * Respuesta canned canónica (servicio / planes / beneficios / transferencia / condiciones) sin LLM.
     *
     * @return array<string, mixed>|null
     */
    public function intentarCanned(WhatsappMensaje $mensaje): ?array
    {
        // Intenciones canned: solo el mensaje actual (la ráfaga unida repetía beneficios/planes).
        $texto = trim((string) $mensaje->cuerpo);
        $canned = WhatsAppAgentService::textosCanned();
        $cuenta = $this->cuentas->buscar($this->telefonoCuenta($mensaje));
        if (! $cuenta) {
            $cuenta = $this->cuentas->buscarEnTexto($texto);
        }
        $clienteId = isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null;

        $pago = $this->respuestaPago($cuenta, $texto);
        if ($pago !== null) {
            return $pago;
        }

        if (SoporteTexto::parecePideClaveWifiActual($texto)) {
            return $this->paquete(
                FraseCliente::wifiNoVemosClaveActual(),
                false,
                null,
                $clienteId,
                'canned_wifi_clave',
                'No vemos la clave actual del WiFi',
                null,
            );
        }

        if (WhatsAppAgentService::textoParecePlazoPago($texto) && ! WhatsAppAgentService::textoPareceDatosTransferencia($texto)) {
            return $this->paquete(
                FraseCliente::PLAZO_PAGO,
                false,
                null,
                $clienteId,
                'canned_plazo_pago',
                'Plazo de pago, sin datos de cuenta',
                null,
            );
        }

        if (SoporteTexto::pareceCierre($texto)) {
            return $this->paquete(
                FraseCliente::AGRADECIMIENTO,
                false,
                null,
                $clienteId,
                'canned_cierre',
                'Cierre corto, sin venta',
                null,
            );
        }

        if (SoporteTexto::pareceConsultaTitular($texto)) {
            if (! $cuenta) {
                return $this->paquete(FraseCliente::SIN_CUENTA, false, null, null, 'sin_cuenta', null, null);
            }

            return $this->paquete(
                FraseCliente::titularServicio($cuenta),
                false,
                null,
                $clienteId,
                'canned_titular',
                'Titular desde cuenta (sin n8n)',
                null,
            );
        }

        if (SoporteTexto::pareceEstadoServicio($texto)) {
            if (! $cuenta) {
                return $this->paquete(FraseCliente::SIN_CUENTA, false, null, null, 'sin_cuenta', null, null);
            }
            $servicio = $this->cuentas->servicioDe(isset($cuenta['servicio_id']) ? (int) $cuenta['servicio_id'] : null);
            $reply = FraseCliente::REVISANDO_SERVICIO;
            if ($servicio) {
                try {
                    $medicion = $this->recolector->medir($servicio, null);
                } catch (\Throwable) {
                    $medicion = [];
                }
                $causas = is_array($medicion['causas'] ?? null) ? $medicion['causas'] : [];
                if (($medicion['pudo_medir'] ?? false) === true && ($medicion['sesion_online'] ?? null) === true && $causas === []) {
                    $reply = FraseCliente::SERVICIO_EN_LINEA;
                } elseif (($medicion['sesion_online'] ?? null) === false) {
                    $reply = FraseCliente::SERVICIO_SIN_SESION;
                }
            }

            return $this->paquete(
                $reply,
                false,
                null,
                $clienteId,
                'canned_estado_servicio',
                'Estado del servicio mirado desde la red, sin decir que no se puede',
                null,
            );
        }

        if (SoporteTexto::pareceConsultaServicio($texto)) {
            if (! $cuenta) {
                return $this->paquete(FraseCliente::SIN_CUENTA, false, null, null, 'sin_cuenta', null, null);
            }
            $reply = FraseCliente::filtrar(FraseCliente::detalleServicio($cuenta), FraseCliente::ESCALADO);

            return $this->paquete(
                $reply,
                false,
                null,
                $clienteId,
                'canned_servicio',
                'Detalle de servicio desde cuenta (sin n8n)',
                null,
            );
        }

        $rafaga = app(WhatsAppAgentService::class)->rafagaClienteReciente((string) $mensaje->telefono, $mensaje->id);
        $pedido = trim($rafaga['unido'] !== '' ? $rafaga['unido'] : $texto);

        if (WhatsAppAgentService::textoPareceBajaServicio($pedido)) {
            return $this->paquete(
                WhatsAppAgentService::fraseBaja(),
                false,
                null,
                $clienteId,
                'canned_baja',
                'Baja: queda para un asesor (sin n8n)',
                null,
            );
        }

        if (WhatsAppAgentService::textoPareceCambioPlan($pedido)) {
            $catalogo = app(WhatsAppAgentService::class)->catalogoPlanes();
            $plan = WhatsAppAgentService::planPedido(
                $pedido,
                array_merge($catalogo['fibra'] ?? [], $catalogo['antena'] ?? [], $catalogo['otros'] ?? []),
            );

            return $this->paquete(
                WhatsAppAgentService::fraseCambioPlan($plan),
                false,
                null,
                $clienteId,
                'canned_cambio_plan',
                'Cambio de plan cotizado, sin aplicar (sin n8n)',
                null,
            );
        }

        if (SoporteTexto::pareceConsultaCatalogo($texto)) {
            $catalogo = app(WhatsAppAgentService::class)->catalogoPlanes();
            $filtro = WhatsAppAgentService::filtroCatalogoDesdeTexto($texto);
            $reply = WhatsAppAgentService::planesParaCliente(
                $catalogo['fibra'] ?? [],
                $catalogo['antena'] ?? [],
                $catalogo['otros'] ?? [],
                $filtro,
            );
            $reply = FraseCliente::filtrar($reply, $reply);

            return $this->paquete(
                $reply,
                false,
                null,
                $clienteId,
                'canned_catalogo',
                'Catálogo de planes (sin n8n)',
                null,
            );
        }

        $pideUbicacion = WhatsAppAgentService::detectarUbicacion((string) ($mensaje->tipo ?: 'text'), $texto);
        if (WhatsAppAgentService::textoPareceInstalacion($texto) || (WhatsAppAgentService::textoPareceInstalacion($pedido) && $pideUbicacion)) {
            $ya = $pideUbicacion || app(WhatsAppAgentService::class)->yaEnvioUbicacion((string) $mensaje->telefono);

            return $this->paquete(
                WhatsAppAgentService::fraseInstalacion($ya),
                false,
                null,
                $clienteId,
                'canned_instalacion',
                $ya ? 'Instalación: ubicación ya recibida' : 'Instalación: se pidió ubicación',
                null,
            );
        }

        if (WhatsAppAgentService::textoPareceConsultaCobertura($texto) || (WhatsAppAgentService::textoPareceConsultaCobertura($pedido) && $pideUbicacion)) {
            $ya = $pideUbicacion || app(WhatsAppAgentService::class)->yaEnvioUbicacion((string) $mensaje->telefono);

            return $this->paquete(
                WhatsAppAgentService::fraseCobertura($ya),
                false,
                null,
                $clienteId,
                'canned_cobertura',
                $ya ? 'Cobertura: ubicación ya recibida' : 'Cobertura: se pidió ubicación',
                null,
            );
        }

        if (WhatsAppAgentService::textoUenoNoDisponible($texto)) {
            return $this->paquete(
                FraseCliente::filtrar((string) $canned['transferencia'], (string) $canned['transferencia']),
                false,
                null,
                $clienteId,
                'canned_transferencia',
                'Ueno no se usa: cuentas vigentes',
                null,
            );
        }

        if (WhatsAppAgentService::textoPideTigoMoney($texto)) {
            return $this->paquete(
                FraseCliente::filtrar(WhatsAppAgentService::frasePagoTigo(), WhatsAppAgentService::frasePagoTigo()),
                false,
                null,
                $clienteId,
                'canned_tigo',
                'Pago por Tigo Money, sin pedir el comprobante antes',
                null,
            );
        }

        if (WhatsAppAgentService::textoPareceDatosTransferencia($texto)) {
            return $this->paquete(
                FraseCliente::filtrar((string) $canned['transferencia'], (string) $canned['transferencia']),
                false,
                null,
                $clienteId,
                'canned_transferencia',
                'Texto canónico de transferencia (sin n8n)',
                null,
            );
        }
        if (WhatsAppAgentService::textoPareceBeneficiosPlanes($texto)) {
            return $this->paquete(
                FraseCliente::filtrar((string) $canned['beneficios'], (string) $canned['beneficios']),
                false,
                null,
                $clienteId,
                'canned_beneficios',
                'Texto canónico de beneficios (sin n8n)',
                null,
            );
        }
        if (WhatsAppAgentService::textoPareceCondicionesServicio($texto)) {
            return $this->paquete(
                FraseCliente::filtrar((string) $canned['condiciones'], (string) $canned['condiciones']),
                false,
                null,
                $clienteId,
                'canned_condiciones',
                'Texto canónico de condiciones (sin n8n)',
                null,
            );
        }

        return null;
    }

    /** Consulta de cuenta o catálogo. No es el corte de zona. */
    private function preguntaDeCuenta(string $texto): bool
    {
        return WhatsAppAgentService::textoParecePago($texto)
            || WhatsAppAgentService::textoPareceDatosTransferencia($texto)
            || WhatsAppAgentService::textoPareceBeneficiosPlanes($texto)
            || WhatsAppAgentService::textoPareceCondicionesServicio($texto)
            || WhatsAppAgentService::textoParecePlazoPago($texto)
            || WhatsAppAgentService::textoPareceBajaServicio($texto)
            || WhatsAppAgentService::textoPareceCambioPlan($texto)
            || WhatsAppAgentService::textoPareceInstalacion($texto)
            || WhatsAppAgentService::textoPareceConsultaCobertura($texto)
            || WhatsAppAgentService::textoPideTigoMoney($texto)
            || WhatsAppAgentService::textoUenoNoDisponible($texto);
    }

    /**
     * @param  array<string, mixed>|null  $cuenta
     * @return array<string, mixed>
     */
    private function hechosParaN8n(WhatsappMensaje $mensaje, ?array $cuenta, bool $zonaAbierta): array
    {
        $clave = $this->telefonoCuenta($mensaje);
        $dialogo = $this->dialogo->leer($clave);
        if ($dialogo === [] && (string) $mensaje->telefono !== $clave) {
            $dialogo = $this->dialogo->leer((string) $mensaje->telefono);
        }
        $medicion = is_array($dialogo['medicion'] ?? null) ? $dialogo['medicion'] : [];

        // Si hay cuenta y el texto parece falla, medir ahora para no mandar a n8n a ciegas.
        $texto = trim((string) $mensaje->cuerpo);
        $pareceFalla = SoporteTexto::pareceFalla($texto) && ! WhatsAppAgentService::textoPareceInstalacion($texto);
        $servicio = $cuenta ? $this->cuentas->servicioDe($cuenta['servicio_id'] ?? null) : null;
        $suspendido = $cuenta && in_array($cuenta['estado'] ?? '', ['suspendido', 'cortado'], true);
        if ($servicio && $pareceFalla && ! $suspendido && $medicion === []) {
            try {
                $medicion = $this->recolector->medir($servicio, null);
            } catch (\Throwable) {
                $medicion = [];
            }
        }

        $nombresEquipos = [];
        foreach (is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [] as $equipo) {
            if (! is_array($equipo)) {
                continue;
            }
            $n = trim((string) ($equipo['nombre'] ?? ''));
            if ($n !== '') {
                $nombresEquipos[] = $n;
            }
        }

        return [
            'pudo_medir' => (bool) ($medicion['pudo_medir'] ?? false),
            'causas' => is_array($medicion['causas'] ?? null) ? $medicion['causas'] : [],
            'incidente_zona_abierto' => $zonaAbierta,
            'sesion_online' => $medicion['sesion_online'] ?? null,
            'ping_cpe_ok' => $medicion['ping_cpe_ok'] ?? null,
            'ping_general_ok' => $medicion['ping_general_ok'] ?? null,
            'optica_ok' => $medicion['optica_ok'] ?? null,
            'lan_conectado' => $medicion['lan_conectado'] ?? null,
            'es_fibra' => $medicion['es_fibra'] ?? null,
            'es_antena' => $medicion['es_antena'] ?? ($cuenta['es_antena'] ?? null),
            'equipos_count' => count($nombresEquipos),
            'equipos_nombres' => array_slice($nombresEquipos, 0, 20),
            'puede_listar_equipos' => true,
            'puede_listar_vecinos_wifi' => false,
            'diagnostico_local' => $medicion !== [] ? [
                'tiene_medicion' => true,
                'causas' => $medicion['causas'] ?? [],
            ] : null,
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

    /**
     * @param  array<string, mixed>  $medicion
     */
    private function enlaceCaido(array $medicion): bool
    {
        if ($medicion === [] || ! ($medicion['pudo_medir'] ?? false)) {
            return false;
        }
        if (array_key_exists('sesion_online', $medicion) && $medicion['sesion_online'] === false) {
            return true;
        }
        if (array_key_exists('ping_cpe_ok', $medicion) && $medicion['ping_cpe_ok'] === false) {
            return true;
        }
        if (array_key_exists('optica_ok', $medicion) && $medicion['optica_ok'] === false) {
            return true;
        }

        return false;
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

        $partes = [
            'Estado del servicio: '.(string) ($cuenta['estado'] ?? 'desconocido'),
            'Saldo pendiente: '.number_format((float) ($cuenta['saldo'] ?? 0), 0, ',', '.'),
            'Días de mora: '.(int) ($cuenta['dias_mora'] ?? 0),
        ];
        if (! empty($cuenta['plan_nombre'])) {
            $partes[] = 'Plan actual: '.$cuenta['plan_nombre'];
            if (isset($cuenta['plan_precio']) && is_numeric($cuenta['plan_precio'])) {
                $partes[] = 'Precio plan: '.number_format((float) $cuenta['plan_precio'], 0, ',', '.').' Gs';
            }
        }
        if (! empty($cuenta['cedula'])) {
            $partes[] = 'Cédula: '.$cuenta['cedula'];
        }
        if (array_key_exists('es_antena', $cuenta)) {
            $partes[] = 'Tecnología: '.(($cuenta['es_antena'] ?? false) ? 'antena' : 'fibra/otros');
        }
        if (! empty($cuenta['ticket_abierto'])) {
            $partes[] = 'Tiene ticket abierto.';
        }

        return implode('. ', $partes).'.';
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
            $lineas[] = 'Ping 1.1.1.1 (POP): '.var_export($medicion['ping_general_ok'], true)
                .' '.($medicion['ping_general_ms'] ?? '-').' ms';
        }
        if (array_key_exists('ping_cpe_ok', $medicion)) {
            $lineas[] = 'Ping CPE: '.var_export($medicion['ping_cpe_ok'], true)
                .' '.($medicion['ping_cpe_ms'] ?? '-').' ms';
        }
        if (array_key_exists('optica_rx_dbm', $medicion) || array_key_exists('optica_ok', $medicion)) {
            $lineas[] = 'Óptica RX: '.($medicion['optica_rx_dbm'] ?? '-').' dBm ok='
                .var_export($medicion['optica_ok'] ?? null, true)
                .' fuente='.($medicion['optica_fuente'] ?? '-');
        }
        if (! empty($medicion['causas']) && is_array($medicion['causas'])) {
            $lineas[] = 'Causas: '.implode(',', $medicion['causas']);
        }
        if (! empty($medicion['sitio'])) {
            $lineas[] = 'Sitio '.$medicion['sitio'].': '.var_export($medicion['sitio_ok'] ?? null, true)
                .' '.($medicion['sitio_ms'] ?? '-').' ms';
        }
        foreach ((array) ($medicion['accesos_fallidos'] ?? []) as $falla) {
            $lineas[] = 'Sin acceso: '.$falla;
        }
        if (isset($medicion['senal_antena'])) {
            $lineas[] = 'Señal antena: '.($medicion['senal_antena'] ?? '-').' dBm, delta cadenas: '.($medicion['chain_delta'] ?? '-');
        }
        if (isset($medicion['lan_speed']) || isset($medicion['lan_conectado'])) {
            $lineas[] = 'Puerto LAN: '.($medicion['lan_speed'] ?? '-').' conectado='.var_export($medicion['lan_conectado'] ?? null, true);
        }
        if (isset($medicion['uptime']) || isset($medicion['bytes_out'])) {
            $lineas[] = 'Uptime: '.($medicion['uptime'] ?? '-').' bytes_out='.($medicion['bytes_out'] ?? '-');
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
     * Dónde depositar y/o cuánto abonar, con el saldo de la cuenta y el texto de transferencia.
     * Sale antes de que un hilo con falla o una zona abierta se queden con el mensaje.
     *
     * @param  array<string, mixed>|null  $cuenta
     * @return array<string, mixed>|null
     */
    private function respuestaPago(?array $cuenta, string $texto): ?array
    {
        $pideCuentas = WhatsAppAgentService::textoPareceDatosTransferencia($texto);
        $pideSaldo = SoporteTexto::pareceCuantoAbonar($texto);
        if (! $pideCuentas && ! $pideSaldo) {
            return null;
        }

        $transferencia = trim((string) (WhatsAppAgentService::textosCanned()['transferencia'] ?? ''));
        $clienteId = isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null;

        if ($pideCuentas && $pideSaldo) {
            $reply = FraseCliente::saldoYCuentas($cuenta, $transferencia);
            $modo = 'canned_transferencia';
            $resumen = 'Saldo de la cuenta y datos de transferencia';
        } elseif ($pideCuentas) {
            $reply = $transferencia;
            $modo = 'canned_transferencia';
            $resumen = 'Texto canónico de transferencia (sin n8n)';
        } elseif ($cuenta) {
            $reply = FraseCliente::saldoPendiente($cuenta);
            $modo = 'canned_servicio';
            $resumen = 'Saldo desde la cuenta (sin n8n)';
        } else {
            $reply = FraseCliente::saldoYCuentas(null, $transferencia);
            $modo = 'canned_transferencia';
            $resumen = 'Sin cuenta: se pidió identificación y se pasaron las cuentas';
        }

        return $this->paquete(
            FraseCliente::filtrar($reply, $reply),
            false,
            null,
            $clienteId,
            $modo,
            $resumen,
            null,
        );
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
