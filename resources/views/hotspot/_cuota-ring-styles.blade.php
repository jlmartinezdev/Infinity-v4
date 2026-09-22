    .hotspot-cuota {
        position: relative;
        flex: none;
        display: inline-grid;
        place-items: center;
    }
    .hotspot-cuota--slot {
        width: 3.5rem;
        height: 3.5rem;
        margin-top: 0.05rem;
    }
    .hotspot-cuota--insp {
        width: 4.5rem;
        height: 4.5rem;
    }
    .hotspot-cuota--table {
        width: 2.75rem;
        height: 2.75rem;
    }
    .hotspot-cuota-svg {
        width: 100%;
        height: 100%;
        display: block;
        grid-area: 1 / 1;
    }
    .hotspot-cuota-track,
    .hotspot-cuota-fill {
        stroke-width: 3.25;
        stroke-linecap: round;
    }
    .hotspot-cuota-track {
        stroke: #e5e7eb;
    }
    html.dark .hotspot-cuota-track {
        stroke: #4b5563;
    }
    .hotspot-cuota-fill {
        stroke: #9333ea;
    }
    .hotspot-cuota.is-warn .hotspot-cuota-fill {
        stroke: #d97706;
    }
    .hotspot-cuota.is-danger .hotspot-cuota-fill {
        stroke: #dc2626;
    }
    .hotspot-cuota-center {
        grid-area: 1 / 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 1;
        pointer-events: none;
        color: #111827;
    }
    html.dark .hotspot-cuota-center {
        color: #f3f4f6;
    }
    .hotspot-cuota-num {
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .hotspot-cuota--table .hotspot-cuota-num {
        font-size: 0.625rem;
    }
    .hotspot-cuota--insp .hotspot-cuota-num {
        font-size: 0.875rem;
    }
    .hotspot-cuota-unit {
        margin-top: 0.05rem;
        font-size: 0.5625rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #6b7280;
    }
    html.dark .hotspot-cuota-unit {
        color: #9ca3af;
    }
    .hotspot-cuota--insp .hotspot-cuota-unit {
        font-size: 0.625rem;
    }
    .hotspot-cuota--table .hotspot-cuota-unit {
        font-size: 0.5rem;
    }
