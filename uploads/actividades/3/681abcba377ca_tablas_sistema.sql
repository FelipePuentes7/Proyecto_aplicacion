-- Tabla para clases virtuales
CREATE TABLE IF NOT EXISTS clases_virtuales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    duracion INT NOT NULL,
    plataforma VARCHAR(50) NOT NULL,
    enlace VARCHAR(255) NOT NULL,
    id_tutor INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para actividades/tareas
CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_limite DATE NOT NULL,
    hora_limite TIME NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    id_tutor INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para archivos de actividades
CREATE TABLE IF NOT EXISTS archivos_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_actividad INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_actividad) REFERENCES actividades(id) ON DELETE CASCADE
);

-- Tabla para materiales de apoyo
CREATE TABLE IF NOT EXISTS materiales_apoyo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    categoria VARCHAR(100) NOT NULL,
    id_tutor INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para archivos de materiales
CREATE TABLE IF NOT EXISTS archivos_material (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_material INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_material) REFERENCES materiales_apoyo(id) ON DELETE CASCADE
);

-- Tabla para entregas de actividades
CREATE TABLE IF NOT EXISTS entregas_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_actividad INT NOT NULL,
    id_estudiante INT NOT NULL,
    comentario TEXT,
    calificacion DECIMAL(5,2) DEFAULT NULL,
    estado ENUM('pendiente', 'revisado', 'calificado') DEFAULT 'pendiente',
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_actividad) REFERENCES actividades(id) ON DELETE CASCADE
);

-- Tabla para archivos de entregas
CREATE TABLE IF NOT EXISTS archivos_entrega (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_entrega INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamano_archivo INT NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_entrega) REFERENCES entregas_actividad(id) ON DELETE CASCADE
);