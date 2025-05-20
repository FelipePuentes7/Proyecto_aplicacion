DELIMITER //

-- Procedimiento para asignar material a todos los estudiantes de un curso
CREATE PROCEDURE asignar_material_a_curso(IN p_material_id INT, IN p_curso_id INT)
BEGIN
    INSERT INTO material_estudiante_seminario (material_id, estudiante_id)
    SELECT p_material_id, e.id
    FROM estudiante_seminario e
    JOIN curso_estudiante_seminario ce ON e.id = ce.estudiante_id
    WHERE ce.curso_id = p_curso_id
    AND NOT EXISTS (
        SELECT 1 FROM material_estudiante_seminario
        WHERE material_id = p_material_id AND estudiante_id = e.id
    );
END //

-- Procedimiento para calificar una entrega
CREATE PROCEDURE calificar_entrega(
    IN p_entrega_id INT,
    IN p_calificacion DECIMAL(3,1),
    IN p_retroalimentacion TEXT
)
BEGIN
    UPDATE entrega_actividad_seminario
    SET estado = 'calificado',
        calificacion = p_calificacion,
        retroalimentacion = p_retroalimentacion,
        fecha_calificacion = NOW()
    WHERE id = p_entrega_id;
    
    -- Crear notificación para el estudiante
    INSERT INTO notificacion_seminario (usuario_id, titulo, mensaje, tipo)
    SELECT e.usuario_id, 
           CONCAT('Actividad calificada: ', a.titulo),
           CONCAT('Tu entrega para la actividad "', a.titulo, '" ha sido calificada.'),
           'info'
    FROM entrega_actividad_seminario ea
    JOIN estudiante_seminario e ON ea.estudiante_id = e.id
    JOIN actividad_seminario a ON ea.actividad_id = a.id
    WHERE ea.id = p_entrega_id;
END //

-- Función para obtener el promedio de calificaciones de un estudiante en un curso
CREATE FUNCTION promedio_estudiante_curso(p_estudiante_id INT, p_curso_id INT) 
RETURNS DECIMAL(3,2)
DETERMINISTIC
BEGIN
    DECLARE promedio DECIMAL(3,2);
    
    SELECT AVG(ea.calificacion) INTO promedio
    FROM entrega_actividad_seminario ea
    JOIN actividad_seminario a ON ea.actividad_id = a.id
    WHERE ea.estudiante_id = p_estudiante_id
    AND a.curso_id = p_curso_id
    AND ea.estado = 'calificado';
    
    RETURN IFNULL(promedio, 0);
END //

-- Procedimiento para crear una nueva actividad y notificar a los estudiantes
CREATE PROCEDURE crear_actividad(
    IN p_curso_id INT,
    IN p_tutor_id INT,
    IN p_titulo VARCHAR(100),
    IN p_descripcion TEXT,
    IN p_tipo ENUM('taller', 'cuestionario', 'proyecto', 'examen'),
    IN p_fecha_limite DATE,
    IN p_hora_limite TIME
)
BEGIN
    DECLARE nueva_actividad_id INT;
    
    -- Insertar la nueva actividad
    INSERT INTO actividad_seminario (curso_id, tutor_id, titulo, descripcion, tipo, fecha_limite, hora_limite)
    VALUES (p_curso_id, p_tutor_id, p_titulo, p_descripcion, p_tipo, p_fecha_limite, p_hora_limite);
    
    SET nueva_actividad_id = LAST_INSERT_ID();
    
    -- Notificar a todos los estudiantes del curso
    INSERT INTO notificacion_seminario (usuario_id, titulo, mensaje, tipo)
    SELECT e.usuario_id, 
           CONCAT('Nueva actividad: ', p_titulo),
           CONCAT('Se ha publicado una nueva actividad en tu curso: "', p_titulo, '". Fecha límite: ', p_fecha_limite),
           'info'
    FROM estudiante_seminario e
    JOIN curso_estudiante_seminario ce ON e.id = ce.estudiante_id
    WHERE ce.curso_id = p_curso_id;
END //

DELIMITER ;

