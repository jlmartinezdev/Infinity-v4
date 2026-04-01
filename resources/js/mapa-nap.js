import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import MapaNap from '@/components/MapaNap.vue';

const el = document.getElementById('mapa-nap-app');
if (el) {
  const cfg = typeof window.__MAPA_NAP_CONFIG__ !== 'undefined' ? window.__MAPA_NAP_CONFIG__ : {};
  const app = createApp(MapaNap, {
    apiKey: cfg.apiKey || '',
    mapaDataUrl: cfg.mapaDataUrl || '',
    nodoId: cfg.nodoId || '',
  });
  app.mount(el);

  // Filtro de nodo: recargar con query param
  const filtroNodo = document.getElementById('filtro-nodo');
  if (filtroNodo) {
    filtroNodo.addEventListener('change', () => {
      const nodoId = filtroNodo.value;
      const url = new URL(window.location.href);
      if (nodoId) url.searchParams.set('nodo_id', nodoId);
      else url.searchParams.delete('nodo_id');
      window.location.href = url.toString();
    });
  }
}
