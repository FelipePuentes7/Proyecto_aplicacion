<?php
    // Incluir archivo de conexión
    require_once '../../config/conexion.php';

    // Iniciar sesión
    session_start();

    // Habilitar todos los errores para depuración
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Crear instancia de conexión
    $conexion = new Conexion();
    $db = $conexion->getConexion();

    // Crear directorio de logs si no existe
    $log_dir = "../../logs";
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    // Función para escribir en el log
    function escribirLog($mensaje) {
        global $log_dir;
        $fecha = date('Y-m-d H:i:s');
        $log_file = $log_dir . "/calificaciones_" . date('Y-m-d') . ".log";
        file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
    }

    escribirLog("Página calificar_entregas.php cargada");

    // ID del tutor (hardcodeado para pruebas)
    $tutor_id = 1;

    // Obtener ID de la actividad
    $actividad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    escribirLog("Actividad ID: $actividad_id, Tutor ID: $tutor_id");

    // Verificar si se ha enviado el formulario para calificar
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        escribirLog("Formulario POST recibido: " . print_r($_POST, true));
        
        $calificaciones_guardadas = false;
        $errores = [];
        $entregas_calificadas = 0;
        
        // Verificar si hay calificaciones para procesar
        if (isset($_POST['calificaciones']) && is_array($_POST['calificaciones'])) {
            escribirLog("Procesando " . count($_POST['calificaciones']) . " calificaciones");
            
            // Iniciar transacción
            $db->beginTransaction();
            
            try {
                foreach ($_POST['calificaciones'] as $entrega_id => $datos) {
                    // Ignorar entradas de prueba
                    if ($entrega_id === 'test') {
                        escribirLog("Ignorando entrada de prueba");
                        continue;
                    }
                    
                    escribirLog("Procesando entrega ID: $entrega_id");
                    
                    // Verificar si esta entrega existe y si ya está calificada
                    $stmt = $db->prepare("
                        SELECT id, estado
                        FROM entregas_actividad
                        WHERE id = :entrega_id
                    ");
                    $stmt->bindParam(':entrega_id', $entrega_id);
                    $stmt->execute();
                    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$entrega) {
                        escribirLog("Error: Entrega $entrega_id no encontrada");
                        $errores[] = "La entrega #$entrega_id no existe.";
                        continue;
                    }
                    
                    // Saltar si ya está calificada
                    if ($entrega['estado'] === 'calificado') {
                        escribirLog("Entrega $entrega_id ya está calificada, saltando");
                        continue;
                    }
                    
                    // Validar que la calificación exista y sea un número válido
                    if (!isset($datos['calificacion']) || trim($datos['calificacion']) === '') {
                        escribirLog("Error: Calificación vacía para entrega $entrega_id");
                        $errores[] = "La calificación para la entrega #$entrega_id no puede estar vacía.";
                        continue;
                    }
                    
                    // Limpiar y validar la calificación
                    $calificacion_raw = trim($datos['calificacion']);
                    $calificacion = str_replace(',', '.', $calificacion_raw); // Reemplazar comas por puntos
                    
                    // Verificar si es un número válido
                    if (!is_numeric($calificacion)) {
                        escribirLog("Error: Calificación no numérica: $calificacion_raw");
                        $errores[] = "La calificación para la entrega #$entrega_id debe ser un número (recibido: $calificacion_raw).";
                        continue;
                    }
                    
                    $calificacion = floatval($calificacion);
                    $comentario = isset($datos['comentario']) ? trim($datos['comentario']) : '';
                    
                    escribirLog("Calificación procesada: $calificacion, Comentario: " . substr($comentario, 0, 30) . "...");
                    
                    // Validar calificación
                    if ($calificacion < 0 || $calificacion > 5) {
                        escribirLog("Error: Calificación fuera de rango: $calificacion");
                        $errores[] = "La calificación para la entrega #$entrega_id debe estar entre 0.0 y 5.0 (valor recibido: $calificacion).";
                        continue;
                    }
                    
                    try {
                        // Actualizar la entrega con la calificación
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
                        
                        $result = $stmt->execute();
                        
                        if ($result) {
                            escribirLog("Calificación guardada exitosamente para entrega $entrega_id");
                            $entregas_calificadas++;
                            $calificaciones_guardadas = true;
                            
                            // Verificar que la actualización fue exitosa
                            $stmt = $db->prepare("
                                SELECT estado, calificacion
                                FROM entregas_actividad
                                WHERE id = :entrega_id
                            ");
                            $stmt->bindParam(':entrega_id', $entrega_id);
                            $stmt->execute();
                            $entrega_actualizada = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            escribirLog("Verificación de actualización: Estado = " . $entrega_actualizada['estado'] . 
                                    ", Calificación = " . $entrega_actualizada['calificacion']);
                        } else {
                            escribirLog("Error al guardar calificación: " . print_r($stmt->errorInfo(), true));
                            $errores[] = "Error al guardar la calificación para la entrega #$entrega_id: " . implode(" - ", $stmt->errorInfo());
                        }
                    } catch (PDOException $e) {
                        escribirLog("Excepción PDO: " . $e->getMessage());
                        $errores[] = "Error de base de datos para la entrega #$entrega_id: " . $e->getMessage();
                    }
                }
                
                // Si hay al menos una calificación guardada y no hay errores, confirmar transacción
                if ($calificaciones_guardadas && empty($errores)) {
                    $db->commit();
                    escribirLog("Transacción confirmada exitosamente. $entregas_calificadas entregas calificadas.");
                } else {
                    $db->rollBack();
                    escribirLog("Transacción revertida debido a errores: " . implode(", ", $errores));
                }
            } catch (Exception $e) {
                $db->rollBack();
                escribirLog("Excepción general: " . $e->getMessage());
                $errores[] = "Error general: " . $e->getMessage();
            }
        } else {
            escribirLog("No se recibieron calificaciones para procesar");
            $errores[] = "No se recibieron calificaciones para procesar.";
        }
        
        // Redirigir después de calificar
        if ($calificaciones_guardadas && empty($errores)) {
            escribirLog("Redirigiendo a actividades_tutor.php con éxito");
            // Asegurarse de que la redirección sea correcta
            header("Location: actividades_tutor.php?filtro=calificadas&success=4");
            exit();
        } else {
            $error_mensaje = !empty($errores) ? implode("<br>", $errores) : "No se pudieron guardar las calificaciones. Asegúrate de ingresar valores válidos entre 0.0 y 5.0.";
            escribirLog("Mostrando mensaje de error: $error_mensaje");
        }
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
            escribirLog("Actividad no encontrada: $actividad_id");
            header("Location: actividades_tutor.php?error=1");
            exit();
        }
    } catch (PDOException $e) {
        escribirLog("Error al cargar la actividad: " . $e->getMessage());
        $error_mensaje = "Error al cargar la actividad: " . $e->getMessage();
    }

    // Obtener entregas pendientes de calificación
    try {
        // Modificar la consulta para eliminar la referencia a e.id_usuario que no existe
        $stmt = $db->prepare("
            SELECT e.id, e.fecha_entrega, e.comentario, e.calificacion, e.estado, e.fecha_calificacion,
                u.nombre as estudiante_nombre, e.id_estudiante,
                u.avatar as estudiante_avatar, u.email as estudiante_email,
                ae.nombre_archivo, ae.ruta_archivo, ae.tipo_archivo
            FROM entregas_actividad e
            LEFT JOIN estudiantes est ON e.id_estudiante = est.id
            LEFT JOIN usuarios u ON est.usuario_id = u.id
            LEFT JOIN archivos_entrega ae ON e.id = ae.id_entrega
            WHERE e.id_actividad = :actividad_id
            ORDER BY e.fecha_entrega DESC
        ");
        $stmt->bindParam(':actividad_id', $actividad_id);
        $stmt->execute();
        $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        escribirLog("Entregas encontradas: " . count($entregas));
        
    } catch (PDOException $e) {
        escribirLog("Error al cargar las entregas: " . $e->getMessage());
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

    // Función para formatear fecha
    function formatearFecha($fecha) {
        $timestamp = strtotime($fecha);
        $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        
        $dia_semana = $dias_semana[date('w', $timestamp)];
        $dia = date('j', $timestamp);
        $mes = $meses[date('n', $timestamp) - 1];
        $anio = date('Y', $timestamp);
        $hora = date('H:i', $timestamp);
        
        return "$dia_semana $dia de $mes de $anio a las $hora";
    }

    // Calcular días restantes o vencidos
    function calcularEstadoFecha($fecha_limite) {
        $hoy = time();
        $limite = strtotime($fecha_limite);
        $diferencia = $limite - $hoy;
        $dias = floor($diferencia / (60 * 60 * 24));
        
        if ($dias > 0) {
            return [
                'estado' => 'pendiente',
                'texto' => "Faltan $dias día(s)",
                'clase' => 'text-primary'
            ];
        } elseif ($dias == 0) {
            return [
                'estado' => 'hoy',
                'texto' => "Vence hoy",
                'clase' => 'text-warning'
            ];
        } else {
            return [
                'estado' => 'vencido',
                'texto' => "Vencida hace " . abs($dias) . " día(s)",
                'clase' => 'text-danger'
            ];
        }
    }

    // Contar entregas por estado
    $entregas_pendientes = 0;
    $entregas_calificadas = 0;
    $entregas_vencidas = 0;

    foreach ($entregas as $entrega) {
        if ($entrega['estado'] === 'calificado') {
            $entregas_calificadas++;
        } else {
            $fecha_limite = strtotime($actividad['fecha_limite'] . ' ' . $actividad['hora_limite']);
            $fecha_entrega = strtotime($entrega['fecha_entrega']);
            
            if ($fecha_entrega > $fecha_limite && !$actividad['permitir_entregas_tarde']) {
                $entregas_vencidas++;
            } else {
                $entregas_pendientes++;
            }
        }
    }

    $total_entregas = count($entregas);

    // Filtrar entregas pendientes (no calificadas y no vencidas, a menos que permita entregas tardías)
$entregas_pendientes_mostrar = array_filter($entregas, function($entrega) use ($actividad) {
    // Primero, excluir las que ya están calificadas
    if ($entrega['estado'] === 'calificado') {
        return false;
    }
    
    // Si permiten entregas tardías, mostrar todas las no calificadas
    if ($actividad['permitir_entregas_tarde']) {
        return true;
    }
    
    // Verificar si la entrega está vencida
    $fecha_limite = strtotime($actividad['fecha_limite'] . ' ' . $actividad['hora_limite']);
    $fecha_entrega = strtotime($entrega['fecha_entrega']);
    
    // Solo mostrar si no está vencida (entrega antes o igual al límite)
    return ($fecha_entrega <= $fecha_limite);
});
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
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
                color: #333;
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
                padding: 20px;
                border-radius: 8px;
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
                display: flex;
                align-items: center;
            }
            
            .header h1 i {
                margin-right: 10px;
            }
            
            .activity-card {
                background-color: white;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .activity-header {
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
                color: white;
                padding: 20px;
                position: relative;
            }
            
            .activity-title {
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0 0 10px 0;
                display: flex;
                align-items: center;
            }
            
            .activity-title i {
                margin-right: 10px;
            }
            
            .activity-subtitle {
                font-size: 1rem;
                opacity: 0.9;
                margin: 0;
            }
            
            .activity-status {
                position: absolute;
                top: 20px;
                right: 20px;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 500;
                background-color: rgba(255, 255, 255, 0.2);
            }
            
            .activity-body {
                padding: 25px;
            }
            
            .activity-description {
                color: #555;
                margin-bottom: 25px;
                line-height: 1.6;
                font-size: 1rem;
            }
            
            .activity-meta {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }
            
            .meta-item {
                display: flex;
                flex-direction: column;
            }
            
            .meta-label {
                font-size: 0.85rem;
                color: #6c757d;
                margin-bottom: 5px;
            }
            
            .meta-value {
                font-weight: 500;
                color: var(--dark);
                display: flex;
                align-items: center;
            }
            
            .meta-value i {
                margin-right: 8px;
                color: var(--primary);
                width: 20px;
                text-align: center;
            }
            
            .entregas-stats {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px;
            }
            
            .stat-card {
                flex: 1;
                background-color: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin: 0 10px;
                text-align: center;
                transition: transform 0.3s;
            }
            
            .stat-card:first-child {
                margin-left: 0;
            }
            
            .stat-card:last-child {
                margin-right: 0;
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                font-size: 1.5rem;
            }
            
            .stat-icon.total {
                background-color: rgba(0,166,61,0.1);
                color: var(--primary);
            }
            
            .stat-icon.pending {
                background-color: rgba(255, 193, 7, 0.1);
                color: var(--warning);
            }
            
            .stat-icon.graded {
                background-color: rgba(40, 167, 69, 0.1);
                color: var(--success);
            }
            
            .stat-value {
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: 5px;
            }
            
            .stat-label {
                color: #6c757d;
                font-size: 0.9rem;
            }
            
            .entregas-tabs {
                display: flex;
                background-color: white;
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .entregas-tab {
                flex: 1;
                text-align: center;
                padding: 15px;
                color: var(--dark);
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s;
                border-bottom: 3px solid transparent;
                cursor: pointer;
            }
            
            .entregas-tab:hover {
                background-color: #f8f9fa;
                text-decoration: none;
                color: var(--primary);
            }
            
            .entregas-tab.active {
                color: var(--primary);
                border-bottom-color: var(--primary);
                background-color: #f8f9fa;
            }
            
            .entrega-card {
                background-color: white;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: transform 0.3s;
                border-left: 5px solid transparent;
            }
            
            .entrega-card:hover {
                transform: translateY(-5px);
            }
            
            .entrega-card.pendiente {
                border-left-color: var(--warning);
            }
            
            .entrega-card.calificado {
                border-left-color: var(--success);
            }
            
            .entrega-header {
                padding: 20px;
                background-color: #f8f9fa;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #eee;
            }
            
            .estudiante-info {
                display: flex;
                align-items: center;
            }
            
            .estudiante-avatar {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                object-fit: cover;
                margin-right: 15px;
                border: 3px solid white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            .estudiante-datos {
                flex-grow: 1;
            }
            
            .estudiante-datos h4 {
                margin: 0;
                font-size: 1.1rem;
                color: var(--dark);
                font-weight: 600;
            }
            
            .estudiante-datos p {
                margin: 5px 0 0;
                font-size: 0.85rem;
                color: #6c757d;
                display: flex;
                align-items: center;
            }
            
            .estudiante-datos p i {
                margin-right: 5px;
                font-size: 0.8rem;
            }
            
            .entrega-estado {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
            }
            
            .entrega-badge {
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 500;
                margin-bottom: 5px;
            }
            
            .entrega-badge.pendiente {
                background-color: rgba(255, 193, 7, 0.2);
                color: #856404;
            }
            
            .entrega-badge.calificado {
                background-color: rgba(40, 167, 69, 0.2);
                color: #155724;
            }
            
            .entrega-fecha {
                font-size: 0.8rem;
                color: #6c757d;
            }
            
            .entrega-body {
                padding: 20px;
            }
            
            .entrega-comentario {
                background-color: #f8f9fa;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
                border-left: 3px solid #dee2e6;
            }
            
            .entrega-comentario h5 {
                margin-top: 0;
                font-size: 0.95rem;
                color: var(--dark);
                margin-bottom: 10px;
                display: flex;
                align-items: center;
            }
            
            .entrega-comentario h5 i {
                margin-right: 8px;
                color: var(--primary);
            }
            
            .entrega-comentario p {
                margin: 0;
                color: #555;
                line-height: 1.5;
            }
            
            .entrega-archivo {
                display: flex;
                align-items: center;
                background-color: #f8f9fa;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
                border: 1px solid #eee;
                transition: all 0.3s;
            }
            
            .entrega-archivo:hover {
                background-color: #e9ecef;
            }
            
            .archivo-icon {
                width: 40px;
                height: 40px;
                background-color: var(--primary);
                color: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.2rem;
                margin-right: 15px;
            }
            
            .archivo-info {
                flex-grow: 1;
            }
            
            .archivo-nombre {
                margin: 0;
                font-weight: 500;
                font-size: 0.95rem;
                color: var(--dark);
                margin-bottom: 3px;
            }
            
            .archivo-meta {
                font-size: 0.8rem;
                color: #6c757d;
            }
            
            .archivo-acciones {
                display: flex;
                gap: 15px;
            }
            
            .archivo-acciones a {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background-color: white;
                color: #6c757d;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                text-decoration: none;
            }
            
            .archivo-acciones a:hover {
                background-color: var(--primary);
                color: white;
                transform: translateY(-2px);
            }
            
            .calificacion-form {
                background-color: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-top: 20px;
                border-top: 1px solid #eee;
            }
            
            .calificacion-form h5 {
                margin-top: 0;
                margin-bottom: 20px;
                font-size: 1.1rem;
                color: var(--dark);
                display: flex;
                align-items: center;
            }
            
            .calificacion-form h5 i {
                margin-right: 8px;
                color: var(--primary);
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark);
                font-size: 0.95rem;
            }
            
            .form-control {
                display: block;
                width: 100%;
                padding: 10px 15px;
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 1rem;
                transition: border-color 0.3s;
            }
            
            .form-control:focus {
                border-color: var(--primary);
                outline: none;
                box-shadow: 0 0 0 0.2rem rgba(0,166,61,0.25);
            }
            
            .form-text {
                margin-top: 5px;
                font-size: 0.85rem;
                color: #6c757d;
            }
            
            .calificacion-input {
                display: flex;
                align-items: center;
            }
            
            .calificacion-input .form-control {
                max-width: 100px;
                text-align: center;
                font-weight: 600;
                font-size: 1.2rem;
                height: 50px;
            }
            
            .calificacion-escala {
                margin-left: 15px;
                color: #6c757d;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s;
                font-size: 0.95rem;
            }
            
            .btn-primary {
                background-color: var(--primary);
                border-color: var(--primary);
                color: white;
            }
            
            .btn-primary:hover {
                background-color: var(--primary-dark);
                border-color: var(--primary-dark);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .btn-secondary {
                background-color: #6c757d;
                border-color: #6c757d;
                color: white;
            }
            
            .btn-secondary:hover {
                background-color: #5a6268;
                border-color: #545b62;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .btn-success {
                background-color: var(--success);
                border-color: var(--success);
                color: white;
            }
            
            .btn-success:hover {
                background-color: #218838;
                border-color: #1e7e34;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .btn-lg {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .btn i {
                margin-right: 8px;
            }
            
            .alert {
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 25px;
                border-left: 4px solid transparent;
            }
            
            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border-left-color: #28a745;
            }
            
            .alert-danger {
                background-color: #f8d7da;
                color: #721c24;
                border-left-color: #dc3545;
            }
            
            .alert-info {
                background-color: #d1ecf1;
                color: #0c5460;
                border-left-color: #17a2b8;
            }
            
            .empty-message {
                text-align: center;
                padding: 50px 30px;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            }
            
            .empty-message i {
                font-size: 4rem;
                color: #dee2e6;
                margin-bottom: 20px;
            }
            
            .empty-message h3 {
                color: var(--dark);
                margin-bottom: 15px;
                font-weight: 600;
            }
            
            .empty-message p {
                color: #6c757d;
                margin-bottom: 25px;
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .breadcrumb {
                background-color: transparent;
                padding: 0;
                margin-bottom: 20px;
            }
            
            .breadcrumb-item a {
                color: var(--primary);
                text-decoration: none;
                transition: color 0.3s;
            }
            
            .breadcrumb-item a:hover {
                color: var(--primary-dark);
                text-decoration: underline;
            }
            
            .breadcrumb-item.active {
                color: var(--dark);
            }
            
            .calificacion-actual {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 8px;
            }
            
            .calificacion-valor {
                font-size: 2rem;
                font-weight: 700;
                color: var(--success);
                margin-right: 15px;
            }
            
            .calificacion-info {
                flex-grow: 1;
            }
            
            .calificacion-info h6 {
                margin: 0 0 5px 0;
                font-size: 0.9rem;
                color: #6c757d;
            }
            
            .calificacion-info p {
                margin: 0;
                font-size: 0.85rem;
                color: #6c757d;
            }
            
            .is-invalid {
                border-color: #dc3545 !important;
            }
            
            .is-valid {
                border-color: #28a745 !important;
            }
            
            .invalid-feedback {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 80%;
                color: #dc3545;
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
                
                .entregas-stats {
                    flex-direction: column;
                }
                
                .stat-card {
                    margin: 0 0 15px 0;
                }
                
                .activity-meta {
                    grid-template-columns: 1fr;
                }
            }
            .actividad-label {
                display: block;
                color: #6c757d;
                background: #f1f3f5;
                border-radius: 6px;
                padding: 10px 18px;
                font-style: italic;
                font-size: 1.05rem;
                margin: 18px 0 28px 0;
                box-shadow: 0 1px 4px rgba(0,0,0,0.04);
                letter-spacing: 0.5px;
                border-left: 4px solid #dee2e6;
                width: fit-content;
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
                <div class="tutor-profile" style="margin-top: 15px; display: flex; align-items: center; background: var(--primary); border-radius: 8px; padding: 10px 12px;">
    <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
        <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
    </div>
    <div style="color: #fff;">
        <div style="font-weight: 500; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
            <?php
                $nombre_tutor = isset($tutor['nombre']) && isset($tutor['apellido'])
                    ? htmlspecialchars($tutor['nombre'] . ' ' . $tutor['apellido'])
                    : 'Derek Agmeth Quevedo';
                echo $nombre_tutor;
            ?>
        </div>
        <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Académico</div>
    </div>
</div>
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
                    <li class="breadcrumb-item active">Revisar Entregas</li>
                </ol>
            </nav>
            
            <div class="header">
                <h1><i class="fas fa-clipboard-check"></i> Revisar y Calificar Entregas</h1>
                <a href="actividades_tutor.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Actividades
                </a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i> Las calificaciones han sido guardadas exitosamente. Las entregas calificadas ya no pueden ser modificadas.
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_mensaje)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_mensaje; ?>
                </div>
            <?php endif; ?>
            
            <div class="activity-card">
                <div class="activity-header">
                    <h2 class="activity-title">
                        <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                        <?php echo htmlspecialchars($actividad['titulo']); ?>
                    </h2>
                    <p class="activity-subtitle">
                        <?php echo ucfirst($actividad['tipo']); ?> | Puntaje máximo: <?php echo $actividad['puntaje']; ?>
                    </p>
                    
                    <?php 
                        $estado_fecha = calcularEstadoFecha($actividad['fecha_limite']);
                    ?>
                    <div class="activity-status">
                        <?php echo $estado_fecha['texto']; ?>
                    </div>
                </div>
                <div class="activity-body">
                    <div class="activity-description">
                        <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                    </div>
                    
                    <div class="activity-meta">
                        <div class="meta-item">
                            <div class="meta-label">Fecha de creación</div>
                            <div class="meta-value">
                                <i class="fas fa-calendar-plus"></i>
                                <?php echo date('d/m/Y', strtotime($actividad['fecha_creacion'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Fecha límite</div>
                            <div class="meta-value">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Hora límite</div>
                            <div class="meta-value">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($actividad['hora_limite'])); ?> hrs
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Tipo de actividad</div>
                            <div class="meta-value">
                                <i class="fas <?php echo obtenerIconoActividad($actividad['tipo']); ?>"></i>
                                <?php echo ucfirst($actividad['tipo']); ?>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Puntaje máximo</div>
                            <div class="meta-value">
                                <i class="fas fa-star"></i>
                                <?php echo $actividad['puntaje']; ?> puntos
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-label">Entregas tardías</div>
                            <div class="meta-value">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $actividad['permitir_entregas_tarde'] ? 'Permitidas' : 'No permitidas'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <span class="actividad-label"><i class="fas fa-info-circle mr-1"></i>De esta actividad</span>
            <div class="entregas-stats">
                <?php if ($total_entregas > 0): ?>
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_entregas; ?></div>
                        <div class="stat-label">Total de Entregas</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo $entregas_pendientes; ?></div>
                        <div class="stat-label">Pendientes Calificar</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon graded">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $entregas_calificadas; ?></div>
                        <div class="stat-label">Calificado</div>
                    </div>
                    
                    <?php if ($entregas_vencidas > 0): ?>
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: var(--danger);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-value"><?php echo $entregas_vencidas; ?></div>
                        <div class="stat-label">Vencidas</div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <form method="post" action="" id="form-calificaciones">
                <input type="hidden" name="calificar" value="1">
                <div id="entregas-container">
                    <?php if (count($entregas_pendientes_mostrar) > 0): ?>
                        <?php foreach ($entregas_pendientes_mostrar as $entrega): ?>
                            <div class="entrega-card <?php echo ($entrega['estado'] === 'calificado') ? 'calificado' : 'pendiente'; ?>" data-estado="<?php echo ($entrega['estado'] === 'calificado') ? 'calificado' : 'pendiente'; ?>">
                                <div class="entrega-header">
                                    <div class="estudiante-info">
                                        <img src="<?php echo $entrega['estudiante_avatar'] ?: '/placeholder.svg?height=50&width=50'; ?>" alt="Avatar" class="estudiante-avatar">
                                        <div class="estudiante-datos">
                                            <h4><?php echo htmlspecialchars($entrega['estudiante_nombre'] ?: 'Estudiante ID: '.$entrega['id_estudiante']); ?></h4>
                                            <p><i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($entrega['id_estudiante']); ?></p>
                                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($entrega['estudiante_email'] ?: 'No disponible'); ?></p>
                                        </div>
                                    </div>
                                    <div class="entrega-estado">
                                        <?php if ($entrega['estado'] === 'calificado'): ?>
                                            <span class="entrega-badge calificado">
                                                <i class="fas fa-check-circle mr-1"></i> Calificado
                                            </span>
                                        <?php else: ?>
                                            <span class="entrega-badge pendiente">
                                                <i class="fas fa-clock mr-1"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                        <span class="entrega-fecha">
                                            <i class="far fa-calendar-alt mr-1"></i> Entregado: <?php echo formatearFecha($entrega['fecha_entrega']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="entrega-body">
                                    <?php if (!empty($entrega['comentario'])): ?>
                                        <div class="entrega-comentario">
                                            <h5><i class="far fa-comment-alt"></i> Comentario del estudiante:</h5>
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
                                                <p class="archivo-meta">Archivo adjunto</p>
                                            </div>
                                            <div class="archivo-acciones">
                                                <a href="<?php echo $entrega['ruta_archivo']; ?>" target="_blank" title="Ver archivo">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                               
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entrega['estado'] === 'calificado'): ?>
                                        <div class="calificacion-actual">
                                            <div class="calificacion-valor"><?php echo number_format($entrega['calificacion'], 1); ?></div>
                                            <div class="calificacion-info">
                                                <h6>Calificación actual</h6>
                                                <p>Calificado el <?php echo date('d/m/Y', strtotime($entrega['fecha_calificacion'] ?? $entrega['fecha_entrega'])); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($entrega['comentario_tutor'])): ?>
                                            <div class="entrega-comentario">
                                                <h5><i class="far fa-comment-dots"></i> Retroalimentación del tutor:</h5>
                                                <p><?php echo nl2br(htmlspecialchars($entrega['comentario_tutor'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-2"></i> Esta entrega ya ha sido calificada y no puede ser modificada.
                                        </div>
                                    <?php else: ?>
                                        <div class="calificacion-form">
                                            <h5><i class="fas fa-star"></i> Calificación</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="calificacion-<?php echo $entrega['id']; ?>">0.0 / 5.0 <span class="text-class=meta-value">*</span></label>
                                                        <div class="calificacion-input">
                                                            <input type="text" class="form-control calificacion-input-field" 
                                                            id="calificacion-<?php echo $entrega['id']; ?>" 
                                                            name="calificaciones[<?php echo $entrega['id']; ?>][calificacion]" 
                                                            required
                                                            value="<?php echo $entrega['calificacion'] ?? ''; ?>">
                                                            <span class="calificacion-escala"></span>
                                                        </div>
                                                        <small class="form-text text-muted">Nota: la calificacion sera a criterio del tutor </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="form-group">
                                                        <label for="comentario-<?php echo $entrega['id']; ?>">Retroalimentación para el estudiante</label>
                                                        <textarea class="form-control" id="comentario-<?php echo $entrega['id']; ?>" 
                                                                name="calificaciones[<?php echo $entrega['id']; ?>][comentario]" 
                                                                rows="4" placeholder="Escribe aquí tus comentarios y retroalimentación para el estudiante..."><?php echo $entrega['comentario_tutor'] ?? ''; ?></textarea>
                                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($entregas_pendientes > 0): ?>
                            <div class="text-center mt-4 mb-5">
                                <button type="submit" class="btn btn-success btn-lg" id="btn-guardar-calificaciones">
                                    <i class="fas fa-save"></i> Guardar Todas las Calificaciones
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> Todas las entregas ya han sido calificadas.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (count($entregas_pendientes_mostrar) === 0): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i> 
            <?php if ($total_entregas === 0): ?>
                No se han recibido entregas para esta actividad.
            <?php elseif ($entregas_calificadas === $total_entregas): ?>
                Todas las entregas han sido calificadas.
            <?php else: ?>
                No hay entregas pendientes por calificar (las entregas restantes están vencidas).
            <?php endif; ?>
        </div>
    <?php endif; ?>
                </div>
            </form>
        </main>
        
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <script>
            // Auto-ocultar alertas después de 10 segundos (aumentado para dar más tiempo de lectura)
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }, 10000);
            
            // Validar y formatear campos de calificación
            document.querySelectorAll('.calificacion-input-field').forEach(function(input) {
                // Validar al escribir
                input.addEventListener('input', function(e) {
                    // Permitir solo números, punto y coma
                    let value = this.value;
                    
                    // Eliminar caracteres no válidos (solo permitir números, punto y coma)
                    value = value.replace(/[^\d.,]/g, '');
                    
                    // Reemplazar comas por puntos
                    value = value.replace(',', '.');
                    
                    // Limitar a un solo punto decimal
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    
                    // Limitar el primer dígito a 0-5
                    if (value.length > 0 && (isNaN(parseInt(value[0])) || parseInt(value[0]) > 5)) {
                        value = '5' + value.substring(1);
                    }
                    
                    // Limitar a 3 caracteres (X.X)
                    if (value.length > 3) {
                        value = value.substring(0, 3);
                    }
                    
                    // Actualizar el valor
                    this.value = value;
                    
                    // Validar el rango
                    const numValue = parseFloat(value);
                    if (value !== '' && (isNaN(numValue) || numValue < 0 || numValue > 5)) {
                        this.classList.add('is-invalid');
                        
                        // Crear mensaje de error si no existe
                        let errorDiv = this.parentNode.querySelector('.invalid-feedback');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            errorDiv.textContent = 'Ingresa un valor entre 0.0 y 5.0';
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
                    if (!isNaN(numValue) && numValue >= 0 && numValue <= 5) {
                        // Formatear a 1 decimal
                        this.value = numValue.toFixed(1);
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.add('is-invalid');
                    }
                });
            });
            
            // Validar formulario antes de enviar
            document.getElementById('form-calificaciones').addEventListener('submit', function(e) {
                let inputs = document.querySelectorAll('.calificacion-input-field:not([disabled])');
                let valid = true;
                let errorMessages = [];
                
                inputs.forEach(function(input) {
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
                    if (value < 0 || value > 5) {
                        valid = false;
                        input.classList.add('is-invalid');
                        errorMessages.push('Las calificaciones deben estar entre 0.0 y 5.0.');
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
                
                // Confirmar antes de enviar
                if (!confirm('¿Estás seguro de guardar las calificaciones? Esta acción no se puede deshacer.')) {
                    e.preventDefault();
                    return false;
                }

                // Mostrar indicador de carga
                const submitButton = document.getElementById('btn-guardar-calificaciones');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                }
                
                return true;
            });
        </script>
    </body>
    </html>


