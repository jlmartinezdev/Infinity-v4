@extends('layouts.guest')

@section('title', 'Iniciar Sesión')

@section('content')
<div id="app"></div>
@endsection

@push('scripts')
<script src="{{ asset(mix('js/login.js')) }}" defer></script>
@endpush
