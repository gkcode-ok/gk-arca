<template>
  <v-dialog v-model="model" max-width="720" persistent scrollable>
    <v-card>
      <v-card-title class="wizard-title">
        <span>Configuración de facturación electrónica</span>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>

      <v-card-subtitle v-if="step <= 4" class="wizard-progress">
        Paso {{ step }} de 4
        <v-progress-linear :model-value="(step / 4) * 100" color="primary" height="6" rounded class="mt-2" />
      </v-card-subtitle>

      <v-card-text class="wizard-body">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>

        <!-- Paso 1: Datos del comercio -->
        <section v-if="step === 1" class="wizard-step">
          <h3>Datos del comercio</h3>
          <p class="wizard-hint">Estos datos ya existen en el sistema y no se pueden modificar desde este proceso.</p>

          <div class="wizard-fields">
            <v-text-field v-model="form.cuit" label="CUIT" variant="outlined" density="comfortable" :readonly="!form.editable" maxlength="11" />
            <v-text-field v-model="form.razon_social" label="Razón social" variant="outlined" density="comfortable" :readonly="!form.editable" />
            <v-select
              v-model="form.condicion_iva"
              label="Condición frente al IVA"
              :items="condicionIvaOptions"
              variant="outlined"
              density="comfortable"
              :readonly="!form.editable"
            />
          </div>
        </section>

        <!-- Paso 2: Conectar ARCA -->
        <section v-else-if="step === 2" class="wizard-step">
          <h3>Conectar ARCA</h3>
          <p>
            Para facturar electrónicamente necesitamos autorizar la conexión entre tu comercio y este sistema.
          </p>
          <div class="wizard-illustration">
            <v-icon size="48" color="primary">mdi-store-outline</v-icon>
            <v-icon size="26" color="grey">mdi-arrow-right</v-icon>
            <v-icon size="48" color="primary">mdi-cloud-sync-outline</v-icon>
            <v-icon size="26" color="grey">mdi-arrow-right</v-icon>
            <v-icon size="48" color="success">mdi-receipt-text-check-outline</v-icon>
          </div>
          <p>
            Este proceso se realiza mediante ARCA y permite que el sistema emita comprobantes utilizando los datos
            fiscales de tu comercio.
          </p>
          <v-select
            v-model="form.entorno"
            label="Entorno de ARCA"
            :items="entornoOptions"
            variant="outlined"
            density="comfortable"
            hint="Usá Homologación para pruebas y Producción para comprobantes reales."
            persistent-hint
          />
          <a href="#" class="wizard-help" @click.prevent="helpDialog = true">¿Necesitás ayuda?</a>
        </section>

        <!-- Paso 3: Certificado digital -->
        <section v-else-if="step === 3" class="wizard-step">
          <h3>Certificado digital</h3>
          <p>ARCA utiliza un certificado digital para identificar de forma segura a tu comercio.</p>

          <div v-if="certificateDetected" class="wizard-cert-detected">
            <ArcaStatusChip state="green" text="Certificado detectado" />
            <div class="wizard-cert-facts">
              <div><span>Nombre</span><strong>{{ status.certificate?.name || '-' }}</strong></div>
              <div><span>Vencimiento</span><strong>{{ formatDate(status.certificate?.expires_at) }}</strong></div>
              <div><span>Estado</span><strong>{{ certificateLabel }}</strong></div>
            </div>
          </div>

          <div v-else class="wizard-cert-missing">
            <ArcaStatusChip state="grey" text="Certificado no configurado" />
            <p class="wizard-hint">Necesitás generar un certificado digital desde ARCA y luego cargarlo aquí.</p>
            <div class="wizard-cert-actions">
              <v-btn variant="tonal" color="primary" prepend-icon="mdi-open-in-new" @click="openCertHelp">Ver cómo obtener mi certificado</v-btn>
              <v-btn variant="flat" color="primary" prepend-icon="mdi-file-certificate-outline" @click="showCertForm = true">Ya tengo mi certificado</v-btn>
              <v-btn variant="text" color="primary" prepend-icon="mdi-arrow-right" @click="step = 4">Continuar sin certificado (Solo Consultas)</v-btn>
            </div>

            <v-form v-if="showCertForm" class="wizard-cert-form">
              <v-file-input v-model="certForm.cert_file" label="Certificado (.crt)" accept=".crt,.pem" variant="outlined" density="comfortable" />
              <v-file-input v-model="certForm.key_file" label="Clave privada (.key o .pem)" accept=".key,.pem,.txt" variant="outlined" density="comfortable" />
              <p class="wizard-hint">Si tu clave privada está en otro formato (por ejemplo el .pem que entrega la CA), no hace falta convertirla manualmente con openssl: el sistema la procesa automáticamente.</p>
              <v-text-field v-model="certForm.passphrase" label="Passphrase (opcional)" type="password" variant="outlined" density="comfortable" />
              <v-text-field v-model="certForm.fecha_vencimiento" label="Fecha de vencimiento" type="date" variant="outlined" density="comfortable" />
            </v-form>
          </div>
        </section>

        <!-- Paso 4: Punto de venta -->
        <section v-else-if="step === 4" class="wizard-step">
          <h3>Configurar punto de venta</h3>

          <v-alert
            v-if="!certificateDetected"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            <strong>Modo Consultas y Padrón Fiscal:</strong> Como no tenés certificado digital cargado, podés usar el punto de venta <strong>0001</strong> para habilitar búsquedas de CUIT, autocompletado y validación de clientes en Contabilidad.
          </v-alert>

          <div class="wizard-fields">
            <v-text-field
              v-model="form.point_of_sale"
              label="Punto de venta"
              variant="outlined"
              density="comfortable"
              placeholder="0001"
              hint="Podés ingresar 0001 para habilitar el canal de consultas"
              persistent-hint
            />
            <v-text-field
              label="Tipo de comprobante"
              :model-value="certificateDetected ? 'Factura electrónica' : 'Consultas y Padrón Fiscal'"
              variant="outlined"
              density="comfortable"
              readonly
            />
            <v-text-field
              label="Web Service"
              :model-value="certificateDetected ? 'WSFEv1' : 'WS_SR_PADRON'"
              variant="outlined"
              density="comfortable"
              readonly
            />
          </div>

          <div v-if="!form.point_of_sale" class="mt-2">
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-flash"
              @click="form.point_of_sale = '0001'"
            >
              Usar punto 0001 (Modo Consultas)
            </v-btn>
          </div>

          <p class="wizard-hint mt-2">El punto de venta identifica desde qué sistema se canalizarán las emisiones o consultas fiscales.</p>
        </section>

        <!-- Pantalla de éxito -->
        <section v-else class="wizard-success">
          <v-icon size="72" color="success">mdi-check-circle</v-icon>
          <h3>¡ARCA está conectado!</h3>
          <p v-if="certificateDetected">Tu comercio ya está configurado para emitir facturas electrónicas desde el sistema.</p>
          <p v-else>Tu sistema ya está conectado en <strong>Modo Consultas y Padrón Fiscal</strong>. Podés validar CUITs, autocompletar clientes y consultar ARCA de inmediato.</p>

          <div class="wizard-summary">
            <div><span>CUIT</span><strong>{{ status.cuit }}</strong></div>
            <div><span>Estado</span><ArcaStatusChip state="green" text="Conectado" /></div>
            <div><span>Certificado</span><ArcaStatusChip :state="certificateDetected ? 'green' : 'grey'" :text="certificateLabel" /></div>
            <div><span>Web Service</span><strong>{{ status.web_service }}</strong></div>
            <div><span>Punto de venta</span><strong>{{ status.point_of_sale }}</strong></div>
            <div><span>Última conexión</span><strong>{{ formatDate(status.last_connection) }}</strong></div>
          </div>
        </section>
      </v-card-text>

      <v-card-actions class="wizard-actions">
        <v-btn v-if="step > 1 && step <= 4" variant="text" @click="step -= 1">Atrás</v-btn>
        <v-spacer />

        <template v-if="step === 1">
          <v-btn color="primary" variant="flat" @click="step = 2">Continuar</v-btn>
        </template>
        <template v-else-if="step === 2">
          <v-btn color="primary" variant="flat" :loading="loading" @click="conectar">Continuar con ARCA</v-btn>
        </template>
        <template v-else-if="step === 3">
          <v-btn color="primary" variant="flat" :loading="loading" @click="continuarCertificado">Continuar</v-btn>
        </template>
        <template v-else-if="step === 4">
          <v-btn color="primary" variant="flat" :loading="loading" @click="finalizar">
            {{ form.point_of_sale ? 'Finalizar configuración' : 'Finalizar (Modo Consultas 0001)' }}
          </v-btn>
        </template>
        <template v-else>
          <v-btn variant="tonal" color="primary" prepend-icon="mdi-connection" @click="$emit('test-connection')">Probar conexión</v-btn>
          <v-btn color="primary" variant="flat" @click="close">Volver a configuración</v-btn>
        </template>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <ArcaConnectionDialog v-model="helpDialog" />
</template>

<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import ArcaStatusChip from './ArcaStatusChip.vue';
import ArcaConnectionDialog from './ArcaConnectionDialog.vue';

const model = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['completed', 'test-connection']);

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;

const step = ref(1);
const loading = ref(false);
const error = ref('');
const helpDialog = ref(false);
const showCertForm = ref(false);

const form = ref({ cuit: '', razon_social: '', condicion_iva: '', entorno: 'homo', editable: true, point_of_sale: '' });
const certForm = ref({ cert_file: null, key_file: null, passphrase: '', fecha_vencimiento: '' });
const status = ref({});

const condicionIvaOptions = [
  'Responsable Inscripto',
  'Monotributista',
  'Exento',
  'Consumidor Final',
];

const entornoOptions = [
  { title: 'Homologación (pruebas)', value: 'homo' },
  { title: 'Producción', value: 'prod' },
];

const certificateDetected = computed(() => (status.value?.certificate?.status || 'none') !== 'none');
const certificateLabel = computed(() => ({
  valid: 'Vigente',
  expiring: 'Por vencer',
  expired: 'Vencido',
  none: 'No configurado',
}[status.value?.certificate?.status || 'none']));

watch(model, async (value) => {
  if (value) {
    step.value = 1;
    error.value = '';
    showCertForm.value = false;
    await cargarDatosComercio();
  }
});

async function cargarDatosComercio() {
  try {
    const result = await $gkaxios.get('/home/Arca/datos-comercio');
    if (result?.status === 0) {
      Object.assign(form.value, result.data);
    }
  } catch (e) {
    error.value = 'No se pudieron cargar los datos del comercio.';
  }
}

async function conectar() {
  loading.value = true;
  error.value = '';
  try {
    const result = await $gkaxios.post('/home/Arca/conectar', {
      cuit: form.value.cuit,
      razon_social: form.value.razon_social,
      condicion_iva: form.value.condicion_iva,
      entorno: form.value.entorno,
    });
    if (result?.status !== 0) throw new Error(result?.descripcion);
    status.value = result.data;
    step.value = 3;
  } catch (e) {
    error.value = e?.response?.data?.descripcion || e?.message || 'No se pudo conectar con ARCA.';
  } finally {
    loading.value = false;
  }
}

async function continuarCertificado() {
  if (!showCertForm.value || certificateDetected.value) {
    step.value = 4;
    return;
  }

  const certFile = toSingleFile(certForm.value.cert_file);
  const keyFile = toSingleFile(certForm.value.key_file);

  if (!certFile || !keyFile) {
    error.value = 'Carga el certificado y la clave privada para continuar.';
    return;
  }

  loading.value = true;
  error.value = '';
  try {
    const payload = new FormData();
    payload.append('cert_file', certFile);
    payload.append('key_file', keyFile);
    if (certForm.value.passphrase) payload.append('passphrase', certForm.value.passphrase);
    if (certForm.value.fecha_vencimiento) payload.append('fecha_vencimiento', certForm.value.fecha_vencimiento);

    const result = await $gkaxios.postImagen('/home/Arca/certificado', payload);
    if (result?.status !== 0) throw new Error(result?.descripcion);
    const { private_key, private_key_filename, ...connectionData } = result.data || {};
    status.value = connectionData;
    if (private_key) downloadPrivateKey(private_key, private_key_filename);
    step.value = 4;
  } catch (e) {
    error.value = e?.response?.data?.descripcion || e?.message || 'No se pudo cargar el certificado.';
  } finally {
    loading.value = false;
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

async function finalizar() {
  loading.value = true;
  error.value = '';
  try {
    const ptoVta = form.value.point_of_sale || '0001';
    form.value.point_of_sale = ptoVta;
    const ws = certificateDetected.value ? 'WSFEv1' : 'WS_SR_PADRON';
    const result = await $gkaxios.post('/home/Arca/punto-venta', {
      point_of_sale: ptoVta,
      web_service: ws,
    });
    if (result?.status !== 0) throw new Error(result?.descripcion);
    status.value = result.data;
    step.value = 5;
    emit('completed', status.value);
  } catch (e) {
    error.value = e?.response?.data?.descripcion || e?.message || 'No se pudo finalizar la configuración.';
  } finally {
    loading.value = false;
  }
}

function openCertHelp() {
  window.open('https://www.afip.gob.ar/ws/documentacion/certificados/Cadena_de_certificacion_prod_2024_2035.zip', '_blank', 'noopener');
}

function close() {
  model.value = false;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString('es-AR', { hour12: false });
}
</script>

<style scoped>
.wizard-title { display: flex; align-items: center; justify-content: space-between; }
.wizard-progress { padding-bottom: 12px; }
.wizard-body { min-height: 320px; }
.wizard-step h3 { margin: 0 0 8px; color: #172033; }
.wizard-hint { color: #607086; font-size: .84rem; }
.wizard-fields { display: grid; gap: 12px; margin-top: 12px; }
.wizard-illustration { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 20px 0; }
.wizard-help { display: inline-block; margin-top: 10px; color: #2563eb; font-size: .85rem; text-decoration: underline; }
.wizard-cert-detected { display: grid; gap: 12px; }
.wizard-cert-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.wizard-cert-facts span { display: block; color: #8793a6; font-size: .68rem; text-transform: uppercase; }
.wizard-cert-facts strong { color: #172033; }
.wizard-cert-missing { display: grid; gap: 10px; }
.wizard-cert-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.wizard-cert-form { display: grid; gap: 12px; margin-top: 10px; }
.wizard-success { display: grid; justify-items: center; text-align: center; gap: 8px; padding: 20px 0; }
.wizard-success h3 { margin: 4px 0 0; color: #172033; }
.wizard-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; width: 100%; margin-top: 12px; text-align: left; }
.wizard-summary span { display: block; color: #8793a6; font-size: .68rem; text-transform: uppercase; }
.wizard-summary strong { color: #172033; }
.wizard-actions { padding: 14px 20px; }

@media (max-width: 600px) {
  .wizard-cert-facts, .wizard-summary { grid-template-columns: 1fr; }
}
</style>
