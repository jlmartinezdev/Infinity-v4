import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import MapasPedidos from '@/components/MapasPedidos.vue';

const el = document.getElementById('mapas-pedidos-app');
if (el) {
  const cfg = typeof window.__MAPAS_PEDIDOS_CONFIG__ !== 'undefined' ? window.__MAPAS_PEDIDOS_CONFIG__ : {};
  const app = createApp(MapasPedidos, {
    apiKey: cfg.apiKey || '',
    pedidos: Array.isArray(cfg.pedidos) ? cfg.pedidos : [],
    nodos: cfg.nodos || [],
    planes: cfg.planes || [],
    tiposTecnologia: cfg.tiposTecnologia || [],
    aprobarEstadoUrl: cfg.aprobarEstadoUrl || '',
    urlOpcionesNodoAprobacion: cfg.urlOpcionesNodoAprobacion || '',
    urlClientes: cfg.urlClientes || '',
  });
  app.mount(el);
}
