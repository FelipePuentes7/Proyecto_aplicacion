<?php
// Incluir archivo de conexión a la base de datos
require_once '../../config/conexion.php';

// Obtener el ID del tutor
$tutor_id = 1; // En un sistema real, esto vendría de la sesión

// Obtener estadísticas
try {
    // Total de estudiantes asignados al tutor (método correcto)
    // Buscamos estudiantes matriculados en actividades de este tutor
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id) as total 
        FROM usuarios 
        WHERE rol = 'estudiante' AND 
        id IN (
            SELECT DISTINCT id_estudiante 
            FROM entregas_actividad ea 
            JOIN actividades a ON ea.id_actividad = a.id 
            WHERE a.tutor_id = :tutor_id
        )
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $total_estudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Si no hay estudiantes, establecer un valor predeterminado
    if (!$total_estudiantes) {
        $total_estudiantes = 0;
    }
    
    // Total de actividades creadas por este tutor
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM actividades
        WHERE tutor_id = :tutor_id
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $total_actividades = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Actividades pendientes de calificar
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM entregas_actividad ea
        JOIN actividades a ON ea.id_actividad = a.id
        WHERE a.tutor_id = :tutor_id AND ea.estado = 'pendiente'
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $actividades_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Próxima clase
    $stmt = $pdo->prepare("
        SELECT id, titulo, fecha, hora, duracion, plataforma, enlace
        FROM clases_virtuales
        WHERE tutor_id = :tutor_id AND fecha >= CURDATE()
        ORDER BY fecha ASC, hora ASC
        LIMIT 1
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $proxima_clase = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si hay un error, registrar el error para depuración y usar datos de ejemplo
    error_log("Error en las estadísticas: " . $e->getMessage());
    $total_estudiantes = 0;
    $total_actividades = 0;
    $actividades_pendientes = 0;
    $proxima_clase = null;
}

// Obtener últimas entregas
try {
    $stmt = $pdo->prepare("
        SELECT ea.id, ea.fecha_entrega, ea.comentario, ea.calificacion,
               a.titulo as actividad_titulo,
               e.nombre as estudiante_nombre, e.avatar as estudiante_avatar
        FROM entregas_actividad ea
        JOIN actividades a ON ea.actividad_id = a.id
        JOIN estudiantes e ON ea.estudiante_id = e.id
        WHERE a.tutor_id = :tutor_id
        ORDER BY ea.fecha_entrega DESC
        LIMIT 5
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $ultimas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $ultimas_entregas = [
        [
            'id' => 1,
            'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'comentario' => 'Aquí está mi entrega',
            'calificacion' => null,
            'actividad_titulo' => 'Diseño de Base de Datos',
            'estudiante_nombre' => 'Ana García',
            'estudiante_avatar' => 'https://randomuser.me/api/portraits/women/1.jpg'
        ],
        [
            'id' => 2,
            'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'comentario' => 'Completé todas las consultas',
            'calificacion' => 4.5,
            'actividad_titulo' => 'Consultas SQL',
            'estudiante_nombre' => 'Carlos Rodríguez',
            'estudiante_avatar' => 'https://randomuser.me/api/portraits/men/2.jpg'
        ],
        [
            'id' => 3,
            'fecha_entrega' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'comentario' => 'Adjunto mi trabajo de normalización',
            'calificacion' => null,
            'actividad_titulo' => 'Normalización',
            'estudiante_nombre' => 'María López',
            'estudiante_avatar' => 'https://randomuser.me/api/portraits/women/3.jpg'
        ]
    ];
}

// Obtener próximas actividades
try {
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, fecha_limite, hora_limite
        FROM actividades
        WHERE tutor_id = :tutor_id AND fecha_limite >= CURDATE()
        ORDER BY fecha_limite ASC, hora_limite ASC
        LIMIT 3
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $proximas_actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $proximas_actividades = [
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
        ],
        [
            'id' => 3,
            'titulo' => 'Normalización',
            'descripcion' => 'Aplicar las formas normales a un esquema de base de datos',
            'fecha_limite' => date('Y-m-d', strtotime('+7 days')),
            'hora_limite' => '23:59:00'
        ]
    ];
}

// Formatear fecha para mostrar
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Inicio Tutor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #039708;
            --primary-light: #039708;
            --secondary: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
        }

        .sidebar {
            background-color: var(--primary);
        }
        .sidebar-header h3,
        .header h1,
        .section-title i,
        .card-header,
        .btn-primary,
        .btn-primary:focus,
        .btn-primary:active,
        .btn-primary:visited,
        .class-platform,
        .class-link,
        .activity-icon,
        .view-all {
            background-color: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }
        .btn-primary:hover,
        .class-link:hover {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
            color: #fff !important;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: var(--primary-light) !important;
            border-left: 4px solid white;
        }
        .activity-icon {
            background-color: rgba(0, 166, 61, 0.1) !important;
            color: var(--primary) !important;
        }
        .view-all {
            color: var(--primary) !important;
            background-color: #f8f9fa !important;
        }
        .view-all:hover {
            background-color: #e9ecef !important;
            color: var(--primary) !important;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0,0,0,0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .sidebar-header img {
            width: 40px;
            margin-right: 10px;
        }
        
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar ul li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar ul li a {
            color: white;
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: var(--primary-light);
            border-left: 4px solid white;
        }
        
        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

  .sidebar {
        display: flex;
        flex-direction: column;
        height: 100vh;
        background-color: #039708; /* o tu color verde institucional */
        }

        .logout-btn:hover {
        color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .header h1 {
            color: #222 !important;
            background: none !important;
            box-shadow: none !important;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .user-profile span {
            font-weight: 500;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .stat-icon.students {
            background-color: rgba(23, 162, 184, 0.2);
            color: var(--info);
        }
        
        .stat-icon.activities {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }
        
        .stat-icon.pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        
        .stat-info h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-info p {
            margin: 5px 0 0;
            color: #6c757d;
        }
        
        .section-title {
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .card {
            border: none;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            padding: 15px 20px;
            border-radius: 5px 5px 0 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .next-class {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
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
            align-items: center;
            margin-bottom: 15px;
        }
        
        .class-platform {
            background-color: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-right: 15px;
        }
        
        .class-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .class-link {
            display: block;
            background-color: var(--primary);
            color: white;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .class-link:hover {
            background-color: var(--primary-light);
            text-decoration: none;
            color: white;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(26, 59, 139, 0.1);
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
        
        .activity-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .activity-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .submission-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .submission-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .submission-item:last-child {
            border-bottom: none;
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .submission-info {
            flex-grow: 1;
        }
        
        .student-name {
            font-weight: 500;
            color: var (--dark);
            margin: 0 0 5px;
        }
        
        .submission-activity {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .submission-status {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        
        .status-graded {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }
        
        .submission-actions {
            margin-left: 10px;
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
            transition: all 0.3s;
        }
        
        .view-all:hover {
            background-color: #e9ecef;
            text-decoration: none;
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
                position: absolute;
                top: 20px;
                left: 20px;
                background: none;
                border: none;
                color: var(--primary);
                font-size: 1.5rem;
                cursor: pointer;
                z-index: 1001;
            }
            
            .main-header {
                padding-left: 60px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        .sidebar-header {
            background: none !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3 style="background: none; box-shadow: none; padding: 0; margin: 0;">
                        <img src="/assets/images/logofet.png" alt="FET Logo" style="width: 100px;">
                    </h3>
                    <div class="tutor-profile" style="margin-top: 15px; display: flex; align-items: center; background: var(--primary); border-radius: 8px; padding: 10px 12px;">
                        <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                            <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
                        </div>
                        <div style="color: #fff;">
                            <div style="font-weight: 500; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">

                            </div>
                            <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Seminario</div>
                        </div>
                    </div>
                </div>
                <ul>
                    <li><a href="inicio_tutor.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
                    <li><a href="actividades_tutor.php"><i class="fas fa-tasks"></i> Actividades</a></li>
                    <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
                    <li><a href="material_tutor.php"><i class="fas fa-book"></i> Material de Apoyo</a></li>
                </ul>

            <!-- Botón de cerrar sesión fijo abajo -->
                <a href="/views/general/login.php" class="logout-btn" style="margin-top: auto; padding: 15px 20px; color: rgba(255, 255, 255, 0.8); text-decoration: none; display: flex; align-items: center;">
                    <i class="fas fa-sign-out-alt" style="margin-right: 10px;"></i> Cerrar sesión
                </a>

            </aside>
            
            <!-- Main Content -->
            <main class="main-content">
                <div class="header">
                    <h1>Panel de Control</h1>
                    
                </div>
                
                <!-- Stats -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon students">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_estudiantes; ?></h3>
                            <p>Estudiantes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon activities">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_actividades; ?></h3>
                            <p>Actividades</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $actividades_pendientes; ?></h3>
                            <p>Pendientes de calificar</p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
    <div class="col-lg-8 mb-4">
        <!-- Next Class -->
        <div class="card-body">
    <?php if ($proxima_clase): ?>
        <div class="next-class">
            <div class="class-header">
                <h5 class="class-title"><?php echo htmlspecialchars($proxima_clase['titulo']); ?></h5>
                <span class="class-date">
                    <?php echo formatearFecha($proxima_clase['fecha']); ?>
                </span>
            </div>
            <div class="class-info" style="display: flex; flex-wrap: wrap; gap: 15px;">
                <span class="class-platform">
                    <i class="fas fa-video"></i> <?php echo htmlspecialchars($proxima_clase['plataforma']); ?>
                </span>
                <span class="class-time">
                    <i class="far fa-clock"></i>
                    <?php echo date('H:i', strtotime($proxima_clase['hora'])); ?> -
                    <?php
                        $inicio = strtotime($proxima_clase['hora']);
                        $fin = $inicio + ($proxima_clase['duracion'] * 60);
                        echo date('H:i', $fin);
                    ?>
                    (<?php echo $proxima_clase['duracion']; ?> min)
                </span>
                <span class="class-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('d/m/Y', strtotime($proxima_clase['fecha'])); ?>
                </span>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo htmlspecialchars($proxima_clase['enlace']); ?>" class="class-link" target="_blank">
                    <i class="fas fa-sign-in-alt mr-2"></i> Iniciar clase
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="next-class">
            <div class="class-header">
                <h5 class="class-title">No hay clases programadas</h5>
            </div>
            <p class="text-muted">No tienes clases programadas próximamente.</p>
            <a href="clase_tutor.php" class="class-link">
                <i class="fas fa-plus mr-2"></i> Programar una clase
            </a>
        </div>
    <?php endif; ?>
</div>
        <!-- Próximas Actividades -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-alt mr-2"></i> Próximas Actividades
            </div>
            <div class="card-body">
    <?php if (count($proximas_actividades) > 0): ?>
        <ul class="activity-list">
            <?php foreach ($proximas_actividades as $actividad): ?>
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="activity-info">
                        <h5 class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></h5>
                        <span class="activity-date">
                            Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?>
                        </span>
                    </div>
                    <div class="activity-actions">
                        <a href="ver_actividad.php?id=<?php echo $actividad['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="actividades_tutor.php" class="view-all mt-3">
            Ver todas las actividades
        </a>
    <?php else: ?>
        <p class="text-center text-muted">No hay actividades próximas.</p>
    <?php endif; ?>
</div>
        </div>
    </div>
    <!-- Acciones rápidas a la derecha en una columna más pequeña -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-bolt mr-2"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <a href="actividades_tutor.php" class="btn btn-primary btn-block">
                            <i class="fas fa-plus mr-2"></i> Nueva Actividad
                        </a>
                    </div>
                    <div class="col-12 mb-3">
                        <a href="clase_tutor.php" class="btn btn-primary btn-block">
                            <i class="fas fa-video mr-2"></i> Programar Clase
                        </a>
                    </div>
                    <div class="col-12 mb-3">
                        <a href="material_tutor.php" class="btn btn-primary btn-block">
                            <i class="fas fa-book mr-2"></i> Compartir Material
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
            </main>
        </div>
    </div>
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {           
            
            // Toggle sidebar on button click
            menuToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const menuToggle = document.querySelector('.menu-toggle');
                
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
