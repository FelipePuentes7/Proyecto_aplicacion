<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Iniciar sesión
session_start();

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// ID del tutor (hardcodeado para pruebas)
$tutor_id = 1;

// Obtener información del tutor
try {
    $stmt = $db->prepare("
        SELECT u.nombre, u.apellido, u.email, u.avatar
        FROM usuarios u
        WHERE u.id = :tutor_id AND u.rol = 'tutor'
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tutor) {
        // Si no se encuentra el tutor, usar datos de ejemplo
        $tutor = [
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'email' => 'juan.perez@example.com',
            'avatar' => 'https://randomuser.me/api/portraits/men/41.jpg'
        ];
    }
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $tutor = [
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'email' => 'juan.perez@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/41.jpg'
    ];
}

// Obtener el filtro actual (por defecto: todas)
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';

// Obtener actividades según el filtro
try {
    switch ($filtro) {
        case 'pendientes':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
                       a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'pendiente') as entregas_pendientes,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado') as entregas_calificadas,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id) as total_entregas
                FROM actividades a
                WHERE a.tutor_id = :tutor_id AND a.fecha_limite >= CURDATE()
                ORDER BY a.fecha_limite ASC, a.hora_limite ASC
            ");
            break;
            
        case 'vencidas':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
                       a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'pendiente') as entregas_pendientes,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado') as entregas_calificadas,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id) as total_entregas
                FROM actividades a
                WHERE a.tutor_id = :tutor_id
                  AND (
                    a.fecha_limite < CURDATE()
                    OR (a.fecha_limite = CURDATE() AND a.hora_limite < CURTIME())
                  )
                ORDER BY a.fecha_limite DESC, a.hora_limite DESC
            ");
            break;
            
        case 'calificadas':
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
                       a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'pendiente') as entregas_pendientes,
                       (SELECT AVG(calificacion) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado') as calificacion_promedio,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado') as entregas_calificadas
                FROM actividades a
                WHERE a.tutor_id = :tutor_id 
                AND EXISTS (SELECT 1 FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado')
                ORDER BY a.fecha_limite DESC, a.hora_limite DESC
            ");
            break;
            
        default: // todas
            $stmt = $db->prepare("
                SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
                       a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'pendiente') as entregas_pendientes,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id AND estado = 'calificado') as entregas_calificadas,
                       (SELECT COUNT(*) FROM entregas_actividad WHERE id_actividad = a.id) as total_entregas
                FROM actividades a
                WHERE a.tutor_id = :tutor_id
                ORDER BY a.fecha_limite DESC, a.hora_limite DESC
            ");
    }
    
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $actividades = [
        [
            'id' => 1,
            'titulo' => 'Diseño de Base de Datos',
            'descripcion' => 'Crear un diagrama ER para un sistema de gestión de biblioteca',
            'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
            'hora_limite' => '23:59:00',
            'tipo' => 'tarea',
            'puntaje' => 5.0,
            'permitir_entregas_tarde' => 1,
            'fecha_creacion' => date('Y-m-d', strtotime('-5 days')),
            'entregas_pendientes' => 2
        ],
        [
            'id' => 2,
            'titulo' => 'Consultas SQL Básicas',
            'descripcion' => 'Realizar consultas SELECT con filtros y ordenamiento',
            'fecha_limite' => date('Y-m-d', strtotime('+5 days')),
            'hora_limite' => '23:59:00',
            'tipo' => 'tarea',
            'puntaje' => 4.0,
            'permitir_entregas_tarde' => 0,
            'fecha_creacion' => date('Y-m-d', strtotime('-3 days')),
            'entregas_pendientes' => 0
        ],
        [
            'id' => 3,
            'titulo' => 'Normalización',
            'descripcion' => 'Aplicar las formas normales a un esquema de base de datos',
            'fecha_limite' => date('Y-m-d', strtotime('-2 days')),
            'hora_limite' => '23:59:00',
            'tipo' => 'tarea',
            'puntaje' => 4.5,
            'permitir_entregas_tarde' => 1,
            'fecha_creacion' => date('Y-m-d', strtotime('-10 days')),
            'entregas_pendientes' => 3
        ]
    ];
}

// Obtener estadísticas
try {
    // Total de actividades
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM actividades
        WHERE tutor_id = :tutor_id
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $total_actividades = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Entregas pendientes de calificar
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM entregas_actividad ea
        JOIN actividades a ON ea.id_actividad = a.id
        WHERE a.tutor_id = :tutor_id AND ea.estado = 'pendiente'
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $entregas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Actividades vencidas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM actividades
        WHERE tutor_id = :tutor_id
          AND (
            fecha_limite < CURDATE()
            OR (fecha_limite = CURDATE() AND hora_limite < CURTIME())
          )
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $actividades_vencidas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    // Si hay un error, usar datos de ejemplo
    $total_actividades = count($actividades);
    $entregas_pendientes = 5;
    $actividades_vencidas = 1;
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Actividades Tutor</title>
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
            background-color: rgba(0,166,61,0.1);
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
        
        .stat-icon.activities {
            background-color: rgba(0,166,61,0.2);
            color: var(--primary);
        }
        
        .stat-icon.pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        
        .stat-icon.expired {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger);
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
        
        .filter-tabs {
            display: flex;
            background-color: white;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .filter-tab:hover {
            background-color: #f8f9fa;
            text-decoration: none;
            color: var(--primary);
        }
        
        .filter-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .activities-container {
            margin-bottom: 20px;
        }
        
        .activity-card {
            background-color: white;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .activity-card:hover {
            transform: translateY(-5px);
        }
        
        .activity-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-title {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .activity-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .activity-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .activity-body {
            padding: 15px 20px;
        }
        
        .activity-description {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .meta-item i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .activity-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .submissions-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        
        .submissions-badge.has-submissions {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
        }

        .calificacion-resumen {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
        }
        
        .calificacion-badge {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 1rem;
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
            width: 100%;
        }
        
        .entregas-badge {
            background-color: rgba(0,166,61,0.1);
            color: var(--primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            
            .main-content.active {
                margin-left: 250px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-tabs {
                flex-wrap: wrap;
            }
            
            .filter-tab {
                flex: 1 0 50%;
            }
        }


        
    </style>
</head>
<body>
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
        
                    <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Seminario</div>
                </div>
            </div>
        </div>
        
        <ul>
            <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="actividades_tutor.php" class="active"><i class="fas fa-tasks"></i> Actividades</a></li>
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
            <h1>Gestión de Actividades</h1>
            <a href="nueva_actividad.php" class="btn btn-primary">
                <i class="fas fa-plus mr-2"></i>Nueva Actividad
            </a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php if ($_GET['success'] == 1): ?>
                    Actividad creada exitosamente.
                <?php elseif ($_GET['success'] == 2): ?>
                    Actividad actualizada exitosamente.
                <?php elseif ($_GET['success'] == 3): ?>
                    Actividad eliminada exitosamente.
                <?php elseif ($_GET['success'] == 4): ?>
                    Las calificaciones han sido guardadas exitosamente. Las entregas calificadas ya no pueden ser modificadas.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon activities">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_actividades; ?></h3>
                    <p>Actividades Totales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $entregas_pendientes; ?></h3>
                    <p>Entregas por Calificar</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon expired">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $actividades_vencidas; ?></h3>
                    <p>Actividades Vencidas</p>
                </div>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="actividades_tutor.php" class="filter-tab <?php echo $filtro === 'todas' ? 'active' : ''; ?>">
                Todas
            </a>
            <a href="actividades_tutor.php?filtro=pendientes" class="filter-tab <?php echo $filtro === 'pendientes' ? 'active' : ''; ?>">
                Pendientes
            </a>
            <a href="actividades_tutor.php?filtro=vencidas" class="filter-tab <?php echo $filtro === 'vencidas' ? 'active' : ''; ?>">
                Vencidas
            </a>
            <a href="actividades_tutor.php?filtro=calificadas" class="filter-tab <?php echo $filtro === 'calificadas' ? 'active' : ''; ?>">
                Calificadas
            </a>
        </div>
        
        <div class="activities-container">
            <?php if (count($actividades) > 0): ?>
                <?php foreach ($actividades as $actividad): ?>
                    <div class="activity-card">
                        <div class="activity-header">
                            <h3 class="activity-title">
                                <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                                <?php echo htmlspecialchars($actividad['titulo']); ?>
                            </h3>
                            <span class="activity-date">
                                Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?>
                            </span>
                        </div>
                        <div class="activity-body">
                            <div class="activity-description">
                                <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                            </div>
                            <div class="activity-meta">
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Hora límite: <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-star"></i>
                                    <span>Puntaje: <?php echo $actividad['puntaje']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Creada: <?php echo date('d/m/Y', strtotime($actividad['fecha_creacion'])); ?></span>
                                </div>
                                <?php if ($actividad['permitir_entregas_tarde']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>Permite entregas tardías</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="activity-actions">
                                <?php if ($filtro === 'calificadas'): ?>
                                    <div class="calificacion-resumen">
                                        <div class="calificacion-badge">
                                            <i class="fas fa-star mr-1"></i> Calificación promedio: <strong><?php echo number_format($actividad['calificacion_promedio'] ?? 0, 1); ?>/<?php echo $actividad['puntaje']; ?></strong>
                                        </div>
                                        <div class="entregas-badge">
                                            <i class="fas fa-check-circle mr-1"></i> <?php echo $actividad['entregas_calificadas']; ?> entregas calificadas
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <a href="ver_actividad.php?id=<?php echo $actividad['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye mr-1"></i> Ver Detalles
                                        </a>
                                        
                                        <?php
                                        // Verificar si hay entregas totales y calificadas para determinar el estado
                                        $total_entregas = isset($actividad['total_entregas']) ? $actividad['total_entregas'] : 0;
                                        $entregas_calificadas = isset($actividad['entregas_calificadas']) ? $actividad['entregas_calificadas'] : 0;
                                        $entregas_pendientes = isset($actividad['entregas_pendientes']) ? $actividad['entregas_pendientes'] : 0;
                                        
                                        // Determinar si está calificada o pendiente por entregar
                                        $esta_calificada = ($entregas_calificadas > 0 && $entregas_pendientes == 0);
                                        $pendiente_por_entregar = ($total_entregas == 0);
                                        $tiene_entregas_por_calificar = ($entregas_pendientes > 0);
                                        
                                        // Mostrar botón de editar solo si está pendiente por entregar o no tiene entregas por calificar y no está calificada
                                        if (($pendiente_por_entregar || !$tiene_entregas_por_calificar) && !$esta_calificada): 
                                        ?>
                                            <a href="editar_actividad.php?id=<?php echo $actividad['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-edit mr-1"></i> Editar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($actividad['entregas_pendientes'] > 0): ?>
                                            <a href="calificar_entregas.php?id=<?php echo $actividad['id']; ?>" class="btn btn-success">
                                                <i class="fas fa-check-circle mr-1"></i> Calificar Entregas
                                            </a>
                                        <?php elseif (!$pendiente_por_entregar): // Mostrar "Ver Entregas Calificadas" solo si no está pendiente por entregar ?>
                                            <a href="calificar_entregas.php?id=<?php echo $actividad['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-eye mr-1"></i> Ver Entregas Calificadas
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php
                                        // Verificar si hay entregas totales
                                        $total_entregas = isset($actividad['total_entregas']) ? $actividad['total_entregas'] : 0;
                                        $entregas_calificadas = isset($actividad['entregas_calificadas']) ? $actividad['entregas_calificadas'] : 0;

                                        // Si no hay entregas totales, está pendiente por entregar
                                        if ($total_entregas == 0): ?>
                                            <span class="submissions-badge" style="background-color:#ffc107;color:#856404;">
                                                <i class="fas fa-hourglass-start mr-1"></i> Pendiente por entregar
                                            </span>
                                        <?php 
                                        // Si hay entregas pendientes por calificar
                                        elseif ($actividad['entregas_pendientes'] > 0): ?>
                                            <span class="submissions-badge" style="background-color:#ff9800;color:#fff;">
                                                <i class="fas fa-inbox mr-1"></i> <?php echo $actividad['entregas_pendientes']; ?> entregas por calificar
                                            </span>
                                        <?php 
                                        // Si todas las entregas están calificadas
                                        elseif ($entregas_calificadas > 0): ?>
                                            <span class="submissions-badge has-submissions" style="background-color:rgba(40,167,69,0.2);color:var(--success);">
                                                <i class="fas fa-check-circle mr-1"></i> Calificada
                                            </span>
                                        <?php else: ?>
                                            <span class="submissions-badge" style="background-color:#ffc107;color:#856404;">
                                                <i class="fas fa-hourglass-start mr-1"></i> Pendiente por entregar
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No hay actividades</h3>
                    <p>Comienza creando una nueva actividad para tus estudiantes.</p>
                    <a href="nueva_actividad.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Nueva Actividad
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto-ocultar alertas después de 5 segundos
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);

        // Reemplazar la validación de calificación existente con esta nueva versión
        document.querySelectorAll('.calificacion-input-field').forEach(function(input) {
            // Obtener el puntaje máximo para esta actividad
            const puntajeMaximo = parseFloat(input.closest('.entrega-card').dataset.puntajeMaximo || 5.0);
            
            // Validar al escribir
            input.addEventListener('input', function(e) {
                // Permitir solo números, punto y coma
                let value = this.value;
                
                // Eliminar caracteres no válidos
                value = value.replace(/[^\d.,]/g, '');
                
                // Reemplazar comas por puntos
                value = value.replace(',', '.');
                
                // Limitar a un solo punto decimal
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // Validar que el primer dígito no exceda el puntaje máximo
                if (value.length > 0) {
                    const firstDigit = parseInt(value[0]);
                    if (isNaN(firstDigit) || firstDigit > puntajeMaximo) {
                        value = puntajeMaximo + value.substring(1);
                    }
                }
                
                // Limitar a 3 caracteres (X.X) o 4 si el puntaje máximo es de dos dígitos (XX.X)
                const maxLength = puntajeMaximo >= 10 ? 4 : 3;
                if (value.length > maxLength) {
                    value = value.substring(0, maxLength);
                }
                
                // Actualizar el valor
                this.value = value;
                
                // Validar el rango
                const numValue = parseFloat(value);
                if (value !== '' && (isNaN(numValue) || numValue < 0 || numValue > puntajeMaximo)) {
                    this.classList.add('is-invalid');
                    
                    // Crear mensaje de error si no existe
                    let errorDiv = this.parentNode.querySelector('.invalid-feedback');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Ingresa un valor entre 0.0 y ' + puntajeMaximo;
                        this.parentNode.appendChild(errorDiv);
                    }
                } else {
                    this.classList.remove('is-invalid');
                    if (value !== '') {
                        this.classList.add('is-valid');
                    }
                    
                    // Eliminar mensaje de error si existe
                    let errorDiv = this.parentNode.querySelector('.invalid-feedback');
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                }
            });
            
            // Formatear al perder el foco
            input.addEventListener('blur', function() {
                // Reemplazar comas por puntos
                let value = this.value.replace(',', '.');
                
                // Si está vacío, no hacer nada
                if (value === '') return;
                
                const numValue = parseFloat(value);
                if (!isNaN(numValue) && numValue >= 0 && numValue <= puntajeMaximo) {
                    // Formatear a 1 decimal
                    this.value = numValue.toFixed(1);
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.add('is-invalid');
                }
            });
        });

        // Modificar la validación del formulario
        document.getElementById('form-calificaciones')?.addEventListener('submit', function(e) {
            let inputs = document.querySelectorAll('.calificacion-input-field:not([disabled])');
            let valid = true;
            let errorMessages = [];
            
            inputs.forEach(function(input) {
                // Obtener el puntaje máximo para esta actividad
                const puntajeMaximo = parseFloat(input.closest('.entrega-card').dataset.puntajeMaximo || 5.0);
                
                // Reemplazar comas por puntos
                input.value = input.value.replace(',', '.');
                
                // Validar que no esté vacío
                if (input.value.trim() === '') {
                    valid = false;
                    input.classList.add('is-invalid');
                    errorMessages.push('Hay campos de calificación vacíos.');
                    return;
                }
                
                // Validar que sea un número
                let value = parseFloat(input.value);
                if (isNaN(value)) {
                    valid = false;
                    input.classList.add('is-invalid');
                    errorMessages.push('Hay calificaciones que no son números válidos.');
                    return;
                }
                
                // Validar el rango
                if (value < 0 || value > puntajeMaximo) {
                    valid = false;
                    input.classList.add('is-invalid');
                    errorMessages.push('Las calificaciones deben estar entre 0.0 y ' + puntajeMaximo + '.');
                    return;
                }
                
                // Formatear a 1 decimal
                input.value = value.toFixed(1);
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Por favor, corrige los siguientes errores:\n' + errorMessages.join('\n'));
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
