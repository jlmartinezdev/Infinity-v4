import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import UsuarioManagement from '@/components/UsuarioManagement.vue';

const el = document.getElementById('usuario-management-app');
if (el) {
  const cfg = typeof window.__USUARIO_MANAGEMENT_CONFIG__ !== 'undefined' ? window.__USUARIO_MANAGEMENT_CONFIG__ : {};
  const app = createApp(UsuarioManagement, {
    csrfToken: cfg.csrfToken || '',
    roles: cfg.roles || [],
    storeUrl: cfg.storeUrl || '',
    updateUrl: cfg.updateUrl || '',
    aprobarUrl: cfg.aprobarUrl || '',
    editDataUrl: cfg.editDataUrl || '',
  });
  app.mount(el);
}
