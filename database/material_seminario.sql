-- Obtener todas las actividades pendientes para un estudiante
SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, c.nombre AS curso
FROM actividad_seminario a
JOIN curso_seminario c ON a.curso_id = c.id
JOIN curso_estudiante_seminario ce ON c.id = ce.curso_id
WHERE ce.estudiante_id = 1 -- ID del estudiante
AND a.fecha_limite >= CURDATE()
AND NOT EXISTS (
    SELECT 1 FROM entrega_actividad_seminario ea 
    WHERE ea.actividad_id = a.id AND ea.estudiante_id = 1
)
ORDER BY a.fecha_limite, a.hora_limite;

-- Obtener todas las entregas pendientes de calificar para un tutor
SELECT ea.id AS entrega_id, a.titulo AS actividad, 
       CONCAT(u.nombre, ' ', u.apellido) AS estudiante,
       ea.fecha_entrega
FROM entrega_actividad_seminario ea
JOIN actividad_seminario a ON ea.actividad_id = a.id
JOIN estudiante_seminario e ON ea.estudiante_id = e.id
JOIN usuarios_seminario u ON e.usuario_id = u.id
WHERE a.tutor_id = 1 -- ID del tutor
AND ea.estado = 'pendiente'
ORDER BY ea.fecha_entrega;

-- Obtener las próximas clases para un curso
SELECT c.id, c.titulo, c.descripcion, c.fecha, c.hora, c.duracion, c.plataforma, c.enlace
FROM clase_seminario c
WHERE c.curso_id = 1 -- ID del curso
AND c.fecha >= CURDATE()
ORDER BY c.fecha, c.hora;

-- Obtener el material de apoyo disponible para un estudiante
SELECT ma.id, ma.titulo, ma.descripcion, ma.tipo, ma.url, ma.archivo,
       cm.nombre AS categoria, me.visto
FROM material_apoyo_seminario ma
JOIN categoria_material_seminario cm ON ma.categoria_id = cm.id
JOIN material_estudiante_seminario me ON ma.id = me.material_id
WHERE me.estudiante_id = 1 -- ID del estudiante
AND ma.estado = 'activo'
ORDER BY ma.fecha_publicacion DESC;

-- Obtener estadísticas de entregas para una actividad
SELECT 
    a.titulo,
    COUNT(DISTINCT ce.estudiante_id) AS total_estudiantes,
    COUNT(DISTINCT ea.estudiante_id) AS entregas_realizadas,
    ROUND((COUNT(DISTINCT ea.estudiante_id) / COUNT(DISTINCT ce.estudiante_id)) * 100, 2) AS porcentaje_entregas,
    AVG(ea.calificacion) AS promedio_calificacion
FROM actividad_seminario a
JOIN curso_estudiante_seminario ce ON a.curso_id = ce.curso_id
LEFT JOIN entrega_actividad_seminario ea ON a.id = ea.actividad_id AND ce.estudiante_id = ea.estudiante_id
WHERE a.id = 1 -- ID de la actividad
GROUP BY a.id, a.titulo;