import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import ServiciosIndex from '@/components/ServiciosIndex.vue';

const el = document.getElementById('servicios-index-app');
if (el) {
  const cfg = typeof window.__SERVICIOS_INDEX_CONFIG__ !== 'undefined' ? window.__SERVICIOS_INDEX_CONFIG__ : {};
  const app = createApp(ServiciosIndex, {
    servicios: cfg.servicios || [],
    nodos: cfg.nodos || [],
    clientes: cfg.clientes || [],
    canCreateFactura: cfg.canCreateFactura ?? false,
    canCancelarServicio: cfg.canCancelarServicio ?? false,
    formAction: cfg.formAction || '',
    csrfToken: cfg.csrfToken || '',
    urlIndex: cfg.urlIndex || '',
    urlCreate: cfg.urlCreate || '',
    urlEdit: cfg.urlEdit || '',
    urlMigrar: cfg.urlMigrar || '',
    urlDestroy: cfg.urlDestroy || '',
    urlActivar: cfg.urlActivar || '',
    urlSuspender: cfg.urlSuspender || '',
    urlCancelar: cfg.urlCancelar || '',
    urlSyncPppoe: cfg.urlSyncPppoe || '',
    urlCrearFacturaInterna: cfg.urlCrearFacturaInterna || '',
    filtros: cfg.filtros || { buscar: '', cliente_id: '', nodo_id: '', estado: 'todos', estado_pago: 'todos', app_tv: 'todos', fecha_desde: '', fecha_hasta: '' },
  });
  app.mount(el);
}
