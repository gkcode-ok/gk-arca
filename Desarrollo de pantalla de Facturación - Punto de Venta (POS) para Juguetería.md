# Desarrollo de pantalla de Facturación / Punto de Venta (POS) para Juguetería

Necesito desarrollar una pantalla completa de **Facturación / Punto de Venta (POS)** para una juguetería.

La aplicación está desarrollada con **Laravel + Vue.js + Vuetify + MySQL**.

El objetivo principal es que el operador pueda realizar una venta de manera **rápida, simple y segura**, utilizando principalmente teclado y lector de código de barras.

La pantalla debe estar preparada desde el principio para integrarse con:

- Gestión de clientes.
- Gestión de productos.
- Control de stock.
- Caja.
- Medios de pago.
- Facturación fiscal.
- ARCA.
- Notas de crédito.
- Impresión/PDF.
- Auditoría de operaciones.
- Múltiples sucursales en el futuro.

No quiero una pantalla de formulario tradicional. Quiero una interfaz tipo **POS / caja**, optimizada para operaciones rápidas.

---

# 1. Estructura general

La pantalla debe dividirse visualmente en:

1. Encabezado.
2. Datos del cliente.
3. Carga y listado de productos.
4. Resumen de la venta.
5. Medios de pago.
6. Acciones de facturación.
7. Información de estado.
8. Atajos de teclado.

La pantalla debe ser responsive, pero debe priorizar la experiencia de uso en computadora de escritorio con monitor de caja.

---

# 2. ENCABEZADO

En la parte superior mostrar:

- Título: "Nueva venta" o "Facturación".
- Usuario actualmente conectado.
- Caja actual.
- Sucursal.
- Fecha y hora.
- Estado de conexión con el servidor.
- Estado de conexión con ARCA cuando corresponda.

Ejemplo:

FACTURACIÓN

Caja: #03
Sucursal: Centro
Usuario: Juan
Fecha: 31/08/2026

Estado ARCA: 🟢 Conectado

Agregar botón:

[Nueva venta]

Si existe una venta en curso, preguntar antes de descartarla.

---

# 3. TIPO DE COMPROBANTE

Debe existir una sección para definir el tipo de comprobante.

Ejemplo:

Tipo de comprobante:
[ Factura B ▼ ]

Dependiendo de la configuración fiscal del comercio, permitir los comprobantes que correspondan.

Por ejemplo:

- Factura A
- Factura B
- Factura C
- Nota de crédito
- Nota de débito

IMPORTANTE:

No permitir que el usuario seleccione libremente cualquier comprobante si no corresponde fiscalmente.

El backend debe validar:

- Condición frente al IVA del comercio.
- Condición frente al IVA del cliente.
- Tipo de documento.
- CUIT/DNI.
- Punto de venta.
- Configuración fiscal.
- Entorno ARCA.

La interfaz solamente debe mostrar las opciones válidas.

---

# 4. PUNTO DE VENTA Y NUMERACIÓN

Mostrar información del comprobante:

Punto de venta: 0003
Tipo: Factura B
Número: 00000042

El número NO debe ser ingresado manualmente por el operador.

La numeración debe ser administrada por el backend y validada contra ARCA cuando corresponda.

Después de autorizar el comprobante se deben guardar:

- Tipo de comprobante.
- Punto de venta.
- Número.
- CAE.
- Fecha de vencimiento del CAE.
- Fecha de emisión.
- Resultado de autorización.
- Código de error si ARCA rechaza el comprobante.

---

# 5. CLIENTE

La sección cliente debe estar diseñada para ser extremadamente rápida.

Por defecto seleccionar:

Consumidor Final

Mostrar:

Cliente:
[ Consumidor Final ▼ ]

Botones:

[Buscar cliente]
[Nuevo cliente]

El buscador debe permitir buscar por:

- DNI.
- CUIT.
- Nombre.
- Apellido.
- Razón social.
- Teléfono.
- Código interno.

No obligar al usuario a abandonar la pantalla de facturación para crear un cliente.

Al hacer clic en "Nuevo cliente", abrir un diálogo/modal.

Campos:

- Tipo de persona.
- Nombre / Razón social.
- DNI / CUIT.
- Condición frente al IVA.
- Teléfono.
- Email.
- Dirección.
- Localidad.
- Provincia.

Botones:

[Cancelar]
[Guardar cliente]

Después de guardar el cliente, debe quedar automáticamente seleccionado en la venta.

---

# 6. CONSUMIDOR FINAL

Si el cliente es Consumidor Final, no exigir todos los datos de un cliente registrado.

La aplicación debe manejar correctamente los datos mínimos necesarios según las reglas fiscales vigentes.

No obligar al cajero a completar información innecesaria.

---

# 7. BUSCADOR DE PRODUCTOS

Esta es una de las partes más importantes.

El buscador debe estar siempre disponible y tener el foco cuando corresponda.

Campo:

[🔍 Escanear código / buscar producto...]

Debe permitir:

- Código de barras.
- SKU.
- Código interno.
- Nombre.
- Descripción.

Debe soportar lectores de código de barras USB que funcionan como teclado.

Cuando el lector envía un código:

1. Detectar el código.
2. Buscar el producto.
3. Si existe, agregarlo automáticamente.
4. Si ya existe en la venta, incrementar la cantidad.
5. Actualizar subtotal.
6. Actualizar total.
7. Actualizar stock disponible.

El operador no debería tener que hacer clic después de escanear un producto.

---

# 8. PRODUCTO ENCONTRADO

Cuando se encuentra un producto mostrar:

- Código.
- Nombre.
- SKU.
- Precio.
- Stock disponible.
- Unidad.
- Descuento aplicable.

Ejemplo:

Barbie Casa de Ensueño

Código: 7791234567890
SKU: BAR-001
Stock: 8
Precio: $45.000

Si el producto tiene stock insuficiente, mostrar una advertencia.

No permitir vender más unidades que el stock disponible, salvo que exista una configuración explícita de venta sin stock.

---

# 9. PRODUCTOS SIN CÓDIGO DE BARRAS

Debe existir siempre una alternativa para agregar productos manualmente.

Botón:

[Buscar producto]

Al hacer clic, abrir un buscador/modal con:

- Código.
- SKU.
- Nombre.
- Categoría.
- Precio.
- Stock.

Permitir seleccionar el producto.

---

# 10. LISTADO DE PRODUCTOS DE LA VENTA

Mostrar una tabla.

Columnas recomendadas:

- Código.
- Producto.
- Cantidad.
- Precio unitario.
- Descuento.
- Subtotal.
- Acciones.

Ejemplo:

Código | Producto | Cantidad | Precio | Descuento | Total

779123 | Barbie Casa | 1 | $45.000 | $0 | $45.000
779456 | Hot Wheels | 2 | $8.000 | $0 | $16.000
779789 | Lego | 1 | $35.000 | $5.000 | $30.000

Cada línea debe permitir:

- Incrementar cantidad.
- Disminuir cantidad.
- Editar cantidad.
- Aplicar descuento.
- Eliminar producto.

Utilizar botones claros y grandes para las acciones.

---

# 11. CANTIDAD

La cantidad debe poder modificarse:

- Con botones + y -.
- Escribiendo directamente.
- Desde teclado.

Ejemplo:

[-] 2 [+]

Cuando cambia la cantidad:

1. Recalcular subtotal.
2. Recalcular descuento.
3. Recalcular total.
4. Validar stock.

---

# 12. DESCUENTOS

Debe soportar descuentos por producto y descuentos generales.

Descuento por producto:

Producto: Barbie
Precio: $45.000
Descuento: 10%
Total: $40.500

También permitir descuento global:

Subtotal: $100.000
Descuento: $10.000
Total: $90.000

El descuento puede ser:

- Porcentaje.
- Importe fijo.

El sistema debe validar permisos.

Por ejemplo:

Cajero:
- Hasta 5%.

Supervisor:
- Hasta 20%.

Administrador:
- Sin límite o según configuración.

Guardar quién aplicó el descuento.

---

# 13. RESUMEN DE LA VENTA

En un panel lateral mostrar siempre:

SUBTOTAL
$100.000

DESCUENTO
-$10.000

TOTAL
$90.000

El total debe ser visualmente muy destacado.

No utilizar tamaños pequeños.

---

# 14. MEDIOS DE PAGO

La venta debe soportar uno o varios medios de pago.

NO asumir que una factura tiene solamente un medio de pago.

Ejemplo:

TOTAL:
$100.000

Pagos:

Efectivo: $40.000
Mercado Pago: $60.000

Total pagado:
$100.000

Estado:

🟢 Pago completo

Medios posibles:

- Efectivo.
- Tarjeta de débito.
- Tarjeta de crédito.
- Mercado Pago.
- Transferencia.
- Otro medio configurado.

Los medios deben ser configurables desde administración.

---

# 15. PAGO EN EFECTIVO

Cuando el usuario selecciona efectivo:

Total:
$45.000

Importe recibido:
[ $50.000 ]

Calcular automáticamente:

Vuelto:
$5.000

Agregar botones rápidos:

[Exacto]
[$10.000]
[$20.000]
[$50.000]
[$100.000]

El sistema debe impedir confirmar si el importe recibido es inferior al importe requerido, salvo que exista otro medio de pago.

---

# 16. PAGOS MIXTOS

Ejemplo:

Total factura:
$150.000

Pago:

Efectivo:
$50.000

Mercado Pago:
$100.000

Total pagado:
$150.000

Estado:

🟢 COMPLETO

Si falta dinero:

Total:
$150.000

Pagado:
$100.000

Pendiente:
$50.000

Estado:

🟡 PENDIENTE

No permitir finalizar la venta mientras el total pagado sea menor al total requerido, salvo que el negocio permita ventas a crédito.

---

# 17. VENTA A CUENTA / CRÉDITO

Dejar preparada la arquitectura para que posteriormente se pueda permitir:

Cliente:
Juan Pérez

Total:
$100.000

Pago:
$30.000

Saldo:
$70.000

Estado:
🟡 Cuenta corriente

Esta funcionalidad debe depender de permisos y configuración del comercio.

---

# 18. MERCADO PAGO

La arquitectura debe permitir integrar Mercado Pago.

El flujo ideal:

1. Seleccionar Mercado Pago.
2. Generar solicitud de pago.
3. Mostrar estado:

🟡 Esperando pago...

4. Recibir confirmación.
5. Mostrar:

🟢 Pago aprobado

Guardar:

- ID de operación.
- Medio de pago.
- Importe.
- Estado.
- Fecha.
- Datos necesarios para conciliación.

IMPORTANTE:

No mezclar conceptualmente el pago con la factura.

Pago y comprobante fiscal deben ser entidades relacionadas pero independientes.

---

# 19. RESUMEN FINAL

Antes de facturar mostrar claramente:

CLIENTE
Consumidor Final

PRODUCTOS
3 productos
4 unidades

SUBTOTAL
$100.000

DESCUENTO
-$10.000

TOTAL
$90.000

PAGO
Efectivo $90.000

VUELTO
$0

---

# 20. BOTÓN PRINCIPAL

El botón principal debe ser:

[ FACTURAR $90.000 ]

Debe mostrar el importe.

No utilizar simplemente:

[Guardar]

o

[Enviar]

El usuario debe saber exactamente qué va a hacer.

---

# 21. PROCESO DE FACTURACIÓN

Al hacer clic en FACTURAR:

1. Validar cliente.
2. Validar productos.
3. Validar cantidades.
4. Validar stock.
5. Validar precios.
6. Validar descuentos.
7. Validar medios de pago.
8. Validar total.
9. Crear la operación.
10. Registrar los pagos.
11. Solicitar autorización fiscal cuando corresponda.
12. Obtener CAE.
13. Guardar comprobante.
14. Actualizar stock.
15. Registrar movimiento de caja.
16. Cambiar estado de la venta.
17. Mostrar resultado.

El proceso debe ser transaccional.

Si una operación falla, no debe quedar información inconsistente.

---

# 22. IMPORTANTE: ARCA

La pantalla debe estar preparada para trabajar con ARCA.

No realizar lógica fiscal exclusivamente en Vue.

Toda operación fiscal debe ser validada y ejecutada en Laravel/backend.

El backend debe encargarse de:

- Tipo de comprobante.
- Punto de venta.
- Numeración.
- Datos del cliente.
- Importes.
- Impuestos.
- Solicitud de CAE.
- Respuesta de ARCA.
- CAE.
- Vencimiento.
- Errores.
- Reintentos.

La interfaz debe mostrar claramente el resultado.

Ejemplo exitoso:

🟢 FACTURA AUTORIZADA

Factura B
0003-00000125

CAE:
12345678901234

Vencimiento:
10/09/2026

---

# 23. ERROR DE ARCA

Si ARCA rechaza el comprobante, NO borrar la venta.

Mostrar:

🔴 No fue posible autorizar el comprobante.

Motivo:
[Mensaje devuelto por ARCA]

Botones:

[Reintentar autorización]
[Guardar como pendiente]
[Cancelar]

La venta debe conservarse para poder investigar o reintentar.

---

# 24. ESTADOS DE LA VENTA

Implementar estados claros.

Por ejemplo:

BORRADOR

PENDIENTE_PAGO

PAGO_CONFIRMADO

PENDIENTE_AUTORIZACION

AUTORIZADA

RECHAZADA

ANULADA

ERROR

El sistema debe evitar estados imposibles.

---

# 25. STOCK

Una venta confirmada debe generar movimientos de stock.

Ejemplo:

Producto:
Barbie Casa

Stock anterior:
10

Cantidad vendida:
2

Stock nuevo:
8

Registrar movimiento:

Tipo:
VENTA

Referencia:
Factura B 0003-00000125

Cantidad:
-2

Fecha:
31/08/2026

Usuario:
Juan

Sucursal:
Centro

IMPORTANTE:

No descontar stock simplemente porque el producto fue agregado temporalmente al carrito.

El descuento definitivo debe ocurrir al confirmar la operación según las reglas del sistema.

---

# 26. CAJA

La venta debe estar relacionada con la caja abierta.

Ejemplo:

Caja:
#03

Apertura:
$50.000

Venta:
$90.000

Medio:
Efectivo

Movimiento de caja:
+$90.000

Esto permitirá posteriormente realizar:

- Apertura de caja.
- Movimientos.
- Retiros.
- Ingresos.
- Cierre.
- Arqueo.
- Diferencias.

---

# 27. IMPRESIÓN

Después de facturar correctamente mostrar:

[Imprimir comprobante]

[Descargar PDF]

[Enviar por email]

[Enviar por WhatsApp]

[Nueva venta]

Si existe comprobante electrónico, mostrar los datos fiscales correspondientes.

---

# 28. COMPROBANTE SIN PRECIOS

Preparar la arquitectura para poder generar un comprobante de regalo.

Ejemplo:

COMPROBANTE DE REGALO

Barbie Casa
Hot Wheels
Lego

Sin mostrar:

- Precio.
- Descuento.
- Total.

Esto puede ser especialmente útil para una juguetería.

---

# 29. NOTAS DE CRÉDITO

No permitir eliminar una factura fiscal autorizada.

Para devolver/anular una operación utilizar el mecanismo correspondiente, por ejemplo:

Factura
↓
Nota de crédito

La nota de crédito debe mantener relación con el comprobante original.

Guardar:

- Comprobante original.
- Motivo.
- Importe.
- Usuario.
- Fecha.
- Nuevo comprobante.
- CAE.

---

# 30. PERMISOS

Implementar permisos por usuario.

Ejemplo:

CAJERO:

- Crear venta.
- Cobrar.
- Buscar cliente.
- Crear cliente.
- Consultar productos.
- Consultar stock.

SUPERVISOR:

- Aplicar descuentos especiales.
- Anular ventas.
- Realizar devoluciones.
- Autorizar operaciones especiales.

ADMINISTRADOR:

- Configuración fiscal.
- ARCA.
- Puntos de venta.
- Usuarios.
- Medios de pago.
- Parámetros de caja.
- Productos.
- Precios.

---

# 31. ATAJOS DE TECLADO

Implementar atajos para acelerar la operación.

Ejemplo:

F2
Buscar producto.

F3
Buscar cliente.

F4
Seleccionar medio de pago.

F8
Aplicar descuento.

F10
Facturar.

ESC
Cerrar modal/cancelar acción.

DELETE
Eliminar producto seleccionado.

+
Incrementar cantidad.

-
Disminuir cantidad.

CTRL + ENTER
Confirmar facturación.

Los atajos deben funcionar sin interferir con los campos de texto.

---

# 32. FOCO AUTOMÁTICO

Después de agregar un producto, el foco debe regresar automáticamente al buscador de productos.

Esto es muy importante cuando se utiliza un lector de códigos de barras.

Flujo:

Escanear producto
↓
Producto agregado
↓
Foco vuelve al buscador
↓
Escanear siguiente producto

El cajero debería poder cargar 20 productos consecutivos sin tocar el mouse.

---

# 33. PRODUCTO REPETIDO

Si se escanea dos veces el mismo código:

NO crear dos líneas.

Incrementar cantidad.

Ejemplo:

Primer escaneo:

Barbie
Cantidad: 1

Segundo escaneo:

Barbie
Cantidad: 2

Tercer escaneo:

Barbie
Cantidad: 3

---

# 34. CONFIRMACIÓN DE ACCIONES PELIGROSAS

Pedir confirmación para:

- Cancelar una venta con productos cargados.
- Eliminar todos los productos.
- Aplicar descuentos importantes.
- Anular una operación.
- Cambiar cliente después de determinadas condiciones.
- Cambiar precio manualmente.

No pedir confirmación para acciones normales que hagan lenta la operación.

---

# 35. PRECIOS

El precio debe venir desde el backend.

No confiar en el precio enviado por Vue.

El backend debe recalcular y validar:

- Precio.
- Descuento.
- Subtotal.
- Impuestos.
- Total.

Esto evita manipulación desde el navegador.

---

# 36. SEGURIDAD

Nunca confiar en los valores enviados por frontend.

Laravel debe validar nuevamente:

- Producto.
- Precio.
- Stock.
- Cliente.
- Descuento.
- Usuario.
- Caja.
- Sucursal.
- Medio de pago.
- Totales.

El frontend sirve para experiencia de usuario.

El backend es la autoridad.

---

# 37. AUDITORÍA

Guardar información de operaciones importantes.

Por ejemplo:

- Usuario que creó la venta.
- Usuario que modificó precio.
- Usuario que aplicó descuento.
- Usuario que anuló.
- Fecha.
- Hora.
- IP cuando corresponda.
- Caja.
- Sucursal.

Esto es importante especialmente para operaciones administrativas.

---

# 38. ESTRUCTURA CONCEPTUAL DE BASE DE DATOS

Preparar la arquitectura para entidades similares a:

ventas

venta_detalles

venta_pagos

clientes

productos

movimientos_stock

cajas

movimientos_caja

comprobantes

comprobante_detalles

notas_credito

auditoria

La estructura exacta debe adaptarse a las tablas existentes del proyecto.

NO crear tablas duplicadas si ya existen modelos equivalentes.

Antes de modificar la base de datos, revisar la estructura actual del proyecto.

---

# 39. RELACIÓN ENTRE ENTIDADES

Conceptualmente:

VENTA
│
├── CLIENTE
│
├── DETALLES
│   ├── PRODUCTO
│   ├── CANTIDAD
│   ├── PRECIO
│   └── DESCUENTO
│
├── PAGOS
│   ├── EFECTIVO
│   ├── TARJETA
│   └── MERCADO PAGO
│
├── COMPROBANTE
│   ├── TIPO
│   ├── PUNTO DE VENTA
│   ├── NUMERO
│   ├── CAE
│   └── VENCIMIENTO
│
├── MOVIMIENTOS STOCK
│
└── MOVIMIENTOS CAJA

---

# 40. UX GENERAL

La interfaz debe seguir estas reglas:

- Priorizar velocidad.
- Evitar ventanas innecesarias.
- Evitar formularios largos.
- Utilizar modales para altas rápidas.
- Utilizar autocompletados.
- Soportar lector de código de barras.
- Utilizar teclado.
- Mostrar claramente errores.
- Mostrar claramente el total.
- Mostrar estados con colores/íconos.
- No saturar visualmente.
- Mantener las acciones principales siempre visibles.

La pantalla debe sentirse como una caja registradora moderna, no como un formulario administrativo.

---

# 41. DISEÑO VISUAL PROPUESTO

Utilizar Vuetify.

Distribución:

┌───────────────────────────────────────────────────────────────┐
│ FACTURACIÓN                         Caja #03 | Usuario | ARCA │
├───────────────────────────────────────────────────────────────┤
│ CLIENTE                                                       │
│ [Consumidor Final ▼] [Buscar cliente] [+ Nuevo]              │
├───────────────────────────────────────┬───────────────────────┤
│ PRODUCTOS                              │ RESUMEN               │
│                                       │                       │
│ 🔍 Código / nombre / SKU              │ Subtotal    $100.000 │
│                                       │ Descuento    -$10.000 │
│ Barbie Casa       1    $45.000        │ ──────────────────── │
│ Hot Wheels        2    $16.000        │ TOTAL        $90.000 │
│ Lego              1    $35.000        │                       │
│                                       │ MEDIO DE PAGO         │
│                                       │ [Efectivo ▼]          │
│                                       │                       │
│                                       │ Recibido: $100.000    │
│                                       │ Vuelto:    $10.000    │
│                                       │                       │
│                                       │ [ FACTURAR $90.000 ]  │
├───────────────────────────────────────┴───────────────────────┤
│ F2 Producto | F3 Cliente | F4 Pago | F8 Descuento | F10 OK   │
└───────────────────────────────────────────────────────────────┘

El panel de resumen debe permanecer visible mientras se agregan productos.

En resoluciones pequeñas puede pasar a una sección inferior, pero en escritorio debe permanecer lateral.

---

# 42. COMPONENTES VUE

Separar la interfaz en componentes para evitar un componente gigante.

Propuesta:

InvoicePage.vue

InvoiceHeader.vue

CustomerSelector.vue

ProductSearch.vue

InvoiceProductTable.vue

InvoiceSummary.vue

PaymentSelector.vue

PaymentList.vue

QuickCustomerDialog.vue

QuickProductDialog.vue

InvoiceConfirmationDialog.vue

InvoiceResultDialog.vue

FiscalStatus.vue

KeyboardShortcuts.vue

La estructura exacta puede adaptarse al proyecto existente.

---

# 43. API BACKEND

Separar las responsabilidades.

Ejemplos conceptuales:

GET
/products/search

GET
/customers/search

POST
/customers

POST
/sales

POST
/sales/{id}/payments

POST
/sales/{id}/authorize

GET
/sales/{id}

POST
/sales/{id}/cancel

POST
/sales/{id}/credit-note

No implementar estos endpoints a ciegas.

Primero revisar las rutas, controladores, modelos y servicios existentes para reutilizar la arquitectura actual.

---

# 44. TRANSACCIÓN DE VENTA

El proceso final debe utilizar una transacción de base de datos.

Conceptualmente:

DB::transaction()

Dentro:

1. Validar venta.
2. Validar stock.
3. Crear venta.
4. Crear detalles.
5. Registrar pagos.
6. Actualizar stock.
7. Registrar caja.
8. Crear comprobante.

La comunicación con ARCA debe diseñarse cuidadosamente para evitar inconsistencias entre una transacción de base de datos y una operación externa.

No dejar la base de datos bloqueada mientras se realizan operaciones externas innecesariamente largas.

---

# 45. MANEJO DE ERRORES

Los errores deben ser comprensibles para el usuario.

NO mostrar:

"SQLSTATE[23000]..."

Mostrar:

"El producto Barbie Casa no tiene stock suficiente."

NO mostrar:

"Error 500."

Mostrar:

"No fue posible completar la venta. La operación no fue confirmada."

Registrar el detalle técnico en logs.

---

# 46. ESTADOS VISUALES

Utilizar estados claros:

🟢 Operación exitosa.

🟡 Operación pendiente.

🔴 Error.

Ejemplos:

🟢 ARCA conectado

🟢 Pago aprobado

🟡 Esperando pago

🟡 Pendiente de autorización

🔴 ARCA rechazó el comprobante

---

# 47. RENDIMIENTO

La búsqueda de productos debe ser rápida.

No cargar todos los productos de la base de datos al frontend.

Utilizar búsqueda mediante API y paginación/autocomplete.

Por ejemplo:

El usuario escribe:

"barb"

El frontend consulta:

/products/search?q=barb

El backend devuelve solamente los resultados necesarios.

Para códigos de barras, la búsqueda debe ser directa y rápida.

---

# 48. REGLA IMPORTANTE PARA EL DESARROLLO

Antes de comenzar a crear código:

1. Revisar la estructura actual del proyecto.
2. Revisar modelos existentes.
3. Revisar migraciones.
4. Revisar rutas.
5. Revisar controladores.
6. Revisar servicios.
7. Revisar componentes Vue existentes.
8. Revisar sistema actual de usuarios/permisos.
9. Revisar sistema de stock.
10. Revisar sistema de caja.
11. Revisar configuración fiscal/ARCA.

No duplicar funcionalidades existentes.

Reutilizar modelos, servicios y componentes cuando sea posible.

---

# 49. PRIORIDAD DE DESARROLLO

Implementar en este orden:

FASE 1
Interfaz POS.

FASE 2
Búsqueda y carga de productos.

FASE 3
Clientes.

FASE 4
Cálculo de totales y descuentos.

FASE 5
Medios de pago.

FASE 6
Registro de venta.

FASE 7
Actualización de stock.

FASE 8
Caja.

FASE 9
Facturación fiscal.

FASE 10
Integración ARCA.

FASE 11
Impresión/PDF.

FASE 12
Notas de crédito.

FASE 13
Auditoría y permisos avanzados.

---

# 50. CRITERIO FINAL

La pantalla debe cumplir este flujo:

ABRIR CAJA

↓

NUEVA VENTA

↓

SELECCIONAR CLIENTE
(o Consumidor Final)

↓

ESCANEAR PRODUCTO

↓

ESCANEAR PRODUCTO

↓

ESCANEAR PRODUCTO

↓

CALCULAR TOTAL

↓

SELECCIONAR MEDIO DE PAGO

↓

CONFIRMAR PAGO

↓

VALIDAR STOCK

↓

REGISTRAR VENTA

↓

REGISTRAR CAJA

↓

ACTUALIZAR STOCK

↓

AUTORIZAR COMPROBANTE EN ARCA

↓

OBTENER CAE

↓

MOSTRAR FACTURA

↓

IMPRIMIR / PDF / EMAIL / WHATSAPP

↓

NUEVA VENTA

La experiencia debe ser rápida y prácticamente continua.

El operador debería poder realizar una venta normal utilizando casi exclusivamente:

lector de código de barras + teclado.

El mouse debe ser opcional para las operaciones habituales.