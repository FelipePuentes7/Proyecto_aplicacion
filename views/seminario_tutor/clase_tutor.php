<?php
session_start(); // Mantener por compatibilidad futura

require_once '../../config/conexion.php';

// Usar un ID de tutor por defecto (ajusta este valor según tu base de datos)
$tutor_id = 1; // ID de tutor por defecto

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Procesar subida de grabación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'subir_grabacion') {
    $url_grabacion = $_POST['url_grabacion'];
    $descripcion_grabacion = $_POST['descripcion_grabacion'];
    $clase_id = isset($_POST['clase_id']) ? $_POST['clase_id'] : null;

    try {
        // Verificar que la clase exista y pertenezca al tutor
        if ($clase_id) {
            $stmt = $db->prepare("
                SELECT id FROM clases_virtuales 
                WHERE id = :clase_id AND tutor_id = :tutor_id
            ");
            $stmt->bindParam(':clase_id', $clase_id);
            $stmt->bindParam(':tutor_id', $tutor_id);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new PDOException("La clase seleccionada no existe o no te pertenece");
            }
        } else {
            throw new PDOException("Debes seleccionar una clase");
        }

        // Insertar la grabación
        $stmt = $db->prepare("
            INSERT INTO grabaciones (clase_id, url_grabacion, descripcion, fecha_subida)
            VALUES (:clase_id, :url_grabacion, :descripcion, NOW())
        ");

        $stmt->bindParam(':clase_id', $clase_id);
        $stmt->bindParam(':url_grabacion', $url_grabacion);
        $stmt->bindParam(':descripcion', $descripcion_grabacion);
        $stmt->execute();

        // Redirigir a la misma página con mensaje de éxito
        header("Location: clase_tutor.php?mensaje=Grabación subida exitosamente");
        exit();
    } catch (PDOException $e) {
        $error_mensaje = "Error al subir la grabación: " . $e->getMessage();
    }
}

// Procesar edición de grabación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_grabacion') {
    $grabacion_id = $_POST['grabacion_id'];
    $url_grabacion = $_POST['url_grabacion'];
    $descripcion_grabacion = $_POST['descripcion_grabacion'];

    try {
        $stmt = $db->prepare("
            UPDATE grabaciones 
            SET url_grabacion = :url_grabacion, 
                descripcion = :descripcion 
            WHERE id = :grabacion_id
        ");
        
        $stmt->bindParam(':grabacion_id', $grabacion_id);
        $stmt->bindParam(':url_grabacion', $url_grabacion);
        $stmt->bindParam(':descripcion', $descripcion_grabacion);
        $stmt->execute();
        
        header("Location: clase_tutor.php?mensaje=Grabación actualizada exitosamente");
        exit();
    } catch (PDOException $e) {
        $error_mensaje = "Error al actualizar la grabación: " . $e->getMessage();
    }
}

// Procesar eliminación de grabación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_grabacion') {
    $grabacion_id = $_POST['grabacion_id'];

    try {
        $stmt = $db->prepare("
            DELETE FROM grabaciones 
            WHERE id = :grabacion_id
        ");
        
        $stmt->bindParam(':grabacion_id', $grabacion_id);
        $stmt->execute();
        
        header("Location: clase_tutor.php?mensaje=Grabación eliminada exitosamente");
        exit();
    } catch (PDOException $e) {
        $error_mensaje = "Error al eliminar la grabación: " . $e->getMessage();
    }
}

// Procesar creación de nueva clase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_clase') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $duracion = $_POST['duracion'];
    $plataforma = $_POST['plataforma'];
    $enlace = $_POST['enlace'];

    try {
        $stmt = $db->prepare("
            INSERT INTO clases_virtuales (
                tutor_id,
                titulo,
                descripcion,
                fecha,
                hora,
                duracion,
                plataforma,
                enlace,
                fecha_creacion
            ) VALUES (
                :tutor_id,
                :titulo,
                :descripcion,
                :fecha,
                :hora,
                :duracion,
                :plataforma,
                :enlace,
                NOW()
            )
        ");
        
        // Asegurarse de que tutor_id sea un entero
        $tutor_id_int = (int)$tutor_id;
        $stmt->bindParam(':tutor_id', $tutor_id_int, PDO::PARAM_INT);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':duracion', $duracion);
        $stmt->bindParam(':plataforma', $plataforma);
        $stmt->bindParam(':enlace', $enlace);

        $stmt->execute();

        // Redirigir para evitar reenvío del formulario
        header("Location: clase_tutor.php?mensaje=Clase creada exitosamente");
        exit();

    } catch (PDOException $e) {
        $error_mensaje = "Error al crear la clase: " . $e->getMessage();
    }
}

// Procesar eliminación de clase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_clase') {
    $clase_id = $_POST['clase_id'];
    
    try {
        // Primero eliminar las grabaciones asociadas a la clase
        $stmt = $db->prepare("
            DELETE FROM grabaciones
            WHERE clase_id = :clase_id
        ");
        $stmt->bindParam(':clase_id', $clase_id);
        $stmt->execute();
        
        // Luego eliminar la clase
        $stmt = $db->prepare("
            DELETE FROM clases_virtuales
            WHERE id = :clase_id AND tutor_id = :tutor_id
        ");
        
        $stmt->bindParam(':clase_id', $clase_id);
        $stmt->bindParam(':tutor_id', $tutor_id);
        $stmt->execute();
        
        // Redirigir sin establecer mensajes de sesión
        header("Location: clase_tutor.php?mensaje=Clase eliminada exitosamente");
        exit();
        
    } catch (PDOException $e) {
        $error_mensaje = "Error al eliminar la clase: " . $e->getMessage();
    }
}

// Procesar edición de clase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_clase') {
    $clase_id = $_POST['clase_id'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $duracion = $_POST['duracion'];
    $plataforma = $_POST['plataforma'];
    $enlace = $_POST['enlace'];

    try {
        $stmt = $db->prepare("
            UPDATE clases_virtuales 
            SET titulo = :titulo,
                descripcion = :descripcion,
                fecha = :fecha,
                hora = :hora,
                duracion = :duracion,
                plataforma = :plataforma,
                enlace = :enlace
            WHERE id = :clase_id AND tutor_id = :tutor_id
        ");
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':duracion', $duracion);
        $stmt->bindParam(':plataforma', $plataforma);
        $stmt->bindParam(':enlace', $enlace);
        $stmt->bindParam(':clase_id', $clase_id);
        $stmt->bindParam(':tutor_id', $tutor_id);
        $stmt->execute();

        header("Location: clase_tutor.php?mensaje=Clase actualizada exitosamente");
        exit();
    } catch (PDOException $e) {
        $error_mensaje = "Error al actualizar la clase: " . $e->getMessage();
    }
}

// Obtener clases del tutor
try {
    // Modificamos la consulta para evitar el error de la tabla grabaciones
    $stmt = $db->prepare("
        SELECT c.*
        FROM clases_virtuales c
        WHERE c.tutor_id = :tutor_id
        ORDER BY c.fecha DESC, c.hora DESC
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $clases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ahora obtenemos las grabaciones para cada clase
    foreach ($clases as &$clase) {
        try {
            $stmt = $db->prepare("
                SELECT url_grabacion 
                FROM grabaciones 
                WHERE clase_id = :clase_id 
                LIMIT 1
            ");
            $stmt->bindParam(':clase_id', $clase['id']);
            $stmt->execute();
            $grabacion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($grabacion) {
                $clase['url_grabacion'] = $grabacion['url_grabacion'];
            } else {
                $clase['url_grabacion'] = null;
            }
        } catch (PDOException $e) {
            // Si la tabla no existe, simplemente continuamos sin grabaciones
            $clase['url_grabacion'] = null;
        }
    }
    
} catch (PDOException $e) {
    $error_mensaje = "Error al obtener clases: " . $e->getMessage();
    $clases = [];
}

// Obtener próximas clases
try {
    $stmt = $db->prepare("
        SELECT *
        FROM clases_virtuales
        WHERE fecha >= CURDATE() AND tutor_id = :tutor_id
        ORDER BY fecha ASC, hora ASC
        LIMIT 3
    ");
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    $proximas_clases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_mensaje = "Error al obtener próximas clases: " . $e->getMessage();
    $proximas_clases = [];
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
    <title>FET - Aula Virtual</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00a63d;
            --primary-light: #00c44b;
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
            background-color: rgba(0,166,61,0.08);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            background: none;
            box-shadow: none;
            padding: 0;
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
            background-color: var(--primary-light) !important;
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
            color: var(--primary) !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--primary) !important;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
        }
        
        .class-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            background-color: white;
            transition: all 0.3s;
        }
        
        .class-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .class-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .class-title {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--primary) !important;
            margin: 0;
        }
        
        .class-date {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .class-body {
            padding: 15px 20px;
        }
        
        .class-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .class-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .info-item i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .class-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .modal-header {
            background-color: var(--primary);
            color: white;
        }
        
        .modal-title {
            font-weight: 500;
        }
        
        .close {
            color: white;
            opacity: 0.8;
        }
        
        .close:hover {
            color: white;
            opacity: 1;
        }
        
        .form-group label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 61, 0.25);
        }
        
        .alert {
            border-radius: 5px;
            padding: 15px;
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
        
        .upcoming-class {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .upcoming-class:last-child {
            border-bottom: none;
        }
        
        .class-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary) !important;
            color: white !important;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .class-details {
            flex-grow: 1;
        }
        
        .class-name {
            font-weight: 500;
            color: var(--dark);
            margin: 0 0 5px 0;
        }
        
        .class-time {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .class-time i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .join-button {
            margin-left: 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                margin-left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 2px 0 15px rgba(0,0,0,0.2);
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.menu-open::after {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .menu-toggle {
                display: block !important;
            }
        }
        
        /* Estilos para la notificación */
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Fix para los modales */
        .modal {
            z-index: 1050;
        }
        
        .modal-backdrop {
            z-index: 1040;
        }
        
        /* Asegurarse de que solo un modal esté activo a la vez */
        .modal-open .modal {
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .tutor-profile {
            margin-top: 15px;
            display: flex;
            align-items: center;
            background: var(--primary);
            border-radius: 8px;
            padding: 10px 12px;
        }
        .tutor-profile .fa-user-tie {
            color: var(--primary);
            font-size: 1.5rem;
        }
        .tutor-profile .tutor-name {
            color: #fff;
            font-weight: 500;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
        .tutor-profile .tutor-role {
            font-size: 0.95em;
            color: #e0e0e0;
        }
        
        /* Indicador de grabación disponible */
        .recording-badge {
            display: inline-block;
            background-color: var(--success);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        /* Mejoras para los botones de acción */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        /* Mejoras para la tabla de grabaciones */
        .table-responsive {
            border-radius: 5px;
            overflow: hidden;
        }
        
        .table th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
            border: none;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .btn-action {
            margin-right: 5px;
        }
        
        .btn-action:last-child {
            margin-right: 0;
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
            <div class="tutor-profile">
                <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                    <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
                </div>
                <div>
                    <div class="tutor-name">
                        <?php
                            $nombre_tutor = isset($tutor['nombre']) && isset($tutor['apellido'])
                                ? htmlspecialchars($tutor['nombre'] . ' ' . $tutor['apellido'])
                                : 'Derek Agmeth Quevedo';
                            echo $nombre_tutor;
                        ?>
                    </div>
                    <div class="tutor-role">Tutor Académico</div>
                </div>
            </div>
        </div>
        
        <ul>
            <li><a href="../seminario_tutor/inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="../seminario_tutor/actividades_tutor.php"><i class="fas fa-tasks"></i> Actividades</a></li>
            <li><a href="../seminario_tutor/clase_tutor.php" class="active"><i class="fas fa-video"></i> Aula Virtual</a></li>
            <li><a href="../seminario_tutor/material_tutor.php"><i class="fas fa-book"></i> Material de Apoyo</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1>Aula Virtual</h1>
                <p>Gestiona tus clases virtuales y grabaciones</p>
            </div>
        </div>
        
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['mensaje']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_mensaje)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_mensaje); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Próximas Clases y Acciones Rápidas -->
            <div class="col-lg-5 col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt mr-2"></i> Próximas Clases
                    </div>
                    <div class="card-body">
                        <?php if (count($proximas_clases) > 0): ?>
                            <?php foreach ($proximas_clases as $clase): ?>
                                <div class="upcoming-class">
                                    <div class="class-icon">
                                        <i class="fas fa-video"></i>
                                    </div>
                                    <div class="class-details">
                                        <h5 class="class-name"><?php echo htmlspecialchars($clase['titulo']); ?></h5>
                                        <div class="class-time">
                                            <i class="far fa-calendar"></i> <?php echo formatearFecha($clase['fecha']); ?>
                                        </div>
                                        <div class="class-time">
                                            <i class="far fa-clock"></i> <?php echo date('H:i', strtotime($clase['hora'])); ?> (<?php echo $clase['duracion']; ?> min)
                                        </div>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($clase['enlace']); ?>" class="btn btn-sm btn-primary join-button" target="_blank">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted">No hay clases programadas próximamente.</p>
                            <div class="text-center">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#nuevaClaseModal">
                                    <i class="fas fa-plus-circle mr-2"></i> Programar Clase
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle mr-2"></i> Acciones Rápidas
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-block mb-2" data-toggle="modal" data-target="#nuevaClaseModal">
                                <i class="fas fa-calendar-plus mr-2"></i> Nueva Clase
                            </button>
                            <button type="button" class="btn btn-info btn-block mb-2" data-toggle="modal" data-target="#nuevaGrabacionModal">
                                <i class="fas fa-film mr-2"></i> Subir Grabación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lista de Clases -->
            <div class="col-lg-7 col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-video mr-2"></i> Mis Clases Virtuales</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($clases) > 0): ?>
                            <?php foreach ($clases as $clase): ?>
                                <div class="class-card">
                                    <div class="class-header">
                                        <h5 class="class-title">
                                            <?php echo htmlspecialchars($clase['titulo']); ?>
                                            <?php if (!empty($clase['url_grabacion'])): ?>
                                                <span class="recording-badge">
                                                    <i class="fas fa-video"></i> Grabación disponible
                                                </span>
                                            <?php endif; ?>
                                        </h5>
                                        <span class="class-date"><?php echo formatearFecha($clase['fecha']); ?></span>
                                    </div>
                                    <div class="class-body">
                                        <div class="class-description">
                                            <?php echo nl2br(htmlspecialchars($clase['descripcion'] ?? 'Sin descripción')); ?>
                                        </div>
                                        <div class="class-info">
                                            <div class="info-item">
                                                <i class="far fa-clock"></i>
                                                <span><?php echo date('H:i', strtotime($clase['hora'])); ?> (<?php echo $clase['duracion']; ?> min)</span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-video"></i>
                                                <span><?php echo htmlspecialchars($clase['plataforma']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-calendar-check"></i>
                                                <span>Creada: <?php echo date('d/m/Y', strtotime($clase['fecha_creacion'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="class-actions">
                                            <div class="action-buttons">
                                                <a href="<?php echo htmlspecialchars($clase['enlace']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-sign-in-alt mr-1"></i> Iniciar Clase
                                                </a>
                                                <!-- Botón Editar Clase -->
                                                <button type="button" class="btn btn-sm btn-warning edit-btn" 
                                                    data-id="<?php echo $clase['id']; ?>">
                                                    <i class="fas fa-edit mr-1"></i> Editar Clase
                                                </button>
                                                <!-- Botón Eliminar Clase -->
                                                <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                    data-id="<?php echo $clase['id']; ?>">
                                                    <i class="fas fa-trash-alt mr-1"></i> Eliminar Clase
                                                </button>
                                                
                                                <?php if (empty($clase['url_grabacion'])): ?>
                                                    <!-- Botón Subir Grabación -->
                                                    <button type="button" class="btn btn-sm btn-info upload-recording-btn"
                                                        data-id="<?php echo $clase['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($clase['titulo']); ?>">
                                                        <i class="fas fa-film mr-1"></i> Subir Grabación
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Botón Ver Grabación -->
                                                    <a href="<?php echo htmlspecialchars($clase['url_grabacion']); ?>" class="btn btn-sm btn-success" target="_blank">
                                                        <i class="fas fa-play-circle mr-1"></i> Ver Grabación
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-video-slash fa-4x mb-3 text-muted"></i>
                                <h4>No hay clases creadas</h4>
                                <p class="text-muted">Comienza creando una nueva clase virtual para tus estudiantes</p>
                                <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#nuevaClaseModal">
                                    <i class="fas fa-plus-circle mr-2"></i> Nueva Clase
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Sección de Grabaciones -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-film mr-2"></i> Mis Grabaciones</span>
            </div>
            <div class="card-body">
                <?php
                // Obtener todas las grabaciones del tutor
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            g.id as grabacion_id,
                            g.url_grabacion,
                            g.descripcion,
                            g.fecha_subida,
                            c.id as clase_id,
                            COALESCE(c.titulo, 'Clase Archivada') as titulo
                        FROM grabaciones g
                        LEFT JOIN clases_virtuales c ON g.clase_id = c.id
                        WHERE c.tutor_id = :tutor_id OR c.id IS NULL
                        ORDER BY g.fecha_subida DESC
                    ");
                    $stmt->bindParam(':tutor_id', $tutor_id);
                    $stmt->execute();
                    $grabaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($grabaciones) > 0) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-hover">';
                        echo '<thead class="thead-light">';
                        echo '<tr>';
                        echo '<th>Clase</th>';
                        echo '<th>Descripción</th>';
                        echo '<th>Fecha de subida</th>';
                        echo '<th>Acciones</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($grabaciones as $grabacion) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($grabacion['titulo']) . '</td>';
                            echo '<td>' . (empty($grabacion['descripcion']) ? '<em>Sin descripción</em>' : htmlspecialchars($grabacion['descripcion'])) . '</td>';
                            echo '<td>' . date('d/m/Y H:i', strtotime($grabacion['fecha_subida'])) . '</td>';
                            echo '<td>';
                            echo '<div class="btn-group">';
                            echo '<a href="' . htmlspecialchars($grabacion['url_grabacion']) . '" class="btn btn-sm btn-info btn-action" target="_blank"><i class="fas fa-play-circle mr-1"></i> Ver</a>';
                            echo '<button type="button" class="btn btn-sm btn-warning btn-action edit-grabacion-btn" data-id="' . $grabacion['grabacion_id'] . '" data-descripcion="' . htmlspecialchars($grabacion['descripcion']) . '" data-url="' . htmlspecialchars($grabacion['url_grabacion']) . '"><i class="fas fa-edit mr-1"></i> Editar</button>';
                            echo '<button type="button" class="btn btn-sm btn-danger btn-action delete-grabacion-btn" data-id="' . $grabacion['grabacion_id'] . '"><i class="fas fa-trash-alt mr-1"></i> Eliminar</button>';
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    } else {
                        echo '<div class="text-center py-4">';
                        echo '<i class="fas fa-film fa-3x text-muted mb-3"></i>';
                        echo '<p class="text-muted">No has subido grabaciones aún.</p>';
                        echo '</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">Error al cargar grabaciones: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Contenedor para modales dinámicos -->
    <div id="modalesContainer">
        <!-- Modal para editar grabación -->
        <div class="modal fade" id="editarGrabacionModal" tabindex="-1" role="dialog" aria-labelledby="editarGrabacionModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editarGrabacionModalLabel">Editar Enlace de Grabación</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="editGrabacionForm" action="clase_tutor.php" method="post">
                            <input type="hidden" name="accion" value="editar_grabacion">
                            <input type="hidden" name="grabacion_id" id="edit_grabacion_id">
                            
                            <div class="form-group">
                                <label for="edit_url_grabacion">URL de la grabación</label>
                                <input type="url" class="form-control" id="edit_url_grabacion" name="url_grabacion" required>
                                <small class="form-text text-muted">Ingresa la URL completa del video (YouTube, Vimeo, etc.)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_descripcion_grabacion">Descripción</label>
                                <textarea class="form-control" id="edit_descripcion_grabacion" name="descripcion_grabacion" rows="3"></textarea>
                            </div>
                            
                            <div class="text-right">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para eliminar grabación -->
        <div class="modal fade" id="eliminarGrabacionModal" tabindex="-1" role="dialog" aria-labelledby="eliminarGrabacionModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="eliminarGrabacionModalLabel">Confirmar eliminación de grabación</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar esta grabación?</p>
                        <p class="text-muted">Solo se eliminará el enlace de la grabación en el sistema, no el video original en la plataforma externa.</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <form action="clase_tutor.php" method="post">
                            <input type="hidden" name="accion" value="eliminar_grabacion">
                            <input type="hidden" name="grabacion_id" id="delete_grabacion_id">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Eliminar Grabación</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Los modales se generarán aquí dinámicamente con JavaScript -->
    </div>
    
    <!-- Modal para crear nueva clase -->
    <div class="modal fade" id="nuevaClaseModal" tabindex="-1" role="dialog" aria-labelledby="nuevaClaseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevaClaseModalLabel">Nueva Clase Virtual</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createForm" action="clase_tutor.php" method="post">
                        <input type="hidden" name="accion" value="crear_clase">
                        
                        <div class="form-group">
                            <label for="titulo">Título</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ej: Introducción a SQL" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Describe brevemente el contenido de la clase..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fecha">Fecha</label>
                                    <input type="date" class="form-control" id="fecha" name="fecha" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hora">Hora</label>
                                    <input type="time" class="form-control" id="hora" name="hora" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duracion">Duración (minutos)</label>
                                    <input type="number" class="form-control" id="duracion" name="duracion" value="60" min="15" max="180" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plataforma">Plataforma</label>
                                    <select class="form-control" id="plataforma" name="plataforma" required>
                                        <option value="Zoom">Zoom</option>
                                        <option value="Google Meet">Google Meet</option>
                                        <option value="Microsoft Teams">Microsoft Teams</option>
                                        <option value="Otra">Otra</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="enlace">Enlace de la clase</label>
                            <input type="url" class="form-control" id="enlace" name="enlace" placeholder="https://..." required>
                        </div>
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Crear Clase</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para subir grabación con selección de clase -->
    <div class="modal fade" id="nuevaGrabacionModal" tabindex="-1" role="dialog" aria-labelledby="nuevaGrabacionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevaGrabacionModalLabel">Subir Grabación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" action="clase_tutor.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="subir_grabacion">
                        
                        <div class="form-group">
                            <label for="clase_seleccionada">Clase</label>
                            <select class="form-control" id="clase_seleccionada" name="clase_id" required>
                                <option value="">Seleccionar clase...</option>
                                <?php
                                // Obtener todas las clases del tutor para el selector
                                try {
                                    $stmt = $db->prepare("
                                        SELECT id, titulo, fecha
                                        FROM clases_virtuales
                                        WHERE tutor_id = :tutor_id
                                        ORDER BY fecha DESC, hora DESC
                                    ");
                                    $stmt->bindParam(':tutor_id', $tutor_id);
                                    $stmt->execute();
                                    $clases_select = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($clases_select as $clase) {
                                        echo '<option value="' . $clase['id'] . '">' . htmlspecialchars($clase['titulo']) . ' (' . formatearFecha($clase['fecha']) . ')</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option value="" disabled>Error al cargar clases</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="url_grabacion">URL de la grabación</label>
                            <input type="url" class="form-control" id="url_grabacion" name="url_grabacion" placeholder="https://..." required>
                            <small class="form-text text-muted">Ingresa la URL de la grabación (YouTube, Vimeo, etc.)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion_grabacion">Descripción (opcional)</label>
                            <textarea class="form-control" id="descripcion_grabacion" name="descripcion_grabacion" rows="3" placeholder="Añade una descripción para la grabación..."></textarea>
                        </div>
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Subir Grabación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejo del sidebar responsive
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuToggle = document.createElement('button');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.className = 'menu-toggle btn btn-primary';
            menuToggle.style.position = 'fixed';
            menuToggle.style.top = '10px';
            menuToggle.style.left = '10px';
            menuToggle.style.zIndex = '1001';
            menuToggle.style.display = 'none';
            document.body.prepend(menuToggle);

            function toggleMenu() {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('menu-open');
            }

            menuToggle.addEventListener('click', toggleMenu);

            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 &&
                    !sidebar.contains(e.target) &&
                    !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('menu-open');
                }
            });

            function handleResize() {
                if (window.innerWidth <= 768) {
                    menuToggle.style.display = 'block';
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('menu-open');
                } else {
                    menuToggle.style.display = 'none';
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('menu-open');
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize();

            // Auto-hide alerts after 5 seconds
            window.setTimeout(function() {
                const alerts = document.getElementsByClassName('alert');
                for (let i = 0; i < alerts.length; i++) {
                    alerts[i].style.opacity = '0';
                    alerts[i].style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        alerts[i].style.display = 'none';
                    }, 500);
                }
            }, 5000);

            // Set default date for new class to today
            const fechaInput = document.getElementById('fecha');
            if (fechaInput) {
                fechaInput.valueAsDate = new Date();
            }

            // Plataforma "Otra"
            document.querySelectorAll('[id^="plataforma"]').forEach(select => {
                select.addEventListener('change', function() {
                    const container = this.closest('.form-group');
                    let customInput = container.nextElementSibling;
                    if (this.value === 'Otra' && (!customInput || !customInput.classList.contains('custom-platform'))) {
                        customInput = document.createElement('div');
                        customInput.className = 'form-group custom-platform';
                        customInput.innerHTML = `
                            <label>Especificar plataforma</label>
                            <input type="text" class="form-control" name="plataforma_custom" required>
                        `;
                        container.parentNode.insertBefore(customInput, container.nextSibling);
                    } else if (this.value !== 'Otra' && customInput && customInput.classList.contains('custom-platform')) {
                        customInput.remove();
                    }
                });
            });

            // ===== NUEVA IMPLEMENTACIÓN PARA MODALES DINÁMICOS =====
            
            // Datos de las clases para generar modales dinámicamente
            const clasesData = <?php echo json_encode($clases); ?>;
            
            // Función para generar modales dinámicamente
            function generarModales() {
                // Limpiar el contenedor de modales
                const modalesContainer = document.getElementById('modalesContainer');
                
                // Generar modales para cada clase
                clasesData.forEach(clase => {
                    // Modal de edición
                    const modalEditar = document.createElement('div');
                    modalEditar.className = 'modal fade';
                    modalEditar.id = `editarClaseModal${clase.id}`;
                    modalEditar.setAttribute('tabindex', '-1');
                    modalEditar.setAttribute('role', 'dialog');
                    modalEditar.setAttribute('aria-labelledby', `editarClaseModalLabel${clase.id}`);
                    modalEditar.setAttribute('aria-hidden', 'true');
                    
                    modalEditar.innerHTML = `
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <form id="editForm${clase.id}" action="clase_tutor.php" method="post">
                                    <input type="hidden" name="accion" value="editar_clase">
                                    <input type="hidden" name="clase_id" value="${clase.id}">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editarClaseModalLabel${clase.id}">Editar Clase</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="titulo${clase.id}">Título</label>
                                            <input type="text" class="form-control" id="titulo${clase.id}" name="titulo" value="${clase.titulo}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="descripcion${clase.id}">Descripción</label>
                                            <textarea class="form-control" id="descripcion${clase.id}" name="descripcion" rows="3">${clase.descripcion || ''}</textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="fecha${clase.id}">Fecha</label>
                                                    <input type="date" class="form-control" id="fecha${clase.id}" name="fecha" value="${clase.fecha}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="hora${clase.id}">Hora</label>
                                                    <input type="time" class="form-control" id="hora${clase.id}" name="hora" value="${clase.hora}" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="duracion${clase.id}">Duración (minutos)</label>
                                                    <input type="number" class="form-control" id="duracion${clase.id}" name="duracion" value="${clase.duracion}" min="15" max="180" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="plataforma${clase.id}">Plataforma</label>
                                                    <select class="form-control" id="plataforma${clase.id}" name="plataforma" required>
                                                        <option value="Zoom" ${clase.plataforma === 'Zoom' ? 'selected' : ''}>Zoom</option>
                                                        <option value="Google Meet" ${clase.plataforma === 'Google Meet' ? 'selected' : ''}>Google Meet</option>
                                                        <option value="Microsoft Teams" ${clase.plataforma === 'Microsoft Teams' ? 'selected' : ''}>Microsoft Teams</option>
                                                        <option value="Otra" ${!['Zoom', 'Google Meet', 'Microsoft Teams'].includes(clase.plataforma) ? 'selected' : ''}>Otra</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="enlace${clase.id}">Enlace de la clase</label>
                                            <input type="url" class="form-control" id="enlace${clase.id}" name="enlace" value="${clase.enlace}" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                    
                    // Modal de eliminación
                    const modalEliminar = document.createElement('div');
                    modalEliminar.className = 'modal fade';
                    modalEliminar.id = `eliminarClaseModal${clase.id}`;
                    modalEliminar.setAttribute('tabindex', '-1');
                    
                    modalEliminar.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="clase_tutor.php" method="post">
                                    <input type="hidden" name="accion" value="eliminar_clase">
                                    <input type="hidden" name="clase_id" value="${clase.id}">
                                    
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirmar eliminación de clase</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    
                                    <div class="modal-body">
                                        <p>¿Estas seguro de eliminar esta clase? Se eliminarán todos los videos relacionados con esta clase.</p>
                                        ${clase.url_grabacion ? '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Esta clase tiene una grabación que también será eliminada.</div>' : ''}
                                    </div>
                                    
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger">Eliminar Clase</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                    
                    // Modal para subir grabación específica
                    const modalSubirGrabacion = document.createElement('div');
                    modalSubirGrabacion.className = 'modal fade';
                    modalSubirGrabacion.id = `subirGrabacionModal${clase.id}`;
                    modalSubirGrabacion.setAttribute('tabindex', '-1');
                    
                    modalSubirGrabacion.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="clase_tutor.php" method="post">
                                    <input type="hidden" name="accion" value="subir_grabacion">
                                    <input type="hidden" name="clase_id" value="${clase.id}">
                                    
                                    <div class="modal-header">
                                        <h5 class="modal-title">Subir Grabación: ${clase.titulo}</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="url_grabacion${clase.id}">URL de la grabación</label>
                                            <input type="url" class="form-control" id="url_grabacion${clase.id}" name="url_grabacion" placeholder="https://..." required>
                                            <small class="form-text text-muted">Ingresa la URL de la grabación (YouTube, Vimeo, etc.)</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="descripcion_grabacion${clase.id}">Descripción (opcional)</label>
                                            <textarea class="form-control" id="descripcion_grabacion${clase.id}" name="descripcion_grabacion" rows="3" placeholder="Añade una descripción para la grabación..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Subir Grabación</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                    
                    // Agregar modales al contenedor
                    modalesContainer.appendChild(modalEditar);
                    modalesContainer.appendChild(modalEliminar);
                    modalesContainer.appendChild(modalSubirGrabacion);
                });
                
                // Inicializar eventos para los modales generados
                inicializarEventosModales();
            }
            
            // Inicializar eventos para los modales
            function inicializarEventosModales() {
                // Manejar correctamente el envío de formularios en modales
                $('form[id^="editForm"]').on('submit', function(e) {
                    e.preventDefault();
                    const form = $(this);
                    $(this).closest('.modal').modal('hide');
                    setTimeout(function() {
                        form.off('submit').submit();
                    }, 300);
                });
                
                // Plataforma "Otra" para modales generados dinámicamente
                document.querySelectorAll('[id^="plataforma"]').forEach(select => {
                    select.addEventListener('change', function() {
                        const container = this.closest('.form-group');
                        let customInput = container.nextElementSibling;
                        if (this.value === 'Otra' && (!customInput || !customInput.classList.contains('custom-platform'))) {
                            customInput = document.createElement('div');
                            customInput.className = 'form-group custom-platform';
                            customInput.innerHTML = `
                                <label>Especificar plataforma</label>
                                <input type="text" class="form-control" name="plataforma_custom" required>
                            `;
                            container.parentNode.insertBefore(customInput, container.nextSibling);
                        } else if (this.value !== 'Otra' && customInput && customInput.classList.contains('custom-platform')) {
                            customInput.remove();
                        }
                    });
                });
            }
            
            // Generar modales al cargar la página
            generarModales();
            
            // Manejar clics en botones de editar y eliminar
            document.addEventListener('click', function(e) {
                // Botón editar
                if (e.target.closest('.edit-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.edit-btn');
                    const claseId = btn.getAttribute('data-id');
                    
                    // Cerrar cualquier modal abierto
                    $('.modal').modal('hide');
                    
                    // Abrir el modal de edición
                    setTimeout(function() {
                        $(`#editarClaseModal${claseId}`).modal('show');
                    }, 200);
                }
                
                // Botón eliminar
                if (e.target.closest('.delete-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.delete-btn');
                    const claseId = btn.getAttribute('data-id');
                    
                    // Cerrar cualquier modal abierto
                    $('.modal').modal('hide');
                    
                    // Abrir el modal de eliminación
                    setTimeout(function() {
                        $(`#eliminarClaseModal${claseId}`).modal('show');
                    }, 200);
                }
                
                // Botón subir grabación específica
                if (e.target.closest('.upload-recording-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.upload-recording-btn');
                    const claseId = btn.getAttribute('data-id');
                    
                    // Cerrar cualquier modal abierto
                    $('.modal').modal('hide');
                    
                    // Abrir el modal de subir grabación
                    setTimeout(function() {
                        $(`#subirGrabacionModal${claseId}`).modal('show');
                    }, 200);
                }
            });
            
            // Manejar modales estáticos (crear clase y subir grabación)
            $('#nuevaClaseModal, #nuevaGrabacionModal').on('show.bs.modal', function() {
                // Cerrar cualquier otro modal abierto
                $('.modal').not($(this)).modal('hide');
            });
            
            // Manejar envío de formularios estáticos
            $('#createForm, #uploadForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                $(this).closest('.modal').modal('hide');
                setTimeout(function() {
                    form.off('submit').submit();
                }, 300);
            });
            
            // Corregir problemas con el backdrop de los modales
            $('.modal').on('hidden.bs.modal', function() {
                if ($('.modal:visible').length) {
                    $('body').addClass('modal-open');
                } else {
                    $('.modal-backdrop').remove();
                }
            });

            // Manejar botones de editar grabación
            document.addEventListener('click', function(e) {
                if (e.target.closest('.edit-grabacion-btn')) {
                    const btn = e.target.closest('.edit-grabacion-btn');
                    const grabacionId = btn.getAttribute('data-id');
                    const url = btn.getAttribute('data-url');
                    const descripcion = btn.getAttribute('data-descripcion');
                    
                    document.getElementById('edit_grabacion_id').value = grabacionId;
                    document.getElementById('edit_url_grabacion').value = url;
                    document.getElementById('edit_descripcion_grabacion').value = descripcion;
                    
                    // Cerrar cualquier modal abierto
                    $('.modal').modal('hide');
                    
                    // Abrir el modal de edición de grabación
                    setTimeout(function() {
                        $('#editarGrabacionModal').modal('show');
                    }, 200);
                }
                
                if (e.target.closest('.delete-grabacion-btn')) {
                    const btn = e.target.closest('.delete-grabacion-btn');
                    const grabacionId = btn.getAttribute('data-id');
                    
                    document.getElementById('delete_grabacion_id').value = grabacionId;
                    
                    // Cerrar cualquier modal abierto
                    $('.modal').modal('hide');
                    
                    // Abrir el modal de eliminación de grabación
                    setTimeout(function() {
                        $('#eliminarGrabacionModal').modal('show');
                    }, 200);
                }
            });
        });
    </script>
</body>
</html>