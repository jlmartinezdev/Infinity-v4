import './bootstrap';
import { createApp, h } from 'vue';
import WhatsAppTestN8n from '@/components/WhatsAppTestN8n.vue';

const el = document.getElementById('whatsapp-test-n8n-app');
if (el) {
  const cfg = typeof window.__WHATSAPP_TEST_N8N_CONFIG__ !== 'undefined'
    ? window.__WHATSAPP_TEST_N8N_CONFIG__
    : {};

  createApp({
    render() {
      return h(WhatsAppTestN8n, {
        telInicial: cfg.telInicial || '595000000001',
        urls: cfg.urls || {},
      });
    },
  }).mount(el);
}
