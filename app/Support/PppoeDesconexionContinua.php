<?php

namespace App\Support;

use App\Models\ServicioConexionEvento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Detecta líneas PPPoE que se desconectan seguido (flapping): cuenta las
 * desconexiones de la última hora, 6 horas y 24 horas y las clasifica según
 * los umbrales de config/pppoe_eventos.php.
 */
class PppoeDesconexionContinua
{
    public const NIVEL_CRITICO = 'critico';

    public const NIVEL_ALTO = 'alto';

    public const NIVEL_OBSERVAR = 'observar';

    /** De más grave a menos grave. */
    public const NIVELES = [self::NIVEL_CRITICO, self::NIVEL_ALTO, self::NIVEL_OBSERVAR];

    /** @return array<string, array{h1: ?int, h6: ?int, h24: ?int}> */
    public static function umbrales(): array
    {
        $config = (array) config('pppoe_eventos.atencion', []);
        $umbrales = [];

        foreach (self::NIVELES as $nivel) {
            $fila = (array) ($config[$nivel] ?? []);
            $umbrales[$nivel] = [
                'h1' => self::entero($fila['h1'] ?? null),
                'h6' => self::entero($fila['h6'] ?? null),
                'h24' => self::entero($fila['h24'] ?? null),
            ];
        }

        return $umbrales;
    }

    /** Nivel más grave que cumple, o null si no requiere atención. */
    public static function nivel(int $h1, int $h6, int $h24): ?string
    {
        $conteos = ['h1' => $h1, 'h6' => $h6, 'h24' => $h24];

        foreach (self::umbrales() as $nivel => $minimos) {
            foreach ($minimos as $ventana => $minimo) {
                if ($minimo !== null && $conteos[$ventana] >= $minimo) {
                    return $nivel;
                }
            }
        }

        return null;
    }

    /** Nivel que alcanza una sola ventana (h1, h6 o h24) con esa cantidad de cortes. */
    public static function nivelDeVentana(string $ventana, int $cortes): ?string
    {
        foreach (self::umbrales() as $nivel => $minimos) {
            $minimo = $minimos[$ventana] ?? null;
            if ($minimo !== null && $cortes >= $minimo) {
                return $nivel;
            }
        }

        return null;
    }

    public static function etiqueta(?string $nivel): string
    {
        return match ($nivel) {
            self::NIVEL_CRITICO => 'Crítico',
            self::NIVEL_ALTO => 'Alto',
            self::NIVEL_OBSERVAR => 'A observar',
            default => 'Estable',
        };
    }

    /**
     * Desconexiones por servicio en las últimas 1, 6 y 24 horas.
     *
     * @param  Builder<ServicioConexionEvento>|null  $eventos  consulta ya filtrada (fuente, router…); sin filtro de fechas
     * @param  int  $minimo24h  descarta servicios con menos desconexiones en 24 h
     * @return Collection<int, array{servicio_id: int, h1: int, h6: int, h24: int, ultimo_corte_at: ?string, nivel: ?string}>
     */
    public static function conteos(?Builder $eventos = null, ?CarbonInterface $ahora = null, int $minimo24h = 1): Collection
    {
        $ahora = ($ahora ?? now())->copy();
        $formato = 'Y-m-d H:i:s';
        $eventos = $eventos ? clone $eventos : ServicioConexionEvento::query();

        return $eventos->toBase()
            ->where('servicio_conexion_eventos.tipo', ServicioConexionEvento::TIPO_PPPOE_DOWN)
            ->where('servicio_conexion_eventos.ocurrio_at', '>=', $ahora->copy()->subHours(24)->format($formato))
            ->where('servicio_conexion_eventos.ocurrio_at', '<=', $ahora->format($formato))
            ->select('servicio_conexion_eventos.servicio_id')
            ->selectRaw(
                'SUM(CASE WHEN servicio_conexion_eventos.ocurrio_at >= ? THEN 1 ELSE 0 END) as h1',
                [$ahora->copy()->subHour()->format($formato)]
            )
            ->selectRaw(
                'SUM(CASE WHEN servicio_conexion_eventos.ocurrio_at >= ? THEN 1 ELSE 0 END) as h6',
                [$ahora->copy()->subHours(6)->format($formato)]
            )
            ->selectRaw('COUNT(*) as h24')
            ->selectRaw('MAX(servicio_conexion_eventos.ocurrio_at) as ultimo_corte_at')
            ->groupBy('servicio_conexion_eventos.servicio_id')
            ->havingRaw('COUNT(*) >= ?', [max(1, $minimo24h)])
            ->get()
            ->map(function ($fila) {
                $h1 = (int) $fila->h1;
                $h6 = (int) $fila->h6;
                $h24 = (int) $fila->h24;

                return [
                    'servicio_id' => (int) $fila->servicio_id,
                    'h1' => $h1,
                    'h6' => $h6,
                    'h24' => $h24,
                    'ultimo_corte_at' => $fila->ultimo_corte_at,
                    'nivel' => self::nivel($h1, $h6, $h24),
                ];
            })
            ->keyBy('servicio_id');
    }

    /**
     * Servicios que requieren atención, del más grave al menos grave.
     *
     * @param  Builder<ServicioConexionEvento>|null  $eventos
     * @return Collection<int, array{servicio_id: int, h1: int, h6: int, h24: int, ultimo_corte_at: ?string, nivel: string}>
     */
    public static function enAtencion(?Builder $eventos = null, ?CarbonInterface $ahora = null): Collection
    {
        $orden = array_flip(self::NIVELES);

        return self::conteos($eventos, $ahora, self::minimoParaEntrar())
            ->filter(fn (array $fila) => $fila['nivel'] !== null)
            ->sort(fn (array $a, array $b) => [$orden[$a['nivel']], $b['h1'], $b['h6'], $b['h24'], $b['ultimo_corte_at']]
                <=> [$orden[$b['nivel']], $a['h1'], $a['h6'], $a['h24'], $a['ultimo_corte_at']])
            ->values();
    }

    /** El umbral más chico de todos: con menos cortes en 24 h nadie entra en ningún nivel. */
    private static function minimoParaEntrar(): int
    {
        $minimos = [];
        foreach (self::umbrales() as $fila) {
            foreach ($fila as $minimo) {
                if ($minimo !== null) {
                    $minimos[] = $minimo;
                }
            }
        }

        return $minimos === [] ? PHP_INT_MAX : max(1, min($minimos));
    }

    private static function entero(mixed $valor): ?int
    {
        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }
}
