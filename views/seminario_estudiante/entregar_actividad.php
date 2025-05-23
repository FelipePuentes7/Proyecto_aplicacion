<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Iniciar sesión
session_start();

// Habilitar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Crear directorio de logs si no existe
$log_dir = "../../logs";
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Función para escribir en el log
function escribirLog($mensaje) {
    global $log_dir;
    $fecha = date('Y-m-d H:i:s');
    $log_file = $log_dir . "/entregas_" . date('Y-m-d') . ".log";
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

escribirLog("Página entregar_actividad.php cargada");

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    // Si no hay sesión, redirigir al login
    escribirLog("Usuario no logueado, redirigiendo al login");
    header("Location: /views/general/login.php");
    exit();
}

// Obtener ID del usuario de la sesión
$usuario_id = $_SESSION['usuario']['id'];

// Obtener información del estudiante
try {
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.apellido, u.email, u.avatar
        FROM usuarios u
        WHERE u.id = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($estudiante && isset($estudiante['avatar']) && !empty($estudiante['avatar'])) {
        // Actualizar el avatar en la sesión
        $_SESSION['usuario']['avatar'] = $estudiante['avatar'];
    }
} catch (PDOException $e) {
    // Ignorar errores
}

// Verificar si el usuario existe
try {
    $stmt = $db->prepare("
        SELECT id FROM usuarios 
        WHERE id = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        // Si el usuario no existe en la base de datos
        escribirLog("Usuario no encontrado en la base de datos: $usuario_id");
        header("Location: /views/seminario_estudiante/login.php?error=usuario_no_encontrado");
        exit();
    }
    
    // Aquí eliminamos la verificación de opcion_grado para permitir el acceso
    
} catch (PDOException $e) {
    // Manejar error
    escribirLog("Error al verificar usuario: " . $e->getMessage());
    header("Location: /views/seminario_estudiante/login.php?error=error_db");
    exit();
}

// Verificar y crear tablas necesarias
try {
    // Verificar si la tabla actividades existe
    $stmt = $db->query("SHOW TABLES LIKE 'actividades'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, crearla
        $db->exec("
            CREATE TABLE actividades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NOT NULL,
                descripcion TEXT,
                fecha_limite DATE NOT NULL,
                hora_limite TIME NOT NULL,
                tipo VARCHAR(50) DEFAULT 'tarea',
                puntaje DECIMAL(3,1) DEFAULT 5.0,
                permitir_entregas_tarde TINYINT(1) DEFAULT 1,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                tutor_id INT,
                curso_id INT
            )
        ");
        escribirLog("Tabla actividades creada");
    }
    
    // Verificar si hay actividades en la tabla
    $stmt = $db->query("SELECT COUNT(*) FROM actividades");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // No hay actividades, insertar algunas de ejemplo
        $db->exec("
            INSERT INTO actividades (id, titulo, descripcion, fecha_limite, hora_limite, tipo, tutor_id, curso_id) 
            VALUES 
            (1, 'Diseño de Base de Datos', 'Crear un diagrama ER para un sistema de gestión de biblioteca', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '23:59:00', 'tarea', 1, 1),
            (2, 'Consultas SQL Básicas', 'Realizar consultas SELECT con filtros y ordenamiento', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '23:59:00', 'tarea', 1, 1),
            (3, 'Normalización', 'Aplicar las formas normales a un esquema de base de datos', DATE_ADD(CURDATE(), INTERVAL -2 DAY), '23:59:00', 'tarea', 1, 1),
            (4, 'Introducción a SQL', 'Realizar ejercicios básicos de SQL', DATE_ADD(CURDATE(), INTERVAL -10 DAY), '23:59:00', 'tarea', 1, 1)
        ");
        escribirLog("Actividades de ejemplo insertadas");
    }
    
    // Verificar si la tabla entregas_actividad existe
    $stmt = $db->query("SHOW TABLES LIKE 'entregas_actividad'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, crearla
        $db->exec("
            CREATE TABLE entregas_actividad (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_actividad INT NOT NULL,
                id_estudiante INT NOT NULL,
                comentario TEXT,
                estado VARCHAR(20) DEFAULT 'pendiente',
                calificacion DECIMAL(3,1) NULL,
                comentario_tutor TEXT,
                fecha_entrega DATETIME NOT NULL,
                fecha_calificacion DATETIME NULL,
                INDEX (id_actividad),
                INDEX (id_estudiante)
            )
        ");
        escribirLog("Tabla entregas_actividad creada");
    } else {
        // Verificar si la tabla tiene restricciones de clave foránea
        $stmt = $db->query("
            SELECT * 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
            AND TABLE_NAME = 'entregas_actividad'
        ");
        
        // Si hay restricciones, eliminarlas
        if ($stmt->rowCount() > 0) {
            $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($constraints as $constraint) {
                $constraintName = $constraint['CONSTRAINT_NAME'];
                $db->exec("ALTER TABLE entregas_actividad DROP FOREIGN KEY $constraintName");
                escribirLog("Restricción de clave foránea $constraintName eliminada");
            }
        }
    }
    
    // Verificar si la tabla archivos_entrega existe
    $stmt = $db->query("SHOW TABLES LIKE 'archivos_entrega'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, crearla
        $db->exec("
            CREATE TABLE archivos_entrega (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_entrega INT NOT NULL,
                nombre_archivo VARCHAR(255) NOT NULL,
                ruta_archivo VARCHAR(255) NOT NULL,
                tipo_archivo VARCHAR(100),
                tamano_archivo INT,
                fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (id_entrega)
            )
        ");
        escribirLog("Tabla archivos_entrega creada");
    }
    
    // Verificar si la tabla estudiantes existe
    $stmt = $db->query("SHOW TABLES LIKE 'estudiantes'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, crearla
        $db->exec("
            CREATE TABLE estudiantes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                codigo VARCHAR(20),
                programa VARCHAR(100),
                semestre INT,
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        escribirLog("Tabla estudiantes creada");
        
        // Insertar un estudiante de ejemplo
        $db->exec("
            INSERT INTO estudiantes (id, usuario_id, codigo, programa, semestre) 
            VALUES (1, 1, 'EST001', 'Ingeniería de Sistemas', 5)
        ");
        escribirLog("Estudiante de ejemplo insertado");
    }
    
    // Verificar si la tabla usuarios existe
    $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, crearla
        $db->exec("
            CREATE TABLE usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                rol ENUM('estudiante', 'tutor', 'admin') DEFAULT 'estudiante',
                avatar VARCHAR(255) DEFAULT 'https://randomuser.me/api/portraits/men/32.jpg',
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        escribirLog("Tabla usuarios creada");
        
        // Insertar usuarios de ejemplo
        $db->exec("
            INSERT INTO usuarios (id, nombre, apellido, email, password, rol) 
            VALUES 
            (1, 'Carlos', 'Rodríguez', 'carlos.rodriguez@example.com', '".password_hash('password123', PASSWORD_DEFAULT)."', 'estudiante'),
            (2, 'Juan', 'Pérez', 'juan.perez@example.com', '".password_hash('password123', PASSWORD_DEFAULT)."', 'tutor')
        ");
        escribirLog("Usuarios de ejemplo insertados");
    }
    
} catch (PDOException $e) {
    escribirLog("Error al verificar/crear tablas: " . $e->getMessage());
}

// ID del estudiante (hardcodeado para pruebas)
//$estudiante_id = 1;

// Verificar si se ha enviado el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    escribirLog("Formulario recibido: " . print_r($_POST, true));
    escribirLog("Archivos recibidos: " . print_r($_FILES, true));
    
    try {
        // Obtener datos del formulario
        $actividad_id = isset($_POST['actividad_id']) ? intval($_POST['actividad_id']) : 0;
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        
        escribirLog("Procesando entrega para actividad ID: $actividad_id, estudiante ID: $usuario_id");
        
        // Validar que la actividad exista
        $stmt = $db->prepare("SELECT id, titulo FROM actividades WHERE id = :id");
        $stmt->bindParam(':id', $actividad_id);
        $stmt->execute();
        $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$actividad) {
            throw new Exception("La actividad con ID $actividad_id no existe en la base de datos.");
        }
        
        escribirLog("Actividad encontrada: " . print_r($actividad, true));
        
        // Verificar si ya existe una entrega para esta actividad
        $stmt = $db->prepare("
            SELECT id, fecha_entrega, comentario
            FROM entregas_actividad
            WHERE id_actividad = :actividad_id AND id_estudiante = :usuario_id
        ");
        $stmt->bindParam(':actividad_id', $actividad_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $entrega_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($entrega_existente) {
            // Si ya existe una entrega, mostrar mensaje
            $mensaje = "Ya has realizado una entrega para esta actividad.";
            $tipo_mensaje = "warning";
            escribirLog("Entrega existente encontrada: " . print_r($entrega_existente, true));
        } else {
            // Iniciar transacción para asegurar la integridad de los datos
            $db->beginTransaction();
            escribirLog("Iniciando transacción para nueva entrega");
            
            try {
                // Insertar la entrega en la tabla entregas_actividad
                $sql = "
                    INSERT INTO entregas_actividad (id_actividad, id_estudiante, comentario, estado, fecha_entrega)
                    VALUES (:actividad_id, :usuario_id, :comentario, 'pendiente', NOW())
                ";
                escribirLog("SQL a ejecutar: $sql");
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':actividad_id', $actividad_id);
                $stmt->bindParam(':usuario_id', $usuario_id);
                $stmt->bindParam(':comentario', $comentario);
                
                $result = $stmt->execute();
                escribirLog("Resultado de la inserción: " . ($result ? "Éxito" : "Fallo"));
                
                if (!$result) {
                    escribirLog("Error PDO: " . print_r($stmt->errorInfo(), true));
                    throw new Exception("Error al insertar la entrega en la base de datos: " . implode(" - ", $stmt->errorInfo()));
                }
                
                // Obtener el ID de la entrega recién creada
                $entrega_id = $db->lastInsertId();
                escribirLog("ID de entrega creada: $entrega_id");
                
                // Procesar el archivo si se ha subido uno
                if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                    // Crear directorio para los archivos si no existe
                    $directorio = "../../uploads/entregas/{$entrega_id}";
                    if (!file_exists($directorio)) {
                        mkdir($directorio, 0777, true);
                    }
                    
                    $nombre_temporal = $_FILES['archivo']['tmp_name'];
                    $nombre_archivo = $_FILES['archivo']['name'];
                    $tipo_archivo = $_FILES['archivo']['type'];
                    $tamano_archivo = $_FILES['archivo']['size'];
                    
                    // Generar nombre único para evitar colisiones
                    $nombre_unico = uniqid() . '_' . $nombre_archivo;
                    $ruta_destino = $directorio . '/' . $nombre_unico;
                    
                    escribirLog("Intentando mover archivo de $nombre_temporal a $ruta_destino");
                    
                    // Mover el archivo
                    if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                        escribirLog("Archivo movido exitosamente");
                        
                        // Guardar información del archivo en la base de datos
                        $stmt = $db->prepare("
                            INSERT INTO archivos_entrega (id_entrega, nombre_archivo, ruta_archivo, tipo_archivo, tamano_archivo) 
                            VALUES (:id_entrega, :nombre_archivo, :ruta_archivo, :tipo_archivo, :tamano_archivo)
                        ");
                        $stmt->bindParam(':id_entrega', $entrega_id);
                        $stmt->bindParam(':nombre_archivo', $nombre_archivo);
                        $stmt->bindParam(':ruta_archivo', $ruta_destino);
                        $stmt->bindParam(':tipo_archivo', $tipo_archivo);
                        $stmt->bindParam(':tamano_archivo', $tamano_archivo);
                        $stmt->execute();
                        
                        escribirLog("Información del archivo guardada en la base de datos");
                    } else {
                        escribirLog("Error al mover el archivo");
                        throw new Exception("Error al subir el archivo: " . $nombre_archivo);
                    }
                } else {
                    escribirLog("No se subió ningún archivo o hubo un error: " . (isset($_FILES['archivo']) ? $_FILES['archivo']['error'] : "No hay archivo"));
                }
                
                // Confirmar transacción
                $db->commit();
                escribirLog("Transacción confirmada exitosamente");
                
                // Mensaje de éxito
                $mensaje = "¡Actividad entregada exitosamente! El tutor revisará tu entrega pronto.";
                $tipo_mensaje = "success";
                
                // Redirigir a la página de actividades después de 3 segundos
                header("Refresh: 3; URL=actividades.php?filtro=entregadas");
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $db->rollBack();
                escribirLog("Error en la transacción: " . $e->getMessage());
                $mensaje = "Error al entregar la actividad: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } catch (Exception $e) {
        escribirLog("Error general: " . $e->getMessage());
        $mensaje = "Error al entregar la actividad: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener la actividad solicitada
$actividad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // Verificar que la actividad exista
    $stmt = $db->prepare("SELECT COUNT(*) FROM actividades WHERE id = :id");
    $stmt->bindParam(':id', $actividad_id);
    $stmt->execute();
    $actividad_existe = $stmt->fetchColumn() > 0;
    
    if (!$actividad_existe) {
        escribirLog("Actividad no encontrada: $actividad_id");
        $mensaje = "La actividad solicitada no existe.";
        $tipo_mensaje = "danger";
    } else {
        $stmt = $db->prepare("
            SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
                   a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion,
                   u.nombre as tutor_nombre
            FROM actividades a
            LEFT JOIN usuarios u ON a.tutor_id = u.id
            WHERE a.id = :id
        ");
        $stmt->bindParam(':id', $actividad_id);
        $stmt->execute();
        $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si ya existe una entrega para esta actividad
        $stmt = $db->prepare("
            SELECT id, fecha_entrega, comentario, estado, calificacion
            FROM entregas_actividad
            WHERE id_actividad = :actividad_id AND id_estudiante = :usuario_id
        ");
        $stmt->bindParam(':actividad_id', $actividad_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $entrega_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener archivos adjuntos de la actividad
        $stmt = $db->prepare("
            SELECT id, nombre_archivo, tipo_archivo, tamano_archivo
            FROM archivos_actividad
            WHERE id_actividad = :id_actividad
        ");
        $stmt->bindParam(':id_actividad', $actividad_id);
        $stmt->execute();
        $archivos_actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    escribirLog("Error al cargar la actividad: " . $e->getMessage());
    $mensaje = "Error al cargar la actividad: " . $e->getMessage();
    $tipo_mensaje = "danger";
    
    // Valores predeterminados para evitar errores
    $actividad = [
        'id' => $actividad_id,
        'titulo' => 'Actividad no disponible',
        'descripcion' => 'No se pudo cargar la información de esta actividad.',
        'fecha_limite' => date('Y-m-d'),
        'hora_limite' => '23:59:00',
        'tipo' => 'tarea',
        'puntaje' => 5.0,
        'permitir_entregas_tarde' => 1,
        'fecha_creacion' => date('Y-m-d H:i:s'),
        'tutor_nombre' => 'Tutor'
    ];
    $entrega_existente = false;
    $archivos_actividad = [];
}

// Función para formatear tamaño de archivo
function formatearTamano($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $unidades[$i];
}

// Función para obtener icono según tipo de archivo
function obtenerIconoArchivo($tipo) {
    if (strpos($tipo, 'pdf') !== false) {
        return 'fa-file-pdf';
    } elseif (strpos($tipo, 'word') !== false || strpos($tipo, 'document') !== false) {
        return 'fa-file-word';
    } elseif (strpos($tipo, 'excel') !== false || strpos($tipo, 'sheet') !== false) {
        return 'fa-file-excel';
    } elseif (strpos($tipo, 'powerpoint') !== false || strpos($tipo, 'presentation') !== false) {
        return 'fa-file-powerpoint';
    } elseif (strpos($tipo, 'image') !== false) {
        return 'fa-file-image';
    } elseif (strpos($tipo, 'zip') !== false || strpos($tipo, 'rar') !== false) {
        return 'fa-file-archive';
    } elseif (strpos($tipo, 'text') !== false) {
        return 'fa-file-alt';
    } else {
        return 'fa-file';
    }
}

// Función para calcular días restantes
function diasRestantes($fecha_limite) {
    $hoy = time();
    $limite = strtotime($fecha_limite);
    $diferencia = $limite - $hoy;
    return max(0, floor($diferencia / (60 * 60 * 24)));
}

// Función para obtener icono según tipo de actividad
function obtenerIconoActividad($tipo) {
    switch (strtolower($tipo)) {
        case 'tarea':
            return 'fa-clipboard-list';
        case 'proyecto':
            return 'fa-project-diagram';
        case 'examen':
            return 'fa-file-alt';
        case 'cuestionario':
            return 'fa-question-circle';
        case 'investigacion':
            return 'fa-search';
        default:
            return 'fa-tasks';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar Actividad - FET</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #039708;
            --primary-light: #039708;
            --primary-dark: #039708;
            --secondary: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            height: 40px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-icon {
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }
        
        .main-content {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--dark);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .activity-info {
            padding: 20px;
            background-color: rgba(0, 166, 61, 0.05);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .activity-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .meta-item i {
            margin-right: 5px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .activity-description {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .activity-files {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .file-item:hover {
            background-color: #e9ecef;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
        }
        
        .file-info {
            flex-grow: 1;
        }
        
        .file-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .file-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .file-actions a {
            color: #6c757d;
            transition: color 0.3s;
        }
        
        .file-actions a:hover {
            color: var(--primary);
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .custom-file-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 30px 20px;
            margin-top: 50px;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .footer-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .footer-info p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .footer-image {
            max-width: 150px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-links a {
            color: white;
            font-size: 1.2rem;
            transition: opacity 0.3s;
        }
        
        .social-links a:hover {
            opacity: 0.8;
        }
        
        .days-left {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .days-left.urgent {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .file-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .entrega-existente {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        
        .entrega-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .entrega-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .entrega-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pendiente {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-calificado {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .entrega-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .entrega-comment {
            background-color: white;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .calificacion-container {
            background-color: white;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        
        .calificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calificacion-title {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .calificacion-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .feedback-tutor {
            background-color: rgba(40, 167, 69, 0.05);
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .activity-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .social-links {
                justify-content: center;
            }
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        /* Estilos para el debug panel */
        .debug-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .debug-panel h4 {
            color: #dc3545;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .debug-panel pre {
            background-color: #343a40;
            color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <header class="header">
    <img src="../../assets/images/logofet.png" alt="FET Logo" class="logo">
    
    <nav class="nav-links">
        <a href="inicio_estudiantes.php">Inicio</a>
        <a href="actividades.php" class="active">Actividades</a>
        <a href="aula_virtual.php">Aula Virtual</a>
        <a href="material_apoyo.php">Material de Apoyo</a>
    </nav>
    
    <div class="user-profile" style="position: relative;">
    <!-- Notificación -->
<div id="notification-bell" style="position: relative; cursor: pointer;">
    <i class="fas fa-bell notification-icon"></i>
    <?php
    // Notificaciones: actividades pendientes
    try {
        $stmt = $db->prepare("
            SELECT a.titulo, a.fecha_limite
            FROM actividades a
            LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
            WHERE e.id IS NULL
            ORDER BY a.fecha_limite ASC
            LIMIT 10
        ");
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $actividades_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear array de notificaciones
        $notificaciones = [];
        foreach ($actividades_pendientes as $act) {
            $notificaciones[] = [
                'mensaje' => 'Nueva actividad: ' . htmlspecialchars($act['titulo']),
                'fecha' => date('d/m/Y', strtotime($act['fecha_limite']))
            ];
        }
        $num_notificaciones = count($notificaciones);
    } catch (PDOException $e) {
        $notificaciones = [];
        $num_notificaciones = 0;
    }
    ?>
    <?php if ($num_notificaciones > 0): ?>
        <span id="notification-badge" style="
            position: absolute;
            top: -6px; right: -6px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            font-size: 0.75rem;
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold;
            border: 2px solid #fff;
            z-index: 2;
        "><?php echo $num_notificaciones; ?></span>
    <?php endif; ?>
    <!-- Panel de notificaciones -->
    <div id="notification-panel" style="
        display: none;
        position: absolute;
        right: 0; top: 35px;
        background: #fff;
        color: #343a40;
        min-width: 280px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        border-radius: 8px;
        z-index: 10;
        overflow: hidden;
    ">
        <div style="padding: 12px 16px; border-bottom: 1px solid #eee; font-weight: bold;">
            Notificaciones
        </div>
        <?php if ($num_notificaciones > 0): ?>
            <ul style="list-style: none; margin: 0; padding: 0; max-height: 250px; overflow-y: auto;">
                <?php foreach ($notificaciones as $n): ?>
                    <li style="padding: 12px 16px; border-bottom: 1px solid #f2f2f2;">
                        <div style="font-size: 0.97em;"><?php echo $n['mensaje']; ?></div>
                        <div style="font-size: 0.8em; color: #888;"><?php echo $n['fecha']; ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div style="padding: 16px; color: #888;">No tienes notificaciones nuevas.</div>
        <?php endif; ?>
    </div>
</div>
        <!-- Avatar y menú usuario -->
        <div id="avatar-container" style="position: relative; margin-left: 10px; cursor: pointer;">
            <?php if (!empty($_SESSION['usuario']['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['usuario']['avatar']); ?>" alt="Avatar" class="avatar">
            <?php else: ?>
                <span class="avatar" style="
                    display: flex; align-items: center; justify-content: center;
                    background: #e9ecef; color: #adb5bd; font-size: 1.3rem;
                    width: 35px; height: 35px; border-radius: 50%;
                ">
                    <i class="fas fa-user"></i>
                </span>
            <?php endif; ?>
            <!-- Menú usuario -->
            <div id="user-menu" style="
                display: none;
                position: absolute;
                right: 0; top: 40px;
                background: #fff;
                color: #343a40;
                min-width: 180px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.15);
                border-radius: 8px;
                z-index: 10;
                overflow: hidden;
            ">
                <div style="padding: 12px 16px; border-bottom: 1px solid #eee;">
                    <strong><?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?></strong>
                </div>
                <a href="subir_avatar.php" style="display: block; padding: 12px 16px; color: #343a40; text-decoration: none; border-bottom: 1px solid #eee;">
                    <i class="fas fa-upload"></i> Cambiar avatar
                </a>
                <form action="../../views/general/login.php" method="post" style="margin: 0;">
                    <button type="submit" style="
                        width: 100%; background: #dc3545; color: #fff;
                        border: none; padding: 12px 16px; text-align: left;
                        font-weight: bold; cursor: pointer;
                    ">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Notificaciones
    const bell = document.getElementById('notification-bell');
    const panel = document.getElementById('notification-panel');
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
        // Oculta el menú usuario si está abierto
        document.getElementById('user-menu').style.display = 'none';
    });
    // Avatar menú
    const avatar = document.getElementById('avatar-container');
    const userMenu = document.getElementById('user-menu');
    avatar.addEventListener('click', function(e) {
        e.stopPropagation();
        userMenu.style.display = (userMenu.style.display === 'block') ? 'none' : 'block';
        // Oculta el panel de notificaciones si está abierto
        panel.style.display = 'none';
    });
    // Cerrar ambos al hacer click fuera
    document.addEventListener('click', function() {
        panel.style.display = 'none';
        userMenu.style.display = 'none';
    });
});
</script>
    
    <main class="main-content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio_estudiantes.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="actividades.php">Actividades</a></li>
                <li class="breadcrumb-item active" aria-current="page">Entregar Actividad</li>
            </ol>
        </nav>
        
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Panel de depuración (solo visible en desarrollo) -->
        <?php if (false): // Cambiar a false en producción ?>
            <div class="debug-panel">
                <h4><i class="fas fa-bug mr-2"></i>Panel de Depuración</h4>
                <p>Este panel solo es visible en desarrollo y debe ser eliminado en producción.</p>
                
                <h5>Información de la Actividad:</h5>
                <pre><?php print_r($actividad); ?></pre>
                
                <h5>Entrega Existente:</h5>
                <pre><?php print_r($entrega_existente); ?></pre>
                
                <h5>Tablas de la Base de Datos:</h5>
                <pre><?php 
                    try {
                        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        print_r($tables);
                    } catch (Exception $e) {
                        echo "Error al obtener tablas: " . $e->getMessage();
                    }
                ?></pre>
                
                <h5>Estructura de actividades:</h5>
                <pre><?php 
                    try {
                        $columns = $db->query("DESCRIBE actividades")->fetchAll(PDO::FETCH_ASSOC);
                        print_r($columns);
                    } catch (Exception $e) {
                        echo "Error al obtener estructura: " . $e->getMessage();
                    }
                ?></pre>
                
                <h5>Estructura de entregas_actividad:</h5>
                <pre><?php 
                    try {
                        $columns = $db->query("DESCRIBE entregas_actividad")->fetchAll(PDO::FETCH_ASSOC);
                        print_r($columns);
                    } catch (Exception $e) {
                        echo "Error al obtener estructura: " . $e->getMessage();
                    }
                ?></pre>
                
                <h5>Actividades disponibles:</h5>
                <pre><?php 
                    try {
                        $actividades = $db->query("SELECT * FROM actividades")->fetchAll(PDO::FETCH_ASSOC);
                        print_r($actividades);
                    } catch (Exception $e) {
                        echo "Error al obtener actividades: " . $e->getMessage();
                    }
                ?></pre>
                
                <h5>Últimas 5 entregas:</h5>
                <pre><?php 
                    try {
                        $entregas = $db->query("SELECT * FROM entregas_actividad ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                        print_r($entregas);
                    } catch (Exception $e) {
                        echo "Error al obtener entregas: " . $e->getMessage();
                    }
                ?></pre>
            </div>
        <?php endif; ?>
        
        <?php if (isset($actividad) && is_array($actividad)): ?>
            <h1 class="page-title">
                <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                Entregar Actividad
            </h1>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i> Información de la Actividad
                        </div>
                        <div class="card-body">
                            <div class="activity-info">
                                <h2 class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></h2>
                                
                                <div class="activity-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user-tie"></i>
                                        <span>Tutor: <?php echo htmlspecialchars($actividad['tutor_nombre'] ?? 'Tutor'); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?></span>
                                        <?php 
                                            $dias = diasRestantes($actividad['fecha_limite']);
                                            $urgente = $dias <= 2;
                                        ?>
                                        <span class="days-left <?php echo $urgente ? 'urgent' : ''; ?>">
                                            <?php echo $dias; ?> día(s)
                                        </span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Hora límite: <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span>Tipo: <?php echo ucfirst($actividad['tipo']); ?></span>
                                    </div>
                                    
                                    <?php if ($actividad['puntaje']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-star"></i>
                                        <span>Puntaje: <?php echo $actividad['puntaje']; ?> puntos</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="activity-description">
                                    <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                                </div>
                                
                                <?php if (isset($archivos_actividad) && count($archivos_actividad) > 0): ?>
                                <div class="activity-files">
                                    <h5><i class="fas fa-paperclip"></i> Archivos adjuntos</h5>
                                    <div class="file-list">
                                        <?php foreach ($archivos_actividad as $archivo): ?>
                                            <div class="file-item">
                                                <div class="file-icon">
                                                    <i class="fas <?php echo obtenerIconoArchivo($archivo['tipo_archivo']); ?> fa-lg"></i>
                                                </div>
                                                <div class="file-info">
                                                    <div class="file-name"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></div>
                                                    <div class="file-meta"><?php echo formatearTamano($archivo['tamano_archivo']); ?></div>
                                                </div>
                                                <div class="file-actions">
                                                    <a href="descargar_archivo.php?id=<?php echo $archivo['id']; ?>" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($entrega_existente): ?>
                                <!-- Mostrar información de la entrega existente -->
                                <div class="entrega-existente">
                                    <div class="entrega-header">
                                        <h3 class="entrega-title">Tu entrega</h3>
                                        <?php if ($entrega_existente['estado'] === 'pendiente'): ?>
                                            <span class="entrega-status status-pendiente">Pendiente de revisión</span>
                                        <?php else: ?>
                                            <span class="entrega-status status-calificado">Calificado</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="entrega-date">
                                        <i class="far fa-calendar-alt mr-1"></i> Entregado el: <?php echo date('d/m/Y H:i', strtotime($entrega_existente['fecha_entrega'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($entrega_existente['comentario'])): ?>
                                        <div class="entrega-comment">
                                            <h5><i class="far fa-comment-alt mr-1"></i> Tu comentario:</h5>
                                            <p><?php echo nl2br(htmlspecialchars($entrega_existente['comentario'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entrega_existente['estado'] === 'calificado' && isset($entrega_existente['calificacion'])): ?>
                                        <div class="calificacion-container">
                                            <div class="calificacion-header">
                                                <h5 class="calificacion-title"><i class="fas fa-award mr-1"></i> Calificación:</h5>
                                                <span class="calificacion-value"><?php echo $entrega_existente['calificacion']; ?>/5.0</span>
                                            </div>
                                            
                                            <?php if (isset($entrega_existente['comentario_tutor']) && !empty($entrega_existente['comentario_tutor'])): ?>
                                                <div class="feedback-tutor">
                                                    <h6><i class="fas fa-comment-dots mr-1"></i> Retroalimentación del tutor:</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($entrega_existente['comentario_tutor'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Formulario para nueva entrega -->
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="actividad_id" value="<?php echo $actividad['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="comentario">Comentarios (opcional)</label>
                                        <textarea class="form-control" id="comentario" name="comentario" rows="4" placeholder="Escribe aquí tus comentarios o dudas sobre la actividad..."></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Archivo adjunto</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="archivo" name="archivo">
                                            <label class="custom-file-label" for="archivo">Seleccionar archivo</label>
                                        </div>
                                        <small class="form-text text-muted">Puedes adjuntar un archivo. Tamaño máximo: 10MB.</small>
                                    </div>
                                    
                                    <div class="form-group mb-0 text-right">
                                        <a href="actividades.php" class="btn btn-secondary mr-2">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane mr-1"></i> Enviar Entrega
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-lightbulb"></i> Consejos para la entrega
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    Asegúrate de leer detenidamente las instrucciones de la actividad.
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    Verifica que los archivos que adjuntes estén en los formatos solicitados.
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    Nombra tus archivos de manera descriptiva para facilitar su identificación.
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    Realiza la entrega con tiempo suficiente antes de la fecha límite.
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success mr-2"></i>
                                    Si tienes dudas, puedes incluirlas en el campo de comentarios.
                                </li>
                            </ul>
                        </div>
                    </div>
                    
            
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error al cargar la actividad</h4>
                <p>No se pudo encontrar la actividad solicitada. Por favor, verifica que la actividad exista e intenta nuevamente.</p>
                <hr>
                <p class="mb-0">Si el problema persiste, contacta al administrador del sistema.</p>
            </div>
            
            <div class="text-center mt-4">
                <a href="actividades.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left mr-2"></i> Volver a Actividades
                </a>
            </div>
        <?php endif; ?>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-info">
               <p>Email: direccion_software@fet.edu.co</p>
                <p>Dirección: Kilómetro 12, via Neiva – Rivera</p>
                <p>Teléfono: 6088674935 – (+57) 3223041567</p>
                
                <div class="social-links">
                    <a href="https://www.facebook.com/YoSoyFet" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com/yosoyfet" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/fetneiva" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/channel/UCv647ftA-d--0F02AqF7eng" target="_blank"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <img src="../../assets/images/logofet.png" alt="FET Logo" class="footer-image">
        </div>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Actualizar el nombre del archivo seleccionado en el input file
        document.querySelector('.custom-file-input').addEventListener('change', function(e) {
            var files = [];
            for (var i = 0; i < this.files.length; i++) {
                files.push(this.files[i].name);
            }
            document.querySelector('.custom-file-label').textContent = files.length > 0 ? files.join(', ') : 'Seleccionar archivo';
        });
    </script>
</body>
</html>
