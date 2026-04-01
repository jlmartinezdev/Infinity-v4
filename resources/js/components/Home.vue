<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <Sidebar 
            :user="user" 
            :is-open="sidebarOpen"
            @toggle-sidebar="toggleSidebar"
            @menu-click="handleMenuClick"
            @update:desktop-expanded="desktopSidebarExpanded = $event"
        />

        <!-- Main Content Area (margen según sidebar fijo: 64 expandido, 20 colapsado) -->
        <div
            :class="[
                'flex-1 flex flex-col min-h-screen transition-all duration-300',
                desktopSidebarExpanded ? 'lg:ml-64' : 'lg:ml-20'
            ]"
        >
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center">
                            <button 
                                @click="toggleSidebar"
                                class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <h1 class="ml-2 lg:ml-0 text-xl font-bold text-gray-900">Infinity ISP</h1>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="hidden sm:block text-sm text-gray-700">
                                <span class="font-medium">{{ user?.name }}</span>
                                <span class="text-gray-500 ml-2">{{ user?.email }}</span>
                            </div>
                            <button 
                                @click="handleLogout"
                                class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors"
                            >
                                Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Bienvenido, {{ user?.name }}</h2>
                <p class="text-gray-600">Panel de control de Infinity ISP</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Clientes</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.clientes }}</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Servicios Activos</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.servicios }}</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Facturación Mensual</p>
                            <p class="text-2xl font-bold text-gray-900 mt-2">{{ formatNumber(stats.facturacion) }} PYG</p>
                        </div>
                        <div class="bg-yellow-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Tickets Abiertos</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.tickets }}</p>
                        </div>
                        <div class="bg-red-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <a :href="urlServiciosHoy" class="bg-white rounded-lg shadow p-6 border border-gray-200 hover:border-cyan-400 transition-colors block">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Instalados hoy</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.clientes_instalados_hoy }}</p>
                        </div>
                        <div class="bg-cyan-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </a>

                <a :href="urlServiciosMes" class="bg-white rounded-lg shadow p-6 border border-gray-200 hover:border-teal-400 transition-colors block">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Instalados este mes</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.clientes_instalados_mes }}</p>
                        </div>
                        <div class="bg-teal-100 rounded-full p-3">
                            <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <button class="bg-blue-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors text-left">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Nuevo Cliente
                            </div>
                        </button>
                        <button class="bg-green-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-green-700 transition-colors text-left">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Nuevo Servicio
                            </div>
                        </button>
                        <button class="bg-yellow-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-yellow-700 transition-colors text-left">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Nuevo Ticket
                            </div>
                        </button>
                        <button class="bg-purple-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors text-left">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Nueva Factura
                            </div>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Actividad Reciente</h3>
                    <div class="space-y-3">
                        <div v-for="activity in recentActivity" :key="activity.id" class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div :class="['w-2 h-2 rounded-full', activity.color]"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ activity.title }}</p>
                                <p class="text-xs text-gray-500">{{ activity.time }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Sidebar from './Sidebar.vue';

const user = ref(null);
const sidebarOpen = ref(false);
const desktopSidebarExpanded = ref(true);
const stats = ref({
    clientes: 0,
    servicios: 0,
    facturacion: 0,
    tickets: 0,
    clientes_instalados_hoy: 0,
    clientes_instalados_mes: 0
});

const recentActivity = ref([
    { id: 1, title: 'Nuevo cliente registrado', time: 'Hace 2 horas', color: 'bg-blue-500' },
    { id: 2, title: 'Servicio activado', time: 'Hace 5 horas', color: 'bg-green-500' },
    { id: 3, title: 'Ticket resuelto', time: 'Hace 1 día', color: 'bg-yellow-500' },
    { id: 4, title: 'Factura generada', time: 'Hace 2 días', color: 'bg-purple-500' }
]);

const loadUser = async () => {
    try {
        const response = await axios.get('/api/user', {
            withCredentials: true,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (response.data.user) {
            user.value = response.data.user;
        }
    } catch (error) {
        console.error('Error al cargar usuario:', error);
        console.error('Response:', error.response?.data);
        console.error('Status:', error.response?.status);
        
        // Si es error 401 (Unauthenticated), redirigir al login
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
    }
};

const today = new Date().toISOString().slice(0, 10);
const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
const lastDay = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10);
const urlServiciosHoy = `/servicios?fecha_desde=${today}&fecha_hasta=${today}`;
const urlServiciosMes = `/servicios?fecha_desde=${firstDay}&fecha_hasta=${lastDay}`;

const formatNumber = (n) => {
    const num = Number(n) || 0;
    return num.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const loadStats = async () => {
    try {
        const response = await axios.get('/api/dashboard/stats', {
            withCredentials: true,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (response.data) {
            stats.value = {
                clientes: response.data.clientes ?? 0,
                servicios: response.data.servicios ?? 0,
                facturacion: response.data.facturacion ?? 0,
                tickets: response.data.tickets ?? 0,
                clientes_instalados_hoy: response.data.clientes_instalados_hoy ?? 0,
                clientes_instalados_mes: response.data.clientes_instalados_mes ?? 0
            };
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
};

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const handleMenuClick = (item) => {
    console.log('Menu clicked:', item);
    // Aquí puedes manejar la navegación cuando se implemente vue-router
    // Por ahora, solo cerramos el sidebar en móvil
    if (window.innerWidth < 1024) {
        sidebarOpen.value = false;
    }
};

const handleLogout = async () => {
    try {
        await axios.post('/api/logout');
        window.location.href = '/login';
    } catch (error) {
        console.error('Error al cerrar sesión:', error);
        // Redirigir de todas formas
        window.location.href = '/login';
    }
};

onMounted(() => {
    loadUser();
    loadStats();
});
</script>

<style scoped>
/* Estilos adicionales si los necesitas */
</style>
