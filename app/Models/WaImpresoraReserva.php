<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaImpresoraReserva extends Model
{
    protected $table = 'wa_impresora_reservas';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'ticket_id',
        'ip',
        'mac',
        'hostname',
        'lease_fijo',
    ];

    protected function casts(): array
    {
        return [
            'lease_fijo' => 'boolean',
        ];
    }

    public function bat(): string
    {
        $ip = $this->ip;
        $nombre = 'Impresora';

        return "@echo off\r\n".
            "echo Preparando la impresora {$nombre}\r\n".
            "powershell -NoProfile -Command \"Add-PrinterPort -Name 'IP_{$ip}' -PrinterHostAddress '{$ip}'\"\r\n".
            "echo Listo. En Windows, agrega la impresora y elegi el puerto IP_{$ip}.\r\n".
            "pause\r\n";
    }
}
