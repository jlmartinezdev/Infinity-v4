<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Corta respuestas de n8n que inventan causas técnicas o precios fuera del catálogo.
 */
class ValidadorHechos
{
    /**
     * @param  array<string, mixed>  $hechos
     * @param  array{fibra?: list<array<string, mixed>>, antena?: list<array<string, mixed>>}  $catalogo
     * @return array{reply: string, modo: ?string, filtrado: bool}
     */
    public function validar(string $reply, array $hechos, array $catalogo = [], string $fallback = ''): array
    {
        $reply = trim($reply);
        $fallback = $fallback !== '' ? $fallback : FraseCliente::ESCALADO;
        if ($reply === '') {
            return ['reply' => $fallback, 'modo' => 'n8n_sin_reply', 'filtrado' => true];
        }

        $causas = is_array($hechos['causas'] ?? null) ? $hechos['causas'] : [];
        $zona = (bool) ($hechos['incidente_zona_abierto'] ?? false);
        $pudoMedir = (bool) ($hechos['pudo_medir'] ?? false);

        if ($this->afirmaReinicio($reply) && ! in_array('sesion_offline', $causas, true) && ! $zona) {
            return ['reply' => $fallback, 'modo' => 'n8n_hechos_reinicio', 'filtrado' => true];
        }

        if ($this->afirmaCable($reply) && ! in_array('cable_lan', $causas, true)) {
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
            $planesTexto = (string) ($catalogo['planes_texto'] ?? '');
            if ($planesTexto !== '') {
                return ['reply' => $planesTexto, 'modo' => 'n8n_catalogo', 'filtrado' => true];
            }

            return ['reply' => $fallback, 'modo' => 'n8n_precio_inventado', 'filtrado' => true];
        }

        $filtrado = FraseCliente::filtrar($reply, $fallback);
        if ($filtrado !== $reply) {
            return ['reply' => $filtrado, 'modo' => 'n8n_filtrado', 'filtrado' => true];
        }

        return ['reply' => $reply, 'modo' => null, 'filtrado' => false];
    }

    private function afirmaReinicio(string $reply): bool
    {
        return (bool) preg_match('/\b(reinicie|reiniciar|apague y prenda|desenchufe|reset(ee|ear)?)\b/iu', $reply);
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
     * @param  array{fibra?: list<array<string, mixed>>, antena?: list<array<string, mixed>>}  $catalogo
     */
    private function precioFueraDeCatalogo(string $reply, array $catalogo): bool
    {
        if (! preg_match_all('/\b(\d{1,3}(?:[.\s]\d{3})+|\d{5,7})\b/u', $reply, $m)) {
            return false;
        }

        $precios = [];
        foreach (array_merge($catalogo['fibra'] ?? [], $catalogo['antena'] ?? []) as $plan) {
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

        foreach ($m[1] as $raw) {
            $n = (int) preg_replace('/\D+/', '', $raw);
            if ($n < 50000) {
                continue; // cédulas u otros números chicos
            }
            if (! in_array($n, $precios, true)) {
                // Solo marcar si el contexto parece cotización
                if (preg_match('/\b(gs\.?|guaran|plan|precio|cuesta|mensual)\b/iu', $reply)) {
                    return true;
                }
            }
        }

        return false;
    }
}
