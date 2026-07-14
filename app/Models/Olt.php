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
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
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

    public function getRouteKeyName(): string
    {
        return 'olt_id';
    }
}
