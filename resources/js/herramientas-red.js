import './bootstrap';
import '../css/herramientas-red.css';
import { createApp } from 'vue';
import HerramientasRed from '@/components/HerramientasRed.vue';

const el = document.getElementById('herramientas-red-app');
if (el) {
  const cfg = typeof window.__HERRAMIENTAS_RED_CONFIG__ !== 'undefined'
    ? window.__HERRAMIENTAS_RED_CONFIG__
    : {};
  createApp(HerramientasRed, {
    compact: !!cfg.compact,
    initialPayload: cfg.initialPayload || null,
    servicios: Array.isArray(cfg.servicios) ? cfg.servicios : [],
  }).mount(el);
}
