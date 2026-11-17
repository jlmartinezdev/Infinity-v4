<?php

namespace App\Support;

use App\Models\HotspotPerfil;

/**
 * Traduce entre el formulario de perfil (Mbps, número + unidad) y lo que guarda el perfil
 * en formato MikroTik ("5M/10M", "5m", "1d").
 */
final class HotspotPerfilFormulario
{
    public const UNIDADES = [
        'm' => 'minutos',
        'h' => 'horas',
        'd' => 'días',
    ];

    /** Formato de rate-limit de MikroTik: "rx[/tx]" y hasta cinco grupos más (ráfaga, umbral, tiempo, prioridad, mínimo). */
    public const REGEX_RATE_LIMIT = '/^\d+(?:\.\d+)?[kKmMgG]?(?:\/\d+(?:\.\d+)?[kKmMgG]?)?(?:\s+\d+(?:\.\d+)?[kKmMgG]?(?:\/\d+(?:\.\d+)?[kKmMgG]?)?){0,5}$/';

    /**
     * Valores para llenar el formulario a partir del perfil guardado.
     *
     * @return array{
     *     nombre: string,
     *     bajada_mbps: string,
     *     subida_mbps: string,
     *     rate_limit_avanzado: string,
     *     cuota_gb: string,
     *     shared_users: string,
     *     inactividad_valor: string,
     *     inactividad_unidad: string,
     *     sesion_valor: string,
     *     sesion_unidad: string,
     *     invalidos: array<string, string>
     * }
     */
    public static function campos(?HotspotPerfil $perfil): array
    {
        $rate = trim((string) ($perfil?->rate_limit ?? ''));
        $simple = self::rateSimple($rate);
        $inactividad = self::duracionACampos($perfil?->idle_timeout);
        $sesion = self::duracionACampos($perfil?->session_timeout);

        $invalidos = [];
        if ($inactividad['invalido']) {
            $invalidos['inactividad'] = trim((string) $perfil?->idle_timeout);
        }
        if ($sesion['invalido']) {
            $invalidos['sesion'] = trim((string) $perfil?->session_timeout);
        }
        if ($rate !== '' && ! $simple && ! preg_match(self::REGEX_RATE_LIMIT, $rate)) {
            $invalidos['velocidad'] = $rate;
        }

        return [
            'nombre' => (string) ($perfil?->nombre ?? ''),
            'bajada_mbps' => $simple ? self::numero($simple['bajada']) : '',
            'subida_mbps' => $simple ? self::numero($simple['subida']) : '',
            'rate_limit_avanzado' => ($rate !== '' && ! $simple && ! isset($invalidos['velocidad'])) ? $rate : '',
            'cuota_gb' => $perfil?->cuota_gb ? (string) (int) $perfil->cuota_gb : '',
            'shared_users' => trim((string) ($perfil?->shared_users ?? '')),
            'inactividad_valor' => $inactividad['valor'],
            'inactividad_unidad' => $inactividad['unidad'],
            'sesion_valor' => $sesion['valor'],
            'sesion_unidad' => $sesion['unidad'],
            'invalidos' => $invalidos,
        ];
    }

    /**
     * Atributos del perfil a partir de los datos ya validados del formulario.
     *
     * @param  array<string, mixed>  $datos
     * @return array{nombre: string, rate_limit: ?string, cuota_gb: ?int, shared_users: ?string, idle_timeout: ?string, session_timeout: ?string}
     */
    public static function atributos(array $datos): array
    {
        $avanzado = trim((string) ($datos['rate_limit_avanzado'] ?? ''));
        $bajada = self::valorONull($datos['bajada_mbps'] ?? null);
        $subida = self::valorONull($datos['subida_mbps'] ?? null);

        $rate = null;
        if ($avanzado !== '') {
            $rate = $avanzado;
        } elseif ($bajada !== null && $subida !== null) {
            $rate = self::rateDesdeMbps((float) $subida, (float) $bajada);
        }

        $shared = self::valorONull($datos['shared_users'] ?? null);
        $cuota = self::valorONull($datos['cuota_gb'] ?? null);

        return [
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'rate_limit' => $rate,
            'cuota_gb' => $cuota === null ? null : (int) $cuota,
            'shared_users' => $shared === null ? null : (string) (int) $shared,
            'idle_timeout' => self::duracionDesdeCampos($datos['inactividad_valor'] ?? null, $datos['inactividad_unidad'] ?? null),
            'session_timeout' => self::duracionDesdeCampos($datos['sesion_valor'] ?? null, $datos['sesion_unidad'] ?? null),
        ];
    }

    /**
     * Subida y bajada en Mbps si el rate-limit es simple ("5M/10M", sin ráfagas).
     *
     * @return array{subida: float, bajada: float}|null
     */
    public static function rateSimple(string $rate): ?array
    {
        $rate = trim($rate);
        if ($rate === '' || preg_match('/\s/', $rate)) {
            return null;
        }
        $partes = explode('/', $rate);
        if (count($partes) > 2) {
            return null;
        }
        $subida = self::aMbps($partes[0]);
        $bajada = self::aMbps($partes[1] ?? $partes[0]);
        if ($subida === null || $bajada === null) {
            return null;
        }

        return ['subida' => $subida, 'bajada' => $bajada];
    }

    /**
     * "10M" → 10, "512k" → 0.512, "1G" → 1000, "1000000" (bps) → 1.
     */
    public static function aMbps(string $valor): ?float
    {
        if (! preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kKmMgG]?)\s*$/', $valor, $m)) {
            return null;
        }
        $n = (float) $m[1];
        $mbps = match (strtolower($m[2])) {
            'k' => $n / 1000,
            'm' => $n,
            'g' => $n * 1000,
            default => $n / 1_000_000,
        };

        return $mbps > 0 ? round($mbps, 3) : null;
    }

    /**
     * Rate-limit "subida/bajada" para el MikroTik. Los decimales van en kbps: 1,5 Mbps → "1500k".
     */
    public static function rateDesdeMbps(float $subida, float $bajada): string
    {
        return self::tasa($subida).'/'.self::tasa($bajada);
    }

    /**
     * @return array{valor: string, unidad: string, invalido: bool}
     */
    public static function duracionACampos(?string $valor): array
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strcasecmp($valor, 'none') === 0) {
            return ['valor' => '', 'unidad' => 'm', 'invalido' => false];
        }
        $segundos = HotspotDuracion::segundos($valor);
        if ($segundos === null || $segundos <= 0) {
            return ['valor' => '', 'unidad' => 'm', 'invalido' => true];
        }
        if ($segundos % 86400 === 0) {
            return ['valor' => (string) intdiv($segundos, 86400), 'unidad' => 'd', 'invalido' => false];
        }
        if ($segundos % 3600 === 0) {
            return ['valor' => (string) intdiv($segundos, 3600), 'unidad' => 'h', 'invalido' => false];
        }

        return ['valor' => (string) max(1, (int) round($segundos / 60)), 'unidad' => 'm', 'invalido' => false];
    }

    public static function duracionDesdeCampos(mixed $valor, mixed $unidad): ?string
    {
        $valor = self::valorONull($valor);
        if ($valor === null || (int) $valor <= 0) {
            return null;
        }
        $unidad = isset(self::UNIDADES[(string) $unidad]) ? (string) $unidad : 'm';

        return (int) $valor.$unidad;
    }

    private static function tasa(float $mbps): string
    {
        if (abs($mbps - round($mbps)) < 0.0005) {
            return (int) round($mbps).'M';
        }

        return (int) round($mbps * 1000).'k';
    }

    private static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private static function valorONull(mixed $valor): mixed
    {
        if ($valor === null) {
            return null;
        }
        if (is_string($valor) && trim($valor) === '') {
            return null;
        }

        return $valor;
    }
}
