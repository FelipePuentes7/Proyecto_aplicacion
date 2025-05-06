<?php
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
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        });
    </script>
</body>
</html>

## Archivo de Configuración para la Conexión a la Base de Datos

Para completar la integración, aquí está el archivo de configuración actualizado para la conexión a la base de datos:
