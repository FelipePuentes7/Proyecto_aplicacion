<<<<<<< HEAD
=======
<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Iniciar sesión
session_start();

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
        header("Location: /views/seminario_estudiante/login.php?error=usuario_no_encontrado");
        exit();
    }
    
    // Aquí eliminamos la verificación de opcion_grado para permitir el acceso
    
} catch (PDOException $e) {
    // Manejar error
    error_log("Error al verificar usuario: " . $e->getMessage());
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
    
    if (!$estudiante) {
        // Si no se encuentra el estudiante, usar datos de ejemplo
        $estudiante = [
            'id' => 1,
            'nombre' => 'Carlos',
            'apellido' => 'Rodríguez',
            'email' => 'carlos.rodriguez@example.com',
            'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg'
        ];
    }
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $estudiante = [
        'id' => 1,
        'nombre' => 'Carlos',
        'apellido' => 'Rodríguez',
        'email' => 'carlos.rodriguez@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg'
    ];
}

// Obtener actividades pendientes del estudiante
try {
    $stmt = $db->prepare("
        SELECT a.titulo, a.fecha_limite
        FROM actividades a
        LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
        WHERE (e.id IS NULL OR e.estado != 'entregado')
        ORDER BY a.fecha_limite ASC
        LIMIT 10
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $actividades_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $actividades_pendientes = [];
}

// Obtener el filtro actual (por defecto: recientes)
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'recientes';

// Obtener grabaciones de clases según el filtro
try {
    // Primero verificamos si existe la tabla grabaciones
    $stmt = $db->prepare("
        SHOW TABLES LIKE 'grabaciones'
    ");
    $stmt->execute();
    $tabla_existe = $stmt->rowCount() > 0;
    
    if (!$tabla_existe) {
        // Crear la tabla grabaciones si no existe
        $db->exec("
            CREATE TABLE `grabaciones` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `clase_id` int(11) NOT NULL,
              `url_grabacion` varchar(255) NOT NULL,
              `descripcion` text DEFAULT NULL,
              `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
              `thumbnail_url` varchar(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `clase_id` (`clase_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Modificamos la restricción de clave foránea para permitir ON DELETE SET NULL
        $db->exec("
            ALTER TABLE `grabaciones` 
            DROP FOREIGN KEY IF EXISTS `grabaciones_ibfk_1`,
            ADD CONSTRAINT `grabaciones_ibfk_1` 
            FOREIGN KEY (`clase_id`) 
            REFERENCES `clases_virtuales` (`id`) 
            ON DELETE SET NULL
        ");
    } else {
        // Verificar si existe la columna thumbnail_url
        $stmt = $db->prepare("
            SHOW COLUMNS FROM `grabaciones` LIKE 'thumbnail_url'
        ");
        $stmt->execute();
        $columna_existe = $stmt->rowCount() > 0;
        
        if (!$columna_existe) {
            // Agregar la columna thumbnail_url si no existe
            $db->exec("
                ALTER TABLE `grabaciones` 
                ADD COLUMN `thumbnail_url` varchar(255) DEFAULT NULL
            ");
        }
    }
    
    // Modificamos las consultas para manejar grabaciones de clases eliminadas
    if ($filtro === 'recientes') {
        $stmt = $db->prepare("
        SELECT 
            g.id as grabacion_id,
            g.url_grabacion,
            g.descripcion,
            g.fecha_subida,
            g.thumbnail_url,
            c.id as clase_id,
            COALESCE(c.titulo, 'Clase Archivada') as titulo,
            COALESCE(c.fecha, DATE(g.fecha_subida)) as fecha,
            COALESCE(c.hora, '00:00:00') as hora,
            COALESCE(c.duracion, 0) as duracion,
            COALESCE(c.plataforma, 'N/A') as plataforma,
            COALESCE(c.enlace, '#') as enlace
        FROM grabaciones g
        LEFT JOIN clases_virtuales c ON g.clase_id = c.id
        WHERE 
            (c.fecha >= CURDATE() AND c.id IS NOT NULL)
        ORDER BY g.fecha_subida DESC
        LIMIT 6
    ");
    } else {
        $stmt = $db->prepare("
        SELECT 
            g.id as grabacion_id,
            g.url_grabacion,
            g.descripcion,
            g.fecha_subida,
            g.thumbnail_url,
            c.id as clase_id,
            COALESCE(c.titulo, 'Clase Archivada') as titulo,
            COALESCE(c.fecha, DATE(g.fecha_subida)) as fecha,
            COALESCE(c.hora, '00:00:00') as hora,
            COALESCE(c.duracion, 0) as duracion,
            COALESCE(c.plataforma, 'N/A') as plataforma,
            COALESCE(c.enlace, '#') as enlace
        FROM grabaciones g
        LEFT JOIN clases_virtuales c ON g.clase_id = c.id
        WHERE 
            (c.fecha < CURDATE() OR c.id IS NULL)
        ORDER BY g.fecha_subida DESC
        LIMIT 12
    ");
    }
    
    $stmt->execute();
    $grabaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $grabaciones = [];
    
    // Crear grabaciones de ejemplo
    $fechas_base = [
        'recientes' => [
            date('Y-m-d', strtotime('-1 day')),
            date('Y-m-d', strtotime('-3 days')),
            date('Y-m-d', strtotime('-5 days')),
            date('Y-m-d', strtotime('-6 days')),
            date('Y-m-d', strtotime('-8 days')),
            date('Y-m-d', strtotime('-10 days'))
        ],
        'antiguas' => [
            date('Y-m-d', strtotime('-15 days')),
            date('Y-m-d', strtotime('-20 days')),
            date('Y-m-d', strtotime('-25 days')),
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d', strtotime('-35 days')),
            date('Y-m-d', strtotime('-40 days'))
        ]
    ];
    
    $titulos = [
        'Introducción a SQL',
        'Diseño de Bases de Datos',
        'Normalización',
        'Consultas Avanzadas',
        'Procedimientos Almacenados',
        'Optimización de Consultas'
    ];
    
    $fechas = $fechas_base[$filtro === 'recientes' ? 'recientes' : 'antiguas'];
    
    for ($i = 0; $i < 6; $i++) {
        $grabaciones[] = [
            'grabacion_id' => $i + 1,
            'clase_id' => $i + 1,
            'titulo' => $titulos[$i],
            'fecha' => $fechas[$i],
            'hora' => '10:00:00',
            'duracion' => 60,
            'plataforma' => 'Zoom',
            'enlace' => 'https://zoom.us/rec/share/example-link',
            'url_grabacion' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
            'descripcion' => 'Grabación de ejemplo ' . ($i + 1),
            'fecha_subida' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days'))
        ];
    }
}

// Función para obtener la miniatura de un video
function obtenerMiniatura($url) {
    // YouTube
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        $video_id = '';
        
        if (strpos($url, 'youtube.com/watch?v=') !== false) {
            $query_string = parse_url($url, PHP_URL_QUERY);
            parse_str($query_string, $query_params);
            $video_id = isset($query_params['v']) ? $query_params['v'] : '';
        } elseif (strpos($url, 'youtu.be/') !== false) {
            $path = parse_url($url, PHP_URL_PATH);
            $video_id = trim($path, '/');
        }
        
        if (!empty($video_id)) {
            // Intentar obtener la miniatura de alta calidad primero
            return "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
        }
    }
    
    // Vimeo
    elseif (strpos($url, 'vimeo.com') !== false) {
        $video_id = '';
        
        if (preg_match('/vimeo\.com\/([0-9]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }
        
        if (!empty($video_id)) {
            // Para Vimeo necesitaríamos usar su API para obtener la miniatura
            // Como alternativa, usamos una imagen de respaldo
            return "../../assets/img/vimeo_placeholder.jpg";
        }
    }
    
    // Para otros servicios o si no se pudo obtener la miniatura
    return "../../assets/img/video_placeholder.jpg";
}

// Formatear fecha para mostrar
function formatearFecha($fecha) {
    $timestamp = strtotime($fecha);
    return date('d/m/Y', $timestamp);
}

// Actualizar miniaturas para las grabaciones que no tienen
foreach ($grabaciones as &$grabacion) {
    if (empty($grabacion['thumbnail_url'])) {
        $grabacion['thumbnail_url'] = obtenerMiniatura($grabacion['url_grabacion']);
        
        // En un entorno real, aquí actualizaríamos la base de datos con la miniatura
        try {
            $stmt = $db->prepare("
                UPDATE grabaciones 
                SET thumbnail_url = :thumbnail_url 
                WHERE id = :grabacion_id
            ");
            $stmt->bindParam(':thumbnail_url', $grabacion['thumbnail_url']);
            $stmt->bindParam(':grabacion_id', $grabacion['grabacion_id']);
            $stmt->execute();
        } catch (PDOException $e) {
            // Ignorar errores en este ejemplo
        }
    }
}
?>

>>>>>>> origin/Master
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Grabaciones de Clases - FET</title>
    <link rel="stylesheet" href="../../assets/css/aula_virtual.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body>
<header class="bg-green-600 text-white p-4 flex justify-between items-center">
    <div class="flex items-center">
        <img alt="FET Logo" src="../../assets/img/logofet.png" class="h-12" height="100" width="100"/>
    </div>
    <nav class="flex space-x-4">
        <a class="hover:underline" href="#">Inicio</a>
        <a class="hover:underline" href="actividades.php">Actividades</a>
        <a class="hover:underline" href="#">Aula Virtual</a>
        <a class="hover:underline" href="#">Material de Apoyo</a>
    </nav>
    <div class="flex items-center space-x-4">
        <i class="fas fa-bell"></i>
        <img alt="User Avatar" class="h-10 w-10 rounded-full" height="40"
             src="https://storage.googleapis.com/a1aa/image/rGwRP1XDqncVs5Qe91jniYR4E9ipWdbNPtR2FDUEMUE.jpg"
             width="40"/>
    </div>
</header>

    <main class="recordings-container">
        <h1 class="recordings-title">Grabaciones de Clases</h1>
        
        <div class="recordings-filter">
            <span>Fecha:</span>
            <button class="filter-button active">Recientes</button>
            <button class="filter-button">Antiguas</button>
        </div>

        <div class="recordings-grid">
            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
=======
    <title>FET - Aula Virtual</title>
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
        
        .filter-container {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .filter-label {
            color: white;
            margin-right: 15px;
            font-weight: 500;
        }
        
        .filter-buttons {
            display: flex;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            overflow: hidden;
        }
        
        .filter-button {
            padding: 8px 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: white;
        }
        
        .filter-button.active {
            background-color: white;
            color: var(--primary);
        }
        
        .recordings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .recording-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .video-thumbnail {
            position: relative;
            height: 200px;
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
        }
        
        .thumbnail-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .video-thumbnail:hover .thumbnail-img {
            transform: scale(1.05);
        }
        
        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background-color: rgba(0, 166, 61, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .video-thumbnail:hover .play-button {
            background-color: var(--primary);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .recording-info {
            padding: 15px;
        }
        
        .recording-title {
            font-weight: 500;
            color: var(--dark);
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .recording-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .recording-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .archived-badge {
            display: inline-block;
            background-color: var(--warning);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
            vertical-align: middle;
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
        
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .video-container {
            width: 80%;
            max-width: 800px;
            background-color: black;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0,0,0,0.5);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            transition: all 0.3s;
        }
        
        .close-button:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .video-player {
            width: 100%;
            height: auto;
            display: block;
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
        
        /* Indicador de duración del video */
        .video-duration {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            z-index: 2;
        }
        
        /* Efecto de carga para las miniaturas */
        .thumbnail-loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .thumbnail-loading::after {
            content: '';
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 166, 61, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .recordings-grid {
                grid-template-columns: 1fr;
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
    </style>
</head>
<body>
    <header class="header">
        <img src="../../assets/images/logofet.png" alt="FET Logo" class="logo">
        
        <nav class="nav-links">
            <a href="inicio_estudiantes.php">Inicio</a>
            <a href="actividades.php">Actividades</a>
            <a href="aula_virtual.php" class="active">Aula Virtual</a>
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

    <main class="main-content">
        <h1 class="page-title">Grabaciones de Clases</h1>
        
        <div class="filter-container">
            <div class="filter-label">Fecha:</div>
            <div class="filter-buttons">
                <a href="aula_virtual.php?filtro=recientes" class="filter-button <?php echo $filtro === 'recientes' ? 'active' : ''; ?>">Recientes</a>
                <a href="aula_virtual.php?filtro=antiguas" class="filter-button <?php echo $filtro === 'antiguas' ? 'active' : ''; ?>">Antiguas</a>
            </div>
        </div>
        
        <?php if (count($grabaciones) > 0): ?>
            <div class="recordings-grid">
                <?php foreach ($grabaciones as $grabacion): ?>
                    <div class="recording-card">
                        <div class="video-thumbnail" onclick="openVideo('<?php echo $grabacion['url_grabacion']; ?>', '<?php echo htmlspecialchars($grabacion['titulo']); ?>')">
                            <div class="thumbnail-loading"></div>
                            <img src="<?php echo $grabacion['thumbnail_url']; ?>" alt="<?php echo htmlspecialchars($grabacion['titulo']); ?>" class="thumbnail-img" 
                                 onload="this.parentNode.querySelector('.thumbnail-loading').style.display='none';"
                                 onerror="this.onerror=null; this.src='../../assets/img/video_placeholder.jpg'; this.parentNode.querySelector('.thumbnail-loading').style.display='none';">
                            <div class="play-button">
                                <i class="fas fa-play"></i>
                            </div>
                            <?php if ($grabacion['duracion'] > 0): ?>
                                <div class="video-duration">
                                    <?php echo floor($grabacion['duracion'] / 60) . ':' . str_pad($grabacion['duracion'] % 60, 2, '0', STR_PAD_LEFT); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="recording-info">
                            <h3 class="recording-title">
                                <?php echo htmlspecialchars($grabacion['titulo']); ?>
                                <?php if ($grabacion['clase_id'] === null): ?>
                                    <span class="archived-badge">Archivada</span>
                                <?php endif; ?>
                            </h3>
                            <p class="recording-date">Fecha: <?php echo formatearFecha($grabacion['fecha']); ?></p>
                            <?php if (!empty($grabacion['descripcion'])): ?>
                                <p class="recording-description"><?php echo htmlspecialchars($grabacion['descripcion']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <h3>No hay grabaciones disponibles</h3>
                <p>Aún no se han publicado grabaciones de clases para este curso.</p>
            </div>
        <?php endif; ?>
        
        <!-- Video Modal -->
        <div class="video-modal" id="videoModal">
            <div class="video-container">
                <div class="close-button" onclick="closeVideo()">
                    <i class="fas fa-times"></i>
                </div>
                <div id="videoWrapper">
                    <!-- El contenido del video se insertará aquí dinámicamente -->
>>>>>>> origin/Master
                </div>
            </div>
        </div>
    </main>
<<<<<<< HEAD

    <footer class="bg-green-600 text-white p-4 mt-8">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="text-center md:text-left">
            <h4 class="font-bold">Fundacion Escuela Tecnologica de Neiva</h4>
            <p>Email: soporte@universidad.edu</p>
            <p>Dirección: Calle 123, Bogotá</p>
            <p>Teléfono: +57 123 456 7890</p>
        </div>
        <img alt="Promotional Image" class="h-24 mt-4 md:mt-0" height="100" src="../../assets/img/image.png"
             width="150"/>
    </div>
    <div class="flex justify-center space-x-4 mt-4">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-tiktok"></i></a>
    </div>
    <div class="text-center mt-4">
        <p class="font-bold">FET</p>
    </div>
</footer>
</body>
</html>
=======
    
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
        // Función para extraer el ID de video de YouTube
        function getYoutubeVideoId(url) {
            let videoId = '';
            
            if (url.includes('youtube.com/watch?v=')) {
                const urlParams = new URLSearchParams(new URL(url).search);
                videoId = urlParams.get('v');
            } else if (url.includes('youtu.be/')) {
                videoId = url.split('youtu.be/')[1];
                if (videoId.includes('?')) {
                    videoId = videoId.split('?')[0];
                }
            }
            
            return videoId;
        }
        
        // Función para extraer el ID de video de Vimeo
        function getVimeoVideoId(url) {
            let videoId = '';
            
            if (url.includes('vimeo.com/')) {
                const matches = url.match(/vimeo\.com\/([0-9]+)/);
                if (matches && matches.length > 1) {
                    videoId = matches[1];
                }
            }
            
            return videoId;
        }
        
        // Función para cargar miniaturas de YouTube
        function loadYoutubeThumbnails() {
            document.querySelectorAll('.video-thumbnail').forEach(thumbnail => {
                const videoUrl = thumbnail.getAttribute('onclick').split("'")[1];
                if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
                    const videoId = getYoutubeVideoId(videoUrl);
                    if (videoId) {
                        const img = thumbnail.querySelector('.thumbnail-img');
                        img.src = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
                        
                        // Si la miniatura de alta calidad falla, usar la estándar
                        img.onerror = function() {
                            this.onerror = null;
                            this.src = `https://img.youtube.com/vi/${videoId}/0.jpg`;
                        };
                    }
                }
            });
        }
        
        // Cargar miniaturas cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            loadYoutubeThumbnails();
        });
        
        // Función para abrir el modal de video
        function openVideo(videoUrl, videoTitle) {
            const videoModal = document.getElementById('videoModal');
            const videoWrapper = document.getElementById('videoWrapper');
            
            // Mostrar un indicador de carga
            videoWrapper.innerHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 450px; color: white;">
                    <div style="text-align: center;">
                        <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                        <p>Cargando video...</p>
                    </div>
                </div>
            `;
            
            // Mostrar el modal inmediatamente con el indicador de carga
            videoModal.style.display = 'flex';
            
            // Determinar el tipo de URL
            if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
                // Convertir URL de YouTube a formato embed
                const youtubeId = getYoutubeVideoId(videoUrl);
                
                if (youtubeId) {
                    const iframe = document.createElement('iframe');
                    iframe.width = '100%';
                    iframe.height = '450';
                    iframe.src = `https://www.youtube.com/embed/${youtubeId}?autoplay=1`;
                    iframe.frameBorder = '0';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    // Reemplazar el indicador de carga con el iframe
                    videoWrapper.innerHTML = '';
                    videoWrapper.appendChild(iframe);
                } else {
                    // Si no se pudo extraer el ID, mostrar mensaje de error
                    showVideoError('No se pudo procesar la URL de YouTube.');
                }
            } else if (videoUrl.includes('vimeo.com')) {
                // Convertir URL de Vimeo a formato embed
                const vimeoId = getVimeoVideoId(videoUrl);
                
                if (vimeoId) {
                    const iframe = document.createElement('iframe');
                    iframe.width = '100%';
                    iframe.height = '450';
                    iframe.src = `https://player.vimeo.com/video/${vimeoId}?autoplay=1`;
                    iframe.frameBorder = '0';
                    iframe.allow = 'autoplay; fullscreen';
                    iframe.allowFullscreen = true;
                    
                    // Reemplazar el indicador de carga con el iframe
                    videoWrapper.innerHTML = '';
                    videoWrapper.appendChild(iframe);
                } else {
                    // Si no se pudo extraer el ID, mostrar mensaje de error
                    showVideoError('No se pudo procesar la URL de Vimeo.');
                }
            } else {
                // Para otros tipos de URL, intentar con un iframe genérico primero
                try {
                    const iframe = document.createElement('iframe');
                    iframe.width = '100%';
                    iframe.height = '450';
                    iframe.src = videoUrl;
                    iframe.frameBorder = '0';
                    iframe.allow = 'autoplay; fullscreen';
                    iframe.allowFullscreen = true;
                    
                    // Reemplazar el indicador de carga con el iframe
                    videoWrapper.innerHTML = '';
                    videoWrapper.appendChild(iframe);
                    
                    // Verificar si el iframe cargó correctamente
                    iframe.onerror = function() {
                        // Si hay error, intentar con el reproductor de video nativo
                        useNativeVideoPlayer(videoUrl, videoWrapper);
                    };
                } catch (e) {
                    // Si hay error, intentar con el reproductor de video nativo
                    useNativeVideoPlayer(videoUrl, videoWrapper);
                }
            }
        }
        
        // Función para usar el reproductor de video nativo
        function useNativeVideoPlayer(videoUrl, container) {
            // Limpiar el contenedor
            container.innerHTML = '';
            
            // Crear elemento de video
            const video = document.createElement('video');
            video.className = 'video-player';
            video.controls = true;
            video.preload = 'metadata';
            video.style.width = '100%';
            video.style.height = 'auto';
            video.style.maxHeight = '450px';
            
            // Crear fuente de video
            const source = document.createElement('source');
            source.src = videoUrl;
            
            // Intentar determinar el tipo de video por la extensión
            const fileExtension = videoUrl.split('.').pop().toLowerCase();
            if (fileExtension === 'mp4') {
                source.type = 'video/mp4';
            } else if (fileExtension === 'webm') {
                source.type = 'video/webm';
            } else if (fileExtension === 'ogg' || fileExtension === 'ogv') {
                source.type = 'video/ogg';
            } else if (fileExtension === 'mov') {
                source.type = 'video/quicktime';
            } else {
                // Si no se puede determinar, usar un tipo genérico
                source.type = 'video/mp4';
            }
            
            // Mensaje de error si el video no puede reproducirse
            video.innerHTML = 'Tu navegador no soporta la reproducción de videos HTML5.';
            
            // Añadir la fuente al video
            video.appendChild(source);
            
            // Añadir el video al contenedor
            container.appendChild(video);
            
            // Manejar errores de carga
            video.onerror = function() {
                showVideoError('No se pudo cargar el video. Formato no soportado o URL incorrecta.');
            };
            
            // Intentar reproducir después de un breve retraso
            setTimeout(() => {
                try {
                    const playPromise = video.play();
                    
                    // Manejar posibles errores de reproducción
                    if (playPromise !== undefined) {
                        playPromise.catch(error => {
                            console.error('Error al reproducir el video:', error);
                            video.pause();
                            // No mostrar alerta, solo dejar el video con controles
                        });
                    }
                } catch (e) {
                    console.error('Error al intentar reproducir:', e);
                }
            }, 300);
        }
        
        // Función para mostrar mensaje de error en el reproductor
        function showVideoError(message) {
            const videoWrapper = document.getElementById('videoWrapper');
            videoWrapper.innerHTML = `
                <div style="padding: 20px; text-align: center; color: white;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h4>${message}</h4>
                    <p>Intenta con otra URL o contacta al administrador.</p>
                </div>
            `;
        }
        
        // Función para cerrar el modal de video
        function closeVideo() {
            const videoModal = document.getElementById('videoModal');
            const videoWrapper = document.getElementById('videoWrapper');
            
            // Limpiar el contenedor de video
            videoWrapper.innerHTML = '';
            
            // Ocultar el modal
            videoModal.style.display = 'none';
        }
        
        // Cerrar el modal al hacer clic fuera del video
        document.getElementById('videoModal').addEventListener('click', function(event) {
            // Cerrar solo si se hace clic fuera del contenedor de video
            if (event.target === document.getElementById('videoModal')) {
                closeVideo();
            }
        });
        
        // Cerrar el modal con la tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('videoModal').style.display === 'flex') {
                closeVideo();
            }
        });
        
        // Manejar errores de carga de imágenes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.thumbnail-img').forEach(img => {
                img.addEventListener('error', function() {
                    this.src = '../../assets/img/video_placeholder.jpg';
                    this.parentNode.querySelector('.thumbnail-loading').style.display = 'none';
                });
            });
        });
        
        // Notificaciones
        document.addEventListener('DOMContentLoaded', function() {
            const bell = document.getElementById('notification-bell');
            const panel = document.getElementById('notification-panel');
            const avatar = document.getElementById('avatar-container');
            const userMenu = document.getElementById('user-menu');

            // Mostrar/ocultar panel de notificaciones
            if (bell && panel) {
                bell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Cierra el menú usuario si está abierto
                    if (userMenu) userMenu.style.display = 'none';
                    // Toggle panel notificaciones
                    panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
                });
            }

            // Mostrar/ocultar menú usuario
            if (avatar && userMenu) {
                avatar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Cierra el panel de notificaciones si está abierto
                    if (panel) panel.style.display = 'none';
                    // Toggle menú usuario
                    userMenu.style.display = (userMenu.style.display === 'block') ? 'none' : 'block';
                });
            }

            // Cerrar ambos al hacer click fuera
            document.addEventListener('click', function() {
                if (panel) panel.style.display = 'none';
                if (userMenu) userMenu.style.display = 'none';
            });

            // Evita que el panel se cierre al hacer click dentro de él
            if (panel) {
                panel.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
</body>
</html>
>>>>>>> origin/Master
