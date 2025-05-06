<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php'); // Asegúrate de que $conexion se crea aquí

// Verificar si el usuario está logueado (esto debe ejecutarse siempre)
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    header('Location: /views/general/login.php');
    exit();
}

// --- Lógica para manejar solicitudes AJAX (Chat) ---
// Detectamos si es una solicitud AJAX para el chat basándonos en un parámetro 'action'
if (isset($_REQUEST['action'])) {
    // Asegúrate de que la conexión esté disponible si aún no se ha cerrado
    // (La corrección del error anterior, eliminando `$conexion = null;`, es crucial aquí)

    switch ($_REQUEST['action']) {
        case 'get_messages':
            // --- Lógica para obtener mensajes ---
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['pasantia_id'])) {
                $pasantia_id = filter_input(INPUT_GET, 'pasantia_id', FILTER_VALIDATE_INT);

                if ($pasantia_id) {
                     // Re-utilizamos la función existente (asegúrate que la conexión $conexion esté disponible)
                    $mensajes = obtenerMensajesChat($conexion, $pasantia_id);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'mensajes' => $mensajes]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de pasantía inválido']);
                }
            } else {
                 header('Content-Type: application/json');
                 echo json_encode(['success' => false, 'message' => 'Solicitud inválida para obtener mensajes']);
            }
            exit(); // Detiene la ejecución después de enviar la respuesta JSON

        case 'send_message':
            // --- Lógica para enviar mensajes ---
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pasantia_id'], $_POST['emisor_id'], $_POST['receptor_id'])) {
                $pasantia_id = filter_input(INPUT_POST, 'pasantia_id', FILTER_VALIDATE_INT);
                $emisor_id = filter_input(INPUT_POST, 'emisor_id', FILTER_VALIDATE_INT);
                $receptor_id = filter_input(INPUT_POST, 'receptor_id', FILTER_VALIDATE_INT);
                $mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_UNSAFE_RAW); // Sanitizar al mostrar, no al guardar
                $archivo = $_FILES['archivo'] ?? null; // Archivo adjunto

                $errores_chat = [];
                $archivo_nombre = null;
                $upload_dir = __DIR__ . "/../../uploads/pasantias/chat/"; // Directorio de subida

                // Validaciones básicas
                if (!$pasantia_id || !$emisor_id || !$receptor_id) {
                    $errores_chat[] = "Faltan IDs de la conversación.";
                }

                if (empty(trim($mensaje)) && (!$archivo || $archivo['size'] == 0)) {
                     $errores_chat[] = "No puedes enviar un mensaje vacío sin archivo adjunto.";
                }

                // Manejar subida de archivo si existe
                if ($archivo && $archivo['size'] > 0) {
                    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'pptx']; // Tipos permitidos
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
                             mkdir($upload_dir, 0775, true); // Crear directorio si no existe con permisos
                        }
                         // Nombre único para el archivo
                         $sanitized_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['filename']);
                         $archivo_nombre = $pasantia_id . "_" . time() . "_" . $sanitized_filename . "." . $file_extension;
                         $target_file = $upload_dir . $archivo_nombre;

                         if (!move_uploaded_file($archivo['tmp_name'], $target_file)) {
                             $errores_chat[] = "Error técnico al mover el archivo subido.";
                             $archivo_nombre = null; // Asegurarse de que sea null si falla el movimiento
                              error_log("Error moviendo archivo de chat: " . $archivo['tmp_name'] . " a " . $target_file);
                         }
                    }
                }

                // Si no hay errores, insertar en la base de datos
                if (empty($errores_chat)) {
                    try {
                        $query = "INSERT INTO mensajes_chat (pasantia_id, emisor_id, receptor_id, mensaje, archivo) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conexion->prepare($query);
                        $stmt->execute([$pasantia_id, $emisor_id, $receptor_id, trim($mensaje), $archivo_nombre]);

                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);

                    } catch (PDOException $e) {
                        error_log("Error DB al enviar mensaje: " . $e->getMessage());
                         // Limpiar archivo subido si la inserción falla
                        if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                             unlink($upload_dir . $archivo_nombre);
                        }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error en la base de datos al enviar mensaje']);
                    } catch (Exception $e) {
                        error_log("Error general al enviar mensaje: " . $e->getMessage());
                         // Limpiar archivo subido si falla
                         if ($archivo_nombre && file_exists($upload_dir . $archivo_nombre)) {
                              unlink($upload_dir . $archivo_nombre);
                         }
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error del servidor al enviar mensaje']);
                    }

                } else {
                     // Si hubo errores de validación, limpiar archivo si se llegó a mover
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
            exit(); // Detiene la ejecución después de enviar la respuesta JSON
    }
}

// --- Fin de la lógica para manejar solicitudes AJAX ---

// El resto del código PHP para la carga inicial de la página continúa aquí
// (Obtener datos del estudiante, pasantía, entregas, calcular progreso, nota final, notificaciones)

$estudiante_id = $_SESSION['usuario']['id'];
$nombre_estudiante = $_SESSION['usuario']['nombre'];
$carrera = $_SESSION['usuario']['ciclo'] ?? 'No especificada';

// Obtener datos de la pasantía del estudiante
$query = "SELECT p.*, u.nombre AS tutor_nombre
          FROM pasantias p
          LEFT JOIN usuarios u ON p.tutor_id = u.id
          WHERE p.estudiante_id = ?";
$stmt = $conexion->prepare($query);
$stmt->execute([$estudiante_id]);
$pasantia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pasantia) {
    // Aquí podrías redirigir o mostrar un mensaje y salir si no hay pasantía
    // echo "<div class='error-container'>No tienes una pasantía registrada. Por favor, comunícate con administración.</div>";
    // exit(); // O mostrar el mensaje y renderizar una página mínima
    // Para este ejemplo, asumimos que siempre hay una pasantía para simplificar.
    // Si el usuario no tiene pasantía, el resto del código podría fallar.
    // Una mejor práctica es manejar esto adecuadamente, tal vez mostrando solo un mensaje.
    // Para continuar con el chat/otras secciones, necesitamos una pasantía válida.
    // Considera mostrar un mensaje y redirigir si $pasantia es nulo.
    // Por ahora, seguimos asumiendo que $pasantia tiene datos válidos.
     echo "<div class='error-container'>No tienes una pasantía registrada. Por favor, comunícate con administración.</div>";
     exit(); // Es mejor salir aquí si no hay pasantía para evitar errores posteriores
}

$pasantia_id = $pasantia['id'];

// Obtener entregas de la pasantía
$query = "SELECT * FROM entregas_pasantia WHERE pasantia_id = ? ORDER BY numero_avance";
$stmt = $conexion->prepare($query);
$stmt->execute([$pasantia_id]);
$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular progreso
$avances_aprobados = 0;
$total_avances = 4; // Definido el total de avances
$avances_entregados = [];

foreach ($entregas as $entrega) {
    $avances_entregados[$entrega['numero_avance']] = $entrega;
    if ($entrega['estado'] == 'aprobado') {
        $avances_aprobados++;
    }
}

$porcentaje_progreso = ($avances_aprobados / $total_avances) * 100;

// Calcular nota final
$nota_final = 0;
$puede_ver_nota_final = false;

if (count($entregas) == $total_avances) { // Se necesitan las 4 entregas
    $suma_notas = 0;
    $todas_calificadas = true;

    foreach ($entregas as $entrega) {
        // Verifica si la nota no es NULL (significa que ha sido calificada)
        if ($entrega['nota'] === null) {
            $todas_calificadas = false;
            break;
        }
        $suma_notas += $entrega['nota'];
    }

    if ($todas_calificadas) {
        $nota_final = $suma_notas / $total_avances;
        $puede_ver_nota_final = true;
    }
}


// Manejar subida de avance (Deja esta lógica después de la carga inicial pero antes del HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_avance'])) {
    // ... (Tu lógica existente para manejar la subida de avances) ...
    // Asegúrate de que $conexion esté disponible aquí también.
    // Esta parte ya debe estar funcionando si el error anterior se resolvió.

     $numero_avance = filter_input(INPUT_POST, 'numero_avance', FILTER_VALIDATE_INT);
     $comentario_estudiante = filter_input(INPUT_POST, 'comentario_estudiante', FILTER_UNSAFE_RAW);
     $archivo = $_FILES['archivo_avance'] ?? null;

     $errores = []; // Usamos $errores para los errores de subida de avance

     // Validar avances (misma lógica que tenías)
     $max_avance_actual = 0;
     foreach ($entregas as $entrega) {
         if ($entrega['numero_avance'] > $max_avance_actual) {
             $max_avance_actual = $entrega['numero_avance'];
         }
     }

     // Validar que el avance a subir sea el siguiente o uno que requiere corrección
     $avance_requiere_correccion = null;
     foreach ($entregas as $entrega) {
         if ($entrega['numero_avance'] == $numero_avance && $entrega['estado'] == 'corregir') {
             $avance_requiere_correccion = $numero_avance;
             break;
         }
     }

     if ($numero_avance > $max_avance_actual + 1 && !$avance_requiere_correccion) {
         $errores[] = "No puedes subir el avance $numero_avance sin completar los anteriores.";
     }

     // Validar archivo (misma lógica que tenías)
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
         $directorio_entregas = __DIR__ . "/../../uploads/pasantias/entregas/"; // Usa __DIR__ para ruta absoluta
         if (!file_exists($directorio_entregas)) {
             mkdir($directorio_entregas, 0755, true);
         }

         // Sanitizar nombre de archivo
         $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $pasantia['titulo']);
         $nombre_archivo = $pasantia_id . "_avance_" . $numero_avance . "_" . $nombre_base . "_" . time() . ".pdf";
         $ruta_archivo = $directorio_entregas . $nombre_archivo;

         if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
             $existe_avance = false;
             $id_entrega = null;
             foreach ($entregas as $entrega) {
                 if ($entrega['numero_avance'] == $numero_avance) {
                     $existe_avance = true;
                     $id_entrega = $entrega['id'];
                     break;
                 }
             }

             try {
                 $conexion->beginTransaction();

                 if ($existe_avance) {
                     // Si ya existe y requiere corrección, actualizar
                     if ($avance_requiere_correccion) {
                         $query = "UPDATE entregas_pasantia SET
                                     archivo_entregado = ?,
                                     comentario_estudiante = ?,
                                     estado = 'pendiente', -- Cambiar a pendiente al resubir
                                     fecha_entrega = NOW(),
                                     comentario_tutor = NULL, -- Limpiar comentario y nota del tutor al resubir
                                     nota = NULL
                                     WHERE id = ?";
                         $stmt = $conexion->prepare($query);
                         $stmt->execute([$nombre_archivo, $comentario_estudiante, $id_entrega]);
                     } else {
                          // Esto no debería ocurrir si la validación de arriba funciona, pero como fallback
                          $errores[] = "El avance $numero_avance ya ha sido entregado y no requiere correcciones.";
                          $conexion->rollBack(); // Rollback en caso de error lógico
                          // Limpiar el archivo subido que no se usará
                          if (file_exists($ruta_archivo)) {
                             unlink($ruta_archivo);
                          }
                     }
                 } else {
                     // Insertar nueva entrega
                     $query = "INSERT INTO entregas_pasantia
                                 (pasantia_id, numero_avance, archivo_entregado, comentario_estudiante, estado)
                                 VALUES (?, ?, ?, ?, 'pendiente')";
                     $stmt = $conexion->prepare($query);
                     $stmt->execute([$pasantia_id, $numero_avance, $nombre_archivo, $comentario_estudiante]);
                 }

                 if (empty($errores)) { // Solo commit si no hubo errores lógicos en la transacción
                      $conexion->commit();
                      // Redirigir para evitar reenvío del formulario y actualizar datos mostrados
                      header("Location: " . $_SERVER['PHP_SELF']);
                      exit();
                 }


             } catch (PDOException $e) {
                 $conexion->rollBack();
                 error_log("Error en la transacción de entrega: " . $e->getMessage());
                 $errores[] = "Error al procesar la entrega. Inténtalo de nuevo.";
                 // Limpiar el archivo subido si falla la DB
                 if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                 }
             }
         } else {
             error_log("Error moviendo archivo de avance: " . $archivo['tmp_name'] . " a " . $ruta_archivo);
             $errores[] = "Error técnico al subir el archivo de avance. Contacta al administrador.";
         }
     }
     // Note: If there are validation errors, the script continues to render the HTML,
     // displaying the $errores array in the alert box. This is the current behavior.
}


// Función para formatear fechas (ya existe)
function formatearFecha($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}

// Obtener mensajes del chat para la CARGA INICIAL de la página
// Nota: Esta función SOLO se llama en la carga inicial para mostrar el historial.
// Las actualizaciones en tiempo real usan la lógica AJAX definida arriba.
// Asegúrate que $conexion esté disponible aquí.
function obtenerMensajesChat($conexion, $pasantia_id) {
     // Validar $pasantia_id si no se hizo antes
     if (!$pasantia_id) {
         error_log("obtenerMensajesChat llamada con ID de pasantia nulo/inválido");
         return []; // Retornar vacío para evitar errores
     }
    try {
        $query = "SELECT m.*, u.nombre AS emisor_nombre
                  FROM mensajes_chat m
                  INNER JOIN usuarios u ON m.emisor_id = u.id
                  WHERE m.pasantia_id = ?
                  ORDER BY m.fecha_envio ASC";
        $stmt = $conexion->prepare($query);
        $stmt->execute([$pasantia_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error DB en obtenerMensajesChat: " . $e->getMessage());
        return []; // Retornar array vacío en caso de error
    }
}


// Verificar notificaciones (esto es para las notificaciones de entregas que requieren corrección)
$notificaciones = 0; // Inicializa a 0 por si no hay pasantía o entregas
if (isset($pasantia_id)) { // Solo consulta si tenemos un ID de pasantía válido
    $query = "SELECT COUNT(*) as total FROM entregas_pasantia
              WHERE pasantia_id = ? AND comentario_tutor IS NOT NULL
              AND estado = 'corregir'";
    $stmt = $conexion->prepare($query);
    $stmt->execute([$pasantia_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $notificaciones = $resultado['total'];
}


// Clases para el estado de la pasantía (ya existe)
$clases_estado = [
    'pendiente' => 'estado-pendiente',
    'aprobada' => 'estado-aprobado',
    'rechazada' => 'estado-rechazado',
    'en_proceso' => 'estado-proceso',
    'finalizada' => 'estado-finalizado'
];

// Asegúrate de que $pasantia existe antes de acceder a 'estado'
$clase_estado = isset($pasantia['estado'], $clases_estado[$pasantia['estado']]) ?
               $clases_estado[$pasantia['estado']] :
               'estado-pendiente';


// $conexion = null; // COMENTAR O ELIMINAR ESTA LÍNEA - La conexión se cierra al final del script

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Pasantías - Estudiante</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/estudiante_pasantia.css">
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-university logo"></i>
                <h3>Portal Pasantías</h3>
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
                 <?php if ($puede_ver_nota_final): // Mostrar solo si la nota final está disponible ?>
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
                    <h2>Sistema de Gestión de Pasantías</h2>
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
                    <h3><i class="fas fa-file-alt"></i> Resumen de la Pasantía</h3>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Detalles del Proyecto</h4>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Título del Proyecto:</span>
                                <span class="info-value"><?php echo htmlspecialchars($pasantia['titulo']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Empresa:</span>
                                <span class="info-value"><?php echo htmlspecialchars($pasantia['empresa']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Dirección:</span>
                                <span class="info-value"><?php echo htmlspecialchars($pasantia['direccion_empresa'] ?: 'No especificada'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Supervisor de Empresa:</span>
                                <span class="info-value"><?php echo htmlspecialchars($pasantia['supervisor_empresa'] ?: 'No asignado'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tutor Asignado:</span>
                                <span class="info-value"><?php echo htmlspecialchars($pasantia['tutor_nombre'] ?: 'No asignado'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha de Inicio:</span>
                                <span class="info-value"><?php echo $pasantia['fecha_inicio'] ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No definida'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Fecha de Finalización:</span>
                                <span class="info-value"><?php echo $pasantia['fecha_fin'] ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No definida'; ?></span>
                            </div>
                             <div class="info-item">
                                <span class="info-label">Estado:</span>
                                <span class="info-value estado <?php echo $clase_estado; ?>">
                                     <?php echo ucfirst(str_replace('_', ' ', $pasantia['estado'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($pasantia['archivo_documento']): ?>
                        <div class="document-section">
                            <a href="../../uploads/pasantias/documentos/<?php echo htmlspecialchars($pasantia['archivo_documento']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Ver Documento Inicial
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="progreso" class="content-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-line"></i> Progreso de la Pasantía</h3>
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

                                        // Ordenar las entregas por número de avance para encontrar el más alto
                                        $entregas_ordenadas = $entregas;
                                        usort($entregas_ordenadas, function($a, $b) {
                                            return $a['numero_avance'] <=> $b['numero_avance'];
                                        });

                                        foreach ($entregas_ordenadas as $entrega) {
                                            if ($entrega['estado'] == 'corregir') {
                                                $avance_para_corregir = $entrega['numero_avance'];
                                                break; // Si hay uno para corregir, solo permitimos subir ese
                                            }
                                            // Si no hay para corregir, calculamos el próximo basado en el avance más alto entregado
                                            if ($entrega['numero_avance'] >= $proximo_avance_disponible) {
                                                $proximo_avance_disponible = $entrega['numero_avance'] + 1;
                                            }
                                        }

                                        // Limitar a máximo 4 avances
                                        if ($proximo_avance_disponible > $total_avances) {
                                            $proximo_avance_disponible = $total_avances + 1; // Indica que todos han sido entregados
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
                                 // Deshabilitar el botón si ya se entregaron los 4 avances y no hay correcciones pendientes
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
                        <?php if (empty($entregas)): ?>
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
                                    <?php foreach ($entregas as $entrega):
                                        $estado_class = '';
                                        switch ($entrega['estado']) {
                                            case 'pendiente': $estado_class = 'estado-pendiente'; break;
                                            case 'corregir': $estado_class = 'estado-corregir'; break;
                                            case 'aprobado': $estado_class = 'estado-aprobado'; break;
                                            case 'revisado': $estado_class = 'estado-revisado'; break;
                                            default: $estado_class = 'estado-pendiente'; break; // Fallback
                                        }
                                    ?>
                                    <tr>
                                        <td>Avance <?php echo htmlspecialchars($entrega['numero_avance']); ?></td>
                                        <td>
                                            <?php if ($entrega['archivo_entregado']): ?>
                                            <a href="../../uploads/pasantias/entregas/<?php echo htmlspecialchars($entrega['archivo_entregado']); ?>" target="_blank" class="btn-download">
                                                <i class="fas fa-file-pdf"></i> Ver PDF
                                            </a>
                                            <?php else: ?>
                                                 —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($entrega['comentario_tutor']): ?>
                                                <div class="comentario-tutor">
                                                    <?php echo nl2br(htmlspecialchars($entrega['comentario_tutor'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin comentarios</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $entrega['nota'] !== null ? number_format($entrega['nota'], 1) : '—'; ?></td>
                                        <td><span class="estado <?php echo $estado_class; ?>"><?php echo htmlspecialchars(ucfirst($entrega['estado'])); ?></span></td>
                                        <td><?php echo formatearFecha($entrega['fecha_entrega']); ?></td>
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
                            <span><?php echo htmlspecialchars($pasantia['tutor_nombre'] ?: 'Tutor no asignado'); ?></span>
                        </div>
                        <div class="chat-status">
                            <?php if ($pasantia['tutor_id']): ?>
                                <span class="status-indicator online"></span>
                                <span class="status-text">Disponible</span>
                            <?php else: ?>
                                <span class="status-indicator offline"></span>
                                <span class="status-text">No disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="chat-body" id="chat-messages">
                         <?php
                         // Obtener mensajes para la visualización inicial
                         // Ya se hizo al inicio del script para pasarla a JS,
                         // pero también la mostramos aquí en el render inicial por si JS falla
                         // No, mejor que JS la cargue para mantener la lógica de un solo origen para el chat
                         // Mantenemos el div vacío y que JS lo llene al cargar la página.
                         // Agregamos un mensaje por defecto si no hay tutor.
                         ?>
                         <?php if (!$pasantia['tutor_id']): ?>
                             <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <p>Tu tutor aún no ha sido asignado. El chat estará disponible una vez que se te asigne un tutor.</p>
                            </div>
                         <?php endif; ?>

                    </div>

                    <div class="chat-footer">
                        <form id="chat-form" class="chat-input-container">
                            <input type="hidden" id="pasantia-id" value="<?php echo htmlspecialchars($pasantia_id); ?>">
                            <input type="hidden" id="emisor-id" value="<?php echo htmlspecialchars($estudiante_id); ?>">
                            <input type="hidden" id="receptor-id" value="<?php echo htmlspecialchars($pasantia['tutor_id'] ?: ''); ?>"> <div class="chat-attachment">
                                <label for="chat-file" class="chat-file-label">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" id="chat-file" name="chat-file" style="display: none;">
                            </div>

                            <div class="chat-text">
                                <textarea id="chat-message" placeholder="Escribe un mensaje..." <?php echo $pasantia['tutor_id'] ? '' : 'disabled'; ?>></textarea>
                            </div>

                            <button type="submit" class="chat-send" <?php echo $pasantia['tutor_id'] ? '' : 'disabled'; ?>>
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
                                            <?php foreach ($entregas as $entrega):
                                                 if ($entrega['nota'] !== null): // Mostrar solo si la nota existe
                                            ?>
                                            <tr>
                                                <td>Avance <?php echo htmlspecialchars($entrega['numero_avance']); ?></td>
                                                <td><?php echo number_format($entrega['nota'], 1); ?></td>
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

                        <?php if ($pasantia['documento_adicional']): ?>
                        <div class="acta-container">
                            <a href="../../uploads/pasantias/actas/<?php echo htmlspecialchars($pasantia['documento_adicional']); ?>" class="btn btn-download" target="_blank">
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
 document.addEventListener('DOMContentLoaded', function() { // Corregido
    console.log('JavaScript cargado correctamente');

    // --- Navegación entre secciones ---
    const navItems = document.querySelectorAll('.nav-item');
    const contentSections = document.querySelectorAll('.content-section');
    const chatBadge = document.getElementById('chat-badge'); // Mover aquí para acceso global

    // Función para mostrar una sección (Redefinida para el chat)
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
        originalShowSection(sectionId); // Llama a la lógica original

        // Lógica específica al mostrar la sección de chat
        if (sectionId === 'chat') {
            const pasantiaId = document.getElementById('pasantia-id')?.value;
            if (pasantiaId) {
                 // Al mostrar el chat, marcamos los mensajes como leídos (aproximadamente)
                 localStorage.setItem('lastChatView_' + pasantiaId, Date.now());
                 if (chatBadge) chatBadge.style.display = 'none'; // Oculta el badge inmediatamente
                 console.log('Sección de chat mostrada, marcando mensajes como leídos.');
                 // Forzar una carga de mensajes para asegurar que se ve lo último y el badge se actualiza correctamente
                 cargarMensajes(); // Llama a cargar mensajes para refrescar y asegurar que el badge se oculte
            }
        }
    };


    // Manejar clic en los items del menú (usa la función showSection redefinida)
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
    const avanceFileInput = document.getElementById('archivo_avance'); // Renombrar para claridad
    const avanceFileNameDisplay = document.getElementById('file-name'); // Renombrar
    const avanceBrowseButton = document.querySelector('#form-avance .btn-browse'); // Ser más específico con el selector

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
    const chatFileLabel = document.querySelector('.chat-file-label'); // Etiqueta para el ícono del clip
    const pasantiaId = document.getElementById('pasantia-id')?.value;
    const emisorId = document.getElementById('emisor-id')?.value;
    const receptorId = document.getElementById('receptor-id')?.value; // Puede ser vacío si no hay tutor

    // Deshabilitar chat si no hay tutor asignado
    if (!receptorId) {
         console.warn('Chat deshabilitado: Tutor no asignado.');
         if (chatInput) chatInput.disabled = true;
         if (chatForm) chatForm.querySelector('.chat-send').disabled = true;
         if (chatFile) chatFile.disabled = true;
         if (chatFileLabel) chatFileLabel.style.pointerEvents = 'none'; // Deshabilitar clic en el ícono del clip
         // No intentar inicializar listeners o polling si el chat está deshabilitado
    }


    // Función para formatear fechas (ya existe)
    function formatDate(dateString) {
        const date = new Date(dateString);
        // Asegúrate de que la fecha sea válida
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

    // Función para actualizar el chat (ya existe)
    function actualizarChat(mensajes) {
        const chatMessages = document.getElementById('chat-messages'); // Obtener de nuevo por si la sección no estaba activa inicialmente
        if (!chatMessages) {
             console.error('chat-messages element not found for update');
             return;
        }
        chatMessages.innerHTML = '';

        if (!mensajes || mensajes.length === 0) {
            // Si no hay tutor, mostrar mensaje específico de tutor no asignado
            if (!receptorId) { // Usamos el receptorId obtenido del input hidden
                 chatMessages.innerHTML = `
                     <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Tu tutor aún no ha sido asignado. El chat estará disponible una vez que se te asigne un tutor.</p>
                     </div>
                 `;
            } else { // Si hay tutor pero no hay mensajes
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
                                <a href="../../uploads/pasantias/chat/${encodeURIComponent(mensaje.archivo)}" target="_blank">
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
             // Usar insertAdjacentHTML es mejor que innerHTML =+ para rendimiento
            chatMessages.insertAdjacentHTML('beforeend', mensajeHtml);
        });

        // Hacer scroll al último mensaje
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

     // Funciones de ayuda para JS para replicar htmlspecialchars y nl2br
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


    // Función para cargar mensajes (AJAX GET a este mismo archivo)
    function cargarMensajes() {
        const pasantiaId = document.getElementById('pasantia-id')?.value; // Obtener el ID más reciente
        if (!pasantiaId || !receptorId) { // No intentar cargar si falta ID de pasantía o tutor
            // console.warn("No se cargan mensajes: Falta ID de pasantia o tutor");
            // Mostrar el estado vacío adecuado si el elemento chat-messages existe
            const chatMessages = document.getElementById('chat-messages');
             if (chatMessages && chatMessages.innerHTML === '') { // Solo si está vacío
                 actualizarChat([]); // Esto mostrará el mensaje de "No hay mensajes" o "Tutor no asignado"
             }
             return;
        }


        // --- FETCH ACTUALIZADO ---
        fetch(`Pasantias.php?action=get_messages&pasantia_id=${encodeURIComponent(pasantiaId)}`)
            .then(response => {
                if (!response.ok) {
                     // Intentar leer la respuesta como JSON incluso si no es OK (ej. si PHP devolvió error con JSON)
                    return response.json().then(err => {
                         console.error('Error en la respuesta del servidor al obtener mensajes:', err);
                         throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                         // Si no es JSON, lanzar error genérico
                         console.error('Error en la respuesta del servidor (no-JSON) al obtener mensajes:', response.status, response.statusText);
                         throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const currentMessagesCount = document.querySelectorAll('#chat-messages .chat-message').length; // Contar mensajes actuales en el DOM
                     // Solo actualizar si el número de mensajes en la respuesta es mayor que los que ya tenemos
                    if (data.mensajes && data.mensajes.length > currentMessagesCount) {
                         actualizarChat(data.mensajes);

                         // Actualizar badge de mensajes no leídos SOLO si la sección de chat NO está activa
                         const chatSection = document.getElementById('chat');
                         if (chatSection && !chatSection.classList.contains('active') && chatBadge) {
                              let unreadCount = 0;
                              const lastViewTime = localStorage.getItem('lastChatView_' + pasantiaId) || 0;

                              data.mensajes.forEach(mensaje => {
                                  // Contar mensajes del RECEPTOR (tutor) que son más nuevos que la última vez que vimos el chat
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
                             // Si la sección de chat está activa, asegúrate de que el badge esté oculto
                             chatBadge.style.display = 'none';
                         }

                    } else if (!data.mensajes || data.mensajes.length === 0) {
                        // Si no hay mensajes en la respuesta, asegúrate de que el div esté vacío o muestre el estado vacío
                         if (currentMessagesCount > 0) { // Si actualmente hay mensajes pero la API devuelve 0 (error?), limpia
                             actualizarChat([]);
                         } else {
                             // Asegurarse de que el estado vacío se muestre correctamente si no hay mensajes
                             const chatMessages = document.getElementById('chat-messages');
                             if (chatMessages && chatMessages.innerHTML === '') { // Solo si está vacío
                                 actualizarChat([]); // Esto mostrará el mensaje de "No hay mensajes" o "Tutor no asignado"
                             }
                         }
                         if(chatBadge) chatBadge.style.display = 'none'; // Ocultar badge si no hay mensajes
                    }
                } else {
                    console.error('API Error al obtener mensajes:', data.message);
                    // Opcional: mostrar un mensaje de error en la UI del chat
                     const chatMessages = document.getElementById('chat-messages');
                     if (chatMessages) {
                          chatMessages.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-circle"></i><p>Error al cargar mensajes: ${htmlspecialchars(data.message)}</p></div>`;
                     }
                }
            })
            .catch(error => {
                 console.error('Error en fetch cargarMensajes:', error);
                 // Opcional: mostrar un mensaje de error de conexión en la UI del chat
                 const chatMessages = document.getElementById('chat-messages');
                  if (chatMessages) {
                       chatMessages.innerHTML = `<div class="empty-state text-muted"><i class="fas fa-exclamation-triangle"></i><p>Error de conexión al cargar mensajes.</p></div>`;
                  }
            });
    }

    // Manejar envío de mensajes (AJAX POST a este mismo archivo)
    if (chatForm && chatInput && chatFile && pasantiaId && emisorId && receptorId) { // Asegurarse de que los elementos y IDs existan y haya tutor
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mensaje = chatInput.value.trim();
            const archivo = chatFile.files[0];

            if (!mensaje && !archivo) {
                console.log('No se envió mensaje: sin contenido');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_message'); // Indicar la acción al script PHP
            formData.append('pasantia_id', pasantiaId);
            formData.append('emisor_id', emisorId);
            formData.append('receptor_id', receptorId);
            formData.append('mensaje', mensaje);
            if (archivo) {
                formData.append('archivo', archivo);
            }

            // --- FETCH ACTUALIZADO ---
            fetch('Pasantias.php', { // Enviar al mismo archivo
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Intentar leer la respuesta como JSON incluso si no es OK
                    return response.json().then(err => {
                        console.error('Error en la respuesta del servidor al enviar mensaje:', err);
                        throw new Error('Error en la respuesta del servidor: ' + (err.message || response.statusText));
                    }).catch(() => {
                         // Si no es JSON, lanzar error genérico
                         console.error('Error en la respuesta del servidor (no-JSON) al enviar mensaje:', response.status, response.statusText);
                         throw new Error('Error en la respuesta del servidor: ' + response.statusText);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    chatInput.value = ''; // Limpiar input de texto
                    chatFile.value = ''; // Limpiar input de archivo
                     // Limpiar el nombre del archivo mostrado si existe
                     const chatFileNameDisplay = document.querySelector('.chat-attachment span'); // Si agregaste un span para mostrar el nombre
                     if(chatFileNameDisplay) chatFileNameDisplay.textContent = ''; // Ocultar o limpiar

                    cargarMensajes(); // Volver a cargar los mensajes para ver el nuevo
                    console.log('Mensaje enviado correctamente');
                } else {
                    console.error('API Error al enviar mensaje:', data.message);
                    alert('Error al enviar el mensaje: ' + data.message); // Mostrar error al usuario
                }
            })
            .catch(error => {
                 console.error('Error en fetch enviar_mensaje:', error);
                 alert('Error de conexión al enviar el mensaje.'); // Mostrar error genérico al usuario
            });
        });

         // Opcional: Mostrar el nombre del archivo seleccionado junto al ícono del clip
         if(chatFile && chatFileLabel){
             // Puedes añadir un span al lado del icono del clip en el HTML
             // <div class="chat-attachment"> <label for="chat-file" class="chat-file-label"><i class="fas fa-paperclip"></i></label> <span id="chat-file-name"></span> <input type="file" id="chat-file" ...> </div>
             // Y luego actualizar ese span
             chatFile.addEventListener('change', function() {
                 const chatFileNameDisplay = document.querySelector('.chat-attachment span');
                 if(chatFileNameDisplay){
                      chatFileNameDisplay.textContent = this.files.length > 0 ? this.files[0].name : '';
                 }
             });
         }


    } else {
        // Si el chat está deshabilitado (por falta de tutor o IDs), mostrar en consola
        console.warn('Formulario de chat no inicializado o deshabilitado.');
    }


    // Notificaciones de entregas (el click en la campana te lleva al historial) - Esto ya estaba bien.
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationBadge = document.querySelector('.notification-badge');

    if (notificationIcon && notificationBadge) {
        notificationIcon.addEventListener('click', function() {
            const historialNavItem = document.querySelector('[data-section="historial"]');
            if (historialNavItem) {
                historialNavItem.click(); // Simula el clic en el ítem del menú
                console.log('Clic en ícono de notificaciones, mostrando historial');
            } else {
                console.error('No se encontró el nav-item para historial');
            }
        });
    } else {
        console.error('No se encontraron los elementos de notificación');
    }


    // Cargar mensajes iniciales y configurar recarga periódica
    // Solo iniciar el polling si hay tutor asignado (receptorId no vacío)
    const receptorIdCheck = document.getElementById('receptor-id')?.value;
    if (receptorIdCheck) {
        cargarMensajes(); // Carga inicial de mensajes
        setInterval(cargarMensajes, 5000); // Poll cada 5 segundos
        console.log("Polling de chat iniciado.");
    } else {
        console.warn("Polling de chat no iniciado: Tutor no asignado.");
         // Si no hay tutor, asegurar que la sección de chat muestre el mensaje adecuado al hacer clic
         const chatNavItem = document.querySelector('[data-section="chat"]');
         if(chatNavItem){
             chatNavItem.addEventListener('click', function(){
                  // showSection('chat') ya maneja el caso de tutor no asignado mostrando el empty-state
                  // Simplemente asegúrate de que showSection se llama correctamente
                  showSection('chat');
             });
         }
    }


}); // Fin de DOMContentLoaded

</script>
</body>
</html>