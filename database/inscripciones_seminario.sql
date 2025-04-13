-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-04-2025 a las 01:14:00
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
-- Estructura de tabla para la tabla `inscripciones_seminario`
--

CREATE TABLE `inscripciones_seminario` (
  `id` int(11) NOT NULL,
  `seminario_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `estado` enum('inscrito','aprobado','rechazado','finalizado') DEFAULT 'inscrito',
  `asistencia` tinyint(1) DEFAULT 0,
  `nota` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seminario_id` (`seminario_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  ADD CONSTRAINT `inscripciones_seminario_ibfk_1` FOREIGN KEY (`seminario_id`) REFERENCES `seminarios` (`id`),
  ADD CONSTRAINT `inscripciones_seminario_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
