<?php
<<<<<<< HEAD
<<<<<<< HEAD
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    header('Location: /views/general/login.php');
    exit();
}

// Obtener los datos del usuario desde la base de datos
try {
    $userId = $_SESSION['usuario']['id'];
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Error: No se encontró información del usuario.");
    }
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
=======
// Incluir archivo de conexión
require_once realpath(__DIR__ . '/../../config/conexion.php');
=======
// Incluir archivo de conexión
require_once '../../config/conexion.php';
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1

// Iniciar sesión
session_start();

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    // Si no hay sesión, redirigir al login
    header("Location: /views/general/login.php");
    exit();
}

// Obtener ID del usuario de la sesión
$usuario_id = $_SESSION['usuario']['id'];

// Verificar si el usuario tiene rol de seminario
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
    // Podemos implementar una verificación más específica más adelante si es necesario
    
} catch (PDOException $e) {
    // Manejar error
    error_log("Error al verificar usuario: " . $e->getMessage());
    header("Location: /views/seminario_estudiante/login.php?error=error_db");
    exit();
}

// Obtener información del estudiante y actualizar la sesión con el avatar más reciente
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
        // Actualizar el avatar en la sesión si es diferente al de la base de datos
        if (isset($estudiante['avatar']) && (!isset($_SESSION['usuario']['avatar']) || $_SESSION['usuario']['avatar'] !== $estudiante['avatar'])) {
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

// Obtener actividades pendientes
try {
    $stmt = $db->prepare("
        SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo
        FROM actividades a
        LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
        WHERE e.id IS NULL
        ORDER BY a.fecha_limite ASC, a.hora_limite ASC
        LIMIT 5
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $actividades_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $actividades_pendientes = [
        [
            'id' => 1,
            'titulo' => 'Diseño de Base de Datos',
            'descripcion' => 'Crear un diagrama ER para un sistema de gestión de biblioteca',
            'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
            'hora_limite' => '23:59:00',
            'tipo' => 'tarea'
        ],
        [
            'id' => 2,
            'titulo' => 'Consultas SQL Básicas',
            'descripcion' => 'Realizar consultas SELECT con filtros y ordenamiento',
            'fecha_limite' => date('Y-m-d', strtotime('+5 days')),
            'hora_limite' => '23:59:00',
            'tipo' => 'tarea'
        ]
    ];
}

// Obtener actividades entregadas recientemente
try {
    $stmt = $db->prepare("
        SELECT ea.id, ea.fecha_entrega, ea.estado, ea.calificacion, 
               a.id as actividad_id, a.titulo as actividad_titulo
        FROM entregas_actividad ea
        JOIN actividades a ON ea.id_actividad = a.id
        WHERE ea.id_estudiante = :usuario_id
        ORDER BY ea.fecha_entrega DESC
        LIMIT 5
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $actividades_entregadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $actividades_entregadas = [
        [
            'id' => 1,
            'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'estado' => 'calificado',
            'calificacion' => 4.5,
            'actividad_id' => 3,
            'actividad_titulo' => 'Normalización de Bases de Datos'
        ],
        [
            'id' => 2,
            'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'estado' => 'pendiente',
            'calificacion' => null,
            'actividad_id' => 4,
            'actividad_titulo' => 'Consultas SQL Avanzadas'
        ]
    ];
}

// Calcular progreso del curso
try {
    // Calcular progreso real: solo actividades calificadas cuentan
    $total_actividades = $db->query("SELECT COUNT(*) FROM actividades")->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM entregas_actividad 
        WHERE id_estudiante = :usuario_id AND estado = 'calificado'
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $actividades_calificadas_count = $stmt->fetchColumn();

    if ($total_actividades > 0) {
        $porcentaje_progreso = ($actividades_calificadas_count / $total_actividades) * 100;
    } else {
        $porcentaje_progreso = 0;
    }
} catch (PDOException $e) {
    // Si hay un error, usar valores predeterminados
    $total_actividades = 4;
    $actividades_calificadas_count = 1;
    $porcentaje_progreso = 25; // 1 de 4 = 25%
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

// Función para calcular días restantes
function calcularDiasRestantes($fecha_limite) {
    $hoy = time();
    $limite = strtotime($fecha_limite);
    $diferencia = $limite - $hoy;
    return max(0, floor($diferencia / (60 * 60 * 24)));
}

function entregaCalificada($db, $actividad_id, $usuario_id) {
    $stmt = $db->prepare("
        SELECT 1 
        FROM entregas_actividad 
        WHERE id_actividad = :actividad_id 
          AND id_estudiante = :usuario_id 
          AND estado = 'calificado'
        LIMIT 1
    ");
    $stmt->bindParam(':actividad_id', $actividad_id);
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    return $stmt->fetchColumn() !== false;
<<<<<<< HEAD
>>>>>>> origin/Master
=======
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
}
?>

<!DOCTYPE html>
<<<<<<< HEAD
<<<<<<< HEAD
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/Inicio_Estu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>MissionFet - Inicio Estudiante</title>
</head>

<body>

<nav class="navbar">
        <!-- Espacio para el logo -->
        <div class="logo">
            <img src="/assets/images/logofet.png" alt="Logo FET" style="max-height: 50px;">
        </div>

        <!-- Links de navegación -->
        <div class="nav-links">
            <a href="/views/estudiantes/Inicio_Estudiante.php">Inicio</a>
            <a href="#">Material de Apoyo</a>
            <a href="#">Tutorías</a>
        </div>

        <!-- Acciones del usuario -->
        <div class="user-actions">
            <div class="notifications">
            <i class="fa-solid fa-bell"></i> <!-- Ícono de notificaciones -->
            </div>
            <div class="dropdown">
                <i class="fa-solid fa-user"></i> <!-- Ícono de perfil -->
                <div class="dropdown-content">
                    <a href="/views/estudiantes/Perfil_estudiante.php">Editar Perfil</a>
                    <a href="/views/general/login.php" class="logout-btn" onclick="cerrarSesion(event)">
                <i class="fa-solid fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
</nav>

<section class="Bienvenido">

    <div class="mensaje">
        <h3><strong>¡BIENVENIDO, <?php echo htmlspecialchars($user['nombre']); ?>!</strong></h3>

            <p>Organiza y Gestiona tu proyecto de manera eficiente. <br>Aqui Encontraras todo lo que necesitas para avanzar.</p>
    </div>

    <img src="/assets/images/Imagen_principal.png" alt="Imagen_inicio">

</section>

<div class="calendario">
    


</div>







<footer class="footer">

        <!-- Sección de información -->
        <div class="info-section">

            <!-- Links -->
            <div class="links">

                <p class="title_footer"><strong>Fundación Escuela Tecnológica de Neiva</strong></p>

                <a href="#">Inicio</a>
                <a href="#">Subir Avances</a>
                <a href="#">Historial de Avances</a>
                <a href="#">Material de Apoyo</a>
            </div>

            <!-- Información de contacto -->
            <div class="contact">
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Bogotá</p>
                <p>Teléfono: +57 123 456 7890</p>
            </div>

            <!-- Espacio para la imagen -->
            <div class="image">
                <img src="/assets/images/image.png" alt="Imagen del footer" style="max-width: 50%;">
            </div>
        </div>

        <!-- Sección de redes sociales -->
        <div class="social-section">
            <!-- Redes sociales -->
            <div class="social-links">
                <a href="https://es-es.facebook.com/" target="_blank"><i class="fa-brands fa-facebook"></i></a>
                <a href="https://twitter.com/?lang=es" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="https://www.instagram.com/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
            </div>

            <!-- Logo del medio -->
            <div class="logo">
            <img src="/assets/images/logo_footer.png" alt="Footer FET" style="max-height: 50px;">
            </div>
        </div>
    </footer>




    <script src="https://kit.fontawesome.com/4fac1b523f.js" crossorigin="anonymous"></script>

</body>
</html>
=======
=======
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Inicio Estudiante</title>
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
        
        .welcome-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .welcome-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .welcome-message {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(0, 166, 61, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .activity-info {
            flex-grow: 1;
        }
        
        .activity-title {
            font-weight: 500;
            color: var(--dark);
            margin: 0 0 5px;
        }
        
        .activity-meta {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .activity-meta i {
            margin-right: 5px;
        }
        
        .activity-meta span {
            margin-right: 15px;
        }
        
        .activity-status {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-submitted {
            background-color: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }
        
        .status-graded {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .view-all {
            display: block;
            text-align: center;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .view-all:hover {
            background-color: #e9ecef;
            text-decoration: none;
            color: var(--primary);
        }
        
        .course-progress {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .progress-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .progress-percentage {
            font-weight: 700;
            color: var(--primary);
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            background-color: var(--primary);
        }
        
        .progress-details {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .calendar-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .calendar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .calendar-navigation {
            display: flex;
            gap: 10px;
        }
        
        .calendar-nav-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .calendar-nav-btn:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-weight: 500;
            color: var(--dark);
            padding: 5px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        
        .calendar-day.today {
            background-color: var(--primary);
            color: white;
            font-weight: 700;
        }
        
        .calendar-day.has-event {
            position: relative;
        }
        
        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 5px;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: var(--primary);
        }
        
        .calendar-day.today.has-event::after {
            background-color: white;
        }
        
        .calendar-day.other-month {
            color: #ced4da;
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
            
            .dashboard-grid {
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

        .dashboard-cards-vertical {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
            max-width: 900px;
            margin: 0 auto 20px auto;
        }
        .dashboard-card {
            width: 100% !important;
            max-width: 100% !important;
        }

        .next-class {
            margin-bottom: 15px;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .class-title {
            font-weight: 500;
            color: var(--dark);
            margin: 0;
        }

        .class-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .class-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }

        .class-platform {
            background-color: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .class-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <img src="../../assets/images/logofet.png" alt="FET Logo" class="logo">
        
        <nav class="nav-links">
            <a href="inicio_estudiantes.php" class="active">Inicio</a>
            <a href="actividades.php">Actividades</a>
            <a href="aula_virtual.php">Aula Virtual</a>
            <a href="material_apoyo.php">Material de Apoyo</a>
        </nav>
        
        <div class="user-profile" style="position: relative;">
            <!-- Notificación -->
            <div id="notification-bell" style="position: relative; cursor: pointer;">
                <i class="fas fa-bell notification-icon"></i>
                <?php
                // Ejemplo: cuenta de notificaciones (puedes reemplazar por consulta real)
                $notificaciones = [];
                foreach ($actividades_pendientes as $act) {
                    $notificaciones[] = [
                        'mensaje' => 'Nueva actividad: ' . $act['titulo'],
                        'fecha' => date('d/m/Y', strtotime($act['fecha_limite']))
                    ];
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
        <section class="welcome-section">
            <div class="welcome-header">
                <h1 class="welcome-title">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?></h1>
                <span class="welcome-date"><?php 
    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');
    echo strftime('%A, %d de %B de %Y', time()); 
  ?></span>
            </div>
            <p class="welcome-message">Aquí encontrarás un resumen de tus actividades pendientes y entregas recientes.</p>
        </section>

        <!-- Progreso del curso -->
        <section class="course-progress mb-4">
            <div class="progress-header">
<<<<<<< HEAD
                <h2 class="progress-title">Desarrollo Del Seminario</h2>
=======
                <h2 class="progress-title">Seminario - Base de Datos Relacionales</h2>
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
                <span class="progress-percentage"><?php echo round($porcentaje_progreso); ?>%</span>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar"
                    style="width: <?php echo $porcentaje_progreso; ?>%"
                    aria-valuenow="<?php echo $porcentaje_progreso; ?>"
                    aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="progress-details">
                <span><?php echo $actividades_calificadas_count; ?> de <?php echo $total_actividades; ?> actividades calificadas</span>
                <a href="actividades.php?filtro=calificadas">Ver detalles</a>
            </div>
        </section>

        <!-- Tarjetas de resumen: ahora en columna, una sobre otra -->
        <div class="dashboard-cards-vertical">
            <!-- Actividades Pendientes -->
            <div class="dashboard-card flex-fill mb-4" style="min-width:320px; max-width:100%;">
                <div class="card-header">
                    <h2 class="card-title">Actividades Pendientes</h2>
                    <div class="card-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <?php if (count($actividades_pendientes) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($actividades_pendientes as $actividad): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                                </div>
                                <div class="activity-info">
                                    <h3 class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></h3>
                                    <div class="activity-meta">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?></span>
                                    </div>
                                </div>
                                <a href="entregar_actividad.php?id=<?php echo $actividad['id']; ?>" class="btn btn-sm btn-outline-primary">Entregar</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="actividades.php?filtro=pendientes" class="view-all">Ver todas las actividades pendientes</a>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p>¡No tienes actividades pendientes!</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Entregas Recientes -->
            <div class="dashboard-card flex-fill mb-4" style="min-width:320px; max-width:100%;">
                <div class="card-header">
                    <h2 class="card-title">Entregas Recientes</h2>
                    <div class="card-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                </div>

                
                <?php if (count($actividades_entregadas) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($actividades_entregadas as $entrega): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="activity-info">
                                    <h3 class="activity-title"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></h3>
                                    <div class="activity-meta">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($entrega['fecha_entrega'])); ?></span>
                                    </div>
                                </div>
                                <?php if ($entrega['estado'] === 'pendiente'): ?>
                                    <span class="activity-status status-submitted">Entregado</span>
                                <?php else: ?>
                                    <span class="activity-status status-graded">Calificado: <?php echo $entrega['calificacion']; ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="actividades.php?filtro=entregadas" class="view-all">Ver todas mis entregas</a>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p>No has realizado entregas recientemente</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Próximas Clases -->
            <div class="dashboard-card flex-fill mb-4" style="min-width:320px; max-width:100%;">
                <div class="card-header">
                    <h2 class="card-title">Próximas Clases</h2>
                    <div class="card-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
                <?php
                // Consultar próximas clases en la base de datos
                try {
                    $stmt = $db->prepare("
                        SELECT id, titulo, fecha, hora, duracion, plataforma, enlace
                        FROM clases_virtuales
                        WHERE fecha >= CURDATE()
                        ORDER BY fecha ASC, hora ASC
                        LIMIT 3
                    ");
                    $stmt->execute();
                    $proximas_clases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // Si hay un error, usar datos de ejemplo
                    $proximas_clases = [
                        [
                            'id' => 1,
                            'titulo' => 'Clase de Normalización',
                            'fecha' => date('Y-m-d', strtotime('+2 days')),
                            'hora' => '10:00:00',
                            'duracion' => 60,
                            'plataforma' => 'Zoom',
                            'enlace' => '#'
                        ],
                        [
                            'titulo' => 'Consultas SQL Avanzadas',
                            'fecha' => date('Y-m-d', strtotime('+5 days')),
                            'hora' => '14:00:00',
                            'duracion' => 90,
                            'plataforma' => 'Google Meet',
                            'enlace' => '#'
                        ]
                    ];
                }

                // Función para formatear fecha
                function formatearFecha($fecha) {
                    $timestamp = strtotime($fecha);
                    $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                    
                    $dia_semana = $dias_semana[date('w', $timestamp)];
                    $dia = date('j', $timestamp);
                    $mes = $meses[date('n', $timestamp) - 1];
                    
                    return "$dia_semana $dia de $mes";
                }
                ?>

                <?php if (count($proximas_clases) > 0): ?>
                    <?php foreach ($proximas_clases as $clase): ?>
                        <div class="next-class" style="border-bottom: 1px solid #eee; padding: 15px 0; margin-bottom: 10px;">
                            <div class="class-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h5 class="class-title" style="font-weight: 500; color: var(--dark); margin: 0;"><?php echo htmlspecialchars($clase['titulo']); ?></h5>
                                <span class="class-date" style="color: #6c757d; font-size: 0.9rem;">
                                    <?php echo formatearFecha($clase['fecha']); ?>
                                </span>
                            </div>
                            <div class="class-info" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px;">
                                <span class="class-platform" style="background-color: var(--primary); color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.85rem;">
                                    <i class="fas fa-video"></i> <?php echo htmlspecialchars($clase['plataforma']); ?>
                                </span>
                                <span class="class-time" style="color: #6c757d; font-size: 0.85rem;">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('H:i', strtotime($clase['hora'])); ?> -
                                    <?php
                                        $inicio = strtotime($clase['hora']);
                                        $fin = $inicio + ($clase['duracion'] * 60);
                                        echo date('H:i', $fin);
                                    ?>
                                    (<?php echo $clase['duracion']; ?> min)
                                </span>
                                <span class="class-date" style="color: #6c757d; font-size: 0.85rem;">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('d/m/Y', strtotime($clase['fecha'])); ?>
                                </span>
                            </div>
                            <div>
                                <a href="<?php echo htmlspecialchars($clase['enlace']); ?>" class="btn btn-sm btn-primary" target="_blank" style="background-color: var(--primary); border-color: var(--primary);">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Unirse a la clase
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                        <p>No hay clases programadas próximamente.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-info">
<<<<<<< HEAD
                <p>Email: direccion_software@fet.edu.co</p>
                <p>Dirección: Kilómetro 12, via Neiva – Rivera</p>
                <p>Teléfono: 6088674935 – (+57) 3223041567</p>
                
                <div class="social-links">
                    <a href="https://www.facebook.com/YoSoyFet" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com/yosoyfet" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/fetneiva" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/channel/UCv647ftA-d--0F02AqF7eng" target="_blank"><i class="fab fa-youtube"></i></a>
=======
                <h3>Fundación Escuela Tecnológica de Neiva</h3>
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Neiva</p>
                <p>Teléfono: +57 123 456 7890</p>
                
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
                </div>
            </div>
            
            <img src="../../assets/images/logofet.png" alt="FET Logo" class="footer-image">
        </div>
    </footer>
    
    <script>
        // Código JavaScript para funcionalidades adicionales
        document.addEventListener('DOMContentLoaded', function() {
            // Aquí puedes agregar código para manejar notificaciones, interacciones, etc.
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

        // Verificar que los elementos existan antes de añadir event listeners
        if (bell && panel && avatar && userMenu) {
            console.log('Elementos de UI encontrados correctamente');
        } else {
            console.error('Algunos elementos de UI no fueron encontrados');
        }
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
<<<<<<< HEAD
>>>>>>> origin/Master
=======
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
