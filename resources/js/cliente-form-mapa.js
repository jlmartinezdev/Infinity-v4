import './bootstrap';
import { createApp } from 'vue';
import ClienteFormMapa from '@/components/ClienteFormMapa.vue';

const el = document.getElementById('cliente-form-mapa-app');
if (el) {
  const cfg = typeof window.__CLIENTE_FORM_MAPA_CONFIG__ !== 'undefined' ? window.__CLIENTE_FORM_MAPA_CONFIG__ : {};
  const app = createApp(ClienteFormMapa, {
    apiKey: cfg.apiKey || '',
    initialLat: cfg.initialLat != null ? Number(cfg.initialLat) : null,
    initialLon: cfg.initialLon != null ? Number(cfg.initialLon) : null,
  });
  app.mount(el);
}
