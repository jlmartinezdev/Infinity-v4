<?php

namespace App\Http\Controllers;

use App\Models\TpagoPaymentLink;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TpagoPaymentLinkController extends Controller
{
    public function index(Request $request): View
    {
        $query = TpagoPaymentLink::query()
            ->with([
                'cliente:cliente_id,nombre,apellido,cedula',
                'facturaInterna:id,cliente_id,total,estado',
                'cobro:id,numero_recibo,monto,fecha_pago',
            ])
            ->orderByDesc('id');

        $estado = trim((string) $request->get('estado', ''));
        if ($estado !== '' && $estado !== 'todos') {
            if ($estado === 'expired') {
                $query->where('status', 'pending')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            } elseif ($estado === 'confirmed') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['confirmed', 'paid', 'approved'])
                        ->orWhereNotNull('cobro_id');
                });
            } else {
                $query->where('status', $estado);
            }
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('link_alias', 'like', "%{$q}%")
                    ->orWhere('reference_id', 'like', "%{$q}%")
                    ->orWhere('ticket_number', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('nombre', 'like', "%{$q}%")
                            ->orWhere('apellido', 'like', "%{$q}%")
                            ->orWhere('cedula', 'like', "%{$q}%");
                    });

                if (ctype_digit($q)) {
                    $builder->orWhere('id', (int) $q)
                        ->orWhere('factura_interna_id', (int) $q)
                        ->orWhere('cliente_id', (int) $q);
                }
            });
        }

        if ($request->filled('factura_interna_id')) {
            $query->where('factura_interna_id', (int) $request->get('factura_interna_id'));
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->get('cliente_id'));
        }

        if ($request->boolean('hoy')) {
            $query->whereDate('created_at', now()->toDateString());
        }

        $links = $query->paginate(25)->withQueryString();
        $estados = TpagoPaymentLink::estados();
        $estados['expired'] = 'Vencido';

        $stats = [
            'total' => TpagoPaymentLink::query()->count(),
            'pending' => TpagoPaymentLink::query()->where('status', 'pending')->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->whereNull('cobro_id')->count(),
            'confirmed' => TpagoPaymentLink::query()->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'paid', 'approved'])->orWhereNotNull('cobro_id');
            })->count(),
            'error' => TpagoPaymentLink::query()->whereIn('status', ['error', 'rejected', 'unavailable'])->count(),
        ];

        return view('tpago.links.index', compact('links', 'estados', 'stats'));
    }

    public function show(TpagoPaymentLink $tpago_link): View
    {
        $tpago_link->load([
            'cliente',
            'facturaInterna.detalles',
            'cobro',
        ]);

        return view('tpago.links.show', [
            'link' => $tpago_link,
            'estados' => TpagoPaymentLink::estados(),
        ]);
    }
}
