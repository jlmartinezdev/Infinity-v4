import './bootstrap';
import '../css/app.css';
import { createApp } from 'vue';
import ClientesList from '@/components/ClientesList.vue';

const el = document.getElementById('clientes-list-app');
if (el) {
  const cfg = typeof window.__CLIENTES_LIST_CONFIG__ !== 'undefined' ? window.__CLIENTES_LIST_CONFIG__ : {};
  const app = createApp(ClientesList, {
    clientes: cfg.clientes || [],
    firstItem: cfg.firstItem ?? 1,
    csrfToken: cfg.csrfToken || '',
    urlEditClienteBase: cfg.urlEditClienteBase || '',
    urlDestroyClienteBase: cfg.urlDestroyClienteBase || '',
    urlCreateCliente: cfg.urlCreateCliente || '',
    urlEditServicioBase: cfg.urlEditServicioBase || '',
    urlCreateServicioBase: cfg.urlCreateServicioBase || '',
    urlBuscarTemp: cfg.urlBuscarTemp || '',
    urlActualizarDesdeTempBase: cfg.urlActualizarDesdeTempBase || '',
    puedeEditar: cfg.puedeEditar ?? false,
  });
  app.mount(el);
}
