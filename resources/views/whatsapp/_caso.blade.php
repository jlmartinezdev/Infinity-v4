@php
    $opciones = $etiquetas;
    $actual = (string) ($caso->etiqueta ?? '');
    if ($actual !== '' && ! in_array($actual, $opciones, true)) {
        $opciones[] = $actual;
    }
    $prohibido = \App\Services\WhatsApp\Soporte\EstiloInterplus::textoProhibido(
        trim($caso->titulo.' '.$caso->texto.' '.$caso->palabras)
    );
@endphp
<form id="borrar-caso-{{ $caso->id }}" method="POST" action="{{ route('whatsapp.casos.destroy', $caso) }}" class="hidden">
    @csrf
    @method('DELETE')
</form>
<form method="POST" action="{{ route('whatsapp.casos.update', $caso) }}" class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    @csrf
    @method('PUT')
    @if($prohibido)
        <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">No se manda a n8n: tiene Ueno, un número de cuenta o voseo.</p>
    @endif
    <div class="grid gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="texto-{{ $caso->id }}">Frase al cliente</label>
            <textarea id="texto-{{ $caso->id }}" name="texto" required rows="4" class="{{ $campo }}">{{ $caso->texto }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="titulo-{{ $caso->id }}">Título</label>
            <input id="titulo-{{ $caso->id }}" name="titulo" required maxlength="120" value="{{ $caso->titulo }}" class="{{ $campo }}">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="etiqueta-{{ $caso->id }}">Etiqueta</label>
            <select id="etiqueta-{{ $caso->id }}" name="etiqueta" class="{{ $campo }}">
                @foreach($opciones as $opcion)
                    <option value="{{ $opcion }}" @selected($actual === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="palabras-{{ $caso->id }}">Palabras</label>
            <input id="palabras-{{ $caso->id }}" name="palabras" value="{{ $caso->palabras }}" class="{{ $campo }}">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" name="activo" value="1" @checked($caso->activo)> Activo
        </label>
        <p class="text-xs text-gray-500">Usos OK: {{ (int) ($caso->usos_exitosos ?? 0) }} · Descartes: {{ (int) ($caso->usos_descartes ?? 0) }}</p>
        <div class="flex items-center gap-4 border-t border-gray-100 pt-3 dark:border-gray-700">
            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">Actualizar</button>
            <button type="submit" form="borrar-caso-{{ $caso->id }}" class="text-sm text-rose-600">Eliminar</button>
        </div>
    </div>
</form>
