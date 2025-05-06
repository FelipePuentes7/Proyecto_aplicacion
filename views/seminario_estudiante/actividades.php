<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    // Redirigir a la página de inicio de sesión
    header('Location: /login.php');
    exit;
}

// Incluir la configuración de la base de datos
require_once '../../config/database.php';

// Crear conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener el ID del estudiante
$estudiante_id = $_SESSION['estudiante_id'];

// Determinar el filtro de estado
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'pendientes';

// Consulta SQL base
$sql_base = "
    SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, 
           CONCAT(a.fecha_limite, ' ', a.hora_limite) AS fecha_completa,
           c.nombre AS curso, ea.id AS entrega_id, ea.estado, ea.calificacion
    FROM actividad_seminario a
    JOIN curso_seminario c ON a.curso_id = c.id
    JOIN curso_estudiante_seminario ce ON c.id = ce.curso_id AND ce.estudiante_id = :estudiante_id
    LEFT JOIN entrega_actividad_seminario ea ON a.id = ea.actividad_id AND ea.estudiante_id = :estudiante_id
";

// Aplicar filtro
switch ($filtro) {
    case 'pendientes':
        $sql = $sql_base . " WHERE (ea.id IS NULL OR ea.estado = 'devuelto') AND CONCAT(a.fecha_limite, ' ', a.hora_limite) >= NOW()";
        break;
    case 'entregadas':
        $sql = $sql_base . " WHERE ea.id IS NOT NULL AND ea.estado = 'pendiente'";
        break;
    case 'calificadas':
        $sql = $sql_base . " WHERE ea.id IS NOT NULL AND ea.estado = 'calificado'";
        break;
    default: // todas
        $sql = $sql_base;
        break;
}

// Ordenar por fecha límite
$sql .= " ORDER BY fecha_completa";

// Preparar y ejecutar la consulta
$stmt = $db->prepare($sql);
$stmt->bindParam(':estudiante_id', $estudiante_id);
$stmt->execute();
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para calcular tiempo restante
function calcularTiempoRestante($fecha_limite, $hora_limite) {
    $fecha_completa = $fecha_limite . ' ' . $hora_limite;
    $timestamp_limite = strtotime($fecha_completa);
    $timestamp_actual = time();
    
    // Si ya pasó la fecha límite
    if ($timestamp_actual > $timestamp_limite) {
        return [
            'expirado' => true,
            'dias' => 0,
            'horas' => 0,
            'minutos' => 0
        ];
    }
    
    $segundos_restantes = $timestamp_limite - $timestamp_actual;
    $dias = floor($segundos_restantes / (60 * 60 * 24));
    $horas = floor(($segundos_restantes % (60 * 60 * 24)) / (60 * 60));
    $minutos = floor(($segundos_restantes % (60 * 60)) / 60);
    
    return [
        'expirado' => false,
        'dias' => $dias,
        'horas' => $horas,
        'minutos' => $minutos
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades - FET</title>
    <link rel="stylesheet" href="../../assets/css/actividades.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<header class="bg-green-600 text-white p-4 flex justify-between items-center">
    <div class="flex items-center">
        <img alt="FET Logo" src="../../assets/img/logofet.png" class="h-12" height="100" width="100"/>
    </div>
    <nav class="flex space-x-4">
        <a class="hover:underline" href="inicio_estudiantes.php">Inicio</a>
        <a class="hover:underline" href="actividades.php">Actividades</a>
        <a class="hover:underline" href="aula_virtual.php">Aula Virtual</a>
        <a class="hover:underline" href="material_apoyo.php">Material de Apoyo</a>
    </nav>
    <div class="flex items-center space-x-4">
        <i class="fas fa-bell"></i>
        <img alt="User Avatar" class="h-10 w-10 rounded-full" height="40"
             src="<?php echo $_SESSION['avatar']; ?>"
             width="40"/>
    </div>
</header>
    <main class="main-content">
        <h1 class="page-title">Gestión de Actividades</h1>
        
        <div class="tabs">
            <a href="?filtro=pendientes" class="tab-button <?php echo $filtro === 'pendientes' ? 'active' : ''; ?>">Pendientes</a>
            <a href="?filtro=entregadas" class="tab-button <?php echo $filtro === 'entregadas' ? 'active' : ''; ?>">Entregadas</a>
            <a href="?filtro=calificadas" class="tab-button <?php echo $filtro === 'calificadas' ? 'active' : ''; ?>">Calificadas</a>
            <a href="?filtro=todas" class="tab-button <?php echo $filtro === 'todas' ? 'active' : ''; ?>">Todas</a>
        </div>

        <div class="activities-list">
            <?php if (count($actividades) > 0): ?>
                <?php foreach ($actividades as $actividad): ?>
                    <?php 
                        $tiempo_restante = calcularTiempoRestante($actividad['fecha_limite'], $actividad['hora_limite']);
                        $clase_tiempo = $tiempo_restante['expirado'] ? 'expired' : ($tiempo_restante['dias'] < 1 ? 'urgent' : '');
                    ?>
                    <div class="activity-card">
                        <div class="activity-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="activity-content">
                            <h3><?php echo htmlspecialchars($actividad['titulo']); ?></h3>
                            <p>Curso: <span><?php echo htmlspecialchars($actividad['curso']); ?></span></p>
                            <p>Fecha límite: <span><?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?> a las <?php echo $actividad['hora_limite']; ?></span></p>
                            
                            <?php if (isset($actividad['estado'])): ?>
                                <p>Estado: 
                                    <span class="status-badge <?php echo $actividad['estado']; ?>">
                                        <?php 
                                            switch($actividad['estado']) {
                                                case 'pendiente':
                                                    echo 'Entregado - Pendiente de calificación';
                                                    break;
                                                case 'calificado':
                                                    echo 'Calificado: ' . $actividad['calificacion'];
                                                    break;
                                                case 'devuelto':
                                                    echo 'Devuelto para correcciones';
                                                    break;
                                                default:
                                                    echo 'Pendiente';
                                            }
                                        ?>
                                    </span>
                                </p>
                            <?php else: ?>
                                <p>Estado: <span class="status-badge pendiente">Pendiente</span></p>
                                
                                <?php if (!$tiempo_restante['expirado']): ?>
                                    <div class="countdown <?php echo $clase_tiempo; ?>">
                                        <p>Tiempo restante:</p>
                                        <div class="countdown-timer">
                                            <div class="countdown-item">
                                                <span class="countdown-value"><?php echo $tiempo_restante['dias']; ?></span>
                                                <span class="countdown-label">días</span>
                                            </div>
                                            <div class="countdown-item">
                                                <span class="countdown-value"><?php echo $tiempo_restante['horas']; ?></span>
                                                <span class="countdown-label">horas</span>
                                            </div>
                                            <div class="countdown-item">
                                                <span class="countdown-value"><?php echo $tiempo_restante['minutos']; ?></span>
                                                <span class="countdown-label">min</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="expired-notice">¡Plazo expirado!</p>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="activity-description">
                                <p><?php echo htmlspecialchars($actividad['descripcion']); ?></p>
                            </div>
                            
                            <?php if (!isset($actividad['entrega_id']) && !$tiempo_restante['expirado']): ?>
                                <a href="entregar_actividad.php?id=<?php echo $actividad['id']; ?>" class="confirm-button">Entregar Actividad</a>
                            <?php elseif (isset($actividad['entrega_id']) && $actividad['estado'] === 'calificado'): ?>
                                <a href="ver_calificacion.php?id=<?php echo $actividad['entrega_id']; ?>" class="view-button">Ver Calificación</a>
                            <?php elseif (isset($actividad['entrega_id']) && $actividad['estado'] === 'pendiente'): ?>
                                <a href="ver_entrega.php?id=<?php echo $actividad['entrega_id']; ?>" class="view-button">Ver Entrega</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-activities">
                    <i class="fas fa-info-circle"></i>
                    <p>No hay actividades <?php echo $filtro !== 'todas' ? 'en esta categoría' : ''; ?> en este momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

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

<script>
    // Actualizar los contadores de tiempo cada minuto
    setInterval(function() {
        const now = new Date().getTime();
        const countdowns = document.querySelectorAll('.countdown-timer');
        
        countdowns.forEach(function(countdown) {
            const parent = countdown.closest('.activity-card');
            const fechaLimite = parent.querySelector('.activity-content p:nth-child(3) span').textContent;
            const [fecha, hora] = fechaLimite.split(' a las ');
            const [dia, mes, anio] = fecha.split('/');
            const [horas, minutos] = hora.split(':');
            
            const fechaCompleta = new Date(anio, mes-1, dia, horas, minutos);
            const tiempoRestante = fechaCompleta.getTime() - now;
            
            if (tiempoRestante <= 0) {
                // Recargar la página para actualizar el estado
                location.reload();
                return;
            }
            
            const dias = Math.floor(tiempoRestante / (1000 * 60 * 60 * 24));
            const horasRestantes = Math.floor((tiempoRestante % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutosRestantes = Math.floor((tiempoRestante % (1000 * 60 * 60)) / (1000 * 60));
            
            countdown.querySelector('.countdown-item:nth-child(1) .countdown-value').textContent = dias;
            countdown.querySelector('.countdown-item:nth-child(2) .countdown-value').textContent = horasRestantes;
            countdown.querySelector('.countdown-item:nth-child(3) .countdown-value').textContent = minutosRestantes;
            
            // Actualizar clases de urgencia
            const countdownContainer = countdown.closest('.countdown');
            if (dias < 1) {
                countdownContainer.classList.add('urgent');
            }
        });
    }, 60000); // Actualizar cada minuto
</script>
</body>
</html>