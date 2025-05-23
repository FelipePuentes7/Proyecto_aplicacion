-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-05-2025 a las 02:37:20
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
-- Base de datos: `proyecto`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_limite` date NOT NULL,
  `hora_limite` time NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `id_tutor` int(11) NOT NULL,
  `tutor_id` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `puntaje` decimal(5,2) DEFAULT NULL,
  `permitir_entregas_tarde` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `actividades`
--

INSERT INTO `actividades` (`id`, `titulo`, `descripcion`, `fecha_limite`, `hora_limite`, `tipo`, `id_tutor`, `tutor_id`, `fecha_creacion`, `puntaje`, `permitir_entregas_tarde`) VALUES
(59, 'Actividad ', 'asd', '2025-05-15', '14:29:00', 'examen', 0, 1, '2025-05-15 05:29:11', 5.00, 1),
(60, 'pendiente por calificar', 'asd', '2025-05-15', '14:31:00', 'examen', 0, 1, '2025-05-15 05:29:38', 4.00, 1),
(61, 'Pendiente por entregar', 'asdasd', '2025-05-15', '13:32:00', 'tarea', 0, 1, '2025-05-15 05:30:20', 5.00, 0),
(62, 'asdasd', 'asdasdasd', '2025-05-31', '08:57:00', 'tarea', 0, 1, '2025-05-17 00:57:52', 4.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_actividad`
--

CREATE TABLE `archivos_actividad` (
  `id` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_archivo` varchar(100) DEFAULT '',
  `tamano_archivo` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `archivos_actividad`
--

INSERT INTO `archivos_actividad` (`id`, `id_actividad`, `nombre_archivo`, `ruta_archivo`, `fecha_subida`, `tipo_archivo`, `tamano_archivo`) VALUES
(20, 59, 'images (1) (1).jpeg', '../../uploads/actividades/59/images (1) (1).jpeg', '2025-05-15 05:29:11', 'jpeg', 5242),
(21, 60, 'images (1) (1).jpeg', '../../uploads/actividades/60/images (1) (1).jpeg', '2025-05-15 05:29:38', 'jpeg', 5242),
(22, 61, 'images (1) (1).jpeg', '../../uploads/actividades/61/images (1) (1).jpeg', '2025-05-15 05:30:20', 'jpeg', 5242),
(23, 62, 'images (1) (1).jpeg', '../../uploads/actividades/62/images (1) (1).jpeg', '2025-05-17 00:57:52', 'jpeg', 5242);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_entrega`
--

CREATE TABLE `archivos_entrega` (
  `id` int(11) NOT NULL,
  `id_entrega` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(100) NOT NULL,
  `tamano_archivo` int(11) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `archivos_entrega`
--

INSERT INTO `archivos_entrega` (`id`, `id_entrega`, `nombre_archivo`, `ruta_archivo`, `tipo_archivo`, `tamano_archivo`, `fecha_subida`) VALUES
(18, 58, 'images (1) (1).jpeg', '../../uploads/entregas/58/68257c05166ef_images (1) (1).jpeg', 'image/jpeg', 5242, '2025-05-15 05:30:45'),
(19, 59, 'images (1) (1).jpeg', '../../uploads/entregas/59/68257c138f0fd_images (1) (1).jpeg', 'image/jpeg', 5242, '2025-05-15 05:30:59'),
(20, 62, 'material_diagnostics.php', '../../uploads/entregas/62/682a904a6e2c3_material_diagnostics.php', 'application/octet-stream', 11819, '2025-05-19 01:58:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_material`
--

CREATE TABLE `archivos_material` (
  `id` int(11) NOT NULL,
  `id_material` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(100) NOT NULL,
  `tamano_archivo` int(11) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_clase`
--

CREATE TABLE `asignaciones_clase` (
  `id` int(11) NOT NULL,
  `clase_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `asistencia` tinyint(1) DEFAULT 0,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_material`
--

CREATE TABLE `asignaciones_material` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asignaciones_material`
--

INSERT INTO `asignaciones_material` (`id`, `material_id`, `estudiante_id`, `fecha_asignacion`) VALUES
(1692, 1266, 7, '2025-05-19 05:09:48'),
(1693, 1266, 21, '2025-05-19 05:09:50'),
(1694, 1267, 7, '2025-05-19 05:10:00'),
(1695, 1268, 7, '2025-05-19 05:11:30'),
(1696, 1268, 20, '2025-05-19 05:11:30'),
(1697, 1268, 22, '2025-05-19 05:11:30'),
(1698, 1268, 23, '2025-05-19 05:11:30'),
(1699, 1269, 7, '2025-05-19 12:33:22'),
(1700, 1269, 20, '2025-05-19 12:33:22'),
(1701, 1269, 22, '2025-05-19 12:33:22'),
(1702, 1269, 23, '2025-05-19 12:33:22'),
(1703, 1270, 7, '2025-05-20 00:19:18'),
(1704, 1270, 20, '2025-05-20 00:19:18'),
(1705, 1270, 22, '2025-05-20 00:19:18'),
(1706, 1270, 23, '2025-05-20 00:19:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_tutor`
--

CREATE TABLE `asignaciones_tutor` (
  `id` int(11) NOT NULL,
  `tutor_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Estructura de tabla para la tabla `clases_virtuales`
--

CREATE TABLE `clases_virtuales` (
  `id` int(11) NOT NULL,
  `tutor_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `duracion` int(11) NOT NULL,
  `plataforma` varchar(50) NOT NULL,
  `enlace` varchar(255) NOT NULL,
  `id_tutor` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clases_virtuales`
--

INSERT INTO `clases_virtuales` (`id`, `tutor_id`, `titulo`, `descripcion`, `fecha`, `hora`, `duracion`, `plataforma`, `enlace`, `id_tutor`, `fecha_creacion`) VALUES
(23, 1, 'sadas', 'dasdasdasd', '2025-05-20', '09:35:00', 60, 'Zoom', 'https://youtu.be/koc4qnxS5qc', 0, '2025-05-20 00:33:50');

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
-- Estructura de tabla para la tabla `enlaces_video`
--

CREATE TABLE `enlaces_video` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `plataforma` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas_actividad`
--

CREATE TABLE `entregas_actividad` (
  `id` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `comentario_tutor` text DEFAULT NULL,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `fecha_calificacion` datetime DEFAULT NULL,
  `estado` enum('pendiente','revisado','calificado') DEFAULT 'pendiente',
  `fecha_entrega` timestamp NOT NULL DEFAULT current_timestamp(),
  `actividad_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entregas_actividad`
--

INSERT INTO `entregas_actividad` (`id`, `id_actividad`, `id_estudiante`, `comentario`, `comentario_tutor`, `calificacion`, `fecha_calificacion`, `estado`, `fecha_entrega`, `actividad_id`) VALUES
(58, 59, 1, 'asd', '', 5.00, '2025-05-15 00:31:13', 'calificado', '2025-05-15 05:30:45', 0),
(59, 61, 1, 'asd', '', 4.00, '2025-05-16 18:08:10', 'calificado', '2025-05-15 05:30:59', 0),
(60, 61, 7, '', NULL, NULL, NULL, 'pendiente', '2025-05-16 22:46:30', 0),
(61, 59, 7, '', '', 4.00, '2025-05-16 18:09:21', 'calificado', '2025-05-16 23:09:03', 0),
(62, 60, 7, 'dasdadasdad', NULL, NULL, NULL, 'pendiente', '2025-05-19 01:58:34', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas_pasantia`
--

CREATE TABLE `entregas_pasantia` (
  `id` int(11) NOT NULL,
  `pasantia_id` int(11) NOT NULL,
  `numero_avance` tinyint(4) NOT NULL,
  `archivo_entregado` varchar(255) DEFAULT NULL,
  `comentario_estudiante` text DEFAULT NULL,
  `comentario_tutor` text DEFAULT NULL,
  `estado` enum('pendiente','revisado','corregir','aprobado') DEFAULT 'pendiente',
  `nota` decimal(4,2) DEFAULT NULL,
  `fecha_entrega` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `semestre` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id`, `usuario_id`, `codigo_estudiante`, `semestre`, `nombre`, `avatar`, `foto_perfil`) VALUES
(1, 1, NULL, NULL, 'Noah Wilson', 'https://randomuser.me/api/portraits/men/32.jpg', NULL),
(7, 7, NULL, NULL, 'Felipe Puentes', '/uploads/avatars/6828e1259e4b5_7.jpeg', NULL),
(21, 21, NULL, NULL, 'Derek', NULL, NULL);

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grabaciones`
--

CREATE TABLE `grabaciones` (
  `id` int(11) NOT NULL,
  `clase_id` int(11) NOT NULL,
  `url_grabacion` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `thumbnail_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(12, 8, 'Andres Felipe Puentes Rivera', '24@fet.edu.co', '577', 'SOF1207871', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-20 23:46:14', NULL),
(13, 11, 'Andres Felipe Puentes Rivera', '2@fet.edu.co', '5775445', 'SOF1207871', 'tecnico', 'pasantia', NULL, 'ujiji', 'estudiante', 'aprobado', NULL, '2025-04-25 01:54:24', 10),
(14, 12, 'ams ms', 'lskdldks@fet.edu.co', '878786', 'mnsmsm57', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'rechazado', NULL, '2025-04-27 16:37:22', NULL),
(15, 13, 'djjdjd', 'jdjddjjjjj@fet.edu.co', '4547', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 16:38:43', NULL),
(16, 14, 'jorge', '786865@fet.edu.co', '454721', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 16:52:23', NULL),
(17, 15, 'jorge', '78665@fet.edu.co', '4721', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 16:57:34', NULL),
(18, 16, 'jorge', '7665@fet.edu.co', '472155', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 16:59:06', NULL),
(19, 17, 'jorge', 'j7665@fet.edu.co', '4728955', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 17:01:40', NULL),
(20, 18, 'jorges', 'j57665@fet.edu.co', '7897', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-04-27 17:40:44', 10),
(21, 10, 'juan ', 'a@fet.edu.co', '57876855', NULL, NULL, NULL, NULL, NULL, 'tutor', 'rechazado', NULL, '2025-05-16 17:53:24', NULL),
(22, 9, 'djjdjd', 'jdjdd@fet.edu.co', '454', '5787', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'rechazado', NULL, '2025-05-16 17:53:27', NULL),
(23, 19, 'Derek', 'derek_quevedosa@fet.edu.co', '1016714254', 'Sof12345', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'rechazado', NULL, '2025-05-16 17:53:30', NULL),
(24, 20, 'Derek', 'derek@fet.edu.co', '1016714254', NULL, NULL, NULL, NULL, NULL, 'tutor', 'aprobado', NULL, '2025-05-16 19:08:24', NULL),
(25, 21, 'asd', 'asd@fet.edu.co', '1781268736', 'Sof12345', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-05-16 20:50:38', NULL),
(26, 22, 'ww', 'ww@fet.edu.co', '1234567890', 'Sof12345', 'tecnico', 'seminario', NULL, NULL, 'estudiante', 'aprobado', NULL, '2025-05-16 21:26:09', 22);

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
(1, 1, 7, 'inscrito', 0, NULL);

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
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `plataforma` varchar(50) DEFAULT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `enlace` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materiales_apoyo`
--

INSERT INTO `materiales_apoyo` (`id`, `titulo`, `descripcion`, `archivo`, `tipo`, `creado_por`, `fecha_subida`, `plataforma`, `thumbnail_url`, `enlace`) VALUES
(1266, '123', '123', NULL, '', NULL, '2025-05-19 05:09:48', 'youtube', 'https://img.youtube.com/vi/RlGqDGiBvO0/maxresdefault.jpg', 'https://youtu.be/RlGqDGiBvO0'),
(1267, 'asd', 'asd', NULL, '', NULL, '2025-05-19 05:10:00', 'youtube', 'https://via.placeholder.com/640x360.png?text=Youtube+Video', 'http://localhost:8000/views/seminario_tutor/material_tutor.php?step=1&category=video_links'),
(1268, '23222', '2222222', NULL, '', NULL, '2025-05-19 05:11:30', 'youtube', 'https://img.youtube.com/vi/lUHlckT8a44/maxresdefault.jpg', 'https://youtu.be/lUHlckT8a44'),
(1269, 'hsgdhagsdi', 'hasdoasod', NULL, '', NULL, '2025-05-19 12:33:22', 'youtube', 'https://img.youtube.com/vi/lUHlckT8a44/maxresdefault.jpg', 'https://youtu.be/lUHlckT8a44'),
(1270, 'wasdasda', 'wdsada', NULL, '', NULL, '2025-05-20 00:19:18', 'youtube', 'https://img.youtube.com/vi/RlGqDGiBvO0/maxresdefault.jpg', 'https://youtu.be/RlGqDGiBvO0');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `material_estudiante`
--

CREATE TABLE `material_estudiante` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `visto` tinyint(1) DEFAULT 0,
  `fecha_visto` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_chat`
--

CREATE TABLE `mensajes_chat` (
  `id` int(11) NOT NULL,
  `pasantia_id` int(11) NOT NULL,
  `emisor_id` int(11) NOT NULL,
  `receptor_id` int(11) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('actividad','clase','material','calificacion','general') NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
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
(2, 9, 'jjss', '', 'ss', '', '', '', '', '2025-04-21', '2025-04-22', 'pendiente', NULL, NULL, '2025-04-20 22:23:52', NULL),
(3, 11, 'gvgv', '', 'ujiji', '', '', '', '', '2025-04-24', '2025-04-28', 'pendiente', 6, NULL, '2025-04-25 01:58:10', NULL);

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
-- Estructura de tabla para la tabla `tutores`
--

CREATE TABLE `tutores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `biografia` text DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL
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
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `avatar` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `documento`, `codigo_estudiante`, `telefono`, `opcion_grado`, `nombre_proyecto`, `nombre_empresa`, `ciclo`, `estado`, `avatar`, `foto_perfil`) VALUES
(1, 'pipe', 'Danl@fet.edu.co', '$2y$10$RVtSEQKf8mjc4lpeFdNHTul4bXJYBFiN.1GCX5/DWuBV2dKAwBON6', 'estudiante', '015648', 'SOF120787', '3228400865', 'proyecto', 'AL', NULL, 'tecnico', 'activo', NULL, NULL),
(2, 'Olivia Brown', 'olivia.b@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'activo', 'https://randomuser.me/api/portraits/women/65.jpg', NULL),
(3, 'Liam Smith', 'liam.s@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'activo', 'https://randomuser.me/api/portraits/men/22.jpg', NULL),
(4, 'Ava Davis', 'ava.d@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'activo', 'https://randomuser.me/api/portraits/women/17.jpg', NULL),
(5, 'Felipe P', 'nl@fet.edu.co', '$2y$10$JeABZWziqUfDvHJmgBZ47.6e4tgrMH//Ytk15oQlZ8NfrhMn61HnS', 'estudiante', '016488522', 'SOF120787', '3228400865', 'proyecto', 'ALi', NULL, 'tecnico', 'activo', NULL, NULL),
(6, 'Andres Felipe Puentes Rivera', 'mamama@fet.edu.co', '$2y$10$zl26D2pgbS1KynJYEWSZPOlxZxOBJc58O2cK8lFmgxlmXjMj/vIlS', 'tutor', '5787687', NULL, '13153453', NULL, NULL, NULL, NULL, 'activo', NULL, NULL),
(7, 'Felipe Puentes', '7@fet.edu.co', '$2y$10$.XqcSVbPk9aXB49S.Z9keetP1qTm2hpAg65CbmFajjCEkHw3vd1SO', 'estudiante', '0156485778', 'SOF1207871', '3228400865', 'seminario', NULL, NULL, 'tecnico', 'activo', 'https://cdn-icons-png.flaticon.com/512/4319/4319130.png', NULL),
(8, 'Andres Puentes', 'jsjs@fet.edu.co', '$2y$10$tRVw1uq/u5M5u5gTxflqOem0IoyBt2x1/IJpGwQujQbtTLIxm.SJO', 'estudiante', '79787', '410001-18', '32284005', 'pasantia', NULL, 'GROK', 'tecnico', 'activo', NULL, NULL),
(9, 'css', 'asad@fet.edu.co', '$2y$10$.0cFEqYtxXK9OWVuJ1wRCu4.3TVhSBRyU0h83ZtGqefW/nPyBVcc6', 'estudiante', '784681531', 'jsjds', '3465456', 'pasantia', NULL, 'ss', 'tecnico', 'activo', NULL, NULL),
(10, 'Andres Felipe Puentes Rivera', '24@fet.edu.co', '1234', 'admin', '577', 'SOF1207871', '131537', '', NULL, NULL, '', 'activo', NULL, NULL),
(11, 'Andres Felipe Puentes Rivera', '2@fet.edu.co', '$2y$10$TJW3VrQb1JlM2OKCeQgg5.3b8ZTQ0STtmFGSC7bSKmhP4i32ERCge', 'estudiante', '5775445', 'SOF1207871', '3228400865', 'pasantia', NULL, 'ujiji', 'tecnico', 'activo', NULL, NULL),
(20, 'jorges', 'j57665@fet.edu.co', '$2y$10$6Jkm1hBp63LXhb7ZTAWB3ONZ2YFY7kWCd8XiR4pZE3QOWbBhpF/5K', 'estudiante', '7897', '5787', '3228400865', 'seminario', NULL, NULL, 'tecnico', 'activo', NULL, NULL),
(21, 'Derek', 'derek@fet.edu.co', '$2y$10$SviSrG/0pHqZr2phgVApTeNbwRXhJt/iZo5BUm5aQyCVfdVfJFJ0K', 'admin', '1016714254', 'Sof12345', '3228129970', '', NULL, NULL, '', 'activo', NULL, NULL),
(22, 'asd', 'asd@fet.edu.co', '$2y$10$mz8zqLGwEqr51iCxU5qEJeEeJhBlPgXnjfikgrcFIb0S3f7qIObJu', 'estudiante', '1781268736', 'Sof12345', '3228129970', 'seminario', NULL, NULL, 'tecnico', 'activo', NULL, NULL),
(23, 'ww', 'ww@fet.edu.co', '$2y$10$lU8Pd/bSvKvaEhov/oHFUuvQfLOdfOhcKqgTsubvbjA.UxyyOanGK', 'estudiante', '1234567890', 'Sof12345', '3228129970', 'seminario', NULL, NULL, 'tecnico', 'activo', NULL, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `archivos_actividad`
--
ALTER TABLE `archivos_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_actividad` (`id_actividad`);

--
-- Indices de la tabla `archivos_entrega`
--
ALTER TABLE `archivos_entrega`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_entrega` (`id_entrega`);

--
-- Indices de la tabla `archivos_material`
--
ALTER TABLE `archivos_material`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_material` (`id_material`);

--
-- Indices de la tabla `asignaciones_clase`
--
ALTER TABLE `asignaciones_clase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clase_id` (`clase_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `asignaciones_material`
--
ALTER TABLE `asignaciones_material`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `asignaciones_tutor`
--
ALTER TABLE `asignaciones_tutor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tutor_estudiante` (`tutor_id`,`estudiante_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `avances_proyecto`
--
ALTER TABLE `avances_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `registrado_por` (`registrado_por`);

--
-- Indices de la tabla `clases_virtuales`
--
ALTER TABLE `clases_virtuales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `comentarios_proyecto`
--
ALTER TABLE `comentarios_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `enlaces_video`
--
ALTER TABLE `enlaces_video`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indices de la tabla `entregas_actividad`
--
ALTER TABLE `entregas_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_actividad` (`id_actividad`),
  ADD KEY `actividad_id` (`actividad_id`);

--
-- Indices de la tabla `entregas_pasantia`
--
ALTER TABLE `entregas_pasantia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pasantia_id` (`pasantia_id`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_estudiante` (`codigo_estudiante`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proyecto_id` (`proyecto_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `grabaciones`
--
ALTER TABLE `grabaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clase_id` (`clase_id`);

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
-- Indices de la tabla `material_estudiante`
--
ALTER TABLE `material_estudiante`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indices de la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pasantia_id` (`pasantia_id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

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
-- Indices de la tabla `tutores`
--
ALTER TABLE `tutores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

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
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `archivos_actividad`
--
ALTER TABLE `archivos_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `archivos_entrega`
--
ALTER TABLE `archivos_entrega`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `archivos_material`
--
ALTER TABLE `archivos_material`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asignaciones_clase`
--
ALTER TABLE `asignaciones_clase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asignaciones_material`
--
ALTER TABLE `asignaciones_material`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1707;

--
-- AUTO_INCREMENT de la tabla `asignaciones_tutor`
--
ALTER TABLE `asignaciones_tutor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `avances_proyecto`
--
ALTER TABLE `avances_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clases_virtuales`
--
ALTER TABLE `clases_virtuales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `comentarios_proyecto`
--
ALTER TABLE `comentarios_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `enlaces_video`
--
ALTER TABLE `enlaces_video`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entregas_actividad`
--
ALTER TABLE `entregas_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `entregas_pasantia`
--
ALTER TABLE `entregas_pasantia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `grabaciones`
--
ALTER TABLE `grabaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `historial_solicitudes`
--
ALTER TABLE `historial_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `inscripciones_seminario`
--
ALTER TABLE `inscripciones_seminario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materiales_apoyo`
--
ALTER TABLE `materiales_apoyo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1271;

--
-- AUTO_INCREMENT de la tabla `material_estudiante`
--
ALTER TABLE `material_estudiante`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pasantias`
--
ALTER TABLE `pasantias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `tutores`
--
ALTER TABLE `tutores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `archivos_actividad`
--
ALTER TABLE `archivos_actividad`
  ADD CONSTRAINT `archivos_actividad_ibfk_1` FOREIGN KEY (`id_actividad`) REFERENCES `actividades` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `archivos_entrega`
--
ALTER TABLE `archivos_entrega`
  ADD CONSTRAINT `archivos_entrega_ibfk_1` FOREIGN KEY (`id_entrega`) REFERENCES `entregas_actividad` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `archivos_material`
--
ALTER TABLE `archivos_material`
  ADD CONSTRAINT `archivos_material_ibfk_1` FOREIGN KEY (`id_material`) REFERENCES `materiales_apoyo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignaciones_clase`
--
ALTER TABLE `asignaciones_clase`
  ADD CONSTRAINT `asignaciones_clase_ibfk_1` FOREIGN KEY (`clase_id`) REFERENCES `clases_virtuales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_clase_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignaciones_material`
--
ALTER TABLE `asignaciones_material`
  ADD CONSTRAINT `asignaciones_material_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales_apoyo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_material_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignaciones_tutor`
--
ALTER TABLE `asignaciones_tutor`
  ADD CONSTRAINT `asignaciones_tutor_ibfk_1` FOREIGN KEY (`tutor_id`) REFERENCES `tutores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_tutor_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE;

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
-- Filtros para la tabla `enlaces_video`
--
ALTER TABLE `enlaces_video`
  ADD CONSTRAINT `enlaces_video_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales_apoyo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entregas_pasantia`
--
ALTER TABLE `entregas_pasantia`
  ADD CONSTRAINT `entregas_pasantia_ibfk_1` FOREIGN KEY (`pasantia_id`) REFERENCES `pasantias` (`id`);

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estudiantes_proyecto`
--
ALTER TABLE `estudiantes_proyecto`
  ADD CONSTRAINT `estudiantes_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`),
  ADD CONSTRAINT `estudiantes_proyecto_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `grabaciones`
--
ALTER TABLE `grabaciones`
  ADD CONSTRAINT `grabaciones_ibfk_1` FOREIGN KEY (`clase_id`) REFERENCES `clases_virtuales` (`id`) ON DELETE CASCADE;

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
-- Filtros para la tabla `material_estudiante`
--
ALTER TABLE `material_estudiante`
  ADD CONSTRAINT `material_estudiante_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales_apoyo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_estudiante_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  ADD CONSTRAINT `mensajes_chat_ibfk_1` FOREIGN KEY (`pasantia_id`) REFERENCES `pasantias` (`id`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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

--
-- Filtros para la tabla `tutores`
--
ALTER TABLE `tutores`
  ADD CONSTRAINT `tutores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
