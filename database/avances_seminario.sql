-- Insertar categorías de material
INSERT INTO categoria_material_seminario (nombre, descripcion, icono) VALUES
('Videos', 'Videos educativos y tutoriales', 'fas fa-video'),
('Documentación', 'Manuales, guías y documentos de referencia', 'fas fa-file-alt'),
('Herramientas', 'Software y herramientas útiles', 'fas fa-tools'),
('Motivación', 'Contenido motivacional', 'fas fa-fire');

-- Insertar usuarios
INSERT INTO usuarios_seminario (nombre, apellido, email, password, avatar, tipo) VALUES
-- Tutores
('Juan', 'Pérez', 'juan.perez@fet.edu', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/men/1.jpg', 'tutor'),
('María', 'Rodríguez', 'maria.rodriguez@fet.edu', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/women/1.jpg', 'tutor'),
-- Estudiantes
('Noah', 'Wilson', 'noah.w@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/men/32.jpg', 'estudiante'),
('Olivia', 'Brown', 'olivia.b@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/women/65.jpg', 'estudiante'),
('Liam', 'Smith', 'liam.s@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/men/22.jpg', 'estudiante'),
('Ava', 'Davis', 'ava.d@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/women/17.jpg', 'estudiante'),
('Ethan', 'Miller', 'ethan.m@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'https://randomuser.me/api/portraits/men/42.jpg', 'estudiante');

-- Insertar tutores
INSERT INTO tutor_seminario (usuario_id, especialidad, biografia) VALUES
(1, 'Bases de Datos', 'Especialista en bases de datos relacionales con más de 10 años de experiencia.'),
(2, 'Programación Web', 'Desarrolladora web con experiencia en PHP, JavaScript y frameworks modernos.');

-- Insertar estudiantes
INSERT INTO estudiante_seminario (usuario_id, codigo_estudiante, programa, semestre) VALUES
(3, 'EST001', 'Ingeniería de Sistemas', 5),
(4, 'EST002', 'Ingeniería de Sistemas', 5),
(5, 'EST003', 'Ingeniería de Sistemas', 5),
(6, 'EST004', 'Ingeniería de Sistemas', 5),
(7, 'EST005', 'Ingeniería de Sistemas', 5);

-- Insertar cursos
INSERT INTO curso_seminario (nombre, descripcion, codigo_curso, fecha_inicio, fecha_fin, estado) VALUES
('Seminario - Base de Datos Relacionales', 'Curso avanzado sobre diseño y optimización de bases de datos relacionales', 'SEM-BD-001', '2025-01-01', '2025-06-30', 'activo'),
('Desarrollo Web con PHP', 'Fundamentos y técnicas avanzadas de desarrollo web con PHP', 'SEM-PHP-001', '2025-01-01', '2025-06-30', 'activo');

-- Asignar tutores a cursos
INSERT INTO curso_tutor_seminario (curso_id, tutor_id) VALUES
(1, 1), -- Juan Pérez enseña Bases de Datos
(2, 2); -- María Rodríguez enseña PHP

-- Inscribir estudiantes a cursos
INSERT INTO curso_estudiante_seminario (curso_id, estudiante_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), -- Todos los estudiantes en el curso de BD
(2, 1), (2, 3), (2, 5); -- Algunos estudiantes en el curso de PHP

-- Insertar clases programadas
INSERT INTO clase_seminario (curso_id, tutor_id, titulo, descripcion, fecha, hora, duracion, plataforma, enlace) VALUES
(1, 1, 'Introducción a la Programación', 'Clase introductoria sobre conceptos básicos de programación.', '2025-05-06', '10:00:00', 60, 'meet', 'https://meet.google.com/abc-defg-hij'),
(1, 1, 'Estructuras de Datos', 'Revisión de estructuras de datos fundamentales.', '2025-05-08', '14:30:00', 90, 'classroom', 'https://classroom.google.com/c/123456789'),
(1, 1, 'Algoritmos de Búsqueda', 'Análisis y aplicación de algoritmos de búsqueda.', '2025-05-10', '09:00:00', 120, 'meet', 'https://meet.google.com/xyz-abcd-efg');

-- Insertar actividades
INSERT INTO actividad_seminario (curso_id, tutor_id, titulo, descripcion, tipo, fecha_publicacion, fecha_limite, hora_limite) VALUES
(1, 1, 'Ejercicios de Programación', 'Completar los ejercicios 1-10 del capítulo 3.', 'taller', '2025-04-01 10:00:00', '2025-05-15', '23:59:00'),
(1, 1, 'Proyecto: Aplicación Web', 'Desarrollar una aplicación web simple utilizando HTML, CSS y JavaScript.', 'proyecto', '2025-04-05 14:30:00', '2025-05-20', '23:59:00'),
(1, 1, 'Cuestionario: Fundamentos de Bases de Datos', 'Responder el cuestionario sobre normalización y diseño de bases de datos.', 'cuestionario', '2025-04-10 09:00:00', '2025-05-07', '23:59:00');

-- Insertar entregas de actividades
INSERT INTO entrega_actividad_seminario (actividad_id, estudiante_id, comentario, fecha_entrega, estado) VALUES
(1, 3, 'Aquí está mi entrega del taller 1', '2025-01-08 15:30:00', 'pendiente'),
(2, 4, 'Entrega del taller 2', '2025-01-09 10:15:00', 'calificado'),
(3, 5, 'Entrega del taller 3', '2025-01-10 18:45:00', 'pendiente');

-- Actualizar la entrega calificada
UPDATE entrega_actividad_seminario SET calificacion = 4.5, retroalimentacion = 'Buen trabajo, pero falta mejorar la normalización', fecha_calificacion = '2025-01-11 09:30:00' WHERE id = 2;

-- Insertar material de apoyo
INSERT INTO material_apoyo_seminario (curso_id, tutor_id, categoria_id, titulo, descripcion, tipo, url, plataforma) VALUES
(1, 1, 1, 'Introduccion a HTML y CSS', 'Video tutorial sobre HTML y CSS básico', 'video', 'https://www.youtube.com/watch?v=example1', 'youtube'),
(1, 1, 1, 'Introduccion a PHP', 'Video tutorial sobre PHP básico', 'video', 'https://www.youtube.com/watch?v=example2', 'youtube'),
(1, 1, 1, 'JavaScript Esencial', 'Video tutorial sobre JavaScript', 'video', 'https://www.youtube.com/watch?v=example3', 'youtube'),
(1, 1, 1, 'MySQL Basico', 'Video tutorial sobre MySQL', 'video', 'https://www.youtube.com/watch?v=example4', 'youtube');

-- Asignar material a estudiantes
INSERT INTO material_estudiante_seminario (material_id, estudiante_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
(2, 1), (2, 2), (2, 3),
(3, 1), (3, 4), (3, 5),
(4, 2), (4, 3), (4, 5);

