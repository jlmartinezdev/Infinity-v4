<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\WaCaso;

class CasoSelector
{
    /**
     * @return list<array{titulo: string, texto: string, caso_id?: int}>
     */
    public function para(?string $etiqueta, string $mensaje, int $max = 3): array
    {
        try {
            $casos = WaCaso::query()
                ->where('activo', true)
                ->orderByDesc('usos_exitosos')
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            $casos = collect();
        }

        $texto = SoporteTexto::norm($mensaje);
        $etiqueta = $etiqueta !== null && $etiqueta !== '' ? $etiqueta : null;
        $elegidos = [];

        foreach ($casos as $caso) {
            if ($etiqueta !== null && (string) $caso->etiqueta === $etiqueta) {
                $elegidos[] = $caso;
                continue;
            }
            $palabras = array_filter(array_map('trim', explode(',', mb_strtolower((string) $caso->palabras))));
            foreach ($palabras as $palabra) {
                if ($palabra !== '' && str_contains($texto, $palabra)) {
                    $elegidos[] = $caso;
                    break;
                }
            }
        }

        // Desempatar: más exitos primero (por si el match por etiqueta rompió el order).
        usort($elegidos, function ($a, $b) {
            $diff = ((int) $b->usos_exitosos) - ((int) $a->usos_exitosos);

            return $diff !== 0 ? $diff : ((int) $a->id) - ((int) $b->id);
        });

        // El estilo va solo por estilo_interplus al payload n8n; NUNCA al reply del cliente.
        $salida = [];

        foreach (array_slice($elegidos, 0, $max) as $caso) {
            $raw = (string) $caso->texto;
            if ($this->esInstruccionOSaludo($raw)) {
                continue;
            }
            $limpio = FraseCliente::filtrar($raw, '');
            if ($limpio === '' || FraseCliente::pareceFugaLineamiento($limpio)) {
                continue;
            }
            if ($this->tieneMedicion($limpio) || $this->esInstruccionOSaludo($limpio)) {
                continue;
            }
            // Keywords demasiado genéricas (wifi/internet solos) no aportan conocimiento útil.
            $palabras = array_filter(array_map('trim', explode(',', mb_strtolower((string) $caso->palabras))));
            $soloGenericas = $palabras !== [] && count(array_diff($palabras, ['wifi', 'internet', 'router', 'hola', 'buenas'])) === 0;
            if ($soloGenericas && $etiqueta === null) {
                continue;
            }
            $salida[] = [
                'titulo' => (string) $caso->titulo,
                'texto' => $limpio,
                'caso_id' => (int) $caso->id,
            ];
        }

        return $salida;
    }

    private function esInstruccionOSaludo(string $texto): bool
    {
        $t = mb_strtolower(trim($texto));
        if ($t === '') {
            return true;
        }
        if (FraseCliente::pareceFugaLineamiento($t)) {
            return true;
        }
        if (preg_match('/estilo interplus|obligatorio|use esto solo|nunca diga|nunca empuje/u', $t)) {
            return true;
        }
        if (preg_match('/^(muy )?(buenos? d[ií]as|buenas tardes|hola)[!¡.]*$/u', $t)) {
            return true;
        }
        if (preg_match('/titular:|n[°º]\s*de cuenta|ueno bank/u', $t)) {
            return true;
        }

        return false;
    }

    public function registrarUso(?int $casoId, bool $exito): void
    {
        if (! $casoId) {
            return;
        }
        try {
            $caso = WaCaso::query()->find($casoId);
            if (! $caso) {
                return;
            }
            if ($exito) {
                $caso->increment('usos_exitosos');
            } else {
                $caso->increment('usos_descartes');
            }
        } catch (\Throwable) {
            // tabla vieja sin columnas
        }
    }

    private function tieneMedicion(string $texto): bool
    {
        return FraseCliente::filtrar($texto, '__no__') === '__no__';
    }
}
