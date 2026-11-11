@extends('layouts.app')

@section('title', 'Chat interno')

@section('content')
<div id="chat-interno">
    <header class="st-top">
        <div class="st-brand">
            <span class="st-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 8h4v4H4V8Zm6 0h4v4h-4V8Zm6 0h4v4h-4V8ZM7 14h10v2H7v-2Zm2 4h6v2H9v-2Z"/></svg>
            </span>
            <div>
                <div class="st-brand-row">
                    <span class="st-pill">ISP v4</span>
                    <h1>Chat interno y coordinación</h1>
                </div>
                <p>Mensajes del personal, archivos y pedidos de acción</p>
            </div>
        </div>
        <div class="st-top-tools">
            <label class="st-search st-search-top">
                <svg viewBox="0 0 24 24"><path d="M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14Zm8.7 14.3-3.4-3.4 1.4-1.4 3.4 3.4-1.4 1.4Z"/></svg>
                <input id="chat-buscar-global" type="search" placeholder="Buscar mensajes o archivos" autocomplete="off">
            </label>
            <button type="button" id="chat-btn-directo" class="st-btn-ghost">Mensaje directo</button>
            <button type="button" id="chat-btn-grupo" class="st-btn-blue">Nuevo grupo</button>
        </div>
    </header>

    <div class="st-body">
        <aside id="chat-aside">
            <div class="st-side-tools">
                <label class="st-search">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4V6Zm2 5h12v2H6v-2Zm3 5h6v2H9v-2Z"/></svg>
                    <input id="chat-buscar" type="search" placeholder="Filtrar chats y canales" autocomplete="off">
                </label>
                <div class="st-chips">
                    <button type="button" id="chat-filtro-acciones">Acciones pendientes</button>
                    <button type="button" id="chat-filtro-noleidos">No leídos</button>
                </div>
            </div>
            <div id="chat-lista"></div>
            <footer class="st-yo">
                <span class="st-av" id="chat-yo-avatar"></span>
                <span>
                    <strong id="chat-yo-nombre"></strong>
                    <em><i></i> Conectado</em>
                </span>
            </footer>
        </aside>

        <section id="chat-panel">
            <header id="chat-hilo-head" class="hidden">
                <button type="button" id="chat-volver" class="st-icon" aria-label="Volver a la lista">
                    <svg viewBox="0 0 24 24"><path d="M15.4 4.6 8 12l7.4 7.4 1.4-1.4L10.8 12l6-6Z"/></svg>
                </button>
                <span class="st-av lg" id="chat-hilo-avatar"></span>
                <div class="st-head-nombre">
                    <h2 id="chat-hilo-titulo"></h2>
                    <span id="chat-hilo-sub" class="st-tipo"></span>
                </div>
                <button type="button" id="chat-ir-acciones" class="st-pendientes hidden"></button>
            </header>
            <div id="chat-vacio">Elige una conversación o empieza una nueva.</div>
            <div id="chat-hilo" class="hidden"></div>
            <form id="chat-composer" class="hidden">
                <div id="chat-asignados" class="st-asignados hidden"></div>
                <div id="chat-archivo-nombre" class="st-archivo hidden"></div>
                <div class="st-caja">
                    <div id="chat-menciones" class="st-menciones hidden" role="listbox"></div>
                    <textarea id="chat-texto" rows="2" maxlength="4000" placeholder="Escribe un mensaje"></textarea>
                    <input id="chat-archivo" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/*,application/pdf">
                    <div class="st-caja-bar">
                        <div class="st-tools">
                            <button type="button" id="chat-btn-archivo" class="st-icon" title="Adjuntar archivo">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a5 5 0 0 1-7.07-7.07l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </button>
                            <button type="button" id="chat-btn-imagen" class="st-icon" title="Adjuntar imagen">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            </button>
                            <button type="button" id="chat-btn-mencion" class="st-icon" title="Mencionar personal">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>
                            </button>
                            <span class="st-sep"></span>
                            <button type="button" id="chat-accion-btn" class="st-requiere" aria-pressed="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                                Requiere acción
                            </button>
                        </div>
                        <input id="chat-accion" type="checkbox" class="sr-only">
                        <span class="st-atajo">Enter para enviar</span>
                        <button type="submit" class="st-enviar">Enviar
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </button>
                    </div>
                </div>
                <p id="chat-error" class="st-error hidden"></p>
            </form>
        </section>

        <aside id="chat-detalle">
            <p class="st-vacio">Los datos del chat aparecen al abrir una conversación.</p>
        </aside>
    </div>
    <div id="chat-msg-menu" class="st-msg-menu hidden" role="menu"></div>
    <div id="chat-reenviar" class="st-modal hidden" role="dialog" aria-modal="true" aria-labelledby="chat-reenviar-titulo">
        <div class="st-modal-caja">
            <h3 id="chat-reenviar-titulo">Reenviar mensaje</h3>
            <div id="chat-reenviar-lista"></div>
            <p id="chat-reenviar-error" class="st-error hidden"></p>
            <div class="st-modal-acciones">
                <button type="button" id="chat-reenviar-cancelar">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div id="chat-modal" class="st-modal hidden">
    <div class="st-modal-caja">
        <h3 id="chat-modal-titulo"></h3>
        <form id="chat-modal-form">
            <input id="chat-modal-nombre" type="text" maxlength="120" placeholder="Nombre del grupo" class="hidden">
            <input id="chat-modal-buscar" type="search" placeholder="Buscar personal">
            <div id="chat-modal-lista"></div>
            <p id="chat-modal-error" class="st-error hidden"></p>
            <div class="st-modal-acciones">
                <button type="button" id="chat-modal-cancelar">Cancelar</button>
                <button type="submit">Crear</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<style>
#chat-interno {
    --bg: #070d19;
    --s900: #0b1326;
    --s850: #0f172a;
    --s800: #172033;
    --s750: #1e293b;
    --s700: #334155;
    --ink: #e2e8f0;
    --muted: #94a3b8;
    --sky: #38bdf8;
    --blue: #2563eb;
    margin: -2rem -1rem;
    height: calc(100vh - 4rem);
    display: flex;
    flex-direction: column;
    background: var(--bg);
    color: var(--ink);
    font-size: 14px;
    line-height: 1.35;
    border-top: 1px solid #1e293b;
}
@media (min-width: 640px) { #chat-interno { margin-left: -1.5rem; margin-right: -1.5rem; } }
@media (min-width: 1024px) { #chat-interno { margin-left: -2rem; margin-right: -2rem; } }
#chat-interno, #chat-interno * { box-sizing: border-box; }
#chat-interno ::selection { background: #2563eb; color: #fff; }
#chat-interno * { scrollbar-width: thin; scrollbar-color: #1e293b #0b1326; }
.st-top, .st-body, #chat-aside, #chat-panel, #chat-detalle { min-width: 0; }
.st-top {
    height: 64px; flex: none; display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 0 20px; background: rgba(11,19,38,.95); border-bottom: 1px solid rgba(30,41,59,.7);
}
.st-brand, .st-brand-row, .st-top-tools, .st-head-nombre { display: flex; align-items: center; }
.st-brand { gap: 12px; min-width: 0; }
.st-brand-row { gap: 8px; }
.st-mark {
    width: 36px; height: 36px; border-radius: 8px; flex: none;
    background: linear-gradient(to top right, #2563eb, #0ea5e9 55%, #22d3ee);
    display: flex; align-items: center; justify-content: center;
}
.st-mark svg { width: 18px; height: 18px; fill: #fff; }
.st-pill { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #38bdf8; background: rgba(8,47,73,.8); border: 1px solid rgba(7,89,133,.6); border-radius: 4px; padding: 2px 6px; }
.st-top h1 { margin: 0; font-size: 14px; font-weight: 700; color: #fff; }
.st-brand p { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
.st-top-tools { gap: 8px; }
.st-search { position: relative; display: block; }
.st-search svg { position: absolute; left: 10px; top: 8px; width: 14px; height: 14px; fill: #64748b; }
.st-search input, #chat-modal input[type="search"], #chat-modal input[type="text"], .st-caja textarea {
    width: 100%; border: 1px solid var(--s750); background: var(--s850); color: var(--ink);
    border-radius: 8px; font: inherit; font-size: 12px; outline: none;
}
.st-search input { padding: 7px 10px 7px 30px; }
.st-search-top input { width: 240px; }
.st-search input:focus, #chat-modal input[type="search"]:focus, #chat-modal input[type="text"]:focus, .st-caja:focus-within { border-color: #0ea5e9; }
.st-btn-ghost, .st-btn-blue, .st-enviar, .st-modal-acciones button {
    border: 0; border-radius: 8px; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; padding: 7px 12px;
}
.st-btn-ghost { background: var(--s850); color: var(--ink); border: 1px solid var(--s750); }
.st-btn-blue, .st-enviar, .st-modal-acciones button[type="submit"] { background: #2563eb; color: #fff; }
.st-btn-blue:hover, .st-enviar:hover, .st-modal-acciones button[type="submit"]:hover { background: #3b82f6; }
.st-body { flex: 1; min-height: 0; display: grid; grid-template-columns: 300px minmax(0,1fr) 288px; }
#chat-aside, #chat-detalle { background: var(--s900); display: flex; flex-direction: column; }
#chat-aside { border-right: 1px solid rgba(30,41,59,.7); }
#chat-detalle { border-left: 1px solid rgba(30,41,59,.7); }
#chat-panel { background: var(--bg); display: flex; flex-direction: column; min-height: 0; }
.st-side-tools { padding: 12px; border-bottom: 1px solid rgba(30,41,59,.6); }
.st-chips { display: flex; gap: 6px; margin-top: 8px; overflow: auto; }
.st-chips button, .st-pendientes {
    border: 1px solid var(--s750); background: var(--s800); color: var(--muted);
    border-radius: 999px; font: inherit; font-size: 11px; padding: 3px 8px; cursor: pointer; white-space: nowrap;
}
#chat-filtro-acciones[aria-pressed="true"] { background: rgba(245,158,11,.2); color: #fcd34d; border-color: rgba(245,158,11,.35); }
#chat-filtro-noleidos[aria-pressed="true"] { color: #e2e8f0; border-color: #38bdf8; }
#chat-lista { flex: 1; overflow: auto; padding: 12px 8px; }
.st-sec { display: flex; align-items: center; justify-content: space-between; padding: 0 8px; margin: 0 0 6px; }
.st-sec span { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.st-sec button, .st-sec em { border: 0; background: transparent; color: #64748b; font-style: normal; font-size: 11px; cursor: pointer; }
.st-canal, .st-dm {
    width: 100%; display: flex; text-align: left; border: 0; background: transparent; color: inherit;
    border-radius: 8px; cursor: pointer; font: inherit;
}
.st-canal { align-items: center; justify-content: space-between; padding: 6px 10px; color: #cbd5e1; font-size: 12px; font-weight: 500; }
.st-canal:hover, .st-dm:hover { background: rgba(23,32,51,.8); }
.st-canal.activo, .st-dm.activo { background: rgba(23,32,51,.95); }
.st-canal .hash { color: #64748b; margin-right: 6px; }
.st-canal.activo .hash { color: #38bdf8; }
.st-count { min-width: 16px; padding: 1px 5px; border-radius: 6px; font-size: 10px; font-weight: 700; }
.st-count.rojo { background: #450a0a; color: #f87171; border: 1px solid rgba(153,27,27,.6); }
.st-count.cielo { background: #082f49; color: #38bdf8; border: 1px solid rgba(7,89,133,.6); }
.st-dm { position: relative; align-items: flex-start; gap: 10px; padding: 8px; }
.st-dm.activo { border: 1px solid rgba(14,165,233,.4); background: rgba(23,32,51,.9); }
.st-dm.activo::before { content: ""; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; border-radius: 0 4px 4px 0; background: #0ea5e9; }
.st-av {
    width: 32px; height: 32px; border-radius: 999px; flex: none; display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff; background: #334155;
}
.st-yo .st-av { background: #2563eb; }
.st-av.lg { width: 40px; height: 40px; font-size: 13px; background: linear-gradient(to top right, #0284c7, #4f46e5); }
.st-av.xl { width: 56px; height: 56px; font-size: 18px; margin: 0 auto 8px; background: linear-gradient(to top right, #0284c7, #4f46e5); }
.st-dm b { display: block; font-size: 12px; color: #fff; }
.st-dm small, .st-meta { color: var(--muted); font-size: 11px; }
.st-dm .txt { min-width: 0; flex: 1; }
.st-dm .fila { display: flex; justify-content: space-between; gap: 8px; }
.st-dm .fila small, .st-dm .prev { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.st-tag { display: inline-flex; margin-top: 6px; color: #fbbf24; background: rgba(69,26,3,.4); border: 1px solid rgba(180,83,9,.5); border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600; }
.st-yo { display: flex; gap: 10px; align-items: center; padding: 12px; border-top: 1px solid rgba(30,41,59,.7); background: rgba(7,13,25,.7); }
.st-yo strong { display: block; font-size: 12px; color: #fff; }
.st-yo em { display: flex; align-items: center; gap: 6px; font-style: normal; color: #34d399; font-size: 10px; }
.st-yo em i, .st-dot { width: 6px; height: 6px; border-radius: 99px; background: #34d399; display: inline-block; }
#chat-hilo-head { height: 64px; flex: none; display: flex; align-items: center; gap: 12px; padding: 0 20px; border-bottom: 1px solid rgba(30,41,59,.7); background: rgba(11,19,38,.8); }
#chat-hilo-titulo { margin: 0; font-size: 14px; color: #fff; }
.st-tipo { margin-left: 8px; font-size: 10px; font-weight: 700; color: #38bdf8; background: #082f49; border: 1px solid #075985; border-radius: 4px; padding: 2px 6px; }
.st-pendientes { margin-left: auto; border-radius: 8px; background: rgba(69,26,3,.3); border-color: rgba(217,119,6,.4); color: #fcd34d; }
.st-icon { width: 32px; height: 32px; border: 0; background: transparent; color: var(--muted); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.st-icon svg { width: 16px; height: 16px; fill: currentColor; }
.st-icon:hover { background: var(--s800); color: #fff; }
#chat-volver { display: none; }
#chat-vacio, .st-vacio { margin: auto; color: var(--muted); text-align: center; padding: 24px; font-size: 13px; }
#chat-hilo { flex: 1; overflow: auto; padding: 16px 24px; display: flex; flex-direction: column; gap: 16px; }
.st-dia { display: flex; align-items: center; gap: 12px; color: var(--muted); font-size: 11px; }
.st-dia::before, .st-dia::after { content: ""; flex: 1; border-top: 1px solid rgba(30,41,59,.6); }
.st-dia span { background: var(--s850); border: 1px solid rgba(30,41,59,.7); border-radius: 999px; padding: 4px 12px; }
.msg { display: flex; gap: 12px; max-width: 640px; }
.msg.out { align-self: flex-end; }
.msg-col { display: flex; flex-direction: column; align-items: flex-end; }
.msg-meta { display: flex; gap: 8px; align-items: baseline; font-size: 12px; color: var(--muted); }
.msg-meta .name { color: #e2e8f0; font-weight: 600; font-size: 12px; }
.msg-meta .tu { color: #38bdf8; font-weight: 600; }
.bubble { margin-top: 4px; padding: 8px 14px; border-radius: 16px; font-size: 14px; cursor: pointer; }
.bubble.in { background: rgba(23,32,51,.8); border: 1px solid rgba(30,41,59,.8); border-top-left-radius: 4px; }
.bubble.out { background: #2563eb; color: #fff; border-top-right-radius: 4px; }
.bubble p { margin: 0; white-space: pre-wrap; }
.st-linea { display: flex; align-items: flex-end; justify-content: flex-end; gap: 6px; }
.st-linea p { flex: 1; }
.st-checks { display: inline-flex; flex: none; }
.st-checks svg { width: 14px; height: 14px; fill: rgba(255,255,255,.72); }
.st-checks.leido svg { fill: #7dd3fc; }
.bubble.accion .st-checks svg { fill: #94a3b8; }
.bubble.accion .st-checks.leido svg { fill: #38bdf8; }
.bubble.accion { border-color: rgba(245,158,11,.4); background: rgba(23,32,51,.95); box-shadow: 0 0 14px -2px rgba(245,158,11,.18); padding: 14px; max-width: 520px; }
.st-banner { margin-top: 10px; padding: 10px; border-radius: 8px; border: 1px solid rgba(245,158,11,.2); background: rgba(245,158,11,.1); display: flex; justify-content: space-between; gap: 8px; align-items: center; }
.st-banner strong { display: block; color: #fcd34d; font-size: 12px; }
.st-banner span { color: #cbd5e1; font-size: 11px; }
.st-resolver { border: 0; border-radius: 4px; background: #f59e0b; color: #0f172a; font-weight: 700; font-size: 11px; padding: 4px 10px; cursor: pointer; }
.st-hora { font-size: 10px; color: #64748b; }
.st-adjunto { color: #bae6fd; font-size: 13px; font-weight: 600; }
.st-img { max-height: 180px; border-radius: 8px; display: block; margin-top: 8px; }
#chat-composer { padding: 16px; background: var(--s900); border-top: 1px solid rgba(30,41,59,.7); }
.st-caja { position: relative; background: var(--bg); border: 1px solid var(--s700); border-radius: 12px; padding: 10px; }
.st-caja textarea { border: 0; background: transparent; color: #f1f5f9; resize: none; min-height: 44px; padding: 4px; font-size: 14px; }
.st-caja textarea:focus { outline: none; }
.st-caja-bar { display: flex; align-items: center; gap: 8px; padding-top: 8px; border-top: 1px solid rgba(30,41,59,.8); }
.st-tools { display: flex; align-items: center; gap: 2px; min-width: 0; }
.st-sep { width: 1px; height: 16px; background: #334155; margin: 0 4px; flex: none; }
.st-caja-bar .st-icon svg, .st-requiere svg, .st-enviar svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.st-requiere { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--s750); background: var(--s850); color: #cbd5e1; border-radius: 8px; font: inherit; font-size: 12px; padding: 4px 8px; cursor: pointer; }
.st-requiere svg { stroke: #fbbf24; }
.st-requiere.activa { color: #fcd34d; border-color: rgba(245,158,11,.45); background: rgba(69,26,3,.35); }
.st-atajo { margin-left: auto; color: #64748b; font-size: 11px; white-space: nowrap; }
.st-enviar { display: inline-flex; align-items: center; gap: 6px; }
.st-mencion { color: #7dd3fc; font-weight: 600; }
.bubble.out .st-mencion { color: #e0f2fe; }
.st-menciones { position: absolute; left: 0; bottom: calc(100% + 8px); width: min(280px, 100%); background: #121c30; border: 1px solid #334155; border-radius: 8px; padding: 4px; max-height: 220px; overflow: auto; z-index: 4; }
.st-menciones button { width: 100%; display: flex; flex-direction: column; align-items: flex-start; gap: 1px; border: 0; background: transparent; color: #e2e8f0; font: inherit; font-size: 13px; font-weight: 600; text-align: left; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.st-menciones button span { color: #94a3b8; font-size: 11px; font-weight: 400; }
.st-menciones button.activa, .st-menciones button:hover { background: #1b2944; }
.st-asignados, .st-archivo { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; color: var(--muted); font-size: 12px; }
.st-asignados label { display: inline-flex; gap: 6px; align-items: center; background: var(--s800); border-radius: 999px; padding: 4px 8px; }
.st-error { color: #fecaca; font-size: 13px; margin: 8px 0 0; }
#chat-detalle { overflow: auto; }
.st-det-h { padding: 16px; border-bottom: 1px solid rgba(30,41,59,.7); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.st-ficha { text-align: center; padding: 16px; border-bottom: 1px solid rgba(30,41,59,.5); }
.st-ficha strong { display: block; color: #fff; }
.st-ficha em { color: var(--muted); font-style: normal; font-size: 12px; }
.st-bloque { padding: 16px; }
.st-bloque h3 { margin: 0 0 10px; font-size: 12px; color: #cbd5e1; display: flex; justify-content: space-between; }
.st-bloque h3 b { background: rgba(245,158,11,.2); color: #fcd34d; border: 1px solid rgba(245,158,11,.3); border-radius: 4px; padding: 1px 6px; font-size: 10px; }
.st-card { background: var(--s850); border: 1px solid rgba(245,158,11,.3); border-radius: 8px; padding: 10px; margin-bottom: 8px; font-size: 12px; }
.st-card .top { display: flex; justify-content: space-between; color: #fbbf24; font-size: 10px; font-weight: 700; margin-bottom: 4px; }
.st-card p { margin: 0; color: #e2e8f0; font-weight: 600; }
.st-card .pie { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 6px; border-top: 1px solid var(--s750); color: var(--muted); font-size: 10px; }
.st-card button { border: 0; background: transparent; color: #38bdf8; font-weight: 600; font-size: 11px; cursor: pointer; }
.st-file { display: flex; justify-content: space-between; gap: 8px; padding: 6px; border-radius: 6px; color: #cbd5e1; text-decoration: none; font-size: 12px; }
.st-file:hover { background: var(--s800); }
.st-file small { color: #64748b; font-size: 10px; }
.st-msg-menu { position: fixed; z-index: 70; min-width: 188px; padding: 4px; background: #121c30; border: 1px solid #334155; border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,.35); }
.st-msg-menu button { width: 100%; display: block; border: 0; background: transparent; color: #e2e8f0; font: inherit; font-size: 13px; text-align: left; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.st-msg-menu button:hover, .st-msg-menu button:focus { background: #1b2944; outline: none; }
.st-msg-menu button.peligro { color: #fca5a5; }
.st-msg-menu p { margin: 6px 10px 8px; color: #cbd5e1; font-size: 13px; }
#chat-reenviar-lista { max-height: 280px; overflow: auto; }
#chat-reenviar-lista button { width: 100%; display: flex; flex-direction: column; align-items: flex-start; gap: 1px; border: 0; background: transparent; color: #e2e8f0; font: inherit; font-size: 13px; font-weight: 600; text-align: left; padding: 8px 10px; border-radius: 8px; cursor: pointer; }
#chat-reenviar-lista button span { color: #94a3b8; font-size: 11px; font-weight: 400; }
#chat-reenviar-lista button:hover { background: #1b2944; }
.st-modal { position: fixed; inset: 0; z-index: 80; background: rgba(4,8,16,.72); display: flex; align-items: center; justify-content: center; padding: 16px; }
.st-modal-caja { width: min(440px, 100%); background: #121c30; border: 1px solid var(--s750); border-radius: 12px; padding: 18px; }
.st-modal-caja h3 { margin: 0 0 12px; }
#chat-modal input[type="search"], #chat-modal input[type="text"] { padding: 8px 12px; margin-bottom: 8px; }
#chat-modal-lista { max-height: 280px; overflow: auto; scrollbar-color: #334155 transparent; }
#chat-modal-lista label {
    display: grid; grid-template-columns: 16px minmax(0, 1fr); column-gap: 10px; align-items: center;
    padding: 8px 10px; border-radius: 8px; cursor: pointer;
}
#chat-modal-lista label:hover, #chat-modal-lista label:has(input:checked) { background: #1b2944; }
#chat-modal-lista input {
    width: 16px; height: 16px; margin: 0; padding: 0; accent-color: #38bdf8;
}
#chat-modal-lista .st-persona { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
#chat-modal-lista .st-persona b {
    font-weight: 600; color: #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
#chat-modal-lista .st-persona span {
    color: #94a3b8; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.st-modal-acciones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
.st-modal-acciones button { background: transparent; color: var(--muted); }
#chat-interno .sr-only, #chat-modal .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
#chat-interno .hidden, #chat-modal.hidden, #chat-modal .hidden { display: none !important; }
@media (max-width: 1180px) {
    .st-body { grid-template-columns: 280px minmax(0,1fr); }
    #chat-detalle { display: none; }
    .st-brand p, .st-search-top { display: none; }
}
@media (max-width: 760px) {
    .st-body { grid-template-columns: 1fr; }
    .st-top { height: auto; padding: 10px; }
    .st-btn-ghost, .st-btn-blue { display: none; }
    #chat-interno.st-en-hilo #chat-aside { display: none; }
    #chat-interno:not(.st-en-hilo) #chat-panel { display: none; }
    #chat-volver { display: inline-flex; }
}
</style>
<script>
window.__CHAT_INTERNO__ = @json($config);
</script>
<script src="{{ asset(mix('js/chat-interno.js')) }}"></script>
@endpush
