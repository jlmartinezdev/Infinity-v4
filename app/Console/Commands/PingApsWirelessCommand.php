<?php

namespace App\Console\Commands;

use App\Models\NodoApWireless;
use App\Services\Monitoreo\ApWirelessPingStatusService;
use App\Services\Monitoreo\PingExecutor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PingApsWirelessCommand extends Command
{
    protected $signature = 'aps-wireless:ping
                            {--ap= : Solo un ap_id}';

    protected $description = 'Ping ICMP a los APs wireless (airOS) y avisa por WhatsApp si no responden.';

    public function handle(PingExecutor $ping, ApWirelessPingStatusService $status): int
    {
        $query = NodoApWireless::query()->where('activo', true)->orderBy('ap_id');
        if ($this->option('ap') !== null && $this->option('ap') !== '') {
            $query->where('ap_id', (int) $this->option('ap'));
        }

        $aps = $query->get();
        if ($aps->isEmpty()) {
            $this->warn('No hay APs wireless activos para pingear.');

            return self::SUCCESS;
        }

        $online = 0;
        $offline = 0;
        $omitidos = 0;
        $alertas = 0;

        foreach ($aps as $ap) {
            if (! $ping->ipEsPinguable($ap->ip)) {
                $omitidos++;
                $status->aplicarResultado($ap, ['ok' => false, 'latency_ms' => null, 'error' => 'IP inválida'], true);
                $this->line("  #{$ap->ap_id} {$ap->nombre}: IP inválida");
                continue;
            }

            $result = $ping->ping($ap->ip);
            $antesAlerta = (bool) ($ap->ping_alerta_enviada ?? false);
            $status->aplicarResultado($ap, $result, false);
            $ap->refresh();

            if ($result['ok']) {
                $online++;
                $lat = $result['latency_ms'] !== null ? " {$result['latency_ms']}ms" : '';
                $this->info("  #{$ap->ap_id} {$ap->nombre} ({$ap->ip}): OK{$lat}");
            } else {
                $offline++;
                $this->warn("  #{$ap->ap_id} {$ap->nombre} ({$ap->ip}): ".$ap->ping_error);
                if (! $antesAlerta && (bool) ($ap->ping_alerta_enviada ?? false)) {
                    $alertas++;
                }
            }
        }

        Log::info('[aps-wireless:ping] ronda', [
            'online' => $online,
            'offline' => $offline,
            'omitidos' => $omitidos,
            'alertas_whatsapp' => $alertas,
            'total' => $aps->count(),
        ]);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total', $aps->count()],
                ['En línea', $online],
                ['Sin respuesta', $offline],
                ['Omitidos', $omitidos],
                ['Alertas WhatsApp', $alertas],
            ]
        );

        return self::SUCCESS;
    }
}
