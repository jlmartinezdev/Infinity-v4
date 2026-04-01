import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import Sidebar from '@/components/Sidebar.vue';

const el = document.getElementById('sidebar-app');
if (el) {
  const cfg = typeof window.__SIDEBAR_CONFIG__ !== 'undefined' ? window.__SIDEBAR_CONFIG__ : {};
  const app = createApp(Sidebar, {
    menu: cfg.menu || null,
    user: cfg.user || null,
    isOpen: undefined,
  });
  app.mount(el);
}
