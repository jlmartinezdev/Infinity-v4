@push('styles')
<style>
    .ppe-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
    }
    .ppe-seccion-titulo {
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
    }
    .ppe-seccion-ayuda {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-conteo {
        display: block;
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .pppoe-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border-radius: 9999px;
        font-weight: 600;
        line-height: 1.2;
    }
    .pppoe-badge--sm {
        padding: 0.15rem 0.55rem;
        font-size: 11px;
    }
    .pppoe-badge--up {
        background: #e0f2fe;
        color: #0c4a6e;
        border: 1px solid #7dd3fc;
    }
    .pppoe-badge--down {
        background: #ffedd5;
        color: #7c2d12;
        border: 1px solid #fdba74;
    }
    .ppe-cliente-btn {
        padding: 0;
        border: 0;
        background: transparent;
        color: #0f766e;
        font: inherit;
        font-weight: 600;
        text-align: left;
        text-decoration: underline;
        text-decoration-color: rgba(15, 118, 110, 0.35);
        text-underline-offset: 3px;
        cursor: pointer;
    }
    .ppe-cliente-btn:hover { text-decoration-color: currentColor; }
    .ppe-cliente-btn:focus-visible,
    .ppe-btn:focus-visible {
        outline: 2px solid #2dd4bf;
        outline-offset: 2px;
    }
    .ppe-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2.25rem;
        padding: 0 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: transparent;
        color: #1f2937;
        font-size: 0.8125rem;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }
    .ppe-btn:hover { background: #f3f4f6; }
    .ppe-btn-icono { width: 2.25rem; padding: 0; flex: none; }
    .ppe-fondo {
        position: fixed;
        inset: 0;
        z-index: 60;
        background: rgba(17, 24, 39, 0.55);
    }
    .ppe-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        z-index: 61;
        width: 100%;
        max-width: 34rem;
        overflow-y: auto;
        background: #fff;
        color: #111827;
        border-left: 1px solid #e5e7eb;
        box-shadow: -12px 0 30px rgba(0, 0, 0, 0.2);
    }
    .ppe-panel:focus { outline: none; }
    .ppe-fondo[hidden],
    .ppe-panel[hidden] { display: none; }
    .ppe-panel-cabecera {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        background: inherit;
    }
    .ppe-panel-titulos { min-width: 0; }
    .ppe-panel-titulo {
        font-size: 1.125rem;
        font-weight: 700;
        line-height: 1.5rem;
    }
    .ppe-panel-sub,
    .ppe-linea-meta,
    .ppe-evento-meta {
        display: block;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-panel-cuerpo { padding: 1rem; }
    .ppe-panel-cuerpo > * + * { margin-top: 1rem; }
    .ppe-linea {
        padding: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
    }
    .ppe-linea-cabecera {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .ppe-linea-nombre { min-width: 0; }
    .ppe-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.8125rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .ppe-linea-estado {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.625rem;
        font-size: 0.875rem;
    }
    .ppe-punto {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        flex: none;
        background: #9ca3af;
    }
    .ppe-punto.is-up { background: #0d9488; }
    .ppe-punto.is-down { background: #ea580c; }
    .ppe-nivel {
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
        white-space: nowrap;
    }
    .ppe-nivel.is-critico { background: #fee2e2; color: #991b1b; }
    .ppe-nivel.is-alto { background: #ffedd5; color: #9a3412; }
    .ppe-nivel.is-observar { background: #fef3c7; color: #854d0e; }
    .ppe-ventanas {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 0.625rem;
    }
    .ppe-ventanas dt {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-ventanas dd {
        font-size: 1.125rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .ppe-tl {
        display: flex;
        height: 0.875rem;
        margin-top: 0.75rem;
        border-radius: 4px;
        background: #e5e7eb;
        overflow: hidden;
    }
    .ppe-tl-seg { display: block; height: 100%; min-width: 1px; }
    .ppe-tl-seg.is-up,
    .ppe-tl-clave.is-up { background: #14b8a6; }
    .ppe-tl-seg.is-down,
    .ppe-tl-clave.is-down { background: #ea580c; }
    .ppe-tl-seg.is-unknown,
    .ppe-tl-clave.is-unknown { background: #9ca3af; }
    .ppe-tl-marcas {
        display: flex;
        justify-content: space-between;
        margin-top: 0.125rem;
        font-size: 11px;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .ppe-tl-leyenda {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: #4b5563;
    }
    .ppe-tl-clave {
        display: inline-block;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 2px;
    }
    .ppe-eventos {
        margin-top: 0.5rem;
        list-style: none;
        padding: 0;
    }
    .ppe-evento {
        display: flex;
        gap: 0.75rem;
        padding: 0.5rem 0 0.5rem 0.75rem;
        border-left: 3px solid #14b8a6;
        font-size: 0.8125rem;
    }
    .ppe-evento + .ppe-evento { margin-top: 0.25rem; }
    .ppe-evento.is-down { border-left-color: #ea580c; }
    .ppe-evento-hora {
        flex: none;
        width: 5.75rem;
        color: #4b5563;
        font-variant-numeric: tabular-nums;
    }
    .ppe-evento-texto { min-width: 0; }
    .ppe-evento.is-down strong { color: #c2410c; }
    .ppe-evento-dura { color: #4b5563; }
    .ppe-evento-meta { overflow-wrap: anywhere; }
    .ppe-kpi-valor.is-critico { color: #b91c1c; }
    .ppe-contador {
        margin-left: 0.25rem;
        padding: 1px 8px;
        border-radius: 9999px;
        background: #f3f4f6;
        color: #4b5563;
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    #ppe-atencion { scroll-margin-top: 5rem; }
    .ppe-atencion-cabecera {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
    }
    .ppe-atencion-vacio {
        padding: 0 1rem 0.75rem;
        font-size: 0.8125rem;
        color: #4b5563;
    }
    .ppe-niveles {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 2px;
        padding: 2px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }
    .ppe-nivel-btn {
        padding: 0.25rem 0.625rem;
        border: 0;
        border-radius: 0.375rem;
        background: transparent;
        color: #4b5563;
        font-size: 0.8125rem;
        white-space: nowrap;
        cursor: pointer;
    }
    .ppe-nivel-btn:hover { background: #f3f4f6; }
    .ppe-nivel-btn[aria-pressed="true"] {
        background: #e5e7eb;
        color: #111827;
        font-weight: 600;
    }
    .ppe-nivel-btn:focus-visible,
    .ppe-menu-item:focus-visible {
        outline: 2px solid #2dd4bf;
        outline-offset: 1px;
    }
    .ppe-atencion-scroll {
        max-height: 24rem;
        overflow: auto;
        border-top: 1px solid #e5e7eb;
    }
    .ppe-tabla {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .ppe-tabla th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 0.5rem 1rem;
        background: #f9fafb;
        color: #4b5563;
        font-weight: 500;
        text-align: left;
        white-space: nowrap;
    }
    .ppe-tabla td {
        padding: 0.5rem 1rem;
        border-top: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: middle;
        white-space: nowrap;
    }
    .ppe-tabla .is-num { text-align: right; }
    .ppe-tabla tr[hidden] { display: none; }
    .ppe-cortes {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .ppe-cortes.is-observar { color: #a16207; }
    .ppe-cortes.is-alto { color: #c2410c; }
    .ppe-cortes.is-critico { color: #b91c1c; font-weight: 700; }
    .ppe-acciones {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .ppe-menu-wrap { display: inline-block; }
    .ppe-menu {
        position: fixed;
        z-index: 50;
        min-width: 11rem;
        padding: 0.25rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        text-align: left;
    }
    .ppe-menu[hidden] { display: none; }
    .ppe-menu-item {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 0;
        border-radius: 0.375rem;
        background: transparent;
        color: #111827;
        font-size: 0.875rem;
        text-align: left;
        text-decoration: none;
        cursor: pointer;
    }
    .ppe-menu-item:hover { background: #f3f4f6; }
    .ppe-umbrales {
        padding: 0.5rem 1rem 0.75rem;
        border-top: 1px solid #e5e7eb;
        font-size: 0.75rem;
        color: #4b5563;
    }
    .ppe-umbrales summary { cursor: pointer; width: fit-content; }
    .ppe-umbrales ul { margin: 0.375rem 0; padding-left: 1rem; list-style: disc; }
    .ppe-umbrales .ppe-mono { font-size: 0.75rem; font-weight: 400; }
    html.dark .ppe-kpi-valor.is-critico { color: #fca5a5; }
    html.dark .ppe-contador { background: #374151; color: #d1d5db; }
    html.dark .ppe-atencion-vacio,
    html.dark .ppe-umbrales { color: #d1d5db; }
    html.dark .ppe-niveles { border-color: #4b5563; }
    html.dark .ppe-nivel-btn { color: #d1d5db; }
    html.dark .ppe-nivel-btn:hover { background: #374151; }
    html.dark .ppe-nivel-btn[aria-pressed="true"] { background: #4b5563; color: #fff; }
    html.dark .ppe-atencion-scroll,
    html.dark .ppe-tabla td,
    html.dark .ppe-umbrales { border-color: #374151; }
    html.dark .ppe-tabla th { background: #111827; color: #d1d5db; }
    html.dark .ppe-tabla td { color: #e5e7eb; }
    html.dark .ppe-cortes { color: #9ca3af; }
    html.dark .ppe-cortes.is-observar { color: #fde047; }
    html.dark .ppe-cortes.is-alto { color: #fdba74; }
    html.dark .ppe-cortes.is-critico { color: #fca5a5; }
    html.dark .ppe-menu { background: #1f2937; border-color: #4b5563; }
    html.dark .ppe-menu-item { color: #f3f4f6; }
    html.dark .ppe-menu-item:hover { background: #374151; }
    html.dark .ppe-cliente-btn {
        color: #5eead4;
        text-decoration-color: rgba(94, 234, 212, 0.35);
    }
    html.dark .ppe-btn {
        border-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .ppe-btn:hover { background: #374151; }
    html.dark .ppe-panel {
        background: #111827;
        color: #f3f4f6;
        border-left-color: #374151;
    }
    html.dark .ppe-panel-cabecera,
    html.dark .ppe-linea { border-color: #374151; }
    html.dark .ppe-panel-sub,
    html.dark .ppe-linea-meta,
    html.dark .ppe-evento-meta,
    html.dark .ppe-ventanas dt,
    html.dark .ppe-tl-marcas { color: #9ca3af; }
    html.dark .ppe-tl { background: #374151; }
    html.dark .ppe-tl-leyenda,
    html.dark .ppe-evento-hora,
    html.dark .ppe-evento-dura { color: #d1d5db; }
    html.dark .ppe-evento.is-down strong { color: #fdba74; }
    html.dark .ppe-nivel.is-critico { background: #7f1d1d; color: #fee2e2; }
    html.dark .ppe-nivel.is-alto { background: #7c2d12; color: #ffedd5; }
    html.dark .ppe-nivel.is-observar { background: #713f12; color: #fef3c7; }
    html.dark .ppe-card {
        border-color: #374151;
        background: #1f2937;
    }
    html.dark .ppe-periodos {
        border-color: #4b5563;
        background: #1f2937;
    }
    html.dark .ppe-periodo { color: #d1d5db; }
    html.dark .ppe-periodo:hover { background: #374151; color: #fff; }
    html.dark .ppe-periodo[aria-current="true"] { background: #0d9488; color: #fff; }
    html.dark .ppe-kpi:hover { border-color: #14b8a6; }
    html.dark .ppe-kpi[aria-current="true"] {
        border-color: #14b8a6;
        background: rgba(13, 148, 136, 0.18);
    }
    html.dark .ppe-kpi-titulo { color: #d1d5db; }
    html.dark .ppe-kpi[aria-current="true"] .ppe-kpi-titulo { color: #99f6e4; }
    html.dark .ppe-kpi-valor { color: #f3f4f6; }
    html.dark .ppe-kpi-valor.is-down { color: #fdba74; }
    html.dark .ppe-kpi-detalle,
    html.dark .ppe-seccion-ayuda,
    html.dark .ppe-conteo { color: #9ca3af; }
    html.dark .ppe-seccion-titulo,
    html.dark .ppe-router-nombre { color: #f3f4f6; }
    html.dark a.ppe-router:hover { background: #374151; }
    html.dark .ppe-router[aria-current="true"] {
        background: rgba(13, 148, 136, 0.18);
        box-shadow: inset 0 0 0 1px #14b8a6;
    }
    html.dark .ppe-router-num { color: #d1d5db; }
    html.dark .ppe-router-num strong,
    html.dark .ppe-conteo .is-down { color: #fdba74; }
    html.dark .ppe-bar { background: #374151; }
    html.dark .ppe-bar-fill { background: #f97316; }
    html.dark .pppoe-badge--up {
        background: #0c4a6e;
        color: #e0f2fe;
        border-color: #0284c7;
    }
    html.dark .pppoe-badge--down {
        background: #7c2d12;
        color: #ffedd5;
        border-color: #ea580c;
    }
</style>
@endpush
