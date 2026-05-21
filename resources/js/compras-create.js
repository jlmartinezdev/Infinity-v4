import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import ComprasCreatePanel from '@/components/ComprasCreatePanel.vue';

const cfg = typeof window.__COMPRA_CREATE_CONFIG__ !== 'undefined' ? window.__COMPRA_CREATE_CONFIG__ : {};
const productos = cfg.productos || [];
const oldDetalles = cfg.oldDetalles || [];

const panelEl = document.getElementById('compra-detalle-app');
let panelVm = null;

if (panelEl) {
  const app = createApp(ComprasCreatePanel, {
    productos,
    oldDetalles,
    formId: 'form-compra',
    cancelUrl: cfg.cancelUrl || '/compras',
  });
  panelVm = app.mount(panelEl);
}

const buscarProductoInput = document.getElementById('buscar-producto');
const catalogoBody = document.getElementById('catalogo-body');
let lastFiltered = [];

const formatMoney = (value) => new Intl.NumberFormat('es-AR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
}).format(parseFloat(value) || 0);

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

const renderCatalogo = (filtro = '') => {
  if (!catalogoBody) return;
  const term = (filtro || '').toLowerCase().trim();
  const filtrados = term
    ? productos.filter((p) => `${p.codigo ?? ''} ${p.nombre}`.toLowerCase().includes(term))
    : productos;
  lastFiltered = filtrados;

  if (!filtrados.length) {
    catalogoBody.innerHTML = '<tr><td colspan="4" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">No hay articulos para este filtro.</td></tr>';
    return;
  }

  catalogoBody.innerHTML = filtrados.map((p) => `
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
      <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">${escapeHtml(p.codigo || '-')}</td>
      <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">${escapeHtml(p.nombre)}</td>
      <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">${formatMoney(p.precio_compra)}</td>
      <td class="px-3 py-2 text-right">
        <button type="button" data-producto-id="${p.id}" class="btn-agregar-catalogo text-xs px-2 py-1 rounded bg-purple-600 text-white hover:bg-purple-700">+</button>
      </td>
    </tr>
  `).join('');
};

if (catalogoBody) {
  catalogoBody.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-agregar-catalogo');
    if (!btn || !panelVm) return;
    panelVm.addOrIncrementProducto(btn.dataset.productoId);
  });
}

if (buscarProductoInput) {
  buscarProductoInput.addEventListener('input', (e) => renderCatalogo(e.target.value));
  buscarProductoInput.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (!lastFiltered.length || !panelVm) return;
    panelVm.addOrIncrementProducto(lastFiltered[0].id);
  });
}

renderCatalogo();
