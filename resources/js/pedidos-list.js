import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import PedidosList from '@/components/PedidosList.vue';

const el = document.getElementById('pedidos-app') || document.getElementById('pedidos-list-app');
if (el) {
  const cfg = typeof window.__PEDIDOS_APP_CONFIG__ !== 'undefined'
    ? window.__PEDIDOS_APP_CONFIG__
    : (typeof window.__PEDIDOS_LIST_CONFIG__ !== 'undefined' ? window.__PEDIDOS_LIST_CONFIG__ : {});
  const app = createApp(PedidosList, {
    pedidos: cfg.pedidos || [],
    estados: cfg.estados || [],
    nodos: cfg.nodos || [],
    planes: cfg.planes || [],
    tiposTecnologia: cfg.tiposTecnologia || [],
    clientes: cfg.clientes || [],
    csrfToken: cfg.csrfToken || '',
    urlPedidosIndex: cfg.urlPedidosIndex || '',
    urlPedidosStore: cfg.urlPedidosStore || '',
    pedidoFormConfig: cfg.pedidoFormConfig || {},
    filtroEstadoId: cfg.filtroEstadoId || '',
    filtroClienteId: cfg.filtroClienteId || '',
    mostrarInstaladosInitial: cfg.mostrarInstaladosInitial || '1',
    aprobarEstadoUrl: cfg.aprobarEstadoUrl || '',
    descartarEstadoUrl: cfg.descartarEstadoUrl || '',
    reabrirEstadoUrl: cfg.reabrirEstadoUrl || '',
    crearUsuarioPppoeUrl: cfg.crearUsuarioPppoeUrl || '',
    crearAgendaUrl: cfg.crearAgendaUrl || '',
    finalizarPedidoUrl: cfg.finalizarPedidoUrl || '',
    urlExportarExcel: cfg.urlExportarExcel || '',
  });
  app.mount(el);
}
