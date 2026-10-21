@extends('layouts.app')

@section('title', 'Nuevo Cable de Fibra Óptica')

@section('content')
<div class="max-w-4xl mx-auto space-y-6 pb-12" x-data="{
    origenTipo: '{{ old('origen_tipo', 'nodo') }}',
    destinoTipo: '{{ old('destino_tipo', 'caja_nap') }}',
    capacidad: {{ old('cantidad_hilos', 12) }},
    get tubos() { return Math.ceil(this.capacidad / 12); }
}">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('sistema.lineas-cable.index') }}" class="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1">
                &larr; Volver al listado de cables
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Registrar Nuevo Cable de Planta Externa</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                Defina el tramo de cable de calle, capacidad en pelos/hilos, origen y destino de conexión.
            </p>
        </div>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-sm">
            <div class="font-bold mb-1">Por favor corrija los siguientes errores:</div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('sistema.lineas-cable.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        @csrf

        {{-- SECCIÓN 1: DATOS BÁSICOS DEL CABLE --}}
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2 mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                Identificación y Características del Cable
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Nombre / Código --}}
                <div class="sm:col-span-2">
                    <label for="nombre" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Nombre o Código del Cable
                    </label>
                    <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}"
                        placeholder="Ej. TRONCAL-N2-PYTERE-24FO o DIST-SUR-01"
                        class="w-full h-11 px-3.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <p class="text-[11px] text-gray-400 mt-1">Si lo deja en blanco, el sistema generará un identificador automático.</p>
                </div>

                {{-- Tipo de Cable --}}
                <div>
                    <label for="tipo_cable" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Tipo de Cable *
                    </label>
                    <select name="tipo_cable" id="tipo_cable" required
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        @foreach ($tiposCable as $k => $v)
                            <option value="{{ $k }}" @selected(old('tipo_cable', 'distribucion') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Capacidad de Hilos / Pelos --}}
                <div>
                    <label for="cantidad_hilos" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Capacidad (Pelos / Hilos) *
                    </label>
                    <select name="cantidad_hilos" id="cantidad_hilos" x-model.number="capacidad" required
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 font-bold focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        @foreach ($capacidades as $cap)
                            <option value="{{ $cap }}" @selected((int)old('cantidad_hilos', 12) === $cap)>
                                {{ $cap }} Pelos (FO)
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-1 font-semibold" x-text="capacidad + ' pelos distribuidos en ' + tubos + (tubos === 1 ? ' tubo buffer' : ' tubos buffer')"></p>
                </div>

                {{-- Color de Chaqueta / Buffer --}}
                <div>
                    <label for="fibra_color_id" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Color Distintivo *
                    </label>
                    <select name="fibra_color_id" id="fibra_color_id" required
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        @foreach($fibraColores as $fc)
                            <option value="{{ $fc->fibra_color_id }}" @selected(old('fibra_color_id') == $fc->fibra_color_id)>
                                {{ $fc->nombre }} ({{ $fc->codigo }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Estado Operativo --}}
                <div>
                    <label for="estado" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Estado Operativo *
                    </label>
                    <select name="estado" id="estado" required
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        @foreach ($estados as $k => $v)
                            <option value="{{ $k }}" @selected(old('estado', 'activo') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Longitud en Metros --}}
                <div>
                    <label for="longitud_metros" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Longitud (metros)
                    </label>
                    <div class="relative">
                        <input type="number" name="longitud_metros" id="longitud_metros" value="{{ old('longitud_metros') }}" min="0" step="0.1"
                            placeholder="Ej. 1200"
                            class="w-full h-11 px-3.5 pr-12 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <span class="absolute right-3.5 top-3 text-xs text-gray-400 font-bold">m</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECCIÓN 2: ORIGEN Y DESTINO INTELIGENTES --}}
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2 mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                Puntos de Conexión de Campo (Extremos del Cable)
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Extremo ORIGEN --}}
                <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-950/10 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">A</span>
                        <h4 class="text-sm font-bold text-emerald-900 dark:text-emerald-300">Extremo A (Origen)</h4>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Tipo de Elemento</label>
                        <select name="origen_tipo" x-model="origenTipo" required
                            class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 font-semibold">
                            <option value="nodo">Nodo / Central</option>
                            <option value="salida_pon">Salida PON</option>
                            <option value="caja_nap">Caja NAP</option>
                            <option value="splitter_primario">Splitter Primario</option>
                            <option value="splitter_secundario">Splitter Secundario</option>
                        </select>
                    </div>

                    {{-- Select Dinámico de Elemento Origen --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Elemento Seleccionado</label>
                        
                        {{-- Select para Nodo --}}
                        <div x-show="origenTipo === 'nodo'">
                            <select :disabled="origenTipo !== 'nodo'" name="origen_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($nodos as $n)
                                    <option value="{{ $n->nodo_id }}" @selected(old('origen_id') == $n->nodo_id)>
                                        Nodo: {{ $n->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Salida PON --}}
                        <div x-show="origenTipo === 'salida_pon'">
                            <select :disabled="origenTipo !== 'salida_pon'" name="origen_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($salidaPons as $sp)
                                    <option value="{{ $sp->salida_pon_id }}" @selected(old('origen_id') == $sp->salida_pon_id)>
                                        PON: {{ $sp->codigo }} ({{ $sp->nodo?->descripcion }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Caja NAP --}}
                        <div x-show="origenTipo === 'caja_nap'">
                            <select :disabled="origenTipo !== 'caja_nap'" name="origen_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($cajas as $c)
                                    <option value="{{ $c->caja_nap_id }}" @selected(old('origen_id') == $c->caja_nap_id)>
                                        NAP: {{ $c->codigo }} ({{ $c->nodo?->descripcion }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Splitters Primarios --}}
                        <div x-show="origenTipo === 'splitter_primario'">
                            <select :disabled="origenTipo !== 'splitter_primario'" name="origen_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($splittersPrimarios as $spr)
                                    <option value="{{ $spr->splitter_primario_id }}" @selected(old('origen_id') == $spr->splitter_primario_id)>
                                        Splitter 1º: {{ $spr->codigo }} ({{ $spr->cajaNap?->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Splitters Secundarios --}}
                        <div x-show="origenTipo === 'splitter_secundario'">
                            <select :disabled="origenTipo !== 'splitter_secundario'" name="origen_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($splittersSecundarios as $ssec)
                                    <option value="{{ $ssec->splitter_secundario_id }}" @selected(old('origen_id') == $ssec->splitter_secundario_id)>
                                        Splitter 2º: {{ $ssec->codigo }} ({{ $ssec->cajaNap?->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Extremo DESTINO --}}
                <div class="p-4 rounded-xl border border-purple-200 dark:border-purple-800/60 bg-purple-50/30 dark:bg-purple-950/10 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-xs">B</span>
                        <h4 class="text-sm font-bold text-purple-900 dark:text-purple-300">Extremo B (Destino)</h4>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Tipo de Elemento</label>
                        <select name="destino_tipo" x-model="destinoTipo" required
                            class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 font-semibold">
                            <option value="caja_nap">Caja NAP</option>
                            <option value="nodo">Nodo / Central</option>
                            <option value="salida_pon">Salida PON</option>
                            <option value="splitter_primario">Splitter Primario</option>
                            <option value="splitter_secundario">Splitter Secundario</option>
                        </select>
                    </div>

                    {{-- Select Dinámico de Elemento Destino --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Elemento Seleccionado</label>
                        
                        {{-- Select para Caja NAP --}}
                        <div x-show="destinoTipo === 'caja_nap'">
                            <select :disabled="destinoTipo !== 'caja_nap'" name="destino_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($cajas as $c)
                                    <option value="{{ $c->caja_nap_id }}" @selected(old('destino_id') == $c->caja_nap_id)>
                                        NAP: {{ $c->codigo }} ({{ $c->nodo?->descripcion }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Nodo --}}
                        <div x-show="destinoTipo === 'nodo'">
                            <select :disabled="destinoTipo !== 'nodo'" name="destino_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($nodos as $n)
                                    <option value="{{ $n->nodo_id }}" @selected(old('destino_id') == $n->nodo_id)>
                                        Nodo: {{ $n->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Salida PON --}}
                        <div x-show="destinoTipo === 'salida_pon'">
                            <select :disabled="destinoTipo !== 'salida_pon'" name="destino_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($salidaPons as $sp)
                                    <option value="{{ $sp->salida_pon_id }}" @selected(old('destino_id') == $sp->salida_pon_id)>
                                        PON: {{ $sp->codigo }} ({{ $sp->nodo?->descripcion }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Splitters Primarios --}}
                        <div x-show="destinoTipo === 'splitter_primario'">
                            <select :disabled="destinoTipo !== 'splitter_primario'" name="destino_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($splittersPrimarios as $spr)
                                    <option value="{{ $spr->splitter_primario_id }}" @selected(old('destino_id') == $spr->splitter_primario_id)>
                                        Splitter 1º: {{ $spr->codigo }} ({{ $spr->cajaNap?->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select para Splitters Secundarios --}}
                        <div x-show="destinoTipo === 'splitter_secundario'">
                            <select :disabled="destinoTipo !== 'splitter_secundario'" name="destino_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                @foreach($splittersSecundarios as $ssec)
                                    <option value="{{ $ssec->splitter_secundario_id }}" @selected(old('destino_id') == $ssec->splitter_secundario_id)>
                                        Splitter 2º: {{ $ssec->codigo }} ({{ $ssec->cajaNap?->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECCIÓN 3: NOTAS DE TENDIDO --}}
        <div>
            <label for="notas" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                Notas de Tendido / Observaciones de Campo
            </label>
            <textarea name="notas" id="notas" rows="3"
                placeholder="Detalle de postes, cruces de calle, vanos especiales, modelo de cable ADSS o fibra..."
                class="w-full p-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">{{ old('notas') }}</textarea>
        </div>

        {{-- BOTONES DE ACCIÓN --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
            <a href="{{ route('sistema.lineas-cable.index') }}"
                class="px-5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Cancelar
            </a>
            <button type="submit"
                class="px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold shadow-sm transition-colors">
                Guardar Cable de Fibra
            </button>
        </div>
    </form>
</div>
@endsection
