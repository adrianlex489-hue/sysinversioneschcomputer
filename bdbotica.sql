-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-05-2026 a las 02:53:07
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bdbotica`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja`
--

CREATE TABLE `caja` (
  `id_caja` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `turno` enum('mañana','tarde','noche') NOT NULL,
  `fecha_apertura` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `monto_esperado` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `estado` enum('abierta','cerrada') NOT NULL DEFAULT 'abierta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `caja`
--

INSERT INTO `caja` (`id_caja`, `id_usuario`, `turno`, `fecha_apertura`, `fecha_cierre`, `monto_inicial`, `monto_final`, `monto_esperado`, `diferencia`, `observacion`, `estado`) VALUES
(1, 1, 'tarde', '2026-05-12 17:34:36', '2026-05-12 23:58:31', 800.00, 812.00, 812.00, 0.00, NULL, 'cerrada'),
(2, 1, 'mañana', '2026-05-13 11:03:31', '2026-05-13 11:56:35', 600.00, 600.00, 600.00, 0.00, NULL, 'cerrada'),
(3, 1, 'tarde', '2026-05-13 11:57:16', NULL, 600.00, NULL, NULL, NULL, NULL, 'abierta');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`, `descripcion`, `estado`, `fecha_registro`) VALUES
(1, 'MEDICAMENTOS', 'Medicamentos generales', 1, '2026-05-10 22:52:29'),
(2, 'SUPLEMENTOS', 'Vitaminas y suplementos', 1, '2026-05-10 22:52:29'),
(3, 'PRODUCTOS NATURALES', 'Productos naturales', 1, '2026-05-10 22:52:29'),
(4, 'CUIDADO PERSONAL', 'Productos de higiene personal', 1, '2026-05-10 22:52:29'),
(5, 'BEBES', 'Productos para bebés', 1, '2026-05-10 22:52:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `tipo_documento` enum('DNI','RUC','CE','PASAPORTE','OTRO') NOT NULL DEFAULT 'DNI',
  `dni` char(8) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `estado_cliente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombres`, `apellido_paterno`, `apellido_materno`, `tipo_documento`, `dni`, `telefono`, `email`, `direccion`, `estado_cliente`, `fecha_registro`) VALUES
(1, 'CLIENTES', 'VARIOS', '', 'DNI', '00000000', '', NULL, '', 1, '2026-05-10 22:52:29'),
(2, 'ADRIAN ALEXANDER', 'ROMERO', 'MENDOZA', 'DNI', '60415173', '939683782', NULL, 'BLOCK 12 1235, TUMAN, CHICLAYO, LAMBAYEQUE', 1, '2026-05-12 19:58:40'),
(3, 'MARILU ESTHER', 'MENDOZA', 'CULQUI', 'DNI', '16685055', '900796634', NULL, 'BLOCK 12 NRO.1235, TUMAN, CHICLAYO, LAMBAYEQUE', 1, '2026-05-12 19:58:59'),
(4, 'KARINA YOVANY', 'SANDOVAL', 'VIDAURRE', 'DNI', '48562129', NULL, NULL, 'MZ H1 LT 18 A.H SHALOM PRY ESP PACHACUTEC, VENTANILLA, PROV. CONST. DEL CALLAO, PROV. CONST. DEL CALLAO', 1, '2026-05-12 23:28:25'),
(5, 'EDITH GABRIELA', 'CRISOSTOMO', 'AGUILAR', 'DNI', '78945612', NULL, NULL, 'AHM. HUAYCAN F UCV 95 LT 58, ATE, LIMA, LIMA', 1, '2026-05-12 23:36:34'),
(6, 'EDIN', 'LOPEZ', 'OLIVERA', 'DNI', '73761356', NULL, NULL, '---- MZ-B LT-05 CPM SANTA ANA, JOSE LEONARDO ORTIZ, CHICLAYO, LAMBAYEQUE', 1, '2026-05-13 19:04:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_comprobante` enum('factura','boleta','ticket','nota') NOT NULL,
  `numero_comprobante` varchar(30) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aplica_igv` tinyint(1) NOT NULL DEFAULT 1,
  `igv` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_pendiente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo_pago` enum('contado','credito') NOT NULL,
  `fecha_vencimiento_pago` date DEFAULT NULL,
  `metodo_pago` enum('efectivo','transferencia','yape','plin','tarjeta') NOT NULL,
  `estado` enum('pendiente','pagado','anulado') NOT NULL DEFAULT 'pagado',
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `compras`
--

INSERT INTO `compras` (`id_compra`, `id_proveedor`, `id_usuario`, `id_caja`, `fecha`, `tipo_comprobante`, `numero_comprobante`, `subtotal`, `descuento`, `aplica_igv`, `igv`, `total`, `saldo_pendiente`, `tipo_pago`, `fecha_vencimiento_pago`, `metodo_pago`, `estado`, `observacion`) VALUES
(1, 1, 1, NULL, '2026-05-12 12:51:00', 'ticket', 'T001-00000001', 50.00, 0.00, 1, 9.00, 59.00, 0.00, 'contado', NULL, 'efectivo', 'pagado', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_compra`
--

CREATE TABLE `cuotas_compra` (
  `id_cuota` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `numero_cuota` tinyint(3) NOT NULL COMMENT '1 a 4',
  `monto_cuota` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('pendiente','pagado','vencido') NOT NULL DEFAULT 'pendiente',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_venta`
--

CREATE TABLE `cuotas_venta` (
  `id_cuota` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `numero_cuota` tinyint(3) NOT NULL COMMENT '1 a 4',
  `monto_cuota` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('pendiente','pagado','vencido') NOT NULL DEFAULT 'pendiente',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id_detalle` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_lote` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_compra`
--

INSERT INTO `detalle_compra` (`id_detalle`, `id_compra`, `id_producto`, `id_lote`, `cantidad`, `precio_compra`, `descuento`, `subtotal`) VALUES
(1, 1, 1, 1, 50, 1.00, 0.00, 50.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_lote` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`id_detalle`, `id_venta`, `id_producto`, `id_lote`, `cantidad`, `precio_unitario`, `descuento`, `subtotal`) VALUES
(2, 2, 1, 1, 6, 2.00, 0.00, 12.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa`
--

CREATE TABLE `empresa` (
  `id_empresa` int(11) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `nombre_comercial` varchar(200) DEFAULT NULL,
  `ruc` char(11) NOT NULL,
  `direccion` varchar(250) NOT NULL,
  `distrito` varchar(100) DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `web` varchar(150) DEFAULT NULL,
  `logo` varchar(300) DEFAULT NULL COMMENT 'Ruta relativa al logo',
  `igv_porcentaje` decimal(5,2) NOT NULL DEFAULT 18.00,
  `serie_ticket` char(4) NOT NULL DEFAULT 'T001',
  `serie_nota` char(4) NOT NULL DEFAULT 'N001',
  `pie_comprobante` varchar(300) DEFAULT 'Gracias por su preferencia',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `empresa`
--

INSERT INTO `empresa` (`id_empresa`, `razon_social`, `nombre_comercial`, `ruc`, `direccion`, `distrito`, `provincia`, `departamento`, `telefono`, `email`, `web`, `logo`, `igv_porcentaje`, `serie_ticket`, `serie_nota`, `pie_comprobante`, `fecha_registro`) VALUES
(1, 'BOTICA SALUD EXPRESS S.A.C.', 'BOTICA SALUD EXPRESS', '20611967374', 'AV. PRINCIPAL NRO. 123', 'CHICLAYO', 'CHICLAYO', 'LAMBAYEQUE', '074-000000', 'contacto@boticasaludexpress.com', NULL, '/botica-2026/Logo/SALUD EXPRESS.jpg', 18.00, 'T001', 'N001', 'Gracias por su preferencia - Botica Salud Express', '2026-05-12 18:10:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes`
--

CREATE TABLE `lotes` (
  `id_lote` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `codigo_lote` varchar(50) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `stock_inicial` int(11) NOT NULL DEFAULT 0,
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `lotes`
--

INSERT INTO `lotes` (`id_lote`, `id_producto`, `codigo_lote`, `fecha_vencimiento`, `stock_inicial`, `stock_actual`, `fecha_registro`) VALUES
(1, 1, 'LOT-0001', '2026-09-11', 50, 44, '2026-05-12 05:58:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id_movimiento` int(11) NOT NULL,
  `id_caja` int(11) NOT NULL,
  `tipo_referencia` enum('venta','compra','manual') NOT NULL DEFAULT 'manual',
  `id_referencia` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `descripcion` varchar(150) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `movimientos_caja`
--

INSERT INTO `movimientos_caja` (`id_movimiento`, `id_caja`, `tipo_referencia`, `id_referencia`, `id_usuario`, `tipo`, `descripcion`, `monto`, `metodo_pago`, `fecha`) VALUES
(2, 1, 'venta', 2, 1, 'ingreso', 'Venta #2 - Ticket T001-00000001', 12.00, 'efectivo', '2026-05-12 19:18:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_compra`
--

CREATE TABLE `pagos_compra` (
  `id_pago` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','yape','plin','tarjeta') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_venta`
--

CREATE TABLE `pagos_venta` (
  `id_pago` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL DEFAULT 1,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_usuario`
--

CREATE TABLE `permisos_usuario` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `modulo` varchar(60) NOT NULL COMMENT 'Clave del módulo, ej: ventas, compras, caja',
  `permitido` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `permisos_usuario`
--

INSERT INTO `permisos_usuario` (`id`, `id_usuario`, `modulo`, `permitido`) VALUES
(85, 3, 'ventas', 1),
(86, 3, 'compras', 1),
(87, 3, 'historial_ventas', 1),
(88, 3, 'historial_compras', 1),
(89, 3, 'caja', 1),
(90, 3, 'historial_caja', 1),
(91, 3, 'inventario', 1),
(92, 3, 'lotes', 1),
(93, 3, 'productos', 1),
(94, 3, 'categorias', 1),
(95, 3, 'unidades', 1),
(96, 3, 'clientes', 1),
(97, 3, 'proveedores', 1),
(98, 3, 'empresa', 1),
(99, 2, 'ventas', 1),
(100, 2, 'compras', 1),
(101, 2, 'historial_ventas', 1),
(102, 2, 'historial_compras', 1),
(103, 2, 'caja', 1),
(104, 2, 'historial_caja', 1),
(105, 2, 'inventario', 1),
(106, 2, 'lotes', 1),
(107, 2, 'productos', 1),
(108, 2, 'categorias', 1),
(109, 2, 'unidades', 1),
(110, 2, 'clientes', 1),
(111, 2, 'proveedores', 1),
(112, 2, 'empresa', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `codigo_barra` varchar(100) DEFAULT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `laboratorio` varchar(100) DEFAULT NULL,
  `presentacion` varchar(100) DEFAULT NULL,
  `concentracion` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_unidad` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `stock_maximo` int(11) NOT NULL DEFAULT 100,
  `precio_compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_venta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requiere_receta` tinyint(1) NOT NULL DEFAULT 0,
  `registro_sanitario` varchar(100) DEFAULT NULL,
  `imagenes` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `codigo`, `codigo_barra`, `nombre_producto`, `laboratorio`, `presentacion`, `concentracion`, `descripcion`, `id_categoria`, `id_unidad`, `id_proveedor`, `stock`, `stock_minimo`, `stock_maximo`, `precio_compra`, `precio_venta`, `requiere_receta`, `registro_sanitario`, `imagenes`, `estado`, `fecha_registro`) VALUES
(1, 'PARA-0001', NULL, 'PARACETAMOL 500MG', 'BAYER', NULL, '500 mg', NULL, 1, 3, 1, 44, 20, 100, 1.50, 8.00, 0, 'RS-123456', '/botica-2026/public/uploads/productos/prod_6a016edd529bd.webp,/botica-2026/public/uploads/productos/prod_6a016edd530d6.jpg,/botica-2026/public/uploads/productos/prod_6a016edd53590.webp', 1, '2026-05-11 00:53:33'),
(7, 'MED001', '7751234567001', 'PARACETAMOL', 'Bayer', 'Tabletas', '500mg', 'Analgésico y antipirético para aliviar dolor y fiebre', 1, 1, 1, 0, 10, 150, 2.50, 4.00, 0, 'RS10001', NULL, 0, '2026-05-12 21:59:48'),
(8, 'MED002', '7751234567002', 'AMOXICILINA', 'AC Farma', 'Cápsulas', '500mg', 'Antibiótico de amplio espectro', 2, 1, 2, 0, 5, 100, 9.00, 12.50, 1, 'RS10002', '/botica-2026/public/uploads/productos/prod_6a049af0bb303.png', 1, '2026-05-12 21:59:48'),
(9, 'MED003', '7751234567003', 'IBUPROFENO', 'Teva', 'Tabletas', '400mg', 'Antiinflamatorio y analgésico', 1, 1, 1, 0, 10, 120, 3.20, 6.00, 0, 'RS10003', '/botica-2026/public/uploads/productos/prod_6a049ae65fe37.jpg', 1, '2026-05-12 21:59:48'),
(10, 'MED004', '7751234567004', 'LORATADINA', 'Genfar', 'Jarabe', '5mg/5ml', 'Antialérgico para síntomas de alergia', 3, 2, 3, 0, 5, 60, 6.50, 15.00, 0, 'RS10004', '/botica-2026/public/uploads/productos/prod_6a049adb88383.jpg', 1, '2026-05-12 21:59:48'),
(11, 'MED005', '7751234567005', 'OMEPRAZOL', 'Inkafarma Labs', 'Cápsulas', '20mg', 'Protector gástrico para acidez y gastritis', 4, 1, 2, 0, 10, 140, 4.50, 8.00, 0, 'RS10005', '/botica-2026/public/uploads/productos/prod_6a049ad0a6483.jpg', 1, '2026-05-12 21:59:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `ruc` char(11) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `razon_social`, `ruc`, `telefono`, `email`, `direccion`, `contacto`, `estado`, `fecha_registro`) VALUES
(1, 'LABORATORIOS S.A.C', '20508565934', '987654321', 'Laboratorio@gmail.com', 'CHICLAYO', '', 1, '2026-05-11 00:52:49'),
(2, 'INVERSIONES CH COMPUTER SRL', '20479894699', '987654328', 'inversioneschcomputer@gmail.com', 'CAL. JOSE FRANCISCO CABRERA NRO. 274      CERCADO DE CHICLAYO, CHICLAYO, CHICLAYO, LAMBAYEQUE', '', 1, '2026-05-12 19:59:49'),
(3, 'Química Suiza S.A.C.', '20100070970', '014567890', 'ventas@quimicasuiza.com', 'Av. República de Panamá 2577 - Lima', NULL, 1, '2026-05-12 21:58:41'),
(4, 'Distribuidora Farma Perú S.A.C.', '20510293841', '044567890', 'contacto@farmaperu.com', 'Av. España 1250 - Trujillo', NULL, 1, '2026-05-12 21:58:41'),
(5, 'AC Farma S.A.', '20123456789', '016789012', 'pedidos@acfarma.com', 'Av. Industrial 345 - Lima', NULL, 1, '2026-05-12 21:58:41'),
(6, 'MediSalud Distribuciones', '20604567891', '044778899', 'ventas@medisalud.com', 'Jr. Pizarro 456 - Trujillo', NULL, 1, '2026-05-12 21:58:41'),
(7, 'Botica Supply Perú', '20567891234', '019988776', 'soporte@boticasupply.com', 'Av. América Sur 980 - Trujillo', NULL, 1, '2026-05-12 21:58:41'),
(8, 'MIFARMA S.A.C.', '20512002090', NULL, NULL, 'CAL. VICTOR ALZAMORA NRO. 147      SANTA CATALINA, LA VICTORIA, LIMA, LIMA', NULL, 1, '2026-05-12 23:29:18'),
(9, 'T&F ASESORIA Y CONSULTORIA INMOBILIARIA E.I.R.L.', '20607269875', '', NULL, 'CAL. MARISCAL DOMINGO NIETO NRO. 480      CERCADO DE CHICLAYO, CHICLAYO, CHICLAYO, LAMBAYEQUE', '', 1, '2026-05-12 23:37:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `estado`, `fecha_registro`) VALUES
(1, 'Administrador', 'Acceso total al sistema', 1, '2026-05-10 22:52:29'),
(2, 'Cajero', 'Gestión de ventas y caja', 1, '2026-05-10 22:52:29'),
(3, 'Trabajador', 'Gestión de inventario y ventas', 1, '2026-05-10 22:52:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id_unidad` int(11) NOT NULL,
  `nombre_unidad` varchar(50) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id_unidad`, `nombre_unidad`, `abreviatura`, `estado`, `fecha_registro`) VALUES
(1, 'UNIDAD', 'UND', 1, '2026-05-11 01:42:15'),
(2, 'CAJA', 'CJ', 1, '2026-05-11 01:42:15'),
(3, 'BLISTER', 'BLS', 1, '2026-05-11 01:42:15'),
(4, 'FRASCO', 'FCO', 1, '2026-05-11 01:42:15'),
(5, 'PACK', 'PCK', 1, '2026-05-11 01:42:15'),
(6, 'TUBO', 'TBO', 1, '2026-05-11 01:42:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_rol` int(11) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_completo`, `username`, `clave`, `email`, `id_rol`, `estado`, `fecha_registro`) VALUES
(1, 'Adrian Romero', 'Adrian', '$2y$10$aYYiYUbN6ziHACuINA6lbuOCSnKMV9p3tnJJoAJvB2vGOiQ7a443a', NULL, 1, 1, '2026-05-10 22:52:29'),
(2, 'Manuel Medina Lopez', 'Manuel Medina', '$2y$10$tU0/Mlz4NlJoM6E1C/0fcuQzkLTWxt9pUBddJehM2THOsKGchv1Tm', 'Manuelmedina489@gmail.com', 2, 1, '2026-05-13 09:50:45'),
(3, 'Ana Lopez Coronado', 'Ana Lopez', '$2y$10$QgI80noco1n0AuVkJ2rITekeE3pWYbnThlk8fhg3eXmKQkGH8vbXy', 'Analopez123@gmail.com', 3, 1, '2026-05-13 09:51:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_caja` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_comprobante` enum('boleta','factura','ticket') NOT NULL,
  `numero_comprobante` varchar(30) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aplica_igv` tinyint(1) NOT NULL DEFAULT 1,
  `igv` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_pendiente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo_pago` enum('contado','credito') NOT NULL,
  `fecha_vencimiento_pago` date DEFAULT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') NOT NULL,
  `estado` enum('pagado','pendiente','anulado') NOT NULL DEFAULT 'pagado',
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_cliente`, `id_usuario`, `id_caja`, `fecha`, `tipo_comprobante`, `numero_comprobante`, `subtotal`, `descuento`, `aplica_igv`, `igv`, `total`, `saldo_pendiente`, `tipo_pago`, `fecha_vencimiento_pago`, `metodo_pago`, `estado`, `observacion`) VALUES
(2, 1, 1, 1, '2026-05-12 19:18:00', 'ticket', 'T001-00000001', 12.00, 0.00, 0, 0.00, 12.00, 0.00, 'contado', NULL, 'efectivo', 'pagado', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `caja`
--
ALTER TABLE `caja`
  ADD PRIMARY KEY (`id_caja`),
  ADD KEY `fk_caja_usuario` (`id_usuario`),
  ADD KEY `idx_caja_usuario_estado` (`id_usuario`,`estado`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre_categoria` (`nombre_categoria`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `fk_compra_proveedor` (`id_proveedor`),
  ADD KEY `fk_compra_usuario` (`id_usuario`),
  ADD KEY `fk_compra_caja` (`id_caja`);

--
-- Indices de la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  ADD PRIMARY KEY (`id_cuota`),
  ADD KEY `fk_cuota_compra` (`id_compra`);

--
-- Indices de la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  ADD PRIMARY KEY (`id_cuota`),
  ADD KEY `fk_cuotaventa_venta` (`id_venta`);

--
-- Indices de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_detallecompra_compra` (`id_compra`),
  ADD KEY `fk_detallecompra_producto` (`id_producto`),
  ADD KEY `fk_detallecompra_lote` (`id_lote`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_detalleventa_venta` (`id_venta`),
  ADD KEY `fk_detalleventa_producto` (`id_producto`),
  ADD KEY `fk_detalleventa_lote` (`id_lote`);

--
-- Indices de la tabla `empresa`
--
ALTER TABLE `empresa`
  ADD PRIMARY KEY (`id_empresa`);

--
-- Indices de la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD PRIMARY KEY (`id_lote`),
  ADD KEY `fk_lote_producto` (`id_producto`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `fk_movimiento_caja` (`id_caja`),
  ADD KEY `fk_mov_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagocompra_compra` (`id_compra`),
  ADD KEY `fk_pagocompra_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagoventa_venta` (`id_venta`),
  ADD KEY `fk_pagoventa_usuario` (`id_usuario`);

--
-- Indices de la tabla `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuario_modulo` (`id_usuario`,`modulo`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD UNIQUE KEY `codigo_barra` (`codigo_barra`),
  ADD KEY `fk_producto_categoria` (`id_categoria`),
  ADD KEY `fk_producto_unidad` (`id_unidad`),
  ADD KEY `fk_producto_proveedor` (`id_proveedor`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `ruc` (`ruc`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id_unidad`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_cliente` (`id_cliente`),
  ADD KEY `fk_venta_usuario` (`id_usuario`),
  ADD KEY `fk_venta_caja` (`id_caja`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `caja`
--
ALTER TABLE `caja`
  MODIFY `id_caja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  MODIFY `id_cuota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  MODIFY `id_cuota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `empresa`
--
ALTER TABLE `empresa`
  MODIFY `id_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `lotes`
--
ALTER TABLE `lotes`
  MODIFY `id_lote` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id_unidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `caja`
--
ALTER TABLE `caja`
  ADD CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compra_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  ADD CONSTRAINT `fk_cuota_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  ADD CONSTRAINT `fk_cuotaventa_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD CONSTRAINT `fk_detallecompra_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detallecompra_lote` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detallecompra_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalleventa_lote` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalleventa_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalleventa_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD CONSTRAINT `fk_lote_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  ADD CONSTRAINT `fk_pagocompra_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagocompra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  ADD CONSTRAINT `fk_pagoventa_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagoventa_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD CONSTRAINT `fk_permiso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_producto_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_producto_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `unidades` (`id_unidad`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
