<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Services\WhatsApp\WhatsAppService;

class ClienteObserver
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function created(Cliente $cliente): void
    {
        $tel = trim((string) ($cliente->telefono ?? ''));
        if ($tel === '') {
            return;
        }

        $this->sync($cliente, null, $tel);
    }

    public function updated(Cliente $cliente): void
    {
        if (! $cliente->wasChanged('telefono')) {
            return;
        }

        $this->sync(
            $cliente,
            $cliente->getOriginal('telefono'),
            $cliente->telefono,
        );
    }

    private function sync(Cliente $cliente, mixed $anterior, mixed $nuevo): void
    {
        $nombreIsp = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));

        try {
            $this->whatsapp->sincronizarTelefonoCliente(
                (int) $cliente->cliente_id,
                $anterior !== null && $anterior !== '' ? (string) $anterior : null,
                $nuevo !== null && trim((string) $nuevo) !== '' ? (string) $nuevo : null,
                $nombreIsp !== '' ? $nombreIsp : null,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
