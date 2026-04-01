import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import ServicioFormIps from '@/components/ServicioFormIps.vue';

const el = document.getElementById('servicio-form-ips-app');
if (el) {
  const cfg = typeof window.__SERVICIO_FORM_IPS_CONFIG__ !== 'undefined' ? window.__SERVICIO_FORM_IPS_CONFIG__ : {};
  const app = createApp(ServicioFormIps, {
    ipsDisponiblesUrl: cfg.ipsDisponiblesUrl || '',
  });
  app.mount(el);
}
