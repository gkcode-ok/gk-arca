<template>
  <div class="pos-shell">
    <header class="pos-header" style="background-color: transparent !important">
      <div>
        <div class="eyebrow">PUNTO DE VENTA</div>
        <h1>Nueva venta</h1>
      </div>
      <div class="header-meta">
        <span><v-icon size="16">mdi-account-outline</v-icon>{{ userName }}</span
        ><span><v-icon size="16">mdi-calendar-clock</v-icon>{{ now }}</span
        ><v-chip
          :color="arcaConnected ? 'success' : 'warning'"
          size="small"
          :prepend-icon="
            arcaConnected
              ? 'mdi-cloud-check-outline'
              : 'mdi-cloud-alert-outline'
          "
          >{{ arcaConnected ? "ARCA conectado" : "ARCA no verificado" }}</v-chip
        >
      </div>
      <v-tooltip
        text="Descartar el carrito actual y comenzar otra venta"
        location="bottom"
      >
        <template #activator="{ props }"
          ><v-btn
            v-bind="props"
            variant="tonal"
            prepend-icon="mdi-refresh"
            @click="resetSale"
            >Nueva venta</v-btn
          ></template
        >
      </v-tooltip>
    </header>

    <Snacknar :data="snackbarData" />
  <Alerta :msj="alertaData" />
    <section class="customer-bar">
      <div class="section-label">CLIENTE</div>
      <v-autocomplete
        v-model="customer"
        v-model:search="customerSearch"
        :items="customers"
        item-title="display"
        return-object
        hide-details
        density="comfortable"
        variant="outlined"
        placeholder="Consumidor Final / buscar por DNI, nombre o teléfono"
        prepend-inner-icon="mdi-account-search"
        no-data-text="Escribí para buscar clientes"
        @update:search="searchCustomers"
      />
      <v-chip
        v-if="customer"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-account-check-outline"
        >Cliente seleccionado</v-chip
      >
      <v-tooltip
        text="Crear un cliente sin salir del punto de venta"
        location="bottom"
      >
        <template #activator="{ props }"
          ><v-btn
            v-bind="props"
            color="primary"
            variant="tonal"
            prepend-icon="mdi-account-plus-outline"
            @click="notify('El alta rápida de clientes se integra con el módulo Clientes existente.', 'info')"
            >Nuevo cliente</v-btn
          ></template
        >
      </v-tooltip>
    </section>

    <main class="pos-grid">
      <section class="products-panel">
        <div class="product-search">
          <v-text-field
            ref="productInput"
            v-model="productSearch"
            autofocus
            hide-details
            density="comfortable"
            variant="outlined"
            placeholder="Escanear código / buscar producto..."
            prepend-inner-icon="mdi-barcode-scan"
            @update:model-value="searchProducts"
            @keydown.enter.prevent="addFirstProduct"
          /><v-tooltip
            text="Buscar por código de barras, SKU o nombre"
            location="bottom"
            ><template #activator="{ props }"
              ><v-btn
                v-bind="props"
                color="primary"
                height="48"
                prepend-icon="mdi-magnify"
                @click="searchProducts"
                >Buscar</v-btn
              ></template
            ></v-tooltip
          >
        </div>
        <div v-if="productResults.length" class="results">
          <button
            v-for="item in productResults"
            :key="item.id"
            class="result"
            @click="addProduct(item)"
          >
            <span
              ><strong
                ><v-icon size="17" color="primary" class="mr-1">{{
                  item.sale_unit === "weight"
                    ? "mdi-weight-kilogram"
                    : "mdi-package-variant-closed"
                }}</v-icon
                >{{ item.title }}</strong
              ><small
                >{{
                  item.barcode || item.sku || item.identifier || "Sin código"
                }}
                ·
                <v-chip
                  size="x-small"
                  :color="Number(item.stock) > 0 ? 'success' : 'error'"
                  variant="tonal"
                  >Stock {{ formatQty(item.stock) }}
                  {{ item.sale_unit === "weight" ? "kg" : "un." }}</v-chip
                ></small
              ></span
            ><b
              >{{ money(item.price) }}
              <v-icon size="16">mdi-plus-circle-outline</v-icon></b
            >
          </button>
        </div>
        <div class="table-wrap">
          <v-table density="comfortable"
            ><thead>
              <tr>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th class="text-right">Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!cart.length">
                <td colspan="6" class="empty">
                  Escaneá un producto para comenzar la venta
                </td>
              </tr>
              <tr v-for="line in cart" :key="line.id">
                <td>
                  <strong>{{ line.title }}</strong
                  ><small class="d-block text-medium-emphasis">{{
                    line.barcode || line.sku || line.identifier || "Sin código"
                  }}</small>
                </td>
                <td>{{ line.sale_unit === "weight" ? "kg" : "unidad" }}</td>
                <td>
                  <div class="qty">
                    <v-btn
                      icon="mdi-minus"
                      size="small"
                      variant="tonal"
                      @click="changeQty(line, -1)"
                    /><input
                      v-model.number="line.quantity"
                      type="number"
                      min="0.001"
                      step="line.sale_unit === 'weight' ? 0.001 : 1"
                      @change="normalizeQty(line)"
                    /><v-btn
                      icon="mdi-plus"
                      size="small"
                      variant="tonal"
                      @click="changeQty(line, 1)"
                    />
                  </div>
                </td>
                <td>{{ money(line.price) }}</td>
                <td class="text-right line-total">
                  {{ money(lineTotal(line)) }}
                </td>
                <td>
                  <v-btn
                    icon="mdi-delete-outline"
                    size="small"
                    color="error"
                    variant="text"
                    @click="removeLine(line)"
                  />
                </td>
              </tr></tbody
          ></v-table>
        </div>
        <div class="shortcuts">
          <v-chip size="x-small" variant="tonal" prepend-icon="mdi-barcode-scan"
            >F2 Producto</v-chip
          ><v-chip
            size="x-small"
            variant="tonal"
            prepend-icon="mdi-account-search-outline"
            >F3 Cliente</v-chip
          ><v-chip
            size="x-small"
            variant="tonal"
            prepend-icon="mdi-cash-register"
            >F4 Pago</v-chip
          ><v-chip
            size="x-small"
            variant="tonal"
            prepend-icon="mdi-receipt-text-check-outline"
            >F10 Facturar</v-chip
          ><v-chip
            size="x-small"
            variant="tonal"
            prepend-icon="mdi-keyboard-esc"
            >ESC Limpiar</v-chip
          >
        </div>
      </section>

      <aside class="summary-panel">
        <div class="summary-title">RESUMEN</div>
        <div class="summary-row">
          <span>Productos</span
          ><b
            >{{ cart.length }} / {{ formatQty(totalUnits) }}
            {{ hasWeight ? "unidades/kg" : "unidades" }}</b
          >
        </div>
        <div class="summary-row">
          <span>Subtotal</span><b>{{ money(subtotal) }}</b>
        </div>
        <div class="summary-row">
          <span>Descuento general</span
          ><v-text-field
            v-model.number="globalDiscount"
            type="number"
            prefix="$"
            hide-details
            density="compact"
            variant="underlined"
            class="discount-input"
          />
        </div>
        <div class="summary-total">
          <span>TOTAL</span><strong>{{ money(total) }}</strong>
        </div>
        <div class="payment-heading">
          MEDIOS DE PAGO
          <v-btn
            icon="mdi-plus"
            size="small"
            variant="text"
            @click="addPayment"
          />
        </div>
        <div
          v-for="(payment, index) in payments"
          :key="index"
          class="payment-line"
        >
          <v-select
            v-model="payment.method"
            :items="paymentMethods"
            hide-details
            density="compact"
            variant="outlined"
          /><v-text-field
            v-model.number="payment.amount"
            type="number"
            prefix="$"
            hide-details
            density="compact"
            variant="outlined"
          />
        </div>
        <div class="quick-pay">
          <v-btn size="small" variant="tonal" @click="setExact">Exacto</v-btn
          ><v-btn size="small" variant="tonal" @click="paymentAmount(10000)"
            >$10.000</v-btn
          ><v-btn size="small" variant="tonal" @click="paymentAmount(20000)"
            >$20.000</v-btn
          >
        </div>
        <div class="paid-row">
          <span>Pagado</span><b>{{ money(paid) }}</b>
        </div>
        <div class="paid-row" :class="pending > 0 ? 'pending' : 'ok'">
          <span>{{ pending > 0 ? "Pendiente" : "Vuelto" }}</span
          ><b>{{ money(pending > 0 ? pending : change) }}</b>
        </div>
        <v-select
          v-model="receiptType"
          :items="receiptTypes"
          label="Comprobante"
          density="compact"
          variant="outlined"
          class="mt-5"
        /><v-select
          v-if="receiptType !== 'ticket'"
          v-model="fiscalConditionId"
          :items="fiscalConditions"
          label="Condición IVA del receptor"
          density="compact"
          variant="outlined"
          class="mt-2"
        /><v-btn
          block
          size="large"
          color="primary"
          class="invoice-btn"
          :disabled="!canInvoice || saving"
          :loading="saving"
          @click="invoice"
          >FACTURAR {{ money(total) }}</v-btn
        ><small class="fiscal-note"
          >La venta se registra en forma transaccional y queda pendiente de
          autorización ARCA.</small
        >
      </aside>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { getCurrentInstance } from "vue";

const { appContext } = getCurrentInstance();
const api = appContext.config.globalProperties.$gkaxios;
const productInput = ref(null);
const productSearch = ref("");
const productResults = ref([]);
const cart = ref([]);
const customers = ref([]);
const customer = ref(null);
const customerSearch = ref("");
const globalDiscount = ref(0);
const receiptType = ref("ticket");
const fiscalConditionId = ref(5);
const saving = ref(false);
const now = ref("");
const arcaConnected = ref(false);
const snackbarData = ref({ visible: false, text: "", color: "info", style: {} });
const alertaData = ref({ mensaje: "", visible: false, tipo: 0, color: "" });
const payments = ref([{ method: "cash", amount: 0 }]);
const paymentMethods = [
  { title: "Efectivo", value: "cash" },
  { title: "Tarjeta débito", value: "debit_card" },
  { title: "Tarjeta crédito", value: "credit_card" },
  { title: "Mercado Pago", value: "mercado_pago" },
  { title: "Transferencia", value: "transfer" },
];
const receiptTypes = [
  { title: "Ticket interno", value: "ticket" },
  { title: "Factura A", value: "factura_a" },
  { title: "Factura B", value: "factura_b" },
  { title: "Factura C", value: "factura_c" },
];
const fiscalConditions = [
  { title: "Responsable inscripto", value: 1 },
  { title: "Exento", value: 4 },
  { title: "Consumidor final", value: 5 },
  { title: "Monotributista", value: 6 },
];
const userName = window.user?.name || "Operador";
const subtotal = computed(() =>
  cart.value.reduce((sum, line) => sum + lineTotal(line), 0)
);
const total = computed(() =>
  Math.max(0, subtotal.value - Number(globalDiscount.value || 0))
);
const paid = computed(() =>
  payments.value.reduce((sum, item) => sum + Number(item.amount || 0), 0)
);
const pending = computed(() => Math.max(0, total.value - paid.value));
const change = computed(() => Math.max(0, paid.value - total.value));
const totalUnits = computed(() =>
  cart.value.reduce((sum, line) => sum + Number(line.quantity || 0), 0)
);
const hasWeight = computed(() =>
  cart.value.some((line) => line.sale_unit === "weight")
);
const canInvoice = computed(() => cart.value.length > 0 && pending.value <= 0);
const money = (value) =>
  new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 2,
  }).format(Number(value || 0));
const formatQty = (value) =>
  Number(value || 0).toLocaleString("es-AR", { maximumFractionDigits: 3 });
const lineTotal = (line) =>
  Math.max(
    0,
    Number(line.price) * Number(line.quantity || 0) - Number(line.discount || 0)
  );
let searchTimer;
async function searchProducts() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    if (!productSearch.value?.trim()) {
      productResults.value = [];
      return;
    }
    try {
      const response = await api.get("/home/Arca/pos/productos", { q: productSearch.value.trim() });
      productResults.value = response.data || [];
    } catch {
      notify("No fue posible buscar productos.", "error");
    }
  }, 150);
}
function addFirstProduct() {
  if (productResults.value[0]) addProduct(productResults.value[0]);
}
function addProduct(product) {
  const current = cart.value.find((line) => line.id === product.id);
  if (current) current.quantity += product.sale_unit === "weight" ? 0.1 : 1;
  else
    cart.value.push({
      ...product,
      quantity: product.sale_unit === "weight" ? 0.1 : 1,
      discount: 0,
    });
  productResults.value = [];
  productSearch.value = "";
  focusSearch();
}
function changeQty(line, amount) {
  line.quantity =
    Number(line.quantity || 0) +
    (line.sale_unit === "weight" ? amount * 0.1 : amount);
  normalizeQty(line);
}
function normalizeQty(line) {
  line.quantity = Math.max(
    line.sale_unit === "weight" ? 0.001 : 1,
    Number(line.quantity || 0)
  );
  if (line.sale_unit !== "weight") line.quantity = Math.round(line.quantity);
}
function removeLine(line) {
  cart.value = cart.value.filter((item) => item.id !== line.id);
  focusSearch();
}
function addPayment() {
  payments.value.push({ method: "transfer", amount: 0 });
}
function setExact() {
  payments.value[0].amount = total.value;
}
function paymentAmount(value) {
  payments.value[0].amount = value;
}
async function searchCustomers(value) {
  if (!value?.trim()) {
    customers.value = [];
    return;
  }
  try {
    const response = await api.get("/home/Arca/pos/clientes", { q: value.trim() });
    customers.value = (response.data || []).map((item) => ({
    ...item,
    display:
      `${item.name || ""} ${item.last_name || ""}`.trim() +
      (item.document_number ? ` · ${item.document_number}` : ""),
    }));
  } catch {
    notify("No fue posible buscar clientes.", "error");
  }
}
async function invoice() {
  const receiptWindow = window.open("", "_blank");
  saving.value = true;
  try {
    const response = await api.post("/home/Arca/pos/ventas", {
      customer_person_id: customer.value?.id || null,
      receipt_type: receiptType.value,
      fiscal_condition_id: receiptType.value === "ticket" ? null : fiscalConditionId.value,
      discount: Number(globalDiscount.value || 0),
      items: cart.value.map((item) => ({
        item_id: item.item_id || item.id,
        quantity: item.quantity,
        discount: item.discount || 0,
      })),
      payments: payments.value.filter((item) => Number(item.amount) > 0),
    });
    if (response.status !== 0) {
      receiptWindow?.close();
      showError(response.descripcion || "No fue posible registrar la venta.");
      return;
    }
    const receiptUrl = response.data?.receipt_url;
    if (receiptUrl) {
      if (receiptWindow) receiptWindow.location.href = receiptUrl;
      else window.open(receiptUrl, "_blank");
    } else {
      receiptWindow?.close();
    }
    notify(response.descripcion || `Venta #${response.data?.sale?.id || ""} registrada.`, "success");
    resetSale(false);
  } catch (error) {
    receiptWindow?.close();
    showError(error.response?.data?.descripcion || error.response?.data?.message ||
      Object.values(error.response?.data?.errors || {})[0]?.[0] ||
      "No fue posible registrar la venta.");
  } finally {
    saving.value = false;
  }
}
function resetSale(clearMessage = true) {
  cart.value = [];
  globalDiscount.value = 0;
  payments.value = [{ method: "cash", amount: 0 }];
  customer.value = null;
  productResults.value = [];
  productSearch.value = "";
  focusSearch();
}
function notify(text, color = "info") {
  snackbarData.value = { visible: true, text, color, style: {} };
}
function showError(mensaje) {
  alertaData.value = {
    mensaje,
    visible: true,
    tipo: 3,
    color: "error",
  };
}
function focusSearch() {
  nextTick(() => productInput.value?.focus?.());
}
function keyHandler(event) {
  if (event.key === "F10" || (event.ctrlKey && event.key === "Enter")) {
    event.preventDefault();
    if (canInvoice.value) invoice();
  }
  if (event.key === "Escape") resetSale();
}
onMounted(async () => {
  now.value = new Date().toLocaleString("es-AR");
  window.addEventListener("keydown", keyHandler);
  try {
    const response = await api.get("/home/Arca/estado");
    arcaConnected.value = Boolean(response.data?.connected);
  } catch {
    arcaConnected.value = false;
  }
});
onUnmounted(() => window.removeEventListener("keydown", keyHandler));
</script>

<style scoped>
.pos-shell {
  box-sizing: border-box;
  height: 100%;
  min-height: calc(100vh - 64px);
  overflow-y: auto;
  padding: 26px;
  background: linear-gradient(135deg, #071426 0%, #102746 42%, #1b355a 100%);
  color: #e8eef9;
}
.pos-header,
.customer-bar,
.product-search,
.summary-row,
.paid-row,
.payment-heading,
.shortcuts,
.header-meta {
  display: flex;
  align-items: center;
  gap: 14px;
}
.pos-header {
  justify-content: space-between;
  margin-bottom: 20px;
}
.pos-header h1 {
  margin: 0;
  font-size: 30px;
  color: #f8fafc;
}
.eyebrow,
.section-label,
.summary-title {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 1.2px;
  color: #93b4db;
}
.header-meta {
  color: #c6d4e8;
  font-size: 13px;
  flex-wrap: wrap;
}
.customer-bar {
  background: linear-gradient(135deg, #142943, #1c3a60);
  border: 1px solid #5276a3;
  border-radius: 16px;
  padding: 12px 16px;
  margin-bottom: 18px;
  box-shadow: 0 12px 28px #02081766;
}
.customer-bar .v-autocomplete {
  flex: 1;
}
.pos-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: 18px;
  align-items: start;
}
.products-panel,
.summary-panel {
  background: linear-gradient(155deg, #f8fbff 0%, #eef4fb 100%);
  border: 1px solid #7894b8;
  border-radius: 16px;
  box-shadow: 0 16px 38px #02081766;
  color: #172033;
}
.products-panel {
  min-height: 640px;
  position: relative;
  overflow: hidden;
}
.product-search {
  padding: 16px;
  background: #e8f0fa;
  border-bottom: 1px solid #c4d3e5;
}
.product-search .v-text-field {
  flex: 1;
}
.results {
  position: absolute;
  z-index: 5;
  left: 16px;
  right: 105px;
  top: 82px;
  background: #fff;
  border: 1px solid #9cb4d1;
  border-radius: 12px;
  box-shadow: 0 16px 32px #0208173d;
  overflow: hidden;
}
.result {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 13px 16px;
  text-align: left;
  border: 0;
  border-bottom: 1px solid #e4ebf4;
  background: #fff;
  color: #172033;
}
.result:hover {
  background: #e8f2ff;
}
.result strong {
  display: flex;
  align-items: center;
}
.result small {
  display: block;
  color: #64748b;
  margin-top: 4px;
}
.result b {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #1558ad;
  white-space: nowrap;
}
.table-wrap {
  padding: 4px 10px;
  max-height: calc(100vh - 310px);
  min-height: 340px;
  overflow: auto;
}
.table-wrap :deep(th) {
  color: #5b7190 !important;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}
.table-wrap :deep(td) {
  border-color: #dce6f1 !important;
}
.empty {
  text-align: center;
  padding: 100px 0 !important;
  color: #7890ad;
}
.qty {
  display: flex;
  align-items: center;
  gap: 5px;
}
.qty input {
  width: 58px;
  text-align: center;
  border: 1px solid #b7c9de;
  border-radius: 7px;
  padding: 6px;
  background: #fff;
}
.line-total {
  font-weight: 800;
  color: #124d95;
}
.shortcuts {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 16px;
  color: #506986;
  font-size: 11px;
  background: #e5eef8;
  border-radius: 0 0 16px 16px;
  flex-wrap: wrap;
}
.summary-panel {
  padding: 22px;
  position: sticky;
  top: 16px;
}
.summary-title {
  margin-bottom: 20px;
  color: #41658f;
}
.summary-row {
  justify-content: space-between;
  margin: 14px 0;
  color: #64748b;
}
.summary-row b {
  color: #172033;
}
.discount-input {
  max-width: 120px;
}
.summary-total {
  border-top: 1px solid #c6d5e5;
  border-bottom: 1px solid #c6d5e5;
  padding: 20px 0;
  margin: 18px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.summary-total strong {
  font-size: 30px;
  color: #1558ad;
}
.payment-heading {
  justify-content: space-between;
  font-weight: 800;
  font-size: 12px;
  color: #284d78;
}
.payment-line {
  display: grid;
  grid-template-columns: 1fr 125px;
  gap: 8px;
  margin-top: 8px;
}
.quick-pay {
  display: flex;
  gap: 6px;
  margin: 12px 0;
  flex-wrap: wrap;
}
.paid-row {
  justify-content: space-between;
  margin-top: 9px;
}
.paid-row.ok {
  color: #15803d;
}
.paid-row.pending {
  color: #b45309;
}
.invoice-btn {
  font-weight: 800;
  margin-top: 4px;
  box-shadow: 0 8px 18px #1769d244;
}
.fiscal-note {
  display: block;
  text-align: center;
  color: #64748b;
  margin-top: 12px;
  line-height: 1.4;
}
@media (max-width: 1000px) {
  .pos-grid {
    grid-template-columns: 1fr;
  }
  .summary-panel {
    position: static;
  }
  .pos-header,
  .customer-bar {
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .table-wrap {
    max-height: none;
  }
}
@media (max-width: 600px) {
  .pos-shell {
    padding: 12px;
  }
  .header-meta {
    font-size: 11px;
  }
  .pos-header h1 {
    font-size: 24px;
  }
  .customer-bar {
    display: block;
  }
  .customer-bar > * {
    margin-bottom: 8px;
  }
  .products-panel {
    min-height: 520px;
  }
  .product-search {
    align-items: stretch;
    flex-direction: column;
  }
  .product-search .v-btn {
    width: 100%;
  }
  .results {
    left: 12px;
    right: 12px;
    top: 137px;
  }
}
</style>
