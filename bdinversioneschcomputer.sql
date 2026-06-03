-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-06-2026 a las 16:57:51
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
-- Base de datos: `bdinversioneschcomputer`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que realizó la acción',
  `modulo` varchar(60) NOT NULL COMMENT 'productos, ventas, compras, caja, usuarios, servicios, empresa, inventario',
  `accion` varchar(40) NOT NULL COMMENT 'crear, editar, eliminar, ajuste, anular, apertura, cierre, permisos, cambio_rol',
  `tabla` varchar(60) DEFAULT NULL COMMENT 'Tabla de BD afectada',
  `id_registro` int(11) DEFAULT NULL COMMENT 'ID del registro afectado',
  `campo` varchar(80) DEFAULT NULL COMMENT 'Campo específico modificado',
  `valor_antes` text DEFAULT NULL COMMENT 'Valor anterior (puede ser JSON para múltiples campos)',
  `valor_nuevo` text DEFAULT NULL COMMENT 'Valor nuevo (puede ser JSON para múltiples campos)',
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Descripción legible del evento',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IP del cliente',
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci COMMENT='Registro de auditoría de acciones del sistema';

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `id_usuario`, `modulo`, `accion`, `tabla`, `id_registro`, `campo`, `valor_antes`, `valor_nuevo`, `descripcion`, `ip`, `fecha`) VALUES
(1, 2, 'productos', 'editar', 'productos', 1, 'precio_venta', '2850.00', '2900', 'Producto \'LAPTOP HP 15.6\" WINDOWS 11 HOME\' — cambio en precio_venta', '::1', '2026-05-23 18:16:11'),
(2, 2, 'productos', 'editar', 'productos', 1, 'precio_compra', '2300.00', '2300', 'Producto \'LAPTOP HP 15.6\" WINDOWS 11 HOME\' — cambio en precio_compra', '::1', '2026-05-23 18:16:11'),
(3, 1, 'ventas', 'crear', 'ventas', 2, NULL, NULL, NULL, 'Venta #2 — ticket T001-00000002 — Total: S/. 460.20 — Pago: contado', '::1', '2026-05-23 20:19:39'),
(4, 1, 'ventas', 'crear', 'ventas', 3, NULL, NULL, NULL, 'Venta #3 — ticket T001-00000003 — Total: S/. 390.00 — Pago: contado', '::1', '2026-05-23 20:27:57'),
(5, 1, 'ventas', 'crear', 'ventas', 4, NULL, NULL, NULL, 'Venta #4 — nota N001-00000001 — Total: S/. 460.20 — Pago: contado', '::1', '2026-05-23 20:30:53'),
(6, 1, 'compras', 'crear', 'compras', 4, NULL, NULL, NULL, 'Compra #4 — ticket T001-00000002 — Total: S/. 220.90 — Pago: contado', '::1', '2026-05-23 20:56:57'),
(7, 1, 'caja', 'cierre', 'caja', 1, 'diferencia', '879.3', '879.3', 'Cierre de caja: \"Caja 21/05/2026\" — Esperado: S/. 879.30 — Contado: S/. 879.30 — Diferencia: S/. 0.00', '::1', '2026-05-23 22:17:36'),
(8, 1, 'usuarios', 'permisos', 'permisos_usuario', 3, NULL, NULL, NULL, 'Permisos modificados para @Adriana Lopez — cobro_ventas: OFF → ON, cobro_compras: OFF → ON, catalogo_servicios: OFF → ON', '::1', '2026-05-24 12:26:54'),
(9, 1, 'usuarios', 'permisos', 'permisos_usuario', 2, NULL, NULL, NULL, 'Permisos modificados para @Tino Coronel — caja: OFF → ON, historial_caja: OFF → ON', '::1', '2026-05-24 16:13:19'),
(10, 1, 'usuarios', 'permisos', 'permisos_usuario', 2, NULL, NULL, NULL, 'Permisos modificados para @Tino Coronel — historial_caja: ON → OFF', '::1', '2026-05-24 16:33:24'),
(11, 1, 'usuarios', 'permisos', 'permisos_usuario', 2, NULL, NULL, NULL, 'Permisos modificados para @Tino Coronel — caja: ON → OFF', '::1', '2026-05-24 16:33:51'),
(12, 1, 'usuarios', 'permisos', 'permisos_usuario', 2, NULL, NULL, NULL, 'Permisos modificados para @Tino Coronel — caja: OFF → ON', '::1', '2026-05-24 16:35:09'),
(13, 1, 'caja', 'apertura', 'caja', 2, NULL, NULL, NULL, 'Apertura de caja: \"Caja 24/05/2026\" — Fondo inicial: S/. 500.00', '::1', '2026-05-24 19:14:47'),
(14, 1, 'ventas', 'crear', 'ventas', 5, NULL, NULL, NULL, 'Venta #5 — ticket T001-00000004 — Total: S/. 460.20 — Pago: credito', '::1', '2026-05-25 06:38:18'),
(15, 1, 'productos', 'editar', 'productos', 2, 'precio_venta', '130.00', '130', 'Producto \'REDRAGON - MOUSE INVADER\' — cambio en precio_venta', '::1', '2026-05-25 06:42:58'),
(16, 1, 'productos', 'editar', 'productos', 2, 'precio_compra', '93.60', '93.6', 'Producto \'REDRAGON - MOUSE INVADER\' — cambio en precio_compra', '::1', '2026-05-25 06:42:58'),
(17, 1, 'productos', 'editar', 'productos', 2, 'nombre', 'REDRAGON - MOUSE INVADER M719 RGB 10000 DPI - NEGRO', 'REDRAGON - MOUSE INVADER', 'Producto \'REDRAGON - MOUSE INVADER\' — cambio en nombre', '::1', '2026-05-25 06:42:58'),
(18, 1, 'caja', 'cierre', 'caja', 2, 'diferencia', '806.8', '806.8', 'Cierre de caja: \"Caja 24/05/2026\" — Esperado: S/. 806.80 — Contado: S/. 806.80 — Diferencia: S/. 0.00', '::1', '2026-05-25 10:09:47'),
(19, 1, 'caja', 'apertura', 'caja', 3, NULL, NULL, NULL, 'Apertura de caja: \"Caja 25/05/2026\" — Fondo inicial: S/. 800.00', '::1', '2026-05-25 11:09:30'),
(20, 1, 'compras', 'crear', 'compras', 5, NULL, NULL, NULL, 'Compra #5 — ticket T001-00000003 — Total: S/. 2,714.00 — Pago: credito', '::1', '2026-05-25 11:12:09'),
(21, 1, 'ventas', 'crear', 'ventas', 6, NULL, NULL, NULL, 'Venta #6 — ticket T001-00000005 — Total: S/. 3,422.00 — Pago: contado', '::1', '2026-05-25 11:13:49'),
(22, 1, 'usuarios', 'permisos', 'permisos_usuario', 2, NULL, NULL, NULL, 'Permisos modificados para @Tino Coronel — caja: ON → OFF', '::1', '2026-05-25 19:10:16'),
(23, 1, 'usuarios', 'permisos', 'permisos_usuario', 3, NULL, NULL, NULL, 'Permisos modificados para @Adriana Lopez — ventas: ON → OFF, cobro_ventas: ON → OFF, historial_ventas: ON → OFF, compras: ON → OFF, cobro_compras: ON → OFF, historial_compras: ON → OFF, productos: ON → OFF, categorias: ON → OFF, catalogo_servicios: ON → O', '::1', '2026-05-26 08:13:13'),
(24, 1, 'usuarios', 'permisos', 'permisos_usuario', 3, NULL, NULL, NULL, 'Permisos modificados para @Adriana Lopez — caja: OFF → ON, ventas: OFF → ON, cobro_ventas: OFF → ON, historial_ventas: OFF → ON, compras: OFF → ON, cobro_compras: OFF → ON, historial_compras: OFF → ON, productos: OFF → ON, categorias: OFF → ON, clientes: ', '::1', '2026-05-26 08:28:58'),
(25, 3, 'caja', 'cierre', 'caja', 3, 'diferencia', '3543.5', '3543.5', 'Cierre de caja: \"Caja 25/05/2026\" — Esperado: S/. 3,543.50 — Contado: S/. 3,543.50 — Diferencia: S/. 0.00', '::1', '2026-05-26 08:30:38'),
(26, 1, 'inventario', 'ajuste', 'productos', 1, 'stock', '2', '102', 'Corrección de stock — LAPTOP HP 15.6\" WINDOWS 11 HOME — Motivo: Corrección de inventario', '::1', '2026-05-26 18:37:13'),
(27, 1, 'caja', 'apertura', 'caja', 4, NULL, NULL, NULL, 'Apertura de caja: \"Caja 26/05/2026\" — Fondo inicial: S/. 500.00', '::1', '2026-05-26 18:39:06'),
(28, 1, 'ventas', 'crear', 'ventas', 7, NULL, NULL, NULL, 'Venta #7 — ticket T001-00000006 — Total: S/. 7,100.00 — Pago: contado', '::1', '2026-05-26 18:42:05'),
(29, 1, 'inventario', 'ajuste', 'productos', 2, 'stock', '6', '100', 'Corrección de stock — REDRAGON - MOUSE INVADER — Motivo: corrección de inventario', '::1', '2026-05-26 18:49:04'),
(30, 1, 'productos', 'editar', 'productos', 2, 'precio_venta', '130.00', '130', 'Producto \'REDRAGON - MOUSE INVADER\' — cambio en precio_venta', '::1', '2026-05-26 19:48:17'),
(31, 1, 'productos', 'editar', 'productos', 2, 'precio_compra', '93.60', '93.6', 'Producto \'REDRAGON - MOUSE INVADER\' — cambio en precio_compra', '::1', '2026-05-26 19:48:17'),
(32, 1, 'productos', 'crear', 'productos', 2, 'estado', '0', '1', 'Producto reactivado: REDRAGON - MOUSE INVADER', '::1', '2026-05-26 19:48:23'),
(33, 1, 'caja', 'editar', 'movimientos_caja', 4, NULL, NULL, NULL, 'Movimiento manual — EGRESO: Pago de luz — S/. 50.00 (efectivo)', '::1', '2026-05-26 19:55:40'),
(34, 1, 'productos', 'crear', 'productos', 3, NULL, NULL, NULL, 'Producto creado: BATERIA COMPATIBLE DE LAPTOP TOSHIBA INTERNA 5107 (Cód: PRD-319F38) — P.Venta: S/. 120.00', '::1', '2026-05-27 06:13:19'),
(35, 1, 'compras', 'crear', 'compras', 6, NULL, NULL, NULL, 'Compra #6 — ticket T001-00000004 — Total: S/. 236.00 — Pago: contado', '::1', '2026-05-27 06:14:42'),
(36, 1, 'compras', 'crear', 'compras', 7, NULL, NULL, NULL, 'Compra #7 — ticket T001-00000005 — Total: S/. 236.00 — Pago: contado', '::1', '2026-05-27 06:19:54'),
(37, 1, 'compras', 'crear', 'compras', 8, NULL, NULL, NULL, 'Compra #8 — ticket T001-00000006 — Total: S/. 1,000.00 — Pago: contado', '::1', '2026-05-27 06:20:44'),
(38, 1, 'compras', 'crear', 'compras', 9, NULL, NULL, NULL, 'Compra #9 — ticket T001-00000007 — Total: S/. 187.20 — Pago: contado', '::1', '2026-05-27 06:22:25'),
(39, 1, 'compras', 'crear', 'compras', 10, NULL, NULL, NULL, 'Compra #10 — ticket T001-00000008 — Total: S/. 200.00 — Pago: contado', '::1', '2026-05-27 06:23:26'),
(40, 1, 'compras', 'crear', 'compras', 11, NULL, NULL, NULL, 'Compra #11 — ticket T001-00000009 — Total: S/. 944.00 — Pago: contado', '::1', '2026-05-27 06:31:18'),
(41, 1, 'productos', 'crear', 'productos', 4, NULL, NULL, NULL, 'Producto creado: TECLADO COMPATIBLE DE LAPTOP HP (Cód: PRD-E5C097) — P.Venta: S/. 120.00', '::1', '2026-05-27 06:40:56'),
(42, 1, 'compras', 'crear', 'compras', 12, NULL, NULL, NULL, 'Compra #12 — ticket T001-00000010 — Total: S/. 200.00 — Pago: contado', '::1', '2026-05-27 06:41:46'),
(43, 1, 'compras', 'crear', 'compras', 13, NULL, NULL, NULL, 'Compra #13 — ticket T001-00000011 — Total: S/. 200.00 — Pago: contado', '::1', '2026-05-27 06:44:53'),
(44, 1, 'compras', 'crear', 'compras', 14, NULL, NULL, NULL, 'Compra #14 — ticket T001-00000012 — Total: S/. 200.00 — Pago: contado', '::1', '2026-05-27 06:50:15'),
(45, 1, 'compras', 'crear', 'compras', 15, NULL, NULL, NULL, 'Compra #15 — ticket T001-00000013 — Total: S/. 100.00 — Pago: contado', '::1', '2026-05-27 06:54:01'),
(46, 1, 'compras', 'crear', 'compras', 16, NULL, NULL, NULL, 'Compra #16 — ticket T001-00000014 — Total: S/. 200.00 — Pago: contado', '::1', '2026-05-27 06:57:08'),
(47, 1, 'ventas', 'crear', 'ventas', 8, NULL, NULL, NULL, 'Venta #8 — ticket T001-00000007 — Total: S/. 3,422.00 — Pago: credito', '::1', '2026-05-27 07:19:56'),
(48, 1, 'caja', 'editar', 'movimientos_caja', 4, NULL, NULL, NULL, 'Movimiento manual — INGRESO: Pago al dueño de negocio — S/. 500.00 (efectivo)', '::1', '2026-05-27 07:20:54'),
(49, 1, 'productos', 'editar', 'productos', 4, 'precio_venta', '120.00', '120', 'Producto \'TECLADO COMPATIBLE DE LAPTOP HP\' — cambio en precio_venta', '::1', '2026-05-27 09:42:45'),
(50, 1, 'productos', 'editar', 'productos', 4, 'precio_compra', '100.00', '100', 'Producto \'TECLADO COMPATIBLE DE LAPTOP HP\' — cambio en precio_compra', '::1', '2026-05-27 09:42:45'),
(51, 1, 'productos', 'editar', 'productos', 4, 'precio_venta', '120.00', '120', 'Producto \'TECLADO COMPATIBLE DE LAPTOP HP\' — cambio en precio_venta', '::1', '2026-05-27 09:43:43'),
(52, 1, 'productos', 'editar', 'productos', 4, 'precio_compra', '100.00', '100', 'Producto \'TECLADO COMPATIBLE DE LAPTOP HP\' — cambio en precio_compra', '::1', '2026-05-27 09:43:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja`
--

CREATE TABLE `caja` (
  `id_caja` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre o descripción de la caja',
  `fecha_apertura` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_inicial` decimal(10,2) DEFAULT NULL,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `monto_esperado` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `estado` enum('abierta','cerrada') DEFAULT NULL,
  `observacion` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `caja`
--

INSERT INTO `caja` (`id_caja`, `id_usuario`, `nombre`, `fecha_apertura`, `fecha_cierre`, `monto_inicial`, `monto_final`, `monto_esperado`, `diferencia`, `estado`, `observacion`) VALUES
(1, 1, 'Caja 21/05/2026', '2026-05-21 19:46:13', '2026-05-23 22:17:36', 500.00, 879.30, 879.30, 0.00, 'cerrada', NULL),
(2, 1, 'Caja 24/05/2026', '2026-05-24 19:14:47', '2026-05-25 10:09:47', 500.00, 806.80, 806.80, 0.00, 'cerrada', NULL),
(3, 1, 'Caja 25/05/2026', '2026-05-25 11:09:30', '2026-05-26 08:30:38', 800.00, 3543.50, 3543.50, 0.00, 'cerrada', NULL),
(4, 1, 'Caja 26/05/2026', '2026-05-26 18:39:06', NULL, 500.00, NULL, NULL, NULL, 'abierta', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`, `descripcion`, `estado`, `fecha_registro`) VALUES
(1, 'LAPTOP', 'Todas las marcas HP, LENOVO, ASUS, etc', 1, '2026-03-15 19:57:15'),
(2, 'Teclados', '', 1, '2026-03-17 14:47:00'),
(3, 'Mouses', '', 1, '2026-03-17 14:47:16'),
(4, 'COMPUTADORAS', NULL, 1, '2026-05-17 17:52:07'),
(5, 'BATERíAS-REPUESTOS', NULL, 1, '2026-05-27 06:10:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierre_caja_detalle`
--

CREATE TABLE `cierre_caja_detalle` (
  `id` int(11) NOT NULL,
  `id_caja` int(11) NOT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') NOT NULL,
  `monto_esperado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_contado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `diferencia` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `cierre_caja_detalle`
--

INSERT INTO `cierre_caja_detalle` (`id`, `id_caja`, `metodo_pago`, `monto_esperado`, `monto_contado`, `diferencia`) VALUES
(1, 1, 'efectivo', 879.30, 879.30, 0.00),
(2, 1, 'yape', 0.00, 0.00, 0.00),
(3, 1, 'transferencia', 0.00, 0.00, 0.00),
(4, 2, 'efectivo', 806.80, 806.80, 0.00),
(5, 2, 'yape', 0.00, 0.00, 0.00),
(6, 2, 'transferencia', 0.00, 0.00, 0.00),
(7, 3, 'efectivo', 3543.50, 3543.50, 0.00),
(8, 3, 'yape', 0.00, 0.00, 0.00),
(9, 3, 'transferencia', 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_empresa`
--

CREATE TABLE `clientes_empresa` (
  `id_cliente_empresa` int(11) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `ruc` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `estado_cliente` tinyint(4) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `clientes_empresa`
--

INSERT INTO `clientes_empresa` (`id_cliente_empresa`, `razon_social`, `ruc`, `telefono`, `email`, `direccion`, `estado_cliente`, `fecha_registro`) VALUES
(13, 'INGRAM MICRO S.A.C.', '20267163228', '', NULL, 'JR. CARPACCIO NRO. 250, SAN BORJA, LIMA, LIMA', 1, '2026-05-20 18:31:04'),
(14, 'TECNOLOGIA AVANZADA S.A.C.', '20100123456', '074234567', 'ventas@tecavanzada.com', 'AV. GRAU 123 CHICLAYO LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(15, 'DISTRIBUIDORA NORTE E.I.R.L.', '20200234567', '01234567', 'contacto@distnorte.com', 'JR. UNION 456 LIMA LIMA', 1, '2026-05-25 00:07:06'),
(16, 'IMPORTACIONES DEL PACIFICO S.R.L.', '20300345678', '044345678', 'info@imppacific.com', 'CAL. LOS PINOS 789 TRUJILLO LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(17, 'COMERCIAL ANDINA S.A.', '20400456789', '054456789', 'comercial@andina.com', 'AV. REPUBLICA 321 AREQUIPA AREQUIPA', 1, '2026-05-25 00:07:06'),
(18, 'SOLUCIONES DIGITALES PERU S.A.C.', '20500567890', '073567890', 'soporte@soldigital.pe', 'JR. TACNA 654 PIURA PIURA', 1, '2026-05-25 00:07:06'),
(19, 'GRUPO EMPRESARIAL CUSCO S.R.L.', '20600678901', '084678901', 'gerencia@gecusco.com', 'AV. BOLOGNESI 987 CUSCO CUSCO', 1, '2026-05-25 00:07:06'),
(20, 'FERRETERIA INDUSTRIAL ICA E.I.R.L.', '20700789012', '056789012', 'ventas@ferrica.com', 'CAL. REAL 147 ICA ICA', 1, '2026-05-25 00:07:06'),
(21, 'AGRO EXPORTACIONES HUANCAYO S.A.C.', '20800890123', '064890123', 'export@agrohuancayo.com', 'AV. EJERCITO 258 HUANCAYO JUNIN', 1, '2026-05-25 00:07:06'),
(22, 'LOGISTICA AMAZONICA S.R.L.', '20900901234', '061901234', 'logistica@amazonica.pe', 'JR. AMAZONAS 369 PUCALLPA UCAYALI', 1, '2026-05-25 00:07:06'),
(23, 'CONSTRUCTORA TACNA S.A.', '20101012345', '052012345', 'obras@constacna.com', 'AV. INDEPENDENCIA 741 TACNA TACNA', 1, '2026-05-25 00:07:06'),
(24, 'MINERA TUMBES GOLD E.I.R.L.', '20201123456', '072123456', 'mineria@tumbesgold.com', 'CAL. PROGRESO 852 TUMBES TUMBES', 1, '2026-05-25 00:07:06'),
(25, 'PESQUERA DEL SUR S.A.C.', '20301234567', '053234567', 'pesca@delsur.com', 'AV. GRAU 963 MOQUEGUA MOQUEGUA', 1, '2026-05-25 00:07:06'),
(26, 'TEXTIL PUNO S.R.L.', '20401345678', '051345678', 'textil@punoperu.com', 'JR. LIBERTAD 174 PUNO PUNO', 1, '2026-05-25 00:07:06'),
(27, 'CAFETALERA CAJAMARCA S.A.C.', '20501456789', '076456789', 'cafe@cajamarca.com', 'AV. PERU 285 CAJAMARCA CAJAMARCA', 1, '2026-05-25 00:07:06'),
(28, 'ARTESANIAS AYACUCHO E.I.R.L.', '20601567890', '066567890', 'arte@ayacucho.pe', 'CAL. UNION 396 AYACUCHO AYACUCHO', 1, '2026-05-25 00:07:06'),
(29, 'TURISMO HUARAZ S.A.', '20701678901', '043678901', 'tours@huaraz.com', 'AV. BOLOGNESI 507 HUARAZ ANCASH', 1, '2026-05-25 00:07:06'),
(30, 'PESQUERA CHIMBOTE S.A.C.', '20801789012', '043789012', 'pesca@chimbote.pe', 'JR. GRAU 618 CHIMBOTE ANCASH', 1, '2026-05-25 00:07:06'),
(31, 'TRANSPORTE JULIACA E.I.R.L.', '20901890123', '051890123', 'trans@juliaca.com', 'AV. EJERCITO 729 JULIACA PUNO', 1, '2026-05-25 00:07:06'),
(32, 'AGRICOLA ABANCAY S.R.L.', '20102901234', '083901234', 'agro@abancay.pe', 'CAL. REAL 830 ABANCAY APURIMAC', 1, '2026-05-25 00:07:06'),
(33, 'FARMACEUTICA HUANUCO S.A.C.', '20202012345', '062012345', 'farma@huanuco.com', 'AV. REPUBLICA 941 HUANUCO HUANUCO', 1, '2026-05-25 00:07:06'),
(34, 'MADERERA TINGO MARIA E.I.R.L.', '20302123456', '062123456', 'madera@tingomaria.pe', 'JR. TACNA 052 TINGO MARIA HUANUCO', 1, '2026-05-25 00:07:06'),
(35, 'ECOTURISMO MOYOBAMBA S.A.', '20402234567', '042234567', 'eco@moyobamba.com', 'AV. GRAU 163 MOYOBAMBA SAN MARTIN', 1, '2026-05-25 00:07:06'),
(36, 'AGROINDUSTRIAL TARAPOTO S.R.L.', '20502345678', '042345678', 'agro@tarapoto.pe', 'CAL. PROGRESO 274 TARAPOTO SAN MARTIN', 1, '2026-05-25 00:07:06'),
(37, 'ACUICULTURA IQUITOS S.A.C.', '20602456789', '065456789', 'acui@iquitos.com', 'AV. PERU 385 IQUITOS LORETO', 1, '2026-05-25 00:07:06'),
(38, 'CONSTRUCTORA SULLANA E.I.R.L.', '20702567890', '073567890', 'obras@sullana.pe', 'JR. LIBERTAD 496 SULLANA PIURA', 1, '2026-05-25 00:07:06'),
(39, 'PESQUERA PAITA S.A.', '20802678901', '073678901', 'pesca@paita.com', 'AV. INDEPENDENCIA 507 PAITA PIURA', 1, '2026-05-25 00:07:06'),
(40, 'PETROQUIMICA TALARA S.R.L.', '20902789012', '073789012', 'petro@talara.pe', 'CAL. UNION 618 TALARA PIURA', 1, '2026-05-25 00:07:06'),
(41, 'AGRICOLA CHULUCANAS S.A.C.', '20103890123', '074890123', 'agro@chulucanas.com', 'AV. EJERCITO 729 CHULUCANAS PIURA', 1, '2026-05-25 00:07:06'),
(42, 'CERAMICA CATACAOS E.I.R.L.', '20203901234', '073901234', 'ceramica@catacaos.pe', 'JR. AMAZONAS 830 CATACAOS PIURA', 1, '2026-05-25 00:07:06'),
(43, 'AZUCARERA FERRENAFE S.A.', '20303012345', '074012345', 'azucar@ferrenafe.com', 'AV. GRAU 941 FERRENAFE LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(44, 'TEXTIL LAMBAYEQUE S.R.L.', '20403123456', '074123456', 'textil@lambayeque.pe', 'CAL. REAL 052 LAMBAYEQUE LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(45, 'AGROINDUSTRIAL MONSEFÚ S.A.C.', '20503234567', '074234567', 'agro@monsefu.com', 'AV. BOLOGNESI 163 MONSEFÚ LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(46, 'PESQUERA ETEN E.I.R.L.', '20603345678', '074345678', 'pesca@eten.pe', 'JR. UNION 274 ETEN LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(47, 'CONSTRUCTORA REQUE S.A.', '20703456789', '074456789', 'obras@reque.com', 'AV. REPUBLICA 385 REQUE LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(48, 'INMOBILIARIA PIMENTEL S.R.L.', '20803567890', '074567890', 'inmobil@pimentel.pe', 'CAL. PROGRESO 496 PIMENTEL LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(49, 'TURISMO SANTA ROSA S.A.C.', '20903678901', '074678901', 'tours@santarosa.com', 'AV. PERU 507 SANTA ROSA LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(50, 'AGRICOLA POMALCA E.I.R.L.', '20104789012', '074789012', 'agro@pomalca.pe', 'JR. TACNA 618 POMALCA LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(51, 'AZUCARERA TUMAN S.A.', '20204890123', '074890123', 'azucar@tuman.com', 'AV. GRAU 729 TUMAN LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(52, 'AGRICOLA CAYALTI S.R.L.', '20304901234', '074901234', 'agro@cayalti.pe', 'CAL. REAL 830 CAYALTI LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(53, 'MINERA ZAÑA S.A.C.', '20404012345', '074012345', 'mineria@zana.com', 'AV. EJERCITO 941 ZAÑA LAMBAYEQUE', 1, '2026-05-25 00:07:06'),
(54, 'COMERCIAL CHEPEN E.I.R.L.', '20504123456', '044123456', 'comercial@chepen.pe', 'JR. LIBERTAD 052 CHEPEN LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(55, 'AGRICOLA GUADALUPE S.A.', '20604234567', '044234567', 'agro@guadalupe.com', 'AV. INDEPENDENCIA 163 GUADALUPE LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(56, 'PESQUERA PACASMAYO S.R.L.', '20704345678', '044345678', 'pesca@pacasmayo.pe', 'CAL. UNION 274 PACASMAYO LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(57, 'CEMENTERA JEQUETEPEQUE S.A.C.', '20804456789', '044456789', 'cemento@jequetepeque.com', 'AV. BOLOGNESI 385 JEQUETEPEQUE LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(58, 'AGRICOLA ASCOPE E.I.R.L.', '20904567890', '044567890', 'agro@ascope.pe', 'JR. AMAZONAS 496 ASCOPE LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(59, 'PESQUERA VIRU S.A.', '20105678901', '044678901', 'pesca@viru.com', 'AV. GRAU 507 VIRU LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(60, 'AGRICOLA CHAO S.R.L.', '20205789012', '043789012', 'agro@chao.pe', 'CAL. PROGRESO 618 CHAO LA LIBERTAD', 1, '2026-05-25 00:07:06'),
(61, 'PESQUERA HUARMEY S.A.C.', '20305890123', '043890123', 'pesca@huarmey.com', 'AV. PERU 729 HUARMEY ANCASH', 1, '2026-05-25 00:07:06'),
(62, 'CONSTRUCTORA CASMA E.I.R.L.', '20405901234', '043901234', 'obras@casma.pe', 'JR. TACNA 830 CASMA ANCASH', 1, '2026-05-25 00:07:06'),
(63, 'AGRICOLA BARRANCA S.A.', '20505012345', '01234567', 'agro@barranca.com', 'AV. REPUBLICA 941 BARRANCA LIMA', 1, '2026-05-25 00:07:06'),
(64, 'MICROSOFT PERU S.R.L.', '20254138577', '', NULL, 'AV. VICTOR ANDRES BELAUNDE NRO. 147    DPTO. 9, SAN ISIDRO, LIMA, LIMA', 1, '2026-05-25 13:27:17'),
(65, 'REAL PLAZA S.R.L.', '78945612345', '', NULL, '', 0, '2026-05-25 13:34:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_natural`
--

CREATE TABLE `clientes_natural` (
  `id_cliente_natural` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `tipo_documento` varchar(50) NOT NULL DEFAULT 'DNI',
  `documento_identidad` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `estado_cliente` tinyint(4) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `clientes_natural`
--

INSERT INTO `clientes_natural` (`id_cliente_natural`, `nombres`, `apellido_paterno`, `apellido_materno`, `tipo_documento`, `documento_identidad`, `telefono`, `email`, `direccion`, `estado_cliente`, `fecha_registro`) VALUES
(3, 'ADRIAN ALEXANDER', 'ROMERO', 'MENDOZA', 'DNI', '60415173', '939683782', 'adrianlex489@gmail.com', 'BLOCK 12 1235, TUMAN, CHICLAYO, LAMBAYEQUE', 1, '2026-03-15 21:38:48'),
(10, 'ANGELICA', 'PUANCHAR', 'ANAG', 'DNI', '45896375', NULL, NULL, NULL, 1, '2026-05-05 06:37:10'),
(12, 'MARILU ESTHER', 'MENDOZA', 'CULQUI', 'DNI', '16685055', NULL, NULL, 'BLOCK 12 NRO.1235, TUMAN, CHICLAYO, LAMBAYEQUE', 1, '2026-05-15 12:09:38'),
(14, 'FABRICIO DAVID', 'NIETO', 'SIESQUEN', 'DNI', '77072817', NULL, NULL, NULL, 1, '2026-05-21 15:26:13'),
(15, 'CARLOS ALBERTO', 'RAMIREZ', 'FLORES', 'DNI', '10234567', '987654321', 'carlos.ramirez@gmail.com', 'AV. GRAU 123 CHICLAYO', 1, '2026-05-25 00:04:57'),
(16, 'MARIA ELENA', 'TORRES', 'QUISPE', 'DNI', '20345678', '976543210', 'maria.torres@hotmail.com', 'JR. UNION 456 LIMA', 1, '2026-05-25 00:04:57'),
(17, 'JOSE LUIS', 'GARCIA', 'MENDOZA', 'DNI', '30456789', '965432109', 'jose.garcia@gmail.com', 'CAL. LOS PINOS 789 TRUJILLO', 0, '2026-05-25 00:04:57'),
(18, 'ANA PATRICIA', 'LOPEZ', 'VARGAS', 'DNI', '40567890', '954321098', 'ana.lopez@yahoo.com', 'AV. REPUBLICA 321 AREQUIPA', 1, '2026-05-25 00:04:57'),
(19, 'PEDRO ANTONIO', 'CHAVEZ', 'ROJAS', 'DNI', '50678901', '943210987', 'pedro.chavez@gmail.com', 'JR. TACNA 654 PIURA', 0, '2026-05-25 00:04:57'),
(20, 'LUCIA FERNANDA', 'DIAZ', 'CASTILLO', 'DNI', '60789012', '932109876', 'lucia.diaz@hotmail.com', 'AV. BOLOGNESI 987 CUSCO', 0, '2026-05-25 00:04:57'),
(21, 'MIGUEL ANGEL', 'SANCHEZ', 'HERRERA', 'DNI', '70890123', '921098765', 'miguel.sanchez@gmail.com', 'CAL. REAL 147 ICA', 1, '2026-05-25 00:04:57'),
(22, 'ROSA ISABEL', 'GUTIERREZ', 'MORALES', 'DNI', '80901234', '910987654', 'rosa.gutierrez@yahoo.com', 'AV. EJERCITO 258 HUANCAYO', 0, '2026-05-25 00:04:57'),
(23, 'JUAN CARLOS', 'FLORES', 'JIMENEZ', 'DNI', '91012345', '909876543', 'juan.flores@gmail.com', 'JR. AMAZONAS 369 PUCALLPA', 1, '2026-05-25 00:04:57'),
(24, 'CARMEN ROSA', 'REYES', 'PAREDES', 'DNI', '12345670', '998765432', 'carmen.reyes@hotmail.com', 'AV. INDEPENDENCIA 741 TACNA', 1, '2026-05-25 00:04:57'),
(25, 'LUIS ALBERTO', 'VEGA', 'SOTO', 'DNI', '23456701', '987654320', 'luis.vega@gmail.com', 'CAL. PROGRESO 852 TUMBES', 1, '2026-05-25 00:04:57'),
(26, 'PATRICIA MILAGROS', 'RAMOS', 'CRUZ', 'DNI', '34567012', '976543201', 'patricia.ramos@yahoo.com', 'AV. GRAU 963 MOQUEGUA', 1, '2026-05-25 00:04:57'),
(27, 'ROBERTO CARLOS', 'MENDOZA', 'SILVA', 'DNI', '45670123', '965432012', 'roberto.mendoza@gmail.com', 'JR. LIBERTAD 174 PUNO', 1, '2026-05-25 00:04:57'),
(28, 'SILVIA BEATRIZ', 'CASTRO', 'RIOS', 'DNI', '56701234', '954320123', 'silvia.castro@hotmail.com', 'AV. PERU 285 CAJAMARCA', 1, '2026-05-25 00:04:57'),
(29, 'FERNANDO JOSE', 'ORTIZ', 'LEON', 'DNI', '67012345', '943201234', 'fernando.ortiz@gmail.com', 'CAL. UNION 396 AYACUCHO', 1, '2026-05-25 00:04:57'),
(30, 'DIANA CAROLINA', 'PEREZ', 'FUENTES', 'DNI', '78123456', '932012345', 'diana.perez@yahoo.com', 'AV. BOLOGNESI 507 HUARAZ', 1, '2026-05-25 00:04:57'),
(31, 'OSCAR MANUEL', 'ALVA', 'ESPINOZA', 'DNI', '89234567', '921023456', 'oscar.alva@gmail.com', 'JR. GRAU 618 CHIMBOTE', 1, '2026-05-25 00:04:57'),
(32, 'VERONICA SUSANA', 'HUAMAN', 'CONDORI', 'DNI', '90345678', '910234567', 'veronica.huaman@hotmail.com', 'AV. EJERCITO 729 JULIACA', 1, '2026-05-25 00:04:57'),
(33, 'EDGAR MARTIN', 'QUISPE', 'MAMANI', 'DNI', '01456789', '909345678', 'edgar.quispe@gmail.com', 'CAL. REAL 830 ABANCAY', 0, '2026-05-25 00:04:57'),
(34, 'NELLY ROXANA', 'CCOPA', 'APAZA', 'DNI', '12567890', '998456789', 'nelly.ccopa@yahoo.com', 'AV. REPUBLICA 941 HUANUCO', 1, '2026-05-25 00:04:57'),
(35, 'ALEX JUNIOR', 'TAPIA', 'VILLANUEVA', 'DNI', '23678901', '987567890', 'alex.tapia@gmail.com', 'JR. TACNA 052 TINGO MARIA', 1, '2026-05-25 00:04:57'),
(36, 'JESSICA PAOLA', 'MORAN', 'AGUIRRE', 'DNI', '34789012', '976678901', 'jessica.moran@hotmail.com', 'AV. GRAU 163 MOYOBAMBA', 1, '2026-05-25 00:04:57'),
(37, 'CHRISTIAN PAUL', 'SALAZAR', 'CAMPOS', 'DNI', '45890123', '965789012', 'christian.salazar@gmail.com', 'CAL. PROGRESO 274 TARAPOTO', 1, '2026-05-25 00:04:57'),
(38, 'MILAGROS DEL PILAR', 'VASQUEZ', 'ROMERO', 'DNI', '56901234', '954890123', 'milagros.vasquez@yahoo.com', 'AV. PERU 385 IQUITOS', 1, '2026-05-25 00:04:57'),
(39, 'HENRY WILLIAM', 'PALACIOS', 'GUERRERO', 'DNI', '67012346', '943901234', 'henry.palacios@gmail.com', 'JR. LIBERTAD 496 SULLANA', 1, '2026-05-25 00:04:57'),
(40, 'ELIZABETH JANET', 'CANO', 'MEDINA', 'DNI', '78123457', '932012346', 'elizabeth.cano@hotmail.com', 'AV. INDEPENDENCIA 507 PAITA', 1, '2026-05-25 00:04:57'),
(41, 'RONALD DAVID', 'MEZA', 'DELGADO', 'DNI', '89234568', '921123457', 'ronald.meza@gmail.com', 'CAL. UNION 618 TALARA', 1, '2026-05-25 00:04:57'),
(42, 'KARINA LISETH', 'BRAVO', 'PACHECO', 'DNI', '90345679', '910234568', 'karina.bravo@yahoo.com', 'AV. EJERCITO 729 CHULUCANAS', 1, '2026-05-25 00:04:57'),
(43, 'MARCO ANTONIO', 'LEON', 'NAVARRO', 'DNI', '01456780', '909345679', 'marco.leon@gmail.com', 'JR. AMAZONAS 830 CATACAOS', 1, '2026-05-25 00:04:57'),
(44, 'YOLANDA ESPERANZA', 'RIOS', 'CARRASCO', 'DNI', '12567891', '998456780', 'yolanda.rios@hotmail.com', 'AV. GRAU 941 FERRENAFE', 1, '2026-05-25 00:04:57'),
(45, 'JHON ALEXANDER', 'SOLIS', 'PONCE', 'DNI', '23678902', '987567891', 'jhon.solis@gmail.com', 'CAL. REAL 052 LAMBAYEQUE', 1, '2026-05-25 00:04:57'),
(46, 'MARISOL CONSUELO', 'ARCE', 'TELLO', 'DNI', '34789013', '976678902', 'marisol.arce@yahoo.com', 'AV. BOLOGNESI 163 MONSEFÚ', 1, '2026-05-25 00:04:57'),
(47, 'CESAR AUGUSTO', 'INFANTE', 'ZUÑIGA', 'DNI', '45890124', '965789013', 'cesar.infante@gmail.com', 'JR. UNION 274 ETEN', 1, '2026-05-25 00:04:57'),
(48, 'GLADYS MARLENI', 'CORONEL', 'BECERRA', 'DNI', '56901235', '954890124', 'gladys.coronel@hotmail.com', 'AV. REPUBLICA 385 REQUE', 1, '2026-05-25 00:04:57'),
(49, 'WILMER ENRIQUE', 'CUBAS', 'LLONTOP', 'DNI', '67012347', '943901235', 'wilmer.cubas@gmail.com', 'CAL. PROGRESO 496 PIMENTEL', 1, '2026-05-25 00:04:57'),
(50, 'ROSA AMELIA', 'FARRO', 'CHAFLOQUE', 'DNI', '78123458', '932012347', 'rosa.farro@yahoo.com', 'AV. PERU 507 SANTA ROSA', 1, '2026-05-25 00:04:57'),
(51, 'IVAN RODRIGO', 'HEREDIA', 'NIZAMA', 'DNI', '89234569', '921123458', 'ivan.heredia@gmail.com', 'JR. TACNA 618 POMALCA', 1, '2026-05-25 00:04:57'),
(52, 'LOURDES MAGALY', 'IDROGO', 'OBLITAS', 'DNI', '90345670', '910234569', 'lourdes.idrogo@hotmail.com', 'AV. GRAU 729 TUMAN', 1, '2026-05-25 00:04:57'),
(53, 'NELSON JAVIER', 'JULCA', 'PERALTA', 'DNI', '01456781', '909345670', 'nelson.julca@gmail.com', 'CAL. REAL 830 CAYALTI', 1, '2026-05-25 00:04:57'),
(54, 'ANGELA NOEMI', 'KAWAKAMI', 'QUIROZ', 'DNI', '12567892', '998456781', 'angela.kawakami@yahoo.com', 'AV. EJERCITO 941 ZAÑA', 1, '2026-05-25 00:04:57'),
(55, 'PAUL GIANCARLO', 'LLAUCE', 'RIVADENEIRA', 'DNI', '23678903', '987567892', 'paul.llauce@gmail.com', 'JR. LIBERTAD 052 CHEPEN', 1, '2026-05-25 00:04:57'),
(56, 'DIANA MILAGROS', 'MANAYAY', 'SANTISTEBAN', 'DNI', '34789014', '976678903', 'diana.manayay@hotmail.com', 'AV. INDEPENDENCIA 163 GUADALUPE', 1, '2026-05-25 00:04:57'),
(57, 'FRANK JUNIOR', 'NEYRA', 'TIRADO', 'DNI', '45890125', '965789014', 'frank.neyra@gmail.com', 'CAL. UNION 274 PACASMAYO', 1, '2026-05-25 00:04:57'),
(58, 'SANDRA ELIZABETH', 'OLIVA', 'URQUIAGA', 'DNI', '56901236', '954890125', 'sandra.oliva@yahoo.com', 'AV. BOLOGNESI 385 JEQUETEPEQUE', 1, '2026-05-25 00:04:57'),
(59, 'VICTOR HUGO', 'PINTADO', 'VALLEJOS', 'DNI', '67012348', '943901236', 'victor.pintado@gmail.com', 'JR. AMAZONAS 496 ASCOPE', 1, '2026-05-25 00:04:57'),
(60, 'WENDY CAROLINA', 'QUIROZ', 'WESTER', 'DNI', '78123459', '932012348', 'wendy.quiroz@hotmail.com', 'AV. GRAU 507 VIRU', 1, '2026-05-25 00:04:57'),
(61, 'XAVIER ANTONIO', 'RODRIGUEZ', 'YENQUE', 'DNI', '89234560', '921123459', 'xavier.rodriguez@gmail.com', 'CAL. PROGRESO 618 CHAO', 1, '2026-05-25 00:04:57'),
(62, 'YESENIA PAOLA', 'SUAREZ', 'ZEVALLOS', 'DNI', '90345671', '910234560', 'yesenia.suarez@yahoo.com', 'AV. PERU 729 HUARMEY', 1, '2026-05-25 00:04:57'),
(63, 'ZOILA ESPERANZA', 'TAFUR', 'ALVARADO', 'DNI', '01456782', '909345671', 'zoila.tafur@gmail.com', 'JR. TACNA 830 CASMA', 1, '2026-05-25 00:04:57'),
(64, 'BRAYAN SMITH', 'UGAZ', 'BUSTAMANTE', 'DNI', '12567893', '998456782', 'brayan.ugaz@hotmail.com', 'AV. REPUBLICA 941 BARRANCA', 1, '2026-05-25 00:04:57'),
(65, 'EDITH GABRIELA', 'CRISOSTOMO', 'AGUILAR', 'DNI', '78945612', '', NULL, 'AHM. HUAYCAN F UCV 95 LT 58, ATE, LIMA, LIMA', 1, '2026-05-25 13:26:09'),
(66, 'MIGUEL', 'PEREZ', 'GOMEZ', 'DNI', '56987412', '.', NULL, 'CHICLAYO', 1, '2026-05-25 13:31:53'),
(67, 'JORGE LUIS FERNANDO', 'CHAVEZ', 'ESQUIVES', 'DNI', '41781189', NULL, NULL, 'CALLE FRANCISCO CABRERA 274, CHICLAYO, CHICLAYO, LAMBAYEQUE', 1, '2026-05-26 18:39:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `tipo_comprobante` enum('ticket','nota') DEFAULT 'ticket',
  `numero_comprobante` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT NULL,
  `aplica_igv` tinyint(4) DEFAULT NULL,
  `igv` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `saldo_pendiente` decimal(10,2) DEFAULT 0.00,
  `tipo_pago` enum('contado','credito') DEFAULT NULL,
  `metodo_pago` enum('efectivo','transferencia','yape','plin','tarjeta') DEFAULT 'efectivo',
  `estado` varchar(20) DEFAULT 'pagado',
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `compras`
--

INSERT INTO `compras` (`id_compra`, `id_proveedor`, `id_usuario`, `id_caja`, `fecha`, `tipo_comprobante`, `numero_comprobante`, `subtotal`, `descuento`, `aplica_igv`, `igv`, `total`, `saldo_pendiente`, `tipo_pago`, `metodo_pago`, `estado`, `observacion`) VALUES
(3, 5, 1, NULL, '2026-05-17 16:16:00', 'ticket', 'T001-00000001', 2300.00, 0.00, 1, 414.00, 2714.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(4, 1, 1, 1, '2026-05-23 20:56:00', 'ticket', 'T001-00000002', 187.20, 0.00, 1, 33.70, 220.90, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(5, 25, 1, 3, '2026-05-25 11:11:00', 'ticket', 'T001-00000003', 2300.00, 0.00, 1, 414.00, 2714.00, 678.50, 'credito', 'transferencia', 'pendiente', NULL),
(6, 2, 1, 4, '2026-05-27 06:14:00', 'ticket', 'T001-00000004', 200.00, 0.00, 1, 36.00, 236.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(7, 2, 1, 4, '2026-05-27 06:19:00', 'ticket', 'T001-00000005', 200.00, 0.00, 1, 36.00, 236.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(8, 2, 1, 4, '2026-05-27 06:20:00', 'ticket', 'T001-00000006', 1000.00, 0.00, 0, 0.00, 1000.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(9, 54, 1, 4, '2026-05-27 06:22:00', 'ticket', 'T001-00000007', 187.20, 0.00, 0, 0.00, 187.20, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(10, 25, 1, 4, '2026-05-27 06:23:00', 'ticket', 'T001-00000008', 200.00, 0.00, 0, 0.00, 200.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(11, 56, 1, 4, '2026-05-27 06:31:00', 'ticket', 'T001-00000009', 800.00, 0.00, 1, 144.00, 944.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(12, 2, 1, 4, '2026-05-27 06:41:00', 'ticket', 'T001-00000010', 200.00, 0.00, 0, 0.00, 200.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(13, 2, 1, 4, '2026-05-27 06:44:00', 'ticket', 'T001-00000011', 200.00, 0.00, 0, 0.00, 200.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(14, 2, 1, 4, '2026-05-27 06:49:00', 'ticket', 'T001-00000012', 200.00, 0.00, 0, 0.00, 200.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(15, 2, 1, 4, '2026-05-27 06:53:00', 'ticket', 'T001-00000013', 100.00, 0.00, 0, 0.00, 100.00, 0.00, 'contado', 'efectivo', 'pagado', NULL),
(16, 2, 1, 4, '2026-05-27 06:56:00', 'ticket', 'T001-00000014', 200.00, 0.00, 0, 0.00, 200.00, 0.00, 'contado', 'efectivo', 'pagado', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_compra`
--

CREATE TABLE `cuotas_compra` (
  `id_cuota` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `numero_cuota` int(11) DEFAULT NULL,
  `monto_cuota` decimal(10,2) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('pendiente','pagado','vencido') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `cuotas_compra`
--

INSERT INTO `cuotas_compra` (`id_cuota`, `id_compra`, `numero_cuota`, `monto_cuota`, `fecha_vencimiento`, `estado`) VALUES
(1, 5, 1, 678.50, '2026-05-25', 'pagado'),
(2, 5, 2, 678.50, '2026-06-24', 'pagado'),
(3, 5, 3, 678.50, '2026-07-24', 'pagado'),
(4, 5, 4, 678.50, '2026-08-23', 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_venta`
--

CREATE TABLE `cuotas_venta` (
  `id_cuota` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `numero_cuota` int(11) DEFAULT NULL,
  `monto_cuota` decimal(10,2) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('pendiente','pagado','vencido') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `cuotas_venta`
--

INSERT INTO `cuotas_venta` (`id_cuota`, `id_venta`, `numero_cuota`, `monto_cuota`, `fecha_vencimiento`, `estado`) VALUES
(1, 5, 1, 153.40, '2026-06-24', 'pagado'),
(2, 5, 2, 153.40, '2026-07-24', 'pagado'),
(3, 5, 3, 153.40, '2026-08-23', 'pagado'),
(4, 8, 1, 855.50, '2026-06-27', 'pagado'),
(5, 8, 2, 855.50, '2026-07-27', 'pagado'),
(6, 8, 3, 855.50, '2026-08-26', 'pagado'),
(7, 8, 4, 855.50, '2026-09-25', 'pagado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id_detalle` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_compra` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `id_cotizacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_compra`
--

INSERT INTO `detalle_compra` (`id_detalle`, `id_compra`, `id_producto`, `cantidad`, `precio_compra`, `descuento`, `subtotal`, `id_cotizacion`) VALUES
(18, 3, 1, 1, 2300.00, 0.00, 2300.00, NULL),
(19, 4, 2, 2, 93.60, 0.00, 187.20, NULL),
(20, 5, 1, 1, 2300.00, 0.00, 2300.00, NULL),
(21, 6, 3, 2, 100.00, 0.00, 200.00, NULL),
(22, 7, 3, 2, 100.00, 0.00, 200.00, NULL),
(23, 8, 3, 10, 100.00, 0.00, 1000.00, NULL),
(24, 9, 2, 2, 93.60, 0.00, 187.20, NULL),
(25, 10, 3, 2, 100.00, 0.00, 200.00, NULL),
(26, 11, 3, 8, 100.00, 0.00, 800.00, NULL),
(27, 12, 4, 2, 100.00, 0.00, 200.00, NULL),
(28, 13, 4, 2, 100.00, 0.00, 200.00, NULL),
(29, 14, 4, 2, 100.00, 0.00, 200.00, NULL),
(30, 15, 4, 1, 100.00, 0.00, 100.00, NULL),
(31, 16, 4, 2, 100.00, 0.00, 200.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_orden`
--

CREATE TABLE `detalle_orden` (
  `id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_servicio` int(11) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `cantidad` int(11) DEFAULT 1,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_orden`
--

INSERT INTO `detalle_orden` (`id`, `id_orden`, `id_servicio`, `descripcion`, `precio`, `cantidad`, `subtotal`) VALUES
(3, 1, 2, 'Formateo de PC', 80.00, 1, 80.00),
(4, 1, 1, 'Instalación de Programas', 60.00, 1, 60.00),
(7, 2, 13, 'Cambio de Batería', 100.00, 1, 100.00),
(8, 2, 10, 'Cambio de Pantalla', 180.00, 1, 180.00),
(9, 3, 4, 'Cambio de Disco HDD a SSD', 120.00, 1, 120.00),
(10, 3, 11, 'Cambio de Teclado', 120.00, 1, 120.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`id_detalle`, `id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `descuento`, `subtotal`) VALUES
(1, 1, 2, 5, 130.00, 50.00, 600.00),
(2, 2, 2, 3, 130.00, 0.00, 390.00),
(3, 3, 2, 3, 130.00, 0.00, 390.00),
(4, 4, 2, 3, 130.00, 0.00, 390.00),
(5, 5, 2, 3, 130.00, 0.00, 390.00),
(6, 6, 1, 1, 2900.00, 0.00, 2900.00),
(7, 7, 1, 2, 2900.00, 0.00, 5800.00),
(8, 7, 2, 10, 130.00, 0.00, 1300.00),
(9, 8, 1, 1, 2900.00, 0.00, 2900.00);

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
(0, 'INVERSIONES CH COMPUTER SRL', NULL, '20479894699', 'CAL. JOSE FRANCISCO CABRERA NRO. 274', NULL, 'CHICLAYO', 'LAMBAYEQUE', '939683782', NULL, NULL, '/sysinversioneschcomputer/Logo/logo_empresa.jpg', 18.00, 'T001', 'N001', 'Gracias por su preferencia', '2026-05-17 08:32:49'),
(0, 'INVERSIONES CH COMPUTER SRL', 'SYSINVERSIONES CH COMPUTER', '20479894699', 'CAL. JOSE FRANCISCO CABRERA NRO. 274', NULL, 'CHICLAYO', 'LAMBAYEQUE', '939683782', NULL, NULL, '/sysinversioneschcomputer/Logo/logo_empresa.jpg', 18.00, 'T001', 'N001', 'Gracias por su preferencia', '2026-05-17 08:33:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `tipo_cliente` enum('natural','empresa') NOT NULL DEFAULT 'natural',
  `tipo` varchar(50) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `accesorios` varchar(255) DEFAULT NULL COMMENT 'Ej: cargador, mouse, funda',
  `estado_fisico` text DEFAULT NULL COMMENT 'Descripción del estado físico al ingreso',
  `contrasena_equipo` varchar(100) DEFAULT NULL COMMENT 'Contraseña del equipo, puede ser completada por el técnico',
  `fotos_ingreso` varchar(255) DEFAULT NULL COMMENT 'Ruta de fotos al ingresar el equipo',
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`id_equipo`, `id_cliente`, `tipo_cliente`, `tipo`, `marca`, `modelo`, `numero_serie`, `accesorios`, `estado_fisico`, `contrasena_equipo`, `fotos_ingreso`, `estado`) VALUES
(1, 10, 'natural', 'Laptop', 'Lenovo', 'ThinkPad X1 Carbon Gen 12', 'CND1234567', 'llegó el equipo con su cargador', '-Un poco viejito', NULL, '[\"uploads\\/equipos\\/equipo_1_69fa3a210b039.jpg\",\"uploads\\/equipos\\/equipo_1_69fa3a210b522.jpg\"]', 1),
(3, 3, 'natural', 'Laptop', 'LENOVO', '15-FD0350LA', 'CND1234567', 'cargador, bunda, mouse', 'Aparentemente en buen estado', 'adrian123456', '[\"uploads\\/equipos\\/equipo_3_5c2436917f.webp\",\"uploads\\/equipos\\/equipo_3_f7e66f422f.webp\",\"uploads\\/equipos\\/equipo_3_e7af6c5c1f.webp\"]', 1),
(4, 13, 'empresa', 'PC', 'LENOVO', 'MS1033', 'CND789456', 'ninguno', 'nuevo', NULL, NULL, 1),
(5, 3, 'natural', 'Monitor', 'LENOVO', 'JUPITER K04', 'CND1234567', '', '', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id_movimiento` int(11) NOT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `tipo_referencia` varchar(30) DEFAULT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo` enum('ingreso','egreso') DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') DEFAULT 'efectivo',
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `movimientos_caja`
--

INSERT INTO `movimientos_caja` (`id_movimiento`, `id_caja`, `tipo_referencia`, `id_referencia`, `id_usuario`, `tipo`, `descripcion`, `observacion`, `monto`, `metodo_pago`, `fecha`) VALUES
(1, 1, 'venta', 4, 1, 'ingreso', 'Venta #4 - Nota de Venta N001-00000001', NULL, 460.20, 'efectivo', '2026-05-23 20:30:53'),
(2, 1, 'servicio', 1, 1, 'ingreso', 'Cobro servicio ORD-000001', NULL, 140.00, 'efectivo', '2026-05-23 20:50:12'),
(3, 1, 'compra', 4, 1, 'egreso', 'Compra #4 - Ticket de Compra T001-00000002', NULL, 220.90, 'efectivo', '2026-05-23 20:56:57'),
(5, 2, 'venta', 5, 1, 'ingreso', 'Abono crédito VTA-000005', NULL, 153.40, 'efectivo', '2026-05-25 09:05:11'),
(6, 2, 'venta', 5, 1, 'ingreso', 'Abono crédito VTA-000005', NULL, 153.40, 'efectivo', '2026-05-25 09:05:37'),
(7, 3, 'compra', 5, 1, 'egreso', 'Pago crédito proveedor COMP-000005', NULL, 678.50, 'efectivo', '2026-05-25 11:12:36'),
(8, 3, 'venta', 6, 1, 'ingreso', 'Venta #6 - Ticket de Venta T001-00000005', NULL, 3422.00, 'efectivo', '2026-05-25 11:13:49'),
(9, 4, 'venta', 7, 1, 'ingreso', 'Venta #7 - Ticket de Venta T001-00000006', NULL, 7100.00, 'efectivo', '2026-05-26 18:42:05'),
(10, 4, 'compra', 5, 1, 'egreso', 'Pago crédito proveedor COMP-000005', NULL, 678.50, 'efectivo', '2026-05-26 18:53:55'),
(11, 4, 'manual', NULL, 1, 'egreso', 'Pago de luz', NULL, 50.00, 'efectivo', '2026-05-26 19:55:40'),
(12, 4, 'compra', 6, 1, 'egreso', 'Compra #6 - Ticket de Compra T001-00000004', NULL, 236.00, 'efectivo', '2026-05-27 06:14:42'),
(13, 4, 'compra', 7, 1, 'egreso', 'Compra #7 - Ticket de Compra T001-00000005', NULL, 236.00, 'efectivo', '2026-05-27 06:19:54'),
(14, 4, 'compra', 8, 1, 'egreso', 'Compra #8 - Ticket de Compra T001-00000006', NULL, 1000.00, 'efectivo', '2026-05-27 06:20:44'),
(15, 4, 'compra', 9, 1, 'egreso', 'Compra #9 - Ticket de Compra T001-00000007', NULL, 187.20, 'efectivo', '2026-05-27 06:22:25'),
(16, 4, 'compra', 10, 1, 'egreso', 'Compra #10 - Ticket de Compra T001-00000008', NULL, 200.00, 'efectivo', '2026-05-27 06:23:26'),
(17, 4, 'compra', 11, 1, 'egreso', 'Compra #11 - Ticket de Compra T001-00000009', NULL, 944.00, 'efectivo', '2026-05-27 06:31:18'),
(18, 4, 'compra', 12, 1, 'egreso', 'Compra #12 - Ticket de Compra T001-00000010', NULL, 200.00, 'efectivo', '2026-05-27 06:41:46'),
(19, 4, 'compra', 13, 1, 'egreso', 'Compra #13 - Ticket de Compra T001-00000011', NULL, 200.00, 'efectivo', '2026-05-27 06:44:53'),
(20, 4, 'compra', 14, 1, 'egreso', 'Compra #14 - Ticket de Compra T001-00000012', NULL, 200.00, 'efectivo', '2026-05-27 06:50:15'),
(21, 4, 'compra', 15, 1, 'egreso', 'Compra #15 - Ticket de Compra T001-00000013', NULL, 100.00, 'efectivo', '2026-05-27 06:54:01'),
(22, 4, 'compra', 16, 1, 'egreso', 'Compra #16 - Ticket de Compra T001-00000014', NULL, 200.00, 'efectivo', '2026-05-27 06:57:08'),
(23, 4, 'compra', 5, 1, 'egreso', 'Pago crédito proveedor COMP-000005', NULL, 678.50, 'efectivo', '2026-05-27 07:15:32'),
(24, 4, 'venta', 8, 1, 'ingreso', 'Abono crédito VTA-000008', NULL, 855.50, 'efectivo', '2026-05-27 07:20:14'),
(25, 4, 'manual', NULL, 1, 'ingreso', 'Pago al dueño de negocio', NULL, 500.00, 'efectivo', '2026-05-27 07:20:54'),
(26, 4, 'servicio', 2, 1, 'ingreso', 'Cobro servicio ORD-000002', NULL, 1267.20, 'efectivo', '2026-05-27 07:31:36'),
(27, 4, 'venta', 8, 1, 'ingreso', 'Abono cr?dito VTA-000008', NULL, 855.50, 'yape', '2026-05-27 07:49:40'),
(28, 4, 'venta', 8, 1, 'ingreso', 'Abono crédito VTA-000008', NULL, 855.50, 'transferencia', '2026-05-27 07:50:55'),
(29, 4, 'venta', 8, 1, 'ingreso', 'Abono crédito VTA-000008', NULL, 855.50, 'yape', '2026-05-27 08:09:03'),
(30, 4, 'servicio', 3, 1, 'ingreso', 'Cobro servicio ORD-000003', NULL, 340.00, 'yape', '2026-05-29 22:53:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id_movimiento` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo` enum('compra','venta','ajuste','servicio') DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `movimientos_inventario`
--

INSERT INTO `movimientos_inventario` (`id_movimiento`, `id_producto`, `id_usuario`, `tipo`, `cantidad`, `descripcion`, `fecha`) VALUES
(1, 1, 1, 'ajuste', 1, 'Corrección de inventario', '2026-05-17 23:40:58'),
(2, 1, 1, 'ajuste', 102, 'Corrección de inventario', '2026-05-26 18:37:13'),
(3, 2, 1, 'ajuste', 100, 'corrección de inventario', '2026-05-26 18:49:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_servicio`
--

CREATE TABLE `ordenes_servicio` (
  `id_orden` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `tipo_cliente` enum('natural','empresa') NOT NULL DEFAULT 'natural',
  `id_usuario` int(11) DEFAULT NULL,
  `id_tecnico` int(11) DEFAULT NULL COMMENT 'Técnico que tomó la orden',
  `id_equipo` int(11) DEFAULT NULL,
  `fecha_recepcion` datetime DEFAULT NULL,
  `fecha_entrega_estimada` datetime DEFAULT NULL,
  `descripcion_componentes` text DEFAULT NULL,
  `prioridad` enum('baja','normal','media','alta') DEFAULT 'normal',
  `problema_reportado` text DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL COMMENT 'Notas adicionales registradas en recepción',
  `diagnostico` text DEFAULT NULL,
  `solucion` text DEFAULT NULL,
  `fotos_taller` text DEFAULT NULL COMMENT 'JSON array con rutas de fotos tomadas durante el servicio técnico',
  `estado` enum('recibido','diagnostico','en_proceso','listo','entregado','cancelado') DEFAULT NULL,
  `costo_total` decimal(10,2) DEFAULT NULL,
  `estado_pago` enum('pendiente','pagado','sin_cobro') DEFAULT 'pendiente' COMMENT 'Estado del cobro del servicio',
  `saldo_pendiente` decimal(10,2) DEFAULT 0.00 COMMENT 'Saldo pendiente de cobro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `ordenes_servicio`
--

INSERT INTO `ordenes_servicio` (`id_orden`, `id_cliente`, `tipo_cliente`, `id_usuario`, `id_tecnico`, `id_equipo`, `fecha_recepcion`, `fecha_entrega_estimada`, `descripcion_componentes`, `prioridad`, `problema_reportado`, `observacion`, `diagnostico`, `solucion`, `fotos_taller`, `estado`, `costo_total`, `estado_pago`, `saldo_pendiente`) VALUES
(1, 3, 'natural', 1, 1, 3, '2026-05-19 15:05:48', '2026-05-20 00:00:00', 'PROCESADOR: 12th Gen Intel(R) Core(TM) i5-12450H, RAM: 8GB , ALMACENAMIENTO: 477 GB, TARJETA GRÁFICA: NVIDIA GeForce RTX 3050 6GB Laptop GPU (6 GB)\r\nIntel(R) UHD Graphics (128 MB), WINDOWS: Windows 11 Home Single Language', 'normal', 'No enciende, mantenimiento', 'Ninguna', 'El equipo presentaba bajo rendimiento en el sistema, lentitud al ejecutar programas y archivos innecesarios que afectaban su funcionamiento normal.', 'Se realizó el formateo completo del equipo para eliminar archivos innecesarios, errores del sistema y posibles fallas de software que afectaban su rendimiento. Posteriormente, se instaló nuevamente el sistema operativo junto con sus respectivos controladores y programas básicos, dejando el equipo configurado y funcionando correctamente.', NULL, 'entregado', 140.00, 'pagado', 0.00),
(2, 13, 'empresa', 1, 2, 4, '2026-05-20 18:32:59', '2026-05-22 00:00:00', NULL, 'normal', 'no enciende', '', NULL, NULL, NULL, 'entregado', 1267.20, 'pagado', 0.00),
(3, 3, 'natural', 1, 1, 5, '2026-05-25 10:19:21', '2026-05-25 00:00:00', NULL, 'normal', '', '', NULL, NULL, NULL, 'entregado', 340.00, 'pagado', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_cotizaciones`
--

CREATE TABLE `orden_cotizaciones` (
  `id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Nombre/descripción del repuesto cotizado',
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `estado` enum('cotizado','aprobado','rechazado','comprado','pendiente_compra','completado') NOT NULL DEFAULT 'cotizado',
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Técnico o usuario que registró la cotización',
  `fecha_cotizacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora en que se generó la cotización',
  `nota` varchar(255) DEFAULT NULL COMMENT 'Observación adicional sobre el repuesto o la cotización'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `orden_cotizaciones`
--

INSERT INTO `orden_cotizaciones` (`id`, `id_orden`, `id_producto`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`, `estado`, `id_usuario`, `fecha_cotizacion`, `nota`) VALUES
(4, 2, 2, 'REDRAGON - MOUSE INVADER', 2, 93.60, 187.20, 'completado', 1, '2026-05-26 19:30:06', NULL),
(9, 2, 3, 'BATERIA COMPATIBLE DE LAPTOP TOSHIBA INTERNA 5107', 2, 100.00, 200.00, 'completado', 1, '2026-05-27 06:14:04', NULL),
(12, 2, 4, 'TECLADO COMPATIBLE DE LAPTOP HP', 2, 100.00, 200.00, 'completado', 1, '2026-05-27 06:41:23', NULL),
(14, 2, 4, 'TECLADO COMPATIBLE DE LAPTOP HP', 2, 100.00, 200.00, 'completado', 1, '2026-05-27 06:49:48', NULL),
(15, 2, 4, 'TECLADO COMPATIBLE DE LAPTOP HP', 1, 100.00, 100.00, 'completado', 1, '2026-05-27 06:53:35', NULL),
(17, 2, 4, 'TECLADO COMPATIBLE DE LAPTOP HP', 1, 100.00, 100.00, 'completado', 1, '2026-05-27 06:56:40', NULL),
(18, 3, 3, 'BATERIA COMPATIBLE DE LAPTOP TOSHIBA INTERNA 5107', 1, 100.00, 100.00, 'completado', 1, '2026-05-29 22:52:32', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_compra`
--

CREATE TABLE `pagos_compra` (
  `id_pago` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `metodo_pago` enum('efectivo','transferencia','yape','plin','tarjeta') DEFAULT 'efectivo',
  `monto` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `observacion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `pagos_compra`
--

INSERT INTO `pagos_compra` (`id_pago`, `id_compra`, `id_usuario`, `metodo_pago`, `monto`, `fecha`, `observacion`) VALUES
(1, 5, 1, 'efectivo', 678.50, '2026-05-25 11:12:36', NULL),
(2, 5, 1, 'efectivo', 678.50, '2026-05-26 18:53:55', NULL),
(3, 5, 1, 'efectivo', 678.50, '2026-05-27 07:15:32', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_servicio`
--

CREATE TABLE `pagos_servicio` (
  `id_pago` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Usuario que registró el pago',
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') DEFAULT 'efectivo',
  `monto` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `pagos_servicio`
--

INSERT INTO `pagos_servicio` (`id_pago`, `id_orden`, `id_usuario`, `metodo_pago`, `monto`, `fecha`, `observacion`) VALUES
(1, 1, 1, 'efectivo', 140.00, '2026-05-23 20:50:12', NULL),
(2, 2, 1, 'efectivo', 1267.20, '2026-05-27 07:31:36', NULL),
(3, 3, 1, 'yape', 340.00, '2026-05-29 22:53:46', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_venta`
--

CREATE TABLE `pagos_venta` (
  `id_pago` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') DEFAULT 'efectivo',
  `monto` decimal(10,2) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `pagos_venta`
--

INSERT INTO `pagos_venta` (`id_pago`, `id_venta`, `id_usuario`, `metodo_pago`, `monto`, `observacion`, `fecha`) VALUES
(1, 5, 1, 'efectivo', 153.40, NULL, '2026-05-25 06:51:58'),
(2, 5, 1, 'efectivo', 153.40, NULL, '2026-05-25 09:05:11'),
(3, 5, 1, 'efectivo', 153.40, NULL, '2026-05-25 09:05:37'),
(4, 8, 1, 'efectivo', 855.50, NULL, '2026-05-27 07:20:14'),
(5, 8, 1, 'yape', 855.50, NULL, '2026-05-27 07:49:40'),
(6, 8, 1, 'transferencia', 855.50, NULL, '2026-05-27 07:50:55'),
(7, 8, 1, 'yape', 855.50, NULL, '2026-05-27 08:09:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_usuario`
--

CREATE TABLE `permisos_usuario` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `modulo` varchar(60) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `permisos_usuario`
--

INSERT INTO `permisos_usuario` (`id`, `id_usuario`, `modulo`, `permitido`) VALUES
(145, 2, 'servicios', 1),
(146, 2, 'taller', 1),
(147, 2, 'cobro_servicio', 1),
(148, 2, 'caja', 0),
(149, 2, 'historial_caja', 0),
(150, 2, 'ventas', 0),
(151, 2, 'cobro_ventas', 0),
(152, 2, 'historial_ventas', 0),
(153, 2, 'compras', 0),
(154, 2, 'cobro_compras', 0),
(155, 2, 'historial_compras', 0),
(156, 2, 'inventario', 0),
(157, 2, 'productos', 1),
(158, 2, 'categorias', 1),
(159, 2, 'catalogo_servicios', 1),
(160, 2, 'clientes', 1),
(161, 2, 'proveedores', 0),
(162, 2, 'empresa', 0),
(181, 3, 'servicios', 0),
(182, 3, 'taller', 0),
(183, 3, 'cobro_servicio', 0),
(184, 3, 'caja', 1),
(185, 3, 'historial_caja', 0),
(186, 3, 'ventas', 1),
(187, 3, 'cobro_ventas', 1),
(188, 3, 'historial_ventas', 1),
(189, 3, 'compras', 1),
(190, 3, 'cobro_compras', 1),
(191, 3, 'historial_compras', 1),
(192, 3, 'inventario', 0),
(193, 3, 'productos', 1),
(194, 3, 'categorias', 1),
(195, 3, 'catalogo_servicios', 0),
(196, 3, 'clientes', 1),
(197, 3, 'proveedores', 1),
(198, 3, 'empresa', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre_producto` varchar(100) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) DEFAULT NULL,
  `stock_maximo` int(11) DEFAULT NULL,
  `precio_compra` decimal(10,2) DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `imagenes` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `codigo`, `nombre_producto`, `marca`, `modelo`, `descripcion`, `id_categoria`, `id_proveedor`, `stock`, `stock_minimo`, `stock_maximo`, `precio_compra`, `precio_venta`, `imagenes`, `estado`, `fecha_registro`) VALUES
(1, 'PRD-0FA76B', 'LAPTOP HP 15.6\" WINDOWS 11 HOME', 'HP', '15-FD0350LA', 'Laptop HP 15, tu compañera para todo lo que haces. Con batería que aguanta tu ritmo y carga rápida para seguir sin pausas, te ofrece la potencia que necesitas para crear, estudiar y disfrutar sin límites.', 1, 2, 97, 20, 80, 2300.00, 2900.00, '/sysinversioneschcomputer/public/uploads/productos/prod_6a08b7ec73002.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08b7ec72479.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08b7ec71dd5.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08b7ec704e4.webp', 1, '2026-03-16 02:12:12'),
(2, 'PRD-136C85', 'REDRAGON - MOUSE INVADER', 'Redragon', 'M719', NULL, 3, 1, 100, 20, 80, 93.60, 130.00, '/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef61497ba.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef6149b16.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef6149985.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef614957f.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef6149068.webp,/sysinversioneschcomputer/public/uploads/productos/prod_6a08ef6149ce4.webp', 1, '2026-03-17 14:49:58'),
(3, 'PRD-319F38', 'BATERIA COMPATIBLE DE LAPTOP TOSHIBA INTERNA 5107', 'Lenovo', '15-FD0350LA', NULL, 5, 12, 23, 5, 50, 100.00, 120.00, '/sysinversioneschcomputer/public/uploads/productos/prod_6a16d1cf9af0e.jpeg,/sysinversioneschcomputer/public/uploads/productos/prod_6a16d1cf9bc58.webp', 1, '2026-05-27 06:13:19'),
(4, 'PRD-E5C097', 'TECLADO COMPATIBLE DE LAPTOP HP', 'HP', '15-FD0350LA', NULL, 2, 56, 1, 5, 100, 100.00, 120.00, '/sysinversioneschcomputer/public/uploads/productos/prod_6a16d848f05f4.png,/sysinversioneschcomputer/public/uploads/productos/prod_6a17031f90abe.png', 1, '2026-05-27 06:40:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `razon_social` varchar(100) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `razon_social`, `ruc`, `telefono`, `email`, `direccion`, `contacto`, `estado`, `fecha_registro`) VALUES
(1, 'HIPERMERCADOS TOTTUS S.A', '20508565934', '939683782', 'totus@gmail.com', 'AV. ANGAMOS ESTE NRO. 1805 INT. P10, SURQUILLO, LIMA, LIMA', '', 1, '2026-03-16 00:43:29'),
(2, 'INVERSIONES CH COMPUTER SRL', '20479894699', '939683782', 'inversiones123@gmail.com', 'CAL. JOSE FRANCISCO CABRERA NRO. 274  CERCADO DE CHICLAYO, CHICLAYO, CHICLAYO, LAMBAYEQUE', NULL, 1, '2026-03-15 18:51:28'),
(3, 'CORPORACION KEVITANA S.A.C.', '20611967374', '', NULL, 'AV. SANTA ROSA DE LIMA NRO. 1698      VILLA FLORES, SAN JUAN DE LURIGANCHO, LIMA, LIMA', '', 1, '2026-05-15 10:55:01'),
(5, 'GRUPO DELTRON S.A.', '20212331377', NULL, NULL, 'CAL. RAUL REBAGLIATI NRO. 170      SANTA CATALINA, LA VICTORIA, LIMA, LIMA', NULL, 1, NULL),
(6, 'DISTRIBUIDORA NORTE S.A.C.', '20100111001', '074234501', 'ventas@distnorte.com', 'AV. GRAU 101 CHICLAYO', 'CARLOS RAMIREZ', 1, '2026-05-25 05:39:28'),
(7, 'IMPORTACIONES DEL PACIFICO E.I.R.L.', '20100222002', '01234502', 'info@imppacific.com', 'JR. UNION 202 LIMA', 'MARIA TORRES', 1, '2026-05-25 05:39:28'),
(8, 'COMERCIAL ANDINA S.R.L.', '20100333003', '054345603', 'comercial@andina.pe', 'CAL. LOS PINOS 303 AREQUIPA', 'JOSE GARCIA', 1, '2026-05-25 05:39:28'),
(9, 'TECNOLOGIA AVANZADA S.A.C.', '20100444004', '073456704', 'tech@avanzada.com', 'AV. REPUBLICA 404 PIURA', 'ANA LOPEZ', 1, '2026-05-25 05:39:28'),
(10, 'GRUPO EMPRESARIAL NORTE S.A.', '20100555005', '044567805', 'gerencia@genorte.com', 'JR. TACNA 505 TRUJILLO', 'PEDRO CHAVEZ', 1, '2026-05-25 05:39:28'),
(11, 'FERRETERIA INDUSTRIAL PERU E.I.R.L.', '20100666006', '056678906', 'ventas@ferriperu.com', 'CAL. REAL 606 ICA', 'LUCIA DIAZ', 1, '2026-05-25 05:39:28'),
(12, 'AGRO EXPORTACIONES S.A.C.', '20100777007', '064789007', 'export@agroexp.com', 'AV. EJERCITO 707 HUANCAYO', 'MIGUEL SANCHEZ', 1, '2026-05-25 05:39:28'),
(13, 'LOGISTICA AMAZONICA S.R.L.', '20100888008', '061890108', 'logistica@amazonica.pe', 'JR. AMAZONAS 808 PUCALLPA', 'ROSA GUTIERREZ', 1, '2026-05-25 05:39:28'),
(14, 'CONSTRUCTORA NACIONAL S.A.', '20100999009', '052901209', 'obras@consnac.com', 'AV. INDEPENDENCIA 909 TACNA', 'JUAN FLORES', 1, '2026-05-25 05:39:28'),
(15, 'MINERA DEL SUR E.I.R.L.', '20101010010', '072012310', 'mineria@delsur.com', 'CAL. PROGRESO 1010 MOQUEGUA', 'CARMEN REYES', 1, '2026-05-25 05:39:28'),
(16, 'PESQUERA NORTE S.A.C.', '20101111011', '053123411', 'pesca@norte.com', 'AV. GRAU 1111 PIURA', 'LUIS VEGA', 1, '2026-05-25 05:39:28'),
(17, 'TEXTIL PERUANO S.R.L.', '20101222012', '051234512', 'textil@peruano.pe', 'JR. LIBERTAD 1212 PUNO', 'PATRICIA RAMOS', 1, '2026-05-25 05:39:28'),
(18, 'CAFETALERA ANDINA S.A.C.', '20101333013', '076345613', 'cafe@andina.com', 'AV. PERU 1313 CAJAMARCA', 'ROBERTO MENDOZA', 1, '2026-05-25 05:39:28'),
(19, 'ARTESANIAS DEL PERU E.I.R.L.', '20101444014', '066456714', 'arte@delperu.pe', 'CAL. UNION 1414 AYACUCHO', 'SILVIA CASTRO', 1, '2026-05-25 05:39:28'),
(20, 'TURISMO NACIONAL S.A.', '20101555015', '043567815', 'tours@nacional.com', 'AV. BOLOGNESI 1515 ANCASH', 'FERNANDO ORTIZ', 1, '2026-05-25 05:39:28'),
(21, 'FARMACEUTICA PERUANA S.A.C.', '20101666016', '062678916', 'farma@peruana.com', 'JR. GRAU 1616 HUANUCO', 'DIANA PEREZ', 1, '2026-05-25 05:39:28'),
(22, 'MADERERA AMAZONICA E.I.R.L.', '20101777017', '062789017', 'madera@amazonica.pe', 'AV. EJERCITO 1717 UCAYALI', 'OSCAR ALVA', 1, '2026-05-25 05:39:28'),
(23, 'ECOTURISMO PERU S.R.L.', '20101888018', '042890118', 'eco@peru.com', 'CAL. REAL 1818 SAN MARTIN', 'VERONICA HUAMAN', 1, '2026-05-25 05:39:28'),
(24, 'AGROINDUSTRIAL NORTE S.A.C.', '20101999019', '042901219', 'agro@norte.pe', 'AV. GRAU 1919 SAN MARTIN', 'EDGAR QUISPE', 1, '2026-05-25 05:39:28'),
(25, 'ACUICULTURA PERUANA S.A.', '20102010020', '065012320', 'acui@peruana.com', 'JR. LIBERTAD 2020 LORETO', 'NELLY CCOPA', 1, '2026-05-25 05:39:28'),
(26, 'PETROQUIMICA NACIONAL E.I.R.L.', '20102111021', '073123421', 'petro@nacional.pe', 'CAL. UNION 2121 PIURA', 'ALEX TAPIA', 1, '2026-05-25 05:39:28'),
(27, 'CERAMICA ARTESANAL S.A.C.', '20102222022', '073234522', 'ceramica@artesanal.com', 'AV. INDEPENDENCIA 2222 PIURA', 'JESSICA MORAN', 1, '2026-05-25 05:39:28'),
(28, 'AZUCARERA DEL NORTE S.R.L.', '20102333023', '074345623', 'azucar@norte.pe', 'JR. TACNA 2323 LAMBAYEQUE', 'CHRISTIAN SALAZAR', 1, '2026-05-25 05:39:28'),
(29, 'PESQUERA CHICLAYO S.A.', '20102444024', '074456724', 'pesca@chiclayo.com', 'AV. GRAU 2424 LAMBAYEQUE', 'MILAGROS VASQUEZ', 1, '2026-05-25 05:39:28'),
(30, 'INMOBILIARIA PERU E.I.R.L.', '20102555025', '074567825', 'inmobil@peru.pe', 'CAL. PROGRESO 2525 LAMBAYEQUE', 'HENRY PALACIOS', 1, '2026-05-25 05:39:28'),
(31, 'CONSTRUCTORA NORTE S.A.C.', '20102666026', '044678926', 'obras@norte.com', 'JR. UNION 2626 LA LIBERTAD', 'ELIZABETH CANO', 1, '2026-05-25 05:39:28'),
(33, 'PESQUERA PACIFICO S.A.', '20102888028', '044890128', 'pesca@pacifico.com', 'CAL. REAL 2828 LA LIBERTAD', 'KARINA BRAVO', 1, '2026-05-25 05:39:28'),
(34, 'CEMENTERA PERUANA E.I.R.L.', '20102999029', '044901229', 'cemento@peruana.pe', 'JR. LIBERTAD 2929 LA LIBERTAD', 'MARCO LEON', 1, '2026-05-25 05:39:28'),
(35, 'DISTRIBUIDORA SUR S.A.C.', '20103010030', '054012330', 'dist@sur.com', 'AV. PERU 3030 AREQUIPA', 'YOLANDA RIOS', 1, '2026-05-25 05:39:28'),
(36, 'IMPORTADORA LIMA S.R.L.', '20103111031', '01123431', 'import@lima.pe', 'CAL. UNION 3131 LIMA', 'JHON SOLIS', 1, '2026-05-25 05:39:28'),
(37, 'COMERCIAL CUSCO S.A.', '20103222032', '084234532', 'comercial@cusco.com', 'JR. GRAU 3232 CUSCO', 'MARISOL ARCE', 1, '2026-05-25 05:39:28'),
(38, 'TECNOLOGIA LIMA E.I.R.L.', '20103333033', '01345633', 'tech@lima.pe', 'AV. EJERCITO 3333 LIMA', 'CESAR INFANTE', 1, '2026-05-25 05:39:28'),
(39, 'GRUPO NORTE S.A.C.', '20103444034', '074456734', 'grupo@norte.com', 'CAL. PROGRESO 3434 LAMBAYEQUE', 'GLADYS CORONEL', 1, '2026-05-25 05:39:28'),
(40, 'FERRETERIA LIMA S.R.L.', '20103555035', '01567835', 'ferret@lima.pe', 'JR. TACNA 3535 LIMA', 'WILMER CUBAS', 1, '2026-05-25 05:39:28'),
(42, 'LOGISTICA LIMA S.A.', '20103777037', '01789037', 'logist@lima.com', 'CAL. REAL 3737 LIMA', 'IVAN HEREDIA', 1, '2026-05-25 05:39:28'),
(43, 'CONSTRUCTORA SUR S.A.C.', '20103888038', '054890138', 'obras@sur.pe', 'JR. LIBERTAD 3838 AREQUIPA', 'LOURDES IDROGO', 1, '2026-05-25 05:39:28'),
(44, 'MINERA NORTE E.I.R.L.', '20103999039', '074901239', 'mineria@norte.com', 'AV. PERU 3939 LAMBAYEQUE', 'NELSON JULCA', 1, '2026-05-25 05:39:28'),
(45, 'PESQUERA SUR S.R.L.', '20104010040', '053012340', 'pesca@sur.pe', 'CAL. UNION 4040 MOQUEGUA', 'ANGELA KAWAKAMI', 1, '2026-05-25 05:39:28'),
(46, 'TEXTIL LIMA S.A.', '20104111041', '01123441', 'textil@lima.com', 'JR. AMAZONAS 4141 LIMA', 'PAUL LLAUCE', 1, '2026-05-25 05:39:28'),
(47, 'CAFETALERA NORTE S.A.C.', '20104222042', '076234542', 'cafe@norte.pe', 'AV. INDEPENDENCIA 4242 CAJAMARCA', 'DIANA MANAYAY', 1, '2026-05-25 05:39:28'),
(48, 'ARTESANIAS LIMA E.I.R.L.', '20104333043', '01345643', 'arte@lima.pe', 'CAL. PROGRESO 4343 LIMA', 'FRANK NEYRA', 1, '2026-05-25 05:39:28'),
(49, 'TURISMO SUR S.R.L.', '20104444044', '054456744', 'tours@sur.com', 'JR. TACNA 4444 AREQUIPA', 'SANDRA OLIVA', 1, '2026-05-25 05:39:28'),
(50, 'FARMACEUTICA NORTE S.A.', '20104555045', '076567845', 'farma@norte.com', 'AV. GRAU 4545 CAJAMARCA', 'VICTOR PINTADO', 1, '2026-05-25 05:39:28'),
(51, 'MADERERA NORTE S.A.C.', '20104666046', '042678946', 'madera@norte.pe', 'CAL. REAL 4646 SAN MARTIN', 'WENDY QUIROZ', 1, '2026-05-25 05:39:28'),
(52, 'ECOTURISMO SUR E.I.R.L.', '20104777047', '054789047', 'eco@sur.pe', 'JR. LIBERTAD 4747 AREQUIPA', 'XAVIER RODRIGUEZ', 1, '2026-05-25 05:39:28'),
(53, 'AGROINDUSTRIAL SUR S.R.L.', '20104888048', '054890148', 'agro@sur.com', 'AV. PERU 4848 AREQUIPA', 'YESENIA SUAREZ', 1, '2026-05-25 05:39:28'),
(54, 'ACUICULTURA NORTE S.A.', '20104999049', '065901249', 'acui@norte.pe', 'CAL. UNION 4949 LORETO', 'ZOILA TAFUR', 1, '2026-05-25 05:39:28'),
(55, 'PETROQUIMICA SUR E.I.R.L.', '20105010050', '073012350', 'petro@sur.com', 'JR. GRAU 5050 PIURA', 'BRAYAN UGAZ', 1, '2026-05-25 05:39:28'),
(56, 'AGRICOLA DEL NORTE S.R.L.', '20102777027', '044789027', 'agro.norte27@gmail.com', 'AV. REPUBLICA 2727 LA LIBERTAD', 'RONALD MEZA', 1, '2026-05-25 05:42:36'),
(59, 'IBM DEL PERU S A C', '20100075009', '', NULL, 'AV. JAVIER PRADO ESTE NRO. 6230      LA RIVIERA DE MONTERRICO, LA MOLINA, LIMA, LIMA', '', 1, '2026-05-25 13:34:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Administrador', NULL, 1),
(2, 'Asesor Comercial', NULL, 1),
(3, 'Técnico', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `tipo` enum('catalogo','personalizado') NOT NULL DEFAULT 'catalogo',
  `descripcion` varchar(255) DEFAULT NULL,
  `precio_base` decimal(10,2) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `nombre`, `tipo`, `descripcion`, `precio_base`, `estado`) VALUES
(1, 'Instalación de Programas', 'catalogo', 'Programas básicos + Office + Antivirus', 60.00, 1),
(2, 'Formateo de PC', 'catalogo', 'Instalación limpia de Windows y drivers', 80.00, 1),
(4, 'Cambio de Disco HDD a SSD', 'personalizado', 'Migración y mejora de velocidad', 120.00, 1),
(5, 'Mantenimiento Preventivo', 'catalogo', 'Limpieza interna y cambio de pasta térmica', 90.00, 1),
(6, 'Reparación de Windows', 'catalogo', 'Corrección de errores del sistema operativo', 75.00, 1),
(7, 'Recuperación de Archivos', 'catalogo', 'Recuperación de información eliminada', 150.00, 1),
(8, 'Configuración de Impresoras', 'catalogo', 'Instalación y conexión en red', 40.00, 1),
(9, 'Instalación de Drivers', 'catalogo', 'Configuración completa de controladores', 50.00, 1),
(10, 'Cambio de Pantalla', 'personalizado', 'Reemplazo de pantalla dañada', 180.00, 1),
(11, 'Cambio de Teclado', 'personalizado', 'Sustitución de teclado defectuoso', 120.00, 1),
(12, 'Reparación de Bisagras', 'personalizado', 'Ajuste o reemplazo de bisagras', 90.00, 1),
(13, 'Cambio de Batería', 'personalizado', 'Instalación de batería nueva', 100.00, 1),
(14, 'Mantenimiento de Impresora', 'personalizado', 'Limpieza y calibración', 80.00, 1),
(15, 'Recarga de Tinta', 'personalizado', 'Recarga de cartuchos', 50.00, 1),
(16, 'Reparación de Atasco', 'personalizado', 'Corrección de problemas de papel', 70.00, 1),
(17, 'Limpieza de Cooler', 'personalizado', 'Limpieza del sistema de ventilación', 60.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_historial`
--

CREATE TABLE `servicio_historial` (
  `id` int(11) NOT NULL,
  `id_orden` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Usuario que realizó el cambio de estado',
  `estado` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `servicio_historial`
--

INSERT INTO `servicio_historial` (`id`, `id_orden`, `id_usuario`, `estado`, `descripcion`, `fecha`) VALUES
(6, 1, 1, 'recibido', 'Estado actualizado.', '2026-05-19 17:21:37'),
(7, 1, 1, 'diagnostico', 'Orden tomada por el técnico. Iniciando diagnóstico.', '2026-05-20 10:36:49'),
(8, 1, 1, 'en_proceso', 'Estado actualizado a: En proceso', '2026-05-20 11:25:07'),
(9, 1, 1, 'listo', 'Estado actualizado a: Listo', '2026-05-20 11:26:46'),
(10, 2, 1, 'recibido', 'Equipo recibido en recepción. no enciende', '2026-05-20 18:32:59'),
(11, 2, 2, 'diagnostico', 'Orden tomada por el tecnico. Iniciando diagnostico.', '2026-05-23 13:30:15'),
(12, 1, 1, 'entregado', 'Equipo entregado al cliente tras cobro completo.', '2026-05-23 20:50:12'),
(13, 3, 1, 'recibido', 'Equipo recibido en recepción. ', '2026-05-25 10:19:21'),
(14, 3, 1, 'diagnostico', 'Orden tomada por el tecnico. Iniciando diagnostico.', '2026-05-25 10:19:35'),
(15, 2, 1, 'listo', 'Estado actualizado a: Listo', '2026-05-27 07:21:40'),
(16, 2, 1, 'entregado', 'Equipo entregado al cliente tras cobro completo.', '2026-05-27 07:31:36'),
(17, 3, 1, 'listo', 'Estado actualizado a: Listo', '2026-05-29 22:52:55'),
(18, 3, 1, 'entregado', 'Equipo entregado al cliente tras cobro completo.', '2026-05-29 22:53:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_tipos`
--

CREATE TABLE `servicio_tipos` (
  `id` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `tipo_equipo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `servicio_tipos`
--

INSERT INTO `servicio_tipos` (`id`, `id_servicio`, `tipo_equipo`) VALUES
(35, 1, 'Laptop'),
(36, 1, 'PC'),
(11, 2, 'Laptop'),
(12, 2, 'PC'),
(37, 4, 'Laptop'),
(38, 4, 'PC'),
(17, 5, 'Laptop'),
(18, 5, 'PC'),
(19, 6, 'Laptop'),
(20, 6, 'PC'),
(21, 7, 'Laptop'),
(22, 7, 'PC'),
(23, 8, 'Laptop'),
(24, 8, 'PC'),
(25, 9, 'Laptop'),
(26, 9, 'PC'),
(27, 10, 'Laptop'),
(28, 11, 'Laptop'),
(29, 12, 'Laptop'),
(40, 13, 'Laptop'),
(31, 14, 'Impresora'),
(32, 15, 'Impresora'),
(33, 16, 'Impresora'),
(34, 17, 'Laptop');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `clave` varchar(255) DEFAULT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_completo`, `username`, `clave`, `id_rol`, `estado`, `fecha_registro`) VALUES
(1, 'Jorge Luis Chavez', 'Jorge Chavez', '$2y$10$SN/Zz/Xw3EmpGQRquTzj3eI8uT18Z6c7NyZBTPNuD/LFFyRgMHEKW', 1, 1, '2026-03-15 13:42:52'),
(2, 'Tino Coronel Sorogastua', 'Tino Coronel', '$2y$10$BS1H3V.HWAFT5lGymRNKvOkLzCmj/IEflljhL/dqLJAiQUU/ltWBK', 3, 1, '2026-03-24 15:42:58'),
(3, 'Adriana Lopez Ramirez', 'Adriana Lopez', '$2y$10$HyPHoMFrLW5UgfhILk1jze1h3laCTvTLlWkBpx.S1hrX5iIUYkeI6', 2, 1, '2026-03-24 15:44:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `tipo_cliente` enum('natural','empresa') NOT NULL DEFAULT 'natural',
  `id_usuario` int(11) DEFAULT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `tipo_comprobante` enum('boleta','factura','ticket','nota') DEFAULT NULL,
  `numero_comprobante` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT NULL,
  `aplica_igv` tinyint(4) DEFAULT NULL,
  `igv` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `saldo_pendiente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_vencimiento_pago` date DEFAULT NULL,
  `tipo_pago` enum('contado','credito') DEFAULT NULL,
  `metodo_pago` enum('efectivo','yape','plin','transferencia','tarjeta') DEFAULT 'efectivo',
  `estado` varchar(20) DEFAULT 'pagado',
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_cliente`, `tipo_cliente`, `id_usuario`, `id_caja`, `fecha`, `tipo_comprobante`, `numero_comprobante`, `subtotal`, `descuento`, `aplica_igv`, `igv`, `total`, `saldo_pendiente`, `fecha_vencimiento_pago`, `tipo_pago`, `metodo_pago`, `estado`, `observacion`) VALUES
(1, 3, 'natural', 1, NULL, '2026-05-17 13:36:00', 'ticket', 'T001-00000001', 600.00, 0.00, 0, 0.00, 600.00, 0.00, NULL, 'contado', 'yape', 'pagado', NULL),
(2, 3, 'natural', 1, NULL, '2026-05-23 20:19:00', 'ticket', 'T001-00000002', 390.00, 0.00, 1, 70.20, 460.20, 0.00, NULL, 'contado', 'efectivo', 'pagado', NULL),
(3, 3, 'natural', 1, NULL, '2026-05-23 20:27:00', 'ticket', 'T001-00000003', 390.00, 0.00, 0, 0.00, 390.00, 0.00, NULL, 'contado', 'efectivo', 'pagado', NULL),
(4, 3, 'natural', 1, 1, '2026-05-23 20:30:00', 'nota', 'N001-00000001', 390.00, 0.00, 1, 70.20, 460.20, 0.00, NULL, 'contado', 'efectivo', 'pagado', NULL),
(5, 3, 'natural', 1, 2, '2026-05-25 06:37:00', 'ticket', 'T001-00000004', 390.00, 0.00, 1, 70.20, 460.20, 0.00, '2026-06-24', 'credito', 'transferencia', 'pagado', NULL),
(6, 3, 'natural', 1, 3, '2026-05-25 11:13:00', 'ticket', 'T001-00000005', 2900.00, 0.00, 1, 522.00, 3422.00, 0.00, NULL, 'contado', 'efectivo', 'pagado', NULL),
(7, 67, 'natural', 1, 4, '2026-05-26 18:42:00', 'ticket', 'T001-00000006', 7100.00, 0.00, 0, 0.00, 7100.00, 0.00, NULL, 'contado', 'efectivo', 'pagado', NULL),
(8, 3, 'natural', 1, 4, '2026-05-27 07:19:00', 'ticket', 'T001-00000007', 2900.00, 0.00, 1, 522.00, 3422.00, 0.00, '2026-06-27', 'credito', 'transferencia', 'pagado', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_modulo` (`modulo`),
  ADD KEY `idx_accion` (`accion`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_tabla_id` (`tabla`,`id_registro`);

--
-- Indices de la tabla `caja`
--
ALTER TABLE `caja`
  ADD PRIMARY KEY (`id_caja`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `cierre_caja_detalle`
--
ALTER TABLE `cierre_caja_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cierre_caja` (`id_caja`);

--
-- Indices de la tabla `clientes_empresa`
--
ALTER TABLE `clientes_empresa`
  ADD PRIMARY KEY (`id_cliente_empresa`),
  ADD UNIQUE KEY `uk_ruc` (`ruc`);

--
-- Indices de la tabla `clientes_natural`
--
ALTER TABLE `clientes_natural`
  ADD PRIMARY KEY (`id_cliente_natural`),
  ADD UNIQUE KEY `uk_documento_natural` (`documento_identidad`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `fk_compras_caja` (`id_caja`);

--
-- Indices de la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  ADD PRIMARY KEY (`id_cuota`),
  ADD KEY `id_compra` (`id_compra`);

--
-- Indices de la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  ADD PRIMARY KEY (`id_cuota`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_compra` (`id_compra`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `detalle_orden`
--
ALTER TABLE `detalle_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `detalle_orden_ibfk_2` (`id_servicio`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `idx_equipo_cliente` (`id_cliente`,`tipo_cliente`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `id_caja` (`id_caja`);

--
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  ADD PRIMARY KEY (`id_orden`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_equipo` (`id_equipo`),
  ADD KEY `fk_orden_tecnico` (`id_tecnico`),
  ADD KEY `idx_estado_pago` (`estado_pago`),
  ADD KEY `idx_orden_cliente` (`id_cliente`,`tipo_cliente`);

--
-- Indices de la tabla `orden_cotizaciones`
--
ALTER TABLE `orden_cotizaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_id_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_compra` (`id_compra`);

--
-- Indices de la tabla `pagos_servicio`
--
ALTER TABLE `pagos_servicio`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_orden` (`id_orden`);

--
-- Indices de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_usuario` (`id_usuario`);

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
  ADD KEY `id_categoria` (`id_categoria`),
  ADD KEY `productos_ibfk_2` (`id_proveedor`);

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
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`);

--
-- Indices de la tabla `servicio_historial`
--
ALTER TABLE `servicio_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `fk_historial_usuario` (`id_usuario`);

--
-- Indices de la tabla `servicio_tipos`
--
ALTER TABLE `servicio_tipos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_servicio_tipo` (`id_servicio`,`tipo_equipo`),
  ADD KEY `id_servicio` (`id_servicio`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_caja` (`id_caja`),
  ADD KEY `idx_ventas_cliente` (`id_cliente`,`tipo_cliente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `caja`
--
ALTER TABLE `caja`
  MODIFY `id_caja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cierre_caja_detalle`
--
ALTER TABLE `cierre_caja_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `clientes_empresa`
--
ALTER TABLE `clientes_empresa`
  MODIFY `id_cliente_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `clientes_natural`
--
ALTER TABLE `clientes_natural`
  MODIFY `id_cliente_natural` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  MODIFY `id_cuota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  MODIFY `id_cuota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `detalle_orden`
--
ALTER TABLE `detalle_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  MODIFY `id_orden` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `orden_cotizaciones`
--
ALTER TABLE `orden_cotizaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `pagos_servicio`
--
ALTER TABLE `pagos_servicio`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=199;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `servicio_historial`
--
ALTER TABLE `servicio_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `servicio_tipos`
--
ALTER TABLE `servicio_tipos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `caja`
--
ALTER TABLE `caja`
  ADD CONSTRAINT `caja_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`),
  ADD CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_compras_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `cuotas_compra`
--
ALTER TABLE `cuotas_compra`
  ADD CONSTRAINT `cuotas_compra_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cuotas_venta`
--
ALTER TABLE `cuotas_venta`
  ADD CONSTRAINT `cuotas_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`);

--
-- Filtros para la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD CONSTRAINT `detalle_compra_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_compra_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`);

--
-- Filtros para la tabla `detalle_orden`
--
ALTER TABLE `detalle_orden`
  ADD CONSTRAINT `detalle_orden_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_servicio` (`id_orden`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_orden_ibfk_2` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`);

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `detalle_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  ADD CONSTRAINT `detalle_venta_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`);

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`);

--
-- Filtros para la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  ADD CONSTRAINT `movimientos_inventario_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  ADD CONSTRAINT `fk_orden_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `ordenes_servicio_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `ordenes_servicio_ibfk_3` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`);

--
-- Filtros para la tabla `orden_cotizaciones`
--
ALTER TABLE `orden_cotizaciones`
  ADD CONSTRAINT `oc_ibfk_orden` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_servicio` (`id_orden`) ON DELETE CASCADE,
  ADD CONSTRAINT `oc_ibfk_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE SET NULL,
  ADD CONSTRAINT `oc_ibfk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pagos_compra`
--
ALTER TABLE `pagos_compra`
  ADD CONSTRAINT `pagos_compra_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`);

--
-- Filtros para la tabla `pagos_servicio`
--
ALTER TABLE `pagos_servicio`
  ADD CONSTRAINT `pagos_servicio_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_servicio` (`id_orden`);

--
-- Filtros para la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  ADD CONSTRAINT `pagos_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  ADD CONSTRAINT `pagos_venta_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD CONSTRAINT `fk_permiso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicio_historial`
--
ALTER TABLE `servicio_historial`
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `servicio_historial_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_servicio` (`id_orden`);

--
-- Filtros para la tabla `servicio_tipos`
--
ALTER TABLE `servicio_tipos`
  ADD CONSTRAINT `fk_st_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
