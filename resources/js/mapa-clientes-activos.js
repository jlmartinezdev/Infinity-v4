import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import MapaClientesActivos from '@/components/MapaClientesActivos.vue';

const el = document.getElementById('mapa-clientes-activos-app');
if (el) {
  const cfg = typeof window.__MAPA_CLIENTES_ACTIVOS_CONFIG__ !== 'undefined' ? window.__MAPA_CLIENTES_ACTIVOS_CONFIG__ : {};
  const app = createApp(MapaClientesActivos, {
    apiKey: cfg.apiKey || '',
    puntos: Array.isArray(cfg.puntos) ? cfg.puntos : [],
    urlDetalleClienteBase: cfg.urlDetalleClienteBase || '',
  });
  app.mount(el);
}
