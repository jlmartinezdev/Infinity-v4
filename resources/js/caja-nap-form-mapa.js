import './bootstrap';
import { createApp } from 'vue';
import CajaNapFormMapa from '@/components/CajaNapFormMapa.vue';

const el = document.getElementById('caja-nap-form-mapa-app');
if (el) {
  const cfg = typeof window.__CAJA_NAP_FORM_MAPA_CONFIG__ !== 'undefined' ? window.__CAJA_NAP_FORM_MAPA_CONFIG__ : {};
  const app = createApp(CajaNapFormMapa, {
    apiKey: cfg.apiKey || '',
    initialLat: cfg.initialLat != null ? Number(cfg.initialLat) : null,
    initialLon: cfg.initialLon != null ? Number(cfg.initialLon) : null,
  });
  app.mount(el);
}
