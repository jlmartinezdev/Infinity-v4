import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import PedidoForm from '@/components/PedidoForm.vue';

const el = document.getElementById('pedido-form-app');
if (el) {
  const cfg = typeof window.__PEDIDO_FORM_CONFIG__ !== 'undefined' ? window.__PEDIDO_FORM_CONFIG__ : {};
  const app = createApp(PedidoForm, {
    pedidoId: cfg.pedidoId || 'Nuevo',
    planes: cfg.planes || [],
    estadoId: cfg.estadoId || 1,
    buscarClienteUrl: cfg.buscarClienteUrl || '',
    consultarPadronUrl: cfg.consultarPadronUrl || '',
    submitUrl: cfg.submitUrl || '',
    cancelUrl: cfg.cancelUrl || '',
    csrfToken: cfg.csrfToken || '',
    modalMode: cfg.modalMode === true,
  });
  app.mount(el);
}
