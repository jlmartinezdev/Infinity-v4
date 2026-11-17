@php
    $puedeEditar = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $planEditar = $planEditar ?? null;
    $planesVenta = $planesVenta ?? collect();
    $ventasGigas = $ventasGigas ?? collect();
    $th = 'px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap';
    $campo = 'w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none';
    $etiqueta = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
    $valor = fn (string $campo, mixed $defecto = '') => old($campo, $planEditar?->{$campo} ?? $defecto);
    $ilimitado = (string) old('ilimitado', ($planEditar?->esIlimitado() ?? false) ? '1' : '0') === '1';
    $estadosVenta = \App\Models\HotspotVenta::estados();
@endphp

<div id="hotspot-planes" class="space-y-4">
    <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4" aria-labelledby="hotspot-planes-ayuda">
        <h2 id="hotspot-planes-ayuda" class="text-base font-semibold text-gray-900 dark:text-gray-100">Cómo se vende</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">En el portal del Wi‑Fi la persona elige un plan, paga con TPago y recibe un usuario con PIN. Si el plan tiene gigas, esa es la cuota. Si es ilimitado, no hay tope de datos y el acceso se corta cuando pasan las horas del plan. El cobro queda en esta lista, no en la cuenta de un cliente.</p>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Para que el portal muestre los planes, volvé a cargarlo en el router con <span class="font-mono text-xs">php artisan hotspot:portal-mikrotik</span>. Ese paso también abre el acceso a TPago antes del login.</p>
    </section>

    @if($puedeEditar)
        <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4" aria-labelledby="hotspot-planes-form-title">
            <h2 id="hotspot-planes-form-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $planEditar ? 'Editar plan' : 'Cargar plan' }}
            </h2>
            <form method="POST" action="{{ $planEditar ? route('hotspot.planes.update', $planEditar) : route('hotspot.planes.store') }}" class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @csrf
                @if($planEditar)
                    @method('PUT')
                @endif
                @if($selectedRouter)
                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                @endif
                <div>
                    <label for="plan-nombre" class="{{ $etiqueta }}">Nombre</label>
                    <input type="text" name="nombre" id="plan-nombre" value="{{ $valor('nombre') }}" required maxlength="80" class="{{ $campo }}" placeholder="Ej: 2 GB" autocomplete="off">
                    @error('nombre')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="plan-gigas" class="{{ $etiqueta }}">Gigas</label>
                    <input type="number" name="gigas" id="plan-gigas" value="{{ $ilimitado ? '' : $valor('gigas') }}" @unless($ilimitado) required @endunless min="1" max="10240" step="1" inputmode="numeric" class="{{ $campo }}" placeholder="2" @disabled($ilimitado)>
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-100">
                        <input type="hidden" name="ilimitado" value="0">
                        <input type="checkbox" name="ilimitado" id="plan-ilimitado" value="1" class="rounded border-gray-300 dark:border-gray-600" @checked($ilimitado)>
                        Ilimitado (se corta por tiempo)
                    </label>
                    @error('gigas')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="plan-precio" class="{{ $etiqueta }}">Precio (Gs.)</label>
                    <input type="number" name="precio" id="plan-precio" value="{{ $valor('precio') }}" required min="1000" step="1000" inputmode="numeric" class="{{ $campo }}" placeholder="10000">
                    @error('precio')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="plan-vigencia" class="{{ $etiqueta }}">Duración (horas)</label>
                    <input type="number" name="vigencia_horas" id="plan-vigencia" value="{{ $valor('vigencia_horas', 24) }}" required min="{{ $ilimitado ? 1 : 0 }}" max="8760" step="1" inputmode="numeric" class="{{ $campo }}">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">24 = un día desde el pago. 0 = hasta agotar los gigas (no vale para planes ilimitados).</p>
                    @error('vigencia_horas')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="plan-rate" class="{{ $etiqueta }}">Velocidad (opcional)</label>
                    <input type="text" name="rate_limit" id="plan-rate" value="{{ $valor('rate_limit') }}" maxlength="50" class="{{ $campo }} font-mono" placeholder="5M/10M" autocomplete="off" spellcheck="false">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formato del MikroTik: subida/bajada. Vacío = sin tope.</p>
                </div>
                <div>
                    <label for="plan-orden" class="{{ $etiqueta }}">Orden en el portal</label>
                    <input type="number" name="orden" id="plan-orden" value="{{ $valor('orden', 0) }}" min="0" max="999" step="1" inputmode="numeric" class="{{ $campo }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-100">
                        <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 dark:border-gray-600" @checked(old('activo', $planEditar ? $planEditar->activo : true))>
                        Visible en el portal
                    </label>
                    <button type="submit" class="hsd-btn hsd-btn-primary">{{ $planEditar ? 'Guardar cambios' : 'Cargar plan' }}</button>
                    @if($planEditar)
                        <a href="{{ $menuHotspot('planes') }}" class="hsd-btn">Cancelar</a>
                    @endif
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-planes-lista">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 id="hotspot-planes-lista" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                Planes
                <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ $planesVenta->count() }}</span>
            </h2>
        </div>
        @if($planesVenta->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">Todavía no hay planes. Cargá el primero con gigas y precio.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="{{ $th }}">Plan</th>
                            <th scope="col" class="{{ $th }}">Gigas</th>
                            <th scope="col" class="{{ $th }}">Precio</th>
                            <th scope="col" class="{{ $th }}">Duración</th>
                            <th scope="col" class="{{ $th }}">Velocidad</th>
                            <th scope="col" class="{{ $th }}">Portal</th>
                            <th scope="col" class="{{ $th }}">Ventas</th>
                            @if($puedeEditar)
                                <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($planesVenta as $plan)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $plan->nombre }}</td>
                                <td class="px-4 py-3 tabular-nums text-gray-900 dark:text-gray-100">{{ $plan->gigasTexto() }}</td>
                                <td class="px-4 py-3 tabular-nums text-gray-900 dark:text-gray-100">{{ $plan->precioTexto() }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $plan->vigenciaTexto() }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $plan->rate_limit ?: 'Sin tope' }}</td>
                                <td class="px-4 py-3">
                                    @if($plan->activo)
                                        <span class="text-gray-900 dark:text-gray-100">Visible</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Oculto</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums text-gray-900 dark:text-gray-100">{{ (int) $plan->ventas_count }}</td>
                                @if($puedeEditar)
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ $menuHotspot('planes', ['plan' => $plan->getKey()]) }}" class="hsd-btn">Editar</a>
                                            <form method="POST" action="{{ route('hotspot.planes.destroy', $plan) }}" class="inline" onsubmit="return confirm('{{ $plan->ventas_count > 0 ? 'Este plan tiene ventas. Va a quedar oculto en el portal.' : '¿Eliminar el plan '.$plan->nombre.'?' }}');">
                                                @csrf
                                                @method('DELETE')
                                                @if($selectedRouter)
                                                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                @endif
                                                <button type="submit" class="hsd-btn">{{ $plan->ventas_count > 0 ? 'Ocultar' : 'Eliminar' }}</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-ventas-lista">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 id="hotspot-ventas-lista" class="text-base font-semibold text-gray-900 dark:text-gray-100">Últimas compras</h2>
            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Cuando el pago se confirma, acá aparecen el usuario y el PIN.</p>
        </div>
        @if($ventasGigas->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">Todavía no hay compras.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="{{ $th }}">Cuándo</th>
                            <th scope="col" class="{{ $th }}">Plan</th>
                            <th scope="col" class="{{ $th }}">Monto</th>
                            <th scope="col" class="{{ $th }}">Estado</th>
                            <th scope="col" class="{{ $th }}">Usuario</th>
                            <th scope="col" class="{{ $th }}">PIN</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($ventasGigas as $venta)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $venta->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $venta->plan?->nombre ?? ((int) $venta->gigas > 0 ? (int) $venta->gigas.' GB' : 'Ilimitado') }}</td>
                                <td class="px-4 py-3 tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) $venta->monto, 0, ',', '.') }} Gs.</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $estadosVenta[$venta->estado] ?? $venta->estado }}</td>
                                <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $venta->username ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $venta->estaPagada() ? $venta->password : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
<script>
(function () {
    var ilimitado = document.getElementById('plan-ilimitado');
    var gigas = document.getElementById('plan-gigas');
    var vigencia = document.getElementById('plan-vigencia');
    if (!ilimitado || !gigas) return;
    function sincronizar() {
        gigas.disabled = ilimitado.checked;
        gigas.required = !ilimitado.checked;
        if (ilimitado.checked) gigas.value = '';
        if (vigencia) {
            vigencia.min = ilimitado.checked ? '1' : '0';
            if (ilimitado.checked && Number(vigencia.value) < 1) vigencia.value = '24';
        }
    }
    ilimitado.addEventListener('change', sincronizar);
})();
</script>
