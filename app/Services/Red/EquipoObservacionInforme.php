<?php

namespace App\Services\Red;

use App\Models\ServicioConexionEvento;
use App\Models\ServicioObservacion;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EquipoObservacionInforme
{
    public const VEREDICTO_EN_CURSO = 'en_curso';

    public const VEREDICTO_ESTABLE = 'estable';

    public const VEREDICTO_REINCIDIO = 'reincidio';

    /**
     * Observaciones abiertas con cortes, señal y reclamos desde que empezó el plazo.
     *
     * @return list<array<string, mixed>>
     */
    public function filas(): array
    {
        $observaciones = ServicioObservacion::query()
            ->whereNull('cerrada_at')
            ->with(['servicio.cliente', 'servicio.plan', 'usuario'])
            ->orderBy('vence_at')
            ->limit(40)
            ->get();

        if ($observaciones->isEmpty()) {
            return [];
        }

        return $this->enriquecer($observaciones);
    }

    /**
     * @param  Collection<int, ServicioObservacion>  $observaciones
     * @return list<array<string, mixed>>
     */
    public function enriquecer(Collection $observaciones): array
    {
        $servicioIds = $observaciones->pluck('servicio_id')->map(fn ($id) => (int) $id)->unique()->values();
        $desde = $observaciones->min('inicia_at');
        $eventos = ServicioConexionEvento::query()
            ->whereIn('servicio_id', $servicioIds)
            ->where('ocurrio_at', '>=', $desde)
            ->whereIn('tipo', [
                ServicioConexionEvento::TIPO_PPPOE_DOWN,
                ServicioConexionEvento::TIPO_SENAL_OPTICA,
                ServicioConexionEvento::TIPO_SENAL_ANTENA,
            ])
            ->get(['servicio_id', 'tipo', 'ocurrio_at', 'rx_power_dbm', 'antena_signal_dbm']);

        $clienteIds = $observaciones
            ->map(fn (ServicioObservacion $obs) => $obs->servicio?->cliente_id)
            ->filter()
            ->unique()
            ->values();
        $tickets = $clienteIds->isEmpty()
            ? collect()
            : Ticket::query()
                ->whereIn('cliente_id', $clienteIds)
                ->where('created_at', '>=', $desde)
                ->where('estado', '!=', 'cancelado')
                ->get(['id', 'cliente_id', 'created_at', 'estado']);

        return $observaciones->map(function (ServicioObservacion $obs) use ($eventos, $tickets) {
            $inicio = $obs->inicia_at;
            $delServicio = $eventos->filter(function (ServicioConexionEvento $evento) use ($obs, $inicio) {
                return (int) $evento->servicio_id === (int) $obs->servicio_id
                    && $evento->ocurrio_at !== null
                    && $evento->ocurrio_at->greaterThanOrEqualTo($inicio);
            });
            $cortes = $delServicio->where('tipo', ServicioConexionEvento::TIPO_PPPOE_DOWN)->count();
            $rx = $delServicio
                ->where('tipo', ServicioConexionEvento::TIPO_SENAL_OPTICA)
                ->pluck('rx_power_dbm')
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (float) $v);
            $antena = $delServicio
                ->where('tipo', ServicioConexionEvento::TIPO_SENAL_ANTENA)
                ->pluck('antena_signal_dbm')
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (float) $v);
            $peorRx = $rx->isEmpty() ? null : $rx->min();
            $peorAntena = $antena->isEmpty() ? null : $antena->min();
            $clienteId = $obs->servicio?->cliente_id;
            $reclamos = $tickets->filter(function (Ticket $ticket) use ($clienteId, $inicio) {
                return $clienteId !== null
                    && (int) $ticket->cliente_id === (int) $clienteId
                    && $ticket->created_at !== null
                    && $ticket->created_at->greaterThanOrEqualTo($inicio);
            })->count();
            $vencido = $obs->vence_at !== null && $obs->vence_at->lte(now());
            $veredicto = self::veredicto($cortes, $peorRx, $peorAntena, $reclamos, $vencido);
            $cliente = $obs->servicio?->cliente;
            $nombre = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));

            return [
                'id' => $obs->id,
                'servicio_id' => (int) $obs->servicio_id,
                'cliente_id' => $cliente?->cliente_id,
                'nombre' => $nombre !== '' ? $nombre : 'Servicio #'.$obs->servicio_id,
                'plan' => $obs->servicio?->plan?->nombre,
                'ip' => $obs->servicio?->ip,
                'motivo' => ServicioObservacion::motivoEtiqueta((string) $obs->motivo),
                'equipo' => $obs->equipo,
                'nota' => $obs->nota,
                'tecnico' => $obs->usuario?->name,
                'inicia' => $obs->inicia_at,
                'vence' => $obs->vence_at,
                'plazo' => self::textoPlazo($obs->vence_at),
                'cortes' => $cortes,
                'peor_rx' => $peorRx,
                'peor_antena' => $peorAntena,
                'reclamos' => $reclamos,
                'veredicto' => $veredicto,
                'veredicto_texto' => self::veredictoTexto($veredicto),
            ];
        })->all();
    }

    public static function veredicto(int $cortes, ?float $rx, ?float $antena, int $reclamos, bool $vencido): string
    {
        $reincidio = $cortes >= 2
            || ($rx !== null && $rx < -27)
            || ($antena !== null && $antena < -75)
            || $reclamos >= 1;

        if ($reincidio) {
            return self::VEREDICTO_REINCIDIO;
        }
        if ($vencido) {
            return self::VEREDICTO_ESTABLE;
        }

        return self::VEREDICTO_EN_CURSO;
    }

    public static function veredictoTexto(string $veredicto): string
    {
        return match ($veredicto) {
            self::VEREDICTO_REINCIDIO => 'Volvió la falla',
            self::VEREDICTO_ESTABLE => 'El plazo pasó sin falla',
            default => 'En observación',
        };
    }

    public static function textoPlazo(?CarbonInterface $vence): string
    {
        if ($vence === null) {
            return '';
        }
        $minutos = (int) now()->diffInMinutes($vence, false);
        if ($minutos >= 0) {
            if ($minutos < 60) {
                return 'Faltan '.$minutos.' min';
            }
            $horas = intdiv($minutos, 60);
            if ($horas < 48) {
                return 'Faltan '.$horas.' h';
            }

            return 'Faltan '.intdiv($horas, 24).' días';
        }
        $pasados = abs($minutos);
        if ($pasados < 60) {
            return 'Venció hace '.$pasados.' min';
        }
        $horas = intdiv($pasados, 60);
        if ($horas < 48) {
            return 'Venció hace '.$horas.' h';
        }

        return 'Venció hace '.intdiv($horas, 24).' días';
    }
}
