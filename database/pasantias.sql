-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-04-2025 a las 04:04:37
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
-- Estructura de tabla para la tabla `pasantias`
--

CREATE TABLE `pasantias` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `empresa` varchar(255) NOT NULL,
  `direccion_empresa` varchar(255) DEFAULT NULL,
  `contacto_empresa` varchar(100) DEFAULT NULL,
  `supervisor_empresa` varchar(100) DEFAULT NULL,
  `telefono_supervisor` varchar(20) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada','en_proceso','finalizada') DEFAULT 'pendiente',
  `estudiante_id` int(11) NOT NULL,
  `tutor_id` int(11) DEFAULT NULL,
  `archivo_documento` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pasantias`
--

INSERT INTO `pasantias` (`id`, `titulo`, `descripcion`, `empresa`, `direccion_empresa`, `contacto_empresa`, `supervisor_empresa`, `telefono_supervisor`, `fecha_inicio`, `fecha_fin`, `estado`, `estudiante_id`, `tutor_id`, `archivo_documento`, `fecha_creacion`) VALUES
(1, 'GROK', '', 'GROK', '', '', '', '', '2025-04-19', '2025-04-20', 'pendiente', 58, NULL, NULL, '2025-04-18 01:23:37');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `pasantias`
--
ALTER TABLE `pasantias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estudiante_id` (`estudiante_id`),
  ADD KEY `tutor_id` (`tutor_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `pasantias`
--
ALTER TABLE `pasantias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `pasantias`
--
ALTER TABLE `pasantias`
  ADD CONSTRAINT `pasantias_ibfk_1` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pasantias_ibfk_2` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
