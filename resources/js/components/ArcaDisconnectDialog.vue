<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon color="error">mdi-link-off</v-icon>
        ¿Desconectar ARCA?
      </v-card-title>
      <v-card-text>
        Si desconectás ARCA, el sistema no podrá emitir nuevas facturas electrónicas hasta volver a configurar la conexión.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancelar</v-btn>
        <v-btn color="error" variant="flat" :loading="loading" @click="confirm">Desconectar ARCA</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({ loading: { type: Boolean, default: false } });
const emit = defineEmits(['confirm']);

function confirm() {
  emit('confirm');
}
</script>
