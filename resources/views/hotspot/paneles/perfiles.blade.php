@php
    $puedeEditar = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $buscarPerfiles = trim((string) request('buscar', ''));
    $totalPerfiles = ($perfiles ?? null) ? (int) $perfiles->total() : 0;
    $th = 'px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap';
    $legible = function (string $valor): string {
        $texto = \App\Support\HotspotDuracion::legible($valor);

        return $texto === '—' ? $valor : $texto;
    };
@endphp
<div id="hotspot-perfiles" class="space-y-4">
    @php
        $sync = session('perfiles_sync');
        $etiquetasSync = [
            'creado' => ['Creado', 'is-ok'],
            'actualizado' => ['Actualizado', 'is-ok'],
            'sin-cambios' => ['Sin cambios', 'is-off'],
            'error' => ['Error', 'is-error'],
        ];
    @endphp
    @if(is_array($sync) && ! empty($sync['routers']))
        @php
            $variosRouters = count($sync['routers']) > 1;
            $estadosRouter = [
                'ok' => ['Listo', 'is-ok'],
                'error' => ['Con error', 'is-error'],
                'omitido' => ['Omitido', 'is-off'],
            ];
        @endphp
        <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-perfiles-sync-title">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 id="hotspot-perfiles-sync-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    @if($variosRouters)
                        Resultado en {{ count($sync['routers']) }} routers
                    @else
                        Resultado en {{ $sync['routers'][0]['router'] }}
                    @endif
                </h2>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">La cuota no se envía al router: la controla RADIUS.</p>
            </div>
            @foreach($sync['routers'] as $res)
                @php
                    [$textoRouter, $tonoRouter] = $estadosRouter[$res['estado']] ?? [$res['estado'], 'is-off'];
                    $abierto = ! $variosRouters || $res['estado'] === 'error';
                @endphp
                <details class="{{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-700' }}" @if($abierto) open @endif>
                    <summary class="px-4 py-2 cursor-pointer flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $res['router'] }}</span>
                        <span class="hsd-sync {{ $tonoRouter }}">{{ $textoRouter }}</span>
                        <span class="text-gray-600 dark:text-gray-300">{{ $res['mensaje'] }}</span>
                    </summary>
                    @if(! empty($res['perfiles']))
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm border-t border-gray-200 dark:border-gray-700">
                            @foreach($res['perfiles'] as $item)
                                @php
                                    [$textoSync, $tonoSync] = $etiquetasSync[$item['resultado']] ?? [$item['resultado'], 'is-off'];
                                @endphp
                                <li class="pr-4 py-2 flex flex-wrap items-baseline gap-x-3 gap-y-1" style="padding-left: 2rem">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item['nombre'] }}</span>
                                    <span class="hsd-sync {{ $tonoSync }}">{{ $textoSync }}</span>
                                    @if($item['resultado'] === 'actualizado' && ! empty($item['cambios']))
                                        <span class="text-gray-500 dark:text-gray-400">{{ implode(', ', $item['cambios']) }}</span>
                                    @elseif($item['resultado'] === 'error' && $item['detalle'] !== '')
                                        <span class="text-red-600 dark:text-red-400 break-words">{{ $item['detalle'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if(! empty($res['sobrantes']))
                        <div class="pr-4 py-2 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200" style="padding-left: 2rem">
                            <p>En este router hay {{ count($res['sobrantes']) === 1 ? 'un perfil que no está' : count($res['sobrantes']).' perfiles que no están' }} en Infinity (por ejemplo, de un perfil renombrado). No se borran solos:</p>
                            <p class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-300">{{ implode(' · ', $res['sobrantes']) }}</p>
                        </div>
                    @endif
                </details>
            @endforeach
        </section>
    @endif
    <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-perfiles-title">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="hotspot-perfiles-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    @if($buscarPerfiles !== '')
                        Resultados de “{{ $buscarPerfiles }}”
                    @else
                        Perfiles
                    @endif
                    <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ $totalPerfiles }}</span>
                </h2>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Cada cuenta toma la velocidad, la cuota y los tiempos de su perfil.</p>
            </div>
            @if($puedeEditar)
                <div class="flex flex-wrap items-center gap-2">
                    @php
                        $conHotspot = collect($routers ?? [])->filter(fn ($r) => trim((string) $r->hotspot_servidor) !== '')->count();
                    @endphp
                    <form method="POST" action="{{ route('hotspot.perfiles.sync-mikrotik') }}" class="inline-flex flex-wrap items-center gap-2"
                        onsubmit="var s = this.router_id; return confirm(s.value === 'todos' ? '¿Enviar los perfiles a todos los routers con hotspot? Puede tardar un poco.' : '¿Enviar los perfiles al router elegido?');">
                        @csrf
                        <label for="hotspot-perfiles-router" class="sr-only">Router para sincronizar</label>
                        <select name="router_id" id="hotspot-perfiles-router" required
                            class="rounded-lg border border-gray-300 dark:border-gray-600 py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm min-w-[11rem]">
                            <option value="">Elegí un router…</option>
                            @if($conHotspot > 0)
                                <option value="todos">Todos los routers con hotspot ({{ $conHotspot }})</option>
                            @endif
                            @foreach($routers ?? [] as $r)
                                <option value="{{ $r->router_id }}" @selected($selectedRouter && (int) $selectedRouter->router_id === (int) $r->router_id)>{{ $r->nombre }} ({{ $r->ip }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="hsd-btn">Sincronizar con MikroTik</button>
                    </form>
                    <a href="{{ route('hotspot.perfiles.create') }}" class="hsd-btn hsd-btn-primary">+ Nuevo perfil</a>
                </div>
            @endif
        </div>

        @if(($perfiles ?? null) && $perfiles->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="{{ $th }}">Perfil</th>
                            <th scope="col" class="{{ $th }}">Velocidad</th>
                            <th scope="col" class="{{ $th }}">Cuota</th>
                            <th scope="col" class="{{ $th }}" title="Equipos que pueden entrar a la vez con la misma cuenta">Usuarios a la vez</th>
                            <th scope="col" class="{{ $th }}">Tiempos</th>
                            <th scope="col" class="{{ $th }}">Cuentas</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($perfiles as $perfil)
                            @php
                                $velocidad = $perfil->velocidad();
                                $rateCrudo = trim((string) $perfil->rate_limit);
                                $inactividad = trim((string) $perfil->idle_timeout);
                                $sesion = trim((string) $perfil->session_timeout);
                                $cuentas = (int) ($perfil->servicio_hotspots_count ?? 0);
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    @if($puedeEditar)
                                        <a href="{{ route('hotspot.perfiles.edit', $perfil) }}" class="font-semibold text-gray-900 dark:text-gray-100 underline underline-offset-2 decoration-gray-400 dark:decoration-gray-500 hover:text-gray-700 dark:hover:text-white">{{ $perfil->nombre }}</a>
                                    @else
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $perfil->nombre }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">
                                    @if($velocidad)
                                        <span title="{{ $rateCrudo }}">↓ {{ $velocidad['bajada'] }} · ↑ {{ $velocidad['subida'] }}</span>
                                    @elseif($rateCrudo !== '')
                                        <span class="font-mono text-xs">{{ $rateCrudo }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Sin tope</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                    @if($perfil->tieneCuota())
                                        <span class="text-gray-900 dark:text-gray-100">{{ (int) $perfil->cuota_gb }} GB</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Ilimitada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    @if(trim((string) $perfil->shared_users) !== '')
                                        <span class="text-gray-900 dark:text-gray-100">{{ $perfil->shared_users }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400" title="Toma el valor del router">Del router</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    @if($inactividad === '' && $sesion === '')
                                        <span class="text-gray-500 dark:text-gray-400">Sin límite</span>
                                    @else
                                        @if($inactividad !== '')
                                            <span class="block whitespace-nowrap">Inactividad: {{ $legible($inactividad) }}</span>
                                        @endif
                                        @if($sesion !== '')
                                            <span class="block whitespace-nowrap">Sesión: {{ $legible($sesion) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    @if($cuentas > 0)
                                        <span class="text-gray-900 dark:text-gray-100">{{ $cuentas }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Sin uso</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($puedeEditar)
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('hotspot.perfiles.edit', $perfil) }}" class="hsd-btn">Editar</a>
                                            @if($cuentas > 0)
                                                <button type="button" class="hsd-btn hsd-btn-danger" disabled aria-disabled="true"
                                                    title="Lo usan {{ $cuentas }} {{ $cuentas === 1 ? 'cuenta' : 'cuentas' }}: cambiales el perfil antes de eliminarlo"
                                                    style="opacity: .45; cursor: not-allowed">Eliminar</button>
                                            @else
                                                <form action="{{ route('hotspot.perfiles.destroy', $perfil) }}" method="POST" class="inline"
                                                    onsubmit="return confirm({{ json_encode('¿Eliminar el perfil '.$perfil->nombre.'? No se puede deshacer.') }});">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="hsd-btn hsd-btn-danger">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($perfiles->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $perfiles->links() }}
                </div>
            @endif
        @else
            <div class="px-4 py-10 text-center text-sm text-gray-600 dark:text-gray-300">
                @if($buscarPerfiles !== '')
                    <p>Ningún perfil coincide con “{{ $buscarPerfiles }}”.</p>
                    <p class="mt-2"><a href="{{ $menuHotspot('perfiles') }}" class="underline underline-offset-2">Ver todos los perfiles</a></p>
                @else
                    <p>Todavía no hay perfiles.</p>
                    @if($puedeEditar)
                        <p class="mt-2"><a href="{{ route('hotspot.perfiles.create') }}" class="underline underline-offset-2">Crear el primero</a></p>
                    @endif
                @endif
            </div>
        @endif
    </section>
</div>
