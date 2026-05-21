import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import NotasCreditoIndex from '@/components/NotasCreditoIndex.vue';

const el = document.getElementById('notas-credito-app');
if (el) {
  const cfg = typeof window.__NOTAS_CREDITO_CONFIG__ !== 'undefined' ? window.__NOTAS_CREDITO_CONFIG__ : {};
  const app = createApp(NotasCreditoIndex, {
    listUrl: cfg.listUrl || '',
    facturaBaseUrl: cfg.facturaBaseUrl || '',
    clientes: cfg.clientes || [],
  });
  app.mount(el);
}
