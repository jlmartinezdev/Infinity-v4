import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import TareasKanban from '@/components/TareasKanban.vue';

const el = document.getElementById('tareas-kanban-app');
if (el) {
  const cfg = typeof window.__TAREAS_KANBAN_CONFIG__ !== 'undefined' ? window.__TAREAS_KANBAN_CONFIG__ : {};
  const app = createApp(TareasKanban, {
    tareas: cfg.tareas || [],
    usuarios: cfg.usuarios || [],
    canCreate: cfg.canCreate ?? false,
    csrfToken: cfg.csrfToken || '',
    urlStore: cfg.urlStore || '',
    urlUpdate: cfg.urlUpdate || '',
    urlMove: cfg.urlMove || '',
    urlDestroy: cfg.urlDestroy || '',
  });
  app.mount(el);
}
