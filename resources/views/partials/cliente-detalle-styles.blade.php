<style>
    /* Tema alineado con layouts.app (Tailwind dark:) */
    .cliente-detalle-page {
        --cd-card: #ffffff;
        --cd-card-border: #e5e7eb;
        --cd-text: #111827;
        --cd-muted: #6b7280;
        --cd-row-hover: rgb(249 250 251);
        --cd-thead: #f9fafb;
        --cd-row-border: #f3f4f6;
        --cd-head-border: #e5e7eb;
        --cd-pill-ok-bg: rgb(16 185 129 / 0.12);
        --cd-pill-ok-fg: #047857;
        --cd-pill-warn-bg: rgb(245 158 11 / 0.14);
        --cd-pill-warn-fg: #b45309;
        --cd-link: #2563eb;
        --cd-icon: #9ca3af;
        color: var(--cd-text);
    }
    html.dark .cliente-detalle-page {
        --cd-card: #1f2937;
        --cd-card-border: #374151;
        --cd-text: #f3f4f6;
        --cd-muted: #9ca3af;
        --cd-row-hover: rgb(55 65 81 / 0.35);
        --cd-thead: rgb(17 24 39 / 0.55);
        --cd-row-border: rgb(55 65 81 / 0.65);
        --cd-head-border: rgb(55 65 81 / 0.85);
        --cd-pill-ok-bg: rgb(16 185 129 / 0.18);
        --cd-pill-ok-fg: #6ee7b7;
        --cd-pill-warn-bg: rgb(245 158 11 / 0.18);
        --cd-pill-warn-fg: #fcd34d;
        --cd-link: #60a5fa;
        --cd-icon: #6b7280;
    }

    .cliente-detalle-container {
        width: 100%;
        max-width: 80rem;
        margin-left: auto;
        margin-right: auto;
    }
    .cliente-detalle-page .cd-card {
        background: var(--cd-card);
        border: 1px solid var(--cd-card-border);
        border-radius: 0.75rem;
        box-shadow: none;
        overflow: hidden;
    }
    .cliente-detalle-page .cd-label {
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--cd-muted);
    }

    .cliente-detalle-page .cd-panel__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        border-bottom: 1px solid var(--cd-head-border);
        min-height: 2.75rem;
    }
    .cliente-detalle-page .cd-panel__head h2 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--cd-text);
        margin: 0;
        line-height: 1.25;
    }
    .cliente-detalle-page .cd-panel__sub {
        font-size: 0.6875rem;
        color: var(--cd-muted);
        margin-top: 0.125rem;
    }
    .cliente-detalle-page .cd-panel__body {
        padding: 0.75rem 0.875rem;
    }
    .cliente-detalle-page .cd-panel__body--flush {
        padding: 0;
    }

    .cliente-detalle-page .cd-table {
        width: 100%;
        border-collapse: collapse;
    }
    .cliente-detalle-page .cd-table thead {
        background: var(--cd-thead);
    }
    .cliente-detalle-page .cd-table th {
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--cd-muted);
        padding: 0.5rem 0.75rem;
        text-align: left;
        white-space: nowrap;
    }
    .cliente-detalle-page .cd-table td {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        color: var(--cd-text);
        border-top: 1px solid var(--cd-row-border);
        vertical-align: middle;
    }
    .cliente-detalle-page .cd-table tbody tr:hover {
        background: var(--cd-row-hover);
    }
    .cliente-detalle-page .cd-table-scroll {
        overflow-x: auto;
        max-height: 14rem;
        overflow-y: auto;
    }
    .cliente-detalle-page .cd-link {
        color: var(--cd-link);
        text-decoration: none;
    }
    .cliente-detalle-page .cd-link:hover { text-decoration: underline; }
    .cliente-detalle-page .cd-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.1rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 600;
    }
    .cliente-detalle-page .cd-pill--ok {
        background: var(--cd-pill-ok-bg);
        color: var(--cd-pill-ok-fg);
    }
    .cliente-detalle-page .cd-pill--warn {
        background: var(--cd-pill-warn-bg);
        color: var(--cd-pill-warn-fg);
    }
    .cliente-detalle-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.875rem;
        align-items: start;
    }
    @media (min-width: 1280px) {
        .cliente-detalle-layout {
            grid-template-columns: minmax(0, 1fr) 300px;
        }
    }
    .cliente-detalle-chat {
        position: sticky;
        top: 1rem;
        min-height: 28rem;
        max-height: min(32rem, calc(100vh - 6rem));
    }
    .cliente-detalle-page .cd-summary {
        position: relative;
        overflow: hidden;
        padding: 0.875rem 1rem;
    }
    .cliente-detalle-page .cd-summary .mt-2 {
        margin-top: 0.35rem !important;
    }
    .cliente-detalle-page .cd-summary .text-3xl {
        font-size: 1.5rem;
        line-height: 1.2;
    }
    .cliente-detalle-page .cd-summary__icon {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.12;
        width: 2.5rem;
        height: 2.5rem;
        color: var(--cd-icon);
        pointer-events: none;
    }
    .cliente-detalle-page .cd-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        padding: 1.25rem 0.75rem;
        text-align: center;
        color: var(--cd-muted);
        font-size: 0.8125rem;
    }
    .cliente-detalle-page .cd-empty svg {
        width: 1.5rem;
        height: 1.5rem;
        opacity: 0.4;
    }
    .cliente-detalle-page main.space-y-5 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.875rem;
    }

    /* Valores de datos */
    .cliente-detalle-page .cd-value {
        color: var(--cd-text);
    }
    .cliente-detalle-page .cd-muted {
        color: var(--cd-muted);
    }

    /* Sidebar WhatsApp — también respeta tema */
    .cliente-wa-sidebar {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 28rem;
        max-height: min(32rem, calc(100vh - 6rem));
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid var(--cd-card-border);
        background: #f8fafc;
        box-shadow: none;
    }
    html.dark .cliente-wa-sidebar {
        background: #0b141a;
        border-color: #334155;
    }
    .cliente-wa-sidebar__head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid rgb(0 0 0 / 0.06);
        background: #ffffff;
        padding: 0.75rem 0.875rem;
    }
    html.dark .cliente-wa-sidebar__head {
        border-bottom-color: rgb(255 255 255 / 0.06);
        background: #202c33;
    }
    .cliente-wa-sidebar__avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #6366f1, #3b82f6);
        font-size: 0.8125rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    .cliente-wa-sidebar__name {
        font-size: 0.875rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
    }
    html.dark .cliente-wa-sidebar__name { color: #f8fafc; }
    .cliente-wa-sidebar__actions {
        margin-left: auto;
        display: flex;
        gap: 0.25rem;
    }
    .cliente-wa-sidebar__icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 9999px;
        color: #6b7280;
        transition: background 0.15s, color 0.15s;
    }
    .cliente-wa-sidebar__icon-btn:hover {
        background: rgb(0 0 0 / 0.05);
        color: #111827;
    }
    html.dark .cliente-wa-sidebar__icon-btn { color: #94a3b8; }
    html.dark .cliente-wa-sidebar__icon-btn:hover {
        background: rgb(255 255 255 / 0.08);
        color: #e2e8f0;
    }
    .cliente-wa-sidebar__hilo {
        flex: 1;
        overflow-y: auto;
        padding: 0.75rem;
        background: #f1f5f9;
    }
    html.dark .cliente-wa-sidebar__hilo {
        background-color: transparent;
        background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 18px 18px;
    }
    .cliente-wa-sidebar__foot {
        border-top: 1px solid rgb(0 0 0 / 0.06);
        background: #ffffff;
        padding: 0.625rem;
    }
    html.dark .cliente-wa-sidebar__foot {
        border-top-color: rgb(255 255 255 / 0.06);
        background: #202c33;
    }
    .cliente-wa-sidebar__composer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        background: #e5e7eb;
        padding: 0.45rem 0.75rem;
        color: #6b7280;
        font-size: 0.8125rem;
        text-decoration: none;
    }
    .cliente-wa-sidebar__composer:hover {
        background: #d1d5db;
        color: #374151;
    }
    html.dark .cliente-wa-sidebar__composer {
        background: #2a3942;
        color: #8696a0;
    }
    html.dark .cliente-wa-sidebar__composer:hover {
        background: #32424a;
        color: #cbd5e1;
    }
    .cliente-wa-sidebar__warn {
        margin-bottom: 0.5rem;
        border-radius: 0.5rem;
        background: #fef3c7;
        color: #92400e;
        padding: 0.4rem 0.55rem;
        font-size: 0.6875rem;
        line-height: 1.35;
    }
    .cliente-wa-sidebar__warn a {
        font-weight: 600;
        text-decoration: underline;
        color: inherit;
    }
    html.dark .cliente-wa-sidebar__warn {
        background: rgb(120 53 15 / 0.35);
        color: #fcd34d;
    }
    .cliente-wa-sidebar__pending {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        border-radius: 0.625rem;
        background: #e5e7eb;
        padding: 0.4rem 0.5rem;
    }
    html.dark .cliente-wa-sidebar__pending {
        background: #2a3942;
    }
    .cliente-wa-sidebar__pending[hidden] { display: none !important; }
    .cliente-wa-sidebar__pending-img {
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 0.35rem;
        object-fit: cover;
    }
    .cliente-wa-sidebar__pending-name {
        font-size: 0.75rem;
        font-weight: 600;
        color: #111827;
    }
    html.dark .cliente-wa-sidebar__pending-name { color: #e2e8f0; }
    .cliente-wa-sidebar__composer-form {
        display: flex;
        align-items: flex-end;
        gap: 0.35rem;
    }
    .cliente-wa-sidebar__icon-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .cliente-wa-sidebar__input {
        flex: 1;
        min-height: 2.5rem;
        max-height: 7rem;
        resize: none;
        border: 0;
        border-radius: 1.25rem;
        background: #e5e7eb;
        color: #111827;
        padding: 0.55rem 0.85rem;
        font-size: 0.8125rem;
        line-height: 1.35;
        outline: none;
    }
    .cliente-wa-sidebar__input:focus {
        box-shadow: 0 0 0 2px rgb(37 99 235 / 0.35);
    }
    .cliente-wa-sidebar__input:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }
    html.dark .cliente-wa-sidebar__input {
        background: #2a3942;
        color: #e2e8f0;
    }
    .cliente-wa-sidebar__send {
        display: inline-flex;
        height: 2.5rem;
        width: 2.5rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 9999px;
        background: #2563eb;
        color: #fff;
        cursor: pointer;
    }
    .cliente-wa-sidebar__send:hover:not(:disabled) {
        background: #1d4ed8;
    }
    .cliente-wa-sidebar__send:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .cliente-wa-sidebar__hint {
        margin-top: 0.35rem;
        padding: 0 0.15rem;
        font-size: 0.625rem;
        color: #94a3b8;
    }
    html.dark .cliente-wa-sidebar__hint {
        color: #64748b;
    }
    .cliente-wa-sidebar__error {
        margin-top: 0.35rem;
        font-size: 0.6875rem;
        color: #b91c1c;
    }
    html.dark .cliente-wa-sidebar__error {
        color: #fca5a5;
    }
    .cliente-wa-dia {
        display: flex;
        justify-content: center;
        margin: 0.5rem 0;
    }
    .cliente-wa-dia span {
        border-radius: 0.5rem;
        background: #e5e7eb;
        padding: 0.2rem 0.625rem;
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: #6b7280;
    }
    html.dark .cliente-wa-dia span {
        background: #182229;
        color: #aebac1;
    }
    .cliente-wa-row { display: flex; margin-bottom: 0.3rem; }
    .cliente-wa-row--in { justify-content: flex-start; }
    .cliente-wa-row--out { justify-content: flex-end; }
    .cliente-wa-bubble {
        max-width: 88%;
        border-radius: 0.625rem;
        padding: 0.4rem 0.55rem 0.25rem;
        box-shadow: 0 1px 0.5px rgb(0 0 0 / 0.08);
    }
    .cliente-wa-bubble--in { background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }
    html.dark .cliente-wa-bubble--in { background: #262626; color: #f5f5f5; border: none; }
    .cliente-wa-bubble--out { background: #2563eb; color: #fff; border: none; }
    .cliente-wa-bubble--fail { background: #991b1b; color: #fff; border: none; }
    .cliente-wa-bubble__text {
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .cliente-wa-bubble__foot {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.25rem;
        margin-top: 0.125rem;
    }
    .cliente-wa-bubble__hora {
        font-size: 0.625rem;
        opacity: 0.72;
    }
    .cliente-wa-bubble__checks {
        font-size: 0.6875rem;
        color: #86efac;
        line-height: 1;
    }
    .cliente-wa-bubble__meta {
        margin-bottom: 0.125rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.75;
    }
    .cliente-wa-bubble__img-btn {
        display: block;
        max-width: 100%;
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        cursor: zoom-in;
        text-align: left;
    }
    .cliente-wa-bubble__img {
        display: block;
        max-height: 9rem;
        max-width: 100%;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
    }
    .cliente-wa-lightbox {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgb(0 0 0 / 0.82);
    }
    .cliente-wa-lightbox[hidden] { display: none !important; }
    .cliente-wa-lightbox__img {
        max-width: min(96vw, 56rem);
        max-height: 88vh;
        border-radius: 0.5rem;
        object-fit: contain;
        box-shadow: 0 20px 50px rgb(0 0 0 / 0.45);
    }
    .cliente-wa-lightbox__close {
        position: absolute;
        top: 0.85rem;
        right: 0.85rem;
        border: 0;
        border-radius: 9999px;
        background: rgb(255 255 255 / 0.16);
        color: #fff;
        padding: 0.35rem 0.75rem;
        font-size: 0.8125rem;
        cursor: pointer;
    }
    .cliente-wa-lightbox__close:hover {
        background: rgb(255 255 255 / 0.28);
    }
    .cliente-wa-bubble__link {
        display: inline-block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        text-decoration: underline;
        opacity: 0.9;
    }
    .cliente-wa-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        height: 100%;
        min-height: 12rem;
        padding: 1.5rem 1rem;
        text-align: center;
        color: #6b7280;
        font-size: 0.8125rem;
    }
    html.dark .cliente-wa-empty { color: #8696a0; }
</style>
