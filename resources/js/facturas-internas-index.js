import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import FacturasInternasIndex from '@/components/FacturasInternasIndex.vue';

const el = document.getElementById('facturas-internas-app');
if (el) {
  const cfg = typeof window.__FACTURAS_INTERNAS_CONFIG__ !== 'undefined' ? window.__FACTURAS_INTERNAS_CONFIG__ : {};
  const app = createApp(FacturasInternasIndex, {
    listUrl: cfg.listUrl || '',
    facturaBaseUrl: cfg.facturaBaseUrl || '',
    urlGenerarInterna: cfg.urlGenerarInterna || '',
    urlEjecutarCrear: cfg.urlEjecutarCrear || '',
    csrfToken: cfg.csrfToken || '',
    clientes: cfg.clientes || [],
    estados: cfg.estados || {},
    canEjecutarCrear: cfg.canEjecutarCrear ?? false,
    canEditar: cfg.canEditar ?? false,
    canEliminar: cfg.canEliminar ?? false,
    flashSuccess: cfg.flashSuccess || '',
    flashError: cfg.flashError || '',
  });
  app.mount(el);
}
