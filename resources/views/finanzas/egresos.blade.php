@extends('layouts.app')
@section('title', 'Gastos')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Gastos e inversiones</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Los gastos habituales están precargados. La descripción y el monto se editan en la lista y eso no registra un pago. El pago se carga abajo, con el monto que salió, el documento y la factura.</p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="px-3 py-2">Gasto habitual</th>
                    <th class="px-3 py-2">Monto</th>
                    <th class="px-3 py-2">Staff</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($plantillas as $plantilla)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2">
                            <input form="plantilla-{{ $plantilla->id }}" name="nombre" value="{{ $plantilla->nombre }}" required maxlength="160" aria-label="Descripción de {{ $plantilla->nombre }}" class="w-full min-w-[12rem] px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                            @if($plantilla->es_sueldo)<span class="ml-1 text-xs text-emerald-700">Sueldo</span>@endif
                        </td>
                        <td class="px-3 py-2" colspan="3">
                            <form id="plantilla-{{ $plantilla->id }}" method="post" action="{{ route('finanzas.egresos.plantilla', $plantilla) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input name="monto" value="{{ $fmt($plantilla->monto) }}" class="w-36 px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800" required>
                                @if($plantilla->es_sueldo)
                                    <select name="usuario_staff_id" class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                                        <option value="">Sin persona</option>
                                        @foreach($staff as $persona)
                                            <option value="{{ $persona->usuario_id }}" @selected($plantilla->usuario_staff_id == $persona->usuario_id)>{{ $persona->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-700 text-xs">Guardar</button>
                                <button type="button" class="px-3 py-1 rounded bg-emerald-600 text-white text-xs" data-cargar="{{ $plantilla->id }}" data-monto="{{ $fmt($plantilla->monto) }}" data-staff="{{ $plantilla->usuario_staff_id }}" data-sueldo="{{ $plantilla->es_sueldo ? '1' : '0' }}">Usar en el pago</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="post" action="{{ route('finanzas.egresos.plantillas.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6">
        @csrf
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[14rem]">
                <label class="text-sm" for="nuevo-nombre">Descripción</label>
                <input id="nuevo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="160" class="{{ $cls }}" placeholder="Nuevo gasto habitual">
            </div>
            <div>
                <label class="text-sm" for="nuevo-monto">Monto</label>
                <input id="nuevo-monto" name="monto" value="{{ old('monto') }}" required class="mt-1 w-36 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm" placeholder="0">
            </div>
            <label class="flex items-center gap-2 text-sm pb-2">
                <input type="checkbox" name="es_sueldo" id="nuevo-sueldo" value="1" @checked(old('es_sueldo'))> Sueldo
            </label>
            <div id="nuevo-staff" class="{{ old('es_sueldo') ? '' : 'hidden' }}">
                <label class="text-sm" for="nuevo-staff-select">Staff</label>
                <select id="nuevo-staff-select" name="usuario_staff_id" class="{{ $cls }}">
                    <option value="">Elegir persona</option>
                    @foreach($staff as $persona)
                        <option value="{{ $persona->usuario_id }}" @selected(old('usuario_staff_id') == $persona->usuario_id)>{{ $persona->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Agregar gasto habitual</button>
        </div>
    </form>

    <form method="post" action="{{ route('finanzas.egresos.store') }}" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        <div>
            <label class="text-sm">Gasto habitual</label>
            <select name="plantilla_id" id="plantilla" class="{{ $cls }}">
                <option value="">Otro gasto, sin plantilla</option>
                @foreach($plantillas as $plantilla)
                    <option value="{{ $plantilla->id }}" data-monto="{{ $fmt($plantilla->monto) }}" data-staff="{{ $plantilla->usuario_staff_id }}" data-sueldo="{{ $plantilla->es_sueldo ? '1' : '0' }}">{{ $plantilla->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-sm">Cuenta</label><select name="cuenta_id" class="{{ $cls }}" required>@foreach($cuentas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach</select></div>
        <div><label class="text-sm">Categoría</label><select name="categoria_id" class="{{ $cls }}" required>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected($c->nombre === 'Gasto operativo')>{{ strtoupper($c->clase) }} — {{ $c->nombre }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-sm">Nueva categoría</label><input name="nueva_categoria" class="{{ $cls }}" placeholder="Opcional"></div>
            <div><label class="text-sm">Clase</label><select name="nueva_clase" class="{{ $cls }}"><option value="opex">OPEX</option><option value="capex">CAPEX</option></select></div>
        </div>
        <div><label class="text-sm">Importe que salió</label><input name="importe" id="importe" required class="{{ $cls }}" placeholder="Se puede cambiar respecto al monto habitual"></div>
        <div id="staff-pago" class="hidden">
            <label class="text-sm">Staff del sueldo</label>
            <select name="usuario_staff_id" id="staff" class="{{ $cls }}">
                <option value="">Elegir persona</option>
                @foreach($staff as $persona)
                    <option value="{{ $persona->usuario_id }}">{{ $persona->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-sm">Fecha de pago</label><input type="date" name="fecha" required value="{{ now()->toDateString() }}" class="{{ $cls }}"></div>
        <div><label class="text-sm">Proveedor</label><select name="proveedor_id" class="{{ $cls }}"><option value="">Sin proveedor</option>@foreach($proveedores as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach</select></div>
        <div><label class="text-sm">Número de comprobante</label><input name="comprobante" class="{{ $cls }}"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-sm">Documento</label><input type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp" class="{{ $cls }}"></div>
            <div><label class="text-sm">Factura</label><input type="file" name="factura_archivo" accept=".pdf,.jpg,.jpeg,.png,.webp" class="{{ $cls }}"></div>
        </div>
        <div><label class="text-sm">Observaciones</label><textarea name="observaciones" class="{{ $cls }}" rows="2"></textarea></div>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Registrar egreso</button>
    </form>
    <div class="mt-6 space-y-2 text-sm">
        @foreach($filas as $fila)
            @php
                $modoArchivo = function (?string $path): ?string {
                    $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        return 'pdf';
                    }
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        return 'imagen';
                    }

                    return null;
                };
                $archivos = [];
                foreach (['documento' => 'Documento', 'factura' => 'Factura'] as $tipoArchivo => $etiquetaArchivo) {
                    $rutaArchivo = $fila->{$tipoArchivo.'_path'};
                    if (! $rutaArchivo) {
                        continue;
                    }
                    $modo = $modoArchivo($rutaArchivo);
                    $archivos[] = [
                        'etiqueta' => $etiquetaArchivo,
                        'descargar' => route('finanzas.egresos.archivo', [$fila, $tipoArchivo]),
                        'ver' => $modo ? route('finanzas.egresos.archivo', [$fila, $tipoArchivo, 'ver' => 1]) : null,
                        'modo' => $modo,
                    ];
                }
                $detalle = [
                    'titulo' => $fila->plantilla?->nombre ?? $fila->categoria?->nombre ?? 'Gasto',
                    'campos' => array_values(array_filter([
                        ['Fecha', $fila->fecha?->format('d/m/Y')],
                        ['Concepto', $fila->plantilla?->nombre],
                        ['Categoría', $fila->categoria ? strtoupper($fila->categoria->clase).' — '.$fila->categoria->nombre : null],
                        ['Cuenta', $fila->cuenta?->nombre],
                        ['Importe', $fmt($fila->importe).' Gs'],
                        ['Staff', $fila->staff?->name],
                        ['Proveedor', $fila->proveedor?->nombre],
                        ['Comprobante', $fila->comprobante],
                        ['Registró', $fila->usuario?->name],
                        ['Observaciones', $fila->observaciones],
                    ], fn ($par) => filled($par[1]))),
                    'archivos' => $archivos,
                ];
            @endphp
            <div class="flex items-center justify-between gap-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">
                <div class="min-w-0">
                    {{ $fila->fecha?->format('d/m/Y') }} · {{ $fila->plantilla?->nombre ?? $fila->categoria?->nombre }} · {{ $fila->cuenta?->nombre }} · {{ $fmt($fila->importe) }}
                    @if($fila->staff) · {{ $fila->staff->name }} @endif
                    @foreach($archivos as $archivo)
                        · <a class="underline" href="{{ $archivo['descargar'] }}" @if($archivo['ver']) data-ver="{{ $archivo['ver'] }}" data-modo="{{ $archivo['modo'] }}" data-titulo="{{ $archivo['etiqueta'] }}" @endif>{{ $archivo['etiqueta'] }}</a>
                    @endforeach
                </div>
                <button type="button" class="shrink-0 rounded bg-gray-200 px-3 py-1 text-xs dark:bg-gray-700" data-detalle="{{ json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">Ver detalle</button>
            </div>
        @endforeach
    </div>
</div>
<div id="detalle-gasto" class="hidden fixed inset-x-0 bottom-0 top-16 z-[70] overflow-y-auto bg-black/70 p-4 sm:p-6">
    <div class="mx-auto max-w-3xl overflow-hidden rounded-xl bg-white dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 id="detalle-titulo" class="text-sm font-semibold text-gray-900 dark:text-gray-100">Detalle del gasto</h2>
            <button type="button" id="detalle-cerrar" class="rounded bg-gray-200 px-3 py-1 text-sm dark:bg-gray-700">Cerrar</button>
        </div>
        <dl id="detalle-campos" class="grid gap-3 p-4 text-sm sm:grid-cols-2"></dl>
        <div id="detalle-archivos" class="space-y-4 px-4 pb-4"></div>
    </div>
</div>
<div id="visor-factura" class="hidden fixed inset-x-0 bottom-0 top-16 z-[80] bg-black/70 p-4 sm:p-6">
    <div class="mx-auto flex h-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-700">
            <span id="visor-titulo" class="text-sm font-medium text-gray-900 dark:text-gray-100">Archivo</span>
            <div class="flex items-center gap-4 text-sm">
                <a id="visor-descargar" class="underline" href="#">Descargar</a>
                <button type="button" id="visor-cerrar" class="rounded bg-gray-200 px-3 py-1 dark:bg-gray-700">Cerrar</button>
            </div>
        </div>
        <iframe id="visor-frame" class="min-h-0 w-full flex-1 bg-gray-100" title="Archivo"></iframe>
        <img id="visor-imagen" alt="" class="hidden min-h-0 w-full flex-1 object-contain bg-gray-100">
    </div>
</div>
<script>
(function () {
    const plantilla = document.getElementById('plantilla');
    const importe = document.getElementById('importe');
    const staffPago = document.getElementById('staff-pago');
    const staff = document.getElementById('staff');
    function aplicar(origen) {
        if (!origen) return;
        const sueldo = origen.dataset.sueldo === '1';
        if (origen.dataset.monto) importe.value = origen.dataset.monto;
        staff.value = origen.dataset.staff || '';
        staffPago.classList.toggle('hidden', !sueldo);
        staff.required = sueldo;
    }
    plantilla.addEventListener('change', function () {
        aplicar(plantilla.selectedOptions[0]);
    });
    document.querySelectorAll('[data-cargar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            plantilla.value = boton.dataset.cargar;
            aplicar(boton);
            importe.focus();
        });
    });
    const nuevoSueldo = document.getElementById('nuevo-sueldo');
    const nuevoStaff = document.getElementById('nuevo-staff');
    const nuevoStaffSelect = document.getElementById('nuevo-staff-select');
    nuevoSueldo.addEventListener('change', function () {
        nuevoStaff.classList.toggle('hidden', !nuevoSueldo.checked);
        nuevoStaffSelect.required = nuevoSueldo.checked;
    });
    const visor = document.getElementById('visor-factura');
    const visorFrame = document.getElementById('visor-frame');
    const visorImagen = document.getElementById('visor-imagen');
    const visorTitulo = document.getElementById('visor-titulo');
    const visorDescargar = document.getElementById('visor-descargar');
    const detalle = document.getElementById('detalle-gasto');
    const detalleTitulo = document.getElementById('detalle-titulo');
    const detalleCampos = document.getElementById('detalle-campos');
    const detalleArchivos = document.getElementById('detalle-archivos');
    function cerrarVisor() {
        visor.classList.add('hidden');
        visorFrame.src = 'about:blank';
        visorImagen.removeAttribute('src');
    }
    function abrirVisor(urlVer, urlDescarga, modo, titulo) {
        visorTitulo.textContent = titulo || 'Archivo';
        visorDescargar.href = urlDescarga;
        const esImagen = modo === 'imagen';
        visorFrame.classList.toggle('hidden', esImagen);
        visorImagen.classList.toggle('hidden', !esImagen);
        if (esImagen) {
            visorFrame.src = 'about:blank';
            visorImagen.src = urlVer;
        } else {
            visorImagen.removeAttribute('src');
            visorFrame.src = urlVer;
        }
        visor.classList.remove('hidden');
    }
    document.querySelectorAll('[data-ver]').forEach(function (enlace) {
        enlace.addEventListener('click', function (evento) {
            evento.preventDefault();
            abrirVisor(enlace.dataset.ver, enlace.href, enlace.dataset.modo, enlace.dataset.titulo);
        });
    });
    function abrirDetalle(datos) {
        detalleTitulo.textContent = datos.titulo || 'Detalle del gasto';
        detalleCampos.replaceChildren();
        (datos.campos || []).forEach(function (par) {
            const bloque = document.createElement('div');
            if (par[0] === 'Observaciones') bloque.className = 'sm:col-span-2';
            const etiqueta = document.createElement('dt');
            etiqueta.className = 'text-gray-500';
            etiqueta.textContent = par[0];
            const valor = document.createElement('dd');
            valor.className = 'whitespace-pre-wrap text-gray-900 dark:text-gray-100';
            valor.textContent = par[1];
            bloque.append(etiqueta, valor);
            detalleCampos.append(bloque);
        });
        detalleArchivos.replaceChildren();
        (datos.archivos || []).forEach(function (archivo) {
            const bloque = document.createElement('div');
            const cabeza = document.createElement('div');
            cabeza.className = 'mb-2 flex items-center justify-between text-sm';
            const nombre = document.createElement('span');
            nombre.className = 'font-medium text-gray-900 dark:text-gray-100';
            nombre.textContent = archivo.etiqueta;
            const descarga = document.createElement('a');
            descarga.className = 'underline';
            descarga.href = archivo.descargar;
            descarga.textContent = 'Descargar';
            cabeza.append(nombre, descarga);
            bloque.append(cabeza);
            if (archivo.ver && archivo.modo === 'imagen') {
                const img = document.createElement('img');
                img.src = archivo.ver;
                img.alt = archivo.etiqueta;
                img.className = 'max-h-[28rem] w-full rounded border border-gray-200 object-contain bg-gray-100 dark:border-gray-700 dark:bg-gray-800';
                bloque.append(img);
            } else if (archivo.ver) {
                const frame = document.createElement('iframe');
                frame.src = archivo.ver;
                frame.title = archivo.etiqueta;
                frame.className = 'h-[28rem] w-full rounded border border-gray-200 bg-gray-100';
                bloque.append(frame);
            }
            detalleArchivos.append(bloque);
        });
        detalle.classList.remove('hidden');
    }
    document.querySelectorAll('[data-detalle]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirDetalle(JSON.parse(boton.dataset.detalle));
        });
    });
    function cerrarDetalle() {
        detalle.classList.add('hidden');
        detalleArchivos.replaceChildren();
    }
    document.getElementById('detalle-cerrar').addEventListener('click', cerrarDetalle);
    detalle.addEventListener('click', function (evento) {
        if (evento.target === detalle) cerrarDetalle();
    });
    document.getElementById('visor-cerrar').addEventListener('click', cerrarVisor);
    visor.addEventListener('click', function (evento) {
        if (evento.target === visor) cerrarVisor();
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') return;
        if (!visor.classList.contains('hidden')) cerrarVisor();
        else if (!detalle.classList.contains('hidden')) cerrarDetalle();
    });
})();
</script>
@endsection
