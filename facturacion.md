Quiero desarrollar un módulo profesional de FACTURACIÓN ELECTRÓNICA tipo POS para mi sistema.Ver 
Ver documentacion del sistema y seguir las lineas de programacion y desarrollo, ademar seguir con la misma arquitectura,
ademas respetar la composicion y forma de creacion de los componentes frontend.
Usamos en este sistema Laravel, vue, vuetify

IMPORTANTE:
- El sistema está desarrollado con Laravel en backend y Vue + Vuetify en frontend.
- La integración con ARCA/AFIP YA ESTÁ RESUELTA mediante una API externa/intermedia.
- NO quiero que desarrolles la conexión directa con ARCA.
- El frontend y backend de este módulo deben comunicarse con mi API de facturación existente.
- El objetivo principal es que la carga de una factura sea extremadamente rápida y práctica para un comercio.
- La pantalla debe estar pensada para trabajar con una pistola lectora de códigos de barras.
- El operador debe poder cargar muchos productos sin utilizar el mouse.
- No quiero una pantalla tradicional de formulario de factura. Quiero una experiencia tipo POS moderna.

OBJETIVO PRINCIPAL:

Crear una pantalla "Nueva Factura" donde el flujo normal sea:

1. Seleccionar cliente.
2. Si el cliente no existe, poder crearlo rápidamente sin salir de la factura.
3. Escanear productos mediante código de barras.
4. Cada escaneo debe agregar automáticamente el producto.
5. Si el producto ya existe en la factura, aumentar automáticamente la cantidad en lugar de crear otra línea.
6. Después de cada escaneo el foco debe volver automáticamente al campo de código de barras.
7. Poder buscar productos manualmente cuando no tengan código de barras.
8. Poder modificar cantidades.
9. Poder eliminar productos.
10. Calcular subtotal, descuentos, IVA y total.
11. Seleccionar forma de pago.
12. Validar la información en backend.
13. Enviar la factura a mi API de facturación.
14. Recibir la autorización, número de comprobante, CAE y vencimiento.
15. Guardar la factura.
16. Mostrar resultado de factura autorizada o rechazada.
17. Poder visualizar, imprimir, descargar PDF y enviar la factura.

DISEÑO GENERAL:

La pantalla debe ser moderna, profesional y limpia, similar a un sistema POS/ERP comercial.

Estructura:

- Sidebar de navegación.
- Header superior.
- Indicador de estado ARCA.
- Área principal de facturación.
- Cliente arriba.
- Campo de código de barras inmediatamente debajo.
- Grilla de productos.
- Panel lateral derecho con totales y acciones.
- Atajos de teclado visibles.
- Diseño responsive, aunque la prioridad es escritorio porque se utilizará en comercios.

ESTRUCTURA VISUAL:

HEADER:

Mostrar:

"Facturación - Nueva Factura"

A la derecha:

- Estado ARCA: 🟢 Conectado / 🔴 Desconectado
- Usuario
- Empresa actual

DATOS DEL COMPROBANTE:

Mostrar:

- Tipo de comprobante
- Punto de venta
- Fecha
- Número de comprobante

El número no debe ser ingresado manualmente por el usuario.

El punto de venta debe venir de la configuración.

El tipo de comprobante debe ser validado por backend según la condición fiscal del emisor y cliente.

CLIENTE:

Crear un componente CustomerSelector.

Debe permitir buscar cliente por:

- CUIT
- DNI
- Nombre
- Razón social
- Código interno

Mientras el usuario escribe, mostrar resultados.

Ejemplo:

Juan Pérez
CUIT: 20-12345678-9
Consumidor Final

Empresa SA
CUIT: 30-71234567-8
Responsable Inscripto

Al seleccionar un cliente, mostrar sus datos resumidos.

Debe existir un botón:

"+ Nuevo cliente"

También debe existir:

"Nuevo cliente rápido"

El alta rápida debe pedir solamente:

- Nombre / Razón Social
- CUIT / DNI
- Condición frente al IVA

Opcionalmente:

- Domicilio
- Email
- Teléfono

Al guardar el cliente:

- Crear el registro.
- Seleccionarlo automáticamente.
- Continuar con la factura sin salir de la pantalla.

Si el cliente no existe al buscarlo, mostrar:

"Cliente no encontrado"

y botones:

"+ Nuevo cliente"
"+ Nuevo cliente rápido"

No sacar al usuario de la factura.

CONSUMIDOR FINAL:

Debe existir la posibilidad de trabajar con un cliente genérico "Consumidor Final" cuando corresponda.

No obligar a crear una ficha completa para cada operación de consumidor final.

CÓDIGO DE BARRAS:

Esta es la parte más importante de la pantalla.

Crear un componente BarcodeScannerInput.

Debe existir un campo grande y visible:

"Escanear o ingresar código de barras..."

El lector de código de barras normalmente funciona como teclado.

Por lo tanto:

código + ENTER

debe disparar automáticamente la búsqueda del producto.

Ejemplo:

7791234567890 + ENTER

Debe ejecutar:

GET /api/products/barcode/{barcode}

o utilizar el endpoint equivalente ya existente.

Cuando encuentra el producto:

- Agregarlo automáticamente a la grilla.
- Cantidad inicial = 1.
- Mostrar precio.
- Mostrar IVA.
- Mostrar subtotal.
- Limpiar el campo.
- Volver a colocar automáticamente el foco en el campo de código de barras.

El operador debe poder hacer:

SCAN
SCAN
SCAN
SCAN
SCAN

sin utilizar el mouse.

PRODUCTO REPETIDO:

Si se escanea un producto que ya existe en la factura:

NO crear otra línea.

Incrementar automáticamente:

cantidad = cantidad + 1

Ejemplo:

Primer escaneo:

Coca Cola 1.5L
Cantidad 1

Segundo escaneo:

Coca Cola 1.5L
Cantidad 2

Tercer escaneo:

Coca Cola 1.5L
Cantidad 3

Esto es obligatorio.

PRODUCTO NO ENCONTRADO:

Si el código no existe:

Mostrar un mensaje no invasivo:

"Producto no encontrado"

Mostrar el código leído.

Dar opciones:

- Buscar producto manualmente
- Crear producto
- Cancelar

No perder los productos que ya están cargados.

No cerrar la factura.

PRODUCTO MANUAL:

Crear búsqueda manual mediante F2.

Debe permitir buscar por:

- Código
- Descripción
- Código de barras
- Nombre

Mostrar resultados en una lista rápida:

Código
Descripción
Precio
IVA
Stock

Al seleccionar:

Agregar automáticamente a la factura.

GRILLA DE PRODUCTOS:

Crear un componente InvoiceItems.

Columnas:

- Código
- Producto / Servicio
- Cantidad
- Precio
- IVA
- Descuento
- Subtotal
- Acción eliminar

Ejemplo:

779001 | Coca Cola 1.5L | 2 | $2.500 | 21% | $5.000
779002 | Agua 500ml     | 3 | $1.200 | 21% | $3.600

La cantidad debe poder modificarse rápidamente.

Utilizar:

[-] cantidad [+]

También permitir edición directa si resulta conveniente.

Agregar:

Eliminar línea.

Si se elimina una línea, recalcular inmediatamente los totales.

ATAJOS DE TECLADO:

Implementar:

F2 = Buscar producto
F3 = Buscar cliente
F4 = Cambiar cliente
F6 = Forma de pago
F10 = Emitir factura
ENTER = Agregar código escaneado
DEL = Eliminar línea seleccionada
+ = Aumentar cantidad
- = Disminuir cantidad
ESC = Cancelar modal o acción

Los atajos no deben interferir con inputs normales.

El foco debe permanecer en el campo de código de barras después de agregar un producto.

PRECIOS:

El precio debe venir del producto/backend.

No confiar en precios enviados por el frontend.

Debe existir configuración para permitir o no modificar precios.

Ejemplo:

Administrador = puede modificar precio
Supervisor = puede modificar precio
Vendedor = no puede modificar precio

Si se permite modificar precio, registrar el precio utilizado.

DESCUENTOS:

Permitir:

- Descuento por línea.
- Descuento general.

Ejemplo:

Producto: $10.000
Descuento: 10%
Subtotal: $9.000

Registrar el descuento aplicado.

IVA:

El IVA debe provenir de la configuración fiscal del producto y ser validado por backend.

No confiar únicamente en el valor enviado desde Vue.

Contemplar las diferentes alícuotas necesarias:

- 0%
- 2.5%
- 5%
- 10.5%
- 21%
- 27%

El backend debe recalcular todos los importes antes de emitir.

TOTALES:

Crear un componente InvoiceTotals.

Debe mostrar siempre:

Subtotal
Descuentos
Neto gravado
IVA 0%
IVA 10.5%
IVA 21%
IVA 27%
Otros impuestos si corresponden
TOTAL

Ejemplo:

Subtotal        $35.000
Descuentos           $0
Neto            $35.000
IVA 21%          $7.350
------------------------
TOTAL           $42.350

El frontend puede calcular en tiempo real para mostrar una experiencia fluida.

Pero el backend debe recalcular todo antes de autorizar.

FORMA DE PAGO:

Crear componente PaymentSelector.

Permitir:

- Contado
- Efectivo
- Transferencia
- Tarjeta débito
- Tarjeta crédito
- Cuenta corriente
- Mixto

Si es Mixto permitir distribuir:

Efectivo       $20.000
Transferencia  $15.000
Total          $35.000

La forma de pago debe quedar almacenada en la factura.

FACTURA:

La factura debe tener:

- Cliente
- Tipo de comprobante
- Punto de venta
- Número
- Fecha
- Productos
- Cantidades
- Precios
- IVA
- Descuentos
- Totales
- Forma de pago
- Observaciones
- Usuario que creó la factura
- Empresa

ESTADOS:

Implementar estados claros:

DRAFT
PENDING
PROCESSING
AUTHORIZED
REJECTED
ERROR
UNKNOWN

Flujo normal:

DRAFT
↓
PENDING
↓
PROCESSING
↓
AUTHORIZED

Si ARCA rechaza:

PROCESSING
↓
REJECTED

Si hay un problema de comunicación donde no sabemos si ARCA autorizó:

PROCESSING
↓
UNKNOWN

MUY IMPORTANTE:

Si existe timeout después de enviar la factura a la API, NO volver a emitir automáticamente porque podría generar un comprobante duplicado.

Debe existir una forma de consultar/confirmar el estado antes de reintentar.

BACKEND:

Crear servicios separados.

InvoiceService
InvoiceCalculationService
CustomerService
ProductService
StockService
ArcaInvoiceService

El InvoiceController no debe contener toda la lógica.

Ejemplo:

InvoiceController
    ↓
InvoiceService
    ↓
InvoiceCalculationService
    ↓
ArcaInvoiceService
    ↓
API externa de facturación

API:

Crear o adaptar endpoints similares a:

GET /api/customers/search
GET /api/products/search
GET /api/products/barcode/{barcode}

POST /api/invoices/draft
POST /api/invoices/calculate
POST /api/invoices/authorize

GET /api/invoices/{id}
GET /api/invoices/{id}/pdf

Adaptar los endpoints a las rutas existentes si ya existen.

MODELOS:

Invoice:

- id
- company_id
- customer_id
- invoice_type
- invoice_type_code
- point_of_sale
- number
- issue_date
- subtotal
- discount
- net_amount
- iva_0
- iva_2_5
- iva_5
- iva_10_5
- iva_21
- iva_27
- other_taxes
- total
- payment_method
- cae
- cae_expiration
- status
- arca_request
- arca_response
- created_by
- created_at
- updated_at

InvoiceItem:

- id
- invoice_id
- product_id
- description
- code
- barcode
- quantity
- unit
- unit_price
- discount
- iva_rate
- iva_amount
- subtotal
- total

IMPORTANTE:

Aunque exista el producto en la tabla products, guardar en InvoiceItem:

- descripción
- código
- precio
- IVA

porque la factura histórica debe conservar exactamente los datos utilizados al momento de emitirla.

No depender exclusivamente del producto actual.

NUMERACIÓN:

No calcular el número en JavaScript.

No hacer:

ultimoNumero + 1

en frontend.

La numeración debe controlarse en backend.

Debe contemplar concurrencia de varios usuarios.

El punto de venta y tipo de comprobante deben formar parte de la identificación de la numeración.

STOCK:

No descontar stock simplemente al escanear.

El usuario puede cancelar la factura.

El movimiento definitivo de stock debe realizarse cuando la operación quede confirmada según las reglas del sistema.

Si la factura es autorizada:

Invoice AUTHORIZED
↓
Stock movement
↓
Descontar cantidades

ARCA:

NO implementar la conexión directa con ARCA.

Ya existe una API externa/intermedia.

El sistema debe llamar a esa API.

Ejemplo conceptual:

Mi sistema
    ↓
API de facturación existente
    ↓
ARCA

La respuesta esperada debe transformarse en algo simple para el frontend:

{
    success: true,
    invoice_number: "0002-00000125",
    cae: "75123456789012",
    cae_expiration: "20260901"
}

Si es rechazada:

{
    success: false,
    status: "REJECTED",
    code: "...",
    message: "..."
}

EMISIÓN:

Cuando el usuario presione:

"EMITIR FACTURA"

mostrar una confirmación simple:

¿Emitir factura?

Factura B
0002-00000125

Cliente: Juan Pérez
Total: $42.350

"Esta operación será autorizada electrónicamente."

Botones:

Cancelar
Emitir factura

Mientras se procesa:

Deshabilitar el botón.

Mostrar:

"Autorizando comprobante..."

No permitir doble click.

RESULTADO AUTORIZADO:

Mostrar modal:

✓ FACTURA EMITIDA

Factura B
0002-00000125

Total:
$42.350,00

CAE:
75123456789012

Vencimiento CAE:
01/09/2026

Botones:

Ver factura
Descargar PDF
Imprimir
Enviar por email
Enviar por WhatsApp
Nueva factura

RESULTADO RECHAZADO:

Mostrar:

❌ FACTURA NO AUTORIZADA

Código:
XXXX

Descripción:
XXXX

Botones:

Volver a editar
Cerrar

No marcar la factura como autorizada.

ARCA TIMEOUT:

Si la API no responde después de enviar:

No emitir nuevamente automáticamente.

Guardar estado UNKNOWN o PENDING_CONFIRMATION.

Mostrar:

"Estamos verificando el estado del comprobante. No vuelva a emitir hasta confirmar el resultado."

Esto es importante para evitar duplicados fiscales.

DISEÑO:

Utilizar Vue 3 + Vuetify.

Componentes separados y reutilizables.

La interfaz debe ser:

- Moderna
- Profesional
- Limpia
- Rápida
- Optimizada para escritorio
- Compatible con teclado
- Compatible con lector de código de barras
- Sin exceso de modales
- Sin formularios innecesarios

No quiero que el operador tenga que usar el mouse constantemente.

El campo de código de barras debe tener foco automáticamente al iniciar una nueva factura.

Después de cada producto agregado debe recuperar el foco.

PANEL DERECHO:

Mostrar:

Subtotal
Descuentos
Neto
IVA
Total

Estado ARCA:

🟢 ARCA conectado
WSFEv1
Punto de venta 0002
Última conexión

Acciones:

Vista previa
Imprimir
Enviar email
Enviar WhatsApp
EMITIR FACTURA

ATAJOS:

Mostrar discretamente:

F2 Buscar producto
F3 Cliente
DEL Eliminar
+/- Cantidad
F10 Emitir

NO hacer una interfaz saturada.

PRINCIPIO DE UX:

La operación normal debe poder hacerse así:

1. Seleccionar cliente.
2. El cursor queda automáticamente en código de barras.
3. Escanear producto.
4. Escanear producto.
5. Escanear producto.
6. Escanear producto.
7. Revisar total.
8. Seleccionar forma de pago.
9. F10.
10. Confirmar.
11. Emitir.

El operador no debería abrir ventanas entre producto y producto.

SEGURIDAD:

Nunca confiar en:

- precios enviados por frontend
- IVA enviado por frontend
- totales enviados por frontend
- descuentos sin validar
- stock enviado por frontend
- número de factura enviado por frontend
- condición fiscal enviada sin validar

El backend debe volver a consultar y validar:

- empresa
- usuario
- cliente
- producto
- precio
- IVA
- stock
- punto de venta
- numeración
- tipo de comprobante

El frontend solamente debe manejar la experiencia de usuario.

MULTIEMPRESA:

El sistema es multiempresa.

Todas las consultas deben respetar company_id/tenant correspondiente.

Nunca permitir que un usuario de una empresa pueda consultar productos, clientes o facturas de otra empresa.

AUDITORÍA:

Registrar:

- usuario
- fecha
- empresa
- creación
- modificación
- emisión
- rechazo
- cancelación
- reintentos

Guardar request/response de la API de facturación cuando corresponda para poder diagnosticar problemas.

PRIMERA ETAPA DE DESARROLLO:

Antes de programar toda la lógica fiscal, crear primero la interfaz completa con datos mock.

Debe quedar funcionando visualmente:

- Cliente
- Nuevo cliente
- Cliente rápido
- Código de barras
- Productos
- Producto repetido aumenta cantidad
- Búsqueda manual
- Cantidad
- Eliminar
- Descuento
- Totales
- Forma de pago
- Estado ARCA
- Botón emitir
- Modal de confirmación
- Resultado autorizado
- Resultado rechazado

Una vez aprobada la interfaz, conectar los componentes con Laravel y finalmente conectar la emisión con la API externa de facturación.

IMPORTANTE PARA LA IMPLEMENTACIÓN:

No modificar módulos existentes que no sean necesarios.

No romper componentes existentes.

Antes de crear nuevos modelos, rutas o tablas, revisar si ya existen equivalentes en el proyecto.

Reutilizar componentes, servicios, modelos y estilos existentes cuando sea posible.

Mantener separación clara entre:

1. UI
2. lógica de factura
3. cálculo
4. stock
5. clientes
6. productos
7. integración con API de facturación

El resultado final debe parecer un sistema comercial profesional y no un formulario administrativo.