<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TMP — Probar agente WA N8N</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; }
        .warn { background: #fef3c7; color: #92400e; padding: .75rem 1rem; border-radius: 8px; font-size: .9rem; }
        label { display: block; margin: .85rem 0 .25rem; font-weight: 600; font-size: .85rem; }
        input[type=text], textarea { width: 100%; box-sizing: border-box; padding: .55rem .7rem; border: 1px solid #ccc; border-radius: 8px; font: inherit; }
        .row { display: flex; gap: .6rem; flex-wrap: wrap; margin: 1rem 0; }
        .btn { display: inline-block; text-align: center; text-decoration: none; border: 0; border-radius: 10px; padding: .85rem 1.2rem; font-weight: 700; cursor: pointer; font-size: 1rem; }
        .btn-go { background: #16a34a; color: #fff; width: 100%; }
        .btn-go:hover { background: #15803d; }
        .btn-preset { background: #e5e7eb; color: #111; font-size: .85rem; padding: .45rem .75rem; }
        .chk { display: flex; align-items: center; gap: .5rem; margin: 1rem 0; }
        pre { background: #111; color: #d1fae5; padding: 1rem; border-radius: 8px; overflow: auto; font-size: .8rem; }
        .muted { color: #6b7280; font-size: .8rem; }
    </style>
</head>
<body>
    <p class="warn">Página temporal de prueba. No es el webhook de clientes. <code>WA_AGENT_ENABLED</code> sigue aparte.</p>
    <h1>Probar agente WhatsApp → N8N</h1>

    <div class="row">
        <a class="btn btn-preset" href="{{ route('tmp.wa-agent', ['wa_id' => '595986771317', 'nombre' => 'Maria', 'mensaje' => 'Hola, cuanto sale el plan de 20 megas?']) }}">María (sin enviar)</a>
        <a class="btn btn-preset" href="{{ route('tmp.wa-agent', ['wa_id' => '5491141914293', 'nombre' => 'Test', 'mensaje' => 'Hola, cuanto sale el plan de 20 megas?', 'enviar' => '1']) }}">Mi número + enviar</a>
    </div>

    <form method="post" action="{{ route('tmp.wa-agent.run') }}">
        @csrf
        <label for="wa_id">wa_id</label>
        <input id="wa_id" name="wa_id" type="text" required value="{{ old('wa_id', request('wa_id', '595986771317')) }}">

        <label for="nombre">nombre</label>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre', request('nombre', 'Maria')) }}">

        <label for="mensaje">mensaje</label>
        <textarea id="mensaje" name="mensaje" rows="3" required>{{ old('mensaje', request('mensaje', 'Hola, cuanto sale el plan de 20 megas?')) }}</textarea>

        <label class="chk">
            <input type="checkbox" name="enviar" value="1" @checked(old('enviar', request('enviar')))>
            Enviar reply por WhatsApp (solo tu número de prueba)
        </label>

        <button class="btn btn-go" type="submit">Probar agente</button>
    </form>

    @if(session('resultado'))
        <h2>Resultado</h2>
        <pre>{{ json_encode(session('resultado'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    @endif

    <p class="muted">{{ url('/tmp/wa-agent') }}</p>
</body>
</html>
