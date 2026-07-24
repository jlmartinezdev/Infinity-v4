@extends('layouts.app')

@section('title', 'WhatsApp · Contactos')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Nombres de perfil sincronizados desde Meta (cuando el contacto escribe).
            No modifica el nombre del cliente ISP.
        </p>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'contactos'])

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="GET" action="{{ route('whatsapp.contactos') }}" class="flex flex-col gap-2 border-b border-gray-100 p-3 sm:flex-row sm:items-center dark:border-gray-700/80">
            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre WA, teléfono o cliente ID…"
                   class="w-full flex-1 rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Buscar</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">Contacto WA</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Cliente ISP</th>
                        <th class="px-4 py-3">Msgs</th>
                        <th class="px-4 py-3">Último</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse($contactos as $c)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                @if($c->nombre)
                                    {{ $c->nombre }}
                                @else
                                    <span class="font-normal text-gray-400">Sin nombre WA</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-300">{{ $c->telefono }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                @if($c->cliente)
                                    <a href="{{ url('/clientes/'.$c->cliente_id.'/acciones') }}" class="text-emerald-600 hover:underline dark:text-emerald-400">
                                        #{{ $c->cliente_id }} · {{ trim(($c->cliente->nombre ?? '').' '.($c->cliente->apellido ?? '')) }}
                                    </a>
                                @elseif($c->cliente_id)
                                    #{{ $c->cliente_id }}
                                @else
                                    <span class="text-gray-400">Sin vincular</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $c->mensajes_count }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $c->ultimo_visto_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                Todavía no hay contactos. Se sincronizan cuando alguien escribe por WhatsApp.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contactos->hasPages())
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-700/80">
                {{ $contactos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
