<template>
  <section class="arca-api-tester">
    <div class="tester-heading">
      <div>
        <div class="tester-kicker">Herramientas de diagnóstico</div>
        <h2>Probar APIs de GKArca</h2>
        <p>Ejecutá consultas sobre la conexión actual. Las operaciones que emiten comprobantes pueden generar efectos reales en ARCA.</p>
      </div>
      <v-select v-model="environment" :items="environments" label="Entorno" variant="outlined" density="compact" hide-details class="environment-select" />
    </div>

    <v-expansion-panels variant="accordion" multiple>
      <v-expansion-panel v-for="group in groups" :key="group.title">
        <v-expansion-panel-title>
          <v-icon :icon="group.icon" color="primary" class="mr-3" />
          <span>
            <strong>{{ group.title }}</strong>
            <small>{{ group.description }}</small>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="api-grid">
            <article v-for="api in group.apis" :key="api.key" class="api-card">
              <div class="api-card__title">
                <div>
                  <h3>{{ api.title }}</h3>
                  <p>{{ api.description }}</p>
                </div>
                <code>{{ api.method }} {{ api.path }}</code>
              </div>

              <div v-if="api.fields?.length" class="api-fields">
                <template v-for="field in api.fields" :key="field.key">
                  <v-textarea v-if="field.type === 'json'" v-model="api.values[field.key]" :label="field.label" :hint="field.hint" persistent-hint rows="4" variant="outlined" density="compact" />
                  <v-text-field v-else v-model="api.values[field.key]" :label="field.label" :hint="field.hint" :type="field.type || 'text'" persistent-hint variant="outlined" density="compact" />
                </template>
              </div>

              <div class="api-card__actions">
                <v-btn color="primary" variant="flat" :loading="api.loading" prepend-icon="mdi-play" @click="execute(api)">Ejecutar prueba</v-btn>
                <span v-if="api.lastRun" class="run-status" :class="api.lastRun.status === 0 ? 'is-ok' : 'is-error'">{{ api.lastRun.descripcion }}</span>
              </div>
              <pre v-if="api.lastRun" class="api-result">{{ formatResult(api.lastRun) }}</pre>
            </article>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </section>
</template>

<script setup>
import { getCurrentInstance, reactive, ref } from 'vue';

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;
const environment = ref('homo');
const environments = [
  { title: 'Homologación (pruebas)', value: 'homo' },
  { title: 'Producción', value: 'prod' },
];

const field = (key, label, hint = '') => ({ key, label, hint });
const jsonField = (key, label, value, hint = '') => ({ key, label, hint, type: 'json', value });
const api = (key, title, path, description, fields = [], method = 'POST') => ({
  key, title, path, description, method, fields, loading: false, lastRun: null,
  values: Object.fromEntries(fields.map((item) => [item.key, item.value ?? ''])),
});

const groups = reactive([
  {
    title: 'Configuración y consultas básicas', icon: 'mdi-shield-search-outline',
    description: 'Verificá el entorno, el certificado y los datos fiscales de un CUIT.',
    apis: [
      api('setEntorno', 'Cambiar entorno', '/v1/Arca/setEntorno', 'Define para esta consulta si GKArca debe comunicarse con homologación o producción.'),
      api('buscarCuit', 'Consultar CUIT', '/v1/Arca/buscarCuit', 'Obtiene razón social, estado y condición fiscal desde el padrón ARCA.', [field('cuit', 'CUIT', '11 dígitos, con o sin guiones')]),
      api('buscarCertificado', 'Verificar certificado', '/v1/Arca/buscarCertificado', 'Comprueba que el certificado exista, sea válido y muestra su vencimiento.', [], 'POST'),
      api('consultarApoc', 'Consultar reputación fiscal APOC', '/v1/Arca/Apoc/consultar', 'Consulta publicaciones de contribuyentes en la base APOC.', [field('cuit', 'CUIT', '11 dígitos')], 'GET'),
    ],
  },
  {
    title: 'Comprobantes y comunicaciones', icon: 'mdi-file-document-search-outline',
    description: 'Consultá la ventanilla electrónica y servicios de constatación.',
    apis: [
      api('comunicaciones', 'Comunicaciones', '/v1/Arca/comunicaciones', 'Recupera las comunicaciones disponibles en la ventanilla electrónica.', [jsonField('payload', 'Parámetros JSON', '{}', 'Opcional: filtros aceptados por veconsumerws.')]),
      api('constatarComprobante', 'Constatar comprobante', '/v1/Arca/constatarComprobante', 'Consulta datos auxiliares y documentos disponibles para constatar comprobantes.', [jsonField('payload', 'Parámetros JSON', '{}', 'Enviá los parámetros requeridos por wscdc.')]),
    ],
  },
  {
    title: 'Facturación electrónica (WSFE)', icon: 'mdi-receipt-text-check-outline',
    description: 'Consultá numeración, emití una factura o revisá un comprobante autorizado.',
    apis: [
      api('getUltimoComprobante', 'Último comprobante autorizado', '/v1/Arca/Facturacion/getUltimoComprobante', 'Devuelve el último número autorizado para un punto de venta y tipo de comprobante.', [field('PtoVta', 'Punto de venta', 'Ej. 1'), field('CbteTipo', 'Tipo de comprobante', 'Ej. 6')]),
      api('crearFactura', 'Solicitar CAE / crear factura', '/v1/Arca/Facturacion/crearFactura', 'Envía una solicitud de autorización a ARCA. Puede generar un comprobante real.', [jsonField('FeCAEReq', 'FeCAEReq (JSON)', '{\n  "FeCabReq": {},\n  "FeDetReq": { "FECAEDetRequest": [] }\n}', 'Payload requerido por WSFE.')]),
      api('consultarFactura', 'Consultar factura', '/v1/Arca/Facturacion/consultarFactura', 'Consulta el estado y detalle de un comprobante ya emitido.', [field('PtoVta', 'Punto de venta'), field('CbteTipo', 'Tipo de comprobante'), field('CbteNro', 'Número de comprobante')]),
    ],
  },
  {
    title: 'Facturación con detalle (WSMTXCA)', icon: 'mdi-package-variant-closed-check',
    description: 'Operaciones de facturación para comprobantes con productos y detalle.',
    apis: [
      api('getUltimoComprobanteMtxca', 'Último comprobante con detalle', '/v1/Arca/FacturacionDetalle/getUltimoComprobante', 'Devuelve el último número autorizado en WSMTXCA.', [field('pto_vta', 'Punto de venta'), field('cbte_tipo', 'Tipo de comprobante')]),
      api('crearFacturaMtxca', 'Autorizar comprobante con detalle', '/v1/Arca/FacturacionDetalle/crearFactura', 'Envía un comprobante detallado a ARCA. Puede generar un comprobante real.', [jsonField('comprobanteRequest', 'comprobanteRequest (JSON)', '{}', 'Payload requerido por WSMTXCA.')]),
      api('consultarFacturaMtxca', 'Consultar comprobante con detalle', '/v1/Arca/FacturacionDetalle/consultarFactura', 'Consulta un comprobante previamente autorizado en WSMTXCA.', [field('pto_vta', 'Punto de venta'), field('cbte_tipo', 'Tipo de comprobante'), field('cbte_nro', 'Número de comprobante')]),
    ],
  },
]);

async function execute(operation) {
  const parametros = {};
  try {
    for (const item of operation.fields) {
      if (item.type === 'json') {
        parametros[item.key] = operation.values[item.key] ? JSON.parse(operation.values[item.key]) : {};
      } else if (operation.values[item.key] !== '') {
        parametros[item.key] = ['PtoVta', 'CbteTipo', 'CbteNro', 'pto_vta', 'cbte_tipo', 'cbte_nro'].includes(item.key)
          ? Number(operation.values[item.key])
          : operation.values[item.key];
      }
    }
    operation.loading = true;
    operation.lastRun = await $gkaxios.post('/home/Arca/probar-api', {
      operacion: operation.key,
      entorno: environment.value === 'prod',
      parametros,
    });
  } catch (error) {
    operation.lastRun = error?.response?.data || { status: -1, descripcion: error?.message || 'No se pudo ejecutar la prueba.' };
  } finally {
    operation.loading = false;
  }
}

function formatResult(result) {
  return JSON.stringify(result, null, 2);
}
</script>

<style scoped>
.arca-api-tester { margin-top: 18px; padding: 20px; background: #fff; border: 1px solid #e6ebf2; border-radius: 16px; }
.tester-heading { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; margin-bottom: 16px; }
.tester-kicker { color: #3f65c5; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.tester-heading h2 { margin: 3px 0 4px; color: #172033; font-size: 1.15rem; }
.tester-heading p, .api-card p { margin: 0; color: #748095; font-size: .78rem; }
.environment-select { max-width: 220px; min-width: 190px; }
.v-expansion-panel-title span { display: flex; flex-direction: column; gap: 2px; }
.v-expansion-panel-title small { color: #7b8799; font-size: .73rem; }
.api-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.api-card { padding: 15px; border: 1px solid #e8edf4; border-radius: 12px; background: #fbfcfe; }
.api-card__title { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.api-card h3 { margin: 0 0 4px; color: #202c40; font-size: .9rem; }
.api-card code { flex: 0 0 auto; color: #60708a; font-size: .64rem; white-space: normal; text-align: right; }
.api-fields { display: grid; gap: 10px; margin-bottom: 12px; }
.api-card__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.run-status { font-size: .72rem; }.is-ok { color: #16845d; }.is-error { color: #c33b48; }
.api-result { max-height: 260px; overflow: auto; margin: 13px 0 0; padding: 11px; border-radius: 8px; background: #172033; color: #dbe7ff; font-size: .7rem; white-space: pre-wrap; word-break: break-word; }
@media (max-width: 850px) { .api-grid { grid-template-columns: 1fr; } .tester-heading { flex-direction: column; } .environment-select { max-width: none; width: 100%; } }
</style>
