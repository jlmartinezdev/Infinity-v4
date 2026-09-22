import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import PedidoProductos from '@/components/PedidoProductos.vue';

const cfg = typeof window.__PEDIDO_PRODUCTOS_CONFIG__ !== 'undefined' ? window.__PEDIDO_PRODUCTOS_CONFIG__ : {};
const el = document.getElementById('pedido-productos-app');

if (el) {
  createApp(PedidoProductos, {
    productos: cfg.productos || [],
    proveedores: cfg.proveedores || [],
    categorias: cfg.categorias || [],
  }).mount(el);
}
