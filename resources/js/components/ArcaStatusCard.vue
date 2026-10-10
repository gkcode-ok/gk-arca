<template>
  <section class="arca-card">
    <!-- Estado: no configurado -->
    <v-card v-if="!hasConnection" class="arca-empty" elevation="0">
      <div class="arca-empty__icon"><v-icon size="34" color="error">mdi-cloud-off-outline</v-icon></div>
      <ArcaStatusChip state="red" text="ARCA no configurado" class="mb-3" />
      <h3>Conectá tu comercio con ARCA</h3>
      <p>Para comenzar a emitir facturas electrónicas desde el sistema, necesitás conectar tu comercio con ARCA.</p>
      <v-btn color="primary" size="large" variant="flat" prepend-icon="mdi-cloud-sync-outline" @click="emit('configure')">
        CONFIGURAR AHORA
      </v-btn>
      <span class="arca-empty__hint"><v-icon size="14">mdi-information-outline</v-icon> El proceso te guiará paso a paso. No necesitás conocimientos técnicos.</span>
    </v-card>

    <!-- Estado: conectado / con incidencias -->
    <v-card v-else class="arca-status" elevation="0">
      <div class="arca-status__header">
        <div class="arca-status__title">
          <v-icon size="26" color="primary">mdi-receipt-text-outline</v-icon>
          <span>ARCA</span>
        </div>
        <ArcaStatusChip :state="headline.state" :text="headline.text" />
      </div>

      <v-alert v-if="headline.state !== 'green'" :type="alertType" variant="tonal" density="comfortable" class="mb-4">
        <div class="arca-alert-body">
          <span>{{ headline.message }}</span>
          <v-btn size="small" variant="tonal" :color="alertType" @click="emit(headline.action.event)">{{ headline.action.label }}</v-btn>
        </div>
      </v-alert>

      <div class="arca-grid">
        <div class="arca-fact">
          <span><v-icon size="16">mdi-card-account-details-outline</v-icon> CUIT</span>
          <strong>{{ formatCuit(data.cuit) }}</strong>
        </div>
        <div class="arca-fact">
          <span><v-icon size="16">mdi-cloud-check-outline</v-icon> Estado</span>
          <ArcaStatusChip :state="connectionChip.state" :text="connectionChip.text" />
        </div>
        <div class="arca-fact">
          <span><v-icon size="16">mdi-file-certificate-outline</v-icon> Certificado</span>
          <ArcaStatusChip :state="certificateChip.state" :text="certificateChip.text" />
        </div>
        <div class="arca-fact">
          <span><v-icon size="16">mdi-cloud-braces</v-icon> Web Service</span>
          <strong>{{ data.web_service || 'No configurado' }}</strong>
        </div>
        <div class="arca-fact">
          <span><v-icon size="16">mdi-store-outline</v-icon> Punto de venta</span>
          <strong>{{ data.point_of_sale || 'No configurado' }}</strong>
        </div>
        <div class="arca-fact">
          <span><v-icon size="16">mdi-calendar-clock-outline</v-icon> Última conexión</span>
          <strong>{{ formatDate(data.last_connection) }}</strong>
        </div>
      </div>

      <v-divider class="my-4" />

      <div class="arca-actions">
        <v-btn color="primary" variant="flat" prepend-icon="mdi-connection" :loading="testing" @click="emit('test-connection')">
          Probar conexión
        </v-btn>
        <v-btn color="primary" variant="tonal" prepend-icon="mdi-file-certificate-outline" @click="emit('renew-certificate')">
          Renovar certificado
        </v-btn>
        <v-btn color="error" variant="outlined" prepend-icon="mdi-link-off" @click="emit('disconnect')">
          Desconectar ARCA
        </v-btn>
      </div>
    </v-card>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import ArcaStatusChip from './ArcaStatusChip.vue';

const props = defineProps({
  data: { type: Object, default: () => ({}) },
  testing: { type: Boolean, default: false },
});

const emit = defineEmits(['configure', 'test-connection', 'renew-certificate', 'disconnect']);

const hasConnection = computed(() => !!props.data && props.data.status && props.data.status !== 'not_configured');

const certificateChip = computed(() => {
  const status = props.data?.certificate?.status;
  const map = {
    valid: { state: 'green', text: 'Vigente' },
    expiring: { state: 'yellow', text: 'Por vencer' },
    expired: { state: 'red', text: 'Vencido' },
    none: { state: 'grey', text: 'Solo Consultas' },
  };
  return map[status] || map.none;
});

const connectionChip = computed(() => {
  const map = {
    connected: { state: 'green', text: 'Conectado' },
    disconnected: { state: 'red', text: 'Desconectado' },
    error: { state: 'red', text: 'Error' },
    not_configured: { state: 'grey', text: 'No configurado' },
  };
  return map[props.data?.status] || map.not_configured;
});

const alertType = computed(() => (headline.value.state === 'red' ? 'error' : 'warning'));

const headline = computed(() => {
  const d = props.data || {};

  if (d.status === 'disconnected') {
    return { state: 'red', text: 'Desconectado', message: 'El sistema no puede comunicarse con ARCA.', action: { label: 'Revisar configuración', event: 'configure' } };
  }
  if (d.status === 'error') {
    return { state: 'red', text: 'Autorización pendiente', message: 'El comercio todavía no autorizó la utilización del servicio de facturación.', action: { label: 'Ver cómo autorizar', event: 'configure' } };
  }
  if (d.certificate?.status === 'expired') {
    return { state: 'red', text: 'Certificado vencido', message: 'El certificado digital utilizado para facturar ha vencido.', action: { label: 'Renovar certificado', event: 'renew-certificate' } };
  }
  if (d.certificate?.status === 'expiring') {
    return { state: 'yellow', text: 'Certificado próximo a vencer', message: `El certificado vence el ${formatDate(d.certificate?.expires_at)}.`, action: { label: 'Renovar certificado', event: 'renew-certificate' } };
  }
  if (!d.point_of_sale) {
    return { state: 'yellow', text: 'Punto de venta pendiente', message: 'Necesitás configurar un punto de venta para comenzar a emitir facturas.', action: { label: 'Configurar punto de venta', event: 'configure' } };
  }
  return { state: 'green', text: 'Conectado', message: '', action: { label: '', event: 'configure' } };
});

function formatCuit(cuit) {
  if (!cuit || cuit.length !== 11) return cuit || '-';
  return `${cuit.slice(0, 2)}-${cuit.slice(2, 10)}-${cuit.slice(10)}`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString('es-AR', { hour12: false });
}
</script>

<style scoped>
.arca-card { display: grid; }
.arca-empty { display: grid; justify-items: center; text-align: center; gap: 10px; padding: 40px 24px; border: 1px dashed #d7deea; border-radius: 16px; background: #fbfcff; }
.arca-empty__icon { width: 64px; height: 64px; display: grid; place-items: center; border-radius: 50%; background: #fdeceb; }
.arca-empty h3 { margin: 4px 0 0; color: #172033; }
.arca-empty p { margin: 0; max-width: 420px; color: #607086; font-size: .88rem; }
.arca-empty__hint { display: inline-flex; align-items: center; gap: 5px; color: #8793a6; font-size: .74rem; margin-top: 6px; }

.arca-status { padding: 20px; border: 1px solid #dbe2ee; border-radius: 16px; background: white; }
.arca-status__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.arca-status__title { display: flex; align-items: center; gap: 8px; font-size: 1.15rem; font-weight: 700; color: #172033; }
.arca-alert-body { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

.arca-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.arca-fact { display: flex; flex-direction: column; gap: 6px; }
.arca-fact span { display: flex; align-items: center; gap: 5px; color: #8793a6; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
.arca-fact strong { color: #172033; font-size: .92rem; }

.arca-actions { display: flex; gap: 10px; flex-wrap: wrap; }

@media (max-width: 760px) {
  .arca-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 480px) {
  .arca-grid { grid-template-columns: 1fr; }
  .arca-actions { flex-direction: column; }
  .arca-actions .v-btn { width: 100%; }
}
</style>
