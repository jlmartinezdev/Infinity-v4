<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Iniciar Sesión</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Acceda al panel Infinity ISP</p>

      <form class="mt-6 space-y-4" @submit.prevent="handleLogin">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
          <input 
            id="email"
            name="email"
            v-model="form.email" 
            type="email" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            :class="{ 'border-red-500 dark:border-red-500': errors.email }"
            placeholder="Correo electronico"
            autocomplete="email"
            required
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.email }}</p>
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña</label>
          <div class="relative">
            <input 
              id="password"
              name="password"
              v-model="form.password" 
              :type="showPassword ? 'text' : 'password'"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
              :class="{ 'border-red-500 dark:border-red-500': errors.password }"
              placeholder="Contraseña"
              autocomplete="current-password"
              required
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
            >
              <span v-if="showPassword">👁️</span>
              <span v-else>👁️‍🗨️</span>
            </button>
          </div>
          <p v-if="errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.password }}</p>
        </div>

        <div class="flex items-center gap-2">
          <input
            id="remember"
            v-model="form.remember"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:focus:ring-offset-gray-800"
          />
          <label for="remember" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
            Mantener sesión iniciada
          </label>
        </div>

        <div v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</div>

        <button 
          type="submit"
          :disabled="loading" 
          class="w-full px-4 py-2 bg-gray-900 dark:bg-blue-600 text-white rounded-lg hover:bg-gray-800 dark:hover:bg-blue-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 disabled:cursor-not-allowed"
        >
          <span v-if="loading" class="flex items-center justify-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Ingresando...
          </span>
          <span v-else>Ingresar</span>
        </button>

        <div class="text-xs text-gray-500 dark:text-gray-400 text-center mt-2">
          ¿No tienes acceso? <a href="/register" class="text-blue-600 dark:text-blue-400 hover:underline">Solicitar registro</a>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login-success']);

const form = reactive({
    email: '',
    password: '',
    remember: false
});

const errors = reactive({
    email: '',
    password: ''
});

const loading = ref(false);
const error = ref('');
const success = ref('');
const showPassword = ref(false);

const validateForm = () => {
    errors.email = '';
    errors.password = '';
    let isValid = true;

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

    return isValid;
};

const handleLogin = async () => {
    console.log('handleLogin llamado');
    error.value = '';
    success.value = '';

    if (!validateForm()) {
        console.log('Validación fallida');
        return;
    }

    loading.value = true;
    console.log('Enviando petición de login...', { email: form.email });

    try {
        // Preparar datos para enviar
        const loginData = {
            email: form.email,
            password: form.password
        };
        
        // Solo incluir remember si es true
        if (form.remember) {
            loginData.remember = true;
        }

        console.log('Datos a enviar:', loginData);
        console.log('Token CSRF:', document.querySelector('meta[name="csrf-token"]')?.content);

        // Usar la ruta web en lugar de API para evitar problemas de CSRF
        const response = await axios.post('/login', loginData, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        console.log('Respuesta recibida:', response.data);

        if (response.data.success) {
            success.value = 'Inicio de sesión exitoso';
            // Emitir evento de éxito para que App.vue actualice el estado
            emit('login-success');
            // Redirigir según permisos (dashboard principal o panel de accesos)
            const nextUrl = response.data.redirect || '/';
            setTimeout(() => {
                window.location.href = nextUrl;
            }, 500);
        }
    } catch (err) {
        console.error('Error en login:', err);
        console.error('Response data:', err.response?.data);
        console.error('Response status:', err.response?.status);
        
        if (err.response && err.response.data) {
            const data = err.response.data;
            
            // Manejar errores de validación (422)
            if (data.errors) {
                if (data.errors.email) {
                    errors.email = Array.isArray(data.errors.email) ? data.errors.email[0] : data.errors.email;
                }
                if (data.errors.password) {
                    errors.password = Array.isArray(data.errors.password) ? data.errors.password[0] : data.errors.password;
                }
                if (data.errors.remember) {
                    // Ignorar errores de remember
                }
                error.value = 'Por favor, corrige los errores en el formulario.';
            } else if (data.message) {
                error.value = data.message;
            } else {
                error.value = 'Error al iniciar sesión. Por favor, intenta de nuevo.';
            }
        } else if (err.request) {
            error.value = 'Error de conexión. Por favor, verifica tu conexión a internet.';
        } else {
            error.value = 'Error al procesar la solicitud. Por favor, intenta de nuevo.';
        }
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
/* Estilos adicionales si los necesitas */
</style>
