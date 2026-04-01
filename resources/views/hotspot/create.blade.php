@extends('layouts.app')

@section('title', 'Asociar Hotspot a servicio')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Asociar usuario Hotspot a servicio</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('hotspot.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="servicio_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Servicio *</label>
                    <select name="servicio_id" id="servicio_id" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">-- Seleccionar servicio --</option>
                        @if($servicio)
                            <option value="{{ $servicio->servicio_id }}" selected>#{{ $servicio->servicio_id }} - {{ $servicio->cliente?->nombre }} {{ $servicio->cliente?->apellido }}</option>
                        @else
                            @foreach(\App\Models\Servicio::with('cliente')->where('estado', 'A')->whereDoesntHave('servicioHotspot')->orderByDesc('servicio_id')->limit(200)->get() as $s)
                                <option value="{{ $s->servicio_id }}">#{{ $s->servicio_id }} - {{ $s->cliente?->nombre }} {{ $s->cliente?->apellido }}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('servicio_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="router_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router *</label>
                    <select name="router_id" id="router_id" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">-- Seleccionar router --</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}">{{ $r->nombre }} ({{ $r->ip }})</option>
                        @endforeach
                    </select>
                    @error('router_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="hotspot_perfil_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Perfil Hotspot</label>
                    <select name="hotspot_perfil_id" id="hotspot_perfil_id"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">default</option>
                        @foreach($perfiles as $p)
                            <option value="{{ $p->hotspot_perfil_id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario *</label>
                        <input type="text" name="username" id="username" value="{{ old('username') }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            maxlength="64" required>
                        @error('username')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña *</label>
                        <input type="text" name="password" id="password" value="{{ old('password') }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            maxlength="64" required>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comentario</label>
                    <input type="text" name="comment" id="comment" value="{{ old('comment') }}"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        maxlength="255" placeholder="Nombre del cliente">
                    @error('comment')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar</button>
                <a href="{{ route('hotspot.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
