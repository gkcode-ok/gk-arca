<template>
  <v-snackbar v-model="show" :color="color" timeout="4500" location="bottom right">
    <div class="d-flex align-center ga-2">
      <v-progress-circular v-if="loading" size="18" width="2" indeterminate color="white" />
      <span>{{ message }}</span>
    </div>
  </v-snackbar>
</template>

<script setup>
import { getCurrentInstance, ref } from 'vue';

const props = defineProps({
  endpoint: { type: String, default: '/Arca/probar-conexion' },
});
const emit = defineEmits(['result']);

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;

const show = ref(false);
const loading = ref(false);
const color = ref('info');
const message = ref('');

async function run() {
  loading.value = true;
  color.value = 'info';
  message.value = 'Verificando conexión con ARCA...';
  show.value = true;

  try {
    const result = await $gkaxios.post(props.endpoint);
    loading.value = false;

    if (result?.status === 0) {
      color.value = 'success';
      message.value = result.descripcion || 'Conexión con ARCA verificada correctamente.';
    } else {
      color.value = 'error';
      message.value = result?.descripcion || 'No fue posible establecer conexión con ARCA. Verificá la configuración del certificado y la autorización del Web Service.';
    }

    emit('result', result);
    return result;
  } catch (error) {
    loading.value = false;
    color.value = 'error';
    message.value = error?.response?.data?.descripcion || 'No fue posible establecer conexión con ARCA. Verificá la configuración del certificado y la autorización del Web Service.';
    emit('result', error?.response?.data || { status: -1 });
    return null;
  }
}

defineExpose({ run });
</script>
