@extends('layouts.app')
@section('title', 'App clientes — Configuración')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">App clientes — Configuración</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Feature flags, pago online, referidos, WhatsApp y FAQs que consume Interplus Clientes 3.2
                (<code class="text-xs">/api/v1/portal/v1/*</code>).
            </p>
        </div>
        <a href="{{ route('loyalty.dashboard') }}" class="text-sm px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">← Loyalty</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('loyalty.app-config.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Flags --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Feature flags</h2>
                <p class="text-xs text-gray-500 mt-1">enabled = visible · coming_soon = “Pronto” · hidden = oculto · auto (solo pago) = según URL de checkout</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach($flagKeys as $key)
                    <label class="block text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $key }}</span>
                        <select name="flags[{{ $key }}]" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                            @foreach(['enabled' => 'enabled', 'coming_soon' => 'coming_soon', 'hidden' => 'hidden', 'auto' => 'auto'] as $val => $label)
                                @if($key !== 'pago_online' && $val === 'auto')
                                    @continue
                                @endif
                                <option value="{{ $val }}" @selected(($flagsRaw[$key] ?? '') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 border dark:border-gray-700 p-3 text-xs text-gray-600 dark:text-gray-300">
                <p class="font-medium mb-1">Vista previa efectiva (API):</p>
                <ul class="flex flex-wrap gap-2">
                    @foreach($previewFlags as $f)
                        @if(($f['key'] ?? '') === 'whatsapp_contactos') @continue @endif
                        <li class="px-2 py-0.5 rounded bg-white dark:bg-gray-800 border dark:border-gray-600">
                            {{ $f['key'] }}: <strong>{{ $f['state'] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Pago online --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pago online (Bancard)</h2>
            <label class="block text-sm">
                <span class="text-gray-700 dark:text-gray-200">URL de checkout</span>
                <input type="url" name="pago_online[checkout_url]" value="{{ old('pago_online.checkout_url', $pago['checkout_url']) }}"
                       placeholder="https://checkout…?cliente={cliente_id}"
                       class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                <span class="text-xs text-gray-500">Placeholders: {cliente_id} {cedula} {token}. Vacío = flag auto → coming_soon.</span>
            </label>
            <label class="block text-sm max-w-xs">
                <span class="text-gray-700 dark:text-gray-200">Provider</span>
                <input type="text" name="pago_online[provider]" value="{{ old('pago_online.provider', $pago['provider']) }}"
                       class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
            </label>
        </section>

        {{-- Referidos --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Referidos</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-200">Puntos por alta</span>
                    <input type="number" min="0" name="referidos[puntos_por_alta]" value="{{ old('referidos.puntos_por_alta', $referidos['puntos_por_alta']) }}"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-200">Link base</span>
                    <input type="text" name="referidos[link_base]" value="{{ old('referidos.link_base', $referidos['link_base']) }}"
                           placeholder="https://infinityisppro.net/r"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                </label>
            </div>
        </section>

        {{-- WhatsApp --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">WhatsApp (metadata flags)</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-200">Cobranzas / pagos</span>
                    <input type="text" name="whatsapp[pagos]" value="{{ old('whatsapp.pagos', $whatsapp['pagos']) }}"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-200">Soporte</span>
                    <input type="text" name="whatsapp[soporte]" value="{{ old('whatsapp.soporte', $whatsapp['soporte']) }}"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
                </label>
            </div>
        </section>

        {{-- Resumen --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Resumen Home</h2>
            <label class="block text-sm max-w-xs">
                <span class="text-gray-700 dark:text-gray-200">Disponibilidad % (opcional)</span>
                <input type="number" step="0.1" min="0" max="100" name="resumen[disponibilidad_pct]"
                       value="{{ old('resumen.disponibilidad_pct', $resumen['disponibilidad_pct']) }}"
                       placeholder="Auto si vacío"
                       class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm">
            </label>
        </section>

        {{-- FAQs --}}
        <section class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">FAQs Soporte (JSON)</h2>
            <p class="text-xs text-gray-500">Array de topics con <code>topic</code>, <code>label</code>, <code>items[]</code> (question, answer, orden). Vacío al guardar conserva el default de archivo si nunca se guardó; para resetear, pegá el contenido de <code>config/portal_faqs.php</code>.</p>
            <textarea name="faqs_json" rows="14"
                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-xs font-mono"
                      placeholder='[{"topic":"sin_internet","label":"Sin Internet","items":[...]}]'>{{ old('faqs_json', json_encode($faqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
        </section>

        <div class="flex justify-end gap-2">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                Guardar configuración
            </button>
        </div>
    </form>
</div>
@endsection
