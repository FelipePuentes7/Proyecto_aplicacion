<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Iniciar sesión
session_start();

// Habilitar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    // Si no hay sesión, redirigir al login
    header("Location: /views/seminario_estudiante/login.php");
    exit();
}

// Obtener ID del usuario de la sesión
$usuario_id = $_SESSION['usuario']['id'];

// Actualizar avatar en la sesión desde la base de datos
try {
    $conexion = new Conexion();
    $db = $conexion->getConexion();

    $stmt = $db->prepare("
        SELECT avatar FROM usuarios 
        WHERE id = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $avatar = $stmt->fetchColumn();
    
    if ($avatar) {
        $_SESSION['usuario']['avatar'] = $avatar;
    }
} catch (PDOException $e) {
    // Ignorar errores
}

// Verificar si el usuario tiene rol de seminario
try {
    $conexion = new Conexion();
    $db = $conexion->getConexion();

    $stmt = $db->prepare("
        SELECT id FROM usuarios 
        WHERE id = :usuario_id AND opcion_grado = 'seminario'
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $es_seminario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$es_seminario) {
        // Redirigir si no es usuario de seminario
        header("Location: /views/seminario_estudiante/login.php?error=acceso_denegado");
        exit();
    }
} catch (PDOException $e) {
    // Manejar error
    error_log("Error al verificar rol de usuario: " . $e->getMessage());
}

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../actividades.php?error=No se especificó una actividad");
    exit();
}

$actividad_id = $_GET['id'];
$estudiante_id = 1; // En un sistema real, esto vendría de la sesión

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Obtener información de la entrega
try {
    $stmt = $db->prepare("
        SELECT e.*, a.titulo as actividad_titulo, a.descripcion as actividad_descripcion, 
               a.fecha_limite, a.hora_limite
        FROM entregas_actividad e
        JOIN actividades a ON e.id_actividad = a.id
        WHERE e.id_actividad = :actividad_id AND e.id_estudiante = :usuario_id
    ");
    $stmt->bindParam(':actividad_id', $actividad_id);
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entrega) {
        // Si no se encuentra la entrega, redirigir
        header("Location: ../../actividades.php?error=No se encontró la entrega solicitada");
        exit();
    }
    
    // Obtener archivos adjuntos
    $stmt = $db->prepare("
        SELECT * FROM archivos_entrega
        WHERE id_entrega = :entrega_id
    ");
    $stmt->bindParam(':entrega_id', $entrega['id']);
    $stmt->execute();
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En caso de error, usar datos de ejemplo
    $entrega = [
        'id' => 1,
        'id_actividad' => $actividad_id,
        'id_estudiante' => $usuario_id,
        'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'comentario' => 'Este es un comentario de ejemplo para la entrega.',
        'estado' => 'pendiente',
        'calificacion' => null,
        'comentario_tutor' => null,
        'actividad_titulo' => 'Título de actividad de ejemplo',
        'actividad_descripcion' => 'Descripción de la actividad de ejemplo.',
        'fecha_limite' => date('Y-m-d', strtotime('+2 days')),
        'hora_limite' => '23:59:00'
    ];
    
    $archivos = [
        [
            'id' => 1,
            'id_entrega' => 1,
            'nombre_archivo' => 'documento_ejemplo.pdf',
            'ruta_archivo' => '../../uploads/documento_ejemplo.pdf',
            'tipo_archivo' => 'application/pdf',
            'tamano_archivo' => 1024,
            'fecha_subida' => date('Y-m-d H:i:s')
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Ver Entrega</title>
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
        
        .card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            padding: 15px 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h4 {
            color: var(--dark);
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            width: 150px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .info-value {
            flex-grow: 1;
            color: #6c757d;
        }
        
        .comment-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .comment-section h5 {
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .comment-text {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .files-section {
            margin-bottom: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 15px;
        }
        
        .file-info {
            flex-grow: 1;
        }
        
        .file-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .file-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
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
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .actions-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .social-links {
                justify-content: center;
            }
            
            .actions-section {
                flex-direction: column;
                gap: 10px;
            }
            
            .actions-section .btn {
                width: 100%;
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
        <h1 class="page-title">Detalle de Entrega</h1>
        
        <div class="card">
            <div class="card-header">
                <h3 class="m-0"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></h3>
            </div>
            
            <div class="card-body">
                <div class="info-section">
                    <h4>Información de la Actividad</h4>
                    
                    <div class="info-item">
                        <div class="info-label">Descripción:</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($entrega['actividad_descripcion'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Fecha límite:</div>
                        <div class="info-value">
                            <?php echo date('d/m/Y', strtotime($entrega['fecha_limite'])); ?> a las 
                            <?php echo date('H:i', strtotime($entrega['hora_limite'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4>Información de la Entrega</h4>
                    
                    <div class="info-item">
                        <div class="info-label">Fecha de entrega:</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Estado:</div>
                        <div class="info-value">
                            <?php if (isset($entrega['calificacion'])): ?>
                                <span class="badge badge-primary">Calificada</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pendiente de calificación</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($entrega['calificacion'])): ?>
                        <div class="info-item">
                            <div class="info-label">Calificación:</div>
                            <div class="info-value">
                                <strong class="text-primary"><?php echo number_format($entrega['calificacion'], 1); ?>/5.0</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($entrega['comentario'])): ?>
                    <div class="comment-section">
                        <h5>Tu comentario:</h5>
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($entrega['comentario'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($entrega['comentario_tutor']) && !empty($entrega['comentario_tutor'])): ?>
                    <div class="comment-section">
                        <h5>Comentario del tutor:</h5>
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($entrega['comentario_tutor'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="files-section">
                    <h4>Archivos adjuntos</h4>
                    
                    <?php if (count($archivos) > 0): ?>
                        <?php foreach ($archivos as $archivo): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <?php
                                    $extension = pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION);
                                    switch (strtolower($extension)) {
                                        case 'pdf':
                                            echo '<i class="far fa-file-pdf"></i>';
                                            break;
                                        case 'doc':
                                        case 'docx':
                                            echo '<i class="far fa-file-word"></i>';
                                            break;
                                        case 'xls':
                                        case 'xlsx':
                                            echo '<i class="far fa-file-excel"></i>';
                                            break;
                                        case 'ppt':
                                        case 'pptx':
                                            echo '<i class="far fa-file-powerpoint"></i>';
                                            break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                        case 'gif':
                                            echo '<i class="far fa-file-image"></i>';
                                            break;
                                        case 'zip':
                                        case 'rar':
                                            echo '<i class="far fa-file-archive"></i>';
                                            break;
                                        default:
                                            echo '<i class="far fa-file"></i>';
                                    }
                                    ?>
                                </div>
                                
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></div>
                                    <div class="file-meta">
                                        Subido el <?php echo date('d/m/Y H:i', strtotime($archivo['fecha_subida'])); ?>
                                        <?php if (isset($archivo['tamano_archivo'])): ?>
                                            - <?php echo round($archivo['tamano_archivo'] / 1024, 2); ?> KB
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="file-actions">
                                    <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-download mr-1"></i> Descargar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> No hay archivos adjuntos para esta entrega.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="actions-section">
                    <a href="actividades.php<?php echo isset($entrega['calificacion']) ? '?filtro=calificadas' : '?filtro=entregadas'; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left mr-2"></i> Volver a Actividades
                    </a>
                    
            
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-info">
                <h3>Fundación Escuela Tecnológica de Neiva</h3>
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Bogotá</p>
                <p>Teléfono: +57 123 456 7890</p>
                
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <img src="../../assets/images/logofet.png" alt="FET Logo" class="footer-image">
        </div>
    </footer>
</body>
</html>
