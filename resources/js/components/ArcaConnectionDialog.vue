<template>
  <v-dialog v-model="model" max-width="480">
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon color="primary">mdi-help-circle-outline</v-icon>
        ¿Necesitás ayuda?
      </v-card-title>
      <v-card-text>
        <p>
          Para facturar electrónicamente necesitamos autorizar la conexión entre tu comercio y este sistema.
          Este proceso se realiza mediante ARCA y permite que el sistema emita comprobantes utilizando los
          datos fiscales de tu comercio.
        </p>
        <p class="text-body-2 text-medium-emphasis">
          No necesitás realizar ninguna configuración técnica: el sistema se encarga de todo el proceso por vos.
        </p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cerrar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
const model = defineModel({ type: Boolean, default: false });
</script>
