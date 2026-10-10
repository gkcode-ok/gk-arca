<template>
  <ContenedorIndex comentario="Configuración de facturación electrónica con ARCA" :style="'width:100%;'" class="ContenedorIndex">
    <template #cabecera />
    <template #icon><v-icon size="22" color="primary">mdi-receipt-text-outline</v-icon></template>
    <template #titulo>
      <div class="arca-title">
        <span class="arca-title__heading">ARCA</span>
        <span class="arca-title__caption">Facturación electrónica de tu comercio</span>
      </div>
    </template>
    <template #main />
    <template #Tabla>
      <div class="arca-page">
        <ArcaStatusCard
          :data="status"
          :testing="testing"
          @configure="wizardOpen = true"
          @test-connection="probarConexion"
          @renew-certificate="certDialog = true"
          @disconnect="disconnectDialog = true"
        />
        <ArcaApiTester />
      </div>
    </template>
  </ContenedorIndex>

  <ArcaSetupWizard v-model="wizardOpen" @completed="onWizardCompleted" @test-connection="probarConexion" />
  <ArcaCertificateDialog v-model="certDialog" :loading="certLoading" :expires-at="status.certificate?.expires_at" @submit="renovarCertificado" />
  <ArcaDisconnectDialog v-model="disconnectDialog" :loading="disconnectLoading" @confirm="desconectar" />
  <ArcaConnectionTest ref="connectionTestRef" @result="onTestResult" />

  <Load :load="loading" />
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3200">{{ snackbar.text }}</v-snackbar>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue';
import Load from '@Components/Otros/Load/index.vue';
import ArcaStatusCard from './ArcaStatusCard.vue';
import ArcaSetupWizard from './ArcaSetupWizard.vue';
import ArcaCertificateDialog from './ArcaCertificateDialog.vue';
import ArcaDisconnectDialog from './ArcaDisconnectDialog.vue';
import ArcaConnectionTest from './ArcaConnectionTest.vue';
import ArcaApiTester from './ArcaApiTester.vue';

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;

const loading = ref(false);
const testing = ref(false);
const certLoading = ref(false);
const disconnectLoading = ref(false);

const wizardOpen = ref(false);
const certDialog = ref(false);
const disconnectDialog = ref(false);
const connectionTestRef = ref(null);

const status = ref({ status: 'not_configured', certificate: { status: 'none' } });
const snackbar = reactive({ show: false, text: '', color: 'success' });

onMounted(loadEstado);

async function loadEstado() {
  loading.value = true;
  try {
    const result = await $gkaxios.get('/home/Arca/estado');
    if (result?.status === 0) status.value = result.data;
  } catch (e) {
    notify('No se pudo cargar el estado de ARCA.', 'error');
  } finally {
    loading.value = false;
  }
}

function onWizardCompleted(data) {
  status.value = data;
  notify('ARCA configurado correctamente.');
}

async function probarConexion() {
  testing.value = true;
  const result = await connectionTestRef.value?.run();
  if (result?.data) status.value = result.data;
  testing.value = false;
}

function onTestResult(result) {
  if (result?.data) status.value = result.data;
}

async function renovarCertificado(payload) {
  certLoading.value = true;
  try {
    const certFile = toSingleFile(payload.cert_file);
    const keyFile = toSingleFile(payload.key_file);

    const form = new FormData();
    form.append('cert_file', certFile);
    form.append('key_file', keyFile);
    if (payload.passphrase) form.append('passphrase', payload.passphrase);
    if (payload.fecha_vencimiento) form.append('fecha_vencimiento', payload.fecha_vencimiento);

    const result = await $gkaxios.postImagen('/home/Arca/certificado', form);
    if (result?.status !== 0) throw new Error(result?.descripcion);
    const { private_key, private_key_filename, ...connectionData } = result.data || {};
    status.value = connectionData;
    if (private_key) downloadPrivateKey(private_key, private_key_filename);
    certDialog.value = false;
    notify('Certificado actualizado correctamente.');
  } catch (e) {
    notify(e?.response?.data?.descripcion || e?.message || 'No se pudo actualizar el certificado.', 'error');
  } finally {
    certLoading.value = false;
  }
}

function toSingleFile(value) {
  return Array.isArray(value) ? value[0] || null : value;
}

function downloadPrivateKey(base64Content, filename) {
  const bytes = Uint8Array.from(atob(base64Content), (c) => c.charCodeAt(0));
  const blob = new Blob([bytes], { type: 'application/x-pem-file' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename || 'clave-privada.key';
  link.click();
  URL.revokeObjectURL(url);
}

async function desconectar() {
  disconnectLoading.value = true;
  try {
    const result = await $gkaxios.put('/home/Arca/desconectar');
    if (result?.status !== 0) throw new Error(result?.descripcion);
    status.value = result.data;
    disconnectDialog.value = false;
    notify('ARCA fue desconectado correctamente.');
  } catch (e) {
    notify(e?.response?.data?.descripcion || e?.message || 'No se pudo desconectar ARCA.', 'error');
  } finally {
    disconnectLoading.value = false;
  }
}

function notify(text, color = 'success') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.show = true;
}
</script>

<style scoped>
.arca-title { display: flex; flex-direction: column; }
.arca-title__heading { font-weight: 900; font-size: 1rem; }
.arca-title__caption { font-size: .74rem; color: #607086; }
.arca-page { padding: 6px 2px; }
</style>
