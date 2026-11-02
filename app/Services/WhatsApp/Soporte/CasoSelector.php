<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\WaCaso;

class CasoSelector
{
    public function __construct(
        private readonly EstiloInterplus $estilo,
    ) {}

    /**
     * @return list<array{titulo: string, texto: string}>
     */
    public function para(?string $etiqueta, string $mensaje, int $max = 3): array
    {
        try {
            $casos = WaCaso::query()->where('activo', true)->orderBy('id')->get();
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

        $salida = [];
        $estilo = trim($this->estilo->texto());
        if ($estilo !== '') {
            $salida[] = [
                'titulo' => 'Estilo Interplus',
                'texto' => $estilo,
            ];
        }

        foreach (array_slice($elegidos, 0, $max) as $caso) {
            $limpio = FraseCliente::filtrar((string) $caso->texto, '');
            if ($limpio === '' || ($limpio === (string) $caso->texto && $this->tieneMedicion((string) $caso->texto))) {
                continue;
            }
            if ($this->tieneMedicion($limpio)) {
                continue;
            }
            $salida[] = [
                'titulo' => (string) $caso->titulo,
                'texto' => $limpio,
            ];
        }

        return $salida;
    }

    private function tieneMedicion(string $texto): bool
    {
        return FraseCliente::filtrar($texto, '__no__') === '__no__';
    }
}
