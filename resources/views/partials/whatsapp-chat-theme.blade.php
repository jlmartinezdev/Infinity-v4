<?php

/**
 * Estilos WhatsApp chat — tema día/noche vía html.dark (mismo toggle del sistema).
 * Fuera de Vue scoped para que no se rompa el selector.
 */
?>
<style>
.wa-app, .wa-ficha, .wa-chat-menu {
  --wa-border: #e9edef;
  --wa-sidebar: #ffffff;
  --wa-header: #f0f2f5;
  --wa-panel: #ffffff;
  --wa-chat: #efeae2;
  --wa-empty: #f0f2f5;
  --wa-input: #ffffff;
  --wa-input-alt: #f0f2f5;
  --wa-row-hover: #f5f6f6;
  --wa-row-active: #f0f2f5;
  --wa-bubble-in: #ffffff;
  --wa-bubble-out: #d9fdd3;
  --wa-day: #ffffff;
  --wa-text: #111b21;
  --wa-muted: #667781;
  --wa-accent: #008069;
  --wa-send-bg: #00a884;
  --wa-send-fg: #ffffff;
  --wa-unread-bg: #25d366;
  --wa-unread-fg: #ffffff;
  --wa-ticks: #53bdeb;
  --wa-warn-bg: #fff3cd;
  --wa-warn-fg: #664d03;
  --wa-me: #dfe5e7;
  --wa-me-fg: #54656f;
  --wa-chip: #f0f2f5;
  --wa-chip-fg: #54656f;

  background: var(--wa-sidebar);
  border-color: var(--wa-border);
  color: var(--wa-text);
}

html.dark .wa-app, html.dark .wa-ficha, html.dark .wa-chat-menu {
  --wa-border: #2a3942;
  --wa-sidebar: #111b21;
  --wa-header: #202c33;
  --wa-panel: #111b21;
  --wa-chat: #0b141a;
  --wa-empty: #222e35;
  --wa-input: #2a3942;
  --wa-input-alt: #202c33;
  --wa-row-hover: #202c33;
  --wa-row-active: #2a3942;
  --wa-bubble-in: #202c33;
  --wa-bubble-out: #005c4b;
  --wa-day: #182229;
  --wa-text: #e9edef;
  --wa-muted: #8696a0;
  --wa-accent: #00a884;
  --wa-send-bg: #00a884;
  --wa-send-fg: #111b21;
  --wa-unread-bg: #00a884;
  --wa-unread-fg: #111b21;
  --wa-ticks: #53bdeb;
  --wa-warn-bg: rgba(92, 75, 31, 0.45);
  --wa-warn-fg: #ffeeb8;
  --wa-me: rgba(107, 124, 133, 0.3);
  --wa-me-fg: #aebac1;
  --wa-chip: #202c33;
  --wa-chip-fg: #8696a0;
}

.wa-app .wa-sidebar { background: var(--wa-sidebar); border-color: var(--wa-border); }
.wa-app .wa-header { background: var(--wa-header); border-color: var(--wa-border); }
.wa-app .wa-sidebar-body { background: var(--wa-sidebar); }
.wa-app .wa-list { border-color: var(--wa-border); }
.wa-app .wa-chat-pane { background: var(--wa-chat); }
.wa-app .wa-asunto { background: var(--wa-header); border-color: var(--wa-border); }
.wa-app .wa-composer { background: var(--wa-header); border-color: var(--wa-border); }
.wa-app .wa-empty { background: var(--wa-empty); }
.wa-app .wa-title { color: var(--wa-text); }
.wa-app .wa-muted { color: var(--wa-muted); }
.wa-app .wa-accent { color: var(--wa-accent); }
.wa-app .wa-msg-body { color: var(--wa-text); }
.wa-app .wa-ticks-read { color: var(--wa-ticks); }
.wa-app .wa-day { background: var(--wa-day); color: var(--wa-muted); }
.wa-app .wa-me-avatar { background: var(--wa-me); color: var(--wa-me-fg); }
.wa-app .wa-icon-btn { color: var(--wa-muted); }
.wa-app .wa-msg-del { opacity: 0.4; }
.wa-app .wa-bubble:hover .wa-msg-del { opacity: 1; }
.wa-app .wa-input { background: var(--wa-input-alt); color: var(--wa-text); }
.wa-app .wa-input::placeholder { color: var(--wa-muted); }
.wa-app .wa-composer .wa-input { background: var(--wa-input); }
.wa-app .wa-buscar { padding-right: 2.5rem; }
.wa-app .wa-filtro-btn {
  position: absolute;
  top: 50%;
  right: 4px;
  width: 28px;
  height: 28px;
  padding: 0;
  transform: translateY(-50%);
}
.wa-app .wa-filtro-btn:focus-visible { outline: 2px solid var(--wa-accent); outline-offset: 2px; }
.wa-filtro-dot {
  display: none;
  position: absolute;
  top: 5px;
  right: 5px;
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--wa-accent);
  box-shadow: 0 0 0 2px var(--wa-sidebar);
}
.wa-filtro-btn.is-on .wa-filtro-dot { display: block; }
.wa-app .wa-sticker { max-height: 160px; max-width: 160px; }
.wa-app .wa-reaction { font-size: 28px; line-height: 1.15; }
.wa-app img.wa-emoji {
  display: inline-block;
  width: 1.15em;
  height: 1.15em;
  margin: 0 0.05em;
  vertical-align: -0.2em;
}
.wa-app .wa-reaction img.wa-emoji {
  width: 28px;
  height: 28px;
  margin: 0;
  vertical-align: middle;
}
.wa-app .wa-chip { background: var(--wa-chip); color: var(--wa-chip-fg); }
.wa-app .wa-chip-on { background: #00a884 !important; color: #111b21 !important; }
.wa-app .wa-chip-neutral { background: #8696a0 !important; color: #111b21 !important; }
.wa-app .wa-row { border-color: var(--wa-border); outline: none; }
.wa-app .wa-row:hover { background: var(--wa-row-hover); }
.wa-app .wa-row-active { background: var(--wa-row-active) !important; box-shadow: inset 3px 0 0 #00a884; }
.wa-app .wa-unread { background: var(--wa-unread-bg); color: var(--wa-unread-fg); }
.wa-app .wa-send { background: var(--wa-send-bg); color: var(--wa-send-fg); }
.wa-app .wa-empty-icon { background: rgba(0,168,132,.15); color: #00a884; }
.wa-app .wa-warn { background: var(--wa-warn-bg); color: var(--wa-warn-fg); border-color: var(--wa-border); }
.wa-app .wa-sugerencia {
  background: #ecfdf5;
  color: #065f46;
  border-color: var(--wa-border);
}
.wa-app .wa-sugerencia-espera { background: #f0fdf4; color: #166534; }
.wa-app .wa-sugerencia-texto {
  background: rgba(255, 255, 255, 0.72);
  color: #022c22;
}
.wa-app .wa-sugerencia-enviar { background: #16a34a; color: #ffffff; }
.wa-app .wa-sugerencia-sec { background: rgba(255, 255, 255, 0.8); color: #064e3b; }
.wa-app .wa-sugerencia-error { color: #b91c1c; }
html.dark .wa-app .wa-sugerencia,
html.dark .wa-app .wa-sugerencia-espera {
  background: #102923;
  color: #d1fae5;
  border-color: #1c3d36;
}
html.dark .wa-app .wa-sugerencia-texto {
  background: #0b141a;
  color: #e9edef;
  box-shadow: inset 0 0 0 1px #1f3d36;
}
html.dark .wa-app .wa-sugerencia-enviar { background: #00a884; color: #111b21; }
html.dark .wa-app .wa-sugerencia-sec { background: #1a3330; color: #d1fae5; }
html.dark .wa-app .wa-sugerencia-error { color: #fecaca; }
.wa-app .wa-toast-ok { background: rgba(0,168,132,.18); color: var(--wa-accent); border-color: var(--wa-border); }
.wa-app .wa-toast-err { background: rgba(234,0,56,.12); color: #b42318; border-color: var(--wa-border); }
html.dark .wa-app .wa-toast-err { color: #fecaca; }

.wa-app .wa-wallpaper {
  background-color: var(--wa-chat);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='260' viewBox='0 0 260 260'%3E%3Cg fill='none' stroke='%23111b21' stroke-opacity='0.07' stroke-width='1.2'%3E%3Cpath d='M40 30c12 0 18 10 18 18s-6 18-18 18-18-10-18-18 6-18 18-18z'/%3E%3Cpath d='M120 50l10 18h-20z'/%3E%3Cpath d='M190 40c8 4 12 14 8 22s-14 12-22 8-12-14-8-22 14-12 22-8z'/%3E%3Cpath d='M60 120h28v16H60z'/%3E%3Cpath d='M150 110c0 10-8 18-18 18s-18-8-18-18 8-18 18-18 18 8 18 18z'/%3E%3Cpath d='M220 130l14 8-14 8-14-8z'/%3E%3Cpath d='M40 200c10 0 18 6 18 14s-8 14-18 14-18-6-18-14 8-14 18-14z'/%3E%3Cpath d='M130 190l20 6-6 20-20-6z'/%3E%3Cpath d='M210 200c6 8 4 18-4 24s-18 4-24-4 4-18 12-22 10-4 16 2z'/%3E%3C/g%3E%3C/svg%3E");
}
html.dark .wa-app .wa-wallpaper {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='260' viewBox='0 0 260 260'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='0.035' stroke-width='1.2'%3E%3Cpath d='M40 30c12 0 18 10 18 18s-6 18-18 18-18-10-18-18 6-18 18-18z'/%3E%3Cpath d='M120 50l10 18h-20z'/%3E%3Cpath d='M190 40c8 4 12 14 8 22s-14 12-22 8-12-14-8-22 14-12 22-8z'/%3E%3Cpath d='M60 120h28v16H60z'/%3E%3Cpath d='M150 110c0 10-8 18-18 18s-18-8-18-18 8-18 18-18 18 8 18 18z'/%3E%3Cpath d='M220 130l14 8-14 8-14-8z'/%3E%3Cpath d='M40 200c10 0 18 6 18 14s-8 14-18 14-18-6-18-14 8-14 18-14z'/%3E%3Cpath d='M130 190l20 6-6 20-20-6z'/%3E%3Cpath d='M210 200c6 8 4 18-4 24s-18 4-24-4 4-18 12-22 10-4 16 2z'/%3E%3C/g%3E%3C/svg%3E");
}

html.wa-ficha-abierta:not(.wa-ficha-overlay) .wa-chat-pane { padding-right: 340px; }
html.wa-ficha-abierta .wa-chat-pane header button[title="Ficha del cliente"] {
  background: var(--wa-send-bg) !important;
  color: var(--wa-send-fg) !important;
}

.wa-ficha {
  position: fixed;
  z-index: 30;
  display: none;
  flex-direction: column;
  width: 340px;
  overflow: hidden;
  background: var(--wa-panel);
  color: var(--wa-text);
  border-left: 1px solid var(--wa-border);
  box-shadow: none;
  caret-color: var(--wa-accent);
}
.wa-ficha.is-open { display: flex; }
.wa-ficha ::selection { background: rgba(0, 168, 132, 0.28); color: var(--wa-text); }
.wa-ficha * { scrollbar-width: thin; scrollbar-color: var(--wa-border) transparent; }
.wa-ficha-head, .wa-ficha-body, .wa-ficha-foot { padding: 14px 16px; }
.wa-ficha-head {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--wa-header);
  border-bottom: 1px solid var(--wa-border);
}
.wa-ficha-mark {
  display: flex;
  height: 28px;
  width: 28px;
  flex: none;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: rgba(0, 168, 132, 0.16);
  color: var(--wa-accent);
}
.wa-ficha-title {
  min-width: 0;
  flex: 1;
  font-size: 13px;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.wa-ficha-x, .wa-ficha-nota, .wa-ficha-link {
  border: 0;
  background: transparent;
  color: var(--wa-muted);
  cursor: pointer;
}
.wa-ficha-x {
  display: flex;
  height: 28px;
  width: 28px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
}
.wa-ficha-x:hover, .wa-ficha-nota:hover, .wa-ficha-link:hover { color: var(--wa-text); background: var(--wa-row-hover); }
.wa-ficha-x:focus-visible, .wa-ficha-nota:focus-visible, .wa-ficha-link:focus-visible {
  outline: 2px solid var(--wa-accent);
  outline-offset: 2px;
}
.wa-ficha-body { flex: 1; overflow: auto; display: flex; flex-direction: column; gap: 16px; }
.wa-ficha-kicker {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 650;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--wa-muted);
}
.wa-ficha-card {
  background: var(--wa-header);
  border: 1px solid var(--wa-border);
  border-radius: 12px;
  padding: 12px 14px;
}
.wa-ficha-card + .wa-ficha-card { margin-top: 8px; }
.wa-ficha-label { color: var(--wa-muted); font-size: 12px; }
.wa-ficha-name { margin: 2px 0 0; font-size: 16px; font-weight: 650; line-height: 1.3; }
.wa-ficha-sub { margin: 2px 0 0; color: var(--wa-muted); font-size: 12px; }
.wa-ficha-split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--wa-border);
}
.wa-ficha-split b, .wa-ficha-row b { display: block; margin-top: 2px; font-size: 13px; font-weight: 600; }
.wa-ficha-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 7px 0;
  font-size: 13px;
}
.wa-ficha-row + .wa-ficha-row { border-top: 1px solid var(--wa-border); }
.wa-ficha-row b { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
.wa-ficha-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  white-space: nowrap;
}
.wa-ficha-ok { color: #066b56; background: rgba(0, 168, 132, 0.16); }
html.dark .wa-ficha-ok { color: #8ef0c8; background: rgba(0, 168, 132, 0.2); }
.wa-ficha-warn { color: #92400e; background: rgba(217, 119, 6, 0.16); }
html.dark .wa-ficha-warn { color: #fde68a; background: rgba(217, 119, 6, 0.22); }
.wa-ficha-bad { color: #9f1239; background: rgba(234, 0, 56, 0.12); }
html.dark .wa-ficha-bad { color: #fecdd3; background: rgba(234, 0, 56, 0.2); }
.wa-ficha-muted { color: var(--wa-muted); background: var(--wa-chip); }
.wa-ficha-note {
  margin: 8px 0 0;
  color: var(--wa-muted);
  font-size: 11px;
}
.wa-ficha-bit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.wa-ficha-nota {
  border-radius: 8px;
  padding: 2px 6px;
  font-size: 12px;
  font-weight: 650;
  color: var(--wa-accent);
}
.wa-ficha-item { padding: 10px 12px; }
.wa-ficha-item p { margin: 6px 0 0; font-size: 13px; line-height: 1.35; }
.wa-ficha-empty, .wa-ficha-loading { color: var(--wa-muted); font-size: 13px; line-height: 1.45; }
.wa-ficha-foot { border-top: 1px solid var(--wa-border); }
.wa-ficha-link {
  display: inline-flex;
  border-radius: 8px;
  padding: 4px 2px;
  font-size: 12px;
  font-weight: 650;
  color: var(--wa-accent);
  text-decoration: none;
}

.wa-chat-pane header .wa-chip,
.wa-chat-pane header a.wa-accent,
.wa-chat-pane header button[title^="Eliminar chat"] { display: none !important; }

.wa-chat-id {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: center;
  min-width: 0;
  margin: -4px -8px;
  padding: 4px 26px 4px 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
  position: relative;
}
.wa-chat-id:hover,
.wa-chat-id[aria-expanded="true"] { background: var(--wa-row-hover); }
.wa-chat-id:focus-visible { outline: 2px solid var(--wa-accent); outline-offset: 2px; }
.wa-chat-id::after {
  content: "";
  position: absolute;
  right: 10px;
  top: 50%;
  width: 7px;
  height: 7px;
  border-right: 1.6px solid var(--wa-muted);
  border-bottom: 1.6px solid var(--wa-muted);
  transform: translateY(-70%) rotate(45deg);
}
.wa-chat-id[aria-expanded="true"]::after { transform: translateY(-20%) rotate(225deg); }

.wa-chat-menu {
  position: fixed;
  z-index: 40;
  display: none;
  min-width: 300px;
  max-width: 360px;
  padding: 6px;
  border: 1px solid var(--wa-border);
  border-radius: 12px;
  background: var(--wa-header);
  color: var(--wa-text);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
}
.wa-chat-menu.is-open { display: block; }
.wa-chat-menu button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  padding: 8px;
  text-align: left;
  cursor: pointer;
}
.wa-chat-menu button:hover:not(:disabled) { background: var(--wa-row-hover); }
.wa-chat-menu button:focus-visible { outline: 2px solid var(--wa-accent); outline-offset: -2px; }
.wa-chat-menu button:disabled { opacity: 0.45; cursor: default; }
.wa-chat-menu-ico {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  border-radius: 8px;
  background: var(--wa-chip);
  color: var(--wa-accent);
}
.wa-chat-menu-ico svg { width: 16px; height: 16px; }
.wa-chat-menu-copy { display: flex; min-width: 0; flex-direction: column; gap: 2px; }
.wa-chat-menu-label { font-size: 13px; font-weight: 650; line-height: 1.2; }
.wa-chat-menu-desc { font-size: 11px; font-weight: 450; line-height: 1.35; color: var(--wa-muted); }
.wa-chat-menu-sep { height: 1px; margin: 4px 8px; background: var(--wa-border); }
.wa-chat-menu .wa-chat-menu-danger { color: #9f1239; }
.wa-chat-menu .wa-chat-menu-danger .wa-chat-menu-desc { color: #9f1239; }
.wa-chat-menu .wa-chat-menu-danger .wa-chat-menu-ico {
  background: rgba(159, 18, 57, 0.12);
  color: inherit;
}
html.dark .wa-chat-menu .wa-chat-menu-danger,
html.dark .wa-chat-menu .wa-chat-menu-danger .wa-chat-menu-desc { color: #fecdd3; }
html.dark .wa-chat-menu .wa-chat-menu-danger .wa-chat-menu-ico { background: rgba(254, 205, 211, 0.12); }

.wa-app .wa-bubble { border-radius: 7.5px; color: var(--wa-text); }
.wa-app .wa-bubble-out { background: var(--wa-bubble-out); border-top-right-radius: 0; }
.wa-app .wa-bubble-in { background: var(--wa-bubble-in); border-top-left-radius: 0; }
.wa-app .wa-bubble-fail { background: #fecaca !important; color: #7f1d1d; }
html.dark .wa-app .wa-bubble-fail { background: rgba(136, 19, 55, 0.85) !important; color: #fff1f2; }
.wa-app .wa-bubble-out::before {
  content: ''; position: absolute; top: 0; right: -8px; width: 8px; height: 13px;
  background: inherit; clip-path: polygon(0 0, 100% 0, 0 100%);
}
.wa-app .wa-bubble-in::before {
  content: ''; position: absolute; top: 0; left: -8px; width: 8px; height: 13px;
  background: inherit; clip-path: polygon(0 0, 100% 0, 100% 100%);
}

</style>
