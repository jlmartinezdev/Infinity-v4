<template>
  <!-- Desktop Sidebar (collapsible) - Siempre tema oscuro -->
  <aside
    :class="[
      'fixed left-0 top-0 h-screen bg-gray-900 border-r border-gray-800 transition-all duration-300 z-40 overflow-x-hidden',
      'hidden lg:block',
      desktopExpanded ? 'w-64' : 'w-20'
    ]"
  >
    <!-- Logo -->
    <div class="h-16 flex items-center justify-center border-b border-gray-800 gap-2">
      <div v-if="desktopExpanded" class="flex items-center justify-between w-full px-3">
        <div class="flex items-center space-x-2 min-w-0">
          <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-pink-600 rounded-lg flex-shrink-0"></div>
          <span class="text-xl font-bold text-gray-100 truncate">Infinity ISP</span>
        </div>
        <button
          type="button"
          @click="toggleDesktop"
          class="p-1.5 rounded-lg hover:bg-gray-800 transition-colors flex-shrink-0"
          aria-label="Colapsar menú"
        >
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
          </svg>
        </button>
      </div>
      <template v-else>
        <button
          type="button"
          @click="toggleDesktop"
          class="p-2 rounded-lg hover:bg-gray-800 transition-colors"
          aria-label="Expandir menú"
        >
          <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-pink-600 rounded-lg"></div>
        </button>
      </template>
    </div>

    <!-- Navigation -->
    <nav
      ref="navEl"
      :class="[
        'px-3 py-4 space-y-1 overflow-y-auto overflow-x-hidden min-h-0',
        desktopExpanded && user ? 'h-[calc(100vh-8rem)]' : 'h-[calc(100vh-4rem)]'
      ]"
      @scroll="onNavScroll"
    >
      <template v-for="item in filteredMenu" :key="item.name">
        <!-- Item without submenu -->
        <a
          v-if="!item.submenu"
          :href="item.path"
          @click.prevent="handleMenuClick(item)"
          :class="[
            'flex items-center min-w-0 px-3 py-2.5 rounded-lg text-gray-200 hover:bg-gray-800 transition-colors group gap-2',
            isActive(item.path) ? 'bg-purple-900/20 text-purple-400' : ''
          ]"
        >
          <component :is="iconComponent(item.icon)" class="w-5 h-5 flex-shrink-0" />
          <span v-if="desktopExpanded" class="text-sm font-medium min-w-0 flex-1 truncate">{{ item.label }}</span>
          <span v-if="desktopExpanded && item.badge" class="bg-purple-600 text-white text-xs px-2 py-0.5 rounded-full flex-shrink-0">{{ item.badge }}</span>
        </a>

        <!-- Item with submenu -->
        <div v-else class="space-y-1">
          <div
            :class="[
              'flex items-center min-w-0 px-3 py-2.5 text-gray-200 cursor-pointer rounded-lg hover:bg-gray-800 group gap-2',
              isSubmenuActive(item) ? 'bg-purple-900/20 text-purple-400' : ''
            ]"
            @click="toggleSubmenu(item)"
          >
            <component :is="iconComponent(item.icon)" class="w-5 h-5 flex-shrink-0" />
            <span v-if="desktopExpanded" class="text-sm font-medium flex-1 min-w-0 truncate">{{ item.label }}</span>
            <span v-if="desktopExpanded" class="transition-transform duration-200" :class="{ 'rotate-180': isSubmenuExpanded(item) }">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </span>
          </div>
          <div v-if="desktopExpanded && isSubmenuExpanded(item)" class="pl-11 space-y-1">
            <a
              v-for="sub in item.submenu"
              :key="sub.name"
              :href="sub.path"
              @click.prevent="handleMenuClick(sub)"
              :class="[
                'flex items-center justify-between px-3 py-2 text-sm rounded-lg transition-colors truncate',
                isActive(sub.path)
                  ? 'text-purple-400 bg-purple-900/20'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-gray-100'
              ]"
            >
              <span class="truncate">{{ sub.label }}</span>
              <span v-if="sub.badge" class="ml-2 bg-purple-600 text-white text-xs px-2 py-0.5 rounded-full flex-shrink-0">{{ sub.badge }}</span>
            </a>
          </div>
        </div>
      </template>
    </nav>

    <!-- Footer usuario (solo desktop, cuando expandido) -->
    <div
      v-if="user && desktopExpanded"
      class="absolute bottom-0 left-0 right-0 border-t border-gray-800 p-4 bg-gray-900"
    >
      <div class="flex items-center space-x-3">
        <div class="flex-shrink-0">
          <div class="w-10 h-10 bg-gray-700 rounded-full flex items-center justify-center">
            <span class="text-sm font-medium text-gray-300">{{ userInitials }}</span>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-100 truncate">{{ user?.name }}</p>
          <p class="text-xs text-gray-400 truncate">{{ user?.email }}</p>
        </div>
      </div>
    </div>
  </aside>

  <!-- Mobile Sidebar (Modal) - Siempre tema oscuro -->
  <aside
    v-show="isOpen"
    class="fixed left-0 top-0 h-screen w-64 max-w-[min(100vw,16rem)] bg-gray-900 border-r border-gray-800 z-40 lg:hidden overflow-y-auto overflow-x-hidden"
  >
    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-800 sticky top-0 bg-gray-900 z-10">
      <div class="flex items-center space-x-2">
        <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-pink-600 rounded-lg"></div>
        <span class="text-xl font-bold text-gray-100">Infinity ISP</span>
      </div>
      <button
        type="button"
        @click="closeSidebar"
        class="p-1 rounded-lg hover:bg-gray-800 transition-colors lg:hidden"
        aria-label="Cerrar menú"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <nav class="px-3 py-4 space-y-1">
      <template v-for="item in filteredMenu" :key="item.name">
        <a
          v-if="!item.submenu"
          :href="item.path"
          @click.prevent="handleMenuClick(item)"
          :class="[
            'flex items-center min-w-0 gap-2 px-3 py-2.5 rounded-lg text-gray-200 hover:bg-gray-800 transition-colors',
            isActive(item.path) ? 'bg-purple-900/20 text-purple-400' : ''
          ]"
        >
          <component :is="iconComponent(item.icon)" class="w-5 h-5 flex-shrink-0" />
          <span class="text-sm font-medium min-w-0 flex-1 truncate">{{ item.label }}</span>
          <span v-if="item.badge" class="bg-purple-600 text-white text-xs px-2 py-0.5 rounded-full flex-shrink-0">{{ item.badge }}</span>
        </a>
        <div v-else class="space-y-1">
          <div
            :class="[
              'flex items-center min-w-0 gap-2 px-3 py-2.5 text-gray-200 cursor-pointer rounded-lg hover:bg-gray-800 group',
              isSubmenuActive(item) ? 'bg-purple-900/20 text-purple-400' : ''
            ]"
            @click="toggleSubmenu(item)"
          >
            <component :is="iconComponent(item.icon)" class="w-5 h-5 flex-shrink-0" />
            <span class="text-sm font-medium flex-1 min-w-0 truncate">{{ item.label }}</span>
            <span class="transition-transform duration-200" :class="{ 'rotate-180': isSubmenuExpanded(item) }">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </span>
          </div>
          <div v-if="isSubmenuExpanded(item)" class="pl-11 space-y-1">
            <a
              v-for="sub in item.submenu"
              :key="sub.name"
              :href="sub.path"
              @click.prevent="handleMenuClick(sub)"
              :class="[
                'flex items-center justify-between px-3 py-2 text-sm rounded-lg transition-colors',
                isActive(sub.path)
                  ? 'text-purple-400 bg-purple-900/20'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-gray-100'
              ]"
            >
              <span class="truncate">{{ sub.label }}</span>
              <span v-if="sub.badge" class="ml-2 bg-purple-600 text-white text-xs px-2 py-0.5 rounded-full flex-shrink-0">{{ sub.badge }}</span>
            </a>
          </div>
        </div>
      </template>
    </nav>

    <div v-if="user" class="border-t border-gray-800 p-4 mt-4">
      <div class="flex items-center space-x-3">
        <div class="flex-shrink-0">
          <div class="w-10 h-10 bg-gray-700 rounded-full flex items-center justify-center">
            <span class="text-sm font-medium text-gray-300">{{ userInitials }}</span>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-100 truncate">{{ user?.name }}</p>
          <p class="text-xs text-gray-400 truncate">{{ user?.email }}</p>
        </div>
      </div>
    </div>
  </aside>

  <div
    v-if="isOpen"
    @click="closeSidebar"
    class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    aria-hidden="true"
  ></div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const STORAGE_KEY_MENU = 'infinity_sidebar_menu_expanded';
const STORAGE_KEY_DESKTOP = 'infinity_sidebar_desktop_expanded';
const STORAGE_KEY_NAV_SCROLL = 'infinity_sidebar_nav_scroll';

function loadMenuExpanded() {
  try {
    const s = localStorage.getItem(STORAGE_KEY_MENU);
    return s ? JSON.parse(s) : {};
  } catch {
    return {};
  }
}

function saveMenuExpanded(obj) {
  try {
    localStorage.setItem(STORAGE_KEY_MENU, JSON.stringify(obj));
  } catch (_) {}
}

function loadDesktopExpanded() {
  try {
    const s = localStorage.getItem(STORAGE_KEY_DESKTOP);
    return s !== 'false';
  } catch {
    return true;
  }
}

function saveDesktopExpanded(val) {
  try {
    localStorage.setItem(STORAGE_KEY_DESKTOP, String(val));
  } catch (_) {}
}

const props = defineProps({
  user: { type: Object, default: null },
  isOpen: { type: Boolean, default: null },
  menu: { type: Array, default: null },
});

const emit = defineEmits(['toggle-sidebar', 'menu-click', 'update:desktopExpanded']);

const menuExpanded = ref(loadMenuExpanded());
const desktopExpanded = ref(loadDesktopExpanded());
const sidebarOpen = ref(false);
const navEl = ref(null);
let navScrollTimeout = null;

const isOpen = computed(() =>
  props.isOpen !== null && props.isOpen !== undefined ? props.isOpen : sidebarOpen.value
);

function isSubmenuExpanded(item) {
  if (!item.submenu) return false;
  const key = item.name;
  if (key in menuExpanded.value) return menuExpanded.value[key];
  return isSubmenuActive(item);
}

function toggleSubmenu(item) {
  if (!item.submenu) return;
  if (!desktopExpanded.value) {
    toggleDesktop();
    return;
  }
  const key = item.name;
  const next = !isSubmenuExpanded(item);
  menuExpanded.value = { ...menuExpanded.value, [key]: next };
  saveMenuExpanded(menuExpanded.value);
}

watch(menuExpanded, (val) => saveMenuExpanded(val), { deep: true });

const toggleDesktop = () => {
  desktopExpanded.value = !desktopExpanded.value;
  saveDesktopExpanded(desktopExpanded.value);
  emit('update:desktopExpanded', desktopExpanded.value);
};

function closeSidebar() {
  if (props.isOpen !== null && props.isOpen !== undefined) emit('toggle-sidebar');
  else sidebarOpen.value = false;
}

function emitToggle() {
  if (props.isOpen !== null && props.isOpen !== undefined) emit('toggle-sidebar');
  else sidebarOpen.value = !sidebarOpen.value;
}

// Iconos por clave (desde config PHP)
const HomeIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>` };
const UsersIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>` };
const WifiIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path></svg>` };
const DocumentTextIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>` };
const CurrencyDollarIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>` };
const TicketIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>` };
const CogIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>` };
const UserGroupIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>` };
const ServerIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>` };
const ClipboardListIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>` };
const CubeIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>` };
const TvIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>` };
const BoltIcon = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>` };

const iconMap = {
  home: HomeIcon,
  users: UsersIcon,
  wifi: WifiIcon,
  document: DocumentTextIcon,
  currency: CurrencyDollarIcon,
  ticket: TicketIcon,
  cog: CogIcon,
  'user-group': UserGroupIcon,
  server: ServerIcon,
  'clipboard-list': ClipboardListIcon,
  cube: CubeIcon,
  tv: TvIcon,
  bolt: BoltIcon,
};

function iconComponent(icon) {
  if (typeof icon === 'object' && icon !== null) return icon;
  return iconMap[icon] || DocumentTextIcon;
}

const defaultMenu = [
  { name: 'home', label: 'Inicio', path: '/', icon: 'home' },
  { name: 'clientes', label: 'Clientes', icon: 'users', submenu: [
    { name: 'lista-clientes', label: 'Lista clientes', path: '/clientes' },
    { name: 'lista-pedidos', label: 'Lista pedidos', path: '/pedidos' },
    { name: 'agenda', label: 'Agenda', path: '/agenda' },
    { name: 'mapas-pedidos', label: 'Mapas de pedidos', path: '/clientes/mapas-pedidos' },
  ]},
  { name: 'servicios', label: 'Servicios', path: '/servicios', icon: 'wifi' },
  { name: 'facturacion', label: 'Facturación', path: '/facturacion', icon: 'currency' },
  { name: 'tickets', label: 'Tickets', path: '/tickets', icon: 'ticket', badge: '3' },
  { name: 'usuarios', label: 'Usuarios', path: '/usuarios', icon: 'user-group' },
  { name: 'configuracion', label: 'Configuración', path: '/configuracion', icon: 'cog' },
  { name: 'mas', label: 'Más', icon: 'document', submenu: [
    { name: 'ayuda', label: 'Ayuda', path: '/ayuda' },
    { name: 'reportes', label: 'Reportes', path: '/reportes' },
  ]},
];

const filteredMenu = computed(() => {
  // Siempre usar el menú del backend cuando viene definido (ya filtrado por permisos).
  // Solo usar menú por defecto si el backend no envió menú (ej. página sin auth).
  if (props.menu !== null && props.menu !== undefined && Array.isArray(props.menu)) return props.menu;
  return defaultMenu;
});

const userInitials = computed(() => {
  if (!props.user?.name) return 'U';
  const names = props.user.name.split(' ');
  return names.length >= 2 ? (names[0][0] + names[1][0]).toUpperCase() : names[0][0].toUpperCase();
});

function isActive(path) {
  if (typeof window === 'undefined') return false;
  const p = window.location.pathname;
  if (path === '/' || path === '/inicio') {
    return p === '/' || p === '/inicio';
  }
  return p === path;
}

function isSubmenuActive(item) {
  return item.submenu && item.submenu.some((s) => isActive(s.path));
}

function handleMenuClick(item) {
  emit('menu-click', item);
  if (typeof window !== 'undefined' && window.innerWidth < 1024) closeSidebar();
  if (item.path && item.path !== '#') window.location.href = item.path;
}

function onToggleSidebar() {
  if (props.isOpen !== null && props.isOpen !== undefined) emit('toggle-sidebar');
  else sidebarOpen.value = !sidebarOpen.value;
}

function onNavScroll() {
  if (navScrollTimeout) clearTimeout(navScrollTimeout);
  navScrollTimeout = setTimeout(() => {
    try {
      if (navEl.value) localStorage.setItem(STORAGE_KEY_NAV_SCROLL, String(navEl.value.scrollTop));
    } catch (_) {}
  }, 100);
}

function restoreNavScroll() {
  try {
    const y = parseInt(localStorage.getItem(STORAGE_KEY_NAV_SCROLL), 10);
    if (navEl.value && !isNaN(y) && y > 0) navEl.value.scrollTop = y;
  } catch (_) {}
}

onMounted(() => {
  window.addEventListener('toggle-sidebar', onToggleSidebar);
  setTimeout(restoreNavScroll, 100);
});

onUnmounted(() => {
  window.removeEventListener('toggle-sidebar', onToggleSidebar);
  if (navScrollTimeout) clearTimeout(navScrollTimeout);
});
</script>
