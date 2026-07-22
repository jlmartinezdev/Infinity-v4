<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsuarioSesionController extends Controller
{
    /**
     * Usuarios del sistema con sesión web activa y último acceso.
     */
    public function index(Request $request)
    {
        $lifetimeSeconds = max(60, (int) config('session.lifetime', 120) * 60);
        $umbral = time() - $lifetimeSeconds;
        $sesionActualId = $request->session()->getId();

        $filas = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.usuario_id')
            ->leftJoin('roles', 'users.rol_id', '=', 'roles.rol_id')
            ->whereNotNull('sessions.user_id')
            ->whereNull('users.cliente_id')
            ->where('sessions.last_activity', '>=', $umbral)
            ->orderByDesc('sessions.last_activity')
            ->get([
                'sessions.id as session_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.usuario_id',
                'users.name',
                'users.email',
                'users.estado',
                'users.ultimo_acceso_at',
                'users.ultimo_acceso_ip',
                'roles.descripcion as rol',
            ]);

        $sesiones = $filas->map(function ($fila) use ($sesionActualId) {
            return (object) [
                'session_id' => $fila->session_id,
                'es_sesion_actual' => $fila->session_id === $sesionActualId,
                'usuario_id' => $fila->usuario_id,
                'name' => $fila->name,
                'email' => $fila->email,
                'estado' => $fila->estado,
                'rol' => $fila->rol,
                'ip_address' => $fila->ip_address,
                'navegador' => $this->resumenUserAgent($fila->user_agent),
                'ultima_actividad' => \Carbon\Carbon::createFromTimestamp((int) $fila->last_activity),
                'ultimo_acceso_at' => $fila->ultimo_acceso_at
                    ? \Carbon\Carbon::parse($fila->ultimo_acceso_at)
                    : null,
                'ultimo_acceso_ip' => $fila->ultimo_acceso_ip,
            ];
        });

        $conectadosIds = $sesiones->pluck('usuario_id')->unique()->values();

        $recientes = User::query()
            ->staff()
            ->with('rol')
            ->whereNotNull('ultimo_acceso_at')
            ->when($conectadosIds->isNotEmpty(), fn ($q) => $q->whereNotIn('usuario_id', $conectadosIds))
            ->orderByDesc('ultimo_acceso_at')
            ->limit(50)
            ->get();

        return view('usuarios.sesiones', [
            'sesiones' => $sesiones,
            'recientes' => $recientes,
            'lifetimeMinutos' => (int) config('session.lifetime', 120),
        ]);
    }

    /**
     * Cerrar una sesión web de otro usuario (o la propia).
     */
    public function destroy(Request $request, string $session)
    {
        $fila = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.usuario_id')
            ->where('sessions.id', $session)
            ->whereNull('users.cliente_id')
            ->first(['sessions.id', 'sessions.user_id']);

        if (! $fila) {
            return back()->with('error', 'La sesión ya no existe o no es de personal del sistema.');
        }

        DB::table('sessions')->where('id', $session)->delete();

        if ($request->session()->getId() === $session) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'Sesión cerrada.');
        }

        return back()->with('success', 'Sesión cerrada correctamente.');
    }

    private function resumenUserAgent(?string $ua): string
    {
        if (! $ua) {
            return 'Desconocido';
        }

        $navegador = 'Navegador';
        if (Str::contains($ua, 'Edg/')) {
            $navegador = 'Edge';
        } elseif (Str::contains($ua, 'Chrome/')) {
            $navegador = 'Chrome';
        } elseif (Str::contains($ua, 'Firefox/')) {
            $navegador = 'Firefox';
        } elseif (Str::contains($ua, 'Safari/') && ! Str::contains($ua, 'Chrome/')) {
            $navegador = 'Safari';
        }

        $so = 'Otro';
        if (Str::contains($ua, 'Windows')) {
            $so = 'Windows';
        } elseif (Str::contains($ua, 'Android')) {
            $so = 'Android';
        } elseif (Str::contains($ua, 'iPhone') || Str::contains($ua, 'iPad')) {
            $so = 'iOS';
        } elseif (Str::contains($ua, 'Mac OS')) {
            $so = 'macOS';
        } elseif (Str::contains($ua, 'Linux')) {
            $so = 'Linux';
        }

        return "{$navegador} · {$so}";
    }
}
