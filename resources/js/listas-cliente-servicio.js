import './bootstrap';
import '../css/app.css';
import { createApp, reactive, watch } from 'vue';
import ClientesList from '@/components/ClientesList.vue';
import ServiciosIndex from '@/components/ServiciosIndex.vue';

const SEARCH_PLACEHOLDERS = {
  clientes: 'Buscar por cédula, nombre, apellido, email o teléfono...',
  servicios: 'Buscar por cliente, plan, IP o PPPoE...',
};

function getInitialBuscar() {
  const cfg = window.__LISTAS_TABS_CONFIG__ || {};
  const params = new URLSearchParams(window.location.search);
  return params.get('buscar') ?? cfg.initialBuscar ?? '';
}

const sharedBuscar = reactive({ text: getInitialBuscar() });

function mountClientesList() {
  const el = document.getElementById('clientes-list-app');
  if (!el || el.__vue_app__) {
    return;
  }
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
    urlConsultarRucBase: cfg.urlConsultarRucBase || '',
    urlActualizarDesdeTempBase: cfg.urlActualizarDesdeTempBase || '',
    urlDetalleClienteBase: cfg.urlDetalleClienteBase || '',
    urlAccionesClienteBase: cfg.urlAccionesClienteBase || '',
    puedeEditar: cfg.puedeEditar ?? false,
    initialBuscar: cfg.initialBuscar || '',
    initialEstado: cfg.initialEstado || 'todos',
    initialSinServicio: cfg.initialSinServicio || '',
    sharedBuscar,
    hideSearchBar: true,
  });
  app.mount(el);
  el.__vue_app__ = app;
}

function mountServiciosIndex() {
  const el = document.getElementById('servicios-index-app');
  if (!el || el.__vue_app__) {
    return;
  }
  const cfg = typeof window.__SERVICIOS_INDEX_CONFIG__ !== 'undefined' ? window.__SERVICIOS_INDEX_CONFIG__ : {};
  const app = createApp(ServiciosIndex, {
    servicios: cfg.servicios || [],
    nodos: cfg.nodos || [],
    planes: cfg.planes || [],
    clientes: cfg.clientes || [],
    canCreateFactura: cfg.canCreateFactura ?? false,
    canCancelarServicio: cfg.canCancelarServicio ?? false,
    canDarBajaServicio: cfg.canDarBajaServicio ?? false,
    formAction: cfg.formAction || '',
    csrfToken: cfg.csrfToken || '',
    urlIndex: cfg.urlIndex || '',
    urlCreate: cfg.urlCreate || '',
    urlEdit: cfg.urlEdit || '',
    urlMigrar: cfg.urlMigrar || '',
    urlDestroy: cfg.urlDestroy || '',
    urlActivar: cfg.urlActivar || '',
    urlSuspender: cfg.urlSuspender || '',
    urlCancelar: cfg.urlCancelar || '',
    urlDarBaja: cfg.urlDarBaja || '',
    urlSyncPppoe: cfg.urlSyncPppoe || '',
    urlHerramientasRed: cfg.urlHerramientasRed || '',
    urlAccionesClienteBase: cfg.urlAccionesClienteBase || '',
    canVerClientes: cfg.canVerClientes ?? false,
    urlCrearFacturaInterna: cfg.urlCrearFacturaInterna || '',
    urlCrearFacturaServicioEspecial: cfg.urlCrearFacturaServicioEspecial || '',
    urlCrearFacturaFraccionDeuda: cfg.urlCrearFacturaFraccionDeuda || '',
    filtros: cfg.filtros || { buscar: '', cliente_id: '', nodo_id: '', plan_id: '', estado: 'todos', estado_pago: 'todos', app_tv: 'todos', fecha_desde: '', fecha_hasta: '' },
    sharedBuscar,
    hideSearchBar: true,
  });
  app.mount(el);
  el.__vue_app__ = app;
}

function updateSearchPlaceholder(input, tab) {
  if (!input) {
    return;
  }
  input.placeholder = SEARCH_PLACEHOLDERS[tab] || SEARCH_PLACEHOLDERS.clientes;
}

function initSharedSearch(getCurrentTab) {
  const input = document.getElementById('lista-buscar-input');
  const clearBtn = document.getElementById('lista-buscar-clear');
  if (!input) {
    return;
  }

  input.value = sharedBuscar.text;
  updateSearchPlaceholder(input, getCurrentTab());

  input.addEventListener('input', () => {
    sharedBuscar.text = input.value;
    if (clearBtn) {
      clearBtn.classList.toggle('hidden', !input.value);
    }
  });

  clearBtn?.addEventListener('click', () => {
    sharedBuscar.text = '';
    input.value = '';
    clearBtn.classList.add('hidden');
    input.focus();
  });

  watch(() => sharedBuscar.text, (val) => {
    const next = val ?? '';
    if (input.value !== next) {
      input.value = next;
    }
    if (clearBtn) {
      clearBtn.classList.toggle('hidden', !next);
    }
  });
}

function tabFromPathname(pathname, urls) {
  const serviciosPath = new URL(urls.servicios, window.location.origin).pathname;
  if (pathname === serviciosPath || pathname.startsWith(serviciosPath + '/')) {
    return 'servicios';
  }
  return 'clientes';
}

function setActiveTab(tab, urls, pushState = true) {
  const panels = document.querySelectorAll('[data-lista-panel]');
  panels.forEach((panel) => {
    panel.hidden = panel.dataset.listaPanel !== tab;
  });

  document.querySelectorAll('[data-lista-tab]').forEach((btn) => {
    const active = btn.dataset.listaTab === tab;
    btn.classList.toggle('bg-purple-600', active);
    btn.classList.toggle('text-white', active);
    btn.classList.toggle('shadow-sm', active);
    btn.classList.toggle('bg-gray-100', !active);
    btn.classList.toggle('text-gray-600', !active);
    btn.classList.toggle('hover:bg-gray-200', !active);
    btn.classList.toggle('dark:bg-gray-800', !active);
    btn.classList.toggle('dark:text-gray-300', !active);
    btn.classList.toggle('dark:hover:bg-gray-700', !active);
    btn.setAttribute('aria-selected', active ? 'true' : 'false');
  });

  updateSearchPlaceholder(document.getElementById('lista-buscar-input'), tab);
  document.title = tab === 'servicios' ? 'Lista de servicios' : 'Lista de clientes';

  if (pushState) {
    const target = tab === 'servicios' ? urls.servicios : urls.clientes;
    const targetPath = new URL(target, window.location.origin).pathname;
    if (window.location.pathname !== targetPath) {
      window.history.pushState({ listaTab: tab }, '', target);
    }
  }
}

function initListaTabs() {
  const cfg = window.__LISTAS_TABS_CONFIG__;
  if (!cfg) {
    mountClientesList();
    mountServiciosIndex();
    return;
  }

  mountClientesList();
  mountServiciosIndex();

  const urls = cfg.urls || {};
  let currentTab = cfg.initialTab || tabFromPathname(window.location.pathname, urls);

  initSharedSearch(() => currentTab);
  setActiveTab(currentTab, urls, false);
  window.history.replaceState({ listaTab: currentTab }, '', window.location.href);

  document.querySelectorAll('[data-lista-tab]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const tab = btn.dataset.listaTab;
      if (!tab || tab === currentTab) {
        return;
      }
      currentTab = tab;
      setActiveTab(tab, urls, true);
    });
  });

  window.addEventListener('popstate', () => {
    currentTab = window.history.state?.listaTab || tabFromPathname(window.location.pathname, urls);
    setActiveTab(currentTab, urls, false);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initListaTabs);
} else {
  initListaTabs();
}
