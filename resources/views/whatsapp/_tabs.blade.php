{{-- Tabs navegación WhatsApp --}}
@php
    $waTab = $waTab ?? 'estado';
    $tabs = [
        'estado' => ['label' => 'Estado', 'route' => 'whatsapp.index'],
        'mensajes' => ['label' => 'Mensajes', 'route' => 'whatsapp.mensajes'],
        'test-n8n' => ['label' => 'Test n8n', 'route' => 'whatsapp.test-n8n'],
        'contactos' => ['label' => 'Contactos', 'route' => 'whatsapp.contactos'],
        'asuntos' => ['label' => 'Asuntos', 'route' => 'whatsapp.asuntos.index'],
        'enviar' => ['label' => 'Enviar', 'route' => 'whatsapp.enviar'],
    ];
@endphp
<div class="mb-4 flex flex-wrap items-center gap-2">
    @foreach($tabs as $key => $tab)
        @php $active = $waTab === $key; @endphp
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition
                  {{ $active
                      ? 'bg-emerald-600 text-white shadow-sm'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
