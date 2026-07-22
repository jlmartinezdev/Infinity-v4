<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    use Auditable;

    protected $table = 'olts';

    protected $primaryKey = 'olt_id';

    protected $fillable = [
        'nodo_id',
        'marca',
        'codigo',
        'modelo',
        'ip',
        'gestion_usuario',
        'gestion_password',
        'gestion_protocolo',
        'gestion_puerto',
        'gestion_enable_password',
        'mac_cli_comandos',
        'cantidad_puerto',
        'tipo_pon',
        'estado',
        'notas',
        'onus_synced_at',
        'onus_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_puerto' => 'integer',
            'gestion_puerto' => 'integer',
            'onus_synced_at' => 'datetime',
            'mac_cli_comandos' => 'array',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function modeloCatalogo(): BelongsTo
    {
        return $this->belongsTo(OltModelo::class, 'modelo', 'slug');
    }

    public function oltPuertos(): HasMany
    {
        return $this->hasMany(OltPuerto::class, 'olt_id', 'olt_id');
    }

    public function salidaPons(): HasMany
    {
        return $this->hasMany(SalidaPon::class, 'olt_id', 'olt_id');
    }

    public function onus(): HasMany
    {
        return $this->hasMany(OltOnu::class, 'olt_id', 'olt_id');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(RouterIpPool::class, 'olt_id', 'olt_id');
    }

    public function gestionPuertoEfectivo(): int
    {
        if ($this->gestion_puerto) {
            return (int) $this->gestion_puerto;
        }

        return ($this->gestion_protocolo ?? 'telnet') === 'ssh'
            ? (int) config('olt.vsol.default_ssh_port', 22)
            : (int) config('olt.vsol.default_telnet_port', 23);
    }

    public function gestionUsuarioEfectivo(): string
    {
        return trim((string) ($this->gestion_usuario ?: config('olt.vsol.default_user', 'admin')));
    }

    public function tieneCredencialesGestion(): bool
    {
        return filled($this->ip) && filled($this->gestion_password);
    }

    /**
     * Comandos CLI de búsqueda MAC efectivos (custom del OLT o defaults de config).
     *
     * @return array{address: list<string>, tabla: list<string>, pon: list<string>, interface: list<string>}
     */
    public function macCliComandosEfectivos(): array
    {
        $defaults = config('olt.vsol.mac_cli_comandos', []);
        $custom = is_array($this->mac_cli_comandos) ? $this->mac_cli_comandos : [];

        $out = [];
        foreach (['address', 'tabla', 'pon', 'interface'] as $key) {
            $lista = $custom[$key] ?? null;
            if (is_array($lista) && $lista !== []) {
                $out[$key] = array_values(array_filter(array_map('strval', $lista), fn ($c) => trim($c) !== ''));
            } else {
                $out[$key] = array_values($defaults[$key] ?? []);
            }
        }

        return $out;
    }

    /**
     * Texto multilínea para el formulario (una línea = un comando).
     */
    public function macCliComandosTexto(string $seccion): string
    {
        $custom = is_array($this->mac_cli_comandos) ? $this->mac_cli_comandos : [];
        $lista = $custom[$seccion] ?? null;
        if (! is_array($lista) || $lista === []) {
            return '';
        }

        return implode("\n", array_map('strval', $lista));
    }

    public function getRouteKeyName(): string
    {
        return 'olt_id';
    }
}
