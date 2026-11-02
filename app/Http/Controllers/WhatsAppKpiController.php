<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WaAgentKpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppKpiController extends Controller
{
    public function index(Request $request, WaAgentKpiService $kpi): View
    {
        $rango = $kpi->resolverPeriodo(
            $request->get('periodo'),
            $request->get('desde'),
            $request->get('hasta'),
        );
        $sandbox = $request->boolean('sandbox');

        $resumen = $kpi->resumen($rango['desde'], $rango['hasta'], $sandbox);
        $serie = $kpi->serieLineal($rango['desde'], $rango['hasta'], $rango['periodo'], $sandbox);
        $sla = $kpi->slaPrimeraRespuesta($rango['desde'], $rango['hasta'], $sandbox);
        $heatmap = $kpi->heatmap($rango['desde'], $rango['hasta'], $sandbox);
        $ventas = $kpi->ventasRecientes($rango['desde'], $rango['hasta'], 40, $sandbox);
        $eventos = $kpi->eventosRecientes($rango['desde'], $rango['hasta'], 60, $sandbox);

        return view('whatsapp.kpi', [
            'rango' => $rango,
            'resumen' => $resumen,
            'serie' => $serie,
            'sla' => $sla,
            'heatmap' => $heatmap,
            'ventas' => $ventas,
            'eventos' => $eventos,
            'incluirSandbox' => $sandbox,
            'fechaDia' => $rango['periodo'] === 'dia' ? $rango['desde']->toDateString() : null,
            'diasSemana' => ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
        ]);
    }

    public function export(Request $request, WaAgentKpiService $kpi): StreamedResponse
    {
        $rango = $kpi->resolverPeriodo(
            $request->get('periodo'),
            $request->get('desde'),
            $request->get('hasta'),
        );
        $sandbox = $request->boolean('sandbox');
        $filas = $kpi->exportFilas($rango['desde'], $rango['hasta'], $sandbox);

        $filename = 'wa-agent-kpi-'.$rango['periodo'].'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'ocurrido_at', 'tipo', 'modo', 'telefono', 'cliente_id',
                'ticket_id', 'pedido_id', 'cobro_id', 'latency_ms', 'meta',
            ]);
            foreach ($filas as $fila) {
                fputcsv($out, [
                    $fila['ocurrido_at'],
                    $fila['tipo'],
                    $fila['modo'],
                    $fila['telefono'],
                    $fila['cliente_id'],
                    $fila['ticket_id'],
                    $fila['pedido_id'],
                    $fila['cobro_id'],
                    $fila['latency_ms'],
                    $fila['meta'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
