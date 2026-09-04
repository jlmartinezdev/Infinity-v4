import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import PendientesPago from '@/components/PendientesPago.vue';

const el = document.getElementById('pendientes-pago-app');
if (el) {
  const cfg = typeof window.__PENDIENTES_PAGO_CONFIG__ !== 'undefined' ? window.__PENDIENTES_PAGO_CONFIG__ : {};
  const app = createApp(PendientesPago, {
    listUrl: cfg.listUrl || '',
    mapPuntosUrl: cfg.mapPuntosUrl || '',
    googleMapsApiKey: cfg.googleMapsApiKey || '',
    exportExcelUrl: cfg.exportExcelUrl || '',
    exportExcelVencidosUrl: cfg.exportExcelVencidosUrl || '',
    pfKeys: cfg.pfKeys || [],
    urls: cfg.urls || {},
    templates: cfg.templates || {},
    clienteDetalleTpl: cfg.clienteDetalleTpl || '',
    canMulticobro: cfg.canMulticobro ?? false,
    canCrearCobro: cfg.canCrearCobro ?? false,
    canVerClienteDetalle: cfg.canVerClienteDetalle ?? false,
    esAdmin: cfg.esAdmin ?? false,
    flashSuccess: cfg.flashSuccess || '',
    flashError: cfg.flashError || '',
    nodos: cfg.nodos || [],
  });
  app.mount(el);
}
