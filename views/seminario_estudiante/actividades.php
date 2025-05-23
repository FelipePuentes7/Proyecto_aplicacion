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
    $log_file = $log_dir . "/actividades_" . date('Y-m-d') . ".log";
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

escribirLog("Página actividades.php cargada");

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    // Si no hay sesión, redirigir al login
    escribirLog("Usuario no logueado, redirigiendo al login");
    header("Location: /views/general/login.php");
    exit();
}

// Obtener ID del usuario de la sesión
$usuario_id = $_SESSION['usuario']['id'];

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
    
    if ($estudiante) {
        // Actualizar el avatar en la sesión si existe en la base de datos
        if (isset($estudiante['avatar']) && !empty($estudiante['avatar'])) {
            $_SESSION['usuario']['avatar'] = $estudiante['avatar'];
        }
    } else {
        // Si no se encuentra el estudiante, usar datos de ejemplo
        $estudiante = [
            'id' => 1,
            'nombre' => 'Carlos',
            'apellido' => 'Rodríguez',
            'email' => 'carlos.rodriguez@example.com',
            'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg'
        ];
        // También actualizar la sesión con el avatar de ejemplo
        $_SESSION['usuario']['avatar'] = $estudiante['avatar'];
    }
} catch (PDOException $e) {
    escribirLog("Error al obtener información del estudiante: " . $e->getMessage());
    // Si hay un error, usar datos de ejemplo
    $estudiante = [
        'id' => 1,
        'nombre' => 'Carlos',
        'apellido' => 'Rodríguez',
        'email' => 'carlos.rodriguez@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg'
    ];
    // También actualizar la sesión con el avatar de ejemplo
    $_SESSION['usuario']['avatar'] = $estudiante['avatar'];
}

// Añadir este código para depuración (puedes eliminarlo después)
// Justo después del código anterior:
// Depuración - Verificar si el avatar está en la sesión
escribirLog("Avatar en sesión: " . (isset($_SESSION['usuario']['avatar']) ? $_SESSION['usuario']['avatar'] : 'No disponible'));

// Obtener el filtro actual (por defecto: pendientes)
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'pendientes';

// Obtener actividades según el filtro
try {
    escribirLog("Obteniendo actividades con filtro: $filtro");
    
    switch ($filtro) {
        case 'pendientes':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite
                FROM actividades a
                LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                WHERE e.id IS NULL
                ORDER BY a.fecha_limite ASC, a.hora_limite ASC
            ");
            break;
            
        case 'entregadas':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, 
                       e.fecha_entrega, e.comentario, e.estado, ae.nombre_archivo as archivo
                FROM actividades a
                JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                LEFT JOIN archivos_entrega ae ON e.id = ae.id_entrega
                WHERE e.calificacion IS NULL
                ORDER BY e.fecha_entrega DESC
            ");
            break;
            
        case 'calificadas':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, 
                       e.fecha_entrega, e.comentario, e.calificacion, e.comentario_tutor, ae.nombre_archivo as archivo
                FROM actividades a
                JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                LEFT JOIN archivos_entrega ae ON e.id = ae.id_entrega
                WHERE e.calificacion IS NOT NULL
                ORDER BY e.fecha_entrega DESC
            ");
            break;
            
        case 'todas':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite,
                       e.id as entrega_id, e.fecha_entrega, e.comentario, e.estado,
                       e.calificacion, e.comentario_tutor, ae.nombre_archivo as archivo
                FROM actividades a
                LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                LEFT JOIN archivos_entrega ae ON e.id = ae.id_entrega
                ORDER BY a.fecha_limite ASC, a.hora_limite ASC
            ");
            break;
            
        default:
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite
                FROM actividades a
                LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                WHERE e.id IS NULL
                ORDER BY a.fecha_limite ASC, a.hora_limite ASC
            ");
    }
    
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    escribirLog("Actividades encontradas: " . count($actividades));
    
} catch (PDOException $e) {
    escribirLog("Error al obtener actividades: " . $e->getMessage());
    // Si hay un error, usar datos de ejemplo
    switch ($filtro) {
        case 'pendientes':
            $actividades = [
                [
                    'id' => 1,
                    'titulo' => 'Diseño de Base de Datos',
                    'descripcion' => 'Crear un diagrama ER para un sistema de gestión de biblioteca',
                    'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
                    'hora_limite' => '23:59:00'
                ],
                [
                    'id' => 2,
                    'titulo' => 'Consultas SQL Básicas',
                    'descripcion' => 'Realizar consultas SELECT con filtros y ordenamiento',
                    'fecha_limite' => date('Y-m-d', strtotime('+5 days')),
                    'hora_limite' => '23:59:00'
                ]
            ];
            break;
            
        case 'entregadas':
            $actividades = [
                [
                    'id' => 3,
                    'titulo' => 'Normalización',
                    'descripcion' => 'Aplicar las formas normales a un esquema de base de datos',
                    'fecha_limite' => date('Y-m-d', strtotime('-2 days')),
                    'hora_limite' => '23:59:00',
                    'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    'archivo' => 'normalizacion_carlos.pdf',
                    'comentario' => 'Adjunto mi trabajo de normalización',
                    'estado' => 'pendiente'
                ]
            ];
            break;
            
        case 'calificadas':
            $actividades = [
                [
                    'id' => 4,
                    'titulo' => 'Introducción a SQL',
                    'descripcion' => 'Realizar ejercicios básicos de SQL',
                    'fecha_limite' => date('Y-m-d', strtotime('-10 days')),
                    'hora_limite' => '23:59:00',
                    'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-11 days')),
                    'archivo' => 'ejercicios_sql_carlos.pdf',
                    'comentario' => 'Completé todos los ejercicios',
                    'calificacion' => 4.5,
                    'comentario_tutor' => 'Buen trabajo, solo faltó optimizar algunas consultas'
                ]
            ];
            break;
            
        case 'todas':
            $actividades = [
                [
                    'id' => 1,
                    'titulo' => 'Diseño de Base de Datos',
                    'descripcion' => 'Crear un diagrama ER para un sistema de gestión de biblioteca',
                    'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
                    'hora_limite' => '23:59:00',
                    'entrega_id' => null
                ],
                [
                    'id' => 3,
                    'titulo' => 'Normalización',
                    'descripcion' => 'Aplicar las formas normales a un esquema de base de datos',
                    'fecha_limite' => date('Y-m-d', strtotime('-2 days')),
                    'hora_limite' => '23:59:00',
                    'entrega_id' => 2,
                    'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    'archivo' => 'normalizacion_carlos.pdf',
                    'comentario' => 'Adjunto mi trabajo de normalización',
                    'estado' => 'pendiente'
                ],
                [
                    'id' => 4,
                    'titulo' => 'Introducción a SQL',
                    'descripcion' => 'Realizar ejercicios básicos de SQL',
                    'fecha_limite' => date('Y-m-d', strtotime('-10 days')),
                    'hora_limite' => '23:59:00',
                    'entrega_id' => 1,
                    'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-11 days')),
                    'archivo' => 'ejercicios_sql_carlos.pdf',
                    'comentario' => 'Completé todos los ejercicios',
                    'calificacion' => 4.5,
                    'comentario_tutor' => 'Buen trabajo, solo faltó optimizar algunas consultas'
                ]
            ];
            break;
            
        default:
            $actividades = [
                [
                    'id' => 1,
                    'titulo' => 'Diseño de Base de Datos',
                    'descripcion' => 'Crear un diagrama ER para un sistema de gestión de biblioteca',
                    'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
                    'hora_limite' => '23:59:00'
                ],
                [
                    'id' => 2,
                    'titulo' => 'Consultas SQL Básicas',
                    'descripcion' => 'Realizar consultas SELECT con filtros y ordenamiento',
                    'fecha_limite' => date('Y-m-d', strtotime('+5 days')),
                    'hora_limite' => '23:59:00'
                ]
            ];
    }
}

// Procesar envío de actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actividad_id'])) {
    $actividad_id = $_POST['actividad_id'];
    $comentario = isset($_POST['comentario']) ? $_POST['comentario'] : '';
    
    // En un sistema real, aquí se procesaría la subida del archivo
    $archivo = isset($_FILES['archivo']) ? $_FILES['archivo']['name'] : 'archivo_ejemplo.pdf';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO entregas_actividad (id_actividad, id_estudiante, fecha_entrega, comentario, estado)
            VALUES (:actividad_id, :usuario_id, NOW(), :comentario, 'pendiente')
        ");
        
        $stmt->bindParam(':actividad_id', $actividad_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':comentario', $comentario);
        
        $stmt->execute();
        
        // Redirigir para evitar reenvío del formulario
        header("Location: actividades.php?filtro=entregadas&mensaje=Actividad entregada exitosamente");
        exit();
        
    } catch (PDOException $e) {
        escribirLog("Error al entregar actividad: " . $e->getMessage());
        $error_mensaje = "Error al entregar la actividad: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Actividades</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00a63d;
            --primary-light: #00c44b;
            --primary-dark: #008f34;
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
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            min-height: 100vh;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-title {
            color: white;
            margin-bottom: 20px;
            font-weight: 700;
            text-align: center;
        }
        
        .filter-tabs {
            display: flex;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .filter-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: white;
        }
        
        .filter-tab.active {
            background-color: white;
            color: var(--primary);
        }
        
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .activity-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .activity-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-icon {
            background-color: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .activity-title {
            flex-grow: 1;
            margin: 0;
            font-weight: 500;
            color: var(--dark);
        }
        
        .activity-status {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-submitted {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-graded {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .activity-body {
            padding: 20px;
        }
        
        .activity-info {
            margin-bottom: 20px;
        }
        
        .activity-info-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            width: 120px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .info-value {
            flex-grow: 1;
            color: #6c757d;
        }
        
        .activity-description {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .activity-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .submission-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 61, 0.25);
            outline: none;
        }
        
        .file-upload {
            border: 2px dashed #ced4da;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
        }
        
        .file-upload i {
            font-size: 3rem;
            color: #ced4da;
            margin-bottom: 15px;
        }
        
        .file-upload p {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-select-btn {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-select-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .file-preview {
            display: none;
            align-items: center;
            margin-top: 15px;
        }
        
        .file-preview i {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 15px;
        }
        
        .file-info {
            flex-grow: 1;
            text-align: left;
        }
        
        .file-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .file-size {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .remove-file {
            background-color: #f8f9fa;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .remove-file:hover {
            background-color: var(--danger);
            color: white;
        }
        
        .grade-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .grade-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .grade-title {
            font-weight: 500;
            color: var(--dark);
            margin: 0;
        }
        
        .grade-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .feedback {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ced4da;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .filter-tabs {
                flex-wrap: wrap;
            }
            
            .filter-tab {
                flex: 1 0 50%;
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
                // Notificaciones: nuevas actividades pendientes
                $notificaciones = [];
                foreach ($actividades as $act) {
                    if ($filtro === 'pendientes' || ($filtro === 'todas' && !isset($act['entrega_id']))) {
                        $notificaciones[] = [
                            'mensaje' => 'Nueva actividad: ' . $act['titulo'],
                            'fecha' => date('d/m/Y', strtotime($act['fecha_limite']))
                        ];
                    }
                }
                $num_notificaciones = count($notificaciones);
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
                                    <div style="font-size: 0.97em;"><?php echo htmlspecialchars($n['mensaje']); ?></div>
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
    <?php 
    // Forzar actualización del avatar desde la base de datos
    try {
        $stmt = $db->prepare("SELECT avatar FROM usuarios WHERE id = :usuario_id");
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $avatar_db = $stmt->fetchColumn();
        
        // Actualizar la sesión si el avatar en la base de datos es diferente
        if ($avatar_db && (!isset($_SESSION['usuario']['avatar']) || $_SESSION['usuario']['avatar'] !== $avatar_db)) {
            $_SESSION['usuario']['avatar'] = $avatar_db;
        }
    } catch (PDOException $e) {
        // Silenciar errores
    }
    
    // Añadir timestamp para evitar caché
    $avatar_url = !empty($_SESSION['usuario']['avatar']) ? 
        htmlspecialchars($_SESSION['usuario']['avatar']) . '?t=' . time() : '';
    ?>
    
    <?php if (!empty($avatar_url)): ?>
        <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="avatar">
    <?php else: ?>
        <span class="avatar" style="
            display: flex; align-items: center; justify-content: center;
            background: #e9ecef; color: #adb5bd; font-size: 1.3rem;
            width: 35px; height: 35px; border-radius: 50%;
        ">
            <i class="fas fa-user"></i>
        </span>
    <?php endif; ?>
</div>

    
    <!-- Añadir este código para depuración (puedes eliminarlo después) -->
    <!-- <span style="display: none;"><?php echo "Avatar: " . (isset($_SESSION['usuario']['avatar']) ? $_SESSION['usuario']['avatar'] : 'No disponible'); ?></span> -->
    
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
    </header>
    
    <main class="main-content">
        <h1 class="page-title">Gestión de Actividades</h1>
        
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['mensaje']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_mensaje)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_mensaje); ?>
            </div>
        <?php endif; ?>
        
        <!-- Panel de depuración (solo visible en desarrollo) -->
        <?php if (false): // Cambiar a false en producción ?>
            <div class="debug-panel">
                <h4><i class="fas fa-bug mr-2"></i>Panel de Depuración</h4>
                <p>Este panel solo es visible en desarrollo y debe ser eliminado en producción.</p>
                
                <h5>Filtro actual: <?php echo $filtro; ?></h5>
                
                <h5>Actividades encontradas: <?php echo count($actividades); ?></h5>
                
                <h5>Tablas de la Base de Datos:</h5>
                <pre><?php 
                    try {
                        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        print_r($tables);
                    } catch (Exception $e) {
                        echo "Error al obtener tablas: " . $e->getMessage();
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
        
        <div class="filter-tabs">
            <a href="actividades.php?filtro=pendientes" class="filter-tab <?php echo $filtro === 'pendientes' ? 'active' : ''; ?>">Pendientes</a>
            <a href="actividades.php?filtro=entregadas" class="filter-tab <?php echo $filtro === 'entregadas' ? 'active' : ''; ?>">Entregadas</a>
            <a href="actividades.php?filtro=calificadas" class="filter-tab <?php echo $filtro === 'calificadas' ? 'active' : ''; ?>">Calificadas</a>
            <a href="actividades.php?filtro=todas" class="filter-tab <?php echo $filtro === 'todas' ? 'active' : ''; ?>">Todas</a>
        </div>
        
        <div class="activities-list">
            <?php if (count($actividades) > 0): ?>
                <?php foreach ($actividades as $actividad): ?>
                    <div class="activity-card">
                        <div class="activity-header">
                            <div class="activity-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h3 class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></h3>
                            
                            <?php if ($filtro === 'pendientes' || ($filtro === 'todas' && !isset($actividad['entrega_id']))): ?>
                                <span class="activity-status status-pending">Pendiente</span>
                            <?php elseif ($filtro === 'entregadas' || ($filtro === 'todas' && isset($actividad['entrega_id']) && !isset($actividad['calificacion']))): ?>
                                <span class="activity-status status-submitted">Entregada</span>
                            <?php elseif ($filtro === 'calificadas' || ($filtro === 'todas' && isset($actividad['calificacion']))): ?>
                                <span class="activity-status status-graded">Calificada</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="activity-body">
                            <div class="activity-info">
                                <div class="activity-info-item">
                                    <div class="info-label">Fecha límite:</div>
                                    <div class="info-value"><?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?></div>
                                </div>
                                
                                <?php if (isset($actividad['fecha_entrega'])): ?>
                                    <div class="activity-info-item">
                                        <div class="info-label">Fecha de entrega:</div>
                                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($actividad['fecha_entrega'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="activity-info-item">
                                    <div class="info-label">Estado:</div>
                                    <div class="info-value">
                                        <?php if ($filtro === 'pendientes' || ($filtro === 'todas' && !isset($actividad['entrega_id']))): ?>
                                            <span class="text-warning">Pendiente de entrega</span>
                                        <?php elseif ($filtro === 'entregadas' || ($filtro === 'todas' && isset($actividad['entrega_id']) && !isset($actividad['calificacion']))): ?>
                                            <span class="text-success">Entregada, pendiente de calificación</span>
                                        <?php elseif ($filtro === 'calificadas' || ($filtro === 'todas' && isset($actividad['calificacion']))): ?>
                                            <span class="text-primary">Calificada</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="activity-description">
                                <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                            </div>
                            
                            <?php if ($filtro === 'pendientes' || ($filtro === 'todas' && !isset($actividad['entrega_id']))): ?>
                                <div class="activity-actions">
                                    <a href="entregar_actividad.php?id=<?php echo $actividad['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-paper-plane mr-2"></i> Realizar Entrega
                                    </a>
                                </div>
                            <?php elseif ($filtro === 'entregadas' || ($filtro === 'todas' && isset($actividad['entrega_id']) && !isset($actividad['calificacion']))): ?>
                                <div class="activity-actions">
                                    <a href="ver_entrega.php?id=<?php echo $actividad['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye mr-2"></i> Ver Entrega
                                    </a>
                                </div>
                            <?php elseif ($filtro === 'calificadas' || ($filtro === 'todas' && isset($actividad['calificacion']))): ?>
                                <div class="grade-info">
                                    <div class="grade-header">
                                        <h4 class="grade-title">Calificación</h4>
                                        <div class="grade-value"><?php echo number_format($actividad['calificacion'], 1); ?>/5.0</div>
                                    </div>
                                    
                                    <?php if (isset($actividad['comentario_tutor']) && !empty($actividad['comentario_tutor'])): ?>
                                        <div class="feedback">
                                            <strong>Retroalimentación del tutor:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($actividad['comentario_tutor'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle mr-2"></i> Esta actividad ya ha sido calificada y no puede ser modificada.
                                    </div>
                                    
                                    <div class="activity-actions mt-3">
                                        <a href="ver_entrega.php?id=<?php echo $actividad['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye mr-2"></i> Ver Entrega
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if ($filtro === 'pendientes'): ?>
                        <i class="fas fa-check-circle"></i>
                        <h3>No tienes actividades pendientes</h3>
                        <p>¡Felicidades! Has completado todas tus actividades asignadas.</p>
                    <?php elseif ($filtro === 'entregadas'): ?>
                        <i class="fas fa-inbox"></i>
                        <h3>No tienes actividades entregadas</h3>
                        <p>Aún no has entregado ninguna actividad o todas han sido calificadas.</p>
                    <?php elseif ($filtro === 'calificadas'): ?>
                        <i class="fas fa-star"></i>
                        <h3>No tienes actividades calificadas</h3>
                        <p>Tus entregas aún no han sido calificadas por el tutor.</p>
                    <?php else: ?>
                        <i class="fas fa-tasks"></i>
                        <h3>No hay actividades disponibles</h3>
                        <p>Aún no se han asignado actividades para este curso.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
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
    
    <script>
        // Auto-ocultar alertas después de 5 segundos
        window.setTimeout(function() {
            const alerts = document.getElementsByClassName('alert');
            for (let i = 0; i < alerts.length; i++) {
                alerts[i].style.opacity = '0';
                alerts[i].style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    alerts[i].style.display = 'none';
                }, 500);
            }
        }, 5000);

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
    
<script>
    // Forzar recarga de imágenes de avatar para evitar caché
    document.addEventListener('DOMContentLoaded', function() {
        const avatarImages = document.querySelectorAll('.avatar');
        avatarImages.forEach(img => {
            if (img.tagName === 'IMG') {
                const src = img.src;
                img.src = src.includes('?') ? 
                    src.split('?')[0] + '?t=' + new Date().getTime() : 
                    src + '?t=' + new Date().getTime();
            }
        });
    });
</script>
</body>
</html>
