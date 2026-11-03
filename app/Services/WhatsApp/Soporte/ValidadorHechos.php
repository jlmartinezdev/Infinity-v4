<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Corta respuestas de n8n que inventan causas técnicas, precios o filtrán prompts.
 */
class ValidadorHechos
{
    /**
     * @param  array<string, mixed>  $hechos
     * @param  array{fibra?: list<array<string, mixed>>, antena?: list<array<string, mixed>>, otros?: list<array<string, mixed>>, planes_texto?: string}  $catalogo
     * @return array{reply: string, modo: ?string, filtrado: bool}
     */
    public function validar(string $reply, array $hechos, array $catalogo = [], string $fallback = ''): array
    {
        $reply = trim($reply);
        $fallback = $fallback !== '' ? $fallback : FraseCliente::ESCALADO;
        $listaCliente = trim((string) ($catalogo['planes_texto'] ?? ''));
        $fallbackVentas = $listaCliente !== '' ? $listaCliente : $fallback;

        if ($reply === '') {
            return ['reply' => $fallback, 'modo' => 'n8n_sin_reply', 'filtrado' => true];
        }

        if (FraseCliente::pareceFugaLineamiento($reply)) {
            return ['reply' => $fallbackVentas, 'modo' => 'n8n_fuga_prompt', 'filtrado' => true];
        }

        $causas = is_array($hechos['causas'] ?? null) ? $hechos['causas'] : [];
        $zona = (bool) ($hechos['incidente_zona_abierto'] ?? false);
        $pudoMedir = (bool) ($hechos['pudo_medir'] ?? false);

        if ($this->afirmaReinicio($reply) && ! in_array('sesion_offline', $causas, true) && ! $zona) {
            return ['reply' => $fallback, 'modo' => 'n8n_hechos_reinicio', 'filtrado' => true];
        }

        if ($this->afirmaCable($reply) && ! in_array('cable_lan', $causas, true)) {
            if (! $pudoMedir) {
                return ['reply' => $fallback, 'modo' => 'n8n_hechos_cable', 'filtrado' => true];
            }

            return ['reply' => FraseCliente::ENLACE_OK_PREGUNTA, 'modo' => 'n8n_hechos_cable', 'filtrado' => true];
        }

        if ($this->afirmaOptica($reply) && ! in_array('optica_mala', $causas, true)) {
            return ['reply' => $fallback, 'modo' => 'n8n_hechos_optica', 'filtrado' => true];
        }

        if ($this->afirmaZona($reply) && ! $zona) {
            return ['reply' => $fallback, 'modo' => 'n8n_hechos_zona', 'filtrado' => true];
        }

        if ($pudoMedir && $this->afirmaTodoBien($reply) && $causas !== []) {
            return ['reply' => $fallback, 'modo' => 'n8n_hechos_contradice', 'filtrado' => true];
        }

        if (preg_match('/no (podemos|nos es posible) visualizar.*(nombre|wi-?fi|wifi|ssid)|no vemos el nombre (exacto )?(de )?(su |la )?red|no tenemos visibilidad de los dispositivos/iu', $reply)) {
            return [
                'reply' => 'El nombre de la red lo leemos del equipo cuando está en línea. Si no aparece ahora, figura en la etiqueta del router. Escriba NOMBRE WIFI y lo consultamos de nuevo.',
                'modo' => 'n8n_hechos_ssid',
                'filtrado' => true,
            ];
        }

        // n8n no puede negar la lectura de equipos si Laravel sí puede listarlos.
        if (
            ($hechos['puede_listar_equipos'] ?? false) === true
            && preg_match('/no (nos )?es posible visualizar|no (podemos|podemos) ver (el detalle|los equipos|dispositivos)/iu', $reply)
        ) {
            $nombres = is_array($hechos['equipos_nombres'] ?? null) ? $hechos['equipos_nombres'] : [];

            return [
                'reply' => FraseCliente::listaEquiposVista(array_map('strval', $nombres), false),
                'modo' => 'n8n_hechos_equipos',
                'filtrado' => true,
            ];
        }

        if ($this->precioFueraDeCatalogo($reply, $catalogo)) {
            return [
                'reply' => $fallbackVentas,
                'modo' => 'n8n_catalogo',
                'filtrado' => true,
            ];
        }

        $filtrado = FraseCliente::filtrar($reply, $fallback);
        if ($filtrado !== $reply) {
            // Si el filtro tumba por fuga/medición pero el reply era cotización, preferir lista limpia.
            if (FraseCliente::pareceFugaLineamiento($reply) && $listaCliente !== '') {
                return ['reply' => $listaCliente, 'modo' => 'n8n_fuga_prompt', 'filtrado' => true];
            }

            return ['reply' => $filtrado, 'modo' => 'n8n_filtrado', 'filtrado' => true];
        }

        return ['reply' => $reply, 'modo' => null, 'filtrado' => false];
    }

    private function afirmaReinicio(string $reply): bool
    {
        return (bool) preg_match('/\b(reinicie|reiniciar|reinicia|apague y prenda|ap[aá]guelo|desenchufe|desconecte|reset(ee|ear)?)\b/iu', $reply);
    }

    private function afirmaCable(string $reply): bool
    {
        return (bool) preg_match('/\b(revise el cable|cable (?:del|de) (?:router|onu|modem)|enchufe el cable)\b/iu', $reply);
    }

    private function afirmaOptica(string $reply): bool
    {
        return (bool) preg_match('/\b(fibra (?:cortada|dañada|mala)|se[nñ]al [oó]ptica|potencia [oó]ptica)\b/iu', $reply);
    }

    private function afirmaZona(string $reply): bool
    {
        return (bool) preg_match('/\binconveniente en (?:su |la )?zona\b|\bcorte de zona\b|\bafectaci[oó]n (?:en |de )?zona\b/iu', $reply);
    }

    private function afirmaTodoBien(string $reply): bool
    {
        return (bool) preg_match('/\b(todo (?:est[aá] |anda )?bien|sin problemas|funcionando (?:correctamente|bien))\b/iu', $reply);
    }

    /**
     * @param  array{fibra?: list<array<string, mixed>>, antena?: list<array<string, mixed>>, otros?: list<array<string, mixed>>}  $catalogo
     */
    private function precioFueraDeCatalogo(string $reply, array $catalogo): bool
    {
        // Solo montos que parecen precio (Gs. / guaraníes), no cédulas ni cuentas.
        if (! preg_match_all('/Gs\.?\s*(\d{1,3}(?:[.\s]\d{3})+|\d{5,7})|(\d{1,3}(?:[.\s]\d{3})+|\d{5,7})\s*(?:Gs\.?|guaran)/iu', $reply, $m, PREG_SET_ORDER)) {
            return false;
        }

        $precios = [];
        foreach (array_merge($catalogo['fibra'] ?? [], $catalogo['antena'] ?? [], $catalogo['otros'] ?? []) as $plan) {
            if (! is_array($plan)) {
                continue;
            }
            foreach (['precio', 'precio_gs', 'monto'] as $k) {
                if (isset($plan[$k]) && is_numeric($plan[$k])) {
                    $precios[] = (int) $plan[$k];
                }
            }
        }
        if ($precios === []) {
            return false;
        }

        foreach ($m as $match) {
            $raw = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
            $n = (int) preg_replace('/\D+/', '', (string) $raw);
            if ($n < 50000) {
                continue;
            }
            if (! in_array($n, $precios, true)) {
                return true;
            }
        }

        return false;
    }
}
