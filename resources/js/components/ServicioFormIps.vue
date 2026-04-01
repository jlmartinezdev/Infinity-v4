<template>
  <div class="mt-2">
    <label for="ip_disponible_select" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
      Usar IP disponible del pool
    </label>
    <select
      id="ip_disponible_select"
      v-model="selectedIp"
      class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:!bg-gray-700 dark:text-gray-100 text-sm appearance-none dark:[color-scheme:dark]"
      :disabled="loading"
      @change="onSelectIp"
    >
      <option value="">{{ selectPlaceholder }}</option>
      <option v-for="ip in ipsOrdenadas" :key="ip" :value="ip">{{ ip }}</option>
    </select>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      {{ poolId ? 'Seleccione una IP o escriba manualmente arriba.' : 'Seleccione un pool arriba para cargar las IPs disponibles.' }}
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  ipsDisponiblesUrl: { type: String, default: '' },
});

const poolId = ref('');
const ips = ref([]);
const loading = ref(false);
const selectedIp = ref('');
const selectPlaceholder = ref('— Seleccione un pool primero —');

const ipsOrdenadas = ref([]);

function ordenarIpsDeMenorAMayor(list) {
  return list.slice().sort((a, b) => {
    const octA = a.split('.').map((n) => parseInt(n, 10) || 0);
    const octB = b.split('.').map((n) => parseInt(n, 10) || 0);
    for (let i = 0; i < 4; i++) {
      if (octA[i] !== octB[i]) return octA[i] - octB[i];
    }
    return 0;
  });
}

async function cargarIps() {
  if (!props.ipsDisponiblesUrl || !poolId.value) {
    ipsOrdenadas.value = [];
    selectPlaceholder.value = '— Seleccione un pool primero —';
    return;
  }
  loading.value = true;
  selectPlaceholder.value = '— Cargando... —';
  ipsOrdenadas.value = [];
  selectedIp.value = '';
  try {
    const r = await fetch(props.ipsDisponiblesUrl + '?pool_id=' + encodeURIComponent(poolId.value));
    const data = await r.json();
    ipsOrdenadas.value = ordenarIpsDeMenorAMayor(data.ips || []);
    selectPlaceholder.value = '— Ninguna / Escribir manual —';
  } catch {
    selectPlaceholder.value = '— Error al cargar —';
  } finally {
    loading.value = false;
  }
}

function onSelectIp() {
  const ipInput = document.getElementById('ip');
  if (ipInput) ipInput.value = selectedIp.value || '';
}

function setupPoolListener() {
  const poolSelect = document.getElementById('pool_id');
  if (!poolSelect) return;
  const update = () => {
    poolId.value = poolSelect.value || '';
    cargarIps();
  };
  poolSelect.addEventListener('change', update);
  if (poolSelect.value) {
    poolId.value = poolSelect.value;
    cargarIps();
  }
}

onMounted(() => {
  setupPoolListener();
});
</script>
