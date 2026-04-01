@csrf
@isset($agenda)
    @method('PUT')
@endisset

@php
    $agenda = $agenda ?? null;
    $pedido_id = $pedido_id ?? null;
    $fecha_preseleccionada = $fecha_preseleccionada ?? ($agenda?->fecha?->format('Y-m-d')) ?? null;
    $cliente_id_form = old('cliente_id', $agenda?->cliente_id ?? $cliente_id ?? request('cliente_id'));
    $tipoInicial = $agenda ? $agenda->tipo : request('tipo', 'pedido');
    $tipoActual = old('tipo', $tipoInicial);
    $tituloInicial = $agenda?->titulo ?? request('titulo');
@endphp

@if($cliente_id_form)
    <input type="hidden" name="cliente_id" value="{{ $cliente_id_form }}">
@endif

<div class="space-y-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de cita *</label>
        <div class="flex flex-wrap gap-4">
            @foreach (App\Models\Agenda::tipos() as $key => $label)
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="tipo" value="{{ $key }}" {{ $tipoActual === $key ? 'checked' : '' }}
                        class="rounded-full border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 agenda-tipo-radio">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('tipo')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div id="campo-pedido" class="{{ $tipoActual !== 'pedido' ? 'hidden' : '' }}">
        <label for="pedido_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pedido *</label>
        <select name="pedido_id" id="pedido_id"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            <option value="">Seleccione un pedido</option>
            @foreach ($pedidos as $p)
                <option value="{{ $p->pedido_id }}"
                    {{ old('pedido_id', $agenda?->pedido_id ?? $pedido_id) == $p->pedido_id ? 'selected' : '' }}>
                    #{{ $p->pedido_id }} - {{ $p->cliente?->nombre }} {{ $p->cliente?->apellido }} ({{ $p->ubicacion ? Str::limit($p->ubicacion, 40) : '—' }})
                </option>
            @endforeach
        </select>
        @error('pedido_id')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div id="campo-titulo" class="{{ $tipoActual !== 'general' ? 'hidden' : '' }}">
        <label for="titulo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título *</label>
        <input type="text" name="titulo" id="titulo" maxlength="120"
            value="{{ old('titulo', $agenda?->titulo ?? $tituloInicial) }}"
            placeholder="Ej: Reunión equipo, Mantenimiento general..."
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
        @error('titulo')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
            <input type="date" name="fecha" id="fecha"
                value="{{ old('fecha', $agenda?->fecha?->format('Y-m-d') ?? $fecha_preseleccionada ?? date('Y-m-d')) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                required>
            @error('fecha')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                @foreach (App\Models\Agenda::estados() as $key => $label)
                    <option value="{{ $key }}" {{ old('estado', $agenda?->estado ?? 'programado') == $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="hora_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora inicio *</label>
            <input type="time" name="hora_inicio" id="hora_inicio"
                value="{{ old('hora_inicio', $agenda?->hora_inicio ? \Carbon\Carbon::parse($agenda->hora_inicio)->format('H:i') : '08:00') }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                required>
            @error('hora_inicio')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="hora_fin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora fin</label>
            <input type="time" name="hora_fin" id="hora_fin"
                value="{{ old('hora_fin', $agenda?->hora_fin ? \Carbon\Carbon::parse($agenda->hora_fin)->format('H:i') : '') }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            @error('hora_fin')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="usuario_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Técnico asignado</label>
        <select name="usuario_id" id="usuario_id"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            <option value="">-- Sin asignar --</option>
            @foreach ($tecnicos as $t)
                <option value="{{ $t->usuario_id }}"
                    {{ old('usuario_id', $agenda?->usuario_id) == $t->usuario_id ? 'selected' : '' }}>
                    {{ $t->name }} ({{ $t->email }})
                </option>
            @endforeach
        </select>
        @error('usuario_id')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 dark:text-gray-100"
            placeholder="Observaciones adicionales...">{{ old('observaciones', $agenda?->observaciones) }}</textarea>
        @error('observaciones')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $agenda ? 'Actualizar cita' : 'Crear cita' }}
        </button>
        <a href="{{ route('agenda.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Cancelar
        </a>
    </div>
</div>

<script>
(function() {
    var tipoRadios = document.querySelectorAll('.agenda-tipo-radio');
    var campoPedido = document.getElementById('campo-pedido');
    var campoPedidoSelect = document.getElementById('pedido_id');
    var campoTitulo = document.getElementById('campo-titulo');
    var campoTituloInput = document.getElementById('titulo');

    function actualizarCampos() {
        var tipo = document.querySelector('input[name="tipo"]:checked');
        if (!tipo) return;
        var esPedido = tipo.value === 'pedido';
        campoPedido.classList.toggle('hidden', !esPedido);
        campoTitulo.classList.toggle('hidden', esPedido);
        campoPedidoSelect.required = esPedido;
        campoTituloInput.required = !esPedido;
        if (!esPedido) campoPedidoSelect.value = '';
    }

    tipoRadios.forEach(function(r) {
        r.addEventListener('change', actualizarCampos);
    });
    actualizarCampos();
})();
</script>
