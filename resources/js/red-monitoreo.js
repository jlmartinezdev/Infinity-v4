import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import RedMonitoreoTopology from '@/components/RedMonitoreoTopology.vue';

const el = document.getElementById('red-monitoreo-app');
if (el) {
  const cfg = typeof window.__RED_MONITOREO_CONFIG__ !== 'undefined' ? window.__RED_MONITOREO_CONFIG__ : {};
  createApp(RedMonitoreoTopology, {
    initialConfig: cfg,
  }).mount(el);
}
