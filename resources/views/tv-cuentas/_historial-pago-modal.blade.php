<dialog id="tv-historial-pago-modal" class="rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-0 w-[min(100%,42rem)] max-h-[min(90vh,720px)] backdrop:bg-black/40">
    <div class="flex flex-col max-h-[min(90vh,720px)]">
        <div class="flex items-start justify-between gap-3 p-5 border-b border-gray-100 dark:border-gray-700 shrink-0">
            <div class="min-w-0">
                <h2 id="tv-historial-pago-title" class="text-lg font-semibold truncate">Historial de pago</h2>
                <p id="tv-historial-pago-subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate"></p>
            </div>
            <button type="button" onclick="document.getElementById('tv-historial-pago-modal').close()"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none shrink-0">&times;</button>
        </div>
        <div id="tv-historial-pago-body" class="p-5 overflow-y-auto text-sm">
            <p class="text-gray-500 dark:text-gray-400">Cargando…</p>
        </div>
    </div>
</dialog>

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('tv-historial-pago-modal');
    const body = document.getElementById('tv-historial-pago-body');
    const title = document.getElementById('tv-historial-pago-title');
    const subtitle = document.getElementById('tv-historial-pago-subtitle');

    if (!modal || !body || !title || !subtitle) return;

    const fmtGs = (n) => 'Gs. ' + Number(n || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 });

    const badgeEstado = (estado) => {
        const map = {
            pagada: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            emitida: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        };
        return map[estado] || 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
    };

    const renderHistorial = (data) => {
        const cliente = data.cliente || {};
        const resumen = data.resumen || {};
        const lineas = data.lineas || [];

        subtitle.textContent = [
            cliente.nombre,
            cliente.cedula ? '(' + cliente.cedula + ')' : '',
            data.servicio_id ? '· Servicio #' + data.servicio_id : '',
            data.cuenta_tv?.usuario_app ? '· ' + data.cuenta_tv.usuario_app : '',
        ].filter(Boolean).join(' ');

        if (lineas.length === 0) {
            body.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No hay facturas de App TV registradas para este cliente.</p>';
            return;
        }

        let html = `
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Facturado TV</p>
                    <p class="text-base font-semibold mt-0.5">${fmtGs(resumen.total_facturado)}</p>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Pendiente</p>
                    <p class="text-base font-semibold mt-0.5 ${resumen.total_pendiente > 0 ? 'text-amber-700 dark:text-amber-300' : ''}">${fmtGs(resumen.total_pendiente)}</p>
                </div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Facturas</p>
                    <p class="text-base font-semibold mt-0.5">${resumen.cantidad_facturas || lineas.length}</p>
                </div>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Emisión</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Período</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Monto TV</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Estado</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Cobros</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        `;

        lineas.forEach((linea) => {
            const facturaLink = linea.url_factura
                ? `<a href="${linea.url_factura}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">#${linea.factura_id}</a>`
                : `#${linea.factura_id}`;

            const cobros = (linea.cobros || []).map((c) => {
                const monto = fmtGs(c.monto);
                return c.url
                    ? `<a href="${c.url}" class="block text-purple-600 dark:text-purple-400 hover:underline">${c.fecha} · ${monto}</a>`
                    : `<span class="block">${c.fecha} · ${monto}</span>`;
            }).join('') || '<span class="text-gray-400">—</span>';

            html += `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                    <td class="px-3 py-2 whitespace-nowrap">${linea.fecha_emision || '—'}<div class="text-[10px] text-gray-400">${facturaLink}</div></td>
                    <td class="px-3 py-2">${linea.periodo || '—'}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap font-medium">${fmtGs(linea.monto_tv)}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${badgeEstado(linea.estado)}">${linea.estado_label || linea.estado}</span>
                        ${linea.saldo_pendiente > 0.009 ? `<div class="text-[10px] text-amber-700 dark:text-amber-300 mt-0.5">Debe ${fmtGs(linea.saldo_pendiente)}</div>` : ''}
                    </td>
                    <td class="px-3 py-2">${cobros}</td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        body.innerHTML = html;
    };

    window.abrirHistorialPagoTv = function (url, titulo) {
        title.textContent = titulo || 'Historial de pago';
        subtitle.textContent = '';
        body.innerHTML = '<p class="text-gray-500 dark:text-gray-400">Cargando…</p>';
        modal.showModal();

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((res) => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(renderHistorial)
            .catch(() => {
                body.innerHTML = '<p class="text-red-600 dark:text-red-400">No se pudo cargar el historial de pago.</p>';
            });
    };

    document.querySelectorAll('[data-tv-historial-pago]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const url = btn.getAttribute('data-tv-historial-pago');
            const titulo = btn.getAttribute('data-tv-historial-titulo') || 'Historial de pago';
            if (url) window.abrirHistorialPagoTv(url, titulo);
        });
    });
})();
</script>
@endpush
