-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 10-03-2025 a las 00:44:41
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
-- Estructura de tabla para la tabla `historial_solicitudes`
--

CREATE TABLE `historial_solicitudes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `documento` varchar(20) NOT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `opcion_grado` varchar(50) DEFAULT NULL,
  `ciclo` varchar(10) DEFAULT NULL,
  `estado` varchar(10) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_solicitudes`
--

INSERT INTO `historial_solicitudes` (`id`, `usuario_id`, `nombre`, `email`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `ciclo`, `estado`, `fecha_registro`) VALUES
(1, NULL, 'María Gómez', 'maria.gomez@email.com', 'estudiante', '1002234568', 'E2024002', '3002234568', 'seminario', 'tecnologo', 'rechazado', '2025-03-09 18:38:56'),
(2, 46, 'Carlos Ramírez', 'carlos.ramirez@email.com', 'tutor', '1003234569', NULL, '3003234569', NULL, NULL, 'aprobado', '2025-03-09 18:39:06'),
(3, NULL, 'Ana Torres', 'ana.torres@email.com', 'estudiante', '1004234570', 'E2024003', '3004234570', 'pasantia', 'profesiona', 'rechazado', '2025-03-09 18:39:30'),
(4, NULL, 'Gabriela Suárez', 'gabriela.suarez@email.com', 'tutor', '1008234574', NULL, '3008234574', NULL, NULL, 'rechazado', '2025-03-09 18:44:03');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
