@if($puedeEditar)
    @php
        $pinDigitos = \App\Models\ServicioHotspot::PIN_DIGITOS;
        $esteSlot = (int) old('slot_edit') === (int) $sh->getKey();
    @endphp
    <form action="{{ route('hotspot.clientes.update', [$cliente, $sh]) }}" method="POST" class="hotspot-perfil-form mt-2">
        @csrf
        @method('PUT')
        <input type="hidden" name="slot_edit" value="{{ $sh->getKey() }}">
        <label for="hotspot-perfil-{{ $sh->getKey() }}" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Perfil</label>
        <select name="hotspot_perfil_id" id="hotspot-perfil-{{ $sh->getKey() }}" class="{{ $fieldClass }}">
            <option value="">default</option>
            @foreach($perfiles as $p)
                <option value="{{ $p->hotspot_perfil_id }}" @selected((int) $sh->hotspot_perfil_id === (int) $p->hotspot_perfil_id)>{{ $p->etiqueta() }}</option>
            @endforeach
        </select>
        <label for="hotspot-pin-{{ $sh->getKey() }}" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2 mb-1">PIN</label>
        <input type="text" name="password" id="hotspot-pin-{{ $sh->getKey() }}" data-hotspot-pin
            value="{{ $esteSlot ? old('password') : '' }}"
            inputmode="numeric"
            maxlength="{{ $pinDigitos }}"
            pattern="[0-9]{{ '{'.$pinDigitos.'}' }}"
            autocomplete="off"
            placeholder="{{ $pinDigitos }} dígitos · vacío = no cambia"
            class="{{ $fieldClass }} font-mono">
        @if($errors->has('password') && $esteSlot)
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->first('password') }}</p>
        @endif
        <button type="submit" class="hotspot-perfil-guardar mt-2 w-full px-3 py-2.5 rounded-lg text-sm font-medium bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20 disabled:opacity-60">Guardar</button>
    </form>
@elseif($sh->hotspotPerfil)
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $sh->hotspotPerfil->etiqueta() }}</p>
@endif
