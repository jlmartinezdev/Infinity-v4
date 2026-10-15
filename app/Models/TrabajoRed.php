<?php

namespace App\Models;

use App\Helpers\MapsUrlHelper;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class TrabajoRed extends Model
{
    use Auditable;

    protected $table = 'trabajos_red';

    protected $fillable = [
        'tipo',
        'titulo',
        'descripcion',
        'ubicacion_desde',
        'url_ubicacion_desde',
        'ubicacion_hasta',
        'url_ubicacion_hasta',
        'observaciones',
        'estado',
        'prioridad',
        'nodo_id',
        'activo_tipo',
        'activo_id',
        'usuario_id',
        'asignado_id',
        'actualizado_por_id',
        'imagen',
        'fecha_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
        ];
    }

    /**
     * @return array<string, class-string>
     */
    public static function activoClases(): array
    {
        return [
            'nodo' => Nodo::class,
            'olt' => Olt::class,
            'router' => Router::class,
            'caja_nap' => CajaNap::class,
            'salida_pon' => SalidaPon::class,
            'ap' => NodoApWireless::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function activoTipos(): array
    {
        return [
            'nodo' => 'Nodo',
            'olt' => 'OLT',
            'router' => 'Router',
            'caja_nap' => 'Caja NAP',
            'salida_pon' => 'Salida PON',
            'ap' => 'AP wireless',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            'mantenimiento' => 'Mantenimiento',
            'mejora' => 'Mejora',
            'ampliacion' => 'Ampliación',
            'reparo' => 'Reparo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'en_camino' => 'En camino',
            'en_proceso' => 'En proceso',
            'resuelto' => 'Resuelto',
            'no_realizado' => 'No realizado',
            'cerrado' => 'Cerrado',
            'cancelado' => 'Cancelado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function prioridades(): array
    {
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ];
    }

    /**
     * @return list<string>
     */
    public static function estadosAbiertos(): array
    {
        return ['pendiente', 'en_camino', 'en_proceso'];
    }

    /**
     * @return list<string>
     */
    public static function estadosCerrados(): array
    {
        return ['resuelto', 'cerrado', 'cancelado', 'no_realizado'];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_id', 'usuario_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id', 'usuario_id');
    }

    public function getActivoAttribute(): ?Model
    {
        return $this->resolverActivo($this->activo_tipo, $this->activo_id);
    }

    public static function resolverActivo(?string $tipo, mixed $id): ?Model
    {
        $tipo = (string) $tipo;
        $id = (int) $id;
        if ($tipo === '' || $id < 1) {
            return null;
        }
        $class = self::activoClases()[$tipo] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    public static function nodoIdDesdeModelo(?Model $activo): ?int
    {
        if (! $activo) {
            return null;
        }
        if ($activo instanceof Nodo) {
            return (int) $activo->nodo_id;
        }
        $nodoId = $activo->getAttribute('nodo_id');

        return $nodoId !== null ? (int) $nodoId : null;
    }

    public static function etiquetaDeModelo(?Model $activo, string $tipo): string
    {
        if (! $activo) {
            return '—';
        }

        return match ($tipo) {
            'nodo' => (string) ($activo->descripcion ?: 'Nodo #'.$activo->getKey()),
            'olt' => (string) ($activo->codigo ?: ($activo->ip ?: 'OLT #'.$activo->getKey())),
            'router' => (string) ($activo->nombre ?: ($activo->ip ?: 'Router #'.$activo->getKey())),
            'caja_nap' => (string) ($activo->codigo ?: ($activo->descripcion ?: 'NAP #'.$activo->getKey())),
            'salida_pon' => (string) ($activo->codigo ?: ('PON #'.$activo->getKey())),
            'ap' => (string) ($activo->nombre ?: ($activo->ip ?: 'AP #'.$activo->getKey())),
            default => '#'.$activo->getKey(),
        };
    }

    public function etiquetaActivo(): string
    {
        return self::etiquetaDeModelo($this->activo, (string) $this->activo_tipo);
    }

    public function urlActivo(): ?string
    {
        $activo = $this->activo;
        if (! $activo) {
            return null;
        }

        return match ($this->activo_tipo) {
            'nodo' => Route::has('nodos.edit') ? route('nodos.edit', $activo) : null,
            'olt' => Route::has('sistema.olts.show') ? route('sistema.olts.show', $activo) : null,
            'router' => Route::has('sistema.routers.edit') ? route('sistema.routers.edit', $activo) : null,
            'caja_nap' => Route::has('sistema.cajas-nap.show') ? route('sistema.cajas-nap.show', $activo) : null,
            'salida_pon' => Route::has('sistema.salida-pons.edit') ? route('sistema.salida-pons.edit', $activo) : null,
            'ap' => Route::has('sistema.aps-wireless.index') ? route('sistema.aps-wireless.index') : null,
            default => null,
        };
    }

    public function etiquetaTipo(): string
    {
        return self::tipos()[$this->tipo] ?? (string) $this->tipo;
    }

    public function etiquetaEstado(): string
    {
        return self::estados()[$this->estado] ?? (string) $this->estado;
    }

    public function etiquetaPrioridad(): string
    {
        return self::prioridades()[$this->prioridad] ?? (string) $this->prioridad;
    }

    public function claseBadgeEstado(): string
    {
        return match ($this->estado) {
            'pendiente' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            'en_camino' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
            'en_proceso' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            'resuelto' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            'no_realizado' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
            'cerrado' => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200',
            'cancelado' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    public function claseBadgeTipo(): string
    {
        return match ($this->tipo) {
            'mantenimiento' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200 border border-amber-200 dark:border-amber-800',
            'mejora' => 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800',
            'ampliacion' => 'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200 border border-sky-200 dark:border-sky-800',
            'reparo' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200 border border-rose-200 dark:border-rose-800',
            default => 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700',
        };
    }

    public function claseBadgePrioridad(): string
    {
        return match ($this->prioridad) {
            'alta' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300 border border-red-200 dark:border-red-800',
            'media' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
            'baja' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600',
        };
    }

    public function textoWhatsapp(): string
    {
        $tipo = $this->etiquetaTipo();
        $prioridad = strtoupper($this->etiquetaPrioridad());
        $estado = strtoupper($this->etiquetaEstado());
        $activo = $this->etiquetaActivo();
        $tipoActivo = self::activoTipos()[$this->activo_tipo] ?? $this->activo_tipo;
        $nodo = $this->nodo?->descripcion ?? 'N/D';

        $msg = "🛠️ *ORDEN DE TRABAJO DE RED #{$this->id}*\n";
        $msg .= "------------------------------\n";
        $msg .= "📌 *Tipo:* {$tipo} ({$prioridad})\n";
        $msg .= "⚡ *Estado:* {$estado}\n";
        $msg .= "🎯 *Activo:* {$tipoActivo} - {$activo}\n";
        $msg .= "🏢 *Nodo:* {$nodo}\n";
        $msg .= "📝 *Título:* {$this->titulo}\n";

        if ($this->descripcion) {
            $msg .= "📋 *Detalle:* {$this->descripcion}\n";
        }

        if ($this->tieneTrazado()) {
            $desde = $this->etiquetaUbicacionDesde() ?: 'Coordenadas';
            $hasta = $this->etiquetaUbicacionHasta() ?: 'Coordenadas';
            $msg .= "📍 *Desde:* {$desde}\n";
            $msg .= "🏁 *Hasta:* {$hasta}\n";
            $ruta = $this->urlRutaMaps();
            if ($ruta) {
                $msg .= "🗺️ *Ruta Google Maps:* {$ruta}\n";
            }
        }

        if ($this->observaciones) {
            $msg .= "💬 *Observaciones:* {$this->observaciones}\n";
        }

        return $msg;
    }

    public function urlWhatsappDespacho(?string $telefono = null): string
    {
        $tel = preg_replace('/[^0-9]/', '', (string) ($telefono ?: $this->asignado?->telefono));
        $text = rawurlencode($this->textoWhatsapp());
        if ($tel !== '') {
            return "https://wa.me/{$tel}?text={$text}";
        }

        return "https://wa.me/?text={$text}";
    }

    public function etiquetaUbicacionDesde(): string
    {
        return trim((string) $this->ubicacion_desde);
    }

    public function etiquetaUbicacionHasta(): string
    {
        return trim((string) $this->ubicacion_hasta);
    }

    public function mapsUrlDesde(): ?string
    {
        return MapsUrlHelper::toGoogleMapsUrl($this->url_ubicacion_desde);
    }

    public function mapsUrlHasta(): ?string
    {
        return MapsUrlHelper::toGoogleMapsUrl($this->url_ubicacion_hasta);
    }

    public function tieneTrazado(): bool
    {
        return $this->etiquetaUbicacionDesde() !== ''
            || $this->etiquetaUbicacionHasta() !== ''
            || trim((string) $this->url_ubicacion_desde) !== ''
            || trim((string) $this->url_ubicacion_hasta) !== '';
    }

    public function urlRutaMaps(): ?string
    {
        $origen = $this->mapsUrlDesde();
        $destino = $this->mapsUrlHasta();
        if ($origen === null || $destino === null) {
            return null;
        }
        $o = MapsUrlHelper::extractLatLon((string) $this->url_ubicacion_desde);
        $d = MapsUrlHelper::extractLatLon((string) $this->url_ubicacion_hasta);
        if ($o['lat'] === null || $d['lat'] === null) {
            return 'https://www.google.com/maps/dir/?api=1&origin='.rawurlencode((string) $this->url_ubicacion_desde)
                .'&destination='.rawurlencode((string) $this->url_ubicacion_hasta);
        }

        return 'https://www.google.com/maps/dir/?api=1&origin='.$o['lat'].','.$o['lon']
            .'&destination='.$d['lat'].','.$d['lon'];
    }
}
