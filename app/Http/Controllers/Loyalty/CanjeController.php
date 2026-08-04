<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Canje;
use App\Services\Loyalty\CanjeService;
use Illuminate\Http\Request;

class CanjeController extends Controller
{
    public function __construct(
        private readonly CanjeService $service
    ) {}

    public function index(Request $request)
    {
        $query = Canje::with(['cliente', 'premio', 'staff'])
            ->orderByDesc('id');

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->whereHas('cliente', function ($qry) use ($q) {
                $qry->where('cedula', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%");
            });
        }

        if ($request->boolean('hoy')) {
            $query->whereDate('created_at', today());
        }

        $canjes = $query->paginate(30)->withQueryString();
        $estados = Canje::estados();

        return view('loyalty.canjes.index', compact('canjes', 'estados'));
    }

    public function preparar(Canje $canje)
    {
        try {
            $this->service->marcarPreparacion($canje, auth()->user()?->usuario_id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Canje en preparación.');
    }

    public function listo(Canje $canje)
    {
        try {
            $this->service->marcarListo($canje, auth()->user()?->usuario_id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Canje listo para retirar.');
    }

    public function entregar(Canje $canje)
    {
        try {
            $this->service->marcarEntregado($canje, auth()->user()?->usuario_id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Canje marcado como entregado.');
    }

    public function aplicar(Canje $canje)
    {
        try {
            $this->service->marcarAplicado($canje, auth()->user()?->usuario_id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Descuento marcado como aplicado.');
    }

    public function cancelar(Request $request, Canje $canje)
    {
        try {
            $this->service->cancelar(
                $canje,
                auth()->user()?->usuario_id,
                $request->input('motivo')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Canje cancelado. Puntos y stock revertidos.');
    }
}
