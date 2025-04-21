-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-04-2025 a las 01:54:05
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
-- Base de datos: `proyecto`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avances_proyecto`
--

CREATE TABLE `avances_proyecto` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `porcentaje_avance` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `registrado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios_proyecto`
--

CREATE TABLE `comentarios_proyecto` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `fecha_comentario` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes_proyecto`
--

CREATE TABLE `estudiantes_proyecto` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) DEFAULT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `rol_en_proyecto` enum('líder','miembro') DEFAULT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes_proyecto`
--

INSERT INTO `estudiantes_proyecto` (`id`, `proyecto_id`, `estudiante_id`, `rol_en_proyecto`, `fecha_asignacion`) VALUES
(4, 2, 5, 'líder', '2025-04-20 03:16:24'),
(5, 1, 1, 'líder', '2025-04-20 03:16:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_solicitudes`
--

CREATE TABLE `historial_solicitudes` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `ciclo` enum('tecnico','tecnologo','profesional') DEFAULT NULL,
  `opcion_grado` enum('seminario','proyecto','pasantia') DEFAULT NULL,
  `nombre_proyecto` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `rol` enum('estudiante','tutor') DEFAULT NULL,
  `estado_final` enum('aprobado','rechazado') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_resolucion` timestamp NOT NULL DEFAULT current_timestamp(),
  `resuelto_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_solicitudes`
--

INSERT INTO `historial_solicitudes` (`id`, `solicitud_id`, `nombre`, `email`, `documento`, `codigo_estudiante`, `ciclo`, `opcion_grado`, `nombre_proyecto`, `nombre_empresa`, `rol`, `estado_final`, `observaciones`, `fecha_resolucion`, `resuelto_por`) VALUES
(1, 1, 'pipe', 'Danl@fet.edu.co', '015648', 'SOF120787', 'tecnico', 'proyecto', 'AL', NULL, 'estudiante', 'aprobado', NULL, '2025-04-19 23:59:58', NULL),
(2, 1, 'pipe', 'Danl@fet.edu.co', '015648', 'SOF120787', 'tecnico', 'proyecto', 'AL', NULL, 'estudiante', 'rechazado', NULL, '2025-04-20 00:00:15', NULL),
(3, 1, 'pipe', 'Danl@fet.edu.co', '015648', 'SOF120787', 'tecnico', 'proyecto', 'AL', NULL, 'estudiante', 'rechazado', NULL, '2025-04-20 00:03:22', NULL),
(4, 1, 'pipe', 'Danl@fet.edu.co', '015648', 'SOF120787', 'tecnico', 'proyecto', 'AL', NULL, 'estudiante', 'rechazado', NULL, '2025-04-20 00:03:30', NULL),
(5, 1, 'pipe', 'Danl@fet.edu.co', '015648', 'SOF120787', 'tecnico', 'proyecto', 'AL', NULL, 'estudiante', 'rechazado', NULL, '2025-04-20 00:08:08', NULL),
(6, 3, 'Felipe P', 'nl@fet.edu.co', '016488522', 'SOF120787', 'tecnico', 'proyecto', 'ALi', NULL, 'estudiante', 'aprobado', NULL, '2025-04-20 00:11:41', NULL),
(7, 2, 'Felipe Puentes', 'Dnl@fet.edu.co', '01648', 'SOF120787', 'tecnico', 'proyecto', 'ALi', NULL, 'estudiante', 'rechazado', NULL, '2025-04-20 00:11:45', NULL),
(8, 4, 'Andres Felipe Puentes Rivera', 'mamama@fet.edu.co', '5787687', NULL, NULL, NULL, NULL, NULL, 'tutor', 'aprobado', NULL, '2025-04-20 03:15:31', 5),
(9, 5, 'Felipe Puentes', '7@fet.edu.co', '0156485778', 'SOF1207871', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-20 03:50:01', 5),
(10, 6, 'Andres Puentes', 'jsjs@fet.edu.co', '79787', '410001-18', 'tecnico', 'pasantia', NULL, 'GROK', 'estudiante', 'aprobado', NULL, '2025-04-20 20:52:28', NULL),
(11, 7, 'css', 'asad@fet.edu.co', '784681531', 'jsjds', 'tecnico', 'pasantia', NULL, 'ss', 'estudiante', 'aprobado', NULL, '2025-04-20 21:57:50', NULL),
(12, 8, 'Andres Felipe Puentes Rivera', '24@fet.edu.co', '577', 'SOF1207871', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-20 23:46:14', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_seminario`
--

CREATE TABLE `inscripciones_seminario` (
  `id` int(11) NOT NULL,
  `seminario_id` int(11) DEFAULT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `estado` enum('inscrito','aprobado','rechazado','finalizado') DEFAULT NULL,
  `asistencia` tinyint(1) DEFAULT NULL,
  `nota` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inscripciones_seminario`
--

INSERT INTO `inscripciones_seminario` (`id`, `seminario_id`, `estudiante_id`, `estado`, `asistencia`, `nota`) VALUES
(1, 1, 7, 'inscrito', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales_apoyo`
--

CREATE TABLE `materiales_apoyo` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `tipo` enum('video','documento','otro') DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pasantias`
--

CREATE TABLE `pasantias` (
  `id` int(11) NOT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `direccion_empresa` varchar(255) DEFAULT NULL,
  `contacto_empresa` varchar(100) DEFAULT NULL,
  `supervisor_empresa` varchar(100) DEFAULT NULL,
  `telefono_supervisor` varchar(20) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada','en_proceso','finalizada') DEFAULT NULL,
  `tutor_id` int(11) DEFAULT NULL,
  `archivo_documento` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `documento_adicional` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pasantias`
--

INSERT INTO `pasantias` (`id`, `estudiante_id`, `titulo`, `descripcion`, `empresa`, `direccion_empresa`, `contacto_empresa`, `supervisor_empresa`, `telefono_supervisor`, `fecha_inicio`, `fecha_fin`, `estado`, `tutor_id`, `archivo_documento`, `fecha_creacion`, `documento_adicional`) VALUES
(1, 8, 'GROK', '', 'GROK', '', '', '', '', '2025-04-23', '2025-05-01', 'pendiente', 6, NULL, '2025-04-20 20:58:37', NULL),
(2, 9, 'jjss', '', 'ss', '', '', '', '', '2025-04-21', '2025-04-22', 'pendiente', NULL, NULL, '2025-04-20 22:23:52', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyectos`
--

CREATE TABLE `proyectos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `archivo_proyecto` varchar(255) DEFAULT NULL,
  `tipo` enum('proyecto','pasantia','seminario') DEFAULT NULL,
  `nombre_empresa` varchar(255) DEFAULT NULL,
  `estado` enum('propuesto','en_revision','aprobado','finalizado') DEFAULT NULL,
  `tutor_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proyectos`
--

INSERT INTO `proyectos` (`id`, `titulo`, `descripcion`, `archivo_proyecto`, `tipo`, `nombre_empresa`, `estado`, `tutor_id`, `fecha_creacion`) VALUES
(1, 'AL', 'c cm mdc', NULL, 'proyecto', NULL, 'propuesto', 6, NULL),
(2, 'LI', 'msm', NULL, 'proyecto', NULL, 'propuesto', 6, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seminarios`
--

CREATE TABLE `seminarios` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `modalidad` enum('presencial','virtual') DEFAULT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `cupos` int(11) DEFAULT NULL,
  `tutor_id` int(11) DEFAULT NULL,
  `archivo_guia` varchar(255) DEFAULT NULL,
  `estado` enum('activo','finalizado','cancelado') DEFAULT NULL,
  `ciclo` varchar(10) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `seminarios`
--

INSERT INTO `seminarios` (`id`, `titulo`, `descripcion`, `fecha`, `hora`, `modalidad`, `lugar`, `cupos`, `tutor_id`, `archivo_guia`, `estado`, `ciclo`, `fecha_creacion`) VALUES
(1, 'logo FET', 'n n', '2025-04-25', '12:47:00', 'presencial', 'FET', 30, 6, NULL, 'activo', NULL, '2025-04-20 03:47:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_registro`
--

CREATE TABLE `solicitudes_registro` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` enum('estudiante','tutor') DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `codigo_institucional` varchar(20) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `opcion_grado` enum('seminario','proyecto','pasantia') DEFAULT NULL,
  `nombre_proyecto` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `ciclo` enum('tecnico','tecnologo','profesional') DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` enum('estudiante','tutor','admin') DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `opcion_grado` enum('seminario','proyecto','pasantia') DEFAULT NULL,
  `nombre_proyecto` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `ciclo` enum('tecnico','tecnologo','profesional') DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `nombre_proyecto`, `nombre_empresa`, `ciclo`, `estado`) VALUES
(1, 'pipe', 'Danl@fet.edu.co', '$2y$10$RVtSEQKf8mjc4lpeFdNHTul4bXJYBFiN.1GCX5/DWuBV2dKAwBON6', 'estudiante', '015648', 'SOF120787', '3228400865', 'proyecto', 'AL', NULL, 'tecnico', 'activo'),
(5, 'Felipe P', 'nl@fet.edu.co', '$2y$10$JeABZWziqUfDvHJmgBZ47.6e4tgrMH//Ytk15oQlZ8NfrhMn61HnS', 'estudiante', '016488522', 'SOF120787', '3228400865', 'proyecto', 'ALi', NULL, 'tecnico', 'activo'),
(6, 'Andres Felipe Puentes Rivera', 'mamama@fet.edu.co', '$2y$10$zl26D2pgbS1KynJYEWSZPOlxZxOBJc58O2cK8lFmgxlmXjMj/vIlS', 'tutor', '5787687', NULL, '13153453', NULL, NULL, NULL, NULL, 'activo'),
(7, 'Felipe Puentes', '7@fet.edu.co', '$2y$10$OpRWcuhMg0PFUoZxCpnPEu2.gELz4wW2oLudrlYZwKk81D0emqPam', 'estudiante', '0156485778', 'SOF1207871', '3228400865', 'seminario', NULL, NULL, 'tecnico', 'activo'),
(8, 'Andres Puentes', 'jsjs@fet.edu.co', '$2y$10$tRVw1uq/u5M5u5gTxflqOem0IoyBt2x1/IJpGwQujQbtTLIxm.SJO', 'estudiante', '79787', '410001-18', '32284005', 'pasantia', NULL, 'GROK', 'tecnico', 'activo'),
(9, 'css', 'asad@fet.edu.co', '$2y$10$.0cFEqYtxXK9OWVuJ1wRCu4.3TVhSBRyU0h83ZtGqefW/nPyBVcc6', 'estudiante', '784681531', 'jsjds', '3465456', 'pasantia', NULL, 'ss', 'tecnico', 'activo'),
(10, 'Andres Felipe Puentes Rivera', '24@fet.edu.co', '$2y$10$PaPjgx1ArgYZ2M1bGcqxmuul7Jxx3d.2a0Q2x47Z6xqgOFN1NXHge', 'admin', '577', 'SOF1207871', '131537', '', NULL, NULL, '', 'activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `avances_proyecto`
--
ALTER TABLE `avances_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `registrado_por` (`registrado_por`);

--
-- Indices de la tabla `comentarios_proyecto`
--
ALTER TABLE `comentarios_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `solicitud_id` (`solicitud_id`),
  ADD KEY `resuelto_por` (`resuelto_por`);

--
-- Indices de la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seminario_id` (`seminario_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `materiales_apoyo`
--
ALTER TABLE `materiales_apoyo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `pasantias`
--
ALTER TABLE `pasantias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tutor_id` (`tutor_id`),
  ADD KEY `pasantias_ibfk_2` (`estudiante_id`);

--
-- Indices de la tabla `proyectos`
--
ALTER TABLE `proyectos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_proyectos_tutor` (`tutor_id`);

--
-- Indices de la tabla `seminarios`
--
ALTER TABLE `seminarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tutor_id` (`tutor_id`);

--
-- Indices de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `avances_proyecto`
--
ALTER TABLE `avances_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `comentarios_proyecto`
--
ALTER TABLE `comentarios_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materiales_apoyo`
--
ALTER TABLE `materiales_apoyo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pasantias`
--
ALTER TABLE `pasantias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `proyectos`
--
ALTER TABLE `proyectos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `seminarios`
--
ALTER TABLE `seminarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `solicitudes_registro`
--
ALTER TABLE `solicitudes_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `avances_proyecto`
--
ALTER TABLE `avances_proyecto`
  ADD CONSTRAINT `avances_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `avances_proyecto_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `comentarios_proyecto`
--
ALTER TABLE `comentarios_proyecto`
  ADD CONSTRAINT `comentarios_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `comentarios_proyecto_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  ADD CONSTRAINT `estudiantes_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `estudiantes_proyecto_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  ADD CONSTRAINT `historial_solicitudes_ibfk_2` FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  ADD CONSTRAINT `inscripciones_seminario_ibfk_1` FOREIGN KEY (`seminario_id`) REFERENCES `seminarios` (`id`),
  ADD CONSTRAINT `inscripciones_seminario_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `materiales_apoyo`
--
ALTER TABLE `materiales_apoyo`
  ADD CONSTRAINT `materiales_apoyo_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pasantias`
--
ALTER TABLE `pasantias`
  ADD CONSTRAINT `pasantias_ibfk_1` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pasantias_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `proyectos`
--
ALTER TABLE `proyectos`
  ADD CONSTRAINT `fk_proyectos_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `seminarios`
--
ALTER TABLE `seminarios`
  ADD CONSTRAINT `seminarios_ibfk_1` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
