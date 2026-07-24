<style>
    .diag-app-panel {
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid #334155;
        background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
        color: #e2e8f0;
        box-shadow: inset 0 1px 0 rgb(148 163 184 / 0.08), 0 8px 24px rgb(0 0 0 / 0.25);
    }
    .diag-app-panel__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        border-bottom: 1px solid #334155;
        padding: 0.875rem 1rem;
        background: rgb(15 23 42 / 0.85);
    }
    .diag-app-panel__title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #5eead4;
        letter-spacing: 0.01em;
    }
    .diag-app-panel__sub {
        margin-top: 0.125rem;
        font-size: 0.6875rem;
        color: #94a3b8;
    }
    .diag-app-panel__body {
        padding: 1rem;
    }
    .diag-app-panel__body > * + * {
        margin-top: 1.25rem;
    }
    .diag-app-report {
        border-radius: 0.5rem;
        border: 1px solid #475569;
        background: #1e293b;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        color: #cbd5e1;
    }
    .diag-app-report__label {
        font-weight: 600;
        color: #94a3b8;
    }
    .diag-app-section__title {
        margin-bottom: 0.5rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #7dd3fc;
    }
    .diag-app-metrics {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.625rem;
    }
    @media (min-width: 640px) {
        .diag-app-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .diag-app-metrics--wide { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .diag-app-ping-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .diag-app-metric {
        border-radius: 0.5rem;
        border: 1px solid #475569;
        background: #1e293b;
        padding: 0.625rem 0.75rem;
    }
    .diag-app-metric__label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .diag-app-metric__value {
        margin-top: 0.25rem;
        word-break: break-all;
        font-size: 0.875rem;
        color: #f1f5f9;
    }
    .diag-app-metric__value--good { color: #34d399; font-weight: 600; }
    .diag-app-metric__value--ok { color: #a3e635; font-weight: 600; }
    .diag-app-metric__value--warn { color: #fbbf24; font-weight: 600; }
    .diag-app-metric__value--bad { color: #f87171; font-weight: 600; }
    .diag-app-ping-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.625rem;
    }
    @media (min-width: 640px) {
        .diag-app-ping-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .diag-app-ping {
        border-radius: 0.5rem;
        border: 1px solid #1e40af;
        background: rgb(30 58 138 / 0.35);
        padding: 0.625rem 0.75rem;
    }
    .diag-app-ping__label {
        font-size: 0.6875rem;
        font-weight: 600;
        color: #93c5fd;
    }
    .diag-app-ping__value {
        margin-top: 0.25rem;
        font-size: 0.875rem;
        font-weight: 700;
        color: #f8fafc;
    }
    .diag-app-table-wrap {
        overflow-x: auto;
        border-radius: 0.5rem;
        border: 1px solid #475569;
    }
    .diag-app-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .diag-app-table thead {
        background: #1e293b;
    }
    .diag-app-table th {
        padding: 0.5rem 0.75rem;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .diag-app-table td {
        padding: 0.5rem 0.75rem;
        border-top: 1px solid #334155;
        color: #e2e8f0;
    }
    .diag-app-table tbody tr:hover {
        background: rgb(51 65 85 / 0.35);
    }
    .diag-app-table tr.diag-app-table__destino {
        background: rgb(6 78 59 / 0.28);
    }
    .diag-app-table tr.diag-app-table__destino td {
        color: #a7f3d0;
    }
    .diag-app-badge {
        display: inline-flex;
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 600;
    }
    .diag-app-badge--destino {
        background: rgb(6 78 59 / 0.55);
        color: #6ee7b7;
    }
    .diag-app-badge--transito {
        background: rgb(51 65 85 / 0.65);
        color: #cbd5e1;
    }
    .diag-app-location {
        border-radius: 0.5rem;
        border: 1px solid #475569;
        background: #1e293b;
        padding: 0.75rem;
    }
    .diag-app-location__coords {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.875rem;
        color: #f1f5f9;
    }
    .diag-app-link {
        display: inline-flex;
        margin-top: 0.5rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #5eead4;
        text-decoration: none;
    }
    .diag-app-link:hover { color: #99f6e4; text-decoration: underline; }
    .diag-app-btn {
        display: inline-flex;
        align-items: center;
        border-radius: 0.5rem;
        border: 1px solid #475569;
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #cbd5e1;
        text-decoration: none;
        background: rgb(30 41 59 / 0.8);
    }
    .diag-app-btn:hover {
        border-color: #64748b;
        background: #334155;
        color: #f8fafc;
    }
    .diag-app-json summary {
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        color: #5eead4;
    }
    .diag-app-json summary:hover { color: #99f6e4; }
    .diag-app-json pre {
        margin-top: 0.5rem;
        max-height: 12rem;
        overflow: auto;
        border-radius: 0.5rem;
        border: 1px solid #475569;
        background: #020617;
        padding: 0.75rem;
        font-size: 0.6875rem;
        line-height: 1.45;
        color: #cbd5e1;
    }
</style>
