import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import CobrosServiciosList from '@/components/CobrosServiciosList.vue';

const el = document.getElementById('cobros-servicios-app');
if (el) {
  const cfg = typeof window.__COBROS_SERVICIOS_CONFIG__ !== 'undefined' ? window.__COBROS_SERVICIOS_CONFIG__ : {};
  const app = createApp(CobrosServiciosList, {
    servicios: cfg.servicios || [],
    urlCobrosIndex: cfg.urlCobrosIndex || '',
    urlEditServicioBase: cfg.urlEditServicioBase || '',
    urlCrearCobroBase: cfg.urlCrearCobroBase || '',
    canCrearCobro: cfg.canCrearCobro ?? false,
  });
  app.mount(el);
}
