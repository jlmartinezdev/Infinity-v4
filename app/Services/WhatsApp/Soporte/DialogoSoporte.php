<?php

namespace App\Services\WhatsApp\Soporte;

use Illuminate\Support\Facades\Cache;

class DialogoSoporte
{
    /**
     * @return array<string, mixed>
     */
    public function leer(string $telefono): array
    {
        $data = Cache::get($this->clave($telefono));

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardar(string $telefono, array $data): void
    {
        Cache::put($this->clave($telefono), $data, now()->endOfDay());
    }

    public function olvidar(string $telefono): void
    {
        Cache::forget($this->clave($telefono));
    }

    private function clave(string $telefono): string
    {
        $d = preg_replace('/\D+/', '', $telefono) ?: '0';

        return 'wa_soporte_dialogo_'.$d;
    }
}
