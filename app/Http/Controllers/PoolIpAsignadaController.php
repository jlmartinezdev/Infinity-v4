<?php

namespace App\Http\Controllers;

use App\Models\PoolIpAsignada;
use App\Models\RouterIpPool;
use Illuminate\Http\Request;

class PoolIpAsignadaController extends Controller
{
    public function index(Request $request)
    {
        $poolId = $request->get('pool_id');
        if (!$poolId) {
            return redirect()->route('sistema.router-ip-pools.index')
                ->with('error', 'Seleccione un pool para ver las IPs asignadas.');
        }

        $pool = RouterIpPool::with('router')->where('pool_id', $poolId)->firstOrFail();
        $ips = PoolIpAsignada::where('pool_id', $poolId)->orderBy('ip')->paginate(20)->withQueryString();

        $pools = RouterIpPool::with('router')->orderBy('pool_id')->get();

        return view('sistema.pool-ip-asignadas.index', compact('pool', 'ips', 'pools'));
    }

    public function create(Request $request)
    {
        $poolId = $request->get('pool_id');
        if (!$poolId) {
            return redirect()->route('sistema.router-ip-pools.index')
                ->with('error', 'Seleccione un pool para agregar IPs.');
        }

        $pool = RouterIpPool::with('router')->where('pool_id', $poolId)->firstOrFail();
        return view('sistema.pool-ip-asignadas.create', compact('pool'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'ip' => ['required', 'string', 'max:15'],
            'estado' => ['required', 'string', 'in:disponible,asignada,reservada'],
        ]);

        $existe = PoolIpAsignada::where('pool_id', $validated['pool_id'])->where('ip', $validated['ip'])->exists();
        if ($existe) {
            return back()->withInput()->withErrors(['ip' => 'Esta IP ya existe en el pool.']);
        }

        PoolIpAsignada::create($validated);

        return redirect()->route('sistema.pool-ip-asignadas.index', ['pool_id' => $validated['pool_id']])
            ->with('success', 'IP agregada correctamente.');
    }

    /**
     * Agregar IPs por rango (desde IP inicio hasta IP fin).
     */
    public function storeRango(Request $request)
    {
        $validated = $request->validate([
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'ip_inicio' => ['required', 'string', 'max:15'],
            'ip_fin' => ['required', 'string', 'max:15'],
        ]);

        $ipInicio = trim($validated['ip_inicio']);
        $ipFin = trim($validated['ip_fin']);

        if (filter_var($ipInicio, FILTER_VALIDATE_IP) === false) {
            return back()->withInput()->withErrors(['ip_inicio' => 'La IP de inicio no es válida.']);
        }
        if (filter_var($ipFin, FILTER_VALIDATE_IP) === false) {
            return back()->withInput()->withErrors(['ip_fin' => 'La IP de fin no es válida.']);
        }

        $longInicio = ip2long($ipInicio);
        $longFin = ip2long($ipFin);
        if ($longInicio === false || $longFin === false) {
            return back()->withInput()->withErrors(['ip_inicio' => 'Rango de IPs no válido.']);
        }
        if ($longInicio > $longFin) {
            return back()->withInput()->withErrors(['ip_fin' => 'La IP de fin debe ser mayor o igual a la IP de inicio.']);
        }

        $cantidad = $longFin - $longInicio + 1;
        $maxRango = 1024;
        if ($cantidad > $maxRango) {
            return back()->withInput()->withErrors(['ip_fin' => "El rango no puede superar {$maxRango} IPs. Solicitado: {$cantidad}."]);
        }

        $poolId = (int) $validated['pool_id'];
        $ipsEnRango = [];
        for ($long = $longInicio; $long <= $longFin; $long++) {
            $ipsEnRango[] = long2ip($long);
        }
        $existentes = PoolIpAsignada::where('pool_id', $poolId)
            ->whereIn('ip', $ipsEnRango)
            ->pluck('ip')
            ->flip()
            ->all();

        $agregadas = 0;
        foreach ($ipsEnRango as $ip) {
            if (isset($existentes[$ip])) {
                continue;
            }
            PoolIpAsignada::create([
                'pool_id' => $poolId,
                'ip' => $ip,
                'estado' => 'disponible',
            ]);
            $agregadas++;
        }

        $mensaje = $agregadas > 0
            ? "Se agregaron {$agregadas} IP(s) al pool."
            : 'No se agregó ninguna IP nueva (todas ya existían en el pool).';

        return redirect()->route('sistema.pool-ip-asignadas.index', ['pool_id' => $poolId])
            ->with('success', $mensaje);
    }

    public function edit(Request $request, $poolId, $ip)
    {
        $ip = str_replace('_', '.', $ip);
        $registro = PoolIpAsignada::where('pool_id', $poolId)->where('ip', $ip)->firstOrFail();
        $pool = RouterIpPool::with('router')->where('pool_id', $poolId)->firstOrFail();
        return view('sistema.pool-ip-asignadas.edit', compact('registro', 'pool'));
    }

    public function update(Request $request, $poolId, $ip)
    {
        $ip = str_replace('_', '.', $ip);
        $registro = PoolIpAsignada::where('pool_id', $poolId)->where('ip', $ip)->firstOrFail();

        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:disponible,asignada,reservada'],
        ]);

        $registro->update($validated);

        return redirect()->route('sistema.pool-ip-asignadas.index', ['pool_id' => $poolId])
            ->with('success', 'Estado de IP actualizado correctamente.');
    }

    public function destroy(Request $request, $poolId, $ip)
    {
        $ip = str_replace('_', '.', $ip);
        $registro = PoolIpAsignada::where('pool_id', $poolId)->where('ip', $ip)->firstOrFail();
        $registro->delete();

        return redirect()->route('sistema.pool-ip-asignadas.index', ['pool_id' => $poolId])
            ->with('success', 'IP eliminada del pool.');
    }
}
