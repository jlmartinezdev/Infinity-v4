<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: separate; border-spacing: 6px; }
        td { width: 50%; vertical-align: top; }
        .ticket { border: 1px dotted #111; padding: 8px; page-break-inside: avoid; }
        .corte { letter-spacing: 1px; margin: 0 0 4px; }
        .titulo { font-weight: bold; text-align: center; }
        .numero { font-weight: bold; font-size: 16px; text-align: center; margin: 4px 0; }
    </style>
</head>
<body>
    <h1>{{ $campana->nombre }} — {{ $campana->fecha_sorteo?->format('d/m/Y') }}</h1>
    <table>
        @foreach(array_chunk($tickets, 2) as $par)
            <tr>
                @foreach($par as $ticket)
                    <td>
                        <div class="ticket">
                            <p class="corte">8&lt; . . . . . . . . . . . .</p>
                            <p class="titulo">{{ \Illuminate\Support\Str::ascii($campana->texto_ticket) }}</p>
                            <p class="titulo">{{ $campana->fecha_sorteo?->format('d/m/Y') }}</p>
                            <p class="numero">TICKET NRO {{ $ticket['numero'] }}</p>
                            <p>NOMBRE: {{ \Illuminate\Support\Str::ascii($ticket['nombre']) }}</p>
                            <p>CI: {{ $ticket['cedula'] }}</p>
                        </div>
                    </td>
                @endforeach
                @if(count($par) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>
