<style>
    .cliente-detalle-page {
        --cd-bg: #0f172a;
        --cd-card: #1e293b;
        --cd-card-border: #334155;
        --cd-text: #f1f5f9;
        --cd-muted: #94a3b8;
        --cd-accent: #3b82f6;
        --cd-warn: #f59e0b;
        --cd-success: #10b981;
        color: var(--cd-text);
    }
    .cliente-detalle-container {
        width: 100%;
        max-width: 72rem; /* ~1152px — no estira en monitores grandes */
        margin-left: auto;
        margin-right: auto;
    }
    .cliente-detalle-page .cd-card {
        background: var(--cd-card);
        border: 1px solid var(--cd-card-border);
        border-radius: 1rem;
        box-shadow: 0 4px 24px rgb(0 0 0 / 0.18);
    }
    .cliente-detalle-page .cd-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--cd-muted);
    }
    .cliente-detalle-page .cd-table thead {
        background: rgb(15 23 42 / 0.55);
    }
    .cliente-detalle-page .cd-table th {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--cd-muted);
        padding: 0.625rem 1rem;
        text-align: left;
    }
    .cliente-detalle-page .cd-table td {
        padding: 0.75rem 1rem;
        font-size: 0.8125rem;
        color: #e2e8f0;
        border-top: 1px solid rgb(51 65 85 / 0.65);
    }
    .cliente-detalle-page .cd-table tbody tr:hover {
        background: rgb(51 65 85 / 0.25);
    }
    .cliente-detalle-page .cd-link {
        color: #60a5fa;
        text-decoration: none;
    }
    .cliente-detalle-page .cd-link:hover { text-decoration: underline; }
    .cliente-detalle-page .cd-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.125rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 600;
    }
    .cliente-detalle-page .cd-pill--ok {
        background: rgb(16 185 129 / 0.18);
        color: #6ee7b7;
    }
    .cliente-detalle-page .cd-pill--warn {
        background: rgb(245 158 11 / 0.18);
        color: #fcd34d;
    }
    .cliente-detalle-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
        align-items: start;
    }
    @media (min-width: 1280px) {
        .cliente-detalle-layout {
            grid-template-columns: minmax(0, 1fr) 320px;
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
        padding: 1.25rem;
    }
    .cliente-detalle-page .cd-summary__icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.12;
        width: 3.5rem;
        height: 3.5rem;
        color: #fff;
    }
    .cliente-detalle-page .cd-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 2rem 1rem;
        text-align: center;
        color: var(--cd-muted);
        font-size: 0.8125rem;
    }
    .cliente-detalle-page .cd-empty svg {
        width: 2rem;
        height: 2rem;
        opacity: 0.45;
    }

    /* Sidebar chat */
    .cliente-wa-sidebar {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 28rem;
        max-height: min(32rem, calc(100vh - 6rem));
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #334155;
        background: #0b141a;
        box-shadow: 0 8px 32px rgb(0 0 0 / 0.35);
    }
    .cliente-wa-sidebar__head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid rgb(255 255 255 / 0.06);
        background: #202c33;
        padding: 0.875rem 1rem;
    }
    .cliente-wa-sidebar__avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #6366f1, #3b82f6);
        font-size: 0.875rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    .cliente-wa-sidebar__name {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #f8fafc;
        line-height: 1.2;
    }
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
        color: #94a3b8;
        transition: background 0.15s, color 0.15s;
    }
    .cliente-wa-sidebar__icon-btn:hover {
        background: rgb(255 255 255 / 0.08);
        color: #e2e8f0;
    }
    .cliente-wa-sidebar__hilo {
        flex: 1;
        overflow-y: auto;
        padding: 0.875rem 0.75rem;
        background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 18px 18px;
    }
    .cliente-wa-sidebar__foot {
        border-top: 1px solid rgb(255 255 255 / 0.06);
        background: #202c33;
        padding: 0.75rem;
    }
    .cliente-wa-sidebar__composer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        background: #2a3942;
        padding: 0.5rem 0.875rem;
        color: #8696a0;
        font-size: 0.8125rem;
        text-decoration: none;
    }
    .cliente-wa-sidebar__composer:hover {
        background: #32424a;
        color: #cbd5e1;
    }
    .cliente-wa-dia {
        display: flex;
        justify-content: center;
        margin: 0.625rem 0;
    }
    .cliente-wa-dia span {
        border-radius: 0.5rem;
        background: #182229;
        padding: 0.25rem 0.75rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: #aebac1;
    }
    .cliente-wa-row { display: flex; margin-bottom: 0.375rem; }
    .cliente-wa-row--in { justify-content: flex-start; }
    .cliente-wa-row--out { justify-content: flex-end; }
    .cliente-wa-bubble {
        max-width: 88%;
        border-radius: 0.625rem;
        padding: 0.45rem 0.625rem 0.3rem;
        box-shadow: 0 1px 0.5px rgb(0 0 0 / 0.15);
    }
    .cliente-wa-bubble--in { background: #262626; color: #f5f5f5; }
    .cliente-wa-bubble--out { background: #2563eb; color: #fff; }
    .cliente-wa-bubble--fail { background: #991b1b; }
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
    .cliente-wa-bubble__img {
        display: block;
        max-height: 9rem;
        max-width: 100%;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
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
        padding: 2rem 1rem;
        text-align: center;
        color: #8696a0;
        font-size: 0.8125rem;
    }
</style>
