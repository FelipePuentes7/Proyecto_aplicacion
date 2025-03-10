-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-03-2025 a las 22:43:03
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
  `ciclo` enum('tecnico','tecnologo','profesional') DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitudes_registro`
--

INSERT INTO `solicitudes_registro` (`id`, `nombre`, `email`, `password`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `ciclo`, `estado`, `fecha_solicitud`) VALUES
(1, 'felipe', 'andres@fet.edu.co', '$2y$10$bLqK9HofLvjrsxlfCxy4mOUnG9ebYwZ.9uhrrBm1yrARu6.K9atuO', 'estudiante', '10225', 'Sof114', '77878', '', '', 'aprobado', '2025-01-26 23:21:13'),
(2, 'Usuario de Prueba', 'prueba@universidad.edu', '$2y$10$Ej3mPL0PaSsLxW.9sS5uE.0RjK7uZ1JqYVn2TgHkI1bNQrVcXhW6', '', '987654321', 'FET2024001', '3009876543', '', '', 'aprobado', '2025-01-26 23:48:13');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
