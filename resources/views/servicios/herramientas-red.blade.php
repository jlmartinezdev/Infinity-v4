@extends('layouts.app')

@section('title', 'Herramientas de red')

@section('content')
<div class="max-w-7xl mx-auto">
    @if ($ticketOrigen ?? null)
        @include('partials.ticket-diagnostico-app', [
            'datosDiagnostico' => $ticketOrigen->datos_diagnostico,
            'ticketOrigen' => $ticketOrigen,
            'wrapperClass' => 'mb-6',
        ])
    @endif

    <div id="herramientas-red-app"></div>
</div>
@endsection

@push('scripts')
<script>
    window.__HERRAMIENTAS_RED_CONFIG__ = @json($herramientasRedConfig ?? []);
</script>
<script src="{{ mix('js/herramientas-red.js') }}"></script>
@endpush
