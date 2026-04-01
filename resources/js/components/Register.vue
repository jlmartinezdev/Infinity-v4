<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">Registro</h1>
            <p class="text-sm text-gray-500 mt-1">Ingrese su clave de licencia para solicitar acceso</p>

            <form class="mt-6 space-y-4" @submit.prevent="handleRegister">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                    <input 
                        id="nombre"
                        name="nombre"
                        v-model="form.name" 
                        type="text" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        :class="{ 'border-red-500': errors.name }"
                        autocomplete="name"
                        required
                    />
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input 
                        id="email"
                        name="email"
                        v-model="form.email" 
                        type="email" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        :class="{ 'border-red-500': errors.email }"
                        autocomplete="email"
                        required
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <div class="relative">
                        <input 
                            id="password"
                            name="password"
                            v-model="form.password" 
                            :type="showPassword ? 'text' : 'password'"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                            :class="{ 'border-red-500': errors.password }"
                            autocomplete="new-password"
                            required
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <span v-if="showPassword">👁️</span>
                            <span v-else>👁️‍🗨️</span>
                        </button>
                    </div>
                    <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
                </div>
                <div>
                    <label for="licencia" class="block text-sm font-medium text-gray-700 mb-1">Clave de Licencia</label>
                    <input 
                        id="licencia"
                        name="licencia"
                        v-model="form.license" 
                        type="text" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                        :class="{ 'border-red-500': errors.license }"
                        placeholder="LIC-"
                        autocomplete="off"
                        required
                    />
                    <p v-if="errors.license" class="mt-1 text-sm text-red-600">{{ errors.license }}</p>
                </div>

                <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>
                <div v-if="success" class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-600">{{ success }}</p>
                </div>

                <button 
                    :disabled="loading" 
                    class="w-full px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                    <span v-if="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Enviando...
                    </span>
                    <span v-else>Registrar</span>
                </button>

                <div class="text-xs text-gray-500 text-center mt-2">
                    ¿Ya tienes acceso? <a href="/login" class="text-blue-600 hover:underline">Iniciar sesión</a>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';

const emit = defineEmits(['register-success']);

const form = reactive({
    name: '',
    email: '',
    password: '',
    license: ''
});

const errors = reactive({
    name: '',
    email: '',
    password: '',
    license: ''
});

const loading = ref(false);
const error = ref('');
const success = ref('');
const showPassword = ref(false);

const validateForm = () => {
    errors.name = '';
    errors.email = '';
    errors.password = '';
    errors.license = '';
    let isValid = true;

    if (!form.name || form.name.trim().length < 3) {
        errors.name = 'El nombre debe tener al menos 3 caracteres';
        isValid = false;
    }

    if (!form.email) {
        errors.email = 'El correo electrónico es requerido';
        isValid = false;
    } else if (!/\S+@\S+\.\S+/.test(form.email)) {
        errors.email = 'El correo electrónico no es válido';
        isValid = false;
    }

    if (!form.password) {
        errors.password = 'La contraseña es requerida';
        isValid = false;
    } else if (form.password.length < 6) {
        errors.password = 'La contraseña debe tener al menos 6 caracteres';
        isValid = false;
    }

    if (!form.license || form.license.trim().length < 5) {
        errors.license = 'La clave de licencia es requerida';
        isValid = false;
    }

    return isValid;
};

const handleRegister = async () => {
    error.value = '';
    success.value = '';

    if (!validateForm()) {
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post('/api/register', {
            name: form.name,
            email: form.email,
            password: form.password,
            license: form.license
        });

        if (response.data.success) {
            success.value = response.data.message || 'Registro enviado. Un administrador aprobará su acceso.';
            emit('register-success');
            // Redirigir al login después de 2 segundos
            setTimeout(() => {
                window.location.href = '/login';
            }, 2000);
        }
    } catch (err) {
        if (err.response && err.response.data) {
            const data = err.response.data;
            if (data.errors) {
                if (data.errors.name) {
                    errors.name = Array.isArray(data.errors.name) ? data.errors.name[0] : data.errors.name;
                }
                if (data.errors.email) {
                    errors.email = Array.isArray(data.errors.email) ? data.errors.email[0] : data.errors.email;
                }
                if (data.errors.password) {
                    errors.password = Array.isArray(data.errors.password) ? data.errors.password[0] : data.errors.password;
                }
                if (data.errors.license) {
                    errors.license = Array.isArray(data.errors.license) ? data.errors.license[0] : data.errors.license;
                }
            } else if (data.message) {
                error.value = data.message;
            } else {
                error.value = 'Error al registrar. Por favor, intenta de nuevo.';
            }
        } else {
            error.value = 'Error de conexión. Por favor, verifica tu conexión a internet.';
        }
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
/* Estilos adicionales si los necesitas */
</style>
