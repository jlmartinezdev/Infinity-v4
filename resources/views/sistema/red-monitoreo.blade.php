@extends('layouts.app')

@section('title', 'Monitoreo de red')

@section('content')
<div id="red-monitoreo-app"></div>

<script>
    window.__RED_MONITOREO_CONFIG__ = @json($config);
</script>
<script src="{{ mix('js/red-monitoreo.js') }}"></script>
@endsection
