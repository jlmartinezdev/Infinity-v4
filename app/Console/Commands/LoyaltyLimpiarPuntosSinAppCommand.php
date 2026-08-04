<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\ClientePuntos;
use App\Models\PuntosMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoyaltyLimpiarPuntosSinAppCommand extends Command
{
    protected $signature = 'loyalty:limpiar-puntos-sin-app
                            {--dry-run : Solo muestra conteos, no borra}';

    protected $description = 'Borra puntos de clientes sin alta app (fecha_otorgamiento) + app activa; alinea Loyalty con Solicitudes';

    public function handle(): int
    {
        $idsConDatos = PuntosMovimiento::query()->distinct()->pluck('cliente_id')
            ->merge(ClientePuntos::query()->pluck('cliente_id'))
            ->unique()
            ->values();

        // Elegibles: misma regla que PuntosService::clienteElegiblePuntosPorApp
        $idsElegibles = Cliente::query()
            ->whereIn('cliente_id', $idsConDatos)
            ->where('app_activa', true)
            ->whereNotNull('fecha_otorgamiento')
            ->whereHas('usuarioPortal', fn ($q) => $q->where('estado', 'activo'))
            ->pluck('cliente_id');

        $idsBorrar = $idsConDatos->diff($idsElegibles)->values();

        $movs = PuntosMovimiento::query()->whereIn('cliente_id', $idsBorrar)->count();
        $saldos = ClientePuntos::query()->whereIn('cliente_id', $idsBorrar)->count();
        $ptsSum = (int) PuntosMovimiento::query()
            ->whereIn('cliente_id', $idsBorrar)
            ->where('puntos', '>', 0)
            ->sum('puntos');

        $this->info("Clientes elegibles Loyalty (alta + app activa): {$idsElegibles->count()}");
        $this->info("Clientes a limpiar: {$idsBorrar->count()}");
        $this->info("Movimientos a borrar: {$movs}");
        $this->info("Cuentas de saldo a borrar: {$saldos}");
        $this->info("Puntos crédito a anular (suma +): {$ptsSum}");

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se modificó nada.');

            return self::SUCCESS;
        }

        if ($idsBorrar->isEmpty()) {
            $this->info('Nada que limpiar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($idsBorrar) {
            PuntosMovimiento::query()->whereIn('cliente_id', $idsBorrar)->delete();
            ClientePuntos::query()->whereIn('cliente_id', $idsBorrar)->delete();
        });

        $quedanClientes = ClientePuntos::query()->where('saldo', '>', 0)->count();
        $quedanMovs = PuntosMovimiento::query()->count();
        $this->info("Listo. Cuentas con saldo > 0: {$quedanClientes}. Movimientos restantes: {$quedanMovs}.");

        return self::SUCCESS;
    }
}
