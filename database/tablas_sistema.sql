-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS seminario;
USE seminario;

-- Tabla de usuarios (para futura implementación de login)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    tipo ENUM('tutor', 'estudiante', 'admin') NOT NULL,
    avatar VARCHAR(255) DEFAULT '/assets/img/default-avatar.png',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de tutores
CREATE TABLE IF NOT EXISTS tutores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    especialidad VARCHAR(100),
    biografia TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de estudiantes
CREATE TABLE IF NOT EXISTS estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nivel_educativo VARCHAR(50),
    fecha_nacimiento DATE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de actividades
CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo ENUM('tarea', 'examen', 'proyecto', 'lectura', 'otro') DEFAULT 'tarea',
    fecha_limite DATE NOT NULL,
    hora_limite TIME NOT NULL,
    tutor_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutores(id) ON DELETE CASCADE
);

-- Tabla para archivos de actividades
CREATE TABLE IF NOT EXISTS archivos_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
);


-- Tabla para entregas de actividades
CREATE TABLE IF NOT EXISTS entregas_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    comentario TEXT,
    comentario_tutor TEXT,
    calificacion DECIMAL(3,1) DEFAULT NULL,
    estado ENUM('pendiente', 'revisado', 'calificado') DEFAULT 'pendiente',
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
);

-- Tabla para archivos de entregas
CREATE TABLE IF NOT EXISTS archivos_entrega (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entrega_id) REFERENCES entregas_actividad(id) ON DELETE CASCADE
);

-- Tabla para clases virtuales
CREATE TABLE IF NOT EXISTS clases_virtuales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    duracion INT NOT NULL DEFAULT 60,
    plataforma VARCHAR(50) NOT NULL DEFAULT 'Zoom',
    enlace VARCHAR(255) NOT NULL,
    tutor_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutores(id) ON DELETE CASCADE
);

-- Tabla para materiales de apoyo
CREATE TABLE IF NOT EXISTS materiales_apoyo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo ENUM('video', 'documento', 'enlace', 'otro') NOT NULL,
    plataforma VARCHAR(50),
    enlace VARCHAR(255),
    tutor_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutores(id) ON DELETE CASCADE
);

-- Tabla para archivos de materiales
CREATE TABLE IF NOT EXISTS archivos_material (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materiales_apoyo(id) ON DELETE CASCADE
);

-- Tabla para asignación de materiales a estudiantes
CREATE TABLE IF NOT EXISTS asignaciones_material (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materiales_apoyo(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
);

-- Tabla para asignación de clases a estudiantes
CREATE TABLE IF NOT EXISTS asignaciones_clase (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clase_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    asistencia BOOLEAN DEFAULT FALSE,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clase_id) REFERENCES clases_virtuales(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
);

-- Tabla para notificaciones
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('actividad', 'clase', 'material', 'calificacion', 'general') NOT NULL,
    usuario_id INT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Insertar datos de prueba para usuarios
INSERT INTO usuarios (nombre, apellido, email, password, tipo, avatar) VALUES
('Juan', 'Pérez', 'tutor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tutor', '/assets/img/avatar-tutor.png'),
('María', 'López', 'estudiante@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante', '/assets/img/avatar-estudiante.png');

-- Insertar datos de prueba para tutores
INSERT INTO tutores (usuario_id, especialidad, biografia) VALUES
(1, 'Programación', 'Especialista en desarrollo web y programación orientada a objetos.');

-- Insertar datos de prueba para estudiantes
INSERT INTO estudiantes (usuario_id, nivel_educativo, fecha_nacimiento) VALUES
(2, 'Universidad', '2000-01-15');

-- Consultar actividades con total de entregas y entregas pendientes
SELECT a.*, 
       (SELECT COUNT(*) FROM entregas_actividad WHERE actividad_id = a.id) AS total_entregas,
       (SELECT COUNT(*) FROM entregas_actividad WHERE actividad_id = a.id AND estado = 'pendiente') AS entregas_pendientes
FROM actividades a
JOIN tutores t ON a.tutor_id = t.id
WHERE t.id = :tutor_id
ORDER BY a.fecha_creacion DESC;

ALTER TABLE actividades ADD COLUMN tutor_id INT NOT NULL;