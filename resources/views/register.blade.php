@extends('layouts.guest')

@section('title', 'Registro')

@section('content')
<div id="app"></div>
@endsection

@push('scripts')
<script src="{{ asset(mix('js/register.js')) }}" defer></script>
@endpush
