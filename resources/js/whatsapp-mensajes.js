import './bootstrap';
import { createApp, h } from 'vue';
import WhatsAppChat from '@/components/WhatsAppChat.vue';

const el = document.getElementById('whatsapp-mensajes-app');
if (el) {
  const cfg = typeof window.__WHATSAPP_MENSAJES_CONFIG__ !== 'undefined'
    ? window.__WHATSAPP_MENSAJES_CONFIG__
    : {};

  createApp({
    render() {
      return h(WhatsAppChat, {
        telInicial: cfg.telInicial || null,
        buscarInicial: cfg.buscarInicial || '',
        configured: !!cfg.configured,
        puedeEditar: !!cfg.puedeEditar,
        puedeCrearTicket: !!cfg.puedeCrearTicket,
        puedeCrearPedido: !!cfg.puedeCrearPedido,
        puedeCrearCobro: !!cfg.puedeCrearCobro,
        pedidoFormConfig: cfg.pedidoFormConfig || null,
        urls: cfg.urls || {},
        flash: cfg.flash || {},
      });
    },
  }).mount(el);
}
