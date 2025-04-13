-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-04-2025 a las 00:25:40
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
  `nombre_proyecto` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `ciclo` varchar(10) DEFAULT NULL,
  `estado` varchar(10) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_solicitudes`
--

INSERT INTO `historial_solicitudes` (`id`, `usuario_id`, `nombre`, `email`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `nombre_proyecto`, `nombre_empresa`, `ciclo`, `estado`, `fecha_registro`) VALUES
(1, NULL, 'María Gómez', 'maria.gomez@email.com', 'estudiante', '1002234568', 'E2024002', '3002234568', 'seminario', NULL, NULL, 'tecnologo', 'rechazado', '2025-03-09 18:38:56'),
(2, 46, 'Carlos Ramírez', 'carlos.ramirez@email.com', 'tutor', '1003234569', NULL, '3003234569', NULL, NULL, NULL, NULL, 'aprobado', '2025-03-09 18:39:06'),
(3, NULL, 'Ana Torres', 'ana.torres@email.com', 'estudiante', '1004234570', 'E2024003', '3004234570', 'pasantia', NULL, NULL, 'profesiona', 'rechazado', '2025-03-09 18:39:30'),
(4, NULL, 'Gabriela Suárez', 'gabriela.suarez@email.com', 'tutor', '1008234574', NULL, '3008234574', NULL, NULL, NULL, NULL, 'rechazado', '2025-03-09 18:44:03'),
(5, 47, 'Diego Martínez', 'diego.martinez@email.com', 'estudiante', '1007234573', 'E2024005', '3007234573', 'seminario', NULL, NULL, 'tecnologo', 'aprobado', '2025-03-09 21:48:01'),
(6, NULL, 'Andr&eacute;s Felipe Puentes Rivera', 'andres_puentesri@fet.edu.co', 'estudiante', '1076502581', 'SOF120242047', '3228400865', 'proyecto', 'Misi&oacute;n FET', NULL, 'tecnico', 'rechazado', '2025-04-06 00:36:32'),
(7, 55, 'Andr&eacute;s Cacho', 'and@fet.edu.co', 'estudiante', '107654', 'SOF120242047', '3228400865', 'seminario', NULL, NULL, 'tecnico', 'aprobado', '2025-04-06 00:38:34'),
(8, NULL, 'Andr&eacute;s Fa', 'atesri@fet.edu.co', 'estudiante', '10761', 'SOF120', '3225552', 'seminario', NULL, NULL, 'tecnico', 'rechazado', '2025-04-06 01:18:07'),
(9, 56, 'Daniel calderon', 'Daniel@fet.edu.co', 'estudiante', '10245', 'SOF120', '3228400865', 'seminario', NULL, NULL, 'tecnico', 'aprobado', '2025-04-06 09:10:59'),
(10, 57, 'Luis Fernández', 'luis.fernandez@email.com', 'tutor', '1005234571', NULL, '3005234571', NULL, NULL, NULL, NULL, 'aprobado', '2025-04-12 19:24:38');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
