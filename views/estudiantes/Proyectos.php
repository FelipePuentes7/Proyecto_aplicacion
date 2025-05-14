<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php'); // Asegúrate de que $conexion se crea aquí

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    header('Location: /views/general/login.php');
    exit();
}

// --- Lógica para manejar solicitudes AJAX (Chat) ---
if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'get_messages':
            // --- Lógica para obtener mensajes ---
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['proyecto_id'])) {
                $proyecto_id = filter_input(INPUT_GET, 'proyecto_id', FILTER_VALIDATE_INT);

                if ($proyecto_id) {
                    $mensajes = obtenerMensajesChat($conexion, $proyecto_id);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'mensajes' => $mensajes]);
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
            // --- Lógica para enviar mensajes ---
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proyecto_id'], $_POST['emisor_id'], $_POST['receptor_id'])) {
                $proyecto_id = filter_input(INPUT_POST, 'proyecto_id', FILTER_VALIDATE_INT);
                $emisor_id = filter_input(INPUT_POST, 'emisor_id', FILTER_VALIDATE_INT);
                $receptor_id = filter_input(INPUT_POST, 'receptor_id', FILTER_VALIDATE_INT);
                $mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_UNSAFE_RAW);
                $archivo = $_FILES['archivo'] ?? null;

                $errores_chat = [];
                $archivo_nombre = null;
                $upload_dir = __DIR__ . "/../../uploads/proyectos/chat/";

                // Validaciones básicas
                if (!$proyecto_id || !$emisor_id || !$receptor_id) {
                    $errores_chat[] = "Faltan IDs de la conversación.";
                }

                if (empty(trim($mensaje)) && (!$archivo || $archivo['size'] == 0)) {
                    $errores_chat[] = "No puedes enviar un mensaje vacío sin archivo adjunto.";
                }

                // Manejar subida de archivo si existe
                if ($archivo && $archivo['size'] > 0) {
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
                        $archivo_nombre = $proyecto_id . "_" . time() . "_" . $sanitized_filename . "." . $file_extension;
                        $target_file = $upload_dir . $archivo_nombre;

                        if (!move_uploaded_file($archivo['tmp_name'], $target_file)) {
                            $errores_chat[] = "Error técnico al mover el archivo subido.";
                            $archivo_nombre = null;
                            error_log("Error moviendo archivo de chat: " . $archivo['tmp_name'] . " a " . $target_file);
                        }
                    }
                }

                // Si no hay errores, insertar en la base de datos
                if (empty($errores_chat)) {
                    try {
                        $query = "INSERT INTO mensajes_proyecto (proyecto_id, emisor_id, receptor_id, mensaje, archivo) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$proyecto_id, $emisor_id, $receptor_id, trim($mensaje), $archivo_nombre]);

                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);

                    } catch (PDOException $e) {
                        error_log("Error DB al enviar mensaje: " . $e->getMessage());
                        if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                            unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error en la base de datos al enviar mensaje']);
                    } catch (Exception $e) {
                        error_log("Error general al enviar mensaje: " . $e->getMessage());
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
                echo json_encode(['success' => false, 'message' => 'Solicitud inválida para enviar mensaje (método incorrecto)']);
            }
            exit();
    }
}

// --- Fin de la lógica para manejar solicitudes AJAX ---

// El resto del código PHP para la carga inicial de la página
$estudiante_id = $_SESSION['usuario']['id'];
$nombre_estudiante = $_SESSION['usuario']['nombre'];
$carrera = $_SESSION['usuario']['ciclo'] ?? 'No especificada';

// Obtener datos del proyecto del estudiante
$query = "SELECT p.*, u.nombre AS tutor_nombre
          FROM proyectos p
          LEFT JOIN usuarios u ON p.tutor_id = u.id
          LEFT JOIN estudiantes_proyecto ep ON p.id = ep.proyecto_id
          WHERE ep.estudiante_id = ? AND ep.rol_en_proyecto = 'líder'";
$stmt = $conexion->prepare($query);
$stmt->execute([$estudiante_id]);
$proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proyecto) {
    echo "<div class='error-container'>No tienes un proyecto registrado. Por favor, comunícate con administración.</div>";
    exit();
}

$proyecto_id = $proyecto['id'];

// Obtener avances del proyecto
$query = "SELECT * FROM avances_proyecto WHERE proyecto_id = ? ORDER BY numero_avance";
$stmt = $conexion->prepare($query);
$stmt->execute([$proyecto_id]);
$avances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular progreso
$avances_aprobados = 0;
$total_avances = 4; // Definido el total de avances
$avances_entregados = [];

foreach ($avances as $avance) {
    $avances_entregados[$avance['numero_avance']] = $avance;
    if ($avance['estado'] == 'aprobado') {
        $avances_aprobados++;
    }
}

$porcentaje_progreso = ($avances_aprobados / $total_avances) * 100;

// Calcular nota final
$nota_final = 0;
$puede_ver_nota_final = false;

if (count($avances) == $total_avances) {
    $suma_notas = 0;
    $todas_calificadas = true;

    foreach ($avances as $avance) {
        if ($avance['nota'] === null) {
            $todas_calificadas = false;
            break;
        }
        $suma_notas += $avance['nota'];
    }

    if ($todas_calificadas) {
        $nota_final = $suma_notas / $total_avances;
        $puede_ver_nota_final = true;
    }
}

// Manejar subida de avance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_avance'])) {
    $numero_avance = filter_input(INPUT_POST, 'numero_avance', FILTER_VALIDATE_INT);
    $comentario_estudiante = filter_input(INPUT_POST, 'comentario_estudiante', FILTER_UNSAFE_RAW);
    $archivo = $_FILES['archivo_avance'] ?? null;

    $errores = [];

    // Validar avances
    $max_avance_actual = 0;
    foreach ($avances as $avance) {
        if ($avance['numero_avance'] > $max_avance_actual) {
            $max_avance_actual = $avance['numero_avance'];
        }
    }

    // Validar que el avance a subir sea el siguiente o uno que requiere corrección
    $avance_requiere_correccion = null;
    foreach ($avances as $avance) {
        if ($avance['numero_avance'] == $numero_avance && $avance['estado'] == 'corregir') {
            $avance_requiere_correccion = $numero_avance;
            break;
        }
    }

    if ($numero_avance > $max_avance_actual + 1 && !$avance_requiere_correccion) {
        $errores[] = "No puedes subir el avance $numero_avance sin completar los anteriores.";
    }

    // Validar archivo
    if (!$archivo || $archivo['size'] == 0) {
        $errores[] = "Debes adjuntar un archivo PDF.";
    } elseif (pathinfo($archivo['name'], PATHINFO_EXTENSION) !== 'pdf') {
        $errores[] = "El archivo debe ser PDF.";
    } elseif ($archivo['size'] > 5 * 1024 * 1024) {
        $errores[] = "El archivo es demasiado grande. Máximo 5MB.";
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo PDF: Código " . $archivo['error'];
    }

    if (empty($errores)) {
        $directorio_entregas = __DIR__ . "/../../uploads/proyectos/entregas/";
        if (!file_exists($directorio_entregas)) {
            mkdir($directorio_entregas, 0755, true);
        }

        // Sanitizar nombre de archivo
        $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $proyecto['titulo']);
        $nombre_archivo = $proyecto_id . "_avance_" . $numero_avance . "_" . $nombre_base . "_" . time() . ".pdf";
        $ruta_archivo = $directorio_entregas . $nombre_archivo;

        if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
            $existe_avance = false;
            $id_avance = null;
            foreach ($avances as $avance) {
                if ($avance['numero_avance'] == $numero_avance) {
                    $existe_avance = true;
                    $id_avance = $avance['id'];
                    break;
                }
            }

            try {
                $conexion->beginTransaction();

                if ($existe_avance) {
                    // Si ya existe y requiere corrección, actualizar
                    if ($avance_requiere_correccion) {
                        $query = "UPDATE avances_proyecto SET
                                    archivo_entregado = ?,
                                    comentario_estudiante = ?,
                                    estado = 'pendiente',
                                    fecha_entrega = NOW(),
                                    comentario_tutor = NULL,
                                    nota = NULL
                                    WHERE id = ?";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$nombre_archivo, $comentario_estudiante, $id_avance]);
                    } else {
                        $errores[] = "El avance $numero_avance ya ha sido entregado y no requiere correcciones.";
                        $conexion->rollBack();
                        if (file_exists($ruta_archivo)) {
                            unlink($ruta_archivo);
                        }
                    }
                } else {
                    // Insertar nuevo avance
                    $query = "INSERT INTO avances_proyecto
                                (proyecto_id, numero_avance, archivo_entregado, comentario_estudiante, estado)
                                VALUES (?, ?, ?, ?, 'pendiente')";
                    $stmt = $conexion->prepare($query);
                    $stmt->execute([$proyecto_id, $numero_avance, $nombre_archivo, $comentario_estudiante]);
                }

                if (empty($errores)) {
                    $conexion->commit();
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }

            } catch (PDOException $e) {
                $conexion->rollBack();
                error_log("Error en la transacción de entrega: " . $e->getMessage());
                $errores[] = "Error al procesar la entrega. Inténtalo de nuevo.";
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
        } else {
            error_log("Error moviendo archivo de avance: " . $archivo['tmp_name'] . " a " . $ruta_archivo);
            $errores[] = "Error técnico al subir el archivo de avance. Contacta al administrador.";
        }
    }
}

// Función para formatear fechas
function formatearFecha($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}

// Obtener mensajes del chat para la CARGA INICIAL de la página
function obtenerMensajesChat($conexion, $proyecto_id) {
    if (!$proyecto_id) {
        error_log("obtenerMensajesChat llamada con ID de proyecto nulo/inválido");
        return [];
    }
    try {
        $query = "SELECT m.*, u.nombre AS emisor_nombre
                  FROM mensajes_proyecto m
                  INNER JOIN usuarios u ON m.emisor_id = u.id
                  WHERE m.proyecto_id = ?
                  ORDER BY m.fecha_envio ASC";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$proyecto_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error DB en obtenerMensajesChat: " . $e->getMessage());
        return [];
    }
}

// Verificar notificaciones
$notificaciones = 0;
if (isset($proyecto_id)) {
    $query = "SELECT COUNT(*) as total FROM avances_proyecto
              WHERE proyecto_id = ? AND comentario_tutor IS NOT NULL
              AND estado = 'corregir'";
    $stmt = $conexion->prepare($query);
    $stmt->execute([$proyecto_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $notificaciones = $resultado['total'];
}

// Clases para el estado del proyecto
$clases_estado = [
    'propuesto' => 'estado-pendiente',
    'en_revision' => 'estado-proceso',
    'aprobado' => 'estado-aprobado',
    'finalizado' => 'estado-finalizado'
];

$clase_estado = isset($proyecto['estado'], $clases_estado[$proyecto['estado']]) ?
               $clases_estado[$proyecto['estado']] :
               'estado-pendiente';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Proyectos - Estudiante</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/estudiante_proyectos.css">
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-university logo"></i>
                <h3>Portal Proyectos</h3>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($nombre_estudiante); ?></p>
                    <p class="user-role"><?php echo htmlspecialchars($carrera); ?></p>
                </div>
            </div>

            <ul class="sidebar-nav">
                <li class="nav-item active" data-section="resumen">
                    <i class="fas fa-file-alt"></i>
                    <span>Resumen</span>
                </li>
                <li class="nav-item" data-section="progreso">
                    <i class="fas fa-chart-line"></i>
                    <span>Progreso</span>
                </li>
                <li class="nav-item" data-section="subir-avance">
                    <i class="fas fa-upload"></i>
                    <span>Subir Avance</span>
                </li>
                <li class="nav-item" data-section="historial">
                    <i class="fas fa-history"></i>
                    <span>Historial</span>
                </li>
                <li class="nav-item" data-section="chat">
                    <i class="fas fa-comments"></i>
                    <span>Chat con Tutor</span>
                    <span class="badge" id="chat-badge">0</span>
                </li>
                <?php if ($puede_ver_nota_final): ?>
                <li class="nav-item" data-section="nota-final">
                    <i class="fas fa-award"></i>
                    <span>Nota Final</span>
                </li>
                <?php endif; ?>
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
                    <h2>Sistema de Gestión de Proyectos</h2>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificaciones > 0): ?>
                        <span class="notification-badge"><?php echo $notificaciones; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if (isset($errores) && !empty($errores)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <ul>
                    <?php foreach ($errores as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <section id="resumen" class="content-section active">
                <div class="section-header">
                    <h3><i class="fas fa-file-alt"></i> Resumen del Proyecto</h3>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Detalles del Proyecto</h4>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Título del Proyecto:</span>
                                <span class="info-value"><?php echo htmlspecialchars($proyecto['titulo']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tipo de Proyecto:</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst($proyecto['tipo'] ?? 'No especificado')); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Empresa:</span>
                                <span class="info-value"><?php echo htmlspecialchars($proyecto['nombre_empresa'] ?: 'No aplica'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tutor Asignado:</span>
                                <span class="info-value"><?php echo htmlspecialchars($proyecto['tutor_nombre'] ?: 'No asignado'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha de Creación:</span>
                                <span class="info-value"><?php echo $proyecto['fecha_creacion'] ? date('d/m/Y', strtotime($proyecto['fecha_creacion'])) : 'No definida'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Estado:</span>
                                <span class="info-value estado <?php echo $clase_estado; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $proyecto['estado'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($proyecto['archivo_proyecto']): ?>
                        <div class="document-section">
                            <a href="../../uploads/proyectos/documentos/<?php echo htmlspecialchars($proyecto['archivo_proyecto']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Ver Documento Inicial
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="progreso" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-line"></i> Progreso del Proyecto</h3>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="progress-container">
                            <div class="progress-percentage"><?php echo round($porcentaje_progreso); ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $porcentaje_progreso; ?>%"></div>
                            </div>
                            <div class="progress-steps">
                                <?php for ($i = 1; $i <= $total_avances; $i++):
                                    $avance_class = 'pending';
                                    $avance_icon = 'fas fa-circle';

                                    if (isset($avances_entregados[$i])) {
                                        $estado = $avances_entregados[$i]['estado'];
                                        if ($estado == 'aprobado') {
                                            $avance_class = 'completed';
                                            $avance_icon = 'fas fa-check-circle';
                                        } elseif ($estado == 'corregir') {
                                            $avance_class = 'correction';
                                            $avance_icon = 'fas fa-exclamation-circle';
                                        } elseif ($estado == 'pendiente' || $estado == 'revisado') {
                                            $avance_class = 'in-progress';
                                            $avance_icon = 'fas fa-clock';
                                        }
                                    }
                                ?>
                                <div class="progress-step <?php echo $avance_class; ?>">
                                    <div class="step-icon">
                                        <i class="<?php echo $avance_icon; ?>"></i>
                                    </div>
                                    <div class="step-label">Avance <?php echo $i; ?></div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="progress-legend">
                            <div class="legend-item">
                                <div class="legend-icon pending"><i class="fas fa-circle"></i></div>
                                <div class="legend-text">Pendiente</div>
                            </div>
                            <div class="legend-item">
                                <div class="legend-icon in-progress"><i class="fas fa-clock"></i></div>
                                <div class="legend-text">En revisión</div>
                            </div>
                            <div class="legend-item">
                                <div class="legend-icon correction"><i class="fas fa-exclamation-circle"></i></div>
                                <div class="legend-text">Requiere corrección</div>
                            </div>
                            <div class="legend-item">
                                <div class="legend-icon completed"><i class="fas fa-check-circle"></i></div>
                                <div class="legend-text">Aprobado</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="subir-avance" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-upload"></i> Subir Avance</h3>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="form-avance" method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="numero_avance">Número de Avance:</label>
                                <select id="numero_avance" name="numero_avance" required>
                                    <?php
                                        // Determinar el próximo avance que debe subirse o si hay uno para corregir
                                        $proximo_avance_disponible = 1;
                                        $avance_para_corregir = null;

                                        // Ordenar los avances por número de avance para encontrar el más alto
                                        $avances_ordenados = $avances;
                                        usort($avances_ordenados, function($a, $b) {
                                            return $a['numero_avance'] <=> $b['numero_avance'];
                                        });

                                        foreach ($avances_ordenados as $avance) {
                                            if ($avance['estado'] == 'corregir') {
                                                $avance_para_corregir = $avance['numero_avance'];
                                                break;
                                            }
                                            if ($avance['numero_avance'] >= $proximo_avance_disponible) {
                                                $proximo_avance_disponible = $avance['numero_avance'] + 1;
                                            }
                                        }

                                        // Limitar a máximo 4 avances
                                        if ($proximo_avance_disponible > $total_avances) {
                                            $proximo_avance_disponible = $total_avances + 1;
                                        }

                                        if ($avance_para_corregir !== null) {
                                            echo "<option value='{$avance_para_corregir}'>Avance {$avance_para_corregir} (Requiere corrección)</option>";
                                        } elseif ($proximo_avance_disponible <= $total_avances) {
                                            echo "<option value='{$proximo_avance_disponible}'>Avance {$proximo_avance_disponible}</option>";
                                        } else {
                                            echo "<option value='' disabled>Todos los avances entregados</option>";
                                        }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="comentario_estudiante">Comentarios (opcional):</label>
                                <textarea id="comentario_estudiante" name="comentario_estudiante" rows="4" placeholder="Agrega comentarios sobre tu entrega..."></textarea>
                            </div>

                            <div class="form-group file-upload">
                                <label for="archivo_avance">Archivo (PDF, máx. 5MB):</label>
                                <div class="file-input-container">
                                    <input type="file" id="archivo_avance" name="archivo_avance" accept=".pdf" required>
                                    <div class="file-input-custom">
                                        <span id="file-name">Ningún archivo seleccionado</span>
                                        <button type="button" class="btn-browse">
                                            <i class="fas fa-folder-open"></i> Explorar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <?php
                                    $can_submit_avance = ($proximo_avance_disponible <= $total_avances) || ($avance_para_corregir !== null);
                                ?>
                                <button type="submit" name="submit_avance" class="btn btn-primary" <?php echo $can_submit_avance ? '' : 'disabled'; ?>>
                                    <i class="fas fa-paper-plane"></i> Enviar Avance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section id="historial" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Historial de Entregas</h3>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($avances)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>Aún no has realizado ninguna entrega.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Avance</th>
                                        <th>Archivo</th>
                                        <th>Comentario Tutor</th>
                                        <th>Nota</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($avances as $avance):
                                        $estado_class = '';
                                        switch ($avance['estado']) {
                                            case 'pendiente': $estado_class = 'estado-pendiente'; break;
                                            case 'corregir': $estado_class = 'estado-corregir'; break;
                                            case 'aprobado': $estado_class = 'estado-aprobado'; break;
                                            case 'revisado': $estado_class = 'estado-revisado'; break;
                                            default: $estado_class = 'estado-pendiente'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td>Avance <?php echo htmlspecialchars($avance['numero_avance']); ?></td>
                                        <td>
                                            <?php if ($avance['archivo_entregado']): ?>
                                            <a href="../../uploads/proyectos/entregas/<?php echo htmlspecialchars($avance['archivo_entregado']); ?>" target="_blank" class="btn-download">
                                                <i class="fas fa-file-pdf"></i> Ver PDF
                                            </a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($avance['comentario_tutor']): ?>
                                                <div class="comentario-tutor">
                                                    <?php echo nl2br(htmlspecialchars($avance['comentario_tutor'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin comentarios</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $avance['nota'] !== null ? number_format($avance['nota'], 1) : '—'; ?></td>
                                        <td><span class="estado <?php echo $estado_class; ?>"><?php echo htmlspecialchars(ucfirst($avance['estado'])); ?></span></td>
                                        <td><?php echo formatearFecha($avance['fecha_entrega']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="chat" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-comments"></i> Chat con Tutor</h3>
                </div>

                <div class="card chat-card">
                    <div class="chat-header">
                        <div class="chat-user">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($proyecto['tutor_nombre'] ?: 'Tutor no asignado'); ?></span>
                        </div>
                        <div class="chat-status">
                            <?php if ($proyecto['tutor_id']): ?>
                                <span class="status-indicator online"></span>
                                <span class="status-text">Disponible</span>
                            <?php else: ?>
                                <span class="status-indicator offline"></span>
                                <span class="status-text">No disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="chat-body" id="chat-messages">
                        <?php if (!$proyecto['tutor_id']): ?>
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <p>Tu tutor aún no ha sido asignado. El chat estará disponible una vez que se te asigne un tutor.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="chat-footer">
                        <form id="chat-form" class="chat-input-container">
                            <input type="hidden" id="proyecto-id" value="<?php echo htmlspecialchars($proyecto_id); ?>">
                            <input type="hidden" id="emisor-id" value="<?php echo htmlspecialchars($estudiante_id); ?>">
                            <input type="hidden" id="receptor-id" value="<?php echo htmlspecialchars($proyecto['tutor_id'] ?: ''); ?>">
                            <div class="chat-attachment">
                                <label for="chat-file" class="chat-file-label">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" id="chat-file" name="chat-file" style="display: none;">
                            </div>

                            <div class="chat-text">
                                <textarea id="chat-message" placeholder="Escribe un mensaje..." <?php echo $proyecto['tutor_id'] ? '' : 'disabled'; ?>></textarea>
                            </div>

                            <button type="submit" class="chat-send" <?php echo $proyecto['tutor_id'] ? '' : 'disabled'; ?>>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="nota-final" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-award"></i> Nota Final y Acta</h3>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if ($puede_ver_nota_final): ?>
                        <div class="nota-final-container">
                            <div class="nota-circle">
                                <div class="nota-value"><?php echo number_format($nota_final, 1); ?></div>
                                <div class="nota-label">Calificación Final</div>
                            </div>

                            <div class="nota-desglose">
                                <h4>Desglose de Nota</h4>
                                <div class="table-responsive">
                                    <table class="table nota-table">
                                        <thead>
                                            <tr>
                                                <th>Avance</th>
                                                <th>Nota</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($avances as $avance):
                                                if ($avance['nota'] !== null):
                                            ?>
                                            <tr>
                                                <td>Avance <?php echo htmlspecialchars($avance['numero_avance']); ?></td>
                                                <td><?php echo number_format($avance['nota'], 1); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                            <tr class="total-row">
                                                <td><strong>Promedio</strong></td>
                                                <td><strong><?php echo number_format($nota_final, 1); ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($proyecto['documento_adicional']) && $proyecto['documento_adicional']): ?>
                        <div class="acta-container">
                            <a href="../../uploads/proyectos/actas/<?php echo htmlspecialchars($proyecto['documento_adicional']); ?>" class="btn btn-download" target="_blank">
                                <i class="fas fa-file-pdf"></i> Descargar Acta de Finalización
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="notification-info">
                            <i class="fas fa-info-circle"></i>
                            <p>El acta de finalización aún no ha sido generada por el tutor.</p>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-lock"></i>
                            <p>La nota final estará disponible cuando todos los avances hayan sido aprobados y calificados.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('JavaScript cargado correctamente');

    // --- Navegación entre secciones ---
    const navItems = document.querySelectorAll('.nav-item');
    const contentSections = document.querySelectorAll('.content-section');
    const chatBadge = document.getElementById('chat-badge');

    // Función para mostrar una sección
    const originalShowSection = function(sectionId) {
        const targetSection = document.getElementById(sectionId);
        if (!targetSection) {
            console.error(`No se encontró la sección con id: ${sectionId}`);
            return;
        }

        // Remover clase active de todas las secciones y nav-items
        contentSections.forEach(section => section.classList.remove('active'));
        navItems.forEach(navItem => navItem.classList.remove('active'));

        // Agregar clase active a la sección y nav-item seleccionados
        targetSection.classList.add('active');
        const activeNavItem = document.querySelector(`.nav-item[data-section="${sectionId}"]`);
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }

        console.log(`Mostrando sección: ${sectionId}`);
    };

    // Redefinir showSection para añadir lógica específica del chat
    const showSection = function(sectionId) {
        originalShowSection(sectionId);

        // Lógica específica al mostrar la sección de chat
        if (sectionId === 'chat') {
            const proyectoId = document.getElementById('proyecto-id')?.value;
            if (proyectoId) {
                localStorage.setItem('lastChatView_' + proyectoId, Date.now());
                if (chatBadge) chatBadge.style.display = 'none';
                console.log('Sección de chat mostrada, marcando mensajes como leídos.');
                cargarMensajes();
            }
        }
    };

    // Manejar clic en los items del menú
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sectionId = this.dataset.section;
            if (sectionId) {
                showSection(sectionId);
            } else {
                console.error('El elemento nav-item no tiene data-section definido', this);
            }
        });
    });

    // Mostrar sección inicial
    showSection('resumen');

    // --- Toggle sidebar en dispositivos móviles ---
    const toggleSidebar = document.getElementById('toggle-sidebar');
    const wrapper = document.querySelector('.wrapper');

    if (toggleSidebar && wrapper) {
        toggleSidebar.addEventListener('click', function() {
            wrapper.classList.toggle('sidebar-collapsed');
            console.log('Sidebar toggled');
        });
    } else {
        console.error('No se encontraron los elementos toggle-sidebar o wrapper');
    }

    // --- Manejar subida de archivos en el formulario de Avance ---
    const avanceFileInput = document.getElementById('archivo_avance');
    const avanceFileNameDisplay = document.getElementById('file-name');
    const avanceBrowseButton = document.querySelector('#form-avance .btn-browse');

    if (avanceBrowseButton && avanceFileInput && avanceFileNameDisplay) {
        avanceBrowseButton.addEventListener('click', function() {
            avanceFileInput.click();
            console.log('Botón de exploración de avance clickeado');
        });

        avanceFileInput.addEventListener('change', function() {
            avanceFileNameDisplay.textContent = this.files.length > 0
                ? this.files[0].name
                : 'Ningún archivo seleccionado';
            console.log('Archivo de avance seleccionado:', this.files[0]?.name);
        });
    } else {
        console.error('No se encontraron los elementos para la subida de archivos de avance');
    }

    // --- Chat en tiempo real ---
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-message');
    const chatFile = document.getElementById('chat-file');
    const chatFileLabel = document.querySelector('.chat-file-label');
    const proyectoId = document.getElementById('proyecto-id')?.value;
    const emisorId = document.getElementById('emisor-id')?.value;
    const receptorId = document.getElementById('receptor-id')?.value;

    // Deshabilitar chat si no hay tutor asignado
    if (!receptorId) {
        console.warn('Chat deshabilitado: Tutor no asignado.');
        if (chatInput) chatInput.disabled = true;
        if (chatForm) chatForm.querySelector('.chat-send').disabled = true;
        if (chatFile) chatFile.disabled = true;
        if (chatFileLabel) chatFileLabel.style.pointerEvents = 'none';
    }

    // Función para formatear fechas
    function formatDate(dateString) {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return 'Fecha inválida';
        }
        return date.toLocaleString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Función para actualizar el chat
    function actualizarChat(mensajes) {
        const chatMessages = document.getElementById('chat-messages');
        if (!chatMessages) {
            console.error('chat-messages element not found for update');
            return;
        }
        chatMessages.innerHTML = '';

        if (!mensajes || mensajes.length === 0) {
            if (!receptorId) {
                chatMessages.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Tu tutor aún no ha sido asignado. El chat estará disponible una vez que se te asigne un tutor.</p>
                    </div>
                `;
            } else {
                chatMessages.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No hay mensajes todavía. Inicia la conversación con tu tutor.</p>
                    </div>
                `;
            }
            return;
        }

        mensajes.forEach(mensaje => {
            const esEmisor = mensaje.emisor_id == emisorId;
            const claseMsg = esEmisor ? 'message-sent' : 'message-received';
            const mensajeHtml = `
                <div class="chat-message ${claseMsg}">
                    <div class="message-content">
                        ${mensaje.archivo ? `
                            <div class="message-attachment">
                                <a href="../../uploads/proyectos/chat/${encodeURIComponent(mensaje.archivo)}" target="_blank">
                                    <i class="fas fa-paperclip"></i> ${htmlspecialchars(mensaje.archivo)}
                                </a>
                            </div>
                        ` : ''}
                        ${mensaje.mensaje ? `
                            <div class="message-text">${nl2br_js(htmlspecialchars(mensaje.mensaje))}</div>
                        ` : ''}
                        <div class="message-time">${formatDate(mensaje.fecha_envio)}</div>
                    </div>
                </div>
            `;
            chatMessages.insertAdjacentHTML('beforeend', mensajeHtml);
        });

        // Hacer scroll al último mensaje
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Funciones de ayuda para JS
    function htmlspecialchars(str) {
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

    // Función para cargar mensajes
    function cargarMensajes() {
        const proyectoId = document.getElementById('proyecto-id')?.value;
        if (!proyectoId || !receptorId) {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages && chatMessages.innerHTML === '') {
                actualizarChat([]);
            }
            return;
        }

        fetch(`Proyectos.php?action=get_messages&proyecto_id=${encodeURIComponent(proyectoId)}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error('Error en la respuesta del servidor al obtener mensajes:', err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                        console.error('Error en la respuesta del servidor (no-JSON) al obtener mensajes:', response.status, response.statusText);
                        throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const currentMessagesCount = document.querySelectorAll('#chat-messages .chat-message').length;
                    if (data.mensajes && data.mensajes.length > currentMessagesCount) {
                        actualizarChat(data.mensajes);

                        // Actualizar badge de mensajes no leídos
                        const chatSection = document.getElementById('chat');
                        if (chatSection && !chatSection.classList.contains('active') && chatBadge) {
                            let unreadCount = 0;
                            const lastViewTime = localStorage.getItem('lastChatView_' + proyectoId) || 0;

                            data.mensajes.forEach(mensaje => {
                                if (mensaje.emisor_id != emisorId &&
                                    new Date(mensaje.fecha_envio).getTime() > lastViewTime) {
                                    unreadCount++;
                                }
                            });

                            if (unreadCount > 0) {
                                chatBadge.textContent = unreadCount;
                                chatBadge.style.display = 'inline-flex';
                            } else {
                                chatBadge.style.display = 'none';
                            }
                        } else if (chatBadge) {
                            chatBadge.style.display = 'none';
                        }

                    } else if (!data.mensajes || data.mensajes.length === 0) {
                        if (currentMessagesCount > 0) {
                            actualizarChat([]);
                        } else {
                            const chatMessages = document.getElementById('chat-messages');
                            if (chatMessages && chatMessages.innerHTML === '') {
                                actualizarChat([]);
                            }
                        }
                        if(chatBadge) chatBadge.style.display = 'none';
                    }
                } else {
                    console.error('API Error al obtener mensajes:', data.message);
                    const chatMessages = document.getElementById('chat-messages');
                    if (chatMessages) {
                        chatMessages.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-circle"></i><p>Error al cargar mensajes: ${htmlspecialchars(data.message)}</p></div>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error en fetch cargarMensajes:', error);
                const chatMessages = document.getElementById('chat-messages');
                if (chatMessages) {
                    chatMessages.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar mensajes.</p></div>`;
                }
            });
    }

    // Manejar envío de mensajes
    if (chatForm && chatInput && chatFile && proyectoId && emisorId && receptorId) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mensaje = chatInput.value.trim();
            const archivo = chatFile.files[0];

            if (!mensaje && !archivo) {
                console.log('No se envió mensaje: sin contenido');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('proyecto_id', proyectoId);
            formData.append('emisor_id', emisorId);
            formData.append('receptor_id', receptorId);
            formData.append('mensaje', mensaje);
            if (archivo) {
                formData.append('archivo', archivo);
            }

            fetch('Proyectos.php', {
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
                    const chatFileNameDisplay = document.querySelector('.chat-attachment span');
                    if(chatFileNameDisplay) chatFileNameDisplay.textContent = '';

                    cargarMensajes();
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

        // Mostrar el nombre del archivo seleccionado
        if(chatFile && chatFileLabel){
            chatFile.addEventListener('change', function() {
                const chatFileNameDisplay = document.querySelector('.chat-attachment span');
                if(chatFileNameDisplay){
                    chatFileNameDisplay.textContent = this.files.length > 0 ? this.files[0].name : '';
                }
            });
        }
    } else {
        console.warn('Formulario de chat no inicializado o deshabilitado.');
    }

    // Notificaciones de entregas
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationBadge = document.querySelector('.notification-badge');

    if (notificationIcon && notificationBadge) {
        notificationIcon.addEventListener('click', function() {
            const historialNavItem = document.querySelector('[data-section="historial"]');
            if (historialNavItem) {
                historialNavItem.click();
                console.log('Clic en ícono de notificaciones, mostrando historial');
            } else {
                console.error('No se encontró el nav-item para historial');
            }
        });
    } else {
        console.error('No se encontraron los elementos de notificación');
    }

    // Cargar mensajes iniciales y configurar recarga periódica
    const receptorIdCheck = document.getElementById('receptor-id')?.value;
    if (receptorIdCheck) {
        cargarMensajes();
        setInterval(cargarMensajes, 5000);
        console.log("Polling de chat iniciado.");
    } else {
        console.warn("Polling de chat no iniciado: Tutor no asignado.");
        const chatNavItem = document.querySelector('[data-section="chat"]');
        if(chatNavItem){
            chatNavItem.addEventListener('click', function(){
                showSection('chat');
            });
        }
    }
});
</script>
</body>
</html>