@extends('layouts.app')

@section('title', 'APs wireless')

@section('content')
<div id="aps-wireless-app"></div>

<script>
    window.__APS_WIRELESS_CONFIG__ = @json($config);
</script>
<script src="{{ mix('js/aps-wireless.js') }}"></script>
@endsection
