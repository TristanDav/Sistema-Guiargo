-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3307
-- Tiempo de generación: 04-11-2025 a las 00:51:32
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestor_clientes_guiargo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(50) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `estatus` enum('En seguimiento','Por contactar','Agendado','Cliente cerrado') NOT NULL DEFAULT 'Por contactar',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_usuario_creacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `direccion`, `ciudad`, `telefono`, `whatsapp`, `correo`, `notas`, `estatus`, `fecha_registro`, `fecha_actualizacion`, `id_usuario_creacion`) VALUES
(1, 'Juan Pérez', 'Calle Principal 123', 'Xalapa', '2281234567', '2281234567', 'juan@email.com', NULL, 'Por contactar', '2025-10-17 18:08:37', '2025-10-17 18:08:37', 1),
(2, 'María García', 'Av. Central 456', 'Veracruz', '2299876543', '2299876543', 'maria@email.com', NULL, 'En seguimiento', '2025-10-17 18:08:37', '2025-10-17 18:08:37', 1),
(3, 'Carlos López', 'Calle Secundaria 789', 'Córdoba', '2715551234', '2715551234', 'carlos@email.com', NULL, 'Agendado', '2025-10-17 18:08:37', '2025-10-17 18:08:37', 2),
(8, 'Tristan David', '16 de septiembre No.2', 'Coatepec', '2283137281', '2283137281', 'daviton105@gmail.com', 'Llamarlo el martes', 'Por contactar', '2025-10-22 21:31:40', '2025-10-22 21:34:57', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(50) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `rfc` varchar(13) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `estatus` enum('En seguimiento','Por contactar','Agendado','Cliente cerrado') NOT NULL DEFAULT 'Por contactar',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_usuario_creacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre`, `direccion`, `ciudad`, `telefono`, `whatsapp`, `correo`, `rfc`, `notas`, `estatus`, `fecha_registro`, `fecha_actualizacion`, `id_usuario_creacion`) VALUES
(1, 'Empresa ABC S.A.', 'Zona Industrial 100', 'Xalapa', '2281111111', '2281111111', 'contacto@empresaabc.com', 'ABC123456789', NULL, 'Por contactar', '2025-10-17 18:08:37', '2025-10-17 18:08:37', 1),
(2, 'Corporación XYZ', 'Centro Comercial 200', 'Veracruz', '2292222222', '2292222222', 'info@corporacionxyz.com', 'XYZ987654321', NULL, 'En seguimiento', '2025-10-17 18:08:37', '2025-10-17 18:08:37', 2),
(3, 'empresa fantasma', 'calle falsa 123', 'xalapa, veracruz.', '1122334455', '2283137281', 'correo@falso.com', 'JF992D8JYH3', 'Esta empresa lava dinero', 'Agendado', '2025-10-23 16:35:22', '2025-10-23 16:44:34', NULL),
(4, 'La Naolinqueña', 'avenida principal, las trancas', 'xalapa, veracruz.', '2288110103', '2295678513', 'lanaolinquena@gmail.com', 'NLQ378592A', 'EMPRESA LIDER EN XALAPA EN EL SECTOR RESTAURANTERO', 'Cliente cerrado', '2025-10-23 16:48:38', '2025-10-23 16:48:38', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `id_seguimiento` int(11) NOT NULL,
  `id_usuario_destino` int(11) NOT NULL,
  `tipo` enum('Notificacion','Alerta') NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_limite` date DEFAULT NULL,
  `prioritaria` tinyint(1) DEFAULT 0,
  `leida` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `id_seguimiento`, `id_usuario_destino`, `tipo`, `mensaje`, `fecha_envio`, `fecha_limite`, `prioritaria`, `leida`) VALUES
(1, 1, 1, 'Notificacion', 'Recordatorio: Llamar a Juan Pérez mañana', '2025-10-17 18:08:37', '2025-10-18', 0, 1),
(3, 3, 1, 'Notificacion', 'Recordatorio: Reunión con Empresa ABC', '2025-10-17 18:08:37', '2025-10-20', 0, 1),
(8, 9, 1, 'Notificacion', 'ejemplo notificacion', '2025-10-28 20:29:07', '2025-10-28', 1, 0),
(9, 10, 1, 'Alerta', 'notificacion alerta', '2025-10-28 20:29:44', '2025-10-28', 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguimientos`
--

CREATE TABLE `seguimientos` (
  `id` int(11) NOT NULL,
  `id_entidad` int(11) NOT NULL,
  `tipo_entidad` enum('cliente','empresa') NOT NULL,
  `id_usuario_asignado` int(11) NOT NULL,
  `fecha_programada` date NOT NULL,
  `descripcion` text NOT NULL,
  `estatus` enum('Pendiente','Cumplido','Vencido') NOT NULL DEFAULT 'Pendiente',
  `fecha_cumplimiento` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `seguimientos`
--

INSERT INTO `seguimientos` (`id`, `id_entidad`, `tipo_entidad`, `id_usuario_asignado`, `fecha_programada`, `descripcion`, `estatus`, `fecha_cumplimiento`, `fecha_creacion`) VALUES
(1, 1, 'cliente', 1, '2025-10-18', 'Llamar para agendar curso de capacitación', 'Pendiente', NULL, '2025-10-17 18:08:37'),
(2, 2, 'cliente', 2, '2025-10-19', 'Enviar propuesta comercial', 'Pendiente', NULL, '2025-10-17 18:08:37'),
(3, 1, 'empresa', 1, '2025-10-20', 'Reunión para presentar servicios', 'Pendiente', NULL, '2025-10-17 18:08:37'),
(4, 3, 'cliente', 2, '2025-10-17', 'Seguimiento de venta cerrada', 'Cumplido', NULL, '2025-10-17 18:08:37'),
(5, 1, 'cliente', 1, '2025-10-25', 'Notificación general', 'Pendiente', NULL, '2025-10-25 17:24:52'),
(6, 1, 'cliente', 1, '2025-10-25', 'Notificación general', 'Pendiente', NULL, '2025-10-25 17:25:32'),
(7, 1, 'cliente', 1, '2025-10-25', 'Notificación general', 'Pendiente', NULL, '2025-10-25 17:26:15'),
(8, 8, 'cliente', 1, '2025-10-25', 'Llamar a tristan para cerrar trato', 'Pendiente', NULL, '2025-10-25 17:27:29'),
(9, 1, 'cliente', 1, '2025-10-28', 'Notificación general', 'Pendiente', NULL, '2025-10-28 20:29:07'),
(10, 1, 'cliente', 1, '2025-10-28', 'Notificación general', 'Pendiente', NULL, '2025-10-28 20:29:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','colaborador') NOT NULL DEFAULT 'colaborador',
  `email` varchar(100) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `rol`, `email`, `fecha_registro`, `activo`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@guiargo.com', '2025-10-17 18:08:37', 1),
(2, 'colaborador', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'colaborador', 'colaborador@guiargo.com', '2025-10-17 18:08:37', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_creacion` (`id_usuario_creacion`),
  ADD KEY `idx_clientes_estatus` (`estatus`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_creacion` (`id_usuario_creacion`),
  ADD KEY `idx_empresas_estatus` (`estatus`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_seguimiento` (`id_seguimiento`),
  ADD KEY `id_usuario_destino` (`id_usuario_destino`),
  ADD KEY `idx_notificaciones_leida` (`leida`),
  ADD KEY `idx_notificaciones_fecha_limite` (`fecha_limite`),
  ADD KEY `idx_notificaciones_prioritaria` (`prioritaria`);

--
-- Indices de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_asignado` (`id_usuario_asignado`),
  ADD KEY `idx_seguimientos_fecha` (`fecha_programada`),
  ADD KEY `idx_seguimientos_estatus` (`estatus`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_seguimiento`) REFERENCES `seguimientos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `seguimientos`
--
ALTER TABLE `seguimientos`
  ADD CONSTRAINT `seguimientos_ibfk_1` FOREIGN KEY (`id_usuario_asignado`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
