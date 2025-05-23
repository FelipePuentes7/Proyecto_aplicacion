USE proyecto;

-- Tabla de usuarios
CREATE TABLE usuarios_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME,
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    tipo ENUM('estudiante', 'tutor', 'admin') NOT NULL
);

-- Tabla de estudiantes
CREATE TABLE estudiante_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_estudiante VARCHAR(20) UNIQUE,
    programa VARCHAR(100),
    semestre INT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios_seminario(id) ON DELETE CASCADE
);

-- Tabla de tutores
CREATE TABLE tutor_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    especialidad VARCHAR(100),
    biografia TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios_seminario(id) ON DELETE CASCADE
);

-- Tabla de cursos/seminarios
CREATE TABLE curso_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    codigo_curso VARCHAR(20) UNIQUE,
    fecha_inicio DATE,
    fecha_fin DATE,
    estado ENUM('activo', 'inactivo', 'finalizado') DEFAULT 'activo'
);

-- Asignación de tutores a cursos
CREATE TABLE curso_tutor_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tutor_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES curso_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutor_seminario(id) ON DELETE CASCADE,
    UNIQUE KEY (curso_id, tutor_id)
);

-- Inscripción de estudiantes a cursos
CREATE TABLE curso_estudiante_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo', 'finalizado') DEFAULT 'activo',
    FOREIGN KEY (curso_id) REFERENCES curso_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiante_seminario(id) ON DELETE CASCADE,
    UNIQUE KEY (curso_id, estudiante_id)
);

-- Clases programadas
CREATE TABLE clase_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tutor_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    duracion INT NOT NULL COMMENT 'Duración en minutos',
    plataforma ENUM('meet', 'classroom', 'zoom', 'teams', 'otro') NOT NULL,
    enlace VARCHAR(255) NOT NULL,
    estado ENUM('programada', 'en_curso', 'finalizada', 'cancelada') DEFAULT 'programada',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES curso_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutor_seminario(id) ON DELETE CASCADE
);

-- Grabaciones
CREATE TABLE grabacion_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clase_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    archivo VARCHAR(255) NOT NULL,
    duracion INT COMMENT 'Duración en segundos',
    tamaño VARCHAR(20) COMMENT 'Tamaño del archivo',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clase_id) REFERENCES clase_seminario(id) ON DELETE CASCADE
);

-- Actividades
CREATE TABLE actividad_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tutor_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo ENUM('taller', 'cuestionario', 'proyecto', 'examen') NOT NULL,
    fecha_publicacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_limite DATE NOT NULL,
    hora_limite TIME NOT NULL,
    estado ENUM('activa', 'cerrada', 'archivada') DEFAULT 'activa',
    FOREIGN KEY (curso_id) REFERENCES curso_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutor_seminario(id) ON DELETE CASCADE
);

-- Archivos de actividades
CREATE TABLE actividad_archivos_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(50),
    tamaño VARCHAR(20),
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividad_seminario(id) ON DELETE CASCADE
);

-- Entregas
CREATE TABLE entrega_actividad_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    comentario TEXT,
    fecha_entrega DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'calificado', 'devuelto') DEFAULT 'pendiente',
    calificacion DECIMAL(3,1),
    retroalimentacion TEXT,
    fecha_calificacion DATETIME,
    FOREIGN KEY (actividad_id) REFERENCES actividad_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiante_seminario(id) ON DELETE CASCADE,
    UNIQUE KEY (actividad_id, estudiante_id)
);

-- Archivos de entregas
CREATE TABLE entrega_archivos_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_archivo VARCHAR(50),
    tamaño VARCHAR(20),
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entrega_id) REFERENCES entrega_actividad_seminario(id) ON DELETE CASCADE
);

-- Categorías de material
CREATE TABLE categoria_material_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50)
);

-- Material de apoyo
CREATE TABLE material_apoyo_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tutor_id INT NOT NULL,
    categoria_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo ENUM('video', 'documento', 'enlace', 'herramienta', 'otro') NOT NULL,
    url VARCHAR(255),
    plataforma VARCHAR(50),
    archivo VARCHAR(255),
    tamaño VARCHAR(20),
    fecha_publicacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo', 'archivado') DEFAULT 'activo',
    FOREIGN KEY (curso_id) REFERENCES curso_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutor_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categoria_material_seminario(id) ON DELETE CASCADE
);

-- Asignación de material
CREATE TABLE material_estudiante_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    visto BOOLEAN DEFAULT FALSE,
    fecha_visto DATETIME,
    FOREIGN KEY (material_id) REFERENCES material_apoyo_seminario(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiante_seminario(id) ON DELETE CASCADE,
    UNIQUE KEY (material_id, estudiante_id)
);

-- Notificaciones
CREATE TABLE notificacion_seminario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios_seminario(id) ON DELETE CASCADE
);

<<<<<<< HEAD
=======
CREATE TABLE IF NOT EXISTS entregas_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    comentario TEXT,
    calificacion DECIMAL(5,2) DEFAULT NULL,
    estado ENUM('pendiente', 'revisado', 'calificado') DEFAULT 'pendiente',
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
