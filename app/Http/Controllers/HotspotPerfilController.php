<?php

namespace App\Http\Controllers;

use App\Models\HotspotPerfil;
use App\Models\Router;
use App\Services\MikroTikService;
use App\Support\HotspotPerfilFormulario;
use App\Support\HotspotPerfilesMikrotik;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotspotPerfilController extends Controller
{
    public function index(Request $request)
    {
        $routerId = (int) $request->input('router_id', $request->session()->get('hotspot_dashboard_router_id', 0));
        $to = redirect()->route('hotspot.dashboard', array_filter([
            'vista' => 'perfiles',
            'buscar' => $request->input('buscar'),
            'page' => $request->input('page'),
            'router_id' => $routerId > 0 ? $routerId : null,
        ], fn ($valor) => $valor !== null && $valor !== ''));
        foreach (['success', 'error', 'warning', 'perfiles_sync'] as $clave) {
            if ($request->session()->has($clave)) {
                $to->with($clave, $request->session()->get($clave));
            }
        }

        return $to;
    }

    public function create()
    {
        return view('hotspot.perfiles.create', [
            'campos' => HotspotPerfilFormulario::campos(null),
        ]);
    }

    public function store(Request $request)
    {
        $atributos = $this->validarFormulario($request);

        $perfil = HotspotPerfil::create($atributos);

        return redirect()->route('hotspot.perfiles.index')->with('success', 'Perfil '.$perfil->nombre.' creado.');
    }

    public function edit(HotspotPerfil $perfil)
    {
        $perfil->loadCount('servicioHotspots');

        return view('hotspot.perfiles.edit', [
            'perfil' => $perfil,
            'campos' => HotspotPerfilFormulario::campos($perfil),
        ]);
    }

    public function update(Request $request, HotspotPerfil $perfil)
    {
        $atributos = $this->validarFormulario($request, $perfil);
        $nombreAnterior = (string) $perfil->nombre;

        $perfil->update($atributos);

        $cuentas = $perfil->servicioHotspots()->count();
        $mensaje = 'Perfil '.$perfil->nombre.' guardado.';
        if ($cuentas > 0 && $perfil->wasChanged(['rate_limit', 'cuota_gb', 'shared_users', 'idle_timeout', 'session_timeout'])) {
            $mensaje .= ' Se actualizó en RADIUS para '.$cuentas.' '.($cuentas === 1 ? 'cuenta' : 'cuentas').'.';
        }
        if ($nombreAnterior !== $perfil->nombre) {
            $mensaje .= ' Cambió el nombre: sincronizá los perfiles con el MikroTik para que el router lo tenga con el nombre nuevo.';
        }

        return redirect()->route('hotspot.perfiles.index')->with('success', $mensaje);
    }

    public function destroy(HotspotPerfil $perfil)
    {
        if ($perfil->servicioHotspots()->exists()) {
            return redirect()->route('hotspot.perfiles.index')->with('error', 'No se puede eliminar: hay servicios asociados.');
        }
        $perfil->delete();

        return redirect()->route('hotspot.perfiles.index')->with('success', 'Perfil hotspot eliminado correctamente.');
    }

    /**
     * Deja los perfiles del router MikroTik iguales a los de Infinity y muestra qué pasó con cada uno.
     */
    public function syncMikrotik(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', function (string $atributo, mixed $valor, \Closure $falla) {
                if ($valor === 'todos') {
                    return;
                }
                if (! ctype_digit((string) $valor) || ! Router::whereKey((int) $valor)->exists()) {
                    $falla('Ese router no existe.');
                }
            }],
        ], [
            'router_id.required' => 'Elegí el router al que querés enviar los perfiles.',
        ]);

        $todos = $validated['router_id'] === 'todos';
        $routers = $todos
            ? Router::query()
                ->whereNotNull('hotspot_servidor')
                ->where('hotspot_servidor', '!=', '')
                ->orderBy('nombre')
                ->get()
            : Router::whereKey((int) $validated['router_id'])->get();
        $volver = redirect()->route('hotspot.perfiles.index', array_filter([
            'router_id' => $todos ? null : $routers->first()?->router_id,
        ]));

        if ($routers->isEmpty()) {
            return $volver->with('error', 'No hay routers con hotspot configurado.');
        }

        // Un router por vez: darle tiempo a cada uno para que no corte el límite de PHP a mitad de camino.
        if ($routers->count() > 1 && function_exists('set_time_limit')) {
            @set_time_limit(30 * $routers->count());
        }

        $perfiles = HotspotPerfil::orderBy('nombre')->get();
        $detalle = [];
        $resumen = ['bien' => 0, 'con-error' => 0, 'omitido' => 0];

        foreach ($routers as $router) {
            // En "todos", no esperar el timeout de un router que el monitoreo ya da por caído.
            if ($todos && strtolower((string) $router->estado) === Router::ESTADO_DESCONECTADO) {
                $detalle[] = ['router' => $router->nombre, 'estado' => 'omitido', 'mensaje' => 'Está caído: no se intentó.', 'perfiles' => [], 'sobrantes' => []];
                $resumen['omitido']++;

                continue;
            }

            try {
                $plan = $mikrotik->sincronizarPerfilesHotspot($router, $perfiles);
            } catch (Throwable $e) {
                $detalle[] = ['router' => $router->nombre, 'estado' => 'error', 'mensaje' => 'No pude conectar: '.$e->getMessage(), 'perfiles' => [], 'sobrantes' => []];
                $resumen['con-error']++;

                continue;
            }

            $conError = collect($plan['perfiles'])->contains(fn (array $item) => $item['resultado'] === 'error');
            $resumen[$conError ? 'con-error' : 'bien']++;
            $detalle[] = [
                'router' => $router->nombre,
                'estado' => $conError ? 'error' : 'ok',
                'mensaje' => self::resumenPerfiles($plan['perfiles']),
                'perfiles' => array_map(fn (array $item) => [
                    'nombre' => $item['nombre'],
                    'resultado' => $item['resultado'],
                    'detalle' => $item['detalle'],
                    'cambios' => array_map(
                        fn (string $clave) => HotspotPerfilesMikrotik::etiquetaCambio($clave),
                        $item['cambios']
                    ),
                ], $plan['perfiles']),
                'sobrantes' => $plan['sobrantes'],
            ];
        }

        if ($todos) {
            $partes = [];
            if ($resumen['bien'] > 0) {
                $partes[] = $resumen['bien'].' sin problemas';
            }
            if ($resumen['con-error'] > 0) {
                $partes[] = $resumen['con-error'].' con error';
            }
            if ($resumen['omitido'] > 0) {
                $partes[] = $resumen['omitido'].' '.($resumen['omitido'] === 1 ? 'caído, omitido' : 'caídos, omitidos');
            }
            $n = $routers->count();
            $mensaje = 'Perfiles enviados a '.$n.' '.($n === 1 ? 'router' : 'routers').': '.implode(', ', $partes).'.';
        } else {
            $mensaje = 'Perfiles enviados a '.$detalle[0]['router'].': '.$detalle[0]['mensaje'];
        }

        return $volver
            ->with($resumen['con-error'] > 0 ? 'error' : 'success', $mensaje)
            ->with('perfiles_sync', ['routers' => $detalle]);
    }

    /**
     * @param  list<array{resultado: string}>  $perfiles
     */
    private static function resumenPerfiles(array $perfiles): string
    {
        $cuenta = ['creado' => 0, 'actualizado' => 0, 'sin-cambios' => 0, 'error' => 0];
        foreach ($perfiles as $item) {
            $cuenta[$item['resultado']] = ($cuenta[$item['resultado']] ?? 0) + 1;
        }

        $partes = [];
        if ($cuenta['creado'] > 0) {
            $partes[] = $cuenta['creado'].' '.($cuenta['creado'] === 1 ? 'creado' : 'creados');
        }
        if ($cuenta['actualizado'] > 0) {
            $partes[] = $cuenta['actualizado'].' '.($cuenta['actualizado'] === 1 ? 'actualizado' : 'actualizados');
        }
        if ($cuenta['sin-cambios'] > 0) {
            $partes[] = $cuenta['sin-cambios'].' sin cambios';
        }
        if ($cuenta['error'] > 0) {
            $partes[] = $cuenta['error'].' con error';
        }

        return ($partes === [] ? 'no hay perfiles en Infinity' : implode(', ', $partes)).'.';
    }

    /**
     * Valida el formulario (Mbps, número + unidad) y devuelve los atributos en formato MikroTik.
     *
     * @return array{nombre: string, rate_limit: ?string, cuota_gb: ?int, shared_users: ?string, idle_timeout: ?string, session_timeout: ?string}
     */
    protected function validarFormulario(Request $request, ?HotspotPerfil $perfil = null): array
    {
        $unidades = array_keys(HotspotPerfilFormulario::UNIDADES);
        $validated = $request->validate([
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('hotspot_perfiles', 'nombre')->ignore($perfil?->getKey(), 'hotspot_perfil_id'),
            ],
            'bajada_mbps' => ['nullable', 'numeric', 'min:0.064', 'max:100000', 'required_with:subida_mbps'],
            'subida_mbps' => ['nullable', 'numeric', 'min:0.064', 'max:100000', 'required_with:bajada_mbps'],
            'rate_limit_avanzado' => ['nullable', 'string', 'max:50', 'regex:'.HotspotPerfilFormulario::REGEX_RATE_LIMIT],
            'cuota_gb' => ['nullable', 'integer', 'min:1', 'max:10240'],
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:100'],
            'inactividad_valor' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'inactividad_unidad' => ['nullable', Rule::in($unidades)],
            'sesion_valor' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sesion_unidad' => ['nullable', Rule::in($unidades)],
        ], [
            'nombre.required' => 'Poné un nombre al perfil.',
            'nombre.unique' => 'Ya hay un perfil con ese nombre.',
            'bajada_mbps.required_with' => 'Completá también la bajada, o dejá las dos vacías para no poner tope.',
            'subida_mbps.required_with' => 'Completá también la subida, o dejá las dos vacías para no poner tope.',
            'bajada_mbps.min' => 'La bajada tiene que ser de al menos 0,064 Mbps (64 kbps).',
            'subida_mbps.min' => 'La subida tiene que ser de al menos 0,064 Mbps (64 kbps).',
            'bajada_mbps.numeric' => 'La bajada tiene que ser un número de Mbps.',
            'subida_mbps.numeric' => 'La subida tiene que ser un número de Mbps.',
            'rate_limit_avanzado.regex' => 'No es un valor de MikroTik válido. Ejemplo: 5M/10M 8M/15M 4M/8M 16/16.',
            'cuota_gb.integer' => 'La cuota va en GB enteros.',
            'cuota_gb.min' => 'La cuota tiene que ser de al menos 1 GB, o vacía para ilimitada.',
            'shared_users.integer' => 'Poné un número de equipos.',
            'shared_users.min' => 'Tiene que ser al menos 1 equipo, o vacío para usar el del router.',
            'inactividad_valor.integer' => 'La inactividad va en números enteros.',
            'sesion_valor.integer' => 'La duración de sesión va en números enteros.',
        ], [
            'bajada_mbps' => 'bajada',
            'subida_mbps' => 'subida',
            'rate_limit_avanzado' => 'valor avanzado',
            'cuota_gb' => 'cuota',
            'shared_users' => 'usuarios a la vez',
            'inactividad_valor' => 'inactividad',
            'sesion_valor' => 'duración de sesión',
        ]);

        $simple = filled($validated['bajada_mbps'] ?? null) || filled($validated['subida_mbps'] ?? null);
        if ($simple && filled($validated['rate_limit_avanzado'] ?? null)) {
            throw ValidationException::withMessages([
                'rate_limit_avanzado' => 'Usá la velocidad simple o el valor avanzado, no los dos. Vaciá uno de los campos.',
            ]);
        }

        return HotspotPerfilFormulario::atributos($validated);
    }
}
