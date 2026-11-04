<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Decide la respuesta del cliente a partir de datos ya medidos. No mide nada.
 *
 * @phpstan-type Decision array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
 */
class EvaluadorDiagnostico
{
    /**
     * @param  array<string, mixed>  $in
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    public function decidir(array $in): array
    {
        $estado = (string) ($in['estado'] ?? '');
        $pago = (bool) ($in['parece_pago'] ?? false);
        $comprobante = (bool) ($in['parece_comprobante'] ?? false);
        $texto = (string) ($in['texto'] ?? '');
        $paso = isset($in['paso']) ? (string) $in['paso'] : '';
        $incidente = (bool) ($in['incidente'] ?? false);
        $medicion = is_array($in['medicion'] ?? null) ? $in['medicion'] : [];

        if (in_array($estado, ['suspendido', 'cortado'], true) && ! $pago && ! $comprobante) {
            return $this->cerrar('cobranza', FraseCliente::cobranza((float) ($in['saldo'] ?? 0)), false, null, null, 'cobranza');
        }

        if ($comprobante) {
            return $this->cerrar('comprobante', FraseCliente::comprobante(), true, 'comprobante', null, 'comprobante');
        }

        if (SoporteTexto::pareceYaFunciona($texto) || ($paso !== '' && SoporteTexto::pareceCierre($texto))) {
            return $this->cerrar('cierre', FraseCliente::YA_FUNCIONA, false, null, null, 'cierre');
        }

        if (SoporteTexto::pareceTelevision($texto)) {
            return $this->cerrar('accion', FraseCliente::TV, false, null, null, 'tv');
        }

        if (SoporteTexto::pareceLuz($texto)) {
            return $this->cerrar('accion', FraseCliente::LUZ, false, null, null, 'luz');
        }

        if ($incidente && ($paso !== '' || (bool) ($in['parece_falla'] ?? false) || (bool) ($in['parece_impresora'] ?? false))) {
            return $this->cerrar('zona', FraseCliente::zona(), false, null, null, 'zona');
        }

        if ((bool) ($in['parece_wifi'] ?? false)) {
            return $this->cerrar('wifi', '', false, null, null, 'wifi');
        }

        if ((bool) ($in['parece_impresora'] ?? false) || $paso === 'impresora') {
            return $this->cerrar('impresora', '', false, null, null, 'impresora');
        }

        if ($paso === 'sitio') {
            return $this->sitio($texto, $medicion);
        }

        if ($paso === 'equipo') {
            return $this->equipo($texto, $medicion);
        }

        if ($paso === 'cable') {
            return $this->cable($texto, $medicion);
        }

        return $this->enlace($medicion);
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    private function enlace(array $medicion): array
    {
        $causas = is_array($medicion['causas'] ?? null) ? $medicion['causas'] : [];

        if (in_array('optica_mala', $causas, true) || ($medicion['optica_ok'] ?? null) === false) {
            return $this->cerrar('escalado', FraseCliente::OPTICA, true, 'optica', null, 'escalado');
        }

        if (in_array('cpe_sin_respuesta', $causas, true) || ($medicion['ping_cpe_ok'] ?? null) === false) {
            return $this->cerrar('escalado', FraseCliente::CPE_CAIDO, true, 'cpe', null, 'escalado');
        }

        if ($this->enlaceCaido($medicion) || in_array('sesion_offline', $causas, true) || in_array('salida_pop_caida', $causas, true)) {
            return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'enlace', null, 'escalado');
        }

        $lan = $medicion['lan_speed'] ?? null;
        $lanConectado = $medicion['lan_conectado'] ?? null;
        if (
            in_array('cable_lan', $causas, true)
            || $lanConectado === false
            || (is_numeric($lan) && (int) $lan > 0 && (int) $lan < 1000)
        ) {
            return $this->cerrar('accion', FraseCliente::CABLE, false, null, 'cable', 'cable');
        }

        $delta = $medicion['chain_delta'] ?? null;
        $senalAntena = $medicion['senal_antena'] ?? null;
        if (
            in_array('antena_debil', $causas, true)
            || (is_numeric($delta) && (float) $delta > 4)
            || (is_numeric($senalAntena) && (int) $senalAntena <= -75)
        ) {
            return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'antena', null, 'escalado');
        }

        if (($medicion['pudo_medir'] ?? false) !== true) {
            return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'sin_medicion', null, 'escalado');
        }

        // Enlace sano medido: informar análisis real antes de preguntar síntoma.
        return $this->cerrar('pregunta', FraseCliente::ENLACE_OK_PREGUNTA, false, null, 'sitio', 'pregunta');
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    private function sitio(string $texto, array $medicion): array
    {
        if (SoporteTexto::pareceNada($texto) || SoporteTexto::pareceSinSenal($texto)) {
            return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'sin_explicar', null, 'escalado');
        }

        if (SoporteTexto::sitio($texto) === null) {
            $equipos = is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [];
            if ($equipos !== []) {
                $nombres = array_map(fn ($e) => (string) ($e['nombre'] ?? 'Equipo'), $equipos);

                return $this->cerrar('pregunta', FraseCliente::listaEquipos($nombres), false, null, 'equipo', 'pregunta');
            }

            return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'sin_explicar', null, 'escalado');
        }

        if (($medicion['ping_general_ok'] ?? null) === true && ($medicion['sitio_ok'] ?? null) === false) {
            return $this->cerrar('resuelto', FraseCliente::SITIO_MAL, false, null, null, 'sitio');
        }

        if (($medicion['sitio_ok'] ?? null) === true) {
            return $this->cerrar('resuelto', FraseCliente::SITIO_BIEN, false, null, null, 'sitio');
        }

        return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'sitio', null, 'escalado');
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    private function equipo(string $texto, array $medicion): array
    {
        $equipos = is_array($medicion['equipos'] ?? null) ? $medicion['equipos'] : [];
        $indice = SoporteTexto::indiceEquipo($texto, count($equipos));
        if ($indice === null) {
            $nombres = array_map(fn ($e) => (string) ($e['nombre'] ?? 'Equipo'), $equipos);

            return $this->cerrar('pregunta', FraseCliente::listaEquipos($nombres), false, null, 'equipo', 'pregunta');
        }

        $rssi = $equipos[$indice]['rssi'] ?? null;
        if (is_numeric($rssi) && (int) $rssi <= -75) {
            return $this->cerrar('accion', FraseCliente::ACERCATE, false, null, null, 'senal');
        }

        return $this->cerrar('escalado', FraseCliente::ESCALADO, true, 'equipo', null, 'escalado');
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    private function cable(string $texto, array $medicion): array
    {
        if (! SoporteTexto::pareceCableListo($texto)) {
            return $this->cerrar('accion', FraseCliente::CABLE, false, null, 'cable', 'cable');
        }

        $lan = $medicion['lan_speed'] ?? null;
        $conectado = $medicion['lan_conectado'] ?? null;
        if ($conectado !== false && is_numeric($lan) && (int) $lan >= 1000) {
            return $this->cerrar('resuelto', FraseCliente::RESUELTO, false, null, null, 'resuelto');
        }

        return $this->cerrar('accion', FraseCliente::CABLE, false, null, 'cable', 'cable');
    }

    /**
     * @param  array<string, mixed>  $medicion
     */
    private function enlaceCaido(array $medicion): bool
    {
        if (($medicion['sesion_online'] ?? null) === false) {
            return true;
        }

        return ($medicion['ping_general_ok'] ?? null) === false;
    }

    /**
     * @return array{tipo: string, reply: string, escalate: bool, motivo: ?string, paso: ?string, etiqueta: ?string}
     */
    private function cerrar(string $tipo, string $reply, bool $escalate, ?string $motivo, ?string $paso, ?string $etiqueta): array
    {
        return [
            'tipo' => $tipo,
            'reply' => $reply,
            'escalate' => $escalate,
            'motivo' => $motivo,
            'paso' => $paso,
            'etiqueta' => $etiqueta,
        ];
    }
}
