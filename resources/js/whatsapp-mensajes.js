import './bootstrap';
import { createApp } from 'vue';
import WhatsAppChat from '@/components/WhatsAppChat.vue';

const el = document.getElementById('whatsapp-mensajes-app');
if (el) {
  const cfg = typeof window.__WHATSAPP_MENSAJES_CONFIG__ !== 'undefined'
    ? window.__WHATSAPP_MENSAJES_CONFIG__
    : {};
  createApp(WhatsAppChat, {
    telInicial: cfg.telInicial || null,
    buscarInicial: cfg.buscarInicial || '',
    configured: cfg.configured ?? false,
    puedeEditar: cfg.puedeEditar ?? false,
    urls: cfg.urls || {},
    flash: cfg.flash || {},
  }).mount(el);
}
