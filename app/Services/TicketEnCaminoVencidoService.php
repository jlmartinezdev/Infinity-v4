<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketEnCaminoVencidoService
{
    /**
     * Tickets en camino hace más de un día vuelven a pendiente, sin avisar al cliente.
     *
     * @return list<array{id: int, nota: string}>
     */
    public function devolverAPendiente(): array
    {
        $tickets = Ticket::query()->where('estado', 'en_camino')->get(['id', 'updated_at']);
        if ($tickets->isEmpty()) {
            return [];
        }

        $limite = now()->subDay();
        $ingreso = $this->ingresoEnCamino($tickets->pluck('id')->all());
        $devueltos = [];

        foreach ($tickets as $ticket) {
            $entro = $ingreso[$ticket->id] ?? $ticket->updated_at;
            if (! $entro instanceof CarbonInterface || ! $entro->lt($limite)) {
                continue;
            }

            $nota = null;
            DB::transaction(function () use ($ticket, &$nota) {
                $fresco = Ticket::query()
                    ->whereKey($ticket->id)
                    ->where('estado', 'en_camino')
                    ->lockForUpdate()
                    ->first();
                if (! $fresco) {
                    return;
                }

                $linea = self::textoNota(now());
                $previa = trim((string) $fresco->observaciones);
                $fresco->observaciones = $previa === '' ? $linea : $previa."\n".$linea;
                $fresco->estado = 'pendiente';
                $fresco->omitirAvisoCliente = true;
                $fresco->save();
                $nota = $linea;
            });

            if (is_string($nota) && $nota !== '') {
                $devueltos[] = ['id' => (int) $ticket->id, 'nota' => $nota];
            }
        }

        if ($devueltos !== []) {
            Log::info('[Tickets] en_camino devuelto a pendiente sin aviso', [
                'ids' => array_column($devueltos, 'id'),
            ]);
        }

        return $devueltos;
    }

    public static function textoNota(CarbonInterface $cuando): string
    {
        $fecha = Carbon::parse($cuando)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i');

        return 'Volvió a pendiente el '.$fecha.' porque llevaba más de un día en camino.';
    }

    /**
     * Último momento en que cada ticket pasó a en_camino.
     *
     * @param  list<int>  $ids
     * @return array<int, CarbonInterface>
     */
    private function ingresoEnCamino(array $ids): array
    {
        $filas = DB::table('auditoria')
            ->where('tabla', 'tickets')
            ->whereIn('registro_id', $ids)
            ->where('detalles', 'like', '%en_camino%')
            ->orderByDesc('created_at')
            ->get(['registro_id', 'created_at', 'detalles']);

        $desde = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->registro_id;
            if (isset($desde[$id])) {
                continue;
            }

            $det = json_decode((string) $fila->detalles, true);
            if (! is_array($det)) {
                continue;
            }

            $nuevo = (string) ($det['new']['estado'] ?? '');
            $anterior = (string) ($det['old']['estado'] ?? '');
            if ($nuevo !== 'en_camino' || $anterior === 'en_camino') {
                continue;
            }

            $desde[$id] = Carbon::parse($fila->created_at);
        }

        return $desde;
    }
}
