<?php
<<<<<<< HEAD
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'tutor') {
    // Redirigir a la página de inicio de sesión
    header('Location: ../../views/general/login.php');
    exit;
}

// Incluir la configuración de la base de datos
require_once '../../config/database.php';

// Crear conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener el ID del tutor
$tutor_id = $_SESSION['tutor_id'];

// Procesar la calificación si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calificar') {
    $entrega_id = $_POST['entrega_id'];
    $calificacion = $_POST['calificacion'];
    $retroalimentacion = $_POST['retroalimentacion'];
    
    try {
        // Usar el procedimiento almacenado para calificar
        $stmt = $db->prepare("CALL calificar_entrega(?, ?, ?)");
        $stmt->bindParam(1, $entrega_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $calificacion, PDO::PARAM_STR);
        $stmt->bindParam(3, $retroalimentacion, PDO::PARAM_STR);
        $stmt->execute();
        
        $_SESSION['mensaje'] = "La entrega ha sido calificada exitosamente.";
        $_SESSION['tipo_mensaje'] = "success";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al calificar la entrega: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
    }
    
    // Redirigir para evitar reenvío del formulario
    header('Location: actividades_tutor.php');
    exit;
}

// Obtener el filtro de estado
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'all';

// Consulta para obtener las entregas de actividades
$sql = "
    SELECT ea.id AS entrega_id, a.id AS actividad_id, a.titulo, 
           CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.avatar,
           ea.fecha_entrega, ea.estado, ea.calificacion,
           a.fecha_limite, a.hora_limite
    FROM entrega_actividad_seminario ea
    JOIN actividad_seminario a ON ea.actividad_id = a.id
    JOIN estudiante_seminario e ON ea.estudiante_id = e.id
    JOIN usuarios_seminario u ON e.usuario_id = u.id
    WHERE a.tutor_id = :tutor_id
";

// Aplicar filtro
if ($filtro === 'pending') {
    $sql .= " AND ea.estado = 'pendiente'";
} elseif ($filtro === 'graded') {
    $sql .= " AND ea.estado = 'calificado'";
}

// Ordenar por fecha de entrega (más recientes primero)
$sql .= " ORDER BY ea.fecha_entrega DESC";

// Preparar y ejecutar la consulta
$stmt = $db->prepare($sql);
$stmt->bindParam(':tutor_id', $tutor_id);
$stmt->execute();
$workshops = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta para obtener las próximas clases
$sql_clases = "
    SELECT c.id, c.titulo, c.descripcion, c.fecha, c.hora, c.duracion, c.plataforma, c.enlace
    FROM clase_seminario c
    WHERE c.tutor_id = :tutor_id
    AND c.fecha >= CURDATE()
    ORDER BY c.fecha, c.hora
    LIMIT 5
";

$stmt_clases = $db->prepare($sql_clases);
$stmt_clases->bindParam(':tutor_id', $tutor_id);
$stmt_clases->execute();
$upcomingClasses = $stmt_clases->fetchAll(PDO::FETCH_ASSOC);

// Función para generar el badge de estado
function getStatusBadge($status, $grade = null) {
    $class = '';
    $text = $status;
    
    switch($status) {
        case 'pendiente':
            $class = 'status-pending';
            break;
        case 'calificado':
            $class = 'status-completed';
            $text = "Calificado: $grade";
            break;
        default:
            $class = 'status-default';
    }
    
    return "<span class='status-badge $class'>$text</span>";
}

// Función para calcular tiempo restante
function getTimeRemaining($deadline_date, $deadline_time) {
    $deadline = $deadline_date . ' ' . $deadline_time;
    $deadline_timestamp = strtotime($deadline);
    $current_timestamp = time();
    $remaining_seconds = $deadline_timestamp - $current_timestamp;
    
    if ($remaining_seconds <= 0) {
        return [
            'expired' => true,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0
        ];
    }
    
    $days = floor($remaining_seconds / (60 * 60 * 24));
    $hours = floor(($remaining_seconds % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($remaining_seconds % (60 * 60)) / 60);
    $seconds = $remaining_seconds % 60;
    
    return [
        'expired' => false,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'timestamp' => $deadline_timestamp
    ];
}

// Función para obtener icono de plataforma
function getPlatformIcon($platform) {
    $icons = [
        'meet' => 'fas fa-video',
        'classroom' => 'fas fa-chalkboard',
        'zoom' => 'fas fa-video',
        'teams' => 'fas fa-users'
    ];
    
    return $icons[$platform] ?? 'fas fa-globe';
}

// Función para formatear fecha en español (CORREGIDA)
function formatDateSpanish($date) {
    $timestamp = strtotime($date);
    $months = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day de $month de $year";
=======
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
>>>>>>> origin/Master
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>FET - Actividades del Tutor</title>
    <link rel="stylesheet" href="/assets/css/tutor_css/actividades_tutor.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1>FET</h1>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li class="active"><a href="actividades_tutor.php"><i class="fas fa-book"></i> Actividades</a></li>
                    <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
                    <li><a href="material_tutor.php"><i class="fas fa-file-alt"></i> Material de Apoyo</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h2>Revisión de Actividades</h2>
                <div class="header-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>
            
            <section class="content-section">
                <!-- Quick Actions -->
                <div class="quick-actions-container">
                    <div class="action-card" id="add-task-btn">
                        <div class="action-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="action-text">
                            <h4>Añadir Tarea</h4>
                            <p>Crear nueva actividad</p>
                        </div>
                    </div>
                    <div class="action-card" id="review-activities-btn">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h4>Revisar Actividades</h4>
                            <p>Calificar entregas</p>
                        </div>
                    </div>
                    <div class="action-card" onclick="window.location.href='clase_tutor.php'">
                        <div class="action-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="action-text">
                            <h4>Subir Clases</h4>
                            <p>Gestionar aula virtual</p>
                        </div>
                    </div>
                    <div class="action-card" onclick="window.location.href='material_tutor.php'">
                        <div class="action-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div class="action-text">
                            <h4>Subir Material</h4>
                            <p>Compartir recursos</p>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Classes -->
                <div class="card upcoming-classes">
                    <div class="card-header">
                        <h4>Próximas Clases</h4>
                        <button class="add-btn" id="add-class-btn">
                            <i class="fas fa-plus"></i> Nueva Clase
                        </button>
                    </div>
                    <div class="classes-container">
                        <?php if (count($upcomingClasses) > 0): ?>
                            <?php foreach ($upcomingClasses as $class): ?>
                                <div class="class-card">
                                    <div class="class-header">
                                        <div class="class-platform-icon <?php echo $class['plataforma']; ?>">
                                            <i class="<?php echo getPlatformIcon($class['plataforma']); ?>"></i>
                                        </div>
                                        <div class="class-title"><?php echo htmlspecialchars($class['titulo']); ?></div>
                                    </div>
                                    <div class="class-details">
                                        <div class="class-date">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo formatDateSpanish($class['fecha']); ?>
                                        </div>
                                        <div class="class-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($class['hora']); ?> 
                                            (<?php echo htmlspecialchars($class['duracion']); ?> min)
                                        </div>
                                    </div>
                                    <div class="class-actions">
                                        <a href="<?php echo htmlspecialchars($class['enlace']); ?>" target="_blank" class="join-class-btn">
                                            <i class="<?php echo getPlatformIcon($class['plataforma']); ?>"></i>
                                            Unirse a la Clase
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-classes">
                                <p>No hay clases programadas próximamente.</p>
                                <button class="btn-primary" id="schedule-class-btn">Programar una clase</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Workshops List -->
                <div class="card workshops-list">
                    <div class="card-header">
                        <h4>Talleres Enviados</h4>
                        <div class="filter-actions">
                            <select class="filter-select" id="status-filter" onchange="window.location.href='?filtro='+this.value">
                                <option value="all" <?php echo $filtro === 'all' ? 'selected' : ''; ?>>Todos los talleres</option>
                                <option value="pending" <?php echo $filtro === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="graded" <?php echo $filtro === 'graded' ? 'selected' : ''; ?>>Calificados</option>
                            </select>
                            <div class="search-box">
                                <input type="text" id="search-input" placeholder="Buscar taller...">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Taller</th>
                                    <th>Estudiante</th>
                                    <th>Fecha de Envío</th>
                                    <th>Estado</th>
                                    <th>Tiempo Restante</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($workshops) > 0): ?>
                                    <?php foreach($workshops as $workshop): ?>
                                    <?php $timeRemaining = getTimeRemaining($workshop['fecha_limite'], $workshop['hora_limite']); ?>
                                    <tr data-status="<?php echo $workshop['estado']; ?>">
                                        <td><?php echo htmlspecialchars($workshop['titulo']); ?></td>
                                        <td>
                                            <div class="student-info">
                                                <img src="<?php echo $workshop['avatar']; ?>" alt="<?php echo $workshop['estudiante']; ?>" class="student-avatar">
                                                <span><?php echo htmlspecialchars($workshop['estudiante']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($workshop['fecha_entrega'])); ?></td>
                                        <td><?php echo getStatusBadge($workshop['estado'], isset($workshop['calificacion']) ? $workshop['calificacion'] : null); ?></td>
                                        <td>
                                            <div class="countdown-timer <?php echo $timeRemaining['expired'] ? 'expired' : ''; ?>" data-deadline="<?php echo $timeRemaining['timestamp']; ?>">
                                                <?php if ($timeRemaining['expired']): ?>
                                                    <span class="deadline-expired">Plazo expirado</span>
                                                <?php else: ?>
                                                    <div class="countdown-item">
                                                        <span class="countdown-value days"><?php echo $timeRemaining['days']; ?></span>
                                                        <span class="countdown-label">d</span>
                                                    </div>
                                                    <div class="countdown-item">
                                                        <span class="countdown-value hours"><?php echo $timeRemaining['hours']; ?></span>
                                                        <span class="countdown-label">h</span>
                                                    </div>
                                                    <div class="countdown-item">
                                                        <span class="countdown-value minutes"><?php echo $timeRemaining['minutes']; ?></span>
                                                        <span class="countdown-label">m</span>
                                                    </div>
                                                    <div class="countdown-item">
                                                        <span class="countdown-value seconds"><?php echo $timeRemaining['seconds']; ?></span>
                                                        <span class="countdown-label">s</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="ver_entrega.php?id=<?php echo $workshop['entrega_id']; ?>" class="action-btn view-btn" title="Ver taller">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($workshop['estado'] === 'pendiente'): ?>
                                                <button class="action-btn grade-btn" title="Calificar" data-id="<?php echo $workshop['entrega_id']; ?>" data-title="<?php echo htmlspecialchars($workshop['titulo']); ?>" data-student="<?php echo htmlspecialchars($workshop['estudiante']); ?>">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                                <?php endif; ?>
                                                <a href="comentar_entrega.php?id=<?php echo $workshop['entrega_id']; ?>" class="action-btn comment-btn" title="Comentarios">
                                                    <i class="fas fa-comment"></i>
                                                </a>
                                                <a href="descargar_entrega.php?id=<?php echo $workshop['entrega_id']; ?>" class="action-btn download-btn" title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No hay entregas disponibles</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal para calificar taller -->
    <div id="grade-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Calificar Taller</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="actividades_tutor.php" method="post" class="grade-form">
                    <input type="hidden" name="action" value="calificar">
                    <input type="hidden" name="entrega_id" id="entrega_id">
                    
                    <div class="form-group">
                        <label>Taller: <span id="taller-titulo"></span></label>
                    </div>
                    
                    <div class="form-group">
                        <label>Estudiante: <span id="estudiante-nombre"></span></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="calificacion">Calificación (0-5):</label>
                        <input type="number" id="calificacion" name="calificacion" min="0" max="5" step="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="retroalimentacion">Comentarios:</label>
                        <textarea id="retroalimentacion" name="retroalimentacion" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary cancel-modal">Cancelar</button>
                        <button type="submit" class="btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para añadir nueva clase -->
    <div class="modal" id="add-class-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Programar Nueva Clase</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="programar_clase.php" method="post" id="add-class-form">
                    <div class="form-group">
                        <label for="class-title">Título de la Clase <span class="required">*</span></label>
                        <input type="text" id="class-title" name="class_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="class-description">Descripción</label>
                        <textarea id="class-description" name="class_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class-date">Fecha <span class="required">*</span></label>
                            <input type="date" id="class-date" name="class_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="class-time">Hora <span class="required">*</span></label>
                            <input type="time" id="class-time" name="class_time" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class-duration">Duración (minutos) <span class="required">*</span></label>
                            <input type="number" id="class-duration" name="class_duration" min="15" step="5" value="60" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="class-platform">Plataforma <span class="required">*</span></label>
                            <select id="class-platform" name="class_platform" required>
                                <option value="">Seleccionar plataforma</option>
                                <option value="meet">Google Meet</option>
                                <option value="classroom">Google Classroom</option>
                                <option value="zoom">Zoom</option>
                                <option value="teams">Microsoft Teams</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="class-link">Enlace de la Clase <span class="required">*</span></label>
                        <input type="url" id="class-link" name="class_link" placeholder="https://" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary cancel-modal">Cancelar</button>
                        <button type="submit" class="btn-primary">Programar Clase</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notificación de éxito -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="notification <?php echo $_SESSION['tipo_mensaje']; ?>-notification">
            <div class="notification-icon">
                <i class="fas <?php echo $_SESSION['tipo_mensaje'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">
                    <?php echo $_SESSION['tipo_mensaje'] === 'success' ? '¡Operación exitosa!' : 'Error'; ?>
                </div>
                <div class="notification-message"><?php echo $_SESSION['mensaje']; ?></div>
            </div>
            <button class="close-notification">&times;</button>
        </div>
        <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal de calificación
            const gradeModal = document.getElementById('grade-modal');
            const addClassModal = document.getElementById('add-class-modal');
            const gradeButtons = document.querySelectorAll('.grade-btn');
            const addClassBtn = document.getElementById('add-class-btn');
            const scheduleClassBtn = document.getElementById('schedule-class-btn');
            const closeButtons = document.querySelectorAll('.close-modal, .cancel-modal');
            
            // Abrir modal de calificación
            gradeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const entregaId = this.getAttribute('data-id');
                    const titulo = this.getAttribute('data-title');
                    const estudiante = this.getAttribute('data-student');
                    
                    document.getElementById('entrega_id').value = entregaId;
                    document.getElementById('taller-titulo').textContent = titulo;
                    document.getElementById('estudiante-nombre').textContent = estudiante;
                    
                    gradeModal.classList.add('active');
                });
            });
            
            // Abrir modal de clase
            if (addClassBtn) {
                addClassBtn.addEventListener('click', function() {
                    addClassModal.classList.add('active');
                });
            }
            
            if (scheduleClassBtn) {
                scheduleClassBtn.addEventListener('click', function() {
                    addClassModal.classList.add('active');
                });
            }
            
            // Cerrar modales
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    gradeModal.classList.remove('active');
                    addClassModal.classList.remove('active');
                });
            });
            
            // Cerrar modal al hacer clic fuera
            window.addEventListener('click', function(event) {
                if (event.target === gradeModal) {
                    gradeModal.classList.remove('active');
                }
                if (event.target === addClassModal) {
                    addClassModal.classList.remove('active');
                }
            });
            
            // Countdown timers
            const countdownTimers = document.querySelectorAll('.countdown-timer');
            
            countdownTimers.forEach(timer => {
                if (timer.classList.contains('expired')) return;
                
                const deadlineTimestamp = parseInt(timer.dataset.deadline);
                const daysElement = timer.querySelector('.countdown-value.days');
                const hoursElement = timer.querySelector('.countdown-value.hours');
                const minutesElement = timer.querySelector('.countdown-value.minutes');
                const secondsElement = timer.querySelector('.countdown-value.seconds');
                
                // Update the countdown every second
                const timerInterval = setInterval(function() {
                    const now = Math.floor(Date.now() / 1000);
                    const remainingSeconds = deadlineTimestamp - now;
                    
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        timer.classList.add('expired');
                        timer.innerHTML = '<span class="deadline-expired">Plazo expirado</span>';
                        return;
                    }
                    
                    const days = Math.floor(remainingSeconds / (60 * 60 * 24));
                    const hours = Math.floor((remainingSeconds % (60 * 60 * 24)) / (60 * 60));
                    const minutes = Math.floor((remainingSeconds % (60 * 60)) / 60);
                    const seconds = remainingSeconds % 60;
                    
                    daysElement.textContent = days;
                    hoursElement.textContent = hours;
                    minutesElement.textContent = minutes;
                    secondsElement.textContent = seconds;
                    
                    // Add warning class when less than 24 hours remaining
                    if (remainingSeconds < 60 * 60 * 24) {
                        timer.classList.add('warning');
                    }
                    
                    // Add critical class when less than 1 hour remaining
                    if (remainingSeconds < 60 * 60) {
                        timer.classList.add('critical');
                    }
                }, 1000);
            });
            
            // Filtrado de tabla
            const searchInput = document.getElementById('search-input');
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                tableRows.forEach(row => {
                    const title = row.querySelector('td:first-child').textContent.toLowerCase();
                    const student = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (title.includes(searchTerm) || student.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Cerrar notificación
            const closeNotificationBtn = document.querySelector('.close-notification');
            if (closeNotificationBtn) {
                closeNotificationBtn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
                
                // Auto-ocultar notificación después de 5 segundos
                setTimeout(function() {
                    document.querySelector('.notification').style.display = 'none';
                }, 5000);
            }
            
            // Toggle del menú en móvil
            const menuToggle = document.createElement('button');
            menuToggle.classList.add('menu-toggle');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.querySelector('.main-header').prepend(menuToggle);
            
            menuToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });
            
            // Cerrar sidebar al hacer clic fuera en móvil
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const menuToggle = document.querySelector('.menu-toggle');
                
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Botón de añadir tarea
            document.getElementById('add-task-btn').addEventListener('click', function() {
                window.location.href = 'nueva_actividad.php';
            });
            
            // Botón de revisar actividades
            document.getElementById('review-activities-btn').addEventListener('click', function() {
                // Ya estamos en la página de actividades, así que solo hacemos scroll a la tabla
                document.querySelector('.workshops-list').scrollIntoView({
                    behavior: 'smooth'
                });
            });
            
            // Set min date for date inputs to today
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.min = today;
            });
=======
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
>>>>>>> origin/Master
        });
    </script>
</body>
</html>
<<<<<<< HEAD

## Archivo de Configuración para la Conexión a la Base de Datos

Para completar la integración, aquí está el archivo de configuración actualizado para la conexión a la base de datos:
=======
>>>>>>> origin/Master
