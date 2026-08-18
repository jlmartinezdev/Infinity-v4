import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import ApsWirelessMonitor from '@/components/ApsWirelessMonitor.vue';

const el = document.getElementById('aps-wireless-app');
if (el) {
  const cfg = typeof window.__APS_WIRELESS_CONFIG__ !== 'undefined' ? window.__APS_WIRELESS_CONFIG__ : {};
  createApp(ApsWirelessMonitor, {
    initialConfig: cfg,
  }).mount(el);
}
