<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalClienteAccion extends Model
{
    public const ORIGEN_APP = 'app';

    public const TIPO_WIFI_PASSWORD = 'wifi_password';

    public const TIPO_WIFI_SSID = 'wifi_ssid';

    public const TIPO_WIFI_SSID_PASSWORD = 'wifi_ssid_password';

    public $timestamps = false;

    protected $table = 'portal_cliente_acciones';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'tipo',
        'origen',
        'source',
        'titulo',
        'ssid',
        'wifi_id',
        'wifi_password',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $hidden = [
        'wifi_password',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'wifi_password' => 'encrypted',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_WIFI_PASSWORD => 'Clave Wi‑Fi',
            self::TIPO_WIFI_SSID => 'Nombre Wi‑Fi',
            self::TIPO_WIFI_SSID_PASSWORD => 'Nombre y clave Wi‑Fi',
            default => $this->tipo,
        };
    }
}
