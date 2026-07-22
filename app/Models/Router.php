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
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
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
