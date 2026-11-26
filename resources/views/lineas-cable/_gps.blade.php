@php
    $coordsGps = (isset($lineaCable) && is_array($lineaCable->coordenadas)) ? array_values($lineaCable->coordenadas) : [];
    $fmtGps = function ($n) {
        if ($n === null || $n === '') {
            return '';
        }
        $s = number_format((float) $n, 6, '.', '');

        return rtrim(rtrim($s, '0'), '.');
    };
    $finPunto = count($coordsGps) >= 2 ? $coordsGps[count($coordsGps) - 1] : [null, null];
    $inicioLat = old('inicio_lat', $fmtGps($coordsGps[0][0] ?? null));
    $inicioLon = old('inicio_lon', $fmtGps($coordsGps[0][1] ?? null));
    $finLat = old('fin_lat', $fmtGps($finPunto[0] ?? null));
    $finLon = old('fin_lon', $fmtGps($finPunto[1] ?? null));
    $mapaKey = (string) config('services.google.maps_key');
@endphp

<div>
    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-2 mb-4 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
        Punto de inicio y punto de fin (GPS)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        Marcá el inicio y el fin. Google Maps dibuja el cable siguiendo la calle y ese recorrido queda guardado para el mapa de infraestructura.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/40 dark:bg-gray-700 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h4 class="text-sm font-bold text-emerald-900 dark:text-emerald-300">Inicio</h4>
                <button type="button" data-gps-mio="inicio" class="h-8 px-2.5 rounded-lg border border-emerald-300 dark:border-emerald-700 text-xs font-semibold text-emerald-800 dark:text-emerald-200 bg-white dark:bg-gray-800">Usar mi GPS</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="inicio_lat" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Latitud</label>
                    <input type="text" inputmode="decimal" name="inicio_lat" id="inicio_lat" value="{{ $inicioLat }}" placeholder="Ej. -26.60"
                        class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label for="inicio_lon" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Longitud</label>
                    <input type="text" inputmode="decimal" name="inicio_lon" id="inicio_lon" value="{{ $inicioLon }}" placeholder="Ej. -56.45"
                        class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border border-purple-200 dark:border-purple-800/60 bg-purple-50/30 dark:bg-gray-700 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h4 class="text-sm font-bold text-purple-900 dark:text-purple-300">Fin</h4>
                <button type="button" data-gps-mio="fin" class="h-8 px-2.5 rounded-lg border border-purple-300 dark:border-purple-700 text-xs font-semibold text-purple-800 dark:text-purple-200 bg-white dark:bg-gray-800">Usar mi GPS</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="fin_lat" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Latitud</label>
                    <input type="text" inputmode="decimal" name="fin_lat" id="fin_lat" value="{{ $finLat }}" placeholder="Ej. -26.61"
                        class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label for="fin_lon" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Longitud</label>
                    <input type="text" inputmode="decimal" name="fin_lon" id="fin_lon" value="{{ $finLon }}" placeholder="Ej. -56.46"
                        class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>
        </div>
    </div>

    @if($mapaKey !== '')
        <div class="mt-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">El primer clic marca el inicio y el siguiente el fin. Al tener los dos puntos, el cable sigue la ruta por la calle.</p>
            <div id="mapa-cable-gps" class="h-80 rounded-xl border border-gray-200 dark:border-gray-700"></div>
        </div>
    @endif
    <div id="ruta-cable-puntos"></div>
    <p id="gps-cable-ruta" class="text-xs text-cyan-700 dark:text-cyan-300 mt-2 hidden"></p>
    <p id="gps-cable-aviso" class="text-xs text-rose-600 dark:text-rose-300 mt-2 hidden"></p>
</div>

<script>
(function () {
    var campos = { inicio: ['inicio_lat', 'inicio_lon'], fin: ['fin_lat', 'fin_lon'] };
    var aviso = document.getElementById('gps-cable-aviso');
    var rutaTexto = document.getElementById('gps-cable-ruta');
    var rutaInicial = @json(array_values($coordsGps));

    function leer(punto) {
        var lat = parseFloat(document.getElementById(campos[punto][0]).value.replace(',', '.'));
        var lon = parseFloat(document.getElementById(campos[punto][1]).value.replace(',', '.'));
        if (!isFinite(lat) || !isFinite(lon)) return null;
        return { lat: lat, lng: lon };
    }

    function escribir(punto, lat, lng) {
        document.getElementById(campos[punto][0]).value = Number(lat).toFixed(6);
        document.getElementById(campos[punto][1]).value = Number(lng).toFixed(6);
    }

    function partirPegado(input) {
        var texto = (input.value || '').trim();
        var m = texto.match(/^(-?\d+(?:[.,]\d+)?)[\s,;]+(-?\d+(?:[.,]\d+)?)$/);
        if (!m) return;
        var lado = input.id.indexOf('fin') === 0 ? 'fin' : 'inicio';
        var lon = document.getElementById(campos[lado][1]);
        if (lon.value.trim() !== '') return;
        document.getElementById(campos[lado][0]).value = m[1].replace(',', '.');
        lon.value = m[2].replace(',', '.');
    }

    ['inicio_lat', 'inicio_lon', 'fin_lat', 'fin_lon'].forEach(function (id) {
        var input = document.getElementById(id);
        input.addEventListener('change', function () {
            if (id.indexOf('_lat') !== -1) partirPegado(input);
            var lado = id.indexOf('fin') === 0 ? 'fin' : 'inicio';
            var p = leer(lado);
            if (p && window.colocarPuntoCable) window.colocarPuntoCable(lado, p.lat, p.lng, true);
        });
    });

    document.querySelectorAll('[data-gps-mio]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var punto = btn.getAttribute('data-gps-mio');
            if (!navigator.geolocation) {
                aviso.textContent = 'Este navegador no entrega la ubicación.';
                aviso.classList.remove('hidden');
                return;
            }
            aviso.classList.add('hidden');
            navigator.geolocation.getCurrentPosition(function (pos) {
                escribir(punto, pos.coords.latitude, pos.coords.longitude);
                if (window.colocarPuntoCable) window.colocarPuntoCable(punto, pos.coords.latitude, pos.coords.longitude, true);
            }, function () {
                aviso.textContent = 'No se pudo leer el GPS. Revisá el permiso de ubicación.';
                aviso.classList.remove('hidden');
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    });

    @if($mapaKey !== '')
    var map, markers = {}, linea, siguiente = 'inicio', directions, trazoTimer = null, trazoSeq = 0;

    function pin(color) {
        return {
            path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z',
            fillColor: color,
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 1.5,
            scale: 1.5,
            anchor: new google.maps.Point(12, 22)
        };
    }

    function asegurarLinea() {
        if (!linea) {
            linea = new google.maps.Polyline({
                map: map,
                strokeColor: '#0891b2',
                strokeOpacity: 0.95,
                strokeWeight: 5
            });
        }
        return linea;
    }

    function listaDe(path) {
        if (!path) return [];
        if (typeof path.getLength === 'function') {
            var out = [];
            for (var i = 0; i < path.getLength(); i++) out.push(path.getAt(i));
            return out;
        }
        return path;
    }

    function guardarRuta(path) {
        var box = document.getElementById('ruta-cable-puntos');
        if (!box) return;
        var puntos = [];
        path.forEach(function (p) {
            var lat = typeof p.lat === 'function' ? p.lat() : p[0];
            var lng = typeof p.lng === 'function' ? p.lng() : p[1];
            puntos.push([Number(lat).toFixed(6), Number(lng).toFixed(6)]);
        });
        if (puntos.length > 150) {
            var reducido = [puntos[0]];
            var paso = (puntos.length - 1) / 149;
            for (var i = 1; i < 149; i++) reducido.push(puntos[Math.round(i * paso)]);
            reducido.push(puntos[puntos.length - 1]);
            puntos = reducido;
        }
        box.replaceChildren();
        puntos.forEach(function (p, i) {
            ['0', '1'].forEach(function (eje) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'coordenadas[' + i + '][' + eje + ']';
                input.value = p[Number(eje)];
                box.appendChild(input);
            });
        });
    }

    function mismoPunto(a, b) {
        return Math.abs(a - b) < 0.00002;
    }

    function extremosIguales(path, a, b) {
        if (!path || path.length < 2) return false;
        var ini = path[0];
        var fin = path[path.length - 1];
        return mismoPunto(ini[0], a.lat()) && mismoPunto(ini[1], a.lng())
            && mismoPunto(fin[0], b.lat()) && mismoPunto(fin[1], b.lng());
    }

    function mostrarRuta(texto) {
        aviso.classList.add('hidden');
        rutaTexto.textContent = texto;
        rutaTexto.classList.remove('hidden');
    }

    function mostrarAviso(texto) {
        rutaTexto.classList.add('hidden');
        aviso.textContent = texto;
        aviso.classList.remove('hidden');
    }

    function encuadrar(path) {
        var limites = new google.maps.LatLngBounds();
        path.forEach(function (p) { limites.extend(p); });
        map.fitBounds(limites);
    }

    function trazarRuta() {
        var a = markers.inicio ? markers.inicio.getPosition() : null;
        var b = markers.fin ? markers.fin.getPosition() : null;
        if (!a || !b) {
            if (linea) { linea.setMap(null); linea = null; }
            guardarRuta([]);
            rutaTexto.classList.add('hidden');
            return;
        }
        if (rutaInicial && rutaInicial.length > 2 && extremosIguales(rutaInicial, a, b)) {
            var guardada = rutaInicial.map(function (p) { return { lat: Number(p[0]), lng: Number(p[1]) }; });
            asegurarLinea().setPath(guardada);
            guardarRuta(rutaInicial);
            mostrarRuta('El cable sigue el recorrido guardado.');
            encuadrar(guardada);
            rutaInicial = null;
            return;
        }
        rutaInicial = null;
        if (!directions) directions = new google.maps.DirectionsService();
        var seq = ++trazoSeq;
        directions.route({
            origin: a,
            destination: b,
            travelMode: google.maps.TravelMode.DRIVING,
            region: 'py'
        }, function (resultado, estado) {
            if (seq !== trazoSeq) return;
            var pathOk = resultado && resultado.routes && resultado.routes[0] ? listaDe(resultado.routes[0].overview_path) : [];
            if (estado === 'OK' && pathOk.length >= 2) {
                var path = pathOk;
                asegurarLinea().setPath(path);
                guardarRuta(path);
                var metros = 0;
                (resultado.routes[0].legs || []).forEach(function (leg) {
                    metros += leg.distance ? leg.distance.value : 0;
                });
                var km = (metros / 1000).toFixed(2).replace('.', ',');
                mostrarRuta('El cable sigue la calle. Recorrido: ' + km + ' km.');
                encuadrar(path);
                return;
            }
            asegurarLinea().setPath([a, b]);
            guardarRuta([a, b]);
            encuadrar([a, b]);
            var mensaje = estado === 'REQUEST_DENIED'
                ? 'La clave de Google no tiene habilitada la API de rutas. El cable queda en línea recta.'
                : 'No hay una calle entre los dos puntos. El cable queda en línea recta.';
            mostrarAviso(mensaje);
        });
    }

    function programarRuta() {
        clearTimeout(trazoTimer);
        trazoTimer = setTimeout(trazarRuta, 300);
    }

    window.colocarPuntoCable = function (punto, lat, lng, pedirRuta) {
        if (!map) return;
        var pos = new google.maps.LatLng(lat, lng);
        if (markers[punto]) {
            markers[punto].setPosition(pos);
        } else {
            markers[punto] = new google.maps.Marker({
                map: map,
                position: pos,
                draggable: true,
                icon: pin(punto === 'inicio' ? '#059669' : '#7c3aed'),
                title: punto === 'inicio' ? 'Inicio' : 'Fin'
            });
            markers[punto].addListener('dragend', function (e) {
                escribir(punto, e.latLng.lat(), e.latLng.lng());
                programarRuta();
            });
        }
        if (pedirRuta) programarRuta();
        if (!markers.inicio || !markers.fin) {
            map.setCenter(pos);
            map.setZoom(16);
        }
    };

    window.iniciarMapaCable = function () {
        var el = document.getElementById('mapa-cable-gps');
        if (!el) return;
        map = new google.maps.Map(el, {
            center: { lat: -26.6, lng: -56.45 },
            zoom: 13,
            mapTypeControl: false,
            streetViewControl: false
        });
        var ini = leer('inicio');
        var fin = leer('fin');
        if (ini) window.colocarPuntoCable('inicio', ini.lat, ini.lng, false);
        if (fin) window.colocarPuntoCable('fin', fin.lat, fin.lng, false);
        if (ini && fin) {
            siguiente = 'inicio';
            programarRuta();
        }
        map.addListener('click', function (e) {
            escribir(siguiente, e.latLng.lat(), e.latLng.lng());
            window.colocarPuntoCable(siguiente, e.latLng.lat(), e.latLng.lng(), true);
            siguiente = siguiente === 'inicio' ? 'fin' : 'inicio';
        });
    };
    @endif
})();
</script>
@if($mapaKey !== '')
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $mapaKey }}&callback=iniciarMapaCable"></script>
@endif
