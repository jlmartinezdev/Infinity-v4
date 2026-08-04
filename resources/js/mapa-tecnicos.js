import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import MapaTecnicos from '@/components/MapaTecnicos.vue';

const el = document.getElementById('mapa-tecnicos-app');
if (el) {
  const cfg = typeof window.__MAPA_TECNICOS_CONFIG__ !== 'undefined' ? window.__MAPA_TECNICOS_CONFIG__ : {};
  createApp(MapaTecnicos, {
    apiKey: cfg.apiKey || '',
    urlUbicaciones: cfg.urlUbicaciones || '',
    urlClientes: cfg.urlClientes || '',
    urlPedidos: cfg.urlPedidos || '',
    pollSegundos: Number(cfg.pollSegundos) || 15,
    centerLat: Number(cfg.centerLat) || -25.2867,
    centerLng: Number(cfg.centerLng) || -57.647,
  }).mount(el);
}
