<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class LineaCable extends Model
{
    use Auditable;

    /**
     * Estándar TIA-598-C: Código internacional de colores de fibra óptica.
     */
    public const COLORES_TIA598 = [
        1  => ['pos' => 1,  'nombre' => 'Azul',     'hex' => '#0055FF', 'clase_bg' => 'bg-blue-600',    'texto' => 'text-white'],
        2  => ['pos' => 2,  'nombre' => 'Naranja',  'hex' => '#FF7A00', 'clase_bg' => 'bg-orange-500',  'texto' => 'text-white'],
        3  => ['pos' => 3,  'nombre' => 'Verde',    'hex' => '#00A859', 'clase_bg' => 'bg-emerald-600', 'texto' => 'text-white'],
        4  => ['pos' => 4,  'nombre' => 'Marrón',   'hex' => '#8B4513', 'clase_bg' => 'bg-amber-900',   'texto' => 'text-white'],
        5  => ['pos' => 5,  'nombre' => 'Gris',     'hex' => '#808080', 'clase_bg' => 'bg-gray-500',    'texto' => 'text-white'],
        6  => ['pos' => 6,  'nombre' => 'Blanco',   'hex' => '#F8F9FA', 'clase_bg' => 'bg-white',       'texto' => 'text-gray-900 border border-gray-400'],
        7  => ['pos' => 7,  'nombre' => 'Rojo',     'hex' => '#E52222', 'clase_bg' => 'bg-red-600',     'texto' => 'text-white'],
        8  => ['pos' => 8,  'nombre' => 'Negro',    'hex' => '#1A1A1A', 'clase_bg' => 'bg-gray-950',    'texto' => 'text-white border border-gray-700'],
        9  => ['pos' => 9,  'nombre' => 'Amarillo', 'hex' => '#FFD700', 'clase_bg' => 'bg-yellow-400',  'texto' => 'text-gray-900'],
        10 => ['pos' => 10, 'nombre' => 'Violeta',  'hex' => '#8A2BE2', 'clase_bg' => 'bg-purple-600',  'texto' => 'text-white'],
        11 => ['pos' => 11, 'nombre' => 'Rosa',     'hex' => '#FF69B4', 'clase_bg' => 'bg-pink-500',    'texto' => 'text-white'],
        12 => ['pos' => 12, 'nombre' => 'Aqua',     'hex' => '#00CED1', 'clase_bg' => 'bg-cyan-500',    'texto' => 'text-gray-900'],
    ];

    public const TIPOS_CABLE = [
        'troncal'      => 'Troncal / Feeder',
        'distribucion' => 'Distribución',
        'drop'         => 'Drop / Acometida',
    ];

    public const ESTADOS = [
        'activo'        => 'Activo / Operativo',
        'proyectado'    => 'Proyectado / Obra',
        'mantenimiento' => 'En Mantenimiento',
        'danado'        => 'Dañado / Corte',
    ];

    public const CAPACIDADES_ESTANDAR = [2, 4, 6, 12, 24, 48, 72, 96, 144];

    public const ESTADOS_PELO = ['libre', 'iluminado', 'reservado', 'danado'];

    /**
     * Tipos de punto que pueden ser origen o destino del cable, con su modelo.
     */
    public const EXTREMOS = [
        'nodo'                => Nodo::class,
        'caja_nap'            => CajaNap::class,
        'splitter_primario'   => SplitterPrimario::class,
        'splitter_secundario' => SplitterSecundario::class,
        'salida_pon'          => SalidaPon::class,
        'linea_cable'         => self::class,
        'caja_fusion'         => CajaFusion::class,
    ];

    protected $table = 'linea_cables';

    protected $primaryKey = 'linea_cable_id';

    protected $fillable = [
        'nombre',
        'fibra_color_id',
        'tipo_cable',
        'cantidad_hilos',
        'estado',
        'origen_tipo',
        'origen_id',
        'destino_tipo',
        'destino_id',
        'longitud_metros',
        'coordenadas',
        'hilos_detalle',
        'notas',
    ];

    /** Pelos ya armados y la clave de los datos con los que se armaron. */
    protected ?array $pelosCache = null;

    protected ?string $pelosCacheKey = null;

    /** Origen/destino ya buscados (o precargados): prefijo => [tipo, id, modelo]. */
    protected array $extremosCache = [];

    protected function casts(): array
    {
        return [
            'cantidad_hilos'  => 'integer',
            'longitud_metros' => 'float',
            'coordenadas'     => 'array',
            'hilos_detalle'   => 'array',
        ];
    }

    public function fibraColor(): BelongsTo
    {
        return $this->belongsTo(FibraColor::class, 'fibra_color_id', 'fibra_color_id');
    }

    /**
     * Devuelve la lista estructurada de todos los pelos/hilos del cable.
     * Si no se han guardado detalles personalizados, los genera automáticamente
     * siguiendo la norma TIA-598 de tubos y pelos de 12 colores.
     */
    public function getPelosAttribute(): array
    {
        $cacheKey = $this->cantidad_hilos.'|'.($this->attributes['hilos_detalle'] ?? '');
        if ($this->pelosCache !== null && $this->pelosCacheKey === $cacheKey) {
            return $this->pelosCache;
        }

        $guardados = $this->hilos_detalle;
        $total = $this->cantidad_hilos > 0 ? (int) $this->cantidad_hilos : 12;

        $indexados = [];
        if (is_array($guardados)) {
            foreach ($guardados as $p) {
                if (isset($p['numero'])) {
                    $indexados[(int) $p['numero']] = $p;
                }
            }
        }

        $pelos = [];
        for ($i = 1; $i <= $total; $i++) {
            $tuboNum = (int) ceil($i / 12);
            $posTubo = (($tuboNum - 1) % 12) + 1;
            $posPelo = (($i - 1) % 12) + 1;
            $saved = $indexados[$i] ?? null;
            $colorPos = isset($saved['color_pos']) ? (int) $saved['color_pos'] : $posPelo;
            if (! isset(self::COLORES_TIA598[$colorPos])) {
                $colorPos = $posPelo;
            }

            $colorTuboInfo = self::COLORES_TIA598[$posTubo] ?? self::COLORES_TIA598[1];
            $colorPeloInfo = self::COLORES_TIA598[$colorPos] ?? self::COLORES_TIA598[1];

            $estado = $saved['estado'] ?? 'libre';

            $pelos[] = [
                'numero'          => $i,
                'tubo'            => $tuboNum,
                'color_tubo'      => $colorTuboInfo['nombre'],
                'color_tubo_hex'  => $colorTuboInfo['hex'],
                'pelo_en_tubo'    => $posPelo,
                'color_pos'       => $colorPos,
                'color_pelo'      => $colorPeloInfo['nombre'],
                'color_pelo_hex'  => $colorPeloInfo['hex'],
                'clase_bg'        => $colorPeloInfo['clase_bg'],
                'texto'           => $colorPeloInfo['texto'],
                'estado'          => in_array($estado, self::ESTADOS_PELO, true) ? $estado : 'libre',
                'uso'             => $saved['uso'] ?? '',
                'destino_detalle' => $saved['destino_detalle'] ?? '',
                'atenuacion_db'   => $saved['atenuacion_db'] ?? null,
            ];
        }

        $this->pelosCacheKey = $cacheKey;

        return $this->pelosCache = $pelos;
    }

    /**
     * Cantidad de pelos por estado: ['libre' => n, 'iluminado' => n, 'reservado' => n, 'danado' => n].
     */
    public function conteoPelos(): array
    {
        $conteo = array_fill_keys(self::ESTADOS_PELO, 0);
        foreach ($this->pelos as $p) {
            $conteo[$p['estado']]++;
        }

        return $conteo;
    }

    public function pelosIluminadosCount(): int
    {
        return $this->conteoPelos()['iluminado'];
    }

    public function pelosLibresCount(): int
    {
        return $this->conteoPelos()['libre'];
    }

    public function pelosReservadosCount(): int
    {
        return $this->conteoPelos()['reservado'];
    }

    public function pelosDanadosCount(): int
    {
        return $this->conteoPelos()['danado'];
    }

    public function porcentajeOcupacion(): float
    {
        $total = $this->cantidad_hilos > 0 ? (int) $this->cantidad_hilos : 12;

        return round(($this->pelosIluminadosCount() / $total) * 100, 1);
    }

    /**
     * Busca de una vez los orígenes y destinos de varios cables (una consulta por tipo)
     * para no hacer dos consultas por cada fila del listado o del mapa.
     */
    public static function precargarExtremos(iterable $lineas): void
    {
        $lineas = Collection::make($lineas);

        $idsPorTipo = [];
        foreach ($lineas as $l) {
            foreach (['origen', 'destino'] as $prefix) {
                $tipo = $l->{"{$prefix}_tipo"};
                $id = (int) $l->{"{$prefix}_id"};
                if (isset(self::EXTREMOS[$tipo]) && $id > 0) {
                    $idsPorTipo[$tipo][$id] = $id;
                }
            }
        }

        $encontrados = [];
        foreach ($idsPorTipo as $tipo => $ids) {
            $class = self::EXTREMOS[$tipo];
            $encontrados[$tipo] = $class::whereIn((new $class)->getKeyName(), array_values($ids))
                ->get()
                ->keyBy(fn ($m) => (int) $m->getKey());
        }

        foreach ($lineas as $l) {
            foreach (['origen', 'destino'] as $prefix) {
                $tipo = $l->{"{$prefix}_tipo"};
                $id = (int) $l->{"{$prefix}_id"};
                $l->extremosCache[$prefix] = [$tipo, $id, $encontrados[$tipo][$id] ?? null];
            }
        }
    }

    public function getOrigenModelAttribute()
    {
        return $this->resolvePolymorphic('origen');
    }

    public function getDestinoModelAttribute()
    {
        return $this->resolvePolymorphic('destino');
    }

    public function getOrigenEtiquetaAttribute(): string
    {
        return $this->formatearEtiqueta($this->origen_tipo, $this->origen_model, $this->origen_id);
    }

    public function getDestinoEtiquetaAttribute(): string
    {
        return $this->formatearEtiqueta($this->destino_tipo, $this->destino_model, $this->destino_id);
    }

    protected function formatearEtiqueta(?string $tipo, $model, $id): string
    {
        if (! $tipo) return 'Sin definir';
        if (! $model) return ucfirst(str_replace('_', ' ', $tipo)).' #'.$id;

        return match ($tipo) {
            'nodo'                => 'Nodo: '.$model->descripcion,
            'caja_nap'            => 'NAP: '.$model->codigo,
            'salida_pon'          => 'Salida PON: '.$model->codigo,
            'splitter_primario'   => 'Splitter 1º: '.$model->codigo,
            'splitter_secundario' => 'Splitter 2º: '.$model->codigo,
            'linea_cable'         => 'Cable: '.($model->nombre ?: '#'.$id),
            'caja_fusion'         => 'Fusión: '.($model->codigo ?: '#'.$id),
            default               => ucfirst($tipo).' #'.$id,
        };
    }

    public function getOrigenUrlAttribute(): ?string
    {
        return $this->resolverUrl($this->origen_tipo, $this->origen_id);
    }

    public function getDestinoUrlAttribute(): ?string
    {
        return $this->resolverUrl($this->destino_tipo, $this->destino_id);
    }

    protected function resolverUrl(?string $tipo, $id): ?string
    {
        if (! $tipo || ! $id) return null;

        return match ($tipo) {
            'caja_nap'    => route('sistema.cajas-nap.show', $id),
            'salida_pon'  => route('sistema.salida-pons.index', ['q' => $id]),
            'linea_cable' => route('sistema.lineas-cable.edit', $id),
            'caja_fusion' => route('sistema.lineas-cable.index').'#cajas-fusion',
            default       => null,
        };
    }

    protected function resolvePolymorphic(string $prefix)
    {
        $tipo = $this->{"{$prefix}_tipo"};
        $id = (int) $this->{"{$prefix}_id"};
        if (! $tipo || ! $id) {
            return null;
        }

        $cache = $this->extremosCache[$prefix] ?? null;
        if ($cache && $cache[0] === $tipo && $cache[1] === $id) {
            return $cache[2];
        }

        $class = self::EXTREMOS[$tipo] ?? null;
        $model = $class ? $class::find($id) : null;
        $this->extremosCache[$prefix] = [$tipo, $id, $model];

        return $model;
    }

    public function getRouteKeyName(): string
    {
        return 'linea_cable_id';
    }
}
