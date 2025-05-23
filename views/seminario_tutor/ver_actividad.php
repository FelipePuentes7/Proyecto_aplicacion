<?php
// Incluir archivo de conexión a la base de datos
require_once '../../config/conexion.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: actividades_tutor.php');
    exit();
}

$actividad_id = $_GET['id'];
$tutor_id = 1; // En un sistema real, esto vendría de la sesión

// Obtener detalles de la actividad
try {
    $conexion = new Conexion();
    $db = $conexion->getConexion();
    
    // Consultar la actividad
    $stmt = $db->prepare("
        SELECT * FROM actividades 
        WHERE id = :id AND tutor_id = :tutor_id
    ");
    $stmt->bindParam(':id', $actividad_id);
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        // Si no se encuentra la actividad, redirigir
        header('Location: actividades_tutor.php');
        exit();
    }
    
    // Obtener archivos adjuntos
    $stmt = $db->prepare("
        SELECT * FROM archivos_actividad 
        WHERE id_actividad = :id_actividad
    ");
    $stmt->bindParam(':id_actividad', $actividad_id);
    $stmt->execute();
    
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener entregas de estudiantes
    $stmt = $db->prepare("
        SELECT ea.*, u.nombre as estudiante_nombre, u.avatar as estudiante_avatar
        FROM entregas_actividad ea
        JOIN usuarios u ON ea.id_estudiante = u.id
        WHERE ea.id_actividad = :id_actividad
        ORDER BY ea.fecha_entrega DESC
    ");
    $stmt->bindParam(':id_actividad', $actividad_id);
    $stmt->execute();
    
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al cargar los detalles de la actividad: " . $e->getMessage();
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

// Función para formatear tamaño de archivo
function formatearTamano($tamano) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($tamano >= 1024 && $i < count($unidades) - 1) {
        $tamano /= 1024;
        $i++;
    }
    return round($tamano, 2) . ' ' . $unidades[$i];
}

// Función para obtener icono según tipo de archivo
function obtenerIcono($tipo) {
    switch (strtolower($tipo)) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fa-file-image';
        case 'zip':
        case 'rar':
            return 'fa-file-archive';
        default:
            return 'fa-file';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Actividad - Panel de Tutor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary: #039708;
            --primary-light: #039708;
            --secondary: #f8f9fa;
        }

        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
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
            background-color: rgba(255, 255, 255, 0.1);
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
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
        }

        .btn-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }

        .btn-primary:hover {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
            color: #fff !important;
        }

        .back-button {
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .back-button:hover {
            color: var(--primary-light);
            text-decoration: none;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .activity-title {
            margin: 0;
            color: var(--primary);
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
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

        .activity-description {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }

        .file-item:hover {
            background-color: #f8f9fa;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            background-color: #f1f1f1;
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

        .file-actions a {
            color: var(--primary);
            margin-left: 10px;
        }

        .submission-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .submission-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .submission-item:last-child {
            border-bottom: none;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .submission-info {
            flex-grow: 1;
        }

        .student-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .submission-date {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .submission-status {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 10px;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-graded {
            background-color: rgba(40, 167, 69, 0.2);
            color: #039708;
        }

        .empty-state {
            text-align: center;
            padding: 30px 0;
        }

        .empty-state i {
            font-size: 40px;
            color: #d1d1d1;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="p-4 sidebar-header">
            <h3 style="background: none; box-shadow: none; padding: 0; margin: 0;">
                <img src="/assets/images/logofet.png" alt="FET Logo" style="width: 100px;">
            </h3>
            <div class="tutor-profile" style="margin-top: 15px; display: flex; align-items: center; border-radius: 8px; padding: 10px 12px;">
                <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                    <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
                </div>
                <div style="color: #fff;">

                        <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Seminario</div>
                </div>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="inicio_tutor.php" class="nav-link text-white">
                    <i class="fas fa-home"></i>Inicio
                </a>
            </li>
            <li class="nav-item">
                <a href="actividades_tutor.php" class="nav-link text-white active">
                    <i class="fas fa-tasks mr-2"></i>Actividades
                </a>
            </li>
            <li class="nav-item">
                <a href="clase_tutor.php" class="nav-link text-white">
                    <i class="fas fa-video mr-2"></i>Aula Virtual
                </a>
            </li>
            <li class="nav-item">
                <a href="material_tutor.php" class="nav-link text-white">
                    <i class="fas fa-book mr-2"></i>Material de Apoyo
                </a>
            </li>
        </ul>


            <!-- Botón de cerrar sesión fijo abajo -->
                <a href="/views/general/login.php" class="logout-btn" style="margin-top: auto; padding: 15px 20px; color: rgba(255, 255, 255, 0.8); text-decoration: none; display: flex; align-items: center;">
                    <i class="fas fa-sign-out-alt" style="margin-right: 10px;"></i> Cerrar sesión
                </a>


    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <a href="actividades_tutor.php" class="back-button">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver a Actividades
        </a>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="activity-header">
                <div>
                    <h1 class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></h1>
                    <div class="activity-meta">
                        <span class="meta-item">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($actividad['tipo']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-calendar-alt"></i> Creada el <?php echo date('d/m/Y', strtotime($actividad['fecha_creacion'])); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?> a las <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?>
                        </span>
                        <?php if (isset($actividad['puntaje'])): ?>
                            <span class="meta-item">
                                <i class="fas fa-star"></i> Puntaje máximo: <?php echo $actividad['puntaje']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <a href="editar_actividad.php?id=<?php echo $actividad_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit mr-2"></i>Editar Actividad
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Descripción</h5>
                </div>
                <div class="card-body">
                    <div class="activity-description">
                        <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                    </div>
                    
                    <?php if (isset($actividad['permitir_entregas_tarde']) && $actividad['permitir_entregas_tarde']): ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle mr-2"></i>
                            Esta actividad permite entregas después de la fecha límite (con posible penalización).
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Archivos Adjuntos</h5>
                </div>
                <div class="card-body">
                    <?php if (count($archivos) > 0): ?>
                        <ul class="file-list">
                            <?php foreach ($archivos as $archivo): ?>
                                <li class="file-item">
                                    <div class="file-icon">
                                        <i class="fas <?php echo obtenerIcono($archivo['tipo_archivo']); ?>"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></div>
                                        <div class="file-meta">
                                            <?php echo strtoupper($archivo['tipo_archivo']); ?> · 
                                            <?php echo formatearTamano($archivo['tamano_archivo']); ?> · 
                                            Subido el <?php echo date('d/m/Y', strtotime($archivo['fecha_subida'])); ?>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="<?php echo $archivo['ruta_archivo']; ?>" download title="Descargar">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>No hay archivos adjuntos para esta actividad.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Entregas de Estudiantes</h5>
                </div>
                <div class="card-body">
                    <?php if (count($entregas) > 0): ?>
                        <ul class="submission-list">
                            <?php foreach ($entregas as $entrega): ?>
                                <li class="submission-item">
                                    <img src="<?php echo $entrega['estudiante_avatar'] ?: '/assets/img/default-avatar.png'; ?>" alt="Avatar" class="student-avatar">
                                    <div class="submission-info">
                                        <h5 class="student-name"><?php echo htmlspecialchars($entrega['estudiante_nombre']); ?></h5>
                                        <div class="submission-date">
                                            Entregado el <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($entrega['estado'] == 'pendiente'): ?>
                                        <span class="submission-status status-pending">Pendiente</span>
                                    <?php else: ?>
                                        <span class="submission-status status-graded">Calificado: <?php echo $entrega['calificacion']; ?></span>
                                    <?php endif; ?>
                                    
                                        <a href="calificar_entregas.php?id=<?php echo $actividad['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye mr-1"></i> Ver
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Aún no hay entregas para esta actividad.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
