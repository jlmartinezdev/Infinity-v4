import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import PendientesPago from '@/components/PendientesPago.vue';

const el = document.getElementById('pendientes-pago-app');
if (el) {
  const cfg = typeof window.__PENDIENTES_PAGO_CONFIG__ !== 'undefined' ? window.__PENDIENTES_PAGO_CONFIG__ : {};
  const app = createApp(PendientesPago, {
    listUrl: cfg.listUrl || '',
    exportExcelUrl: cfg.exportExcelUrl || '',
    pfKeys: cfg.pfKeys || [],
    urls: cfg.urls || {},
    templates: cfg.templates || {},
    clienteDetalleTpl: cfg.clienteDetalleTpl || '',
    canMulticobro: cfg.canMulticobro ?? false,
    canCrearCobro: cfg.canCrearCobro ?? false,
    canVerClienteDetalle: cfg.canVerClienteDetalle ?? false,
    flashSuccess: cfg.flashSuccess || '',
    flashError: cfg.flashError || '',
  });
  app.mount(el);
}
