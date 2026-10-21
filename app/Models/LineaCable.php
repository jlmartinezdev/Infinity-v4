<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

            $colorTuboInfo = self::COLORES_TIA598[$posTubo] ?? self::COLORES_TIA598[1];
            $colorPeloInfo = self::COLORES_TIA598[$posPelo] ?? self::COLORES_TIA598[1];

            $saved = $indexados[$i] ?? null;

            $pelos[] = [
                'numero'          => $i,
                'tubo'            => $tuboNum,
                'color_tubo'      => $colorTuboInfo['nombre'],
                'color_tubo_hex'  => $colorTuboInfo['hex'],
                'pelo_en_tubo'    => $posPelo,
                'color_pelo'      => $colorPeloInfo['nombre'],
                'color_pelo_hex'  => $colorPeloInfo['hex'],
                'clase_bg'        => $colorPeloInfo['clase_bg'],
                'texto'           => $colorPeloInfo['texto'],
                'estado'          => $saved['estado'] ?? 'libre', // 'iluminado', 'libre', 'reservado', 'danado'
                'uso'             => $saved['uso'] ?? '',
                'destino_detalle' => $saved['destino_detalle'] ?? '',
                'atenuacion_db'   => $saved['atenuacion_db'] ?? null,
            ];
        }

        return $pelos;
    }

    public function pelosIluminadosCount(): int
    {
        return count(array_filter($this->pelos, fn ($p) => ($p['estado'] ?? '') === 'iluminado'));
    }

    public function pelosLibresCount(): int
    {
        return count(array_filter($this->pelos, fn ($p) => in_array($p['estado'] ?? '', ['libre', ''])));
    }

    public function pelosReservadosCount(): int
    {
        return count(array_filter($this->pelos, fn ($p) => ($p['estado'] ?? '') === 'reservado'));
    }

    public function pelosDanadosCount(): int
    {
        return count(array_filter($this->pelos, fn ($p) => ($p['estado'] ?? '') === 'danado'));
    }

    public function porcentajeOcupacion(): float
    {
        $total = $this->cantidad_hilos > 0 ? (int) $this->cantidad_hilos : 12;
        if ($total === 0) return 0.0;
        return round(($this->pelosIluminadosCount() / $total) * 100, 1);
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
            'caja_nap'   => route('sistema.cajas-nap.show', $id),
            'salida_pon' => route('sistema.salida-pons.index', ['q' => $id]),
            default      => null,
        };
    }

    protected function resolvePolymorphic(string $prefix)
    {
        $tipo = $this->{"{$prefix}_tipo"};
        $id = $this->{"{$prefix}_id"};
        if (! $tipo || ! $id) {
            return null;
        }
        $classMap = [
            'nodo'                => Nodo::class,
            'caja_nap'            => CajaNap::class,
            'splitter_primario'   => SplitterPrimario::class,
            'splitter_secundario' => SplitterSecundario::class,
            'salida_pon'          => SalidaPon::class,
        ];
        $class = $classMap[$tipo] ?? null;

        return $class ? $class::find($id) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'linea_cable_id';
    }
}
