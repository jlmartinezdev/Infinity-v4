<style>
    .ticket-wa-panel {
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid #1f3d32;
        background: #0b141a;
        color: #e9edef;
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.04), 0 8px 24px rgb(0 0 0 / 0.28);
    }
    .ticket-wa-panel__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border-bottom: 1px solid rgb(255 255 255 / 0.06);
        background: #202c33;
        padding: 0.75rem 1rem;
    }
    .ticket-wa-panel__title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #d1fae5;
    }
    .ticket-wa-panel__sub {
        margin-top: 0.125rem;
        font-size: 0.6875rem;
        color: #8696a0;
    }
    .ticket-wa-panel__btn {
        display: inline-flex;
        align-items: center;
        border-radius: 0.5rem;
        border: 1px solid #14532d;
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #bbf7d0;
        text-decoration: none;
        background: rgb(20 83 45 / 0.35);
    }
    .ticket-wa-panel__btn:hover {
        background: rgb(20 83 45 / 0.55);
        color: #ecfdf5;
    }
    .ticket-wa-hilo {
        max-height: 24rem;
        overflow-y: auto;
        padding: 0.875rem 0.75rem;
        background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 18px 18px;
    }
    .ticket-wa-dia {
        display: flex;
        justify-content: center;
        margin: 0.75rem 0;
    }
    .ticket-wa-dia span {
        border-radius: 0.5rem;
        background: #182229;
        padding: 0.25rem 0.75rem;
        font-size: 0.6875rem;
        color: #aebac1;
        box-shadow: 0 1px 0 rgb(0 0 0 / 0.2);
    }
    .ticket-wa-row {
        display: flex;
        margin-bottom: 0.375rem;
    }
    .ticket-wa-row--in { justify-content: flex-start; }
    .ticket-wa-row--out { justify-content: flex-end; }
    .ticket-wa-bubble {
        max-width: 85%;
        border-radius: 0.5rem;
        padding: 0.375rem 0.625rem 0.25rem;
        box-shadow: 0 1px 0.5px rgb(0 0 0 / 0.13);
    }
    .ticket-wa-bubble--in {
        background: #202c33;
        color: #e9edef;
    }
    .ticket-wa-bubble--out {
        background: #005c4b;
        color: #e9edef;
    }
    .ticket-wa-bubble--fail {
        background: #7f1d1d;
    }
    .ticket-wa-bubble__meta {
        margin-bottom: 0.125rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.75;
    }
    .ticket-wa-bubble__text {
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .ticket-wa-bubble__foot {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.375rem;
        margin-top: 0.125rem;
    }
    .ticket-wa-bubble__hora {
        font-size: 0.625rem;
        color: rgb(233 237 239 / 0.65);
    }
    .ticket-wa-bubble__estado {
        font-size: 0.625rem;
        color: rgb(233 237 239 / 0.75);
    }
    .ticket-wa-bubble__estado--fail { color: #fecaca; }
    .ticket-wa-bubble__img {
        display: block;
        max-height: 10rem;
        max-width: 100%;
        border-radius: 0.375rem;
        object-fit: contain;
    }
    .ticket-wa-bubble__link {
        display: inline-flex;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #86efac;
        text-decoration: underline;
    }
</style>
