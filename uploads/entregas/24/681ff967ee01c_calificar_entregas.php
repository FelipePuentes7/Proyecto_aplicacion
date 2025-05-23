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

// Obtener ID de la actividad
$actividad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar si se ha enviado el formulario para calificar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar'])) {
    foreach ($_POST['calificaciones'] as $entrega_id => $datos) {
        $calificacion = isset($datos['calificacion']) ? floatval($datos['calificacion']) : null;
        $comentario = isset($datos['comentario']) ? trim($datos['comentario']) : '';
        
        // Validar calificación
        if ($calificacion !== null && $calificacion >= 0 && $calificacion <= 5) {
            try {
                $stmt = $db->prepare("
                    UPDATE entregas_actividad 
                    SET calificacion = :calificacion, 
                        comentario_tutor = :comentario,
                        estado = 'calificado',
                        fecha_calificacion = NOW()
                    WHERE id = :entrega_id
                ");
                
                $stmt->bindParam(':calificacion', $calificacion);
                $stmt->bindParam(':comentario', $comentario);
                $stmt->bindParam(':entrega_id', $entrega_id);
                
                $stmt->execute();
            } catch (PDOException $e) {
                $error_mensaje = "Error al guardar la calificación: " . $e->getMessage();
            }
        }
    }
    
    // Redirigir después de calificar
    header("Location: actividades_tutor.php?success=4");
    exit();
}

// Obtener información de la actividad
try {
    $stmt = $db->prepare("
        SELECT a.id, a.titulo, a.descripcion, a.fecha_limite, a.hora_limite, a.tipo,
               a.puntaje, a.permitir_entregas_tarde, a.fecha_creacion
        FROM actividades a
        WHERE a.id = :actividad_id AND a.tutor_id = :tutor_id
    ");
    $stmt->bindParam(':actividad_id', $actividad_id);
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        // Si no se encuentra la actividad
        header("Location: actividades_tutor.php?error=1");
        exit();
    }
} catch (PDOException $e) {
    $error_mensaje = "Error al cargar la actividad: " . $e->getMessage();
}

// Obtener entregas pendientes de calificación
try {
    $stmt = $db->prepare("
        SELECT e.id, e.fecha_entrega, e.comentario, e.calificacion, e.estado,
               u.nombre as estudiante_nombre, u.apellido as estudiante_apellido,
               u.avatar as estudiante_avatar, est.codigo as estudiante_codigo,
               ae.nombre_archivo, ae.ruta_archivo, ae.tipo_archivo
        FROM entregas_actividad e
        JOIN estudiantes est ON e.id_estudiante = est.id
        JOIN usuarios u ON est.usuario_id = u.id
        LEFT JOIN archivos_entrega ae ON e.id = ae.id_entrega
        WHERE e.id_actividad = :actividad_id AND (e.estado = 'pendiente' OR e.estado IS NULL)
        ORDER BY e.fecha_entrega DESC
    ");
    $stmt->bindParam(':actividad_id', $actividad_id);
    $stmt->execute();
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_mensaje = "Error al cargar las entregas: " . $e->getMessage();
    $entregas = [];
}

// Función para obtener icono según tipo de archivo
function obtenerIconoArchivo($tipo) {
    if (strpos($tipo, 'pdf') !== false) {
        return 'fa-file-pdf';
    } elseif (strpos($tipo, 'word') !== false || strpos($tipo, 'document') !== false) {
        return 'fa-file-word';
    } elseif (strpos($tipo, 'excel') !== false || strpos($tipo, 'sheet') !== false) {
        return 'fa-file-excel';
    } elseif (strpos($tipo, 'powerpoint') !== false || strpos($tipo, 'presentation') !== false) {
        return 'fa-file-powerpoint';
    } elseif (strpos($tipo, 'image') !== false) {
        return 'fa-file-image';
    } elseif (strpos($tipo, 'zip') !== false || strpos($tipo, 'rar') !== false) {
        return 'fa-file-archive';
    } elseif (strpos($tipo, 'text') !== false) {
        return 'fa-file-alt';
    } else {
        return 'fa-file';
    }
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Calificar Entregas</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a3b8b;
            --primary-light: #2a4c9c;
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
        
        .activity-card {
            background-color: white;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .entregas-container {
            margin-top: 30px;
        }
        
        .entrega-card {
            background-color: white;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .entrega-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .estudiante-info {
            display: flex;
            align-items: center;
        }
        
        .estudiante-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .estudiante-datos h4 {
            margin: 0;
            font-size: 1rem;
            color: var(--dark);
        }
        
        .estudiante-datos p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .entrega-fecha {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .entrega-body {
            padding: 15px 20px;
        }
        
        .entrega-comentario {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .entrega-comentario h5 {
            margin-top: 0;
            font-size: 0.9rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .entrega-comentario p {
            margin: 0;
            color: #6c757d;
        }
        
        .entrega-archivo {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        
        .archivo-icon {
            font-size: 1.5rem;
            margin-right: 15px;
            color: var(--primary);
        }
        
        .archivo-info {
            flex-grow: 1;
        }
        
        .archivo-nombre {
            margin: 0;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .archivo-acciones {
            display: flex;
            gap: 10px;
        }
        
        .archivo-acciones a {
            color: #6c757d;
            transition: color 0.3s;
        }
        
        .archivo-acciones a:hover {
            color: var(--primary);
        }
        
        .calificacion-form {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-text {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
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
        
        .empty-message {
            text-align: center;
            padding: 30px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-message i {
            font-size: 3rem;
            color: #ced4da;
            margin-bottom: 15px;
        }
        
        .empty-message h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .empty-message p {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--dark);
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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>
                <i class="fas fa-graduation-cap"></i>
                FET
            </h3>
        </div>
        
        <ul>
            <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="actividades_tutor.php" class="active"><i class="fas fa-tasks"></i> Actividades</a></li>
            <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
            <li><a href="material_tutor.php"><i class="fas fa-book"></i> Material de Apoyo</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="actividades_tutor.php">Actividades</a></li>
                <li class="breadcrumb-item active">Calificar Entregas</li>
            </ol>
        </nav>
        
        <div class="header">
            <h1><i class="fas fa-check-circle mr-2"></i> Calificar Entregas</h1>
            <a href="actividades_tutor.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Volver a Actividades
            </a>
        </div>
        
        <?php if (isset($error_mensaje)): ?>
            <div class="alert alert-danger">
                <?php echo $error_mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="activity-card">
            <div class="activity-header">
                <h3 class="activity-title">
                    <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                    <?php echo htmlspecialchars($actividad['titulo']); ?>
                </h3>
            </div>
            <div class="activity-body">
                <div class="activity-description">
                    <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                </div>
                <div class="activity-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Hora límite: <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-star"></i>
                        <span>Puntaje máximo: <?php echo $actividad['puntaje']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="entregas-container">
            <h2 class="mb-4">Entregas Pendientes de Calificación</h2>
            
            <?php if (count($entregas) > 0): ?>
                <form method="post" action="">
                    <?php foreach ($entregas as $entrega): ?>
                        <div class="entrega-card">
                            <div class="entrega-header">
                                <div class="estudiante-info">
                                    <img src="<?php echo $entrega['estudiante_avatar']; ?>" alt="Avatar" class="estudiante-avatar">
                                    <div class="estudiante-datos">
                                        <h4><?php echo htmlspecialchars($entrega['estudiante_nombre'] . ' ' . $entrega['estudiante_apellido']); ?></h4>
                                        <p>Código: <?php echo htmlspecialchars($entrega['estudiante_codigo']); ?></p>
                                    </div>
                                </div>
                                <div class="entrega-fecha">
                                    <i class="far fa-calendar-alt mr-1"></i> Entregado: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                                </div>
                            </div>
                            
                            <div class="entrega-body">
                                <?php if (!empty($entrega['comentario'])): ?>
                                    <div class="entrega-comentario">
                                        <h5><i class="far fa-comment-alt mr-1"></i> Comentario del estudiante:</h5>
                                        <p><?php echo nl2br(htmlspecialchars($entrega['comentario'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($entrega['nombre_archivo'])): ?>
                                    <div class="entrega-archivo">
                                        <div class="archivo-icon">
                                            <i class="fas <?php echo obtenerIconoArchivo($entrega['tipo_archivo']); ?>"></i>
                                        </div>
                                        <div class="archivo-info">
                                            <p class="archivo-nombre"><?php echo htmlspecialchars($entrega['nombre_archivo']); ?></p>
                                        </div>
                                        <div class="archivo-acciones">
                                            <a href="<?php echo $entrega['ruta_archivo']; ?>" target="_blank" title="Ver archivo">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="descargar_archivo.php?ruta=<?php echo urlencode($entrega['ruta_archivo']); ?>" title="Descargar archivo">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="calificacion-form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="calificacion-<?php echo $entrega['id']; ?>">Calificación</label>
                                                <input type="number" class="form-control" id="calificacion-<?php echo $entrega['id']; ?>" 
                                                       name="calificaciones[<?php echo $entrega['id']; ?>][calificacion]" 
                                                       min="0" max="5" step="0.1" required
                                                       value="<?php echo $entrega['calificacion'] ?? ''; ?>">
                                                <small class="form-text">Calificación de 0.0 a 5.0</small>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <label for="comentario-<?php echo $entrega['id']; ?>">Retroalimentación</label>
                                                <textarea class="form-control" id="comentario-<?php echo $entrega['id']; ?>" 
                                                          name="calificaciones[<?php echo $entrega['id']; ?>][comentario]" 
                                                          rows="3"><?php echo $entrega['comentario_tutor'] ?? ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-right mt-4 mb-5">
                        <button type="submit" name="calificar" class="btn btn-success">
                            <i class="fas fa-save mr-2"></i>Guardar Calificaciones
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-check-circle"></i>
                    <h3>No hay entregas pendientes</h3>
                    <p>Todas las entregas han sido calificadas.</p>
                    <a href="actividades_tutor.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left mr-2"></i>Volver a Actividades
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
    </script>
</body>
</html>
