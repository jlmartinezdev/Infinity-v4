<template>
    <div>
        <!-- Modal Crear Usuario -->
        <div v-show="showModalCrear" 
             @click.self="cerrarModalCrear"
             class="fixed inset-0 bg-gray-600 bg-opacity-50 dark:bg-black/60 overflow-y-auto h-full w-full z-50">
            <div @click.stop class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuevo Usuario</h3>
                    <button @click.stop="cerrarModalCrear" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form @submit.prevent.stop="crearUsuario" @click.stop>
                    <input type="hidden" name="_token" :value="csrfToken">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                            <input v-model="formCrear.name" 
                                   type="text" 
                                   name="name" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                            <input v-model="formCrear.email" 
                                   type="email" 
                                   name="email" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña *</label>
                            <input v-model="formCrear.password" 
                                   type="password" 
                                   name="password" 
                                   required 
                                   minlength="6"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol *</label>
                            <select v-model="formCrear.rol_id" 
                                    name="rol_id" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">Seleccione un rol</option>
                                <option v-for="rol in roles" :key="rol.rol_id" :value="rol.rol_id">
                                    {{ rol.descripcion }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
                            <select v-model="formCrear.estado" 
                                    name="estado" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="activo">Activo</option>
                                <option value="pendiente_aprobacion">Pendiente Aprobación</option>
                                <option value="suspendido">Suspendido</option>
                            </select>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit" 
                                    @click.stop
                                    :disabled="loadingCrear"
                                    class="flex-1 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500">
                                <span v-if="loadingCrear">Creando...</span>
                                <span v-else>Crear</span>
                            </button>
                            <button type="button" 
                                    @click.stop="cerrarModalCrear"
                                    class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Editar Usuario -->
        <div v-show="showModalEditar" 
             @click.self="cerrarModalEditar"
             class="fixed inset-0 bg-gray-600 bg-opacity-50 dark:bg-black/60 overflow-y-auto h-full w-full z-50">
            <div @click.stop class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Editar Usuario</h3>
                    <button @click.stop="cerrarModalEditar" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form @submit.prevent.stop="actualizarUsuario" @click.stop>
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                            <input v-model="formEditar.name" 
                                   type="text" 
                                   name="name" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                            <input v-model="formEditar.email" 
                                   type="email" 
                                   name="email" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nueva Contraseña (dejar vacío para no cambiar)</label>
                            <input v-model="formEditar.password" 
                                   type="password" 
                                   name="password" 
                                   minlength="6"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol *</label>
                            <select v-model="formEditar.rol_id" 
                                    name="rol_id" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">Seleccione un rol</option>
                                <option v-for="rol in roles" :key="rol.rol_id" :value="rol.rol_id">
                                    {{ rol.descripcion }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
                            <select v-model="formEditar.estado" 
                                    name="estado" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="activo">Activo</option>
                                <option value="pendiente_aprobacion">Pendiente Aprobación</option>
                                <option value="suspendido">Suspendido</option>
                            </select>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit" 
                                    @click.stop
                                    :disabled="loadingEditar"
                                    class="flex-1 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500">
                                <span v-if="loadingEditar">Actualizando...</span>
                                <span v-else>Actualizar</span>
                            </button>
                            <button type="button" 
                                    @click.stop="cerrarModalEditar"
                                    class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Aprobar Usuario -->
        <div v-show="showModalAprobar" 
             @click.self="cerrarModalAprobar"
             class="fixed inset-0 bg-gray-600 bg-opacity-50 dark:bg-black/60 overflow-y-auto h-full w-full z-50">
            <div @click.stop class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Aprobar Usuario</h3>
                    <button @click.stop="cerrarModalAprobar" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">¿Estás seguro de que deseas aprobar a este usuario?</p>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ modalAprobar.nombre }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ modalAprobar.email }}</div>
                    </div>
                </div>
                <form @submit.prevent.stop="aprobarUsuario" @click.stop>
                    <input type="hidden" name="_token" :value="csrfToken">
                    <div class="flex gap-3">
                        <button type="submit" 
                                @click.stop
                                :disabled="loadingAprobar"
                                class="flex-1 rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-600 dark:hover:bg-green-500">
                            <span v-if="loadingAprobar">Aprobando...</span>
                            <span v-else>Aprobar Usuario</span>
                        </button>
                        <button type="button" 
                                @click.stop="cerrarModalAprobar"
                                class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    csrfToken: {
        type: String,
        required: true
    },
    roles: {
        type: Array,
        default: () => []
    },
    storeUrl: {
        type: String,
        required: true
    },
    updateUrl: {
        type: String,
        required: true
    },
    aprobarUrl: {
        type: String,
        required: true
    },
    editDataUrl: {
        type: String,
        required: true
    }
});

// Estados de los modales
const showModalCrear = ref(false);
const showModalEditar = ref(false);
const showModalAprobar = ref(false);

// Formularios
const formCrear = ref({
    name: '',
    email: '',
    password: '',
    rol_id: '',
    estado: 'activo'
});

const formEditar = ref({
    usuario_id: null,
    name: '',
    email: '',
    password: '',
    rol_id: '',
    estado: 'activo'
});

const modalAprobar = ref({
    usuario_id: null,
    nombre: '',
    email: ''
});

// Funciones para abrir/cerrar modales
const abrirModalCrear = (event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    console.log('abrirModalCrear llamado');
    formCrear.value = {
        name: '',
        email: '',
        password: '',
        rol_id: '',
        estado: 'activo'
    };
    showModalCrear.value = true;
    console.log('showModalCrear establecido a:', showModalCrear.value);
    return false;
};

const cerrarModalCrear = () => {
    console.log('cerrarModalCrear llamado');
    showModalCrear.value = false;
};

const abrirModalEditar = async (usuarioId, event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    try {
        const response = await axios.get(props.editDataUrl.replace(':usuario', usuarioId));
        const data = response.data;
        
        formEditar.value = {
            usuario_id: usuarioId,
            name: data.name,
            email: data.email,
            password: '',
            rol_id: data.rol_id,
            estado: data.estado
        };
        showModalEditar.value = true;
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar los datos del usuario');
    }
    return false;
};

const cerrarModalEditar = () => {
    showModalEditar.value = false;
};

const abrirModalAprobar = (usuarioId, nombre, email, event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    modalAprobar.value = {
        usuario_id: usuarioId,
        nombre: nombre,
        email: email
    };
    showModalAprobar.value = true;
    return false;
};

const cerrarModalAprobar = () => {
    showModalAprobar.value = false;
};

// Estados de carga
const loadingCrear = ref(false);
const loadingEditar = ref(false);
const loadingAprobar = ref(false);

// Funciones para enviar formularios con axios
const crearUsuario = async () => {
    loadingCrear.value = true;
    try {
        const response = await axios.post(props.storeUrl, {
            name: formCrear.value.name,
            email: formCrear.value.email,
            password: formCrear.value.password,
            rol_id: formCrear.value.rol_id,
            estado: formCrear.value.estado,
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        // Redirigir después de crear
        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingCrear.value = false;
        
        if (error.response && error.response.data) {
            if (error.response.data.message) {
                alert('Error: ' + error.response.data.message);
            } else if (error.response.data.errors) {
                const errors = Object.values(error.response.data.errors).flat().join('\n');
                alert('Errores de validación:\n' + errors);
            } else {
                alert('Error al crear el usuario');
            }
        } else {
            alert('Error al crear el usuario. Por favor, intenta nuevamente.');
        }
    }
};

const actualizarUsuario = async () => {
    loadingEditar.value = true;
    try {
        const updateUrl = props.updateUrl.replace(':usuario', formEditar.value.usuario_id);
        const data = {
            name: formEditar.value.name,
            email: formEditar.value.email,
            rol_id: formEditar.value.rol_id,
            estado: formEditar.value.estado,
        };
        
        if (formEditar.value.password) {
            data.password = formEditar.value.password;
        }
        
        const response = await axios.put(updateUrl, data, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        // Redirigir después de actualizar
        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingEditar.value = false;
        
        if (error.response && error.response.data) {
            if (error.response.data.message) {
                alert('Error: ' + error.response.data.message);
            } else if (error.response.data.errors) {
                const errors = Object.values(error.response.data.errors).flat().join('\n');
                alert('Errores de validación:\n' + errors);
            } else {
                alert('Error al actualizar el usuario');
            }
        } else {
            alert('Error al actualizar el usuario. Por favor, intenta nuevamente.');
        }
    }
};

const aprobarUsuario = async () => {
    loadingAprobar.value = true;
    try {
        const aprobarUrl = props.aprobarUrl.replace(':usuario', modalAprobar.value.usuario_id);
        
        const response = await axios.post(aprobarUrl, {}, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        // Redirigir después de aprobar
        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingAprobar.value = false;
        
        if (error.response && error.response.data) {
            if (error.response.data.message) {
                alert('Error: ' + error.response.data.message);
            } else {
                alert('Error al aprobar el usuario');
            }
        } else {
            alert('Error al aprobar el usuario. Por favor, intenta nuevamente.');
        }
    }
};

// Aplicar permisos desde un array de códigos (ej. los del rol desde backend)
const aplicarPermisosRol = (codigos) => {
    const list = Array.isArray(codigos) ? codigos : [];
    document.querySelectorAll('input[name="permisos[]"]').forEach(checkbox => {
        checkbox.checked = list.includes(checkbox.value);
    });
};

// Exponer funciones globalmente para que puedan ser llamadas desde Blade
window.abrirModalCrear = abrirModalCrear;
window.cerrarModalCrear = cerrarModalCrear;
window.abrirModalEditar = abrirModalEditar;
window.cerrarModalEditar = cerrarModalEditar;
window.abrirModalAprobar = abrirModalAprobar;
window.cerrarModalAprobar = cerrarModalAprobar;
window.aplicarPermisosRol = aplicarPermisosRol;

onMounted(() => {
    window.abrirModalCrear = abrirModalCrear;
    window.cerrarModalCrear = cerrarModalCrear;
    window.abrirModalEditar = abrirModalEditar;
    window.cerrarModalEditar = cerrarModalEditar;
    window.abrirModalAprobar = abrirModalAprobar;
    window.cerrarModalAprobar = cerrarModalAprobar;
    window.aplicarPermisosRol = aplicarPermisosRol;
});
</script>
