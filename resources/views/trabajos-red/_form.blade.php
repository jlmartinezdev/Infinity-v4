@csrf
@isset($trabajo)
    @method('PUT')
@endisset

@php
    $trabajo = $trabajo ?? null;
    $activoInicial = $trabajo?->activo;
    $activoLabelInicial = $activoInicial
        ? \App\Models\TrabajoRed::etiquetaDeModelo($activoInicial, (string) $trabajo->activo_tipo)
        : '';
@endphp

<div class="space-y-6" id="trabajo-red-form"
    data-activos-url="{{ route('trabajos-red.activos') }}"
    data-maps-key="{{ $googleMapsApiKey ?? '' }}"
    data-nodos-coords="{{ e(json_encode($nodosCoords ?? [])) }}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo *</label>
            <select name="tipo" id="tipo" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Seleccione</option>
                @foreach (\App\Models\TrabajoRed::tipos() as $key => $label)
                    <option value="{{ $key }}" @selected(old('tipo', $trabajo?->tipo) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('tipo')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="prioridad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad</label>
            <select name="prioridad" id="prioridad"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach (\App\Models\TrabajoRed::prioridades() as $key => $label)
                    <option value="{{ $key }}" @selected(old('prioridad', $trabajo?->prioridad ?? 'media') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('prioridad')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="titulo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título *</label>
        <input type="text" name="titulo" id="titulo" required maxlength="180"
            value="{{ old('titulo', $trabajo?->titulo) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Ej. Cambio de SFP en OLT-UA">
        @error('titulo')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nodo *</label>
            <select name="nodo_id" id="nodo_id" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Seleccione un nodo</option>
                @foreach ($nodos as $nodo)
                    <option value="{{ $nodo->nodo_id }}" @selected((string) old('nodo_id', $trabajo?->nodo_id) === (string) $nodo->nodo_id)>{{ $nodo->descripcion }}</option>
                @endforeach
            </select>
            @error('nodo_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="activo_tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de activo *</label>
            <select name="activo_tipo" id="activo_tipo" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Seleccione</option>
                @foreach (\App\Models\TrabajoRed::activoTipos() as $key => $label)
                    <option value="{{ $key }}" @selected(old('activo_tipo', $trabajo?->activo_tipo) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('activo_tipo')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="activo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activo *</label>
        <select name="activo_id" id="activo_id" required
            data-initial-id="{{ old('activo_id', $trabajo?->activo_id) }}"
            data-initial-label="{{ $activoLabelInicial }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <option value="">Primero elija nodo y tipo de activo</option>
        </select>
        @error('activo_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Qué hay que hacer en la red...">{{ old('descripcion', $trabajo?->descripcion) }}</textarea>
        @error('descripcion')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Trazado (desde / hasta)</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Para cableado u otro recorrido: punto de origen y destino. Podés pegar GPS o marcarlo en el mapa.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Desde</p>
                <div>
                    <label for="ubicacion_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Referencia</label>
                    <input type="text" name="ubicacion_desde" id="ubicacion_desde" maxlength="180"
                        value="{{ old('ubicacion_desde', $trabajo?->ubicacion_desde) }}"
                        placeholder="Ej. NAP-12, nodo N6, poste esquina"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('ubicacion_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label for="url_ubicacion_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ubicación GPS</label>
                        <button type="button" class="btn-mapa-punto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-sky-800 dark:text-sky-200 border border-sky-300 dark:border-sky-700 hover:bg-sky-50 dark:hover:bg-sky-950/40" data-punto="desde">
                            Seleccionar en mapa
                        </button>
                    </div>
                    <input type="text" name="url_ubicacion_desde" id="url_ubicacion_desde" maxlength="500"
                        value="{{ old('url_ubicacion_desde', $trabajo?->url_ubicacion_desde) }}"
                        placeholder="https://maps.google.com/... o lat, lon"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('url_ubicacion_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Hasta</p>
                <div>
                    <label for="ubicacion_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Referencia</label>
                    <input type="text" name="ubicacion_hasta" id="ubicacion_hasta" maxlength="180"
                        value="{{ old('ubicacion_hasta', $trabajo?->ubicacion_hasta) }}"
                        placeholder="Ej. NAP-20, casa del cliente"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('ubicacion_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label for="url_ubicacion_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ubicación GPS</label>
                        <button type="button" class="btn-mapa-punto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40" data-punto="hasta">
                            Seleccionar en mapa
                        </button>
                    </div>
                    <input type="text" name="url_ubicacion_hasta" id="url_ubicacion_hasta" maxlength="500"
                        value="{{ old('url_ubicacion_hasta', $trabajo?->url_ubicacion_hasta) }}"
                        placeholder="https://maps.google.com/... o lat, lon"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('url_ubicacion_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div id="trabajo-mapa-wrap" class="hidden space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    Click en el mapa para marcar <span id="trabajo-mapa-punto-label" class="font-semibold">Desde</span>.
                    El pin se puede arrastrar.
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" id="trabajo-mapa-satelite" class="text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Satélite</button>
                    <button type="button" id="trabajo-mapa-cerrar" class="text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cerrar mapa</button>
                </div>
            </div>
            <div id="trabajo-mapa" class="w-full h-80 rounded-xl border border-gray-300 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-900"></div>
            <p id="trabajo-mapa-error" class="hidden text-sm text-amber-800 dark:text-amber-200"></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach (\App\Models\TrabajoRed::estados() as $key => $label)
                    <option value="{{ $key }}" @selected(old('estado', $trabajo?->estado ?? 'pendiente') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('estado')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="asignado_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asignado a</label>
            <select name="asignado_id" id="asignado_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Sin asignar</option>
                @foreach ($tecnicos as $t)
                    <option value="{{ $t->usuario_id }}" @selected((string) old('asignado_id', $trabajo?->asignado_id) === (string) $t->usuario_id)>{{ $t->name }}</option>
                @endforeach
            </select>
            @error('asignado_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="imagen" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Imagen</label>
        @if($trabajo?->imagen)
            <div class="mb-2">
                <img src="{{ asset('storage/'.$trabajo->imagen) }}" alt="Imagen del trabajo" class="max-h-40 rounded-lg border border-gray-200 dark:border-gray-600 object-cover">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Imagen actual. Suba otra para reemplazar.</p>
            </div>
        @endif
        <input type="file" name="imagen" id="imagen" accept="image/*"
            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        @error('imagen')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Notas internas...">{{ old('observaciones', $trabajo?->observaciones) }}</textarea>
        @error('observaciones')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-wrap gap-3 items-center">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $trabajo ? 'Actualizar trabajo' : 'Crear trabajo' }}
        </button>
        <a href="{{ route('trabajos-red.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
            Cancelar
        </a>
        @if($trabajo && auth()->user()?->tienePermiso('trabajos-red.eliminar'))
            <button type="submit" form="trabajo-eliminar-form"
                class="inline-flex items-center px-4 py-2 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 rounded-lg font-medium border border-red-200 dark:border-red-800 hover:bg-red-100"
                onclick="return confirm('¿Eliminar este trabajo?');">
                Eliminar
            </button>
        @endif
    </div>
</div>

@if($trabajo && auth()->user()?->tienePermiso('trabajos-red.eliminar'))
<form id="trabajo-eliminar-form" action="{{ route('trabajos-red.destroy', $trabajo) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endif

<script>
(function () {
    var root = document.getElementById('trabajo-red-form');
    if (!root) return;
    var url = root.getAttribute('data-activos-url');
    var nodo = document.getElementById('nodo_id');
    var tipo = document.getElementById('activo_tipo');
    var activo = document.getElementById('activo_id');
    var initialId = activo.getAttribute('data-initial-id') || '';
    var initialLabel = activo.getAttribute('data-initial-label') || '';

    function cargar() {
        var nodoId = nodo.value;
        var tipoVal = tipo.value;
        activo.innerHTML = '<option value="">Cargando…</option>';
        if (!nodoId || !tipoVal) {
            activo.innerHTML = '<option value="">Primero elija nodo y tipo de activo</option>';
            return;
        }
        fetch(url + '?nodo_id=' + encodeURIComponent(nodoId) + '&activo_tipo=' + encodeURIComponent(tipoVal), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            var items = (data && data.items) ? data.items : [];
            activo.innerHTML = '<option value="">Seleccione un activo</option>';
            items.forEach(function (item) {
                var opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.label;
                if (String(item.id) === String(initialId)) opt.selected = true;
                activo.appendChild(opt);
            });
            if (initialId && !activo.value && initialLabel) {
                var fallback = document.createElement('option');
                fallback.value = initialId;
                fallback.textContent = initialLabel;
                fallback.selected = true;
                activo.appendChild(fallback);
            }
        }).catch(function () {
            activo.innerHTML = '<option value="">No se pudieron cargar los activos</option>';
        });
    }

    nodo.addEventListener('change', function () { initialId = ''; cargar(); });
    tipo.addEventListener('change', function () { initialId = ''; cargar(); });
    cargar();

    var mapsKey = root.getAttribute('data-maps-key') || '';
    var nodosCoords = {};
    try { nodosCoords = JSON.parse(root.getAttribute('data-nodos-coords') || '{}') || {}; } catch (e) { nodosCoords = {}; }
    var wrapMapa = document.getElementById('trabajo-mapa-wrap');
    var mapEl = document.getElementById('trabajo-mapa');
    var mapErr = document.getElementById('trabajo-mapa-error');
    var labelPunto = document.getElementById('trabajo-mapa-punto-label');
    var inputDesde = document.getElementById('url_ubicacion_desde');
    var inputHasta = document.getElementById('url_ubicacion_hasta');
    var puntoActivo = 'desde';
    var map = null;
    var markers = { desde: null, hasta: null };
    var line = null;
    var DEFAULT_CENTER = { lat: -25.2637, lng: -57.5759 };

    function parseCoords(texto) {
        var t = String(texto || '').trim();
        if (!t) return null;
        var m = t.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/)
            || t.match(/[?&](?:q|ll|query)=(-?\d+\.?\d*),(-?\d+\.?\d*)/)
            || t.match(/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/)
            || t.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/);
        if (!m) return null;
        var lat = parseFloat(m[1]);
        var lon = parseFloat(m[2]);
        if (isNaN(lat) || isNaN(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) return null;
        return { lat: lat, lng: lon };
    }

    function gpsUrl(lat, lng) {
        return 'https://www.google.com/maps?q=' + Number(lat).toFixed(6) + ',' + Number(lng).toFixed(6);
    }

    function coordsNodo() {
        var raw = nodosCoords[String(nodo.value)] || null;
        if (!raw) return null;
        var lat = Number(raw.lat);
        var lon = Number(raw.lon != null ? raw.lon : raw.lng);
        if (!isFinite(lat) || !isFinite(lon)) return null;
        return { lat: lat, lng: lon };
    }

    function inputDe(punto) {
        return punto === 'hasta' ? inputHasta : inputDesde;
    }

    function colorDe(punto) {
        return punto === 'hasta' ? '#059669' : '#0284c7';
    }

    function setGps(punto, lat, lng) {
        var input = inputDe(punto);
        if (!input) return;
        input.value = gpsUrl(lat, lng);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function pinIcon(color) {
        return {
            path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z',
            fillColor: color,
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 1.5,
            scale: 1.6,
            anchor: new google.maps.Point(12, 22),
        };
    }

    function actualizarLinea() {
        if (!map || !window.google) return;
        var a = markers.desde ? markers.desde.getPosition() : null;
        var b = markers.hasta ? markers.hasta.getPosition() : null;
        if (!a || !b) {
            if (line) { line.setMap(null); line = null; }
            return;
        }
        if (!line) {
            line = new google.maps.Polyline({
                map: map,
                strokeColor: '#7c3aed',
                strokeOpacity: 0.85,
                strokeWeight: 3,
            });
        }
        line.setPath([a, b]);
    }

    function colocarMarker(punto, pos, pan) {
        if (!map || !window.google) return;
        if (markers[punto]) {
            markers[punto].setPosition(pos);
        } else {
            markers[punto] = new google.maps.Marker({
                map: map,
                position: pos,
                draggable: true,
                title: punto === 'hasta' ? 'Hasta' : 'Desde',
                icon: pinIcon(colorDe(punto)),
            });
            markers[punto].addListener('dragend', function () {
                var p = markers[punto].getPosition();
                if (p) {
                    setGps(punto, p.lat(), p.lng());
                    actualizarLinea();
                }
            });
        }
        if (pan) {
            map.panTo(pos);
            if (map.getZoom() < 15) map.setZoom(16);
        }
        actualizarLinea();
    }

    function centroInicial(punto) {
        var actual = parseCoords(inputDe(punto) && inputDe(punto).value);
        if (actual) return { pos: actual, zoom: 16 };
        var otro = parseCoords(inputDe(punto === 'hasta' ? 'desde' : 'hasta') && inputDe(punto === 'hasta' ? 'desde' : 'hasta').value);
        if (otro) return { pos: otro, zoom: 15 };
        var nodoPos = coordsNodo();
        if (nodoPos) return { pos: nodoPos, zoom: 14 };
        return { pos: DEFAULT_CENTER, zoom: 7 };
    }

    function loadGoogleMaps() {
        return new Promise(function (resolve, reject) {
            if (window.google && window.google.maps) {
                resolve(window.google);
                return;
            }
            if (!mapsKey) {
                reject(new Error('Falta GOOGLE_MAPS_API_KEY en .env para usar el mapa.'));
                return;
            }
            var scriptId = 'google-maps-api-trabajo-red';
            if (document.getElementById(scriptId)) {
                var n = 0;
                var check = setInterval(function () {
                    n++;
                    if (window.google && window.google.maps) {
                        clearInterval(check);
                        resolve(window.google);
                    } else if (n > 50) {
                        clearInterval(check);
                        reject(new Error('No se pudo cargar Google Maps.'));
                    }
                }, 100);
                return;
            }
            window.__trabajoRedMapaReady__ = function () { resolve(window.google); };
            var script = document.createElement('script');
            script.id = scriptId;
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapsKey) + '&callback=__trabajoRedMapaReady__';
            script.async = true;
            script.defer = true;
            script.onerror = function () { reject(new Error('No se pudo cargar Google Maps.')); };
            document.head.appendChild(script);
        });
    }

    function initMap() {
        var start = centroInicial(puntoActivo);
        map = new google.maps.Map(mapEl, {
            center: start.pos,
            zoom: start.zoom,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
        });
        map.addListener('click', function (e) {
            if (!e.latLng) return;
            setGps(puntoActivo, e.latLng.lat(), e.latLng.lng());
            colocarMarker(puntoActivo, e.latLng, false);
        });
        ['desde', 'hasta'].forEach(function (punto) {
            var c = parseCoords(inputDe(punto) && inputDe(punto).value);
            if (c) colocarMarker(punto, c, false);
        });
        if (markers.desde && markers.hasta) {
            var bounds = new google.maps.LatLngBounds();
            bounds.extend(markers.desde.getPosition());
            bounds.extend(markers.hasta.getPosition());
            map.fitBounds(bounds, 48);
        }
    }

    function marcarBotonActivo(punto) {
        root.querySelectorAll('.btn-mapa-punto').forEach(function (btn) {
            var activo = (btn.getAttribute('data-punto') || '') === punto;
            btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
            btn.classList.toggle('ring-2', activo);
            btn.classList.toggle('ring-offset-1', activo);
            btn.classList.toggle('ring-purple-500', activo);
        });
    }

    function mostrarMapa(punto) {
        puntoActivo = punto;
        marcarBotonActivo(punto);
        if (labelPunto) labelPunto.textContent = punto === 'hasta' ? 'Hasta' : 'Desde';
        if (wrapMapa) wrapMapa.classList.remove('hidden');
        if (mapErr) {
            mapErr.classList.add('hidden');
            mapErr.textContent = '';
        }
        loadGoogleMaps().then(function () {
            if (!map) initMap();
            setTimeout(function () {
                if (!map || !window.google) return;
                google.maps.event.trigger(map, 'resize');
                var c = parseCoords(inputDe(punto) && inputDe(punto).value) || coordsNodo();
                if (c) {
                    map.panTo(c);
                    if (map.getZoom() < 14) map.setZoom(16);
                }
            }, 80);
        }).catch(function (err) {
            if (mapErr) {
                mapErr.textContent = err.message || 'No se pudo abrir el mapa.';
                mapErr.classList.remove('hidden');
            }
        });
        if (wrapMapa) wrapMapa.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    root.querySelectorAll('.btn-mapa-punto').forEach(function (btn) {
        btn.addEventListener('click', function () {
            mostrarMapa(btn.getAttribute('data-punto') || 'desde');
        });
    });
    var btnCerrar = document.getElementById('trabajo-mapa-cerrar');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            if (wrapMapa) wrapMapa.classList.add('hidden');
        });
    }
    var btnSat = document.getElementById('trabajo-mapa-satelite');
    if (btnSat) {
        btnSat.addEventListener('click', function () {
            if (!map) return;
            var next = map.getMapTypeId() === 'satellite' ? 'roadmap' : 'satellite';
            map.setMapTypeId(next);
            btnSat.textContent = next === 'satellite' ? 'Mapa' : 'Satélite';
        });
    }
    [inputDesde, inputHasta].forEach(function (input, idx) {
        if (!input) return;
        input.addEventListener('change', function () {
            if (!map) return;
            var punto = idx === 1 ? 'hasta' : 'desde';
            var c = parseCoords(input.value);
            if (c) colocarMarker(punto, c, true);
        });
    });
})();
</script>
