<?php

/**
 * Estilos WhatsApp chat — tema día/noche vía html.dark (mismo toggle del sistema).
 * Fuera de Vue scoped para que no se rompa el selector.
 */
?>
<style>
.wa-app {
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

html.dark .wa-app {
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
