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
if (isset($_REQUEST['ajax_action'])) {
    $ajax_action = $_REQUEST['ajax_action'];

    switch ($ajax_action) {
        case 'get_messages':
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['proyecto_id'])) {
                $proyecto_id = filter_input(INPUT_GET, 'proyecto_id', FILTER_VALIDATE_INT);

                if ($proyecto_id) {
                    try {
                        // Primero verificar si el proyecto existe y pertenece al tutor
                        $query_check_proyecto = "SELECT p.id, ep.estudiante_id 
                                                FROM proyectos p 
                                                INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id 
                                                WHERE p.id = ? AND p.tutor_id = ? AND ep.rol_en_proyecto = 'líder'";
                        $stmt_check_proyecto = $conexion->prepare($query_check_proyecto);
                        $stmt_check_proyecto->execute([$proyecto_id, $tutor_id]);
                        $proyecto_info = $stmt_check_proyecto->fetch(PDO::FETCH_ASSOC);
                        $stmt_check_proyecto->closeCursor();

                        if (!$proyecto_info) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado a este proyecto']);
                            exit();
                        }

                        $estudiante_id_chat = $proyecto_info['estudiante_id'];

                        // IMPORTANTE: Usamos la tabla mensajes_chat con proyecto_id en lugar de pasantia_id
                        // Consulta adaptada para usar mensajes_chat con proyectos
                        $query = "SELECT m.*, u.nombre AS emisor_nombre 
                                  FROM mensajes_chat m
                                  LEFT JOIN usuarios u ON m.emisor_id = u.id
                                  WHERE m.pasantia_id = ? 
                                  ORDER BY m.fecha_envio ASC";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$proyecto_id]);
                        $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $stmt->closeCursor();

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
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Database error fetching messages: ' . $e->getMessage()
                        ]);
                    } catch (Exception $e) {
                        error_log("Error fetching chat messages (tutor, general): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Server error fetching messages: ' . $e->getMessage()
                        ]);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para obtener mensajes']);
            }
            exit();

        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proyecto_id'], $_POST['receptor_id'])) {
                $proyecto_id = filter_input(INPUT_POST, 'proyecto_id', FILTER_VALIDATE_INT);
                $receptor_id = filter_input(INPUT_POST, 'receptor_id', FILTER_VALIDATE_INT);
                $mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_UNSAFE_RAW);
                $archivo = $_FILES['archivo'] ?? null;

                $errores_chat = [];
                $archivo_nombre = null;
                $upload_dir = __DIR__ . "/../../uploads/proyectos/chat/";

                if (!$proyecto_id || !$receptor_id || !$tutor_id) {
                    $errores_chat[] = "Faltan IDs necesarios.";
                }

                if (empty($errores_chat)) {
                    try {
                        $query_check = "SELECT ep.estudiante_id 
                                        FROM proyectos p 
                                        INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id 
                                        WHERE p.id = ? AND p.tutor_id = ? AND ep.rol_en_proyecto = 'líder'";
                        $stmt_check = $conexion->prepare($query_check);
                        $stmt_check->execute([$proyecto_id, $tutor_id]);
                        $proyecto_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        $stmt_check->closeCursor();

                        if (!$proyecto_info) {
                            $errores_chat[] = "No autorizado para enviar mensajes a este proyecto.";
                        } elseif ($proyecto_info['estudiante_id'] != $receptor_id) {
                             $errores_chat[] = "El receptor del mensaje no corresponde al estudiante líder de este proyecto.";
                        }

                    } catch (PDOException $e) {
                        error_log("Error checking proyecto details for chat send (tutor): " . $e->getMessage());
                        $errores_chat[] = "Error de base de datos al verificar proyecto: " . $e->getMessage();
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
                         $archivo_nombre = $proyecto_id . "_chat_" . time() . "_" . $sanitized_filename . "." . $file_extension;
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
                        // Iniciar transacción
                        $conexion->beginTransaction();
                        
                        // IMPORTANTE: Usamos la tabla mensajes_chat con proyecto_id en lugar de pasantia_id
                        $query = "INSERT INTO mensajes_chat (pasantia_id, emisor_id, receptor_id, mensaje, archivo) 
                                  VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$proyecto_id, $tutor_id, $receptor_id, trim($mensaje), $archivo_nombre]);
                        $stmt->closeCursor();

                        // Verificar si existe la columna last_message_at en la tabla proyectos
                        $query_check_column = "SHOW COLUMNS FROM proyectos LIKE 'last_message_at'";
                        $stmt_check_column = $conexion->prepare($query_check_column);
                        $stmt_check_column->execute();
                        $column_exists = $stmt_check_column->fetch(PDO::FETCH_ASSOC);
                        $stmt_check_column->closeCursor();

                        // Actualizar timestamp de último mensaje solo si la columna existe
                        if ($column_exists) {
                            $query_update_proyecto = "UPDATE proyectos SET last_message_at = NOW() WHERE id = ?";
                            $stmt_update_proyecto = $conexion->prepare($query_update_proyecto);
                            $stmt_update_proyecto->execute([$proyecto_id]);
                            $stmt_update_proyecto->closeCursor();
                        }

                        // Confirmar la transacción
                        $conexion->commit();

                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);

                    } catch (PDOException $e) {
                        // Revertir la transacción en caso de error
                        if ($conexion->inTransaction()) {
                            $conexion->rollBack();
                        }
                        error_log("Error DB al enviar mensaje (tutor): " . $e->getMessage());
                         if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                             unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Error en la base de datos al enviar mensaje: ' . $e->getMessage()
                        ]);
                    } catch (Exception $e) {
                        // Revertir la transacción en caso de error
                        if ($conexion->inTransaction()) {
                            $conexion->rollBack();
                        }
                        error_log("Error general al enviar mensaje (tutor): " . $e->getMessage());
                         if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                             unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Error del servidor al enviar mensaje: ' . $e->getMessage()
                        ]);
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
                $proyecto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

                if ($proyecto_id) {
                    try {
                        $query_proyecto = "SELECT p.*, u.id AS estudiante_id, u.nombre AS estudiante_nombre, 
                                          u.codigo_estudiante, u.email AS estudiante_email, u.ciclo
                                          FROM proyectos p
                                          INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
                                          INNER JOIN usuarios u ON ep.estudiante_id = u.id
                                          WHERE p.id = ? AND p.tutor_id = ? AND ep.rol_en_proyecto = 'líder'";
                        $stmt_proyecto = $conexion->prepare($query_proyecto);
                        $stmt_proyecto->execute([$proyecto_id, $tutor_id]);
                        $proyecto_detalle = $stmt_proyecto->fetch(PDO::FETCH_ASSOC);
                        $stmt_proyecto->closeCursor();

                        if ($proyecto_detalle) {
                            $query_avances = "SELECT * FROM avances_proyecto WHERE proyecto_id = ? ORDER BY numero_avance ASC";
                            $stmt_avances = $conexion->prepare($query_avances);
                            $stmt_avances->execute([$proyecto_id]);
                            $avances_detalle = $stmt_avances->fetchAll(PDO::FETCH_ASSOC);
                            $stmt_avances->closeCursor();

                            $proyecto_detalle['avances'] = $avances_detalle;

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
                            $proyecto_detalle['puede_finalizar'] = ($total_entregas_contadas === 4 && $todos_avances_aprobados && $proyecto_detalle['estado'] === 'en_revision');

                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'proyecto' => $proyecto_detalle]);

                        } else {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado o no asignado']);
                        }

                    } catch (PDOException $e) {
                        error_log("Error fetching proyecto detail (tutor): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Database error fetching detail']);
                    } catch (Exception $e) {
                        error_log("Error fetching proyecto detail (tutor, general): " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Server error fetching detail']);
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
    }
}

// --- Lógica PHP para la carga inicial de la página ---
$query = "SELECT p.*, u.id AS estudiante_id, u.nombre AS estudiante_nombre, 
          u.codigo_estudiante, u.email AS estudiante_email, u.ciclo
          FROM proyectos p
          INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
          INNER JOIN usuarios u ON ep.estudiante_id = u.id
          WHERE p.tutor_id = ? AND ep.rol_en_proyecto = 'líder'
          ORDER BY p.fecha_creacion DESC";
$stmt = $conexion->prepare($query);
$stmt->bindParam(1, $tutor_id, PDO::PARAM_INT);
$stmt->execute();
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Obtener estadísticas
$total_proyectos = count($proyectos);
$proyectos_finalizados = 0;
$proyectos_en_curso = 0;
$proyectos_retrasados = 0;

foreach ($proyectos as $proyecto) {
    if ($proyecto['estado'] == 'finalizado') {
        $proyectos_finalizados++;
    } elseif ($proyecto['estado'] == 'en_revision') {
        $proyectos_en_curso++;

        // Verificar si hay retraso (más de 30 días sin avances)
        $query = "SELECT MAX(fecha_entrega) as ultima_entrega
                  FROM avances_proyecto
                  WHERE proyecto_id = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(1, $proyecto['id'], PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($resultado['ultima_entrega']) {
            $ultima_entrega = strtotime($resultado['ultima_entrega']);
            $dias_sin_avance = floor((time() - $ultima_entrega) / (60 * 60 * 24));
            if ($dias_sin_avance > 30) {
                $proyectos_retrasados++;
            }
        }
    }
}

// Obtener total de avances corregidos
$query = "SELECT COUNT(*) as total_avances
          FROM avances_proyecto a
          INNER JOIN proyectos p ON a.proyecto_id = p.id
          WHERE p.tutor_id = ? AND a.estado IN ('aprobado', 'corregir')";
$stmt = $conexion->prepare($query);
$stmt->bindParam(1, $tutor_id, PDO::PARAM_INT);
$stmt->execute();
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$total_avances_corregidos = $resultado['total_avances'];
$stmt->closeCursor();

// Procesar calificación de avance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar_avance'])) {
    $avance_id = filter_input(INPUT_POST, 'avance_id', FILTER_VALIDATE_INT);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_SPECIAL_CHARS);
    $nota = isset($_POST['nota']) ? filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_FLOAT) : null;
    $comentario = filter_input(INPUT_POST, 'comentario', FILTER_UNSAFE_RAW);

    $error = null;

    if ($nota !== null && ($nota < 0 || $nota > 5)) {
        $error = "La nota debe estar entre 0 y 5";
    }

    // Modificado: Solo permitir estados 'aprobado' y 'corregir'
    $allowed_states = ['aprobado', 'corregir'];
    if (!in_array($estado, $allowed_states)) {
        $error = "Estado de calificación inválido.";
    }

    if ($error === null) {
        try {
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Actualizar el estado del avance
            $query = "UPDATE avances_proyecto
                      SET estado = ?, nota = ?, comentario_tutor = ?
                      WHERE id = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bindParam(1, $estado, PDO::PARAM_STR);
            $stmt->bindParam(2, $nota, PDO::PARAM_STR);
            $stmt->bindParam(3, $comentario, PDO::PARAM_STR);
            $stmt->bindParam(4, $avance_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Si el estado es "aprobado", verificar si todos los avances están aprobados
                if ($estado === 'aprobado') {
                    // Obtener el ID del proyecto asociado a este avance
                    $query_proyecto_id = "SELECT proyecto_id FROM avances_proyecto WHERE id = ?";
                    $stmt_proyecto_id = $conexion->prepare($query_proyecto_id);
                    $stmt_proyecto_id->bindParam(1, $avance_id, PDO::PARAM_INT);
                    $stmt_proyecto_id->execute();
                    $proyecto_id_result = $stmt_proyecto_id->fetch(PDO::FETCH_ASSOC);
                    $stmt_proyecto_id->closeCursor();
                    
                    if ($proyecto_id_result) {
                        $proyecto_id = $proyecto_id_result['proyecto_id'];
                        
                        // Verificar si todos los avances están aprobados
                        $query_check_all = "SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados
                                           FROM avances_proyecto 
                                           WHERE proyecto_id = ?";
                        $stmt_check_all = $conexion->prepare($query_check_all);
                        $stmt_check_all->bindParam(1, $proyecto_id, PDO::PARAM_INT);
                        $stmt_check_all->execute();
                        $check_result = $stmt_check_all->fetch(PDO::FETCH_ASSOC);
                        $stmt_check_all->closeCursor();
                        
                        // Si hay 4 avances y todos están aprobados, actualizar el estado del proyecto a "aprobado"
                        if ($check_result['total'] == 4 && $check_result['aprobados'] == 4) {
                            $query_update_proyecto = "UPDATE proyectos SET estado = 'aprobado' WHERE id = ? AND estado = 'en_revision'";
                            $stmt_update_proyecto = $conexion->prepare($query_update_proyecto);
                            $stmt_update_proyecto->bindParam(1, $proyecto_id, PDO::PARAM_INT);
                            $stmt_update_proyecto->execute();
                            $stmt_update_proyecto->closeCursor();
                            
                            // Registrar en el log que el proyecto ha sido aprobado automáticamente
                            error_log("Proyecto ID: $proyecto_id ha sido aprobado automáticamente al completar los 4 avances.");
                        }
                    }
                }

                // Obtener información del estudiante para enviar correo
                $query_info = "SELECT u.email, u.nombre, p.titulo, a.numero_avance
                               FROM avances_proyecto a
                               INNER JOIN proyectos p ON a.proyecto_id = p.id
                               INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
                               INNER JOIN usuarios u ON ep.estudiante_id = u.id
                               WHERE a.id = ? AND ep.rol_en_proyecto = 'líder'";
                $stmt_info = $conexion->prepare($query_info);
                $stmt_info->bindParam(1, $avance_id, PDO::PARAM_INT);
                $stmt_info->execute();
                $info_estudiante = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $stmt_info->closeCursor();

                if ($info_estudiante) {
                    // Enviar correo al estudiante
                    $to = $info_estudiante['email'];
                    $subject = "Actualización sobre tu Avance " . $info_estudiante['numero_avance'] . " de Proyecto";
                    $message = "Hola " . $info_estudiante['nombre'] . ",\n\n";
                    $message .= "Tu tutor ha revisado tu Avance " . $info_estudiante['numero_avance'] . " para el proyecto '" . $info_estudiante['titulo'] . "'.\n";
                    $message .= "Estado: " . ucfirst($estado) . "\n";
                    if ($nota !== null) {
                        $message .= "Nota: " . number_format($nota, 1) . "\n";
                    }
                    if (!empty(trim($comentario))) {
                        $message .= "Comentarios del Tutor: " . $comentario . "\n";
                    }
                    $message .= "\nPor favor, revisa el portal de proyectos para más detalles.";
                    $message .= "\n\nEste es un mensaje automático, por favor no respondas a este correo.";

                    $headers = 'From: Sistema de Proyectos <sistema@proyectos.com>' . "\r\n" .
                               'Reply-To: noreply@proyectos.com' . "\r\n" .
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
                error_log("Error execute UPDATE avance: " . print_r($stmt->errorInfo(), true));
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
    $proyecto_id = filter_input(INPUT_POST, 'proyecto_id', FILTER_VALIDATE_INT);
    $archivo = $_FILES['archivo_acta'] ?? null;

    $error = null;

    if (!$proyecto_id) {
        $error = "ID de proyecto no proporcionado.";
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
            $query_check = "SELECT id FROM proyectos WHERE id = ? AND tutor_id = ? AND (estado = 'en_revision' OR estado = 'aprobado')";
            $stmt_check = $conexion->prepare($query_check);
            $stmt_check->execute([$proyecto_id, $tutor_id]);
            if (!$stmt_check->fetch()) {
                $error = "No autorizado para finalizar este proyecto o no está en estado 'En Revisión' o 'Aprobado'.";
            }
            $stmt_check->closeCursor();
        } catch (PDOException $e) {
            error_log("Error DB check proyecto para acta: " . $e->getMessage());
            $error = "Error de base de datos al verificar proyecto.";
        }

        if ($error === null) {
            $directorio_actas = __DIR__ . "/../../uploads/proyectos/actas/";
            if (!file_exists($directorio_actas)) {
                mkdir($directorio_actas, 0775, true);
            }

            $nombre_archivo = $proyecto_id . "_acta_" . time() . ".pdf";
            $ruta_archivo = $directorio_actas . $nombre_archivo;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
                try {
                    $query = "UPDATE proyectos
                              SET documento_adicional = ?,
                                  estado = 'finalizado'
                              WHERE id = ?";
                    $stmt = $conexion->prepare($query);
                    $stmt->bindParam(1, $nombre_archivo, PDO::PARAM_STR);
                    $stmt->bindParam(2, $proyecto_id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $stmt->closeCursor();
                        
                        // Obtener información del estudiante para enviar correo
                        $query_info = "SELECT u.email, u.nombre, p.titulo
                                      FROM proyectos p
                                      INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
                                      INNER JOIN usuarios u ON ep.estudiante_id = u.id
                                      WHERE p.id = ? AND ep.rol_en_proyecto = 'líder'";
                        $stmt_info = $conexion->prepare($query_info);
                        $stmt_info->bindParam(1, $proyecto_id, PDO::PARAM_INT);
                        $stmt_info->execute();
                        $info_estudiante = $stmt_info->fetch(PDO::FETCH_ASSOC);
                        $stmt_info->closeCursor();
                        
                        if ($info_estudiante) {
                            // Enviar correo al estudiante
                            $to = $info_estudiante['email'];
                            $subject = "Tu proyecto ha sido finalizado";
                            $message = "Hola " . $info_estudiante['nombre'] . ",\n\n";
                            $message .= "Tu tutor ha finalizado tu proyecto '" . $info_estudiante['titulo'] . "'.\n";
                            $message .= "El acta de finalización ya está disponible en el portal de proyectos.\n\n";
                            $message .= "Por favor, revisa el portal para ver tu nota final y descargar el acta.\n\n";
                            $message .= "Este es un mensaje automático, por favor no respondas a este correo.";
                            
                            $headers = 'From: Sistema de Proyectos <sistema@proyectos.com>' . "\r\n" .
                                      'Reply-To: noreply@proyectos.com' . "\r\n" .
                                      'X-Mailer: PHP/' . phpversion();
                            
                            if (!mail($to, $subject, $message, $headers)) {
                                error_log("Error al enviar correo de finalización a: " . $to);
                            }
                        }
                        
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $error = "Error al actualizar la base de datos";
                        error_log("Error execute UPDATE proyecto acta: " . print_r($stmt->errorInfo(), true));
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
    <title>Portal del Tutor - Gestión de Proyectos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tutor_proyectos.css">
</head>
<body>
    <script> const TUTOR_ID = <?php echo json_encode($tutor_id); ?>; </script>

    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="/assets/images/logofet.png" alt="FET Logo" style="width: 100px;">
                
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
                <a href="/views/profesores/tutor.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar</span>
                </a>
            </div>
        </nav>

        <div class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button id="toggle-sidebar" class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2>Sistema de Gestión de Proyectos</h2>
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
                            <h4>Total Proyectos</h4>
                            <p class="stat-value"><?php echo $total_proyectos; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Finalizados</h4>
                            <p class="stat-value"><?php echo $proyectos_finalizados; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h4>En Curso</h4>
                            <p class="stat-value"><?php echo $proyectos_en_curso; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Con Retraso</h4>
                            <p class="stat-value"><?php echo $proyectos_retrasados; ?></p>
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
                            <select id="filter-estado">
                                <option value="">Todos los estados</option>
                                <option value="propuesto">Propuesto</option>
                                <option value="en_revision">En Revisión</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="finalizado">Finalizado</option>
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
                                        <th>Título</th>
                                        <th>Progreso</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyectos as $proyecto):
                                        // Calcular progreso para mostrar en la tabla
                                        $query_progreso = "SELECT COUNT(*) as total_aprobados
                                                           FROM avances_proyecto
                                                           WHERE proyecto_id = ? AND estado = 'aprobado'";
                                        $stmt_progreso = $conexion->prepare($query_progreso);
                                        $stmt_progreso->bindParam(1, $proyecto['id'], PDO::PARAM_INT);
                                        $stmt_progreso->execute();
                                        $resultado_progreso = $stmt_progreso->fetch(PDO::FETCH_ASSOC);
                                        $progreso = ($resultado_progreso['total_aprobados'] / 4) * 100; // Asumiendo 4 avances totales
                                        $stmt_progreso->closeCursor();

                                        // Definir clase para el estado
                                        $clases_estado_tabla = [
                                            'propuesto' => 'estado-pendiente',
                                            'aprobado' => 'estado-aprobado',
                                            'rechazado' => 'estado-rechazado',
                                            'en_revision' => 'estado-proceso',
                                            'finalizado' => 'estado-finalizada',
                                        ];
                                        $clase_estado_tabla = $clases_estado_tabla[$proyecto['estado']] ?? 'estado-pendiente';
                                    ?>
                                    <tr data-proyecto-id="<?php echo htmlspecialchars($proyecto['id']); ?>"
                                        data-estudiante-id="<?php echo htmlspecialchars($proyecto['estudiante_id']); ?>"
                                        data-estado="<?php echo htmlspecialchars($proyecto['estado']); ?>"
                                        data-ciclo="<?php echo htmlspecialchars($proyecto['ciclo']); ?>">
                                        <td><?php echo htmlspecialchars($proyecto['estudiante_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($proyecto['codigo_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($proyecto['ciclo'])); ?></td>
                                        <td><?php echo htmlspecialchars($proyecto['titulo']); ?></td>
                                        <td>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progreso; ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo round($progreso); ?>%</span>
                                        </td>
                                        <td><span class="estado <?php echo $clase_estado_tabla; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $proyecto['estado']))); ?></span></td>
                                        <td>
                                            <button class="btn-icon btn-detalle" data-proyecto-id="<?php echo htmlspecialchars($proyecto['id']); ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon btn-chat-open"
                                                    data-proyecto-id="<?php echo htmlspecialchars($proyecto['id']); ?>"
                                                    data-estudiante-id="<?php echo htmlspecialchars($proyecto['estudiante_id']); ?>"
                                                    data-estudiante-nombre="<?php echo htmlspecialchars($proyecto['estudiante_nombre']); ?>"
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

            <section id="detalle-proyecto" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-info-circle"></i> Detalle de Proyecto</h3>
                </div>
                <div id="detalle-contenido">
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>Selecciona un proyecto de la lista en el Dashboard para ver los detalles.</p>
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
                        $query_pendientes = "SELECT a.*, p.titulo AS proyecto_titulo, u.nombre AS estudiante_nombre
                                             FROM avances_proyecto a
                                             INNER JOIN proyectos p ON a.proyecto_id = p.id
                                             INNER JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
                                             INNER JOIN usuarios u ON ep.estudiante_id = u.id
                                             WHERE p.tutor_id = ? AND a.estado = 'pendiente' AND ep.rol_en_proyecto = 'líder'
                                             ORDER BY a.fecha_entrega ASC";
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
                                        <a href="../../uploads/proyectos/entregas/<?php echo htmlspecialchars($avance['archivo_entregado']); ?>" target="_blank" class="btn-download">
                                            <i class="fas fa-file-pdf"></i> Ver entrega
                                        </a>
                                    </div>

                                    <form class="form-calificacion" method="POST">
                                        <input type="hidden" name="avance_id" value="<?php echo htmlspecialchars($avance['id']); ?>">
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
                        <?php foreach ($proyectos as $proyecto): ?>
                        <div class="chat-item"
                             data-proyecto-id="<?php echo htmlspecialchars($proyecto['id']); ?>"
                             data-estudiante-id="<?php echo htmlspecialchars($proyecto['estudiante_id']); ?>"
                             data-estudiante-nombre="<?php echo htmlspecialchars($proyecto['estudiante_nombre']); ?>">
                            <div class="chat-item-avatar">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="chat-item-info">
                                <h4><?php echo htmlspecialchars($proyecto['estudiante_nombre']); ?></h4>
                                <p><?php echo htmlspecialchars($proyecto['titulo']); ?></p>
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
                                    <input type="hidden" id="chat-proyecto-id" name="proyecto_id">
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
                                        <span class="stat-number"><?php echo $proyectos_finalizados; ?></span>
                                        <span class="stat-label">Proyectos Completados</span>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-circle warning">
                                        <span class="stat-number"><?php echo $proyectos_en_curso; ?></span>
                                        <span class="stat-label">En Proceso</span>
                                    </div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-circle danger">
                                        <span class="stat-number"><?php echo $proyectos_retrasados; ?></span>
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
    let currentProyectoId = null;
    let currentEstudianteId = null;
    let chatPollingInterval = null;

    function stopPolling() {
        if (chatPollingInterval) {
            clearInterval(chatPollingInterval);
            chatPollingInterval = null;
            console.log("Chat polling detenido.");
        }
    }

    function startPolling(proyectoId) {
        stopPolling();
        if (proyectoId) {
            chatPollingInterval = setInterval(function() {
                const chatSection = document.getElementById('chat');
                const isChatSectionActive = chatSection && chatSection.classList.contains('active');

                if (isChatSectionActive && currentProyectoId === proyectoId) {
                    cargarMensajes(proyectoId);
                } else {
                    stopPolling();
                }
            }, 5000);
            console.log(`Chat polling iniciado para proyecto ID ${proyectoId}`);
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
            case 'propuesto': return 'estado-pendiente';
            case 'aprobado': return 'estado-aprobado';
            case 'rechazado': return 'estado-rechazado';
            case 'en_revision': return 'estado-proceso';
            case 'finalizado': return 'estado-finalizada';
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
            if (currentProyectoId) {
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
                                <a href="../../uploads/proyectos/chat/${encodeURIComponent(mensaje.archivo)}" target="_blank">
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

    // --- Función para cargar mensajes para un proyectoId específico (AJAX GET) ---
    function cargarMensajes(proyectoIdToLoad) {
        const chatSection = document.getElementById('chat');
        const isChatSectionActive = chatSection && chatSection.classList.contains('active');

        if (!proyectoIdToLoad || (!isChatSectionActive && proyectoIdToLoad != currentProyectoId)) {
            return;
        }

        const targetProyectoId = proyectoIdToLoad || currentProyectoId;
        if (!targetProyectoId) {
            console.warn("cargarMensajes llamada sin un ID de proyecto válido.");
            return;
        }

        // Usar la URL actual del script para las solicitudes AJAX
        const currentScriptPath = window.location.pathname;
        
        fetch(`${currentScriptPath}?ajax_action=get_messages&proyecto_id=${encodeURIComponent(targetProyectoId)}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error(`Error en la respuesta del servidor al obtener mensajes para proyecto ${targetProyectoId}:`, err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                        console.error(`Error en la respuesta del servidor (no-JSON) al obtener mensajes para proyecto ${targetProyectoId}:`, response.status, response.statusText);
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.mensajes) {
                    if (currentProyectoId && (data.mensajes.length > 0 && data.mensajes[0].proyecto_id == currentProyectoId ||
                                              (data.mensajes.length === 0 && data.estudiante_id_chat == currentEstudianteId))) {
                        const currentMessagesCount = document.querySelectorAll('#chat-messages-container .chat-message').length;
                        if (data.mensajes.length !== currentMessagesCount || currentMessagesCount === 0) {
                            actualizarChat(data.mensajes);
                            console.log(`Chat para proyecto ${currentProyectoId} actualizado (${data.mensajes.length} mensajes).`);
                        }
                    } else if (currentProyectoId && data.mensajes && data.mensajes.length > 0 && data.mensajes[0].proyecto_id != currentProyectoId) {
                        console.warn("Recibidos mensajes para un proyecto diferente al actual. Ignorando actualización de UI.");
                    } else if (currentProyectoId && (!data.mensajes || data.mensajes.length === 0)) {
                        if (document.querySelectorAll('#chat-messages-container .chat-message').length > 0) {
                            actualizarChat([]);
                        } else if (document.getElementById('chat-messages-container').innerHTML === '') {
                            actualizarChat([]);
                        }
                    }
                } else {
                    console.error('API Error al obtener mensajes:', data.message);
                    const chatMessagesContainer = document.getElementById('chat-messages-container');
                    if (currentProyectoId && chatMessagesContainer) {
                        chatMessagesContainer.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-circle"></i><p>Error al cargar mensajes: ${htmlspecialchars_js(data.message)}</p></div>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error en fetch cargarMensajes:', error);
                const chatMessagesContainer = document.getElementById('chat-messages-container');
                if (currentProyectoId && chatMessagesContainer) {
                    chatMessagesContainer.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar mensajes.</p></div>`;
                }
            });
    }

    // Funciones de ayuda para generar HTML de detalle
    function generarHTMLDetalle(proyecto) {
        return `
            <div class="card">
                <div class="card-header">
                    <h4>${htmlspecialchars_js(proyecto.titulo)}</h4>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-group">
                            <h5>Información del Estudiante</h5>
                            <p><strong>Nombre:</strong> ${htmlspecialchars_js(proyecto.estudiante_nombre)}</p>
                            <p><strong>Código:</strong> ${htmlspecialchars_js(proyecto.codigo_estudiante)}</p>
                            <p><strong>Email:</strong> ${htmlspecialchars_js(proyecto.estudiante_email)}</p>
                            <p><strong>Ciclo:</strong> ${htmlspecialchars_js(proyecto.ciclo)}</p>
                        </div>

                        <div class="info-group">
                            <h5>Información del Proyecto</h5>
                            <p><strong>Tipo:</strong> ${htmlspecialchars_js(proyecto.tipo || 'No especificado')}</p>
                            <p><strong>Empresa:</strong> ${htmlspecialchars_js(proyecto.nombre_empresa || 'No aplica')}</p>
                            <p><strong>Descripción:</strong> ${htmlspecialchars_js(proyecto.descripcion || 'No especificada')}</p>
                        </div>

                        <div class="info-group">
                            <h5>Estado</h5>
                            <p><strong>Fecha de Creación:</strong> ${proyecto.fecha_creacion ? new Date(proyecto.fecha_creacion).toLocaleDateString() : 'No definida'}</p>
                            <p><strong>Estado:</strong> <span class="estado ${getEstadoClassTutor(proyecto.estado)}">${htmlspecialchars_js(proyecto.estado.replace('_', ' ')).charAt(0).toUpperCase() + htmlspecialchars_js(proyecto.estado.replace('_', ' ')).slice(1)}</span></p>
                        </div>
                    </div>

                    ${proyecto.archivo_proyecto ? `
                        <div class="documento-inicial">
                            <a href="../../uploads/proyectos/documentos/${encodeURIComponent(proyecto.archivo_proyecto)}" target="_blank" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Ver Documento Inicial
                            </a>
                        </div>
                    ` : ''}

                    <div class="avances-timeline">
                        <h5>Historial de Avances</h5>
                        ${generarTimelineAvances(proyecto.avances || [])}
                    </div>

                    ${proyecto.estado === 'en_revision' && proyecto.puede_finalizar ? `
                        <div class="acta-final">
                            <h5>Finalizar Proyecto</h5>
                            <form method="POST" enctype="multipart/form-data" class="form-acta">
                                <input type="hidden" name="proyecto_id" value="${proyecto.id}">
                                <input type="hidden" name="subir_acta" value="1">

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
                    
                    ${proyecto.estado === 'aprobado' ? `
                        <div class="acta-final">
                            <h5>Finalizar Proyecto Aprobado</h5>
                            <form method="POST" enctype="multipart/form-data" class="form-acta">
                                <input type="hidden" name="proyecto_id" value="${proyecto.id}">
                                <input type="hidden" name="subir_acta" value="1">

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
                            <a href="../../uploads/proyectos/entregas/${encodeURIComponent(avance.archivo_entregado)}" target="_blank" class="btn-download">
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
            if (currentProyectoId) {
                if(chatPlaceholder) chatPlaceholder.style.display = 'none';
                if(chatActive) chatActive.style.display = 'flex';
                startPolling(currentProyectoId);
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

    // --- Filtros de búsqueda en la tabla de proyectos ---
    const searchInput = document.getElementById('search-proyectos');
    const filterEstado = document.getElementById('filter-estado');
    const filterCiclo = document.getElementById('filter-ciclo');
    const tablaProyectosBody = document.querySelector('.table tbody');

    if (searchInput && filterEstado && filterCiclo && tablaProyectosBody) {
        function filtrarProyectos() {
            const busqueda = searchInput.value.toLowerCase();
            const estadoFiltro = filterEstado.value;
            const cicloFiltro = filterCiclo.value;

            const filas = tablaProyectosBody.getElementsByTagName('tr');

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

        searchInput.addEventListener('input', filtrarProyectos);
        filterEstado.addEventListener('change', filtrarProyectos);
        filterCiclo.addEventListener('change', filtrarProyectos);
    } else {
        console.error('No se encontraron todos los elementos para los filtros de proyectos.');
    }

    // --- Función para ver detalle de proyecto (AJAX GET) ---
    const tablaProyectos = document.querySelector('.table');

    if (tablaProyectos) {
        tablaProyectos.addEventListener('click', function(event) {
            const target = event.target.closest('.btn-detalle');

            if (target) {
                const proyectoId = target.getAttribute('data-proyecto-id');
                if (proyectoId) {
                    verDetalleProyecto(proyectoId);
                } else {
                    console.error('Botón de detalle clickeado sin data-proyecto-id');
                }
            }
        });
    } else {
        console.error('Tabla de proyectos no encontrada para delegación de eventos.');
    }

    window.verDetalleProyecto = function(proyectoId) {
        // Usar la URL actual del script para las solicitudes AJAX
        const currentScriptPath = window.location.pathname;
        
        fetch(`${currentScriptPath}?ajax_action=get_detalle&id=${encodeURIComponent(proyectoId)}`)
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
                const detalleSection = document.getElementById('detalle-proyecto');
                const detalleContenido = document.getElementById('detalle-contenido');

                if (!detalleSection || !detalleContenido) {
                    console.error('Elementos de la sección de detalle no encontrados.');
                    return;
                }

                if (data.success && data.proyecto) {
                    showSection('detalle-proyecto');

                    data.proyecto.avances = data.proyecto.avances || [];
                    detalleContenido.innerHTML = generarHTMLDetalle(data.proyecto);

                } else {
                    console.error('API Error al obtener detalle:', data.message);
                    alert('Error al cargar el detalle del proyecto: ' + data.message);
                    detalleContenido.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error al cargar detalle: ${htmlspecialchars_js(data.message)}</p></div>`;

                    showSection('detalle-proyecto');
                }
            })
            .catch(error => {
                console.error('Error en fetch verDetalleProyecto:', error);
                alert('Error de conexión al cargar el detalle.');
                const detalleContenido = document.getElementById('detalle-contenido');
                if(detalleContenido) {
                    detalleContenido.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar detalle.</p></div>`;
                    showSection('detalle-proyecto');
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
    const chatProyectoIdInput = document.getElementById('chat-proyecto-id');
    const chatEstudianteIdInput = document.getElementById('chat-estudiante-id');

    // --- Manejar clic en los elementos de la lista de chat ---
    const chatList = document.querySelector('.chat-list');
    if (chatList) {
        chatList.addEventListener('click', function(event) {
            const target = event.target.closest('.chat-item');

            if (target) {
                const proyectoId = target.getAttribute('data-proyecto-id');
                const estudianteId = target.getAttribute('data-estudiante-id');
                const estudianteNombre = target.getAttribute('data-estudiante-nombre');

                if (proyectoId && estudianteId && estudianteNombre) {
                    if (currentProyectoId === proyectoId) {
                        console.log(`Chat con proyecto ${proyectoId} ya activo.`);
                        startPolling(currentProyectoId);
                        return;
                    }

                    chatItems.forEach(ci => ci.classList.remove('active'));
                    target.classList.add('active');

                    if(chatPlaceholder) chatPlaceholder.style.display = 'none';
                    if(chatActive) chatActive.style.display = 'flex';

                    if(chatStudentNameDisplay) chatStudentNameDisplay.textContent = htmlspecialchars_js(estudianteNombre);
                    if(chatProyectoIdInput) chatProyectoIdInput.value = proyectoId;
                    if(chatEstudianteIdInput) chatEstudianteIdInput.value = estudianteId;

                    currentProyectoId = proyectoId;
                    currentEstudianteId = estudianteId;

                    if(chatMessagesContainer) chatMessagesContainer.innerHTML = '';

                    cargarMensajes(currentProyectoId);
                    startPolling(currentProyectoId);

                    const badge = target.querySelector('.chat-badge');
                    if (badge) {
                        badge.style.display = 'none';
                        badge.textContent = '0';
                    }
                    console.log(`Seleccionado chat con proyecto ID: ${proyectoId}, estudiante ID: ${estudianteId}`);

                    showSection('chat');

                } else {
                    console.error("Click en item de chat: faltan data attributes (proyectoId, estudianteId, estudianteNombre)");
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
    if (chatForm && chatInput && chatFile && chatProyectoIdInput && chatEstudianteIdInput && typeof TUTOR_ID_JS !== 'undefined') {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mensaje = chatInput.value.trim();
            const archivo = chatFile.files[0];
            const proyectoId = chatProyectoIdInput.value;
            const estudianteId = chatEstudianteIdInput.value;
            const tutorId = TUTOR_ID_JS;

            if (!proyectoId || !estudianteId || !tutorId || (!mensaje && !archivo)) {
                console.log('No se envió mensaje: chat no seleccionado, IDs faltantes o sin contenido.');
                if (!proyectoId || !estudianteId || !tutorId) {
                    alert("Error: No hay un chat seleccionado o faltan IDs.");
                }
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'send_message');
            formData.append('proyecto_id', proyectoId);
            // Eliminamos esta línea para evitar el error
            // formData.append('emisor_id', tutorId);
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

                    cargarMensajes(currentProyectoId);

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
            const proyectoId = this.getAttribute('data-proyecto-id');
            const estudianteId = this.getAttribute('data-estudiante-id');
            const estudianteNombre = this.getAttribute('data-estudiante-nombre');
            
            if (proyectoId && estudianteId && estudianteNombre) {
                // Encontrar el item de chat correspondiente y simular un clic
                const chatItem = document.querySelector(`.chat-item[data-proyecto-id="${proyectoId}"]`);
                if (chatItem) {
                    chatItem.click();
                } else {
                    console.error(`No se encontró el item de chat para el proyecto ${proyectoId}`);
                }
            }
        });
    });
});
</script>
</body>
</html>