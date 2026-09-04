<?php

namespace App\Models;

use App\Support\MikrotikModelosCatalogo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    use Auditable;

    public const ESTADO_CONECTADO = 'conectado';

    public const ESTADO_DESCONECTADO = 'desconectado';

    public const ESTADO_DESCONOCIDO = 'desconocido';

    protected $table = 'routers';

    protected $primaryKey = 'router_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'nodo_id',
        'nombre',
        'modelo',
        'ip',
        'ip_loopback',
        'hotspot_servidor',
        'api_port',
        'usuario',
        'password',
        'webhook_token',
        'estado',
        'ping_latencia_ms',
        'ping_at',
        'ping_caido_desde',
        'ping_fallos_seguidos',
        'ping_alerta_enviada',
    ];

    public function estaConectado(): bool
    {
        return strtolower((string) $this->estado) === self::ESTADO_CONECTADO;
    }

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'ping_latencia_ms' => 'integer',
            'ping_at' => 'datetime',
            'ping_caido_desde' => 'datetime',
            'ping_fallos_seguidos' => 'integer',
            'ping_alerta_enviada' => 'boolean',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function routerIpPools(): HasMany
    {
        return $this->hasMany(RouterIpPool::class, 'router_id', 'router_id');
    }

    public function scriptsOrigen(): HasMany
    {
        return $this->hasMany(RouterScript::class, 'router_origen_id', 'router_id');
    }

    public function schedulersOrigen(): HasMany
    {
        return $this->hasMany(RouterScheduler::class, 'router_origen_id', 'router_id');
    }

    public function getRouteKeyName(): string
    {
        return 'router_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }

    /**
     * @return array{nombre: string, serie: string, imagen: string, descripcion: string, slug: string, imagen_url?: string}|null
     */
    public function modeloCatalogo(): ?array
    {
        return MikrotikModelosCatalogo::find($this->modelo);
    }

    public function modeloEtiqueta(): string
    {
        return $this->modeloCatalogo()['nombre'] ?? ($this->modelo ?: 'Sin modelo');
    }

    public function imagenUrl(): string
    {
        return MikrotikModelosCatalogo::imagenUrl($this->modelo);
    }
}
