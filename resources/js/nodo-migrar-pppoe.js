import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import NodoMigrarPppoe from '@/components/NodoMigrarPppoe.vue';

const el = document.getElementById('nodo-migrar-pppoe-app');
if (el) {
  const cfg = typeof window.__NODO_MIGRAR_PPPOE_CONFIG__ !== 'undefined' ? window.__NODO_MIGRAR_PPPOE_CONFIG__ : {};
  createApp(NodoMigrarPppoe, {
    nodo: cfg.nodo || {},
    canEditar: cfg.canEditar ?? false,
    urlIndex: cfg.urlIndex || '',
    urlDatos: cfg.urlDatos || '',
    urlServicios: cfg.urlServicios || '',
    urlPools: cfg.urlPools || '',
    urlEjecutar: cfg.urlEjecutar || '',
    csrfToken: cfg.csrfToken || '',
  }).mount(el);
}
