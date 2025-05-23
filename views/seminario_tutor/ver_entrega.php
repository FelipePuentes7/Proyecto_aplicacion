<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Verificar si se proporcionó un ID de entrega
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: actividades_tutor.php');
    exit;
}

$entrega_id = $_GET['id'];

// Obtener información de la entrega
try {
    $stmt = $db->prepare("
        SELECT ea.*, a.titulo AS actividad_titulo, a.descripcion AS actividad_descripcion,
               a.fecha_limite, a.hora_limite, a.tipo AS actividad_tipo,
               u.nombre AS estudiante_nombre, u.apellido AS estudiante_apellido, 
               u.avatar AS estudiante_avatar
        FROM entregas_actividad ea
        JOIN actividades a ON ea.actividad_id = a.id
        JOIN estudiantes e ON ea.estudiante_id = e.id
        JOIN usuarios u ON e.usuario_id = u.id
        WHERE ea.id = :entrega_id
    ");
    
    $stmt->bindParam(':entrega_id', $entrega_id);
    $stmt->execute();
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si la entrega existe
    if (!$entrega) {
        header('Location: actividades_tutor.php');
        exit;
    }
    
    // Obtener archivos de la entrega
    $stmt = $db->prepare("
        SELECT * FROM archivos_entrega
        WHERE entrega_id = :entrega_id
    ");
    
    $stmt->bindParam(':entrega_id', $entrega_id);
    $stmt->execute();
    $archivos_entrega = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    die();
}

// Procesar formulario de calificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'calificar') {
    $calificacion = $_POST['calificacion'];
    $comentario_tutor = sanitize($_POST['comentario_tutor']);
    
    try {
        $stmt = $db->prepare("
            UPDATE entregas_actividad
            SET calificacion = :calificacion, comentario_tutor = :comentario_tutor, estado = 'calificado'
            WHERE id = :entrega_id
        ");
        
        $stmt->bindParam(':calificacion', $calificacion);
        $stmt->bindParam(':comentario_tutor', $comentario_tutor);
        $stmt->bindParam(':entrega_id', $entrega_id);
        
        $stmt->execute();
        
        $mensaje = "Entrega calificada exitosamente";
        $tipo_mensaje = "success";
        
        // Actualizar la información de la entrega
        $stmt = $db->prepare("
            SELECT * FROM entregas_actividad WHERE id = :entrega_id
        ");
        $stmt->bindParam(':entrega_id', $entrega_id);
        $stmt->execute();
        $entrega_actualizada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Actualizar los datos de la entrega
        $entrega['calificacion'] = $entrega_actualizada['calificacion'];
        $entrega['comentario_tutor'] = $entrega_actualizada['comentario_tutor'];
        $entrega['estado'] = $entrega_actualizada['estado'];
        
    } catch (PDOException $e) {
        $mensaje = "Error al calificar la entrega: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Entrega - Panel de Tutor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
<<<<<<< HEAD
            --primary: #039708;
            --primary-light: #039708;
=======
            --primary: #1a3b8b;
            --primary-light: #2a4c9c;
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
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
<<<<<<< HEAD

          .sidebar {
        display: flex;
        flex-direction: column;
        height: 100vh;
        background-color: #039708; /* o tu color verde institucional */
        }

        .logout-btn:hover {
        color: white;
        }
=======
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
        
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .student-details h4 {
            margin: 0 0 5px 0;
            color: var(--primary);
        }
        
        .student-details p {
            margin: 0;
            color: #6c757d;
        }
        
        .activity-info {
            margin-bottom: 20px;
        }
        
        .activity-info h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .activity-meta span {
            margin-right: 20px;
            color: #6c757d;
            display: flex;
            align-items: center;
        }
        
        .activity-meta i {
            margin-right: 5px;
        }
        
        .submission-content {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .submission-section {
            margin-bottom: 20px;
        }
        
        .submission-section h4 {
            color: var(--primary);
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            width: calc(50% - 5px);
        }
        
        .file-item i {
            font-size: 1.5rem;
            margin-right: 10px;
            color: var(--primary);
        }
        
        .file-info {
            flex-grow: 1;
        }
        
        .file-name {
            display: block;
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .file-size {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .file-actions {
            display: flex;
        }
        
        .file-actions a {
            margin-left: 5px;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .grading-form {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .grading-details {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .grade-info {
            display: flex;
            align-items: flex-start;
        }
        
        .grade-value {
            display: flex;
            align-items: baseline;
            margin-right: 20px;
        }
        
        .grade {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .grade-max {
            font-size: 1.2rem;
            color: #6c757d;
            margin-left: 5px;
        }
        
        .grade-feedback {
            flex-grow: 1;
        }
        
        .grade-feedback h5 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(26, 59, 139, 0.25);
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
            
            .file-item {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
<<<<<<< HEAD
            <img src="/assets/images/logofet.png" alt="FET Logo" style="width: 100px;">
        </div>
        
        <ul>
=======
            <h3><img src="../../assets/img/logofet.png" alt="FET Logo"> FET</h3>
        </div>
        
        <ul>z
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
            <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="actividades_tutor.php" class="active"><i class="fas fa-tasks"></i> Actividades</a></li>
            <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
            <li><a href="material_tutor.php"><i class="fas fa-book"></i> Material de Apoyo</a></li>
        </ul>
<<<<<<< HEAD


        
=======
>>>>>>> 60287c4c61215831ef3fe72e1027661b15aa6bf1
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1>Revisar Entrega</h1>
                <p>Califica la entrega del estudiante</p>
            </div>
        </div>
        
        <!-- Mensaje de éxito/error -->
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                Información de la Entrega
            </div>
            <div class="card-body">
                <div class="student-info">
                    <img src="<?php echo $entrega['estudiante_avatar']; ?>" alt="Avatar del estudiante" class="student-avatar">
                    <div class="student-details">
                        <h4><?php echo htmlspecialchars($entrega['estudiante_nombre'] . ' ' . $entrega['estudiante_apellido']); ?></h4>
                        <p>
                            <i class="far fa-calendar-alt mr-1"></i> Entregado el: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                        </p>
                        <p>
                            <i class="fas fa-circle mr-1 <?php echo $entrega['estado'] === 'pendiente' ? 'text-warning' : 'text-success'; ?>"></i>
                            Estado: <?php echo ucfirst($entrega['estado']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="activity-info">
                    <h3><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></h3>
                    <div class="activity-meta">
                        <span>
                            <i class="fas fa-tag"></i> <?php echo ucfirst($entrega['actividad_tipo']); ?>
                        </span>
                        <span>
                            <i class="far fa-calendar-alt"></i> Fecha límite: <?php echo date('d/m/Y', strtotime($entrega['fecha_limite'])); ?>
                        </span>
                        <span>
                            <i class="far fa-clock"></i> Hora límite: <?php echo date('H:i', strtotime($entrega['hora_limite'])); ?>
                        </span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($entrega['actividad_descripcion'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="submission-content">
            <div class="submission-section">
                <h4>Comentarios del Estudiante</h4>
                <?php if (!empty($entrega['comentario'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($entrega['comentario'])); ?></p>
                <?php else: ?>
                    <p class="text-muted">El estudiante no ha dejado comentarios.</p>
                <?php endif; ?>
            </div>
            
            <?php if (count($archivos_entrega) > 0): ?>
                <div class="submission-section">
                    <h4>Archivos Entregados</h4>
                    <div class="file-list">
                        <?php foreach ($archivos_entrega as $archivo): ?>
                            <div class="file-item">
                                <i class="<?php echo obtenerIconoArchivo($archivo['nombre_archivo']); ?>"></i>
                                <div class="file-info">
                                    <span class="file-name"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></span>
                                    <span class="file-size"><?php echo formatearTamano($archivo['tamano_archivo']); ?></span>
                                </div>
                                <div class="file-actions">
                                    <a href="<?php echo $archivo['ruta_archivo']; ?>" target="_blank" title="Ver archivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $archivo['ruta_archivo']; ?>" download title="Descargar archivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($entrega['estado'] === 'pendiente'): ?>
            <div class="grading-form">
                <h4>Calificar Entrega</h4>
                <form action="ver_entrega.php?id=<?php echo $entrega_id; ?>" method="post">
                    <input type="hidden" name="accion" value="calificar">
                    
                    <div class="form-group">
                        <label for="calificacion">Calificación (0-5) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="calificacion" name="calificacion" min="0" max="5" step="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="comentario_tutor">Retroalimentación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="comentario_tutor" name="comentario_tutor" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-group text-right">
                        <a href="actividades_tutor.php" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Calificación</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="grading-details">
                <h4>Calificación</h4>
                <div class="grade-info">
                    <div class="grade-value">
                        <span class="grade"><?php echo $entrega['calificacion']; ?></span>
                        <span class="grade-max">/5</span>
                    </div>
                    <div class="grade-feedback">
                        <h5>Retroalimentación</h5>
                        <p><?php echo nl2br(htmlspecialchars($entrega['comentario_tutor'])); ?></p>
                    </div>
                </div>
                <div class="text-right mt-3">
                    <a href="actividades_tutor.php" class="btn btn-secondary">Volver a Actividades</a>
                    <a href="editar_calificacion.php?id=<?php echo $entrega_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit mr-1"></i> Editar Calificación
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
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