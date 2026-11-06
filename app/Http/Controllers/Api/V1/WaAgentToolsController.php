<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Soporte\CasoSelector;
use App\Services\WhatsApp\Soporte\CuentaWhatsappService;
use App\Services\WhatsApp\Soporte\EvaluadorDiagnostico;
use App\Services\WhatsApp\Soporte\IncidenteZonaService;
use App\Services\WhatsApp\Soporte\RecolectorDiagnostico;
use App\Services\WhatsApp\WhatsAppAgentService;
use App\Services\WhatsApp\WhatsAppInboundTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hechos medibles para LangGraph. Respuestas tipadas; sin prosa libre.
 */
class WaAgentToolsController extends ApiController
{
    public function buscarCuenta(Request $request, CuentaWhatsappService $cuentas): JsonResponse
    {
        $telefono = trim((string) $request->input('telefono', ''));
        $texto = trim((string) $request->input('texto', ''));

        $cuenta = null;
        if ($texto !== '') {
            $cuenta = $cuentas->buscarEnTexto($texto);
        }
        if ($cuenta === null && $telefono !== '') {
            $cuenta = $cuentas->buscar($telefono);
        }

        return $this->ok([
            'encontrada' => $cuenta !== null,
            'cuenta' => $cuenta,
        ]);
    }

    public function saldoYPlan(Request $request, CuentaWhatsappService $cuentas): JsonResponse
    {
        $telefono = trim((string) $request->input('telefono', ''));
        $texto = trim((string) $request->input('texto', ''));
        $clienteId = (int) $request->input('cliente_id', 0);

        $cuenta = null;
        if ($clienteId > 0 && $telefono !== '') {
            $cuenta = $cuentas->buscar($telefono);
            if ($cuenta && (int) ($cuenta['cliente_id'] ?? 0) !== $clienteId) {
                $cuenta = null;
            }
        }
        if ($cuenta === null && $texto !== '') {
            $cuenta = $cuentas->buscarEnTexto($texto);
        }
        if ($cuenta === null && $telefono !== '') {
            $cuenta = $cuentas->buscar($telefono);
        }

        if ($cuenta === null) {
            return $this->ok([
                'encontrada' => false,
                'saldo' => null,
                'plan_nombre' => null,
                'plan_precio' => null,
                'estado' => null,
                'cuenta' => null,
            ]);
        }

        return $this->ok([
            'encontrada' => true,
            'saldo' => $cuenta['saldo'] ?? null,
            'plan_nombre' => $cuenta['plan_nombre'] ?? null,
            'plan_precio' => $cuenta['plan_precio'] ?? null,
            'estado' => $cuenta['estado'] ?? null,
            'dias_mora' => $cuenta['dias_mora'] ?? null,
            'cliente_id' => $cuenta['cliente_id'] ?? null,
            'vocativo' => $cuenta['vocativo'] ?? null,
            'trato' => $cuenta['trato'] ?? null,
            'cuenta' => $cuenta,
        ]);
    }

    public function catalogoPlanes(WhatsAppAgentService $agent): JsonResponse
    {
        $cat = $agent->catalogoPlanes();

        return $this->ok([
            'fibra' => $cat['fibra'] ?? [],
            'antena' => $cat['antena'] ?? [],
            'otros' => $cat['otros'] ?? [],
            'planes_texto' => $cat['planes_texto'] ?? '',
            'planes_prompt' => $cat['planes_prompt'] ?? '',
        ]);
    }

    public function textosCanned(): JsonResponse
    {
        $c = WhatsAppAgentService::textosCanned();

        return $this->ok([
            'transferencia' => $c['transferencia'] ?? '',
            'beneficios' => $c['beneficios'] ?? '',
            'condiciones' => $c['condiciones'] ?? '',
        ]);
    }

    public function diagnosticar(
        Request $request,
        CuentaWhatsappService $cuentas,
        RecolectorDiagnostico $recolector,
        EvaluadorDiagnostico $evaluador,
        IncidenteZonaService $zona,
    ): JsonResponse {
        $sandbox = filter_var($request->input('sandbox', false), FILTER_VALIDATE_BOOLEAN);
        $telefono = trim((string) $request->input('telefono', ''));
        $texto = trim((string) $request->input('texto', ''));
        $sitio = trim((string) $request->input('sitio', '')) ?: null;

        $cuenta = $texto !== '' ? $cuentas->buscarEnTexto($texto) : null;
        $cuenta ??= $telefono !== '' ? $cuentas->buscar($telefono) : null;
        if ($cuenta === null) {
            return $this->ok([
                'ok' => false,
                'motivo' => 'sin_cuenta',
                'medicion' => null,
                'decision' => null,
            ]);
        }

        $servicio = $cuentas->servicioDe(isset($cuenta['servicio_id']) ? (int) $cuenta['servicio_id'] : null);
        if (! $servicio) {
            return $this->ok([
                'ok' => false,
                'motivo' => 'sin_servicio',
                'cliente_id' => $cuenta['cliente_id'] ?? null,
                'medicion' => null,
                'decision' => null,
            ]);
        }

        $routerId = isset($cuenta['router_id']) ? (int) $cuenta['router_id'] : null;
        $incidenteZona = $zona->hayAbierto($routerId)
            || $zona->telefonoConAvisoAbierto($telefono !== '' ? $telefono : ($cuenta['whatsapp'] ?? null));

        // Mora / suspensión: no hace falta medir MikroTik.
        $estado = (string) ($cuenta['estado'] ?? '');
        $saldo = (float) ($cuenta['saldo'] ?? 0);
        $diasMora = (int) ($cuenta['dias_mora'] ?? 0);
        if (in_array($estado, ['suspendido', 'cortado'], true) || ($saldo > 0 && $diasMora > 0)) {
            $decision = $evaluador->decidir([
                'estado' => $estado,
                'saldo' => $saldo,
                'dias_mora' => $diasMora,
                'parece_pago' => false,
                'parece_comprobante' => false,
                'parece_falla' => true,
                'texto' => $texto,
                'paso' => '',
                'incidente' => $incidenteZona,
                'medicion' => [],
            ]);

            return $this->ok([
                'ok' => true,
                'motivo' => null,
                'cliente_id' => $cuenta['cliente_id'] ?? null,
                'sector' => 'tecnico',
                'paso_embudo' => 'cobranza',
                'incidente_zona_abierto' => $incidenteZona,
                'cuenta' => [
                    'estado' => $estado,
                    'saldo' => $saldo,
                    'dias_mora' => $diasMora,
                ],
                'medicion' => null,
                'decision' => [
                    'tipo' => $decision['tipo'] ?? null,
                    'reply' => $decision['reply'] ?? null,
                    'escalate' => (bool) ($decision['escalate'] ?? false),
                    'motivo' => $decision['motivo'] ?? null,
                    'etiqueta' => $decision['etiqueta'] ?? null,
                ],
            ]);
        }

        if ($incidenteZona) {
            $decision = $evaluador->decidir([
                'estado' => $estado,
                'saldo' => $saldo,
                'dias_mora' => $diasMora,
                'parece_pago' => false,
                'parece_comprobante' => false,
                'parece_falla' => true,
                'texto' => $texto,
                'paso' => 'zona',
                'incidente' => true,
                'medicion' => [],
            ]);

            return $this->ok([
                'ok' => true,
                'motivo' => null,
                'cliente_id' => $cuenta['cliente_id'] ?? null,
                'sector' => 'tecnico',
                'paso_embudo' => 'zona',
                'incidente_zona_abierto' => true,
                'medicion' => null,
                'decision' => [
                    'tipo' => $decision['tipo'] ?? null,
                    'reply' => $decision['reply'] ?? null,
                    'escalate' => (bool) ($decision['escalate'] ?? false),
                    'motivo' => $decision['motivo'] ?? null,
                    'etiqueta' => $decision['etiqueta'] ?? null,
                ],
            ]);
        }

        if ($sandbox) {
            return $this->ok([
                'ok' => true,
                'motivo' => 'sandbox_dry_run',
                'cliente_id' => $cuenta['cliente_id'] ?? null,
                'sector' => 'tecnico',
                'paso_embudo' => 'sandbox',
                'incidente_zona_abierto' => false,
                'medicion' => [
                    'pudo_medir' => false,
                    'sandbox' => true,
                ],
                'decision' => [
                    'tipo' => 'sandbox',
                    'reply' => null,
                    'escalate' => false,
                ],
            ]);
        }

        $medicion = $recolector->medir($servicio, $sitio);
        $decision = $evaluador->decidir([
            'estado' => $estado,
            'saldo' => $saldo,
            'dias_mora' => $diasMora,
            'parece_pago' => false,
            'parece_comprobante' => false,
            'parece_falla' => true,
            'texto' => $texto,
            'paso' => '',
            'incidente' => false,
            'medicion' => $medicion,
        ]);

        return $this->ok([
            'ok' => true,
            'motivo' => null,
            'cliente_id' => $cuenta['cliente_id'] ?? null,
            'sector' => 'tecnico',
            'paso_embudo' => 'enlace',
            'incidente_zona_abierto' => false,
            'medicion' => [
                'pudo_medir' => (bool) ($medicion['pudo_medir'] ?? false),
                'sesion_online' => $medicion['sesion_online'] ?? null,
                'ping_cpe_ok' => $medicion['ping_cpe_ok'] ?? null,
                'ping_general_ok' => $medicion['ping_general_ok'] ?? null,
                'optica_ok' => $medicion['optica_ok'] ?? null,
                'lan_conectado' => $medicion['lan_conectado'] ?? null,
                'causas' => $medicion['causas'] ?? [],
                'equipos_count' => is_array($medicion['equipos'] ?? null) ? count($medicion['equipos']) : 0,
                'equipos_nombres' => array_values(array_filter(array_map(
                    fn ($e) => is_array($e) ? (string) ($e['nombre'] ?? $e['hostname'] ?? '') : '',
                    is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : []
                ))),
            ],
            'decision' => [
                'tipo' => $decision['tipo'] ?? null,
                'reply' => $decision['reply'] ?? null,
                'escalate' => (bool) ($decision['escalate'] ?? false),
                'motivo' => $decision['motivo'] ?? null,
                'etiqueta' => $decision['etiqueta'] ?? null,
            ],
        ]);
    }

    public function zona(Request $request, CuentaWhatsappService $cuentas, IncidenteZonaService $zona): JsonResponse
    {
        $telefono = trim((string) $request->input('telefono', ''));
        $texto = trim((string) $request->input('texto', ''));
        $cuenta = $texto !== '' ? $cuentas->buscarEnTexto($texto) : null;
        $cuenta ??= $telefono !== '' ? $cuentas->buscar($telefono) : null;
        $routerId = isset($cuenta['router_id']) ? (int) $cuenta['router_id'] : null;

        $abierto = $zona->hayAbierto($routerId);
        $telAviso = $zona->telefonoConAvisoAbierto($telefono !== '' ? $telefono : ($cuenta['whatsapp'] ?? null));

        return $this->ok([
            'incidente_zona_abierto' => $abierto,
            'telefono_con_aviso' => $telAviso,
            'router_id' => $routerId,
            'cliente_id' => $cuenta['cliente_id'] ?? null,
        ]);
    }

    public function conocimiento(Request $request, CasoSelector $casos): JsonResponse
    {
        $mensaje = trim((string) $request->input('mensaje', ''));
        $etiqueta = trim((string) $request->input('etiqueta', '')) ?: null;
        $max = max(1, min(5, (int) $request->input('max', 3)));
        $items = $casos->para($etiqueta, $mensaje, $max);

        return $this->ok([
            'casos' => array_map(static function ($c) {
                return [
                    'caso_id' => $c['caso_id'] ?? $c['id'] ?? null,
                    'titulo' => $c['titulo'] ?? null,
                    'texto' => $c['texto'] ?? null,
                    'etiqueta' => $c['etiqueta'] ?? null,
                ];
            }, $items),
        ]);
    }

    public function hilo(Request $request, WhatsAppAgentService $agent): JsonResponse
    {
        $telefono = trim((string) $request->input('telefono', $request->query('telefono', '')));
        if ($telefono === '') {
            return $this->fail('Indicá telefono.', 422);
        }
        $limite = $request->filled('limite') ? (int) $request->input('limite') : null;
        $exclude = $request->filled('exclude_id') ? (int) $request->input('exclude_id') : null;

        return $this->ok($agent->hiloParaTelefono($telefono, $exclude, $limite));
    }

    public function crearTicket(Request $request, WhatsAppInboundTicketService $tickets): JsonResponse
    {
        $sandbox = filter_var($request->input('sandbox', false), FILTER_VALIDATE_BOOLEAN);
        if ($sandbox) {
            return $this->ok([
                'created' => false,
                'ticket_id' => null,
                'motivo' => 'sandbox',
            ]);
        }

        $mensajeId = (int) $request->input('mensaje_id', 0);
        $mensaje = $mensajeId > 0 ? WhatsappMensaje::query()->find($mensajeId) : null;
        if (! $mensaje) {
            return $this->fail('mensaje_id inválido', 422);
        }

        $resultado = $tickets->crearOAdjuntar($mensaje, false, [
            'resumen_admin' => trim((string) $request->input('resumen_admin', '')) ?: null,
            'prioridad' => trim((string) $request->input('prioridad', 'alta')) ?: 'alta',
        ]);

        return $this->ok([
            'created' => (bool) ($resultado['created'] ?? false),
            'ticket_id' => $resultado['ticket']?->id,
        ]);
    }
}
