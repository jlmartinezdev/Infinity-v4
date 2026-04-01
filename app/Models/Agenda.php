<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agenda extends Model
{
    use Auditable;
    protected $table = 'agenda';

    protected $fillable = [
        'tipo',
        'titulo',
        'cliente_id',
        'pedido_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'usuario_id',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * Relación con Cliente (cuando la cita se crea desde ticket)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    /**
     * Relación con Pedido
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'pedido_id');
    }

    /**
     * Relación con Usuario (técnico asignado)
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Tipos de agenda
     */
    public static function tipos(): array
    {
        return [
            'pedido' => 'Instalación (pedido)',
            'general' => 'General',
        ];
    }

    /**
     * Estados válidos de agenda
     */
    public static function estados(): array
    {
        return [
            'programado' => 'Programado',
            'en_progreso' => 'En progreso',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado',
            'no_asistio' => 'No asistió',
        ];
    }

    /**
     * Descripción para mostrar en listado/calendario
     */
    public function getDescripcionCortaAttribute(): string
    {
        if ($this->tipo === 'pedido' && $this->pedido) {
            $cliente = $this->pedido->cliente;
            $nombre = $cliente ? trim($cliente->nombre . ' ' . $cliente->apellido) : '';
            return '#' . $this->pedido_id . ($nombre ? ' — ' . $nombre : '');
        }
        if ($this->cliente) {
            $nombre = trim($this->cliente->nombre . ' ' . $this->cliente->apellido);
            return ($this->titulo ?? 'Cita sin título') . ($nombre ? ' — ' . $nombre : '');
        }
        return $this->titulo ?? 'Cita sin título';
    }

    /**
     * URL de Google Maps para la ubicación del cliente/pedido (null si no hay).
     * Prioriza lat/lon para que el mapa muestre la ubicación exacta.
     */
    public function getUbicacionMapsUrlAttribute(): ?string
    {
        if ($this->pedido) {
            $lat = $this->pedido->lat;
            $lon = $this->pedido->lon;
            if ($lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon)) {
                return 'https://www.google.com/maps?q=' . (float) $lat . ',' . (float) $lon;
            }
            $gps = trim((string) ($this->pedido->maps_gps ?? ''));
            if ($gps !== '') {
                return $this->buildMapsUrlFromString($gps);
            }
            if (!empty($this->pedido->ubicacion)) {
                return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->pedido->ubicacion);
            }
        }
        if ($this->cliente) {
            if (!empty($this->cliente->url_ubicacion)) {
                $u = trim($this->cliente->url_ubicacion);
                return $this->buildMapsUrlFromString($u);
            }
            if (!empty($this->cliente->direccion)) {
                return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->cliente->direccion);
            }
        }
        return null;
    }

    /**
     * Construye URL de Google Maps desde un string (URL, "lat,lon" o dirección).
     */
    private function buildMapsUrlFromString(string $value): string
    {
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        $parts = preg_split('/[,;\s]+/', $value, 2, PREG_SPLIT_NO_EMPTY);
        if (count($parts) >= 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
            return 'https://www.google.com/maps?q=' . (float) trim($parts[0]) . ',' . (float) trim($parts[1]);
        }
        return 'https://www.google.com/maps?q=' . rawurlencode($value);
    }
}
