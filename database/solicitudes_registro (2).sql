-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-03-2025 a las 04:12:45
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `grados_fet`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_registro`
--

CREATE TABLE `solicitudes_registro` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('estudiante','tutor') NOT NULL,
  `documento` varchar(20) NOT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `opcion_grado` enum('seminario','proyecto','pasantia') DEFAULT NULL,
  `nombre_proyecto` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `ciclo` enum('tecnico','tecnologo','profesional') DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitudes_registro`
--

INSERT INTO `solicitudes_registro` (`id`, `nombre`, `email`, `password`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `nombre_proyecto`, `nombre_empresa`, `ciclo`, `estado`, `fecha_solicitud`) VALUES
(56, 'Luis Fernández', 'luis.fernandez@email.com', '*23AE809DDACAF96AF0FD78ED04B6A265E05AA257', 'tutor', '1005234571', NULL, '3005234571', NULL, NULL, NULL, NULL, 'pendiente', '2025-03-09 23:07:53'),
(57, 'Sofía Herrera', 'sofia.herrera@email.com', '*23AE809DDACAF96AF0FD78ED04B6A265E05AA257', 'estudiante', '1006234572', 'E2024004', '3006234572', 'proyecto', NULL, NULL, 'tecnico', 'pendiente', '2025-03-09 23:07:53');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
