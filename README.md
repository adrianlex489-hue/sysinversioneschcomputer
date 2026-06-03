# SysInversiones CH Computer

Sistema de gestión integral para **Inversiones CH Computer SRL** — empresa de servicio técnico y venta de equipos tecnológicos ubicada en Chiclayo, Lambayeque, Perú.

---

## Tabla de Contenidos

- [Descripción General](#descripción-general)
- [Tecnologías Utilizadas](#tecnologías-utilizadas)
- [Requisitos del Sistema](#requisitos-del-sistema)
- [Instalación](#instalación)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Módulos del Sistema](#módulos-del-sistema)
- [Roles y Permisos](#roles-y-permisos)
- [Base de Datos](#base-de-datos)
- [Configuración](#configuración)
- [Acceso al Sistema](#acceso-al-sistema)

---

## Descripción General

SysInversiones CH Computer es una aplicación web desarrollada en PHP con MySQL que centraliza todas las operaciones del negocio:

- Registro y seguimiento de órdenes de servicio técnico
- Gestión de ventas y compras con soporte de pago al contado y a crédito
- Control de inventario con alertas de stock mínimo
- Administración de caja con apertura, cierre y movimientos diarios
- Gestión de clientes (personas naturales y empresas) y proveedores
- Catálogo de productos, categorías y servicios técnicos
- Comprobantes imprimibles (tickets, notas de venta, notas de compra)
- Auditoría completa de acciones del sistema
- Dashboard con métricas y gráficos en tiempo real

---

## Tecnologías Utilizadas

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.2 |
| Base de datos | MariaDB 10.4 / MySQL |
| Acceso BD | PDO (prepared statements) |
| Frontend | AdminLTE 3.2, Bootstrap 4 |
| Iconos | Font Awesome 6.5 |
| Tipografía | Google Fonts — Inter |
| Gráficos | Chart.js |
| Alertas | SweetAlert2 |
| Generación PDF | FPDF |
| Servidor local | XAMPP (Apache + MariaDB) |

---

## Requisitos del Sistema

- PHP 8.0 o superior
- MariaDB 10.4+ o MySQL 8.0+
- Servidor web Apache (XAMPP recomendado para desarrollo)
- Extensión PDO habilitada en PHP
- Extensión `pdo_mysql` habilitada en PHP
- Extensión `mbstring` habilitada en PHP

---

## Instalación

### 1. Clonar o copiar el proyecto

Colocar la carpeta del proyecto en el directorio raíz de XAMPP:

```
C:\xampp\htdocs\sysinversioneschcomputer\
```

### 2. Importar la base de datos

1. Iniciar Apache y MySQL desde el panel de XAMPP.
2. Abrir **phpMyAdmin** en `http://localhost/phpmyadmin`.
3. Crear una base de datos llamada `bdinversioneschcomputer`.
4. Importar el archivo:

```
bdinversioneschcomputer.sql
```

### 3. Configurar la conexión

Editar el archivo `conf/database.php` con los datos de tu entorno:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bdinversioneschcomputer');
define('DB_USER', 'root');
define('DB_PASS', '');
```

La zona horaria está configurada para **Perú (UTC-5)**. Si se despliega en otro servidor, ajustar:

```php
date_default_timezone_set('America/Lima');
```

### 4. Acceder al sistema

Abrir el navegador y navegar a:

```
http://localhost/sysinversioneschcomputer/
```

El sistema redirige automáticamente al login si no hay sesión activa.

---

## Estructura del Proyecto

```
sysinversioneschcomputer/
│
├── index.php                       # Punto de entrada (redirige a login o dashboard)
├── bdinversioneschcomputer.sql     # Script completo de la base de datos
│
├── conf/                           # Configuración global
│   ├── database.php                # Conexión PDO a MySQL
│   ├── permisos.php                # Catálogo de módulos y helpers de permisos
│   ├── verificar_acceso.php        # Verificación de sesión activa
│   ├── auditoria.php               # Helper para registrar eventos de auditoría
│   └── ajax_verificar_admin.php    # Verificación de rol administrador vía AJAX
│
├── includes/                       # Componentes reutilizables
│   ├── header.php                  # Cabecera HTML común
│   ├── footer.php                  # Pie de página común
│   ├── sidebar.php                 # Menú lateral dinámico (por rol y permisos)
│   ├── api_dni.php                 # Consulta API de DNI
│   └── api_ruc.php                 # Consulta API de RUC
│
├── public/                         # Páginas accesibles desde el navegador
│   ├── login.php                   # Formulario de inicio de sesión
│   ├── logout.php                  # Cierre de sesión
│   ├── dashboard.php               # Panel de control principal
│   ├── assets/                     # Recursos estáticos globales
│   ├── css/                        # Hojas de estilo globales
│   └── uploads/                    # Archivos subidos (imágenes, etc.)
│
├── modules/                        # Módulos funcionales del sistema
│   ├── servicios/                  # Servicio técnico
│   ├── Caja/                       # Gestión de caja
│   ├── transacciones/              # Ventas y compras
│   ├── Inventario/                 # Control de stock
│   ├── catalogos/                  # Productos, categorías y servicios
│   ├── personas/                   # Clientes, proveedores y usuarios
│   ├── Comprobantes/               # Generación de documentos
│   ├── configuracion_empresa/      # Datos de la empresa
│   └── auditoria/                  # Registro de auditoría
│
├── libs/                           # Librería FPDF para generación de PDFs
│   ├── fpdf.php
│   └── font/
│
├── Logo/                           # Logo de la empresa
└── uploads/                        # Archivos subidos al sistema
```

---

## Módulos del Sistema

### Dashboard

Página principal del sistema tras iniciar sesión. Muestra:

- Indicadores clave: total de productos, clientes, ventas, órdenes de servicio.
- Estado de la caja activa y saldo actual.
- Alertas de stock bajo y órdenes en proceso.
- Gráfico comparativo de ventas vs. compras de los últimos 6 meses.
- Distribución de productos por categoría.
- Listado de últimas ventas y últimas órdenes de servicio.
- Productos con stock bajo el mínimo.

---

### Servicio Técnico

Gestión completa del taller de reparaciones.

**Recepción de Equipos** (`modules/servicios/recepcion.php`)
- Registro de equipos ingresados al taller (laptops, PCs, tablets, celulares, etc.).
- Búsqueda de cliente por DNI o RUC con integración a APIs externas.
- Registro del equipo con descripción del problema, accesorios y prioridad.
- Generación de orden de trabajo en PDF.

**Taller Técnico** (`modules/servicios/taller.php`)
- Vista tipo kanban del estado de las órdenes: Recibido → Diagnóstico → En Proceso → Listo → Entregado.
- Carga de fotos del equipo durante la reparación.
- Registro de cotizaciones y repuestos utilizados.
- Historial de cambios de estado por orden.

**Cobro de Servicios** (`modules/servicios/cobro_servicio.php`)
- Cobro de órdenes con estado "Listo".
- Soporte de pago al contado o a crédito.
- Registro automático en caja activa.

---

### Caja

Control financiero diario.

**Gestión de Caja** (`modules/Caja/caja.php`)
- Apertura de caja con monto inicial.
- Registro de movimientos manuales de ingreso y egreso.
- Cierre de caja con conteo físico del efectivo y cálculo de diferencias.

**Resumen del Día** (`modules/Caja/resumen_caja.php`)
- Vista consolidada de todos los movimientos del día actual.
- Totales por tipo de movimiento (ventas, cobros, servicios, manuales).

**Movimientos** (`modules/Caja/movimientos_caja.php`)
- Listado detallado de todos los movimientos de la caja activa.

**Historial de Cajas** (`modules/Caja/historial_caja.php`)
- Registro de todas las cajas abiertas y cerradas históricamente.

**Reporte de Cajas** (`modules/Caja/reporte_cajas.php`)
- Reporte exportable a PDF con detalle de una caja seleccionada.

---

### Ventas

**Nueva Venta** (`modules/transacciones/ventas.php`)
- Búsqueda de cliente por DNI (persona natural) o RUC (empresa).
- Selección de productos con stock disponible.
- Aplicación de descuentos por ítem o en el total.
- Tipos de comprobante: Ticket, Nota de Venta.
- Métodos de pago: contado o crédito (con registro de cuotas).
- Registro automático del movimiento en caja activa.
- Generación de comprobante imprimible.

**Cobro de Créditos** (`modules/transacciones/cobro_ventas.php`)
- Listado de ventas con saldo pendiente.
- Registro de pagos parciales o totales.
- Actualización del estado de la venta (pendiente → pagado).

**Historial de Ventas** (`modules/transacciones/historial/historial_ventas.php`)
- Búsqueda y filtros por fecha, cliente, comprobante y estado.
- Visualización del detalle de cada venta.
- Anulación de ventas con reversión de stock.

---

### Compras

**Nueva Compra** (`modules/transacciones/compras.php`)
- Registro de compras a proveedores.
- Búsqueda de proveedor por RUC.
- Selección de productos con actualización automática de stock.
- Tipos de comprobante: Ticket, Nota de Compra.
- Métodos de pago: contado o crédito.
- Generación de comprobante de compra imprimible.

**Pago de Créditos** (`modules/transacciones/cobro_compras.php`)
- Gestión de compras a crédito con registro de abonos.

**Historial de Compras** (`modules/transacciones/historial/historial_compras.php`)
- Consulta y filtrado de todas las compras registradas.
- Anulación de compras con reversión de stock.

---

### Inventario

**Stock / Inventario** (`modules/Inventario/inventario.php`)
- Vista completa del stock actual de todos los productos activos.
- Indicadores visuales para productos con stock bajo el mínimo.
- Ajuste manual de stock con registro de motivo (queda registrado en auditoría).
- Exportación a Excel y PDF.

---

### Catálogos

**Productos** (`modules/catalogos/productos.php`)
- Creación, edición y desactivación de productos.
- Campos: código, nombre, categoría, precio de compra, precio de venta, stock mínimo, imagen.
- Exportación a PDF y Excel.

**Categorías** (`modules/catalogos/categorias.php`)
- Gestión de categorías para clasificar productos.
- Exportación a PDF y Excel.

**Servicios Técnicos** (`modules/catalogos/catalogo_servicios.php`)
- Catálogo de servicios técnicos ofrecidos con precio referencial.
- Tipos de servicio asociados a cada servicio principal.
- Exportación a PDF y Excel.

---

### Comprobantes

Generación de documentos imprimibles en formato PDF usando FPDF.

| Archivo | Descripción |
|---------|-------------|
| `comprobante_ticket.php` | Ticket de venta |
| `comprobante_nota_venta.php` | Nota de venta |
| `comprobante_compra.php` | Comprobante de compra |
| `comprobante_nota_compra.php` | Nota de compra |
| `comprobante_ticket_compra.php` | Ticket de compra |
| `imprimir.php` | Vista previa de impresión genérica |

---

### Gestión Administrativa

**Clientes** (`modules/personas/clientes.php`)
- Registro de clientes personas naturales (con DNI) y empresas (con RUC).
- Consulta automática de datos desde APIs de RENIEC/SUNAT.
- Importación masiva desde archivo CSV.
- Exportación a PDF y Excel.

**Proveedores** (`modules/personas/proveedores.php`)
- Registro completo de proveedores con datos de contacto y condiciones comerciales.
- Importación masiva desde CSV.
- Exportación a PDF y Excel.

**Usuarios y Accesos** (`modules/personas/usuarios.php`)
- Gestión de usuarios del sistema (solo Administrador).
- Asignación de roles: Administrador, Asesor Comercial, Técnico.
- Configuración de permisos granulares por módulo para cada usuario.
- Cambio de contraseñas con hash seguro (`password_hash`).

---

### Mi Empresa

**Configuración** (`modules/configuracion_empresa/empresa.php`)
- Datos de la empresa: razón social, RUC, dirección, teléfono, email, logo.
- Esta información se utiliza en los comprobantes generados.

---

### Auditoría

**Registro de Auditoría** (`modules/auditoria/auditoria.php`)
- Solo accesible para el rol Administrador.
- Registro automático de todas las acciones relevantes: crear, editar, eliminar, ajustar stock, apertura/cierre de caja, cambios de permisos.
- Filtros por módulo, acción, usuario y rango de fechas.
- Exportación a PDF.

---

## Roles y Permisos

El sistema maneja tres roles predefinidos:

| ID | Rol | Descripción |
|----|-----|-------------|
| 1 | Administrador | Acceso total a todos los módulos sin restricciones |
| 2 | Asesor Comercial | Acceso configurable por módulo |
| 3 | Técnico | Acceso configurable por módulo |

Los roles 2 y 3 tienen permisos granulares configurables desde el módulo de **Usuarios y Accesos**. Cada módulo puede ser habilitado o deshabilitado individualmente para cada usuario.

### Módulos con permisos granulares

| Clave | Módulo |
|-------|--------|
| `servicios` | Recepción de Equipos |
| `taller` | Taller Técnico |
| `cobro_servicio` | Cobro de Servicios |
| `caja` | Gestión de Caja |
| `historial_caja` | Historial de Caja |
| `ventas` | Nueva Venta |
| `cobro_ventas` | Cobro de Créditos (ventas) |
| `historial_ventas` | Historial de Ventas |
| `compras` | Nueva Compra |
| `cobro_compras` | Pago de Créditos (compras) |
| `historial_compras` | Historial de Compras |
| `inventario` | Stock / Inventario |
| `productos` | Productos |
| `categorias` | Categorías |
| `catalogo_servicios` | Servicios Técnicos |
| `clientes` | Clientes |
| `proveedores` | Proveedores |
| `empresa` | Mi Empresa |

---

## Base de Datos

**Nombre:** `bdinversioneschcomputer`  
**Motor:** InnoDB / MariaDB 10.4  
**Charset:** utf8mb4  
**Zona horaria:** UTC-5 (América/Lima)

### Tablas principales

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Cuentas de acceso al sistema |
| `roles` | Roles del sistema (Administrador, Asesor, Técnico) |
| `permisos_usuario` | Permisos granulares por usuario y módulo |
| `empresa` | Datos de la empresa emisora |
| `clientes_natural` | Clientes personas naturales (DNI) |
| `clientes_empresa` | Clientes personas jurídicas (RUC) |
| `proveedores` | Proveedores del negocio |
| `categorias` | Categorías de productos |
| `productos` | Catálogo de productos con stock |
| `servicios` | Catálogo de servicios técnicos |
| `servicio_tipos` | Subtipos de servicios técnicos |
| `equipos` | Equipos registrados para servicio técnico |
| `ordenes_servicio` | Órdenes de trabajo del taller |
| `detalle_orden` | Repuestos/servicios por orden |
| `orden_cotizaciones` | Cotizaciones por orden de servicio |
| `servicio_historial` | Historial de cambios de estado por orden |
| `pagos_servicio` | Pagos registrados por orden de servicio |
| `ventas` | Cabecera de ventas |
| `detalle_venta` | Productos incluidos en cada venta |
| `cuotas_venta` | Cuotas programadas para ventas a crédito |
| `pagos_venta` | Abonos registrados contra ventas a crédito |
| `compras` | Cabecera de compras |
| `detalle_compra` | Productos incluidos en cada compra |
| `cuotas_compra` | Cuotas programadas para compras a crédito |
| `pagos_compra` | Abonos registrados contra compras a crédito |
| `caja` | Registro de cajas (apertura/cierre) |
| `movimientos_caja` | Movimientos de ingreso y egreso por caja |
| `cierre_caja_detalle` | Detalle del conteo físico al cerrar caja |
| `movimientos_inventario` | Historial de ajustes de stock |
| `auditoria` | Registro de eventos auditables del sistema |

---

## Configuración

### Archivo principal de configuración

**`conf/database.php`** — conexión PDO con zona horaria configurada.

### Zona horaria

La zona horaria está definida tanto en PHP como en MySQL para garantizar consistencia:

```php
date_default_timezone_set('America/Lima');  // PHP
$pdo->exec("SET time_zone = '-05:00'");     // MySQL
```

### Logo de la empresa

El logo se almacena en:

```
Logo/logo.jpg
```

Se utiliza en la pantalla de login, el sidebar y los comprobantes PDF.

### Uploads

Los archivos subidos (fotos de equipos en taller, etc.) se almacenan en:

```
uploads/
public/uploads/
```

---

## Acceso al Sistema

**URL local:**

```
http://localhost/sysinversioneschcomputer/
```

**Credenciales por defecto** (revisar la base de datos importada):

| Campo | Valor |
|-------|-------|
| Usuario | Ver tabla `usuarios` en BD |
| Contraseña | Definida al crear el usuario (hash bcrypt) |

> Las contraseñas se almacenan con `password_hash()` de PHP. No se guardan en texto plano.

---

## Seguridad

- Autenticación por sesión con `session_regenerate_id()` al iniciar sesión.
- Todas las consultas SQL utilizan **prepared statements** con PDO.
- Verificación de acceso en cada módulo mediante `conf/verificar_acceso.php`.
- Permisos granulares verificados antes de renderizar cada sección del menú.
- Sanitización de salidas con `htmlspecialchars()`.
- Contraseñas almacenadas con bcrypt (`password_hash` / `password_verify`).

---

## Generación de PDFs

El sistema utiliza la librería **FPDF** (ubicada en `libs/fpdf.php`) para generar documentos en formato PDF sin dependencias externas adicionales.

PDFs disponibles:

- Orden de trabajo (taller)
- Comprobantes de venta (ticket, nota de venta)
- Comprobantes de compra (ticket, nota de compra)
- Reporte de caja y detalle de caja
- Inventario
- Clientes y proveedores
- Catálogo de productos, categorías y servicios
- Auditoría

---

## Créditos

Desarrollado para **Inversiones CH Computer SRL**  
Chiclayo, Lambayeque, Perú

---

*© 2026 SysInversiones CH Computer — Todos los derechos reservados.*
