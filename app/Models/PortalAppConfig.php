<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalAppConfig extends Model
{
    protected $table = 'portal_app_config';

    protected $fillable = [
        'flags',
        'pago_online',
        'referidos',
        'whatsapp',
        'resumen',
        'faqs',
    ];

    protected function casts(): array
    {
        return [
            'flags' => 'array',
            'pago_online' => 'array',
            'referidos' => 'array',
            'whatsapp' => 'array',
            'resumen' => 'array',
            'faqs' => 'array',
        ];
    }

    public static function obtener(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([]);
    }
}
