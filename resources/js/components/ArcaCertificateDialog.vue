<template>
  <v-dialog v-model="model" max-width="520">
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon color="primary">mdi-file-certificate-outline</v-icon>
        Renovar certificado
      </v-card-title>
      <v-card-text>
        <p>
          El certificado digital permite que el sistema facture electrónicamente en nombre de tu comercio.
          Si el certificado está próximo a vencer, podés reemplazarlo por uno nuevo.
        </p>

        <v-alert v-if="expiresAt" type="warning" variant="tonal" density="compact" class="mb-4">
          Vence el {{ formatDate(expiresAt) }}
        </v-alert>

        <template v-if="step === 'intro'">
          <div class="renew-actions">
            <a href="#" class="renew-link" @click.prevent="openHelp">¿Cómo obtengo mi certificado?</a>
          </div>
        </template>

        <v-form v-else v-model="valid">
          <v-file-input v-model="form.cert_file" label="Certificado (.crt)" accept=".crt,.pem" prepend-icon="mdi-file-certificate-outline" variant="outlined" density="comfortable" :rules="[required]" />
          <v-file-input v-model="form.key_file" label="Clave privada (.key o .pem)" accept=".key,.pem,.txt" prepend-icon="mdi-key-outline" variant="outlined" density="comfortable" :rules="[required]" />
          <p class="renew-hint">Si tu clave privada esta en otro formato (por ejemplo .pem), no hace falta convertirla con openssl: el sistema la procesa automaticamente.</p>
          <v-text-field v-model="form.passphrase" label="Passphrase (opcional)" type="password" variant="outlined" density="comfortable" />
          <v-text-field v-model="form.fecha_vencimiento" label="Fecha de vencimiento" type="date" variant="outlined" density="comfortable" />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="close">Cancelar</v-btn>
        <v-btn v-if="step === 'intro'" color="primary" variant="flat" @click="step = 'form'">Continuar renovación</v-btn>
        <v-btn v-else color="primary" variant="flat" :loading="loading" :disabled="!form.cert_file || !form.key_file" @click="submit">
          Cargar certificado
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
  loading: { type: Boolean, default: false },
  expiresAt: { type: String, default: '' },
});
const emit = defineEmits(['submit']);

const step = ref('intro');
const valid = ref(false);
const form = ref({ cert_file: null, key_file: null, passphrase: '', fecha_vencimiento: '' });

watch(model, (value) => {
  if (value) {
    step.value = 'intro';
    form.value = { cert_file: null, key_file: null, passphrase: '', fecha_vencimiento: '' };
  }
});

function required(value) {
  return !!value || 'Este campo es requerido';
}

function openHelp() {
  window.open('https://www.afip.gob.ar/ws/', '_blank', 'noopener');
}

function close() {
  model.value = false;
}

function submit() {
  emit('submit', { ...form.value });
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('es-AR');
}
</script>

<style scoped>
.renew-actions { margin-top: 6px; }
.renew-link { color: #2563eb; font-size: .85rem; text-decoration: underline; }
.renew-hint { color: #607086; font-size: .8rem; margin: -6px 0 4px; }
</style>
