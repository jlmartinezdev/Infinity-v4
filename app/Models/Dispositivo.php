<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = [
        'cliente_id',
        'device_key',
        'nombre',
        'app_version',
        'app_activa',
        'last_seen',
        'last_login',
    ];

    protected function casts(): array
    {
        return [
            'app_activa' => 'boolean',
            'last_seen' => 'datetime',
            'last_login' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public static function deviceKeyFromName(?string $deviceName): string
    {
        $name = trim((string) $deviceName);
        if ($name === '') {
            return 'default';
        }

        return substr(hash('sha256', mb_strtolower($name)), 0, 40);
    }

    /**
     * ISO 8601 con offset de app timezone (p.ej. 2026-08-19T23:40:00-03:00).
     */
    public static function formatIso(?\DateTimeInterface $dt): ?string
    {
        if (! $dt) {
            return null;
        }

        return \Carbon\Carbon::parse($dt)
            ->timezone(config('app.timezone', 'America/Asuncion'))
            ->format('Y-m-d\TH:i:sP');
    }
}
