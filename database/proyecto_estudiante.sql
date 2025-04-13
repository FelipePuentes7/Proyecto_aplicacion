-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-03-2025 a las 05:00:08
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
-- Estructura de tabla para la tabla `proyecto_estudiante`
--

CREATE TABLE `proyecto_estudiante` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `rol_en_proyecto` enum('lider','miembro') DEFAULT 'miembro',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proyecto_estudiante`
--

INSERT INTO `proyecto_estudiante` (`id`, `proyecto_id`, `estudiante_id`, `rol_en_proyecto`, `fecha_asignacion`) VALUES
(1, 1, 47, 'lider', '2025-03-16 03:58:49');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `proyecto_estudiante`
--
ALTER TABLE `proyecto_estudiante`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_proyecto_estudiante` (`proyecto_id`,`estudiante_id`),
  ADD KEY `idx_proyecto_estudiante_proyecto` (`proyecto_id`),
  ADD KEY `idx_proyecto_estudiante_estudiante` (`estudiante_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `proyecto_estudiante`
--
ALTER TABLE `proyecto_estudiante`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `proyecto_estudiante`
--
ALTER TABLE `proyecto_estudiante`
  ADD CONSTRAINT `proyecto_estudiante_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proyecto_estudiante_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
