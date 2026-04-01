<template>
  <div class="bg-white dark:bg-gray-800 overflow-hidden">
    <!-- Header -->
    

    <!-- Progress Bar -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Paso {{ currentStep }}/2</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ progressPercentage }}%</span>
      </div>
      <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: progressPercentage + '%' }"></div>
      </div>
    </div>

    <!-- Form Content -->
    <form @submit.prevent="submitForm" class="p-6">
      <!-- Paso 1: Datos Básicos -->
      <div v-show="currentStep === 1" class="space-y-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">DATOS BÁSICOS</h3>
        
        <!-- Cédula con búsqueda -->
        <div>
          <label for="cedula" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cédula *</label>
          <div class="flex gap-2">
            <input
              type="text"
              id="cedula"
              v-model="formData.cedula"
              @blur="buscarCliente"
              class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
              placeholder="1234567"
              required
            />
            <button
              type="button"
              @click="buscarCliente"
              :disabled="buscando"
              class="px-4 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors inline-flex items-center justify-center gap-2 min-w-[120px]"
            >
              <svg v-if="buscando" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ buscando ? 'Consultando...' : 'Buscar' }}</span>
            </button>
          </div>
          <p v-if="mensajeClienteSuccess && !buscando" class="mt-1 text-sm text-green-600">{{ mensajeClienteSuccess }}</p>
          <p v-if="errorCliente && !buscando" class="mt-1 text-sm text-red-600">{{ errorCliente }}</p>
        </div>

        <!-- Nombre -->
        <div>
          <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
          <input
            type="text"
            id="nombre"
            v-model="formData.nombre"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            required
          />
        </div>

        <!-- Apellido -->
        <div>
          <label for="apellido" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellido *</label>
          <input
            type="text"
            id="apellido"
            v-model="formData.apellido"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            required
          />
        </div>

        <!-- Celular -->
        <div>
          <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Celular *</label>
          <input
            type="tel"
            id="telefono"
            v-model="formData.telefono"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            placeholder="0981234567"
            required
          />
        </div>

        <!-- Botón siguiente -->
        <div class="flex justify-end">
          <button
            type="button"
            @click="nextStep"
            class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Paso 2: Ubicación y Plan -->
      <div v-show="currentStep === 2" class="space-y-6">
        <!-- Nombre del cliente en header -->
        <div v-if="formData.nombre && formData.apellido" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ formData.nombre }} {{ formData.apellido }}</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">{{ formData.cedula }}</p>
        </div>

        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">UBICACIÓN Y PLAN</h3>

        <!-- Ubicación -->
        <div>
          <label for="ubicacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación *</label>
          <input
            type="text"
            id="ubicacion"
            v-model="formData.ubicacion"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            placeholder="Esquina calle X"
            required
          />
        </div>

        <!-- Maps/GPS -->
        <div>
          <label for="maps_gps" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maps/GPS</label>
          <input
            type="text"
            id="maps_gps"
            v-model="formData.maps_gps"
            @input="onMapsGpsInput"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            placeholder="Pega el link de Google Maps o coordenadas (lat, lon)"
          />
          <p v-if="formData.lat != null && formData.lon != null" class="mt-1 text-xs text-green-600">
            Coordenadas detectadas: {{ formData.lat }}, {{ formData.lon }}
          </p>
        </div>

        
        <!-- Prioridad de instalación -->
        <div>
          <label for="prioridad_instalacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad de instalación</label>
          <select
            id="prioridad_instalacion"
            v-model="formData.prioridad_instalacion"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
          >
            <option :value="1">Alta</option>
            <option :value="2">Media</option>
            <option :value="3">Baja</option>
          </select>
        </div>

        <!-- Notas -->
        <div>
          <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
          <textarea
            id="observaciones"
            v-model="formData.observaciones"
            rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 dark:text-gray-100"
            placeholder="Opcional"
          ></textarea>
        </div>

        <!-- Botones -->
        <div class="flex items-center justify-between">
          <button
            type="button"
            @click="prevStep"
            class="w-12 h-12 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg flex items-center justify-center hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>

      <!-- Campos ocultos para el formulario -->
      <input type="hidden" name="cedula" :value="formData.cedula" />
      <input type="hidden" name="nombre" :value="formData.nombre" />
      <input type="hidden" name="apellido" :value="formData.apellido" />
      <input type="hidden" name="telefono" :value="formData.telefono" />
      <input type="hidden" name="estado_id" :value="estadoId" />
      <input type="hidden" name="fecha_pedido" :value="formData.fecha_pedido" />
      <input type="hidden" name="ubicacion" :value="formData.ubicacion" />
      <input type="hidden" name="maps_gps" :value="formData.maps_gps" />
      <input type="hidden" name="lat" :value="formData.lat" />
      <input type="hidden" name="lon" :value="formData.lon" />
      <input type="hidden" name="plan_id" :value="formData.plan_id" />
      <input type="hidden" name="prioridad_instalacion" :value="formData.prioridad_instalacion" />
      <input type="hidden" name="observaciones" :value="formData.observaciones" />
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { extractLatLonFromMapsUrl } from '../helpers/mapsUrl.js';

const props = defineProps({
  pedidoId: { type: String, default: 'Nuevo' },
  planes: { type: Array, required: true },
  estadoId: { type: Number, required: true },
  buscarClienteUrl: { type: String, required: true },
  consultarPadronUrl: { type: String, required: true },
  submitUrl: { type: String, required: true },
  cancelUrl: { type: String, required: true },
  csrfToken: { type: String, required: true },
  modalMode: { type: Boolean, default: false },
});

const currentStep = ref(1);
const buscando = ref(false);
const guardando = ref(false);
const errorCliente = ref('');
const mensajeClienteSuccess = ref('');

const formData = ref({
  cedula: '',
  cliente_id: null,
  nombre: '',
  apellido: '',
  telefono: '',
  ubicacion: '',
  maps_gps: '',
  lat: null,
  lon: null,
  plan_id: '',
  prioridad_instalacion: 2,
  observaciones: '',
  fecha_pedido: new Date().toISOString().split('T')[0],
});

const progressPercentage = computed(() => {
  return currentStep.value * 50;
});

const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PY', {
    style: 'currency',
    currency: 'PYG',
  }).format(price);
};

const onMapsGpsInput = () => {
  const { lat, lon } = extractLatLonFromMapsUrl(formData.value.maps_gps);
  formData.value.lat = lat;
  formData.value.lon = lon;
};

const buscarCliente = async () => {
  if (!formData.value.cedula) return;

  buscando.value = true;
  errorCliente.value = '';
  mensajeClienteSuccess.value = '';

  try {
    // Primero consultar el padrón
    let datosPadron = null;
    try {
      const padronResponse = await window.axios.post(props.consultarPadronUrl, {
        cedula: formData.value.cedula,
      });
      
      if (padronResponse.data.encontrado) {
        datosPadron = padronResponse.data;
        // Pre-llenar con datos del padrón
        formData.value.nombre = datosPadron.nombre || '';
        formData.value.apellido = datosPadron.apellido || '';
        if (datosPadron.direccion) {
          formData.value.ubicacion = datosPadron.direccion;
        }
      }
    } catch (padronError) {
      // Si no se encuentra en el padrón, continuar sin error
      console.log('No encontrado en padrón o error al consultar:', padronError);
    }

    // Luego buscar en la tabla de clientes
    try {
      const clienteResponse = await window.axios.post(props.buscarClienteUrl, {
        cedula: formData.value.cedula,
      });

      const cliente = clienteResponse.data;
      formData.value.cliente_id = cliente.cliente_id;
      // Si no hay datos del padrón, usar datos del cliente
      if (!datosPadron) {
        formData.value.nombre = cliente.nombre || formData.value.nombre;
        formData.value.apellido = cliente.apellido || formData.value.apellido;
        formData.value.telefono = cliente.telefono || formData.value.telefono;
      } else {
        // Si hay datos del padrón pero también cliente, priorizar padrón pero mantener teléfono del cliente
        formData.value.telefono = cliente.telefono || formData.value.telefono;
      }
      mensajeClienteSuccess.value = 'Cliente encontrado.';
      errorCliente.value = '';
    } catch (clienteError) {
      if (clienteError.response?.status === 404) {
        // Cliente no existe en la tabla, pero puede tener datos del padrón
        if (datosPadron) {
          mensajeClienteSuccess.value = 'Encontrado en padrón. Cliente nuevo, se creará automáticamente.';
          errorCliente.value = '';
        } else {
          errorCliente.value = 'No encontrado. Puedes continuar ingresando los datos manualmente.';
          mensajeClienteSuccess.value = '';
        }
        formData.value.cliente_id = null;
      } else {
        errorCliente.value = 'Error al buscar cliente. Puedes continuar ingresando los datos manualmente.';
        mensajeClienteSuccess.value = '';
      }
    }
  } catch (error) {
    errorCliente.value = 'Error al buscar. Puedes continuar ingresando los datos manualmente.';
    mensajeClienteSuccess.value = '';
  } finally {
    buscando.value = false;
  }
};

const nextStep = () => {
  if (formData.value.cedula && formData.value.nombre && formData.value.apellido && formData.value.telefono) {
    currentStep.value = 2;
  } else {
    alert('Por favor, completa todos los campos requeridos del paso 1.');
  }
};

const prevStep = () => {
  currentStep.value = 1;
};

const closeModal = () => {
  window.dispatchEvent(new CustomEvent('close-pedido-modal'));
};

const submitForm = async () => {
  if (!formData.value.cedula || !formData.value.nombre || !formData.value.apellido || !formData.value.telefono || !formData.value.ubicacion) {
    alert('Por favor, completa todos los campos requeridos.');
    return;
  }

  guardando.value = true;

  const dataToSubmit = {
    cedula: formData.value.cedula,
    nombre: formData.value.nombre,
    apellido: formData.value.apellido,
    telefono: formData.value.telefono,
    estado_id: props.estadoId,
    fecha_pedido: formData.value.fecha_pedido,
    ubicacion: formData.value.ubicacion,
    maps_gps: formData.value.maps_gps || '',
    lat: formData.value.lat ?? null,
    lon: formData.value.lon ?? null,
    plan_id: 1,
    prioridad_instalacion: formData.value.prioridad_instalacion ?? 2,
    observaciones: formData.value.observaciones || '',
  };

  try {
    const response = await window.axios.post(props.submitUrl, dataToSubmit);

    // Si la respuesta es exitosa
    if (response.status === 200 || response.status === 201 || response.data?.redirect) {
      if (props.modalMode) {
        window.dispatchEvent(new CustomEvent('pedido-created'));
      } else {
        window.location.href = props.cancelUrl;
      }
      return;
    }
    if (!props.modalMode) {
      window.location.href = props.cancelUrl;
    }
  } catch (error) {
    console.error('Error al guardar pedido:', error);
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join('\n');
      alert('Error de validación:\n' + errorMessages);
    } else if (error.response?.status === 302 || error.response?.status === 200) {
      if (props.modalMode) {
        window.dispatchEvent(new CustomEvent('pedido-created'));
      } else {
        window.location.href = props.cancelUrl;
      }
    } else {
      alert('Error al guardar el pedido. Por favor, intenta nuevamente.');
    }
  } finally {
    guardando.value = false;
  }
};
</script>
