@csrf
@isset($ticketAsunto)
    @method('PUT')
@endisset

@php
    use App\Support\TicketAsuntoIconos;

    $ticketAsunto = $ticketAsunto ?? null;
    $svgActual = old('icono_svg', $ticketAsunto?->getIconoSvgKey() ?? TicketAsuntoIconos::DEFAULT);
    $iconoActual = old('icono', $ticketAsunto?->getIconoOrDefault() ?? '🎫');
    $colorActual = old('color', $ticketAsunto?->getColorOrDefault() ?? '#8b5cf6');
    $nombreActual = old('nombre', $ticketAsunto?->nombre ?? '');

    $colorPresets = [
        ['hex' => '#8b5cf6', 'nombre' => 'Violeta'],
        ['hex' => '#a855f7', 'nombre' => 'Púrpura'],
        ['hex' => '#3b82f6', 'nombre' => 'Azul'],
        ['hex' => '#0284c7', 'nombre' => 'Celeste'],
        ['hex' => '#06b6d4', 'nombre' => 'Cian'],
        ['hex' => '#10b981', 'nombre' => 'Verde'],
        ['hex' => '#f59e0b', 'nombre' => 'Ámbar'],
        ['hex' => '#f97316', 'nombre' => 'Naranja'],
        ['hex' => '#ef4444', 'nombre' => 'Rojo'],
        ['hex' => '#64748b', 'nombre' => 'Gris'],
    ];

    $emojisRapidos = ['🌐','📶','📡','🔌','⚡','🐢','🚫','📺','📱','📦','🔐','✂️','💥','🔥','🛠️','⚠️','❌','🔄','💰','📝','🔑','🏠','📍','🟢','💬','🎫'];
    $svgLibrary = [];
    foreach (TicketAsuntoIconos::catalogo() as $k => $it) {
        $svgLibrary[$k] = TicketAsuntoIconos::svg($k, 'w-full h-full', 1.5);
    }
@endphp

<div class="space-y-6">
    {{-- Nombre --}}
    <div>
        <label for="nombre" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Nombre del Asunto *</label>
        <input type="text" name="nombre" id="nombre" value="{{ $nombreActual }}"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-base"
            maxlength="120" required autofocus placeholder="Ej: No Tiene Internet, Solicitud APP TV, Cable Fibra Dañado...">
        @error('nombre')<p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Vista previa grande --}}
        <div class="lg:col-span-1 order-first lg:order-last">
            <div class="lg:sticky lg:top-4 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 flex flex-col items-center text-center gap-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Vista previa</span>

                <span id="preview-tile-xl" class="inline-flex items-center justify-center w-28 h-28 rounded-3xl transition-colors"
                      style="background-color: {{ $colorActual }}1f; color: {{ $colorActual }}; border: 1px solid {{ $colorActual }}4d;">
                    <span id="preview-svg-xl" class="w-16 h-16 block">{!! $svgLibrary[$svgActual] ?? $svgLibrary['ticket'] !!}</span>
                </span>
                <p id="preview-nombre-xl" class="text-base font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $nombreActual ?: 'Nombre del asunto' }}</p>

                {{-- Tamaños --}}
                <div class="flex items-end justify-center gap-3 pt-1">
                    @foreach ([['w-14 h-14 rounded-2xl','w-8 h-8'], ['w-10 h-10 rounded-xl','w-6 h-6'], ['w-8 h-8 rounded-lg','w-[18px] h-[18px]']] as $i => $sz)
                        <span class="preview-tile inline-flex items-center justify-center {{ $sz[0] }}"
                              style="background-color: {{ $colorActual }}1f; color: {{ $colorActual }}; border: 1px solid {{ $colorActual }}4d;">
                            <span class="preview-svg block {{ $sz[1] }}">{!! $svgLibrary[$svgActual] ?? $svgLibrary['ticket'] !!}</span>
                        </span>
                    @endforeach
                </div>

                {{-- Cómo se ve en la fila de ticket --}}
                <div class="w-full mt-1 p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center gap-3 text-left">
                    <span class="preview-tile inline-flex items-center justify-center w-10 h-10 rounded-xl shrink-0"
                          style="background-color: {{ $colorActual }}1f; color: {{ $colorActual }}; border: 1px solid {{ $colorActual }}4d;">
                        <span class="preview-svg block w-6 h-6">{!! $svgLibrary[$svgActual] ?? $svgLibrary['ticket'] !!}</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-mono text-gray-400">#4521</p>
                        <p id="preview-nombre-row" class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $nombreActual ?: 'Nombre del asunto' }}</p>
                        <span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Pendiente</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Selectores --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Ícono SVG --}}
            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Ícono del asunto</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Íconos vectoriales: se ven nítidos en cualquier tamaño.</p>
                    </div>
                    <span id="icono-svg-label" class="text-xs font-semibold text-purple-600 dark:text-purple-300">
                        {{ TicketAsuntoIconos::catalogo()[$svgActual]['label'] ?? 'Genérico' }}
                    </span>
                </div>
                <input type="hidden" name="icono_svg" id="icono_svg" value="{{ $svgActual }}">

                <div class="space-y-4 max-h-[22rem] overflow-y-auto pr-1">
                    @foreach (TicketAsuntoIconos::porGrupo() as $grupo => $items)
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">{{ $grupo }}</p>
                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                                @foreach ($items as $clave => $item)
                                    <button type="button"
                                        class="btn-pick-svg group flex flex-col items-center gap-1 p-2 rounded-xl border transition-all
                                               {{ $clave === $svgActual ? 'border-purple-500 ring-2 ring-purple-500/30 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700 hover:border-purple-300 hover:bg-gray-50 dark:hover:bg-gray-700/50' }}"
                                        data-svg="{{ $clave }}" data-label="{{ $item['label'] }}" title="{{ $item['label'] }}">
                                        <span class="w-8 h-8 block text-gray-700 dark:text-gray-200 group-hover:text-purple-600 dark:group-hover:text-purple-300">
                                            {!! TicketAsuntoIconos::svg($clave, 'w-full h-full', 1.6) !!}
                                        </span>
                                        <span class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 text-center line-clamp-1">{{ $item['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('icono_svg')<p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
            </div>

            {{-- Color --}}
            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Color</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Tiñe el ícono y su fondo en la lista de tickets.</p>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($colorPresets as $cp)
                        <button type="button" class="btn-pick-color w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 ring-1 ring-gray-300 dark:ring-gray-600 hover:scale-110 transition-transform"
                            style="background-color: {{ $cp['hex'] }};" data-color="{{ $cp['hex'] }}" title="{{ $cp['nombre'] }}" aria-label="{{ $cp['nombre'] }}"></button>
                    @endforeach
                    <span class="mx-1 h-6 w-px bg-gray-200 dark:bg-gray-700"></span>
                    <input type="color" id="color" name="color" value="{{ $colorActual }}" class="w-9 h-9 rounded-lg cursor-pointer border border-gray-300 dark:border-gray-600 p-0.5 bg-white dark:bg-gray-700">
                    <input type="text" id="color-hex-text" value="{{ strtoupper($colorActual) }}" maxlength="7"
                        class="w-24 px-2 py-1.5 font-mono text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 uppercase">
                </div>
                @error('color')<p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
            </div>

            {{-- Emoji para listas desplegables --}}
            <details class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800" @error('icono') open @enderror>
                <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-gray-200">
                    Emoji para listas desplegables <span id="emoji-actual" class="ml-1">{{ $iconoActual }}</span>
                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">Los &lt;select&gt; del navegador no pueden mostrar SVG, ahí se usa este emoji.</span>
                </summary>
                <div class="mt-3 flex items-center gap-2">
                    <input type="text" name="icono" id="icono" value="{{ $iconoActual }}" maxlength="50"
                        class="w-24 px-3 py-2 text-base rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($emojisRapidos as $em)
                            <button type="button" class="btn-pick-icon w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-purple-50 dark:hover:bg-purple-900/40" data-icon="{{ $em }}">{{ $em }}</button>
                        @endforeach
                    </div>
                </div>
                @error('icono')<p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
            </details>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ isset($ticketAsunto) ? 'Guardar Cambios' : 'Crear Asunto' }}
        </button>
        <a href="{{ route('ticket-asuntos.index') }}" class="inline-flex items-center px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-xl font-medium">Cancelar</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const SVGS = @json($svgLibrary);
    const SEL_ON = ['border-purple-500', 'ring-2', 'ring-purple-500/30', 'bg-purple-50', 'dark:bg-purple-900/30'];
    const SEL_OFF = ['border-gray-200', 'dark:border-gray-700'];

    const inNombre = document.getElementById('nombre');
    const inSvg = document.getElementById('icono_svg');
    const inIcono = document.getElementById('icono');
    const inColor = document.getElementById('color');
    const inHex = document.getElementById('color-hex-text');

    function tiles() { return [document.getElementById('preview-tile-xl'), ...document.querySelectorAll('.preview-tile')]; }

    function pintarColor(hex) {
        tiles().forEach(t => {
            t.style.backgroundColor = hex + '1f';
            t.style.color = hex;
            t.style.borderColor = hex + '4d';
        });
    }

    function pintarSvg(key) {
        const markup = SVGS[key] || SVGS['ticket'];
        document.getElementById('preview-svg-xl').innerHTML = markup;
        document.querySelectorAll('.preview-svg').forEach(el => el.innerHTML = markup);
    }

    function pintarNombre() {
        const n = inNombre.value.trim() || 'Nombre del asunto';
        document.getElementById('preview-nombre-xl').textContent = n;
        document.getElementById('preview-nombre-row').textContent = n;
    }

    function setColor(hex) {
        if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
        inColor.value = hex.toLowerCase();
        inHex.value = hex.toUpperCase();
        pintarColor(hex);
    }

    document.querySelectorAll('.btn-pick-svg').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-pick-svg').forEach(b => { b.classList.remove(...SEL_ON); b.classList.add(...SEL_OFF); });
            this.classList.remove(...SEL_OFF);
            this.classList.add(...SEL_ON);
            inSvg.value = this.dataset.svg;
            document.getElementById('icono-svg-label').textContent = this.dataset.label;
            pintarSvg(this.dataset.svg);
        });
    });

    document.querySelectorAll('.btn-pick-color').forEach(btn => btn.addEventListener('click', () => setColor(btn.dataset.color)));
    inColor.addEventListener('input', () => setColor(inColor.value));
    inHex.addEventListener('input', () => setColor(inHex.value.startsWith('#') ? inHex.value : '#' + inHex.value));
    inNombre.addEventListener('input', pintarNombre);

    document.querySelectorAll('.btn-pick-icon').forEach(btn => btn.addEventListener('click', () => {
        inIcono.value = btn.dataset.icon;
        document.getElementById('emoji-actual').textContent = btn.dataset.icon;
    }));
    inIcono.addEventListener('input', () => document.getElementById('emoji-actual').textContent = inIcono.value);
});
</script>
