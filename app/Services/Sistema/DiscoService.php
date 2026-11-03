<?php

namespace App\Services\Sistema;

/**
 * Espacio libre de los discos que ya tumbaron el panel (/tmp lleno = HTTP 500).
 */
class DiscoService
{
    /**
     * @return array{
     *   alerta: bool,
     *   texto: string,
     *   montajes: list<array{nombre: string, ruta: string, total: int, libre: int, uso: int, alerta: bool}>
     * }
     */
    public function estado(): array
    {
        $candidatos = [
            'Sistema' => DIRECTORY_SEPARATOR,
            'Temporal' => sys_get_temp_dir(),
            'Aplicación' => base_path(),
            'Almacenamiento' => storage_path(),
        ];

        $vistos = [];
        $montajes = [];
        foreach ($candidatos as $nombre => $ruta) {
            $real = @realpath($ruta) ?: $ruta;
            if ($real === '' || isset($vistos[$real]) || ! @is_dir($real)) {
                continue;
            }
            $total = @disk_total_space($real);
            $libre = @disk_free_space($real);
            if (! is_numeric($total) || ! is_numeric($libre) || (float) $total <= 0) {
                continue;
            }
            $vistos[$real] = true;
            $total = (int) $total;
            $libre = (int) $libre;
            $uso = (int) round((($total - $libre) / $total) * 100);
            $alerta = $uso >= 85 || $libre < 200 * 1024 * 1024;
            $montajes[] = [
                'nombre' => $nombre,
                'ruta' => $real,
                'total' => $total,
                'libre' => $libre,
                'uso' => $uso,
                'alerta' => $alerta,
            ];
        }

        $criticos = array_values(array_filter($montajes, fn (array $m) => $m['alerta']));
        $texto = $criticos === []
            ? ''
            : implode(' · ', array_map(function (array $m) {
                return $m['nombre'].' '.$m['uso'].'% ('.self::humano($m['libre']).' libres)';
            }, $criticos));

        return [
            'alerta' => $criticos !== [],
            'texto' => $texto,
            'montajes' => $montajes,
        ];
    }

    public static function humano(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}
