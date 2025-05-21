<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php');

// Verificar si el usuario está logueado y es tutor
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id']) || $_SESSION['usuario']['rol'] !== 'tutor') {
    header('Location: /views/general/login.php');
    exit();
}

$tutor_id = $_SESSION['usuario']['id'];
$nombre_tutor = $_SESSION['usuario']['nombre'];

// --- Lógica para manejar solicitudes AJAX ---
// --- Lógica para manejar solicitudes AJAX ---
if (isset($_REQUEST['ajax_action'])) {
    $ajax_action = $_REQUEST['ajax_action'];

    switch ($ajax_action) {
        case 'get_messages':
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['pasantia_id'])) {
                $pasantia_id = filter_input(INPUT_GET, 'pasantia_id', FILTER_VALIDATE_INT);

                if ($pasantia_id) {
                    try {
                        // Obtener mensajes para la pasantía, asegurando que pertenezca a este tutor
                        $query = "SELECT m.*, u.nombre AS emisor_nombre, p.estudiante_id AS pasantia_estudiante_id
                                  FROM mensajes_chat m
                                  INNER JOIN pasantias p ON m.pasantia_id = p.id
                                  INNER JOIN usuarios u ON m.emisor_id = u.id
                                  WHERE m.pasantia_id = ? AND p.tutor_id = ?
                                  ORDER BY m.fecha_envio ASC";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$pasantia_id, $tutor_id]);
                        $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $stmt->closeCursor();

                        // Verificar si la pasantía existe y pertenece al tutor, incluso si no hay mensajes
                        $estudiante_id_chat = null;
                        $query_check_pasantia = "SELECT estudiante_id FROM pasantias WHERE id = ? AND tutor_id = ?";
                        $stmt_check_pasantia = $conexion->prepare($query_check_pasantia);
                        $stmt_check_pasantia->execute([$pasantia_id, $tutor_id]);
                        $pasantia_info_chat = $stmt_check_pasantia->fetch(PDO::FETCH_ASSOC);
                        $stmt_check_pasantia->closeCursor();

                        if (!$pasantia_info_chat) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado a esta pasantía']);
                            exit();
                        }

                        $estudiante_id_chat = $pasantia_info_chat['estudiante_id'];

                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'mensajes' => $mensajes,
                            'tutor_id' => $tutor_id,
                            'estudiante_id_chat' => $estudiante_id_chat
                        ]);

                    } catch (PDOException $e) {
                        error_log("Error fetching chat messages (tutor): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Database error fetching messages']);
                    } catch (Exception $e) {
                        error_log("Error fetching chat messages (tutor, general): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Server error fetching messages']);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de pasantía inválido']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para obtener mensajes']);
            }
            exit();

        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pasantia_id'], $_POST['receptor_id'])) {
                $pasantia_id = filter_input(INPUT_POST, 'pasantia_id', FILTER_VALIDATE_INT);
                $receptor_id = filter_input(INPUT_POST, 'receptor_id', FILTER_VALIDATE_INT);
                $mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_UNSAFE_RAW);
                $archivo = $_FILES['archivo'] ?? null;

                $errores_chat = [];
                $archivo_nombre = null;
                $upload_dir = __DIR__ . "/../../uploads/pasantias/chat/";

                if (!$pasantia_id || !$receptor_id || !$tutor_id) {
                    $errores_chat[] = "Faltan IDs necesarios.";
                }

                if (empty($errores_chat)) {
                    try {
                        $query_check = "SELECT estudiante_id FROM pasantias WHERE id = ? AND tutor_id = ?";
                        $stmt_check = $conexion->prepare($query_check);
                        $stmt_check->execute([$pasantia_id, $tutor_id]);
                        $pasantia_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        $stmt_check->closeCursor();

                        if (!$pasantia_info) {
                            $errores_chat[] = "No autorizado para enviar mensajes a esta pasantía.";
                        } elseif ($pasantia_info['estudiante_id'] != $receptor_id) {
                             $errores_chat[] = "El receptor del mensaje no corresponde al estudiante de esta pasantía.";
                        }

                    } catch (PDOException $e) {
                        error_log("Error checking pasantia details for chat send (tutor): " . $e->getMessage());
                        $errores_chat[] = "Error de base de datos al verificar pasantía.";
                    }
                }

                if (empty(trim($mensaje)) && (!$archivo || $archivo['size'] == 0)) {
                     $errores_chat[] = "No puedes enviar un mensaje vacío sin archivo adjunto.";
                }

                if ($archivo && $archivo['size'] > 0 && empty($errores_chat)) {
                    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'pptx'];
                    $max_size = 10 * 1024 * 1024; // 10MB

                    $file_info = pathinfo($archivo['name']);
                    $file_extension = strtolower($file_info['extension'] ?? '');

                    if (!in_array($file_extension, $allowed_types)) {
                        $errores_chat[] = "Tipo de archivo no permitido. Tipos: " . implode(', ', $allowed_types);
                    } elseif ($archivo['size'] > $max_size) {
                        $errores_chat[] = "El archivo es demasiado grande. Máximo " . ($max_size / 1024 / 1024) . "MB.";
                    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
                         $errores_chat[] = "Error al subir el archivo: Código " . $archivo['error'];
                    }

                    if (empty($errores_chat)) {
                        if (!file_exists($upload_dir)) {
                             mkdir($upload_dir, 0775, true);
                        }
                         $sanitized_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['filename']);
                         $archivo_nombre = $pasantia_id . "_chat_" . time() . "_" . $sanitized_filename . "." . $file_extension;
                         $target_file = $upload_dir . $archivo_nombre;

                         if (!move_uploaded_file($archivo['tmp_name'], $target_file)) {
                             $errores_chat[] = "Error técnico al mover el archivo subido.";
                             $archivo_nombre = null;
                             error_log("Error moviendo archivo de chat (tutor): " . $archivo['tmp_name'] . " a " . $target_file);
                        }
                    }
                }

                if (empty($errores_chat)) {
                    try {
                        $query = "INSERT INTO mensajes_chat (pasantia_id, emisor_id, receptor_id, mensaje, archivo) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$pasantia_id, $tutor_id, $receptor_id, trim($mensaje), $archivo_nombre]);
                        $stmt->closeCursor();

                        // Actualizar timestamp de último mensaje
                        $query_update_pasantia = "UPDATE pasantias SET last_message_at = NOW() WHERE id = ?";
                        $stmt_update_pasantia = $conexion->prepare($query_update_pasantia);
                        $stmt_update_pasantia->execute([$pasantia_id]);
                        $stmt_update_pasantia->closeCursor();

                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);

                    } catch (PDOException $e) {
                        error_log("Error DB al enviar mensaje (tutor): " . $e->getMessage());
                         if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                             unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error en la base de datos al enviar mensaje']);
                    } catch (Exception $e) {
                        error_log("Error general al enviar mensaje (tutor): " . $e->getMessage());
                         if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                             unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error del servidor al enviar mensaje']);
                    }

                } else {
                     if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                         unlink($upload_dir . $archivo_nombre);
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => implode(', ', $errores_chat)]);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para enviar mensaje']);
            }
            exit();

        case 'get_detalle':
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
                $pasantia_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

                if ($pasantia_id) {
                    try {
                        $query_pasantia = "SELECT p.*, u.id AS estudiante_id, u.nombre AS estudiante_nombre, u.codigo_estudiante, u.email AS estudiante_email, u.ciclo
                                             FROM pasantias p
                                             INNER JOIN usuarios u ON p.estudiante_id = u.id
                                             WHERE p.id = ? AND p.tutor_id = ?";
                        $stmt_pasantia = $conexion->prepare($query_pasantia);
                        $stmt_pasantia->execute([$pasantia_id, $tutor_id]);
                        $pasantia_detalle = $stmt_pasantia->fetch(PDO::FETCH_ASSOC);
                        $stmt_pasantia->closeCursor();

                        if ($pasantia_detalle) {
                            $query_avances = "SELECT * FROM entregas_pasantia WHERE pasantia_id = ? ORDER BY numero_avance ASC";
                            $stmt_avances = $conexion->prepare($query_avances);
                            $stmt_avances->execute([$pasantia_id]);
                            $avances_detalle = $stmt_avances->fetchAll(PDO::FETCH_ASSOC);
                            $stmt_avances->closeCursor();

                            $pasantia_detalle['avances'] = $avances_detalle;

                            $total_aprobados = 0;
                            $total_entregas_contadas = 0;
                            $todos_avances_aprobados = true;

                            foreach($avances_detalle as $avance) {
                                $total_entregas_contadas++;
                                if ($avance['estado'] === 'aprobado') {
                                    $total_aprobados++;
                                } else {
                                    $todos_avances_aprobados = false;
                                }
                            }
                            $pasantia_detalle['puede_finalizar'] = ($total_entregas_contadas === 4 && $todos_avances_aprobados && $pasantia_detalle['estado'] === 'en_proceso');

                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'pasantia' => $pasantia_detalle]);

                        } else {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Pasantía no encontrada o no asignada']);
                        }

                    } catch (PDOException $e) {
                        error_log("Error fetching pasantia detail (tutor): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Database error fetching detail']);
                    } catch (Exception $e) {
                        error_log("Error fetching pasantia detail (tutor, general): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Server error fetching detail']);
                    }

                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de pasantía inválido']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para obtener detalle']);
            }
            exit();

        case 'get_proyectos':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                try {
                    // Registrar información de depuración
                    error_log("Iniciando get_proyectos para tutor_id: $tutor_id");
                    
                    // Primero verificar si hay estudiantes asignados a este tutor
                    $query_check_estudiantes = "SELECT COUNT(*) as total_estudiantes FROM pasantias WHERE tutor_id = ?";
                    $stmt_check = $conexion->prepare($query_check_estudiantes);
                    $stmt_check->execute([$tutor_id]);
                    $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    $stmt_check->closeCursor();
                    
                    if ($result_check['total_estudiantes'] == 0) {
                        error_log("No hay estudiantes asignados al tutor_id: $tutor_id");
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true, 
                            'proyectos' => [],
                            'debug_info' => "No hay estudiantes asignados a este tutor"
                        ]);
                        exit();
                    }
                    
                    error_log("El tutor tiene {$result_check['total_estudiantes']} estudiantes asignados");
                    
                    // Consulta para obtener proyectos a través de estudiantes asignados al tutor
                    $query_proyectos = "SELECT DISTINCT p.* 
                                       FROM proyectos p
                                       INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
                                       INNER JOIN pasantias pa ON ep.estudiante_id = pa.estudiante_id
                                       WHERE pa.tutor_id = ?
                                       ORDER BY p.fecha_creacion DESC";
                    
                    error_log("SQL Query: " . $query_proyectos);
                    $stmt_proyectos = $conexion->prepare($query_proyectos);
                    $stmt_proyectos->execute([$tutor_id]);
                    $proyectos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);
                    $stmt_proyectos->closeCursor();
                    
                    error_log("Encontrados " . count($proyectos) . " proyectos para el tutor_id: $tutor_id");
                    
                    // Para cada proyecto, obtener los estudiantes y avances
                    foreach ($proyectos as &$proyecto) {
                        // Obtener avances aprobados
                        $query_avances_aprobados = "SELECT COUNT(*) as total 
                                                   FROM avances_proyecto 
                                                   WHERE proyecto_id = ? AND estado = 'aprobado'";
                        $stmt_avances = $conexion->prepare($query_avances_aprobados);
                        $stmt_avances->execute([$proyecto['id']]);
                        $avances_result = $stmt_avances->fetch(PDO::FETCH_ASSOC);
                        $stmt_avances->closeCursor();
                        
                        $proyecto['avances_aprobados'] = $avances_result['total'] ?? 0;
                        
                        // Obtener estudiantes del proyecto
                        $query_estudiantes = "SELECT u.id, u.nombre, u.codigo_estudiante, u.email, ep.rol_en_proyecto
                                             FROM usuarios u
                                             INNER JOIN estudiantes_proyecto ep ON u.id = ep.estudiante_id
                                             WHERE ep.proyecto_id = ?";
                        $stmt_estudiantes = $conexion->prepare($query_estudiantes);
                        $stmt_estudiantes->execute([$proyecto['id']]);
                        $proyecto['estudiantes'] = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
                        $stmt_estudiantes->closeCursor();
                        
                        error_log("Proyecto ID {$proyecto['id']} tiene " . count($proyecto['estudiantes']) . " estudiantes");
                        
                        // Calcular el avance actual (1-4)
                        $query_avance = "SELECT MAX(numero_avance) as ultimo_avance
                                        FROM avances_proyecto
                                        WHERE proyecto_id = ?";
                        $stmt_avance = $conexion->prepare($query_avance);
                        $stmt_avance->execute([$proyecto['id']]);
                        $avance_result = $stmt_avance->fetch(PDO::FETCH_ASSOC);
                        $stmt_avance->closeCursor();
                        
                        $proyecto['avance_actual'] = $avance_result['ultimo_avance'] ?? 0;
                        
                        // Calcular progreso
                        $proyecto['progreso'] = ($proyecto['avances_aprobados'] / 4) * 100; // Asumiendo 4 avances totales
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'proyectos' => $proyectos,
                        'debug_info' => "Consulta exitosa: " . count($proyectos) . " proyectos encontrados"
                    ]);
                    
                } catch (PDOException $e) {
                    error_log("Error PDO en get_proyectos: " . $e->getMessage());
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error de base de datos al obtener proyectos',
                        'debug_info' => $e->getMessage()
                    ]);
                } catch (Exception $e) {
                    error_log("Error general en get_proyectos: " . $e->getMessage());
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error del servidor al obtener proyectos',
                        'debug_info' => $e->getMessage()
                    ]);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            }
            exit();
            
        case 'get_detalle_proyecto':
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
                $proyecto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

                if ($proyecto_id) {
                    try {
                        // Verificar primero si el tutor tiene acceso a este proyecto
                        $query_check = "SELECT COUNT(*) as tiene_acceso
                                       FROM estudiantes_proyecto ep
                                       INNER JOIN pasantias pa ON ep.estudiante_id = pa.estudiante_id
                                       WHERE ep.proyecto_id = ? AND pa.tutor_id = ?";
                        $stmt_check = $conexion->prepare($query_check);
                        $stmt_check->execute([$proyecto_id, $tutor_id]);
                        $check_result = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        $stmt_check->closeCursor();
                        
                        if (!$check_result || $check_result['tiene_acceso'] == 0) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'No tienes acceso a este proyecto']);
                            exit();
                        }
                        
                        // Obtener detalles del proyecto
                        $query_proyecto = "SELECT p.*, 
                                          (SELECT nombre FROM usuarios WHERE id = (
                                              SELECT tutor_id FROM pasantias WHERE estudiante_id IN (
                                                  SELECT estudiante_id FROM estudiantes_proyecto WHERE proyecto_id = p.id
                                              ) LIMIT 1
                                          )) as tutor_nombre
                                          FROM proyectos p
                                          WHERE p.id = ?";
                        $stmt_proyecto = $conexion->prepare($query_proyecto);
                        $stmt_proyecto->execute([$proyecto_id]);
                        $proyecto = $stmt_proyecto->fetch(PDO::FETCH_ASSOC);
                        $stmt_proyecto->closeCursor();
                        
                        if ($proyecto) {
                            try {
                                // Obtener estudiantes del proyecto
                                $query_estudiantes = "SELECT u.id, u.nombre, u.codigo_estudiante, u.email, ep.rol_en_proyecto
                                                     FROM usuarios u
                                                     INNER JOIN estudiantes_proyecto ep ON u.id = ep.estudiante_id
                                                     WHERE ep.proyecto_id = ?";
                                $stmt_estudiantes = $conexion->prepare($query_estudiantes);
                                $stmt_estudiantes->execute([$proyecto_id]);
                                $proyecto['estudiantes'] = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
                                $stmt_estudiantes->closeCursor();
                            } catch (PDOException $e) {
                                error_log("Error al obtener estudiantes para detalle de proyecto ID {$proyecto_id}: " . $e->getMessage());
                                $proyecto['estudiantes'] = [];
                            }
                            
                            try {
                                // Obtener avances del proyecto
                                $query_avances = "SELECT * FROM avances_proyecto
                                                 WHERE proyecto_id = ?
                                                 ORDER BY numero_avance ASC";
                                $stmt_avances = $conexion->prepare($query_avances);
                                $stmt_avances->execute([$proyecto_id]);
                                $proyecto['avances'] = $stmt_avances->fetchAll(PDO::FETCH_ASSOC);
                                $stmt_avances->closeCursor();
                            } catch (PDOException $e) {
                                error_log("Error al obtener avances para detalle de proyecto ID {$proyecto_id}: " . $e->getMessage());
                                $proyecto['avances'] = [];
                            }
                            
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'proyecto' => $proyecto]);
                        } else {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
                        }
                    } catch (PDOException $e) {
                        error_log("Error fetching proyecto detail (tutor): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error de base de datos al obtener detalle: ' . $e->getMessage()]);
                    } catch (Exception $e) {
                        error_log("Error general fetching proyecto detail (tutor): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error del servidor al obtener detalle: ' . $e->getMessage()]);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para obtener detalle']);
            }
            exit();

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Acción AJAX no reconocida']);
            exit();
    }
}


            









    


// --- Lógica PHP para la carga inicial de la página ---
$query = "SELECT p.*, u.id AS estudiante_id, u.nombre AS estudiante_nombre, u.codigo_estudiante, u.email AS estudiante_email, u.ciclo
          FROM pasantias p
          INNER JOIN usuarios u ON p.estudiante_id = u.id
          WHERE p.tutor_id = ?
          ORDER BY p.fecha_creacion DESC";
$stmt = $conexion->prepare($query);
$stmt->bindParam(1, $tutor_id, PDO::PARAM_INT);
$stmt->execute();
$pasantias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Obtener estadísticas
$total_pasantias = count($pasantias);
$pasantias_finalizadas = 0;
$pasantias_en_curso = 0;
$pasantias_retrasadas = 0;

foreach ($pasantias as $pasantia) {
    if ($pasantia['estado'] == 'finalizada') {
        $pasantias_finalizadas++;
    } elseif ($pasantia['estado'] == 'en_proceso') {
        $pasantias_en_curso++;

        // Verificar si hay retraso (más de 30 días sin avances)
        $query = "SELECT MAX(fecha_entrega) as ultima_entrega
                  FROM entregas_pasantia
                  WHERE pasantia_id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(1, $pasantia['id'], PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($resultado['ultima_entrega']) {
            $ultima_entrega = strtotime($resultado['ultima_entrega']);
            $dias_sin_avance = floor((time() - $ultima_entrega) / (60 * 60 * 24));
            if ($dias_sin_avance > 30) {
                $pasantias_retrasadas++;
            }
        }
    }
}

// Obtener total de avances corregidos
$query = "SELECT COUNT(*) as total_avances
          FROM entregas_pasantia e
          INNER JOIN pasantias p ON e.pasantia_id = p.id
          WHERE p.tutor_id = ? AND e.estado IN ('aprobado', 'corregir', 'revisado')";
$stmt = $conexion->prepare($query);
$stmt->bindParam(1, $tutor_id, PDO::PARAM_INT);
$stmt->execute();
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$total_avances_corregidos = $resultado['total_avances'];
$stmt->closeCursor();

// Procesar calificación de avance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar_avance'])) {
    $entrega_id = filter_input(INPUT_POST, 'entrega_id', FILTER_VALIDATE_INT);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_SPECIAL_CHARS);
    $nota = isset($_POST['nota']) ? filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_FLOAT) : null;
    $comentario = filter_input(INPUT_POST, 'comentario', FILTER_UNSAFE_RAW);

    $error = null;

    if ($nota !== null && ($nota < 0 || $nota > 5)) {
        $error = "La nota debe estar entre 0 y 5";
    }

    $allowed_states = ['aprobado', 'corregir', 'revisado'];
    if (!in_array($estado, $allowed_states)) {
        $error = "Estado de calificación inválido.";
    }

    if ($error === null) {
        try {
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Actualizar el estado del avance
            $query = "UPDATE entregas_pasantia
                      SET estado = ?, nota = ?, comentario_tutor = ?
                      WHERE id = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bindParam(1, $estado, PDO::PARAM_STR);
            $stmt->bindParam(2, $nota, PDO::PARAM_STR);
            $stmt->bindParam(3, $comentario, PDO::PARAM_STR);
            $stmt->bindParam(4, $entrega_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Si el estado es "aprobado", verificar si todos los avances están aprobados
                if ($estado === 'aprobado') {
                    // Obtener el ID de la pasantía asociada a este avance
                    $query_pasantia_id = "SELECT pasantia_id FROM entregas_pasantia WHERE id = ?";
                    $stmt_pasantia_id = $conexion->prepare($query_pasantia_id);
                    $stmt_pasantia_id->bindParam(1, $entrega_id, PDO::PARAM_INT);
                    $stmt_pasantia_id->execute();
                    $pasantia_id_result = $stmt_pasantia_id->fetch(PDO::FETCH_ASSOC);
                    $stmt_pasantia_id->closeCursor();
                    
                    if ($pasantia_id_result) {
                        $pasantia_id = $pasantia_id_result['pasantia_id'];
                        
                        // Verificar si todos los avances están aprobados
                        $query_check_all = "SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados
                                           FROM entregas_pasantia 
                                           WHERE pasantia_id = ?";
                        $stmt_check_all = $conexion->prepare($query_check_all);
                        $stmt_check_all->bindParam(1, $pasantia_id, PDO::PARAM_INT);
                        $stmt_check_all->execute();
                        $check_result = $stmt_check_all->fetch(PDO::FETCH_ASSOC);
                        $stmt_check_all->closeCursor();
                        
                        // Si hay 4 avances y todos están aprobados, actualizar el estado de la pasantía a "aprobada"
                        if ($check_result['total'] == 4 && $check_result['aprobados'] == 4) {
                            $query_update_pasantia = "UPDATE pasantias SET estado = 'aprobada' WHERE id = ? AND estado = 'en_proceso'";
                            $stmt_update_pasantia = $conexion->prepare($query_update_pasantia);
                            $stmt_update_pasantia->bindParam(1, $pasantia_id, PDO::PARAM_INT);
                            $stmt_update_pasantia->execute();
                            $stmt_update_pasantia->closeCursor();
                            
                            // Registrar en el log que la pasantía ha sido aprobada automáticamente
                            error_log("Pasantía ID: $pasantia_id ha sido aprobada automáticamente al completar los 4 avances.");
                        }
                    }
                }

                // Obtener información del estudiante para enviar correo
                $query_info = "SELECT u.email, u.nombre, p.titulo, e.numero_avance
                               FROM entregas_pasantia e
                               INNER JOIN pasantias p ON e.pasantia_id = p.id
                               INNER JOIN usuarios u ON p.estudiante_id = u.id
                               WHERE e.id = ?";
                $stmt_info = $conexion->prepare($query_info);
                $stmt_info->bindParam(1, $entrega_id, PDO::PARAM_INT);
                $stmt_info->execute();
                $info_estudiante = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $stmt_info->closeCursor();

                if ($info_estudiante) {
                    // Enviar correo al estudiante
                    $to = $info_estudiante['email'];
                    $subject = "Actualización sobre tu Avance " . $info_estudiante['numero_avance'] . " de Pasantía";
                    $message = "Hola " . $info_estudiante['nombre'] . ",\n\n";
                    $message .= "Tu tutor ha revisado tu Avance " . $info_estudiante['numero_avance'] . " para la pasantía '" . $info_estudiante['titulo'] . "'.\n";
                    $message .= "Estado: " . ucfirst($estado) . "\n";
                    if ($nota !== null) {
                        $message .= "Nota: " . number_format($nota, 1) . "\n";
                    }
                    if (!empty(trim($comentario))) {
                        $message .= "Comentarios del Tutor: " . $comentario . "\n";
                    }
                    $message .= "\nPor favor, revisa el portal de pasantías para más detalles.";
                    $message .= "\n\nEste es un mensaje automático, por favor no respondas a este correo.";

                    $headers = 'From: Sistema de Pasantías <sistema@pasantias.com>' . "\r\n" .
                               'Reply-To: noreply@pasantias.com' . "\r\n" .
                               'X-Mailer: PHP/' . phpversion();

                    if (!mail($to, $subject, $message, $headers)) {
                        error_log("Error al enviar correo de notificación a: " . $to);
                    }
                }
                
                // Confirmar la transacción
                $conexion->commit();

                header("Location: " . $_SERVER['PHP_SELF']);
                exit();

            } else {
                // Revertir la transacción en caso de error
                $conexion->rollBack();
                $error = "Error al actualizar la calificación en la base de datos";
                error_log("Error execute UPDATE entrega: " . print_r($stmt->errorInfo(), true));
            }
        } catch (PDOException $e) {
            // Revertir la transacción en caso de error
            $conexion->rollBack();
            error_log("Error DB al calificar avance: " . $e->getMessage());
            $error = "Error en la base de datos al calificar el avance.";
        } catch (Exception $e) {
            // Revertir la transacción en caso de error
            $conexion->rollBack();
            error_log("Error general al calificar avance: " . $e->getMessage());
            $error = "Error del servidor al calificar el avance.";
        }
    }
}

// Procesar subida de acta final
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_acta'])) {
    $pasantia_id = filter_input(INPUT_POST, 'pasantia_id', FILTER_VALIDATE_INT);
    $archivo = $_FILES['archivo_acta'] ?? null;

    $error = null;

    if (!$pasantia_id) {
        $error = "ID de pasantía no proporcionado.";
    } elseif (!$archivo || $archivo['size'] == 0) {
        $error = "Debes adjuntar un archivo PDF";
    } elseif ($archivo['type'] != 'application/pdf') {
        $error = "El archivo debe ser PDF";
    } elseif ($archivo['size'] > 5 * 1024 * 1024) {
        $error = "El archivo es demasiado grande. Máximo 5MB";
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo: Código " . $archivo['error'];
    } else {
        try {
            $query_check = "SELECT id FROM pasantias WHERE id = ? AND tutor_id = ? AND (estado = 'en_proceso' OR estado = 'aprobada')";
            $stmt_check = $conexion->prepare($query_check);
            $stmt_check->execute([$pasantia_id, $tutor_id]);
            if (!$stmt_check->fetch()) {
                $error = "No autorizado para finalizar esta pasantía o no está en estado 'En Proceso' o 'Aprobada'.";
            }
            $stmt_check->closeCursor();
        } catch (PDOException $e) {
            error_log("Error DB check pasantia para acta: " . $e->getMessage());
            $error = "Error de base de datos al verificar pasantía.";
        }

        if ($error === null) {
            $directorio_actas = __DIR__ . "/../../uploads/pasantias/actas/";
            if (!file_exists($directorio_actas)) {
                mkdir($directorio_actas, 0775, true);
            }

            $nombre_archivo = $pasantia_id . "_acta_" . time() . ".pdf";
            $ruta_archivo = $directorio_actas . $nombre_archivo;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
                try {
                    $query = "UPDATE pasantias
                              SET documento_adicional = ?,
                                  estado = 'finalizada'
                              WHERE id = ?";
                    $stmt = $conexion->prepare($query);
                    $stmt->bindParam(1, $nombre_archivo, PDO::PARAM_STR);
                    $stmt->bindParam(2, $pasantia_id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $stmt->closeCursor();
                        
                        // Obtener información del estudiante para enviar correo
                        $query_info = "SELECT u.email, u.nombre, p.titulo
                                      FROM pasantias p
                                      INNER JOIN usuarios u ON p.estudiante_id = u.id
                                      WHERE p.id = ?";
                        $stmt_info = $conexion->prepare($query_info);
                        $stmt_info->bindParam(1, $pasantia_id, PDO::PARAM_INT);
                        $stmt_info->execute();
                        $info_estudiante = $stmt_info->fetch(PDO::FETCH_ASSOC);
                        $stmt_info->closeCursor();
                        
                        if ($info_estudiante) {
                            // Enviar correo al estudiante
                            $to = $info_estudiante['email'];
                            $subject = "Tu pasantía ha sido finalizada";
                            $message = "Hola " . $info_estudiante['nombre'] . ",\n\n";
                            $message .= "Tu tutor ha finalizado tu pasantía '" . $info_estudiante['titulo'] . "'.\n";
                            $message .= "El acta de finalización ya está disponible en el portal de pasantías.\n\n";
                            $message .= "Por favor, revisa el portal para ver tu nota final y descargar el acta.\n\n";
                            $message .= "Este es un mensaje automático, por favor no respondas a este correo.";
                            
                            $headers = 'From: Sistema de Pasantías <sistema@pasantias.com>' . "\r\n" .
                                      'Reply-To: noreply@pasantias.com' . "\r\n" .
                                      'X-Mailer: PHP/' . phpversion();
                            
                            if (!mail($to, $subject, $message, $headers)) {
                                error_log("Error al enviar correo de finalización a: " . $to);
                            }
                        }
                        
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $error = "Error al actualizar la base de datos";
                        error_log("Error execute UPDATE pasantia acta: " . print_r($stmt->errorInfo(), true));
                        if (file_exists($ruta_archivo)) {
                            unlink($ruta_archivo);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error DB al subir acta: " . $e->getMessage());
                    $error = "Error en la base de datos al subir el acta.";
                    if (file_exists($ruta_archivo)) {
                        unlink($ruta_archivo);
                    }
                } catch (Exception $e) {
                    error_log("Error general al subir acta: " . $e->getMessage());
                    $error = "Error del servidor al subir el acta.";
                    if (file_exists($ruta_archivo)) {
                        unlink($ruta_archivo);
                    }
                }
            } else {
                error_log("Error moviendo archivo de acta: " . $archivo['tmp_name'] . " a " . $ruta_archivo);
                $error = "Error técnico al subir el archivo del acta. Contacta al administrador.";
            }
        }
    }
}

// Función para formatear fechas
function formatearFecha($fecha) {
    if ($fecha === null || strtotime($fecha) === false) {
        return 'Fecha inválida';
    }
    return date('d/m/Y H:i', strtotime($fecha));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Tutor - Gestión de Pasantías</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tutor_pasantias.css">
</head>
<body>
    <script> const TUTOR_ID = <?php echo json_encode($tutor_id); ?>; </script>

    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-chalkboard-teacher logo"></i>
                <h3>Portal del Tutor</h3>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($nombre_tutor); ?></p>
                    <p class="user-role">Tutor Académico</p>
                </div>
            </div>

            <ul class="sidebar-nav">
                <li class="nav-item active" data-section="dashboard">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </li>
                <li class="nav-item" data-section="pasantias">
                    <i class="fas fa-users"></i>
                    <span>Pasantías Asignadas</span>
                </li>



                <li class="nav-item" data-section="proyectos">
                    <i class="fas fa-laptop-code"></i>
                    <span>Proyectos Aplicación</span>
                </li>




                <li class="nav-item" data-section="avances">
                    <i class="fas fa-tasks"></i>
                    <span>Gestión de Avances</span>
                </li>
                <li class="nav-item" data-section="chat">
                    <i class="fas fa-comments"></i>
                    <span>Chat con Estudiantes</span>
                    <span class="badge" id="chat-sidebar-badge" style="display: none;">0</span>
                </li>
                <li class="nav-item" data-section="estadisticas">
                    <i class="fas fa-chart-bar"></i>
                    <span>Estadísticas</span>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </nav>

        <div class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button id="toggle-sidebar" class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2>Sistema de Gestión de Pasantías</h2>
                </div>
            </header>

            <?php if (isset($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h3><i class="fas fa-th-large"></i> Dashboard</h3>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Total Pasantías</h4>
                            <p class="stat-value"><?php echo $total_pasantias; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Finalizadas</h4>
                            <p class="stat-value"><?php echo $pasantias_finalizadas; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h4>En Curso</h4>
                            <p class="stat-value"><?php echo $pasantias_en_curso; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Con Retraso</h4>
                            <p class="stat-value"><?php echo $pasantias_retrasadas; ?></p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Pasantías Asignadas</h4>
                        <div class="card-actions">
                            <div class="search-box">
                                <input type="text" id="search-pasantias" placeholder="Buscar pasantía...">
                                <i class="fas fa-search"></i>
                            </div>
                            <select id="filter-estado">
                                <option value="">Todos los estados</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En Proceso</option>
                                <option value="aprobada">Aprobada</option>
                                <option value="finalizada">Finalizada</option>
                            </select>
                            <select id="filter-ciclo">
                                <option value="">Todos los ciclos</option>
                                <option value="tecnico">Técnico</option>
                                <option value="tecnologo">Tecnólogo</option>
                                <option value="profesional">Profesional</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Código</th>
                                        <th>Ciclo</th>
                                        <th>Empresa</th>
                                        <th>Progreso</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pasantias as $pasantia):
                                        // Calcular progreso para mostrar en la tabla
                                        $query_progreso = "SELECT COUNT(*) as total_aprobados
                                                           FROM entregas_pasantia
                                                           WHERE pasantia_id = ? AND estado = 'aprobado'";
                                        $stmt_progreso = $conexion->prepare($query_progreso);
                                        $stmt_progreso->bindParam(1, $pasantia['id'], PDO::PARAM_INT);
                                        $stmt_progreso->execute();
                                        $resultado_progreso = $stmt_progreso->fetch(PDO::FETCH_ASSOC);
                                        $progreso = ($resultado_progreso['total_aprobados'] / 4) * 100; // Asumiendo 4 avances totales
                                        $stmt_progreso->closeCursor();

                                        // Definir clase para el estado
                                        $clases_estado_tabla = [
                                            'pendiente' => 'estado-pendiente',
                                            'aprobada' => 'estado-aprobado',
                                            'rechazada' => 'estado-rechazado',
                                            'en_proceso' => 'estado-proceso',
                                            'finalizada' => 'estado-finalizada',
                                        ];
                                        $clase_estado_tabla = $clases_estado_tabla[$pasantia['estado']] ?? 'estado-pendiente';
                                    ?>
                                    <tr data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>"
                                        data-estudiante-id="<?php echo htmlspecialchars($pasantia['estudiante_id']); ?>"
                                        data-estado="<?php echo htmlspecialchars($pasantia['estado']); ?>"
                                        data-ciclo="<?php echo htmlspecialchars($pasantia['ciclo']); ?>">
                                        <td><?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($pasantia['codigo_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($pasantia['ciclo'])); ?></td>
                                        <td><?php echo htmlspecialchars($pasantia['empresa']); ?></td>
                                        <td>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progreso; ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo round($progreso); ?>%</span>
                                        </td>
                                        <td><span class="estado <?php echo $clase_estado_tabla; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pasantia['estado']))); ?></span></td>
                                        <td>
                                            <button class="btn-icon btn-detalle" data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon btn-chat-open"
                                                    data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>"
                                                    data-estudiante-id="<?php echo htmlspecialchars($pasantia['estudiante_id']); ?>"
                                                    data-estudiante-nombre="<?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?>"
                                                    title="Abrir Chat">
                                                <i class="fas fa-comments"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="detalle-pasantia" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-info-circle"></i> Detalle de Pasantía</h3>
                </div>
                <div id="detalle-contenido">
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>Selecciona una pasantía de la lista en el Dashboard para ver los detalles.</p>
                    </div>
                </div>
            </section>








            <section id="proyectos" class="content-section">
    <div class="section-header">
        <h3><i class="fas fa-laptop-code"></i> Proyectos de Aplicación</h3>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h4>Total Proyectos</h4>
                <p class="stat-value" id="total-proyectos">0</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h4>Finalizados</h4>
                <p class="stat-value" id="proyectos-finalizados">0</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h4>En Curso</h4>
                <p class="stat-value" id="proyectos-en-curso">0</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h4>Con Retraso</h4>
                <p class="stat-value" id="proyectos-retrasados">0</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4>Proyectos Asignados</h4>
            <div class="card-actions">
                <div class="search-box">
                    <input type="text" id="search-proyectos" placeholder="Buscar proyecto...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filter-estado-proyectos">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="finalizado">Finalizado</option>
                </select>
                <select id="filter-avance-proyectos">
                    <option value="">Todos los avances</option>
                    <option value="1">Avance 1</option>
                    <option value="2">Avance 2</option>
                    <option value="3">Avance 3</option>
                    <option value="4">Avance 4</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="tabla-proyectos">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Estudiantes</th>
                            <th>Avance Actual</th>
                            <th>Progreso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Los proyectos se cargarán dinámicamente aquí -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section id="detalle-proyecto" class="content-section">
    <div class="section-header">
        <h3><i class="fas fa-info-circle"></i> Detalle de Proyecto</h3>
    </div>
    <div id="detalle-proyecto-contenido">
        <div class="empty-state">
            <i class="fas fa-info-circle"></i>
            <p>Selecciona un proyecto de la lista para ver los detalles.</p>
        </div>
    </div>
</section>









            <section id="pasantias" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Pasantías Asignadas</h3>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h4>Lista de Pasantías</h4>
                        <div class="card-actions">
                            <div class="search-box">
                                <input type="text" id="search-pasantias-list" placeholder="Buscar pasantía...">
                                <i class="fas fa-search"></i>
                            </div>
                            <select id="filter-estado-list">
                                <option value="">Todos los estados</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En Proceso</option>
                                <option value="aprobada">Aprobada</option>
                                <option value="finalizada">Finalizada</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Código</th>
                                        <th>Ciclo</th>
                                        <th>Empresa</th>
                                        <th>Progreso</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pasantias as $pasantia):
                                        // Calcular progreso para mostrar en la tabla
                                        $query_progreso = "SELECT COUNT(*) as total_aprobados
                                                           FROM entregas_pasantia
                                                           WHERE pasantia_id = ? AND estado = 'aprobado'";
                                        $stmt_progreso = $conexion->prepare($query_progreso);
                                        $stmt_progreso->bindParam(1, $pasantia['id'], PDO::PARAM_INT);
                                        $stmt_progreso->execute();
                                        $resultado_progreso = $stmt_progreso->fetch(PDO::FETCH_ASSOC);
                                        $progreso = ($resultado_progreso['total_aprobados'] / 4) * 100;
                                        $stmt_progreso->closeCursor();

                                        $clase_estado_tabla = $clases_estado_tabla[$pasantia['estado']] ?? 'estado-pendiente';
                                    ?>
                                    <tr data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>"
                                        data-estudiante-id="<?php echo htmlspecialchars($pasantia['estudiante_id']); ?>"
                                        data-estado="<?php echo htmlspecialchars($pasantia['estado']); ?>"
                                        data-ciclo="<?php echo htmlspecialchars($pasantia['ciclo']); ?>">
                                        <td><?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($pasantia['codigo_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($pasantia['ciclo'])); ?></td>
                                        <td><?php echo htmlspecialchars($pasantia['empresa']); ?></td>
                                        <td>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progreso; ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo round($progreso); ?>%</span>
                                        </td>
                                        <td><span class="estado <?php echo $clase_estado_tabla; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pasantia['estado']))); ?></span></td>
                                        <td>
                                            <button class="btn-icon btn-detalle" data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon btn-chat-open"
                                                    data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>"
                                                    data-estudiante-id="<?php echo htmlspecialchars($pasantia['estudiante_id']); ?>"
                                                    data-estudiante-nombre="<?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?>"
                                                    title="Abrir Chat">
                                                <i class="fas fa-comments"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="avances" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-tasks"></i> Gestión de Avances</h3>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Avances Pendientes de Revisión</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        // Obtener avances pendientes
                        $query_pendientes = "SELECT e.*, p.titulo AS proyecto_titulo, u.nombre AS estudiante_nombre
                                             FROM entregas_pasantia e
                                             INNER JOIN pasantias p ON e.pasantia_id = p.id
                                             INNER JOIN usuarios u ON p.estudiante_id = u.id
                                             WHERE p.tutor_id = ? AND e.estado = 'pendiente'
                                             ORDER BY e.fecha_entrega ASC";
                        $stmt_pendientes = $conexion->prepare($query_pendientes);
                        $stmt_pendientes->bindParam(1, $tutor_id, PDO::PARAM_INT);
                        $stmt_pendientes->execute();
                        $avances_pendientes = $stmt_pendientes->fetchAll(PDO::FETCH_ASSOC);
                        $stmt_pendientes->closeCursor();

                        if (empty($avances_pendientes)):
                        ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No hay avances pendientes de revisión</p>
                        </div>
                        <?php else: ?>
                        <div class="avances-grid">
                            <?php foreach ($avances_pendientes as $avance): ?>
                            <div class="avance-card">
                                <div class="avance-header">
                                    <h5><?php echo htmlspecialchars($avance['estudiante_nombre']); ?></h5>
                                    <span class="avance-numero">Avance <?php echo htmlspecialchars($avance['numero_avance']); ?></span>
                                </div>

                                <div class="avance-body">
                                    <p class="proyecto-titulo"><?php echo htmlspecialchars($avance['proyecto_titulo']); ?></p>
                                    <p class="fecha-entrega">Entregado: <?php echo formatearFecha($avance['fecha_entrega']); ?></p>

                                    <?php if ($avance['comentario_estudiante']): ?>
                                    <div class="comentario-estudiante">
                                        <strong>Comentario del estudiante:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($avance['comentario_estudiante'])); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="archivo-entregado">
                                        <a href="../../uploads/pasantias/entregas/<?php echo htmlspecialchars($avance['archivo_entregado']); ?>" target="_blank" class="btn-download">
                                            <i class="fas fa-file-pdf"></i> Ver entrega
                                        </a>
                                    </div>

                                    <form class="form-calificacion" method="POST">
                                        <input type="hidden" name="entrega_id" value="<?php echo htmlspecialchars($avance['id']); ?>">
                                        <input type="hidden" name="calificar_avance" value="1">
                                        <div class="form-group">
                                            <label for="comentario-<?php echo $avance['id']; ?>">Comentario:</label>
                                            <textarea id="comentario-<?php echo $avance['id']; ?>" name="comentario" rows="3" required><?php echo htmlspecialchars($avance['comentario_tutor'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="nota-<?php echo $avance['id']; ?>">Nota (0-5):</label>
                                            <input type="number" id="nota-<?php echo $avance['id']; ?>" name="nota" min="0" max="5" step="0.1" value="<?php echo $avance['nota'] ?? ''; ?>" <?php echo $avance['estado'] !== 'aprobado' ? 'required' : ''; ?>>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" name="estado" value="aprobado" class="btn btn-success" <?php echo $avance['estado'] === 'aprobado' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check"></i> Aprobar
                                            </button>
                                            <button type="submit" name="estado" value="corregir" class="btn btn-warning" <?php echo $avance['estado'] === 'aprobado' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-redo"></i> Solicitar Corrección
                                            </button>
                                            <button type="submit" name="estado" value="revisado" class="btn btn-secondary" <?php echo $avance['estado'] !== 'pendiente' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-eye"></i> Marcar Revisado
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="chat" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-comments"></i> Chat con Estudiantes</h3>
                </div>

                <div class="chat-container">
                    <div class="chat-list">
                        <?php foreach ($pasantias as $pasantia): ?>
                        <div class="chat-item"
                             data-pasantia-id="<?php echo htmlspecialchars($pasantia['id']); ?>"
                             data-estudiante-id="<?php echo htmlspecialchars($pasantia['estudiante_id']); ?>"
                             data-estudiante-nombre="<?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?>">
                            <div class="chat-item-avatar">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="chat-item-info">
                                <h4><?php echo htmlspecialchars($pasantia['estudiante_nombre']); ?></h4>
                                <p><?php echo htmlspecialchars($pasantia['empresa']); ?></p>
                            </div>
                            <span class="chat-badge" style="display: none;">0</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chat-content">
                        <div class="chat-placeholder">
                            <i class="fas fa-comments"></i>
                            <p>Selecciona un estudiante de la lista para abrir el chat.</p>
                        </div>

                        <div class="chat-active" style="display: none;">
                            <div class="chat-header">
                                <div class="chat-user-info">
                                    <i class="fas fa-user-graduate"></i>
                                    <span id="chat-student-name"></span>
                                </div>
                            </div>

                            <div class="chat-messages" id="chat-messages-container"></div>
                            <div class="chat-input">
                                <form id="chat-form">
                                    <input type="hidden" id="chat-pasantia-id" name="pasantia_id">
                                    <input type="hidden" id="chat-estudiante-id" name="receptor_id">
                                    <div class="chat-tools">
                                        <label for="chat-file" class="chat-file-label">
                                            <i class="fas fa-paperclip"></i>
                                            <span id="chat-selected-file-name"></span>
                                        </label>
                                        <input type="file" id="chat-file" name="archivo" style="display: none;">
                                    </div>

                                    <textarea id="chat-message" name="mensaje" placeholder="Escribe un mensaje..."></textarea>
                                    <button type="submit" class="chat-send" title="Enviar Mensaje">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="estadisticas" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-bar"></i> Estadísticas</h3>
                </div>

                <div class="stats-container">
                    <div class="card">
                        <div class="card-header">
                            <h4>Resumen de Actividad</h4>
                        </div>
                        <div class="card-body">
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <div class="stat-circle">
                                        <span class="stat-number"><?php echo $total_avances_corregidos; ?></span>
                                        <span class="stat-label">Avances Corregidos</span>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-circle success">
                                        <span class="stat-number"><?php echo $pasantias_finalizadas; ?></span>
                                        <span class="stat-label">Pasantías Completadas</span>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-circle warning">
                                        <span class="stat-number"><?php echo $pasantias_en_curso; ?></span>
                                        <span class="stat-label">En Proceso</span>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-circle danger">
                                        <span class="stat-number"><?php echo $pasantias_retrasadas; ?></span>
                                        <span class="stat-label">Con Retraso</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('JavaScript cargado correctamente (Tutor)');

    // Hacer TUTOR_ID disponible en JS
    const TUTOR_ID_JS = typeof TUTOR_ID !== 'undefined' ? TUTOR_ID : null;
    if (TUTOR_ID_JS === null) {
        console.error("TUTOR_ID no definido en PHP!");
    }

    // --- Chat Variables y Funciones de Polling ---
    let currentPasantiaId = null;
    let currentEstudianteId = null;
    let chatPollingInterval = null;

    function stopPolling() {
        if (chatPollingInterval) {
            clearInterval(chatPollingInterval);
            chatPollingInterval = null;
            console.log("Chat polling detenido.");
        }
    }

    function startPolling(pasantiaId) {
        stopPolling();
        if (pasantiaId) {
            chatPollingInterval = setInterval(function() {
                const chatSection = document.getElementById('chat');
                const isChatSectionActive = chatSection && chatSection.classList.contains('active');

                if (isChatSectionActive && currentPasantiaId === pasantiaId) {
                    cargarMensajes(pasantiaId);
                } else {
                    stopPolling();
                }
            }, 5000);
            console.log(`Chat polling iniciado para pasantia ID ${pasantiaId}`);
        }
    }

    // --- Funciones de ayuda para sanitización y formato en HTML generado por JS ---
    function htmlspecialchars_js(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }

    function nl2br_js(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/\n/g, '<br>');
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return 'Fecha inválida';
        }
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function getEstadoClassTimeline(estado) {
        switch (estado) {
            case 'aprobado': return 'completed';
            case 'corregir': return 'correction';
            case 'pendiente': return 'pending';
            case 'revisado': return 'in-progress';
            default: return '';
        }
    }

    function getEstadoIconTimeline(estado) {
        switch (estado) {
            case 'aprobado': return 'fas fa-check-circle';
            case 'corregir': return 'fas fa-exclamation-circle';
            case 'pendiente': return 'fas fa-clock';
            case 'revisado': return 'fas fa-clock';
            default: return 'fas fa-circle';
        }
    }

    function getEstadoClassTutor(estado) {
        switch (estado) {
            case 'pendiente': return 'estado-pendiente';
            case 'aprobada': return 'estado-aprobado';
            case 'rechazada': return 'estado-rechazado';
            case 'en_proceso': return 'estado-proceso';
            case 'finalizada': return 'estado-finalizada';
            default: return 'estado-pendiente';
        }
    }

    // --- Función para actualizar la visualización del chat ---
    function actualizarChat(mensajes) {
        const chatMessagesContainer = document.getElementById('chat-messages-container');
        if (!chatMessagesContainer) {
            console.error('Elemento chatMessagesContainer no encontrado para actualizar');
            return;
        }
        chatMessagesContainer.innerHTML = '';

        if (!mensajes || mensajes.length === 0) {
            if (currentPasantiaId) {
                chatMessagesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No hay mensajes todavía. Inicia la conversación.</p>
                    </div>
                `;
            } else {
                chatMessagesContainer.innerHTML = `
                    <div class="chat-placeholder" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; color: #64748b;">
                        <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Selecciona un estudiante de la lista para abrir el chat.</p>
                    </div>
                `;
            }
            return;
        }

        const chatEstudianteIdInput = document.getElementById('chat-estudiante-id');
        const activeChatStudentId = chatEstudianteIdInput?.value;
        const tutorId = TUTOR_ID_JS;

        mensajes.forEach(mensaje => {
            const esEmisor = mensaje.emisor_id == tutorId;
            const claseMsg = esEmisor ? 'message-sent' : 'message-received';

            const mensajeHtml = `
                <div class="chat-message ${claseMsg}">
                    <div class="message-content">
                        ${mensaje.archivo ? `
                            <div class="message-attachment">
                                <a href="../../uploads/pasantias/chat/${encodeURIComponent(mensaje.archivo)}" target="_blank">
                                    <i class="fas fa-paperclip"></i> ${htmlspecialchars_js(mensaje.archivo)}
                                </a>
                            </div>
                        ` : ''}
                        ${mensaje.mensaje ? `
                            <div class="message-text">${nl2br_js(htmlspecialchars_js(mensaje.mensaje))}</div>
                        ` : ''}
                        <div class="message-time">${formatDate(mensaje.fecha_envio)}</div>
                    </div>
                </div>
            `;

            chatMessagesContainer.insertAdjacentHTML('beforeend', mensajeHtml);
        });

        // Scroll al final después de añadir mensajes
        chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
    }

    // --- Función para cargar mensajes para un pasantiaId específico (AJAX GET) ---
    function cargarMensajes(pasantiaIdToLoad) {
        const chatSection = document.getElementById('chat');
        const isChatSectionActive = chatSection && chatSection.classList.contains('active');

        if (!pasantiaIdToLoad || (!isChatSectionActive && pasantiaIdToLoad != currentPasantiaId)) {
            return;
        }

        const targetPasantiaId = pasantiaIdToLoad || currentPasantiaId;
        if (!targetPasantiaId) {
            console.warn("cargarMensajes llamada sin un ID de pasantía válido.");
            return;
        }

        // Usar la URL actual del script para las solicitudes AJAX
        const currentScriptPath = window.location.pathname;
        
        fetch(`${currentScriptPath}?ajax_action=get_messages&pasantia_id=${encodeURIComponent(targetPasantiaId)}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error(`Error en la respuesta del servidor al obtener mensajes para pasantia ${targetPasantiaId}:`, err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                        console.error(`Error en la respuesta del servidor (no-JSON) al obtener mensajes para pasantia ${targetPasantiaId}:`, response.status, response.statusText);
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.mensajes) {
                    if (currentPasantiaId && (data.mensajes.length > 0 && data.mensajes[0].pasantia_id == currentPasantiaId ||
                                              (data.mensajes.length === 0 && data.estudiante_id_chat == currentEstudianteId))) {
                        const currentMessagesCount = document.querySelectorAll('#chat-messages-container .chat-message').length;
                        if (data.mensajes.length !== currentMessagesCount || currentMessagesCount === 0) {
                            actualizarChat(data.mensajes);
                            console.log(`Chat para pasantia ${currentPasantiaId} actualizado (${data.mensajes.length} mensajes).`);
                        }
                    } else if (currentPasantiaId && data.mensajes && data.mensajes.length > 0 && data.mensajes[0].pasantia_id != currentPasantiaId) {
                        console.warn("Recibidos mensajes para una pasantia diferente a la actual. Ignorando actualización de UI.");
                    } else if (currentPasantiaId && (!data.mensajes || data.mensajes.length === 0)) {
                        if (document.querySelectorAll('#chat-messages-container .chat-message').length > 0) {
                            actualizarChat([]);
                        } else if (document.getElementById('chat-messages-container').innerHTML === '') {
                            actualizarChat([]);
                        }
                    }
                } else {
                    console.error('API Error al obtener mensajes:', data.message);
                    const chatMessagesContainer = document.getElementById('chat-messages-container');
                    if (currentPasantiaId && chatMessagesContainer) {
                        chatMessagesContainer.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-circle"></i><p>Error al cargar mensajes: ${htmlspecialchars_js(data.message)}</p></div>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error en fetch cargarMensajes:', error);
                const chatMessagesContainer = document.getElementById('chat-messages-container');
                if (currentPasantiaId && chatMessagesContainer) {
                    chatMessagesContainer.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar mensajes.</p></div>`;
                }
            });
    }

    // Funciones de ayuda para generar HTML de detalle
    function generarHTMLDetalle(pasantia) {
        return `
            <div class="card">
                <div class="card-header">
                    <h4>${htmlspecialchars_js(pasantia.titulo)}</h4>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-group">
                            <h5>Información del Estudiante</h5>
                            <p><strong>Nombre:</strong> ${htmlspecialchars_js(pasantia.estudiante_nombre)}</p>
                            <p><strong>Código:</strong> ${htmlspecialchars_js(pasantia.codigo_estudiante)}</p>
                            <p><strong>Email:</strong> ${htmlspecialchars_js(pasantia.estudiante_email)}</p>
                            <p><strong>Ciclo:</strong> ${htmlspecialchars_js(pasantia.ciclo)}</p>
                        </div>

                        <div class="info-group">
                            <h5>Información de la Empresa</h5>
                            <p><strong>Empresa:</strong> ${htmlspecialchars_js(pasantia.empresa)}</p>
                            <p><strong>Dirección:</strong> ${htmlspecialchars_js(pasantia.direccion_empresa || 'No especificada')}</p>
                            <p><strong>Supervisor:</strong> ${htmlspecialchars_js(pasantia.supervisor_empresa || 'No especificado')}</p>
                            <p><strong>Teléfono:</strong> ${htmlspecialchars_js(pasantia.telefono_supervisor || 'No especificado')}</p>
                        </div>

                        <div class="info-group">
                            <h5>Fechas</h5>
                            <p><strong>Inicio:</strong> ${pasantia.fecha_inicio ? new Date(pasantia.fecha_inicio).toLocaleDateString() : 'No definida'}</p>
                            <p><strong>Fin:</strong> ${pasantia.fecha_fin ? new Date(pasantia.fecha_fin).toLocaleDateString() : 'No definida'}</p>
                            <p><strong>Estado:</strong> <span class="estado ${getEstadoClassTutor(pasantia.estado)}">${htmlspecialchars_js(pasantia.estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(pasantia.estado.replace('_', ' ')).slice(1)}</span></p>
                        </div>
                    </div>

                    ${pasantia.archivo_documento ? `
                        <div class="documento-inicial">
                            <a href="../../uploads/pasantias/documentos/${encodeURIComponent(pasantia.archivo_documento)}" target="_blank" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Ver Documento Inicial
                            </a>
                        </div>
                    ` : ''}

                    <div class="avances-timeline">
                        <h5>Historial de Avances</h5>
                        ${generarTimelineAvances(pasantia.avances || [])}
                    </div>

                    ${pasantia.estado === 'en_proceso' && pasantia.puede_finalizar ? `
                        <div class="acta-final">
                            <h5>Finalizar Pasantía</h5>
                            <form method="POST" enctype="multipart/form-data" class="form-acta">
                                <input type="hidden" name="pasantia_id" value="${pasantia.id}">
                                <input type="hidden" name="subir_acta" value="1">

                                <div class="form-group">
                                    <label>Subir Acta Final (PDF):</label>
                                    <input type="file" name="archivo_acta" accept=".pdf" required>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Finalizar Pasantía
                                </button>
                            </form>
                        </div>
                    ` : ''}
                    
                    ${pasantia.estado === 'aprobada' ? `
                        <div class="acta-final">
                            <h5>Finalizar Pasantía Aprobada</h5>
                            <form method="POST" enctype="multipart/form-data" class="form-acta">
                                <input type="hidden" name="pasantia_id" value="${pasantia.id}">
                                <input type="hidden" name="subir_acta" value="1">

                                <div class="form-group">
                                    <label>Subir Acta Final (PDF):</label>
                                    <input type="file" name="archivo_acta" accept=".pdf" required>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Finalizar Pasantía
                                </button>
                            </form>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    function generarTimelineAvances(avances) {
        if (!avances || avances.length === 0) {
            return '<p class="text-muted">No hay avances registrados</p>';
        }

        return `
            <div class="timeline">
                ${avances.map(avance => `
                    <div class="timeline-item">
                        <div class="timeline-marker ${getEstadoClassTimeline(avance.estado)}">
                            <i class="${getEstadoIconTimeline(avance.estado)}"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Avance ${htmlspecialchars_js(avance.numero_avance)}</h6>
                            <p class="timeline-date">${avance.fecha_entrega ? new Date(avance.fecha_entrega).toLocaleDateString() : 'Sin fecha'}</p>
                            <p class="estado ${getEstadoClassTimeline(avance.estado)}">${htmlspecialchars_js(avance.estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(avance.estado.replace('_', ' ')).slice(1)}</p>
                            ${avance.nota !== null ? `<p class="nota">Nota: ${htmlspecialchars_js(avance.nota)}</p>` : ''}
                            ${avance.comentario_tutor ? `<div class="comentario-tutor"><strong>Comentario Tutor:</strong> ${nl2br_js(htmlspecialchars_js(avance.comentario_tutor))}</div>` : ''}
                            ${avance.comentario_estudiante ? `<div class="comentario-estudiante"><strong>Comentario Estudiante:</strong> ${nl2br_js(htmlspecialchars_js(avance.comentario_estudiante))}</div>` : ''}
                            ${avance.archivo_entregado ? `
                            <a href="../../uploads/pasantias/entregas/${encodeURIComponent(avance.archivo_entregado)}" target="_blank" class="btn-download">
                                <i class="fas fa-file-pdf"></i> Ver entrega
                            </a>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // --- Navegación entre secciones ---
    const navItems = document.querySelectorAll('.nav-item');
    const contentSections = document.querySelectorAll('.content-section');

    function showSection(sectionId) {
        const targetSection = document.getElementById(sectionId);
        if (!targetSection) {
            console.error(`No se encontró la sección con id: ${sectionId}`);
            return;
        }

        contentSections.forEach(section => section.classList.remove('active'));
        navItems.forEach(navItem => navItem.classList.remove('active'));

        targetSection.classList.add('active');
        const activeNavItem = document.querySelector(`.nav-item[data-section="${sectionId}"]`);
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }

        console.log(`Mostrando sección: ${sectionId}`);

        if (sectionId !== 'chat') {
            stopPolling();
        } else {
            const chatPlaceholder = document.querySelector('.chat-placeholder');
            const chatActive = document.querySelector('.chat-active');
            if (currentPasantiaId) {
                if(chatPlaceholder) chatPlaceholder.style.display = 'none';
                if(chatActive) chatActive.style.display = 'flex';
                startPolling(currentPasantiaId);
            } else {
                if(chatPlaceholder) chatPlaceholder.style.display = 'flex';
                if(chatActive) chatActive.style.display = 'none';
                stopPolling();
            }
        }
    }

    navItems.forEach(item => {
        item.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section');
            if (sectionId) {
                showSection(sectionId);
            }
        });
    });

    showSection('dashboard');

    // --- Toggle sidebar en dispositivos móviles ---
    const toggleSidebar = document.getElementById('toggle-sidebar');
    const wrapper = document.querySelector('.wrapper');

    if (toggleSidebar && wrapper) {
        toggleSidebar.addEventListener('click', function() {
            wrapper.classList.toggle('sidebar-collapsed');
        });
    } else {
        console.error('No se encontraron los elementos toggle-sidebar o wrapper para el sidebar.');
    }

    // --- Filtros de búsqueda en la tabla de pasantías ---
    const searchInput = document.getElementById('search-pasantias');
    const filterEstado = document.getElementById('filter-estado');
    const filterCiclo = document.getElementById('filter-ciclo');
    const tablaPasantiasBody = document.querySelector('.table tbody');

    if (searchInput && filterEstado && filterCiclo && tablaPasantiasBody) {
        function filtrarPasantias() {
            const busqueda = searchInput.value.toLowerCase();
            const estadoFiltro = filterEstado.value;
            const cicloFiltro = filterCiclo.value;

            const filas = tablaPasantiasBody.getElementsByTagName('tr');

            Array.from(filas).forEach(fila => {
                const filaEstado = fila.getAttribute('data-estado');
                const filaCiclo = fila.getAttribute('data-ciclo');
                const estudianteText = fila.cells[0].textContent.toLowerCase();

                const coincideBusqueda = estudianteText.includes(busqueda);
                const coincideEstado = !estadoFiltro || filaEstado === estadoFiltro;
                const coincideCiclo = !cicloFiltro || filaCiclo === cicloFiltro;

                fila.style.display = coincideBusqueda && coincideEstado && coincideCiclo ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filtrarPasantias);
        filterEstado.addEventListener('change', filtrarPasantias);
        filterCiclo.addEventListener('change', filtrarPasantias);
    } else {
        console.error('No se encontraron todos los elementos para los filtros de pasantías.');
    }

    // Filtros para la sección de pasantías
    const searchInputList = document.getElementById('search-pasantias-list');
    const filterEstadoList = document.getElementById('filter-estado-list');
    const tablaPasantiasListBody = document.querySelector('#pasantias .table tbody');

    if (searchInputList && filterEstadoList && tablaPasantiasListBody) {
        function filtrarPasantiasList() {
            const busqueda = searchInputList.value.toLowerCase();
            const estadoFiltro = filterEstadoList.value;

            const filas = tablaPasantiasListBody.getElementsByTagName('tr');

            Array.from(filas).forEach(fila => {
                const filaEstado = fila.getAttribute('data-estado');
                const estudianteText = fila.cells[0].textContent.toLowerCase();

                const coincideBusqueda = estudianteText.includes(busqueda);
                const coincideEstado = !estadoFiltro || filaEstado === estadoFiltro;

                fila.style.display = coincideBusqueda && coincideEstado ? '' : 'none';
            });
        }

        searchInputList.addEventListener('input', filtrarPasantiasList);
        filterEstadoList.addEventListener('change', filtrarPasantiasList);
    }

    // --- Función para ver detalle de pasantía (AJAX GET) ---
    const tablaPasantias = document.querySelector('.table');

    if (tablaPasantias) {
        tablaPasantias.addEventListener('click', function(event) {
            const target = event.target.closest('.btn-detalle');

            if (target) {
                const pasantiaId = target.getAttribute('data-pasantia-id');
                if (pasantiaId) {
                    verDetallePasantia(pasantiaId);
                } else {
                    console.error('Botón de detalle clickeado sin data-pasantia-id');
                }
            }
        });
    } else {
        console.error('Tabla de pasantías no encontrada para delegación de eventos.');
    }

    window.verDetallePasantia = function(pasantiaId) {
        // Usar la URL actual del script para las solicitudes AJAX
        const currentScriptPath = window.location.pathname;
        
        fetch(`${currentScriptPath}?ajax_action=get_detalle&id=${encodeURIComponent(pasantiaId)}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error('Error en la respuesta del servidor al obtener detalle:', err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                        console.error('Error en la respuesta del servidor (no-JSON) al obtener detalle:', response.status, response.statusText);
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                const detalleSection = document.getElementById('detalle-pasantia');
                const detalleContenido = document.getElementById('detalle-contenido');

                if (!detalleSection || !detalleContenido) {
                    console.error('Elementos de la sección de detalle no encontrados.');
                    return;
                }

                if (data.success && data.pasantia) {
                    showSection('detalle-pasantia');

                    data.pasantia.avances = data.pasantia.avances || [];
                    detalleContenido.innerHTML = generarHTMLDetalle(data.pasantia);

                } else {
                    console.error('API Error al obtener detalle:', data.message);
                    alert('Error al cargar el detalle de la pasantía: ' + data.message);
                    detalleContenido.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error al cargar detalle: ${htmlspecialchars_js(data.message)}</p></div>`;

                    showSection('detalle-pasantia');
                }
            })
            .catch(error => {
                console.error('Error en fetch verDetallePasantia:', error);
                alert('Error de conexión al cargar el detalle.');
                const detalleContenido = document.getElementById('detalle-contenido');
                if(detalleContenido) {
                    detalleContenido.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar detalle.</p></div>`;
                    showSection('detalle-pasantia');
                }
            });
    };

    // --- Chat ---
    const chatItems = document.querySelectorAll('.chat-item');
    const chatPlaceholder = document.querySelector('.chat-placeholder');
    const chatActive = document.querySelector('.chat-active');
    const chatMessagesContainer = document.getElementById('chat-messages-container');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-message');
    const chatFile = document.getElementById('chat-file');
    const chatFileLabel = document.querySelector('.chat-input .chat-tools label[for="chat-file"]');
    const chatSelectedFileNameDisplay = document.getElementById('chat-selected-file-name');
    const chatStudentNameDisplay = document.getElementById('chat-student-name');
    const chatPasantiaIdInput = document.getElementById('chat-pasantia-id');
    const chatEstudianteIdInput = document.getElementById('chat-estudiante-id');

    // --- Manejar clic en los elementos de la lista de chat ---
    const chatList = document.querySelector('.chat-list');
    if (chatList) {
        chatList.addEventListener('click', function(event) {
            const target = event.target.closest('.chat-item');

            if (target) {
                const pasantiaId = target.getAttribute('data-pasantia-id');
                const estudianteId = target.getAttribute('data-estudiante-id');
                const estudianteNombre = target.getAttribute('data-estudiante-nombre');

                if (pasantiaId && estudianteId && estudianteNombre) {
                    if (currentPasantiaId === pasantiaId) {
                        console.log(`Chat con pasantia ${pasantiaId} ya activo.`);
                        startPolling(currentPasantiaId);
                        return;
                    }

                    chatItems.forEach(ci => ci.classList.remove('active'));
                    target.classList.add('active');

                    if(chatPlaceholder) chatPlaceholder.style.display = 'none';
                    if(chatActive) chatActive.style.display = 'flex';

                    if(chatStudentNameDisplay) chatStudentNameDisplay.textContent = htmlspecialchars_js(estudianteNombre);
                    if(chatPasantiaIdInput) chatPasantiaIdInput.value = pasantiaId;
                    if(chatEstudianteIdInput) chatEstudianteIdInput.value = estudianteId;

                    currentPasantiaId = pasantiaId;
                    currentEstudianteId = estudianteId;

                    if(chatMessagesContainer) chatMessagesContainer.innerHTML = '';

                    cargarMensajes(currentPasantiaId);
                    startPolling(currentPasantiaId);

                    const badge = target.querySelector('.chat-badge');
                    if (badge) {
                        badge.style.display = 'none';
                        badge.textContent = '0';
                    }
                    console.log(`Seleccionado chat con pasantia ID: ${pasantiaId}, estudiante ID: ${estudianteId}`);

                    showSection('chat');

                } else {
                    console.error("Click en item de chat: faltan data attributes (pasantiaId, estudianteId, estudianteNombre)");
                }
            }
        });
    } else {
        console.error('Contenedor de lista de chat no encontrado para delegación de eventos.');
    }

    // Configurar el estado inicial del chat
    const anyChatItemActive = document.querySelector('.chat-item.active');
    if (!anyChatItemActive) {
        if(chatPlaceholder) chatPlaceholder.style.display = 'flex';
        if(chatActive) chatActive.style.display = 'none';
        stopPolling();
    } else {
        stopPolling();
    }

    // --- Manejar envío de mensajes (AJAX POST) ---
    if (chatForm && chatInput && chatFile && chatPasantiaIdInput && chatEstudianteIdInput && typeof TUTOR_ID_JS !== 'undefined') {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mensaje = chatInput.value.trim();
            const archivo = chatFile.files[0];
            const pasantiaId = chatPasantiaIdInput.value;
            const estudianteId = chatEstudianteIdInput.value;
            const tutorId = TUTOR_ID_JS;

            if (!pasantiaId || !estudianteId || !tutorId || (!mensaje && !archivo)) {
                console.log('No se envió mensaje: chat no seleccionado, IDs faltantes o sin contenido.');
                if (!pasantiaId || !estudianteId || !tutorId) {
                    alert("Error: No hay un chat seleccionado o faltan IDs.");
                }
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'send_message');
            formData.append('pasantia_id', pasantiaId);
            formData.append('emisor_id', tutorId);
            formData.append('receptor_id', estudianteId);
            formData.append('mensaje', mensaje);
            if (archivo) {
                formData.append('archivo', archivo);
            }

            // Usar la URL actual del script para las solicitudes AJAX
            const currentScriptPath = window.location.pathname;
            
            fetch(currentScriptPath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error('Error en la respuesta del servidor al enviar mensaje:', err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                        console.error('Error en la respuesta del servidor (no-JSON) al enviar mensaje:', response.status, response.statusText);
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    chatInput.value = '';
                    chatFile.value = '';
                    if(chatSelectedFileNameDisplay) chatSelectedFileNameDisplay.textContent = '';

                    cargarMensajes(currentPasantiaId);

                    console.log('Mensaje enviado correctamente');
                } else {
                    console.error('API Error al enviar mensaje:', data.message);
                    alert('Error al enviar el mensaje: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en fetch enviar_mensaje:', error);
                alert('Error de conexión al enviar el mensaje.');
            });
        });

        if(chatFile && chatFileLabel && chatSelectedFileNameDisplay){
            chatFile.addEventListener('change', function() {
                chatSelectedFileNameDisplay.textContent = this.files.length > 0 ? this.files[0].name : '';
            });
        } else {
            console.warn("Elementos para mostrar nombre de archivo de chat no encontrados.");
        }

    } else {
        console.error('Elementos del formulario de chat o IDs necesarios no encontrados para inicializar el listener.');
        if(chatForm) {
            chatForm.style.pointerEvents = 'none';
            chatInput.disabled = true;
            chatFile.disabled = true;
            const sendButton = chatForm.querySelector('button[type="submit"]');
            if(sendButton) sendButton.disabled = true;
            if(chatFileLabel) chatFileLabel.style.pointerEvents = 'none';
            console.log("Formulario de chat deshabilitado.");
        }
    }

    // Botones para abrir chat desde la tabla
    document.querySelectorAll('.btn-chat-open').forEach(button => {
        button.addEventListener('click', function() {
            const pasantiaId = this.getAttribute('data-pasantia-id');
            const estudianteId = this.getAttribute('data-estudiante-id');
            const estudianteNombre = this.getAttribute('data-estudiante-nombre');
            
            if (pasantiaId && estudianteId && estudianteNombre) {
                // Encontrar el item de chat correspondiente y simular un clic
                const chatItem = document.querySelector(`.chat-item[data-pasantia-id="${pasantiaId}"]`);
                if (chatItem) {
                    chatItem.click();
                } else {
                    console.error(`No se encontró el item de chat para la pasantía ${pasantiaId}`);
                }
            }
        });
    });
});







// --- Funcionalidad para Proyectos de Aplicación ---

function cargarProyectos() {
    console.log("Iniciando carga de proyectos...");
    
    // Actualizar estadísticas con ceros inicialmente
    document.getElementById('total-proyectos').textContent = '0';
    document.getElementById('proyectos-finalizados').textContent = '0';
    document.getElementById('proyectos-en-curso').textContent = '0';
    document.getElementById('proyectos-retrasados').textContent = '0';
    
    // Mostrar mensaje de carga
    const tablaProyectos = document.querySelector('#tabla-proyectos tbody');
    if (tablaProyectos) {
        tablaProyectos.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Cargando proyectos...</p>
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Usar la URL actual del script para las solicitudes AJAX
    const currentScriptPath = window.location.pathname;
    const url = `${currentScriptPath}?ajax_action=get_proyectos`;
    console.log("URL de solicitud:", url);
    
    fetch(url)
        .then(response => {
            console.log("Respuesta recibida:", response.status, response.statusText);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log("Datos recibidos:", data);
            
            if (data.success && data.proyectos) {
                console.log(`Se encontraron ${data.proyectos.length} proyectos`);
                
                if (!tablaProyectos) {
                    console.error("No se encontró el elemento tabla-proyectos tbody");
                    return;
                }
                
                tablaProyectos.innerHTML = '';
                
                let proyectosFinalizados = 0;
                let proyectosEnCurso = 0;
                let proyectosRetrasados = 0;
                
                if (data.proyectos.length === 0) {
                    console.log("No hay proyectos para mostrar");
                    tablaProyectos.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No hay proyectos asignados actualmente</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    
                    // Actualizar estadísticas
                    document.getElementById('total-proyectos').textContent = '0';
                    document.getElementById('proyectos-finalizados').textContent = '0';
                    document.getElementById('proyectos-en-curso').textContent = '0';
                    document.getElementById('proyectos-retrasados').textContent = '0';
                    
                    return;
                }
                
                data.proyectos.forEach((proyecto, index) => {
                    console.log(`Procesando proyecto ${index + 1}:`, proyecto.id, proyecto.titulo);
                    
                    if (proyecto.estado === 'finalizado') proyectosFinalizados++;
                    if (proyecto.estado === 'en_proceso') proyectosEnCurso++;
                    // Lógica para determinar retrasos
                    if (proyecto.estado === 'en_proceso' && proyecto.avance_actual < 2) proyectosRetrasados++;
                    
                    const fila = document.createElement('tr');
                    fila.setAttribute('data-proyecto-id', proyecto.id);
                    fila.setAttribute('data-estado', proyecto.estado || 'pendiente');
                    fila.setAttribute('data-avance', proyecto.avance_actual || 0);
                    
                    // Definir clase para el estado
                    const claseEstado = {
                        'pendiente': 'estado-pendiente',
                        'en_proceso': 'estado-proceso',
                        'aprobado': 'estado-aprobado',
                        'finalizado': 'estado-finalizada'
                    }[proyecto.estado || 'pendiente'] || 'estado-pendiente';
                    
                    // Asegurarse de que todos los campos existan
                    const titulo = proyecto.titulo || 'Sin título';
                    const estudiantes = proyecto.estudiantes || [];
                    const avanceActual = proyecto.avance_actual || 0;
                    const progreso = proyecto.progreso || 0;
                    const estado = proyecto.estado || 'pendiente';
                    
                    console.log(`Proyecto ${index + 1} - Estudiantes:`, estudiantes.length);
                    
                    fila.innerHTML = `
                        <td>${htmlspecialchars_js(titulo)}</td>
                        <td>
                            ${estudiantes.length > 0 ? 
                                estudiantes.map(est => `
                                    <span class="estudiante-tag">${htmlspecialchars_js(est.nombre || 'Sin nombre')}</span>
                                `).join('') : 
                                '<span class="text-muted">Sin estudiantes asignados</span>'
                            }
                        </td>
                        <td>Avance ${avanceActual} de 4</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${progreso}%"></div>
                            </div>
                            <span class="progress-text">${Math.round(progreso)}%</span>
                        </td>
                        <td><span class="estado ${claseEstado}">${htmlspecialchars_js(estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(estado.replace('_', ' ')).slice(1)}</span></td>
                        <td>
                            <button class="btn-icon btn-detalle-proyecto" data-proyecto-id="${proyecto.id}" title="Ver Detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon btn-chat-proyecto" 
                                    data-proyecto-id="${proyecto.id}" 
                                    title="Chat del Proyecto">
                                <i class="fas fa-comments"></i>
                            </button>
                            <button class="btn-icon btn-avances-proyecto" 
                                    data-proyecto-id="${proyecto.id}" 
                                    title="Gestionar Avances">
                                <i class="fas fa-tasks"></i>
                            </button>
                        </td>
                    `;
                    
                    tablaProyectos.appendChild(fila);
                });
                
                console.log("Estadísticas:", {
                    total: data.proyectos.length,
                    finalizados: proyectosFinalizados,
                    enCurso: proyectosEnCurso,
                    retrasados: proyectosRetrasados
                });
                
                // Actualizar estadísticas
                document.getElementById('total-proyectos').textContent = data.proyectos.length;
                document.getElementById('proyectos-finalizados').textContent = proyectosFinalizados;
                document.getElementById('proyectos-en-curso').textContent = proyectosEnCurso;
                document.getElementById('proyectos-retrasados').textContent = proyectosRetrasados;
                
                // Agregar eventos a los botones
                document.querySelectorAll('.btn-detalle-proyecto').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const proyectoId = this.getAttribute('data-proyecto-id');
                        console.log("Click en ver detalle del proyecto:", proyectoId);
                        mostrarDetalleProyecto(proyectoId);
                    });
                });
                
                document.querySelectorAll('.btn-chat-proyecto').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const proyectoId = this.getAttribute('data-proyecto-id');
                        console.log("Click en chat del proyecto:", proyectoId);
                        alert('Funcionalidad de chat para proyecto ' + proyectoId + ' (en desarrollo)');
                    });
                });
                
                document.querySelectorAll('.btn-avances-proyecto').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const proyectoId = this.getAttribute('data-proyecto-id');
                        console.log("Click en avances del proyecto:", proyectoId);
                        mostrarAvancesProyecto(proyectoId);
                    });
                });
                
                console.log("Carga de proyectos completada con éxito");
            } else {
                console.error('Error al cargar proyectos:', data);
                if (tablaProyectos) {
                    tablaProyectos.innerHTML = `
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>Error al cargar proyectos: ${data.message || 'Error desconocido'}</p>
                                    <small>${data.debug_info || ''}</small>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                
                // Actualizar estadísticas con ceros
                document.getElementById('total-proyectos').textContent = '0';
                document.getElementById('proyectos-finalizados').textContent = '0';
                document.getElementById('proyectos-en-curso').textContent = '0';
                document.getElementById('proyectos-retrasados').textContent = '0';
            }
        })
        .catch(error => {
            console.error('Error en fetch cargarProyectos:', error);
            if (tablaProyectos) {
                tablaProyectos.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Error de conexión al cargar proyectos: ${error.message}</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            // Actualizar estadísticas con ceros
            document.getElementById('total-proyectos').textContent = '0';
            document.getElementById('proyectos-finalizados').textContent = '0';
            document.getElementById('proyectos-en-curso').textContent = '0';
            document.getElementById('proyectos-retrasados').textContent = '0';
        });
}

function mostrarDetalleProyecto(proyectoId) {
    // Usar la URL actual del script para las solicitudes AJAX
    const currentScriptPath = window.location.pathname;
    
    fetch(`${currentScriptPath}?ajax_action=get_detalle_proyecto&id=${encodeURIComponent(proyectoId)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            const detalleContenido = document.getElementById('detalle-proyecto-contenido');
            if (!detalleContenido) return;
            
            if (data.success && data.proyecto) {
                const proyecto = data.proyecto;
                
                // Generar HTML para el detalle
                detalleContenido.innerHTML = `
                    <div class="card">
                        <div class="card-header">
                            <h4>${htmlspecialchars_js(proyecto.titulo)}</h4>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-group">
                                    <h5>Información del Proyecto</h5>
                                    <p><strong>Título:</strong> ${htmlspecialchars_js(proyecto.titulo)}</p>
                                    <p><strong>Descripción:</strong> ${htmlspecialchars_js(proyecto.descripcion || 'No disponible')}</p>
                                    <p><strong>Fecha Inicio:</strong> ${proyecto.fecha_inicio ? new Date(proyecto.fecha_inicio).toLocaleDateString() : 'No definida'}</p>
                                    <p><strong>Fecha Fin:</strong> ${proyecto.fecha_fin ? new Date(proyecto.fecha_fin).toLocaleDateString() : 'No definida'}</p>
                                    <p><strong>Estado:</strong> <span class="estado ${getEstadoClassTutor(proyecto.estado)}">${htmlspecialchars_js(proyecto.estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(proyecto.estado.replace('_', ' ')).slice(1)}</span></p>
                                </div>

                                <div class="info-group">
                                    <h5>Estudiantes</h5>
                                    ${proyecto.estudiantes && proyecto.estudiantes.length > 0 ? 
                                        proyecto.estudiantes.map(est => `
                                            <div class="estudiante-item">
                                                <p><strong>${htmlspecialchars_js(est.nombre)}</strong> ${est.rol_en_proyecto === 'lider' ? '<span class="badge-lider">Líder</span>' : ''}</p>
                                                <p>Código: ${htmlspecialchars_js(est.codigo_estudiante || 'No disponible')}</p>
                                                <p>Email: ${htmlspecialchars_js(est.email || 'No disponible')}</p>
                                            </div>
                                        `).join('<hr>') :
                                        '<p>No hay estudiantes asignados a este proyecto</p>'
                                    }
                                </div>
                            </div>

                            <div class="avances-timeline">
                                <h5>Avances del Proyecto</h5>
                                ${proyecto.avances && proyecto.avances.length > 0 ? `
                                    <div class="timeline">
                                        ${proyecto.avances.map(avance => `
                                            <div class="timeline-item">
                                                <div class="timeline-marker ${getEstadoClassTimeline(avance.estado)}">
                                                    <i class="${getEstadoIconTimeline(avance.estado)}"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <h6>Avance ${avance.numero_avance}: ${htmlspecialchars_js(avance.titulo || 'Sin título')}</h6>
                                                    <p class="timeline-date">${avance.fecha_entrega ? new Date(avance.fecha_entrega).toLocaleDateString() : 'Pendiente'}</p>
                                                    <p class="estado ${getEstadoClassTimeline(avance.estado)}">${htmlspecialchars_js(avance.estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(avance.estado.replace('_', ' ')).slice(1)}</p>
                                                    ${avance.nota !== null ? `<p class="nota">Nota: ${htmlspecialchars_js(avance.nota)}</p>` : ''}
                                                    ${avance.comentario_tutor ? `<div class="comentario-tutor"><strong>Comentario:</strong> ${nl2br_js(htmlspecialchars_js(avance.comentario_tutor))}</div>` : ''}
                                                    ${avance.archivo_entregado ? `
                                                        <a href="../../uploads/proyectos/entregas/${encodeURIComponent(avance.archivo_entregado)}" target="_blank" class="btn-download">
                                                            <i class="fas fa-file-pdf"></i> Ver entrega
                                                        </a>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-muted">No hay avances registrados</p>'}
                            </div>
                            
                            ${proyecto.estado === 'en_proceso' ? `
                                <div class="acta-final">
                                    <h5>Finalizar Proyecto</h5>
                                    <form method="POST" enctype="multipart/form-data" class="form-acta">
                                        <input type="hidden" name="proyecto_id" value="${proyecto.id}">
                                        <input type="hidden" name="finalizar_proyecto" value="1">

                                        <div class="form-group">
                                            <label>Subir Acta Final (PDF):</label>
                                            <input type="file" name="archivo_acta" accept=".pdf" required>
                                        </div>

                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check-circle"></i> Finalizar Proyecto
                                        </button>
                                    </form>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                // Mostrar la sección de detalle
                showSection('detalle-proyecto');
            } else {
                console.error('Error al cargar detalle del proyecto:', data.message);
                detalleContenido.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Error al cargar detalle del proyecto: ${data.message || 'Error desconocido'}</p>
                    </div>
                `;
                showSection('detalle-proyecto');
            }
        })
        .catch(error => {
            console.error('Error en fetch mostrarDetalleProyecto:', error);
            const detalleContenido = document.getElementById('detalle-proyecto-contenido');
            if (detalleContenido) {
                detalleContenido.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error de conexión al cargar detalle del proyecto</p>
                    </div>
                `;
                showSection('detalle-proyecto');
            }
        });
}

function mostrarAvancesProyecto(proyectoId) {
    alert('Funcionalidad de gestión de avances para proyecto ' + proyectoId + ' (en desarrollo)');
}

// Función auxiliar para sanitizar texto en HTML
function htmlspecialchars_js(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
}

// Función auxiliar para convertir saltos de línea en <br>
function nl2br_js(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/\n/g, '<br>');
}

// Asegurarse de que la función se ejecute cuando se carga la sección de proyectos
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM cargado, configurando eventos para la sección de proyectos");
    
    // Verificar si estamos en la sección de proyectos al cargar la página
    const proyectosSection = document.getElementById('proyectos');
    const isProyectosSectionActive = proyectosSection && proyectosSection.classList.contains('active');
    
    if (isProyectosSectionActive) {
        console.log("Sección de proyectos activa al cargar la página, cargando proyectos...");
        cargarProyectos();
    }
    
    // Configurar evento para cargar proyectos cuando se hace clic en la pestaña
    const proyectosTab = document.querySelector('.nav-item[data-section="proyectos"]');
    if (proyectosTab) {
        console.log("Tab de proyectos encontrado, configurando evento de clic");
        proyectosTab.addEventListener('click', function() {
            console.log("Clic en tab de proyectos, cargando proyectos...");
            cargarProyectos();
        });
    } else {
        console.error("No se encontró el tab de proyectos");
    }
    
    // Configurar filtros
    const searchProyectos = document.getElementById('search-proyectos');
    const filterEstadoProyectos = document.getElementById('filter-estado-proyectos');
    const filterAvanceProyectos = document.getElementById('filter-avance-proyectos');
    
    if (searchProyectos) {
        searchProyectos.addEventListener('input', filtrarProyectos);
    }
    
    if (filterEstadoProyectos) {
        filterEstadoProyectos.addEventListener('change', filtrarProyectos);
    }
    
    if (filterAvanceProyectos) {
        filterAvanceProyectos.addEventListener('change', filtrarProyectos);
    }
});

function filtrarProyectos() {
    console.log("Filtrando proyectos...");
    const busqueda = document.getElementById('search-proyectos')?.value.toLowerCase() || '';
    const filtroEstado = document.getElementById('filter-estado-proyectos')?.value || '';
    const filtroAvance = document.getElementById('filter-avance-proyectos')?.value || '';
    
    console.log("Filtros:", { busqueda, filtroEstado, filtroAvance });
    
    const filas = document.querySelectorAll('#tabla-proyectos tbody tr');
    let filasVisibles = 0;
    
    filas.forEach(fila => {
        if (fila.querySelector('td:first-child')) {
            const titulo = fila.querySelector('td:first-child').textContent.toLowerCase() || '';
            const estado = fila.getAttribute('data-estado') || '';
            const avance = fila.getAttribute('data-avance') || '';
            
            const coincideTitulo = titulo.includes(busqueda);
            const coincideEstado = filtroEstado === '' || estado === filtroEstado;
            const coincideAvance = filtroAvance === '' || avance === filtroAvance;
            
            if (coincideTitulo && coincideEstado && coincideAvance) {
                fila.style.display = '';
                filasVisibles++;
            } else {
                fila.style.display = 'none';
            }
        }
    });
    
    console.log(`Filtrado completado: ${filasVisibles} filas visibles de ${filas.length} totales`);
}


















</script>
</body>
</html>