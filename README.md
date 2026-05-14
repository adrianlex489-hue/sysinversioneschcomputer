# Sistema de Gestión para Botica — Botica 2026

**Tecnologías:** PHP 8.2 · MariaDB 10.4 · Bootstrap 4 · AdminLTE 3 · jQuery · FPDF · XAMPP  
**Entorno:** Servidor local Apache (XAMPP) · Base de datos: `bdbotica`  
**Zona horaria:** America/Lima (UTC-5, hora peruana)

---

## Descripción General

Botica 2026 es un sistema de gestión integral desarrollado para farmacias y boticas de pequeña y mediana escala. Permite administrar de forma centralizada las operaciones diarias: ventas al público, compras a proveedores, control de inventario por lotes, gestión de caja por turnos, emisión de comprobantes y administración de personas (clientes, proveedores y usuarios). El sistema implementa el patrón PRG (Post-Redirect-Get) en todos sus módulos para evitar el reenvío accidental de formularios, y aplica validaciones tanto en el lado del servidor (PHP) como en el cliente (JavaScript con SweetAlert2).

---

## Estructura del Proyecto

```
Botica-2026/
├── conf/                        # Configuración de base de datos y control de acceso
│   ├── database.php             # Conexión PDO con zona horaria Lima configurada
│   └── verificar_acceso.php     # Middleware de autenticación y roles
├── includes/                    # Componentes reutilizables
│   ├── header.php               # Cabecera HTML, navbar y estilos globales
│   ├── sidebar.php              # Menú lateral dinámico según rol
│   ├── footer.php               # Pie de página y scripts globales
│   ├── api_dni.php              # Clase APIDni — consulta RENIEC
│   └── api_ruc.php              # Clase APIRuc — consulta SUNAT
├── modules/
│   ├── Caja/                    # Gestión de caja por turnos
│   ├── catalogos/               # Productos, categorías y unidades
│   ├── Comprobantes/            # Empresa y generación de PDFs
│   ├── Inventario/              # Stock general
│   ├── Lotes/                   # Control de lotes y vencimientos
│   ├── personas/                # Usuarios, clientes y proveedores
│   └── transacciones/           # Ventas, compras e historiales
├── public/
│   ├── dashboard.php            # Panel de control principal
│   ├── login.php                # Autenticación de usuarios
│   ├── logout.php               # Cierre de sesión
│   └── assets/                  # Imágenes, logos y sonidos
├── libs/                        # Librería FPDF para generación de PDFs
└── bdbotica.sql                 # Script SQL de la base de datos
```

---

## Módulos del Sistema

---

### 1. Autenticación y Control de Acceso

**Archivo:** `public/login.php` · `conf/verificar_acceso.php`

El sistema implementa un control de acceso basado en roles. Existen tres roles definidos:

| Rol | ID | Descripción |
|-----|----|-------------|
| Administrador | 1 | Acceso total al sistema |
| Cajero | 2 | Gestión de caja y ventas |
| Trabajador | 3 | Ventas y consulta de catálogos |

Cada módulo verifica el rol del usuario mediante la función `verificar_acceso(array $roles_permitidos)`. Si el usuario no está autenticado, es redirigido al login. Si no tiene el rol requerido, es redirigido al dashboard con un mensaje de acceso denegado. Las contraseñas se almacenan con hash `bcrypt` mediante `password_hash()`.

---

### 2. Dashboard — Panel de Control

**Archivo:** `public/dashboard.php`

El panel de control es la pantalla principal del sistema tras iniciar sesión. Presenta información consolidada en tiempo real sobre el estado del negocio.

**Funcionalidades:**

- **Indicadores KPI (Key Performance Indicators):** Muestra tarjetas con los totales de productos activos, clientes registrados, proveedores activos, ventas realizadas, ingresos totales acumulados y lotes próximos a vencer. Los KPIs de ventas e ingresos son visibles únicamente para el rol Administrador.

- **Alertas farmacéuticas:** Si existen productos con stock por debajo del mínimo configurado o lotes que vencen en los próximos 90 días, el sistema muestra alertas visuales destacadas en la parte superior del panel y en la barra de navegación con contadores numéricos.

- **Gráfico de ventas:** Gráfico de líneas (Chart.js) que muestra la evolución de las ventas en los últimos 6 meses, con valores en soles peruanos. Visible solo para Administrador.

- **Gráfico de stock por categoría:** Gráfico de dona (doughnut) que distribuye el stock actual entre las categorías de productos registradas.

- **Últimas 5 ventas:** Tabla con las ventas más recientes, mostrando cliente, tipo de comprobante, monto total, fecha y estado (pagado, pendiente, anulado).

- **Lotes próximos a vencer:** Tabla con los 5 lotes más urgentes, indicando días restantes con código de color (amarillo: menos de 90 días, rojo: menos de 30 días).

- **Accesos rápidos:** Botones de acceso directo a los módulos más utilizados.

- **Sonido de bienvenida:** Al iniciar sesión por primera vez en la sesión, el sistema reproduce automáticamente un audio de bienvenida (`bienvenida.mp3`). Este sonido se reproduce una única vez por sesión gracias al control mediante `$_SESSION['bienvenida_reproducida']`.

---

### 3. Gestión de Caja por Turnos

**Archivos:** `modules/Caja/caja.php` · `modules/Caja/historial/historial_caja.php`

Este módulo implementa el control de caja diaria por turnos de trabajo, garantizando que todas las transacciones económicas queden asociadas a un turno específico y a un cajero responsable.

**Funcionalidades:**

#### 3.1 Apertura de Caja

Para iniciar operaciones, el cajero debe aperturar una caja indicando:
- **Turno de trabajo:** Mañana (☀️), Tarde (🌤️) o Noche (🌙).
- **Monto inicial (fondo de caja):** Dinero físico con el que inicia el turno.
- **Observación opcional:** Notas sobre el inicio del turno.

El sistema valida que no exista ya una caja abierta para el mismo usuario antes de permitir la apertura. Una vez aperturada, el estado de la caja queda en `abierta` y se registra la fecha y hora exacta de apertura con zona horaria de Lima.

**Restricción crítica:** Mientras no exista una caja abierta, el sistema bloquea completamente el registro de ventas y compras. Los formularios se muestran con opacidad reducida y los botones de acción (seleccionar cliente, seleccionar proveedor, agregar producto) disparan una alerta visual con sonido (`alerta.mp3`) indicando que la caja debe aperturarse primero, con un botón de acceso directo al módulo de caja.

#### 3.2 Movimientos de Caja

Durante el turno activo, el cajero puede registrar movimientos manuales adicionales:
- **Ingresos externos:** Fondos adicionales, préstamos del propietario, cobros varios.
- **Egresos externos:** Pagos de servicios, gastos operativos, movilidad, limpieza.

Cada movimiento registra: tipo (ingreso/egreso), descripción, monto, método de pago (efectivo, Yape, Plin, transferencia, tarjeta), fecha/hora y usuario responsable.

**Movimientos automáticos:** El sistema registra automáticamente en `movimientos_caja` los siguientes eventos:
- Venta al contado → ingreso con referencia a la venta.
- Abono de venta a crédito → ingreso con referencia a la venta.
- Compra al contado → egreso con referencia a la compra.
- Abono de compra a crédito → egreso con referencia a la compra.

#### 3.3 Resumen Financiero en Tiempo Real

El panel de caja activa muestra en tiempo real:
- Fondo inicial del turno.
- Total de ingresos acumulados.
- Total de egresos acumulados.
- **Saldo actual en caja** = Fondo inicial + Ingresos − Egresos.

#### 3.4 Cierre de Caja

Al finalizar el turno, el cajero realiza el cierre ingresando el monto físico contado en caja. El sistema calcula automáticamente:
- **Monto esperado** = Fondo inicial + Total ingresos − Total egresos.
- **Diferencia** = Monto contado − Monto esperado.
  - Diferencia positiva: sobrante de caja.
  - Diferencia negativa: faltante de caja.
  - Diferencia cero: cuadre exacto.

Todos estos valores quedan registrados en la base de datos para auditoría posterior.

#### 3.5 Historial de Cajas

El historial muestra todas las cajas registradas con filtros por estado (abierta/cerrada) y por turno (mañana/tarde/noche). Cada registro permite ver el detalle completo con todos los movimientos del turno. El Administrador puede ver las cajas de todos los usuarios; los demás roles solo ven las propias.

---

### 4. Gestión de Ventas

**Archivos:** `modules/transacciones/ventas.php` · `modules/transacciones/historial/historial_ventas.php`

Módulo para el registro de ventas al público con soporte para múltiples tipos de comprobante, modalidades de pago y gestión de crédito.

**Funcionalidades:**

#### 4.1 Registro de Nueva Venta

El formulario de nueva venta incluye:

- **Selección de cliente:** Modal de búsqueda con filtro en tiempo real por nombre completo o DNI. Incluye integración con la API de RENIEC para buscar clientes por DNI directamente desde el modal, con dos opciones: usar el cliente solo para la venta actual (sin guardar en el sistema) o guardarlo permanentemente en la base de datos de clientes.

- **Tipo de comprobante:** Ticket de venta o Nota de Venta. El número de comprobante se genera automáticamente con la serie configurada en la empresa (ej. T001-00000001) y se incrementa correlativamente por tipo.

- **Selección de productos:** Modal de búsqueda con filtro por nombre, código o laboratorio. Cada producto muestra precio de venta, stock disponible y categoría. Al seleccionar un producto, se abre un modal de detalle donde se configura cantidad, precio unitario y descuento por ítem.

- **Descuento global:** Campo para aplicar un descuento sobre el subtotal total de la venta.

- **IGV:** Opción de aplicar o no el IGV del 18% sobre la base imponible.

- **Tipo de pago:**
  - *Contado:* Se selecciona el método de pago (efectivo, Yape, Plin, transferencia, tarjeta). El stock se descuenta inmediatamente y se registra el movimiento en caja.
  - *Crédito:* Se configura el número de cuotas (1 a 4), la fecha de la primera cuota y la frecuencia en días. El sistema genera automáticamente el cronograma de cuotas.

#### 4.2 Control de Stock FEFO

El sistema aplica el método FEFO (First Expired, First Out — primero en vencer, primero en salir) para el descuento de stock. Al registrar una venta, el sistema selecciona automáticamente los lotes con fecha de vencimiento más próxima para descontar primero, garantizando la rotación correcta del inventario farmacéutico.

#### 4.3 Historial de Ventas

Tabla completa con todas las ventas registradas, con filtros rápidos por estado (pagadas, pendientes, anuladas). Funcionalidades disponibles:
- **Ver detalle:** Modal con información completa de la venta, productos, cronograma de cuotas y pagos realizados.
- **Registrar abono:** Para ventas a crédito con saldo pendiente, permite registrar pagos parciales o totales. Cada abono actualiza el saldo pendiente y el estado de las cuotas correspondientes, y genera automáticamente un movimiento de ingreso en la caja activa.
- **Anular venta:** Revierte el stock de todos los productos vendidos a sus lotes originales y marca la venta como anulada.
- **Imprimir comprobante:** Genera el PDF correspondiente según el tipo de comprobante.

---

### 5. Gestión de Compras

**Archivos:** `modules/transacciones/compras.php` · `modules/transacciones/historial/historial_compras.php`

Módulo para el registro de compras a proveedores con creación automática de lotes y actualización de stock.

**Funcionalidades:**

#### 5.1 Registro de Nueva Compra

- **Selección de proveedor:** Modal de búsqueda con filtro por nombre o RUC. Incluye integración con la API de SUNAT para buscar proveedores por RUC directamente desde el modal, con opción de guardar el proveedor en el sistema o usarlo solo para la compra actual.

- **Tipo de comprobante:** Ticket o Nota de Venta. Numeración automática correlativa por tipo.

- **Selección de productos con lotes:** Cada producto requiere el ingreso del código de lote del fabricante y la fecha de vencimiento. Si el lote ya existe en el sistema, se incrementa su stock; si es nuevo, se crea automáticamente.

- **IGV configurable:** Opción de aplicar o no el IGV del 18%.

- **Tipo de pago:** Contado (con método de pago) o crédito (con cronograma de cuotas de 1 a 4). Las compras al contado generan automáticamente un egreso en la caja activa.

#### 5.2 Historial de Compras

Funcionalidades equivalentes al historial de ventas: ver detalle, registrar abonos a crédito (que generan egresos en caja), anular compras (revierte el stock de los lotes) e imprimir comprobante.

---

### 6. Gestión de Inventario

**Archivos:** `modules/Inventario/inventario.php` · `modules/Lotes/lotes.php`

#### 6.1 Stock General

Vista consolidada del inventario con el stock actual de cada producto. Permite identificar rápidamente productos con stock bajo el mínimo configurado o sin stock disponible.

#### 6.2 Control de Lotes y Vencimientos

Gestión detallada de los lotes de cada producto con:
- Código de lote del fabricante.
- Fecha de vencimiento.
- Stock inicial y stock actual.
- Alertas visuales por proximidad de vencimiento: amarillo (menos de 90 días), rojo (menos de 30 días), crítico (vencido).

---

### 7. Gestión de Catálogos

**Archivos:** `modules/catalogos/productos.php` · `modules/catalogos/categorias.php` · `modules/catalogos/unidades.php`

#### 7.1 Productos

Catálogo completo de productos farmacéuticos con los siguientes atributos:
- Código interno y código de barras.
- Nombre, laboratorio, presentación y concentración.
- Categoría y unidad de medida.
- Proveedor predeterminado.
- Stock actual, stock mínimo y stock máximo.
- Precio de compra y precio de venta.
- Indicador de requerimiento de receta médica.
- Registro sanitario.
- Imágenes del producto (múltiples).
- Estado activo/inactivo.

#### 7.2 Categorías

Gestión de categorías de productos (Medicamentos, Suplementos, Productos Naturales, Cuidado Personal, Bebés, etc.) con activación/desactivación.

#### 7.3 Unidades de Medida

Gestión de unidades (Unidad, Caja, Blíster, Frasco, Pack, Tubo, etc.) con abreviatura y estado activo/inactivo.

---

### 8. Gestión de Personas

**Archivos:** `modules/personas/clientes.php` · `modules/personas/proveedores.php` · `modules/personas/usuarios.php`

#### 8.1 Clientes

Registro y gestión de clientes con:
- Nombres, apellido paterno y materno.
- Tipo de documento y número de documento.
- Teléfono, email y dirección.
- **Consulta automática a RENIEC:** Ingresando el DNI, el sistema consulta la API de RENIEC y completa automáticamente los datos del cliente (nombres, apellidos y dirección de domicilio).
- Estado activo/inactivo.

#### 8.2 Proveedores

Registro y gestión de proveedores con:
- Razón social y RUC.
- Teléfono, email, dirección y persona de contacto.
- **Consulta automática a SUNAT:** Ingresando el RUC, el sistema consulta la API de SUNAT y completa automáticamente la razón social y dirección fiscal.
- Estado activo/inactivo.

#### 8.3 Usuarios

Gestión de usuarios del sistema con asignación de roles (Administrador, Cajero, Trabajador). Las contraseñas se almacenan con hash bcrypt y pueden ser actualizadas por el Administrador.

---

### 9. Gestión de Comprobantes

**Archivos:** `modules/Comprobantes/empresa.php` · `modules/Comprobantes/comprobante_venta.php` · `modules/Comprobantes/comprobante_ticket.php` · `modules/Comprobantes/comprobante_compra.php`

#### 9.1 Configuración de Empresa

Panel de configuración de los datos de la empresa que aparecen en todos los comprobantes:
- Razón social y nombre comercial.
- RUC (11 dígitos).
- Dirección, distrito, provincia y departamento.
- Teléfono, email y sitio web.
- Logo de la empresa (con vista previa en tiempo real y soporte para cambio estacional).
- Porcentaje de IGV.
- Series de comprobantes: Ticket (T001) y Nota de Venta (N001).
- Pie de comprobante personalizable.

La interfaz incluye vista previa en tiempo real del comprobante mientras se editan los datos, y confirmación SweetAlert antes de guardar los cambios.

#### 9.2 Generación de PDFs

El sistema genera comprobantes en PDF utilizando la librería FPDF:

- **Ticket de venta (80mm):** Formato de impresora térmica. Incluye logo, datos de la empresa, datos del cliente, detalle de productos con precio unitario y subtotal, totales (subtotal, IGV, total), método de pago y pie de comprobante.

- **Nota de Venta A4:** Formato carta con cabecera profesional (logo + datos empresa + caja del comprobante), sección de datos del cliente, tabla de productos con columnas dinámicas (la columna de descuento solo aparece si algún ítem tiene descuento), totales y líneas de firma para receptor y emisor.

- **Ticket de compra (80mm):** Equivalente al ticket de venta pero con datos del proveedor y columna de lote.

- **Nota de Compra A4:** Equivalente a la nota de venta pero con datos del proveedor, columnas de lote y fecha de vencimiento.

El router `imprimir.php` detecta automáticamente el tipo de comprobante y redirige al PDF correspondiente (80mm para tickets, A4 para notas).

---

## Base de Datos

**Motor:** MariaDB 10.4 · **Charset:** utf8mb4 · **Collation:** utf8mb4_spanish_ci

### Tablas Principales

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema con roles y contraseñas bcrypt |
| `roles` | Roles del sistema (Administrador, Cajero, Trabajador) |
| `clientes` | Clientes registrados con datos de contacto |
| `proveedores` | Proveedores con RUC y datos comerciales |
| `categorias` | Categorías de productos |
| `unidades` | Unidades de medida |
| `productos` | Catálogo de productos farmacéuticos |
| `lotes` | Lotes de productos con fecha de vencimiento y stock |
| `ventas` | Cabecera de ventas (comprobante, totales, estado) |
| `detalle_venta` | Líneas de productos por venta |
| `cuotas_venta` | Cronograma de cuotas para ventas a crédito |
| `pagos_venta` | Abonos registrados para ventas a crédito |
| `compras` | Cabecera de compras (comprobante, totales, estado) |
| `detalle_compra` | Líneas de productos por compra con lote |
| `cuotas_compra` | Cronograma de cuotas para compras a crédito |
| `pagos_compra` | Abonos registrados para compras a crédito |
| `caja` | Registro de cajas por turno con montos y diferencias |
| `movimientos_caja` | Movimientos de ingreso/egreso por caja |
| `empresa` | Configuración de datos de la empresa |

### Relaciones Clave

- `ventas` → `clientes`, `usuarios`, `caja`
- `compras` → `proveedores`, `usuarios`, `caja`
- `detalle_venta` → `ventas`, `productos`, `lotes`
- `detalle_compra` → `compras`, `productos`, `lotes`
- `movimientos_caja` → `caja`, `usuarios`
- `productos` → `categorias`, `unidades`, `proveedores`
- `lotes` → `productos`

---

## Seguridad

- **Autenticación:** Sesiones PHP con verificación de rol en cada módulo.
- **Contraseñas:** Hash bcrypt con `password_hash()` / `password_verify()`.
- **Consultas SQL:** Todas las consultas utilizan PDO con sentencias preparadas y parámetros enlazados, previniendo inyección SQL.
- **Salida HTML:** Todos los datos de usuario se escapan con `htmlspecialchars()` antes de renderizarse.
- **Control de acceso:** La función `verificar_acceso()` verifica el rol en cada página antes de procesar cualquier lógica.
- **Zona horaria:** Configurada a `America/Lima` tanto en PHP (`date_default_timezone_set`) como en MySQL (`SET time_zone = '-05:00'`) para garantizar consistencia en todas las fechas.

---

## Instalación

1. Copiar la carpeta `Botica-2026` en `C:\xampp\htdocs\`.
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Abrir phpMyAdmin (`http://localhost/phpmyadmin`).
4. Crear la base de datos `bdbotica`.
5. Importar el archivo `bdbotica.sql`.
6. Importar el archivo `modules/Comprobantes/empresa.sql` y actualizar los datos de la empresa.
7. Acceder al sistema en `http://localhost/botica-2026/public/login.php`.

**Credenciales por defecto:**
- Usuario: `Adrian`
- Contraseña: `(configurada en el sistema)`

---

## Dependencias Externas

| Librería | Versión | Uso |
|----------|---------|-----|
| AdminLTE | 3.2.0 | Framework de interfaz administrativa |
| Bootstrap | 4.6.2 | Sistema de grillas y componentes UI |
| jQuery | 3.7.1 | Manipulación DOM y AJAX |
| Font Awesome | 6.5.2 | Iconografía |
| SweetAlert2 | 11 | Alertas y confirmaciones interactivas |
| Chart.js | 3.7.1 | Gráficos del dashboard |
| DataTables | 1.10.25 | Tablas con búsqueda y paginación |
| FPDF | 1.86 | Generación de PDFs (incluida en `/libs`) |
| miapi.cloud | — | API de consulta DNI (RENIEC) y RUC (SUNAT) |

---

*Documentación generada para el sistema Botica 2026 — Mayo 2026*
