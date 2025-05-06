<?php
session_start(); // Iniciar la sesión al principio, una sola vez
require_once __DIR__ . '/../../config/conexion.php'; // Incluir la conexión

// Configuración de reporte de errores para APIs o Producción
ini_set('display_errors', 0); // Desactiva la visualización de errores en la salida
ini_set('display_startup_errors', 0); // Desactiva la visualización de errores al inicio
ini_set('log_errors', 1); // Activa el registro de errores en un archivo
ini_set('error_log', __DIR__ . '/../../php_error.log'); // Especifica el archivo de log (asegúrate de que PHP tenga permisos de escritura)
error_reporting(E_ALL); // Sigue reportando todos los tipos de errores para que se logren

// Función para manejar valores nulos y sanitizar para HTML (o salida JSON)
function htmlSafe($str) {
    return $str !== null ? htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8') : '';
}

// Función para validar formato de fecha Y-M-D
function isValidDate($dateString) {
    // Verifica que la cadena no esté vacía y tenga el formato YYYY-MM-DD
    if (empty($dateString)) {
        return true; // Permite fechas vacías/null
    }
    $date = DateTime::createFromFormat('Y-m-d', $dateString);
    // Valida que el objeto DateTime se creó correctamente y que el formato original coincide
    return $date && $date->format('Y-m-d') === $dateString;
}

// --- Manejar solicitudes API ---
// Estas APIs se llaman desde el JavaScript para obtener detalles, editar, etc.
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    // API para obtener detalles completos de la pasantía (para modal "Ver Detalles" y "Editar")
    if ($_GET['api'] === 'details' && isset($_GET['id'])) {
        try {
            $pasantiaId = filter_var($_GET['id'], FILTER_VALIDATE_INT); // Sanitizar ID
             if ($pasantiaId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de pasantía no válido']);
                  exit;
             }

            // Obtener detalles de la pasantía (quitando la selección de documento_adicional)
            $stmt = $conexion->prepare("
                SELECT p.id, p.estudiante_id, p.titulo, p.descripcion, p.empresa, p.direccion_empresa,
                       p.contacto_empresa, p.supervisor_empresa, p.telefono_supervisor,
                       p.fecha_inicio, p.fecha_fin, p.estado, p.tutor_id, p.archivo_documento, -- Eliminado documento_adicional
                       p.fecha_creacion,
                       e.nombre as estudiante_nombre,
                       e.codigo_estudiante,
                       e.email as estudiante_email,
                       e.documento as estudiante_documento,
                       e.telefono as estudiante_telefono,
                       t.nombre as tutor_nombre,
                       t.email as tutor_email
                FROM pasantias p
                LEFT JOIN usuarios e ON p.estudiante_id = e.id
                LEFT JOIN usuarios t ON p.tutor_id = t.id
                WHERE p.id = ?
            ");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (details) Prepare Failed: " . implode(" ", $conexion->errorInfo()));
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta.']);
                 exit;
             }

            $stmt->execute([$pasantiaId]);
            $pasantia = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pasantia) {
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }

            // Formatear fechas y sanitizar campos para la visualización/edición
             $response = [
                 'id' => (int)$pasantia['id'], // Asegurarse que es INT
                 'estudiante_id' => $pasantia['estudiante_id'] !== null ? (int)$pasantia['estudiante_id'] : null, // Asegurarse que es INT o null
                 'estudiante_nombre' => htmlSafe($pasantia['estudiante_nombre'] ?? 'No asignado'),
                 'codigo_estudiante' => htmlSafe($pasantia['codigo_estudiante'] ?? 'N/A'),
                 'estudiante_email' => htmlSafe($pasantia['estudiante_email'] ?? 'N/A'),
                 'estudiante_documento' => htmlSafe($pasantia['estudiante_documento'] ?? 'N/A'),
                 'estudiante_telefono' => htmlSafe($pasantia['telefono'] ?? 'No registrado'), // Corregido: usar $pasantia['telefono']
                 'titulo' => htmlSafe($pasantia['titulo'] ?? 'Sin título'),
                 'descripcion' => htmlSafe($pasantia['descripcion'] ?? ''),
                 'empresa' => htmlSafe($pasantia['empresa'] ?? 'No especificada'),
                 'direccion_empresa' => htmlSafe($pasantia['direccion_empresa'] ?? ''),
                 'contacto_empresa' => htmlSafe($pasantia['contacto_empresa'] ?? ''),
                 'supervisor_empresa' => htmlSafe($pasantia['supervisor_empresa'] ?? ''),
                 'telefono_supervisor' => htmlSafe($pasantia['telefono_supervisor'] ?? ''),
                 'fecha_inicio' => htmlSafe($pasantia['fecha_inicio']), // Y-M-D format for input date
                 'fecha_fin' => htmlSafe($pasantia['fecha_fin']), // Y-M-D format for input date
                 'fecha_inicio_formateada' => !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida',
                 'fecha_fin_formateada' => !empty($pasantia['fecha_fin']) ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No establecida',
                 'estado' => htmlSafe($pasantia['estado'] ?? 'pendiente'),
                 'estado_formateado' => ucfirst(str_replace('_', ' ', htmlSafe($pasantia['estado'] ?? 'pendiente'))),
                 'tutor_id' => $pasantia['tutor_id'] !== null ? (int)$pasantia['tutor_id'] : null,
                 'tutor_nombre' => htmlSafe($pasantia['tutor_nombre'] ?? 'No asignado'),
                 'tutor_email' => htmlSafe($pasantia['tutor_email'] ?? 'N/A'),
                 'archivo_documento' => htmlSafe($pasantia['archivo_documento']),
                 // Eliminado documento_adicional de la respuesta
                 'fecha_creacion' => htmlSafe($pasantia['fecha_creacion']),
                 'fecha_creacion_formateada' => date('d/m/Y H:i', strtotime($pasantia['fecha_creacion']))
             ];

            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error (details): " . $e->getMessage());
            echo json_encode(['error' => 'Error al obtener los detalles de la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para obtener tutores disponibles
    if ($_GET['api'] === 'tutores') {
        try {
            $stmt = $conexion->prepare("
                SELECT id, nombre, email
                FROM usuarios
                WHERE rol = 'tutor' AND estado = 'activo'
                ORDER BY nombre
            ");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (tutores) Prepare Failed: " . implode(" ", $conexion->errorInfo()));
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta.']);
                 exit;
             }
            $stmt->execute();
            $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);

             $tutores_sanitized = array_map(function($tutor) {
                  return [
                       'id' => (int)$tutor['id'],
                       'nombre' => htmlSafe($tutor['nombre']),
                       'email' => htmlSafe($tutor['email'])
                  ];
             }, $tutores);

            echo json_encode($tutores_sanitized);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error (tutores): " . $e->getMessage());
            echo json_encode(['error' => 'Error al obtener los tutores: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para obtener estudiantes disponibles para pasantías
    if ($_GET['api'] === 'estudiantes_disponibles') {
        try {
            $stmt = $conexion->prepare("
                SELECT id, nombre, email, codigo_estudiante, documento, telefono, nombre_empresa, ciclo
                FROM usuarios
                WHERE rol = 'estudiante'
                AND opcion_grado = 'pasantia'
                AND estado = 'activo'
                AND id NOT IN (
                    SELECT estudiante_id
                    FROM pasantias
                    WHERE estado != 'finalizada' AND estado != 'rechazada'
                )
                ORDER BY nombre
            ");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (estudiantes_disponibles) Prepare Failed: " . implode(" ", $conexion->errorInfo()));
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta.']);
                 exit;
             }
            $stmt->execute();
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

             $estudiantes_sanitized = array_map(function($est) {
                  return [
                       'id' => (int)$est['id'],
                       'nombre' => htmlSafe($est['nombre']),
                       'email' => htmlSafe($est['email']),
                       'codigo_estudiante' => htmlSafe($est['codigo_estudiante'] ?? 'N/A'),
                       'documento' => htmlSafe($est['documento'] ?? 'N/A'),
                       'telefono' => htmlSafe($est['telefono'] ?? 'No registrado'),
                       'nombre_empresa' => htmlSafe($est['nombre_empresa'] ?? ''),
                       'ciclo' => htmlSafe($est['ciclo'] ?? '')
                  ];
             }, $estudiantes);

            echo json_encode($estudiantes_sanitized);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error (estudiantes_disponibles): " . $e->getMessage());
            echo json_encode(['error' => 'Error al obtener los estudiantes disponibles: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para obtener datos de estudiante
    if ($_GET['api'] === 'estudiante_info' && isset($_GET['id'])) {
        try {
            $estudianteId = filter_var($_GET['id'], FILTER_VALIDATE_INT); // Sanitizar ID
             if ($estudianteId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de estudiante no válido']);
                  exit;
             }

            $stmt = $conexion->prepare("
                SELECT id, nombre, email, codigo_estudiante, documento, telefono, nombre_empresa, ciclo
                FROM usuarios
                WHERE id = ? AND rol = 'estudiante' AND opcion_grado = 'pasantia'
            ");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (estudiante_info) Prepare Failed: " . implode(" ", $conexion->errorInfo()));
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta.']);
                 exit;
             }
            $stmt->execute([$estudianteId]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estudiante) {
                http_response_code(404);
                echo json_encode(['error' => 'Estudiante no encontrado o no elegible para pasantía']);
                exit;
            }

             $response = [
                  'id' => (int)$estudiante['id'],
                  'nombre' => htmlSafe($estudiante['nombre']),
                  'email' => htmlSafe($estudiante['email']),
                  'codigo_estudiante' => htmlSafe($estudiante['codigo_estudiante'] ?? 'N/A'),
                  'documento' => htmlSafe($estudiante['documento'] ?? 'N/A'),
                  'telefono' => htmlSafe($estudiante['telefono'] ?? 'No registrado'),
                  'nombre_empresa' => htmlSafe($estudiante['nombre_empresa'] ?? ''),
                  'ciclo' => htmlSafe($estudiante['ciclo'] ?? '')
             ];

            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error (estudiante_info): " . $e->getMessage());
            echo json_encode(['error' => 'Error al obtener la información del estudiante: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para actualizar pasantía (usa transacción) - Eliminado manejo de documento_adicional
    if ($_GET['api'] === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
             $conexion->beginTransaction();

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                 $conexion->rollBack();
                exit;
            }

             $pasantiaId = filter_var($data['id'], FILTER_VALIDATE_INT);
             if ($pasantiaId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de pasantía inválido']);
                   $conexion->rollBack();
                  exit;
             }

            $campos = [];
            $valores = [];
            // Eliminado 'documento_adicional' de campos permitidos para la API de actualización
            $camposPermitidos = [
                'titulo' => 'string', 'descripcion' => 'string', 'empresa' => 'string',
                'direccion_empresa' => 'string', 'contacto_empresa' => 'string',
                'supervisor_empresa' => 'string', 'telefono_supervisor' => 'string',
                'fecha_inicio' => 'date', 'fecha_fin' => 'date', 'estado' => 'string',
                'tutor_id' => 'int_or_null'
            ];

            foreach ($camposPermitidos as $campo => $tipo) {
                if (isset($data[$campo])) {
                     $valor = $data[$campo];
                     switch ($tipo) {
                          case 'string': $valores[] = $valor; break;
                          case 'date':
                               // Permitir nulos o cadenas vacías para fechas
                               if ($valor !== null && $valor !== '' && !isValidDate($valor)) {
                                    $conexion->rollBack(); http_response_code(400); echo json_encode(['error' => "Fecha inválida para el campo {$campo}"]); exit;
                               }
                               $valores[] = empty($valor) ? null : $valor;
                               break;
                          case 'int_or_null':
                               $validated_val = filter_var($valor, FILTER_VALIDATE_INT);
                               // Permitir nulos o cadenas vacías para IDs opcionales, validar si no están vacíos
                               if ($valor !== null && $valor !== '' && $validated_val === false) {
                                     $conexion->rollBack(); http_response_code(400); echo json_encode(['error' => "Valor inválido para el campo {$campo}"]); exit;
                               }
                               $valores[] = ($valor === '' || $valor === null) ? null : $validated_val;
                               break;
                          default: $valores[] = $valor;
                     }
                    $campos[] = "`{$campo}` = ?";
                }
            }

            if (empty($campos)) {
                 $conexion->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'No se proporcionaron campos válidos para actualizar']);
                exit;
            }

            // La consulta SQL de actualización ya no incluye 'documento_adicional'
            $valores[] = $pasantiaId;
            $sql = "UPDATE pasantias SET " . implode(", ", $campos) . " WHERE id = ?";
            $stmt = $conexion->prepare($sql);

             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (actualizar) Prepare Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta.']);
                 exit;
             }

            if (!$stmt->execute($valores)) {
                 $conexion->rollBack();
                 error_log("API Error (actualizar) DB Execute Error: " . $stmt->errorInfo()[2]);
                 throw new Exception("Error al actualizar la pasantía en la base de datos.");
            }

             $conexion->commit();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía actualizada correctamente'
            ]);

        } catch (Exception $e) {
             if (isset($conexion) && $conexion->inTransaction()) { $conexion->rollBack(); }
            http_response_code(500);
            error_log("API Error (actualizar): " . $e->getMessage());
            echo json_encode(['error' => 'Error al actualizar la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para eliminar pasantía (usa transacción) - Eliminado manejo de documento_adicional
    if ($_GET['api'] === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
             $conexion->beginTransaction();

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                 $conexion->rollBack();
                exit;
            }

            $pasantiaId = filter_var($data['id'], FILTER_VALIDATE_INT);
             if ($pasantiaId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de pasantía inválido']);
                   $conexion->rollBack();
                  exit;
             }

             // Eliminar archivo(s) asociado(s) antes de eliminar la pasantía (solo archivo_documento)
             $stmt_file = $conexion->prepare("SELECT archivo_documento FROM pasantias WHERE id = ? FOR UPDATE"); // Eliminado documento_adicional
             // Manejar posible error en prepare
             if ($stmt_file === false) {
                 error_log("API Error (eliminar) Prepare File Select Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta de archivo.']);
                 exit;
             }
             $stmt_file->execute([$pasantiaId]);
             $archivos = $stmt_file->fetch(PDO::FETCH_ASSOC);

             if ($archivos) {
                  $files_to_delete = [];
                  // Solo añade archivo_documento si existe
                  if (!empty($archivos['archivo_documento'])) $files_to_delete[] = $archivos['archivo_documento'];
                  // NO SE AÑADE documento_adicional aquí

                  foreach ($files_to_delete as $file) {
                      $filePath = __DIR__ . '/../../uploads/pasantias/' . $file;
                      if (file_exists($filePath)) {
                           unlink($filePath);
                      } else {
                           error_log("Advertencia: Archivo de pasantía no encontrado para eliminar: " . $filePath);
                      }
                  }
             }

            // Eliminar la fila de la pasantía de la base de datos
            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("API Error (eliminar) Prepare Delete Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 http_response_code(500);
                 echo json_encode(['error' => 'Error interno del servidor al preparar la consulta de eliminación.']);
                 exit;
             }
             if (!$stmt->execute([$pasantiaId])) {
                  $conexion->rollBack();
                   error_log("DB Execute Error (eliminar_pasantia): " . $stmt->errorInfo()[2]);
                  throw new Exception("Error al eliminar la pasantía de la base de datos.");
             }

             // Verificar si se eliminó alguna fila
             if ($stmt->rowCount() === 0) {
                  $conexion->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }

            $conexion->commit();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía eliminada correctamente'
            ]);

        } catch (Exception $e) {
             if (isset($conexion) && $conexion->inTransaction()) { $conexion->rollBack(); }
            http_response_code(500);
            error_log("API Error (eliminar): " . $e->getMessage());
            echo json_encode(['error' => 'Error al eliminar la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }


    // Manejar endpoints de API no encontrados
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint de API no encontrado']);
    exit;
}
// --- Fin Manejo de solicitudes API ---


// --- Lógica para la carga inicial de la página (si no es una solicitud API) ---

// Recuperar mensaje o error de la URL si existe (después de una redirección GET)
// Usar htmlSafe para sanitizar la salida en HTML
$mensaje = isset($_GET['mensaje']) ? htmlSafe($_GET['mensaje']) : '';
$error = isset($_GET['error']) ? htmlSafe($_GET['error']) : '';

// Cargar el nombre de usuario de la sesión
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener tutores para el selector en los formularios
$tutoresQuery = $conexion->query("
    SELECT id, nombre, email
    FROM usuarios
    WHERE rol = 'tutor' AND estado = 'activo'
    ORDER BY nombre
");
$tutores = $tutoresQuery ? $tutoresQuery->fetchAll(PDO::FETCH_ASSOC) : [];

// Obtener estudiantes disponibles para pasantías para el selector inicial
$estudiantesDisponiblesQuery = $conexion->query("
    SELECT id, nombre, email, codigo_estudiante, documento, telefono, nombre_empresa, ciclo
    FROM usuarios
    WHERE rol = 'estudiante'
    AND opcion_grado = 'pasantia'
    AND estado = 'activo'
    AND id NOT IN (
        SELECT estudiante_id
        FROM pasantias
        WHERE estado != 'finalizada' AND estado != 'rechazada'
    )
    ORDER BY nombre
");
$estudiantes_disponibles = $estudiantesDisponiblesQuery ? $estudiantesDisponiblesQuery->fetchAll(PDO::FETCH_ASSOC) : [];

// Obtener pasantías existentes para la lista principal
$pasantiasQuery = $conexion->query("
    SELECT p.*,
           e.nombre as estudiante_nombre,
           t.nombre as tutor_nombre
    FROM pasantias p
    LEFT JOIN usuarios e ON p.estudiante_id = e.id
    LEFT JOIN usuarios t ON p.tutor_id = t.id
    ORDER BY p.fecha_creacion DESC
");
$pasantias = $pasantiasQuery ? $pasantiasQuery->fetchAll(PDO::FETCH_ASSOC) : [];

// NO ES NECESARIO cerrar la conexión aquí, se cerrará automáticamente al final del script.
// Si insistes en cerrarla manualmente, hazlo al final del archivo,
// después de que todo el PHP necesario para generar el HTML se haya ejecutado.
// $conexion = null;


// --- Procesar formularios (Acciones POST sin API) ---
// Este bloque se ejecuta si la solicitud es POST y NO es una solicitud API.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Las variables $mensaje y $error ya se declararon al inicio del script,
    // y si vienen de una redirección GET, ya se cargaron arriba.
    // Si la acción POST falla, se redirigirá con un error GET.

    try {
        $conexion->beginTransaction();

        if ($_POST['accion'] === 'crear_pasantia') {
            // Sanitización y validación de datos
            $estudiante_id = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $empresa = trim($_POST['empresa'] ?? '');
            $direccion_empresa = trim($_POST['direccion_empresa'] ?? '');
            $contacto_empresa = trim($_POST['contacto_empresa'] ?? '');
            $supervisor_empresa = trim($_POST['supervisor_empresa'] ?? '');
            $telefono_supervisor = trim($_POST['telefono_supervisor'] ?? '');
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
            $fecha_fin = trim($_POST['fecha_fin'] ?? '');
            $tutor_id = !empty($_POST['tutor_id']) ? filter_var($_POST['tutor_id'], FILTER_VALIDATE_INT) : null;

            // Validaciones obligatorias y de formato
            if ($estudiante_id === false || $estudiante_id === null || empty($titulo) || empty($empresa) || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception("Todos los campos marcados con * son obligatorios o tienen formato inválido.");
            }

            // Validar si el estudiante seleccionado existe y es elegible para pasantía
            $stmtEstudiante = $conexion->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'estudiante' AND opcion_grado = 'pasantia' AND estado = 'activo'");
            // Manejar posible error en prepare
             if ($stmtEstudiante === false) {
                 error_log("POST Error (crear_pasantia) Prepare Estudiante Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 throw new Exception('Error interno del servidor al preparar la consulta de estudiante.');
             }
            $stmtEstudiante->execute([$estudiante_id]);
            if (!$stmtEstudiante->fetch()) {
                throw new Exception("El estudiante seleccionado no es válido o no está disponible.");
            }

            if (!isValidDate($fecha_inicio) || !isValidDate($fecha_fin)) {
                throw new Exception("Las fechas de inicio y fin deben tener un formato de fecha válido.");
            }
            if (new DateTime($fecha_fin) < new DateTime($fecha_inicio)) {
                throw new Exception("La fecha de fin no puede ser anterior a la fecha de inicio.");
            }

            if ($tutor_id !== null && $tutor_id === false) {
                 throw new Exception("ID de tutor no válido.");
            }


            // Procesar archivo si se ha subido
            $archivo_documento = null;
            if (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] === UPLOAD_ERR_OK) {
                 $archivo = $_FILES['archivo_documento'];
                 $archivo_nombre = $archivo['name'];
                 $archivo_tmp = $archivo['tmp_name'];
                 $archivo_size = $archivo['size'];
                 $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));

                 if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                     $conexion->rollBack();
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx).");
                 }

                 $max_size = 10 * 1024 * 1024; // 10 MB
                 if ($archivo_size > $max_size) {
                     $conexion->rollBack();
                    throw new Exception("El tamaño del archivo excede el límite permitido (10MB).");
                 }

                 $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                 $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;

                 if (!is_dir(dirname($ruta_destino))) {
                     mkdir(dirname($ruta_destino), 0777, true);
                 }

                 if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                     $archivo_documento = $archivo_nuevo_nombre;
                 } else {
                     $conexion->rollBack();
                     throw new Exception("Error al mover el archivo subido. Intente nuevamente.");
                 }
            } elseif (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] !== UPLOAD_ERR_NO_FILE) {
                 $conexion->rollBack();
                 $phpFileUploadErrors = array(
                    1 => 'El archivo subido supera la directiva upload_max_filesize en php.ini',
                    2 => 'El archivo subido supera la directiva MAX_FILE_SIZE especificada en el formulario HTML.',
                    3 => 'El archivo subido solo se subió parcialmente.',
                    4 => 'No se subió ningún archivo.',
                    6 => 'Falta una carpeta temporal.',
                    7 => 'No se pudo escribir el archivo en el disco.',
                    8 => 'Una extensión de PHP detuvo la carga del archivo.',
                 );
                 $error_code = $_FILES['archivo_documento']['error'];
                 $error_message = $phpFileUploadErrors[$error_code] ?? 'Error de subida desconocido';
                 throw new Exception("Error en la subida del archivo: " . $error_message);
            }


            // Insertar pasantía usando sentencia preparada (sin documento_adicional)
            $stmt = $conexion->prepare("
                INSERT INTO pasantias (
                    estudiante_id, titulo, descripcion, empresa, direccion_empresa,
                    contacto_empresa, supervisor_empresa, telefono_supervisor,
                    fecha_inicio, fecha_fin, estado, tutor_id, archivo_documento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)
            ");

             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("POST Error (crear_pasantia) Prepare Insert Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 throw new Exception('Error interno del servidor al preparar la consulta de inserción.');
             }

            $execute_success = $stmt->execute([
                $estudiante_id,
                $titulo,
                $descripcion,
                $empresa,
                $direccion_empresa,
                $contacto_empresa,
                $supervisor_empresa,
                $telefono_supervisor,
                $fecha_inicio,
                $fecha_fin,
                $tutor_id,
                $archivo_documento
            ]);

            if (!$execute_success) {
                $conexion->rollBack();
                error_log("DB Execute Error (crear_pasantia): " . $stmt->errorInfo()[2]);
                throw new Exception("Error al registrar la pasantía en la base de datos.");
            }

            $conexion->commit();
            $mensaje = "Pasantía creada exitosamente";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;

        } elseif ($_POST['accion'] === 'actualizar_pasantia') {
            // Sanitización y validación de datos para actualizar
            $pasantiaId = filter_var($_POST['pasantia_id'] ?? null, FILTER_VALIDATE_INT);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $empresa = trim($_POST['empresa'] ?? '');
            $direccion_empresa = trim($_POST['direccion_empresa'] ?? '');
            $contacto_empresa = trim($_POST['contacto_empresa'] ?? '');
            $supervisor_empresa = trim($_POST['supervisor_empresa'] ?? '');
            $telefono_supervisor = trim($_POST['telefono_supervisor'] ?? '');
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
            $fecha_fin = trim($_POST['fecha_fin'] ?? '');
            $estado = trim($_POST['estado'] ?? '');
            $tutor_id = !empty($_POST['tutor_id']) ? filter_var($_POST['tutor_id'], FILTER_VALIDATE_INT) : null;

            // Validaciones obligatorias
            if ($pasantiaId === false || $pasantiaId === null || empty($titulo) || empty($empresa) || empty($estado)) {
                 throw new Exception("ID, título, empresa y estado son obligatorios para actualizar.");
            }

            // Validaciones de formato de fecha si los campos no están vacíos
            if (!isValidDate($fecha_inicio) || !isValidDate($fecha_fin)) {
                throw new Exception("Las fechas de inicio y fin deben tener un formato de fecha válido.");
            }
            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                 if (new DateTime($fecha_fin) < new DateTime($fecha_inicio)) {
                     throw new Exception("La fecha de fin no puede ser anterior a la fecha de inicio.");
                 }
            }


            // Validar ID de tutor si no es nulo
            if ($tutor_id !== null && $tutor_id === false) {
                 throw new Exception("ID de tutor no válido.");
            }

            // Validar estado
            $estados_validos = ['pendiente', 'aprobada', 'rechazada', 'en_proceso', 'finalizada'];
            if (!in_array($estado, $estados_validos)) {
                 throw new Exception("Estado de pasantía no válido.");
            }

            // NO HAY MANEJO DE SUBIDA DE ARCHIVO ADICIONAL AQUÍ

            $archivo_documento_actualizado = null; // Variable para guardar el nuevo nombre del archivo o null si se elimina

            // 1. Obtener el nombre del archivo actual de la base de datos
            $stmt_current_file = $conexion->prepare("SELECT archivo_documento FROM pasantias WHERE id = ? FOR UPDATE"); // Usamos FOR UPDATE para bloquear la fila
             if ($stmt_current_file === false) {
                  error_log("POST Error (actualizar_pasantia) Prepare Current File Failed: " . implode(" ", $conexion->errorInfo()));
                  $conexion->rollBack();
                  throw new Exception('Error interno del servidor al preparar la consulta del archivo actual.');
             }
            $stmt_current_file->execute([$pasantiaId]);
            $current_archivo_documento = $stmt_current_file->fetchColumn(); // Obtiene solo el valor de la primera columna

            // 2. Determinar si se eliminará el archivo actual
            $eliminar_archivo_existente = isset($_POST['delete_archivo_documento']) && $_POST['delete_archivo_documento'] === '1';

            // 3. Procesar nueva subida de archivo
            $nueva_subida = isset($_FILES['edit_nuevo_archivo']) && $_FILES['edit_nuevo_archivo']['error'] === UPLOAD_ERR_OK;

            if ($nueva_subida) {
                 // Hay un nuevo archivo subido, procesarlo
                 $archivo = $_FILES['edit_nuevo_archivo'];
                 $archivo_nombre = $archivo['name'];
                 $archivo_tmp = $archivo['tmp_name'];
                 $archivo_size = $archivo['size'];
                 $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));

                 if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                     $conexion->rollBack();
                     throw new Exception("El nuevo archivo debe ser PDF o Word (doc/docx).");
                 }

                 $max_size = 10 * 1024 * 1024; // 10 MB
                 if ($archivo_size > $max_size) {
                     $conexion->rollBack();
                     throw new Exception("El tamaño del nuevo archivo excede el límite permitido (10MB).");
                 }

                 $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                 $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;

                 if (!is_dir(dirname($ruta_destino))) {
                     mkdir(dirname($ruta_destino), 0777, true);
                 }

                 if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                     // Subida exitosa, este será el nuevo valor para archivo_documento
                     $archivo_documento_actualizado = $archivo_nuevo_nombre;

                     // Si había un archivo anterior, eliminarlo
                     if (!empty($current_archivo_documento)) {
                          $ruta_antigua = __DIR__ . '/../../uploads/pasantias/' . $current_archivo_documento;
                          if (file_exists($ruta_antigua)) {
                               unlink($ruta_antigua);
                          } else {
                               error_log("Advertencia: Archivo antiguo no encontrado para eliminar: " . $ruta_antigua);
                          }
                     }

                 } else {
                     $conexion->rollBack();
                     throw new Exception("Error al mover el nuevo archivo subido. Intente nuevamente.");
                 }

            } elseif ($eliminar_archivo_existente) {
                 // No hay nueva subida, pero se marcó para eliminar el archivo existente
                 if (!empty($current_archivo_documento)) {
                      $ruta_antigua = __DIR__ . '/../../uploads/pasantias/' . $current_archivo_documento;
                       if (file_exists($ruta_antigua)) {
                           unlink($ruta_antigua);
                       } else {
                           error_log("Advertencia: Archivo marcado para eliminar no encontrado en disco: " . $ruta_antigua);
                       }
                 }
                 // Establecer archivo_documento a NULL en la DB
                 $archivo_documento_actualizado = null;

            } else {
                 // No hay nueva subida y no se marcó para eliminar el existente
                 // Mantener el nombre del archivo actual en la DB
                 $archivo_documento_actualizado = $current_archivo_documento;
            }
            // Construir la consulta de actualización dinámicamente (QUITANDO documento_adicional)
            $sql_update_campos = [
                'titulo = ?', 'descripcion = ?', 'empresa = ?', 'direccion_empresa = ?',
                'contacto_empresa = ?', 'supervisor_empresa = ?', 'telefono_supervisor = ?',
                'fecha_inicio = ?', 'fecha_fin = ?', 'estado = ?', 'tutor_id = ?'
                // documento_adicional NO se añade aquí intencionalmente
           ];
           $valores_update = [
                $titulo, $descripcion, $empresa, $direccion_empresa, $contacto_empresa,
                $supervisor_empresa, $telefono_supervisor,
                empty($fecha_inicio) ? null : $fecha_inicio,
                empty($fecha_fin) ? null : $fecha_fin,
                $estado, $tutor_id
           ];
           $sql_update_campos[] = 'archivo_documento = ?';
           $valores_update[] = $archivo_documento_actualizado;
            // NO SE AÑADE documento_adicional a la consulta ni a los valores aquí

            $sql = "UPDATE pasantias SET " . implode(", ", $sql_update_campos) . " WHERE id = ?";
            $valores_update[] = $pasantiaId; // Añade el ID de la pasantía al final

            $stmt = $conexion->prepare($sql);

             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("POST Error (actualizar_pasantia) Prepare Update Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 throw new Exception('Error interno del servidor al preparar la consulta de actualización.');
             }


             if (!$stmt->execute($valores_update)) {
                // ... (Manejo de error y rollback) ...
                 // Verificar si el error es sobre una columna desconocida
                 if (strpos($stmt->errorInfo()[2], "Unknown column 'documento_adicional'") !== false) {
                      // Si el error es sobre documento_adicional, lanzar un mensaje más específico
                      // Nota: Con los cambios anteriores, esto ya no debería ocurrir si la columna existe.
                      throw new Exception("Error en la base de datos: Parece que la columna 'documento_adicional' aún es referenciada incorrectamente en el código PHP o no existe en la base de datos. Mensaje original: " . $stmt->errorInfo()[2]);
                 } else {
                      // Para otros errores de DB
                       error_log("DB Execute Error (actualizar_pasantia): " . $stmt->errorInfo()[2]);
                       throw new Exception("Error al actualizar la pasantía en la base de datos.");
                 }
            }

            $conexion->commit();
            $mensaje = "Pasantía actualizada exitosamente";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;


        } elseif ($_POST['accion'] === 'eliminar_pasantia') {
             // Sanitizar ID
             $pasantiaId = filter_var($_POST['pasantia_id'] ?? null, FILTER_VALIDATE_INT);
              if ($pasantiaId === false || $pasantiaId === null) {
                   throw new Exception("ID de pasantía inválido para eliminar.");
              }

              $conexion->beginTransaction();

             // Eliminar archivo(s) asociado(s) antes de eliminar la pasantía (solo archivo_documento)
             $stmt_file = $conexion->prepare("SELECT archivo_documento FROM pasantias WHERE id = ? FOR UPDATE"); // Eliminado documento_adicional
             // Manejar posible error en prepare
             if ($stmt_file === false) {
                 error_log("POST Error (eliminar_pasantia) Prepare File Select Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 throw new Exception('Error interno del servidor al preparar la consulta de archivo.');
             }
             $stmt_file->execute([$pasantiaId]);
             $archivos = $stmt_file->fetch(PDO::FETCH_ASSOC);

             if ($archivos) {
                  $files_to_delete = [];
                  // Solo añade archivo_documento si existe
                  if (!empty($archivos['archivo_documento'])) $files_to_delete[] = $archivos['archivo_documento'];
                  // NO SE AÑADE documento_adicional aquí

                  foreach ($files_to_delete as $file) {
                      $filePath = __DIR__ . '/../../uploads/pasantias/' . $file;
                      if (file_exists($filePath)) {
                           unlink($filePath);
                      } else {
                           error_log("Advertencia: Archivo de pasantía no encontrado para eliminar: " . $filePath);
                      }
                  }
             }

            // Eliminar la fila de la pasantía de la base de datos
            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
             // Manejar posible error en prepare
             if ($stmt === false) {
                 error_log("POST Error (eliminar_pasantia) Prepare Delete Failed: " . implode(" ", $conexion->errorInfo()));
                 $conexion->rollBack();
                 throw new Exception('Error interno del servidor al preparar la consulta de eliminación.');
             }
             if (!$stmt->execute([$pasantiaId])) {
                  $conexion->rollBack();
                   error_log("DB Execute Error (eliminar_pasantia): " . $stmt->errorInfo()[2]);
                  throw new Exception("Error al eliminar la pasantía de la base de datos.");
             }

             // Verificar si se eliminó alguna fila
             if ($stmt->rowCount() === 0) {
                  $conexion->rollBack();
                http_response_code(404); // O 400 si el ID fue inválido
                // No se encontró la pasantía con ese ID
                throw new Exception("La pasantía con ID {$pasantiaId} no fue encontrada para eliminar.");
             }

            $conexion->commit();
            $mensaje = "Pasantía eliminada exitosamente";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
        }

    } catch (Exception $e) {
        // Capturar cualquier excepción y redirigir con un mensaje de error
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = $e->getMessage();
        error_log("Error en gestión de pasantías (POST): " . $e->getMessage());
        // Redirigir con el error, asegurando que se muestre en la página
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit; // Terminar la ejecución después de redirigir
    }
}
// --- Fin Procesar formularios ---


// --- Lógica para la carga inicial de la página (si no es una solicitud API ni POST) ---
// Las variables $mensaje, $error, $nombreUsuario, $tutores, $estudiantes_disponibles, $pasantias
// ya fueron cargadas en el bloque PHP inicial.

// Pasar datos iniciales a JSON para usar en JavaScript
// Estos se asignarán a variables JS dentro de la etiqueta <script>
$pasantiasDataJSON = json_encode($pasantias);
$tutoresDataJSON = json_encode($tutores);
$estudiantesDisponiblesDataJSON = json_encode($estudiantes_disponibles);

// La conexión se cerrará automáticamente al final del script.
// Si insistes en cerrarla manualmente, hazlo al final del archivo PHP,
// después de que todo el contenido HTML se haya generado.
// Por ejemplo:
// $conexion = null;
// ?> 
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pasantías - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestion_pasantias.css">
     </head>
<body>

<div id="logo" onclick="toggleNav()">
    <img src="/assets/images/logofet.png" alt="Logo FET" class="logo-img">
</div>

    <nav id="navbar">
    <div class="nav-header">
            <div id="nav-logo" onclick="toggleNav()">
        <img src="/assets/images/logofet.png" alt="Logo FET" class="logo-img">
        </div>
        <ul>
            <li><a href="/views/administrador/inicio.php" >Inicio</a></li>
            <li><a href="/views/administrador/aprobacion.php">Aprobación de Usuarios</a></li>
            <li><a href="/views/administrador/usuarios.php">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="/views/administrador/gestion_seminario.php">Seminario</a></li>
                    <li><a href="/views/administrador/gestion_proyectos.php">Proyectos</a></li>
                    <li><a href="/views/administrador/gestion_pasantias.php" class="active">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="/views/administrador/reportes.php">Reportes y Estadísticas</a></li>
            <li><a href="#">Rol: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
            <li><a href="/views/general/login.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main>
        <h1>Gestión de Pasantías</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button id="crearPasantiaTab" class="active">Registrar Pasantía</button>
            <button id="listarPasantiasTab">Listar Pasantías</button>
        </div>

        <section id="crearPasantiaSection" class="tab-content">
            <form id="formCrearPasantia" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_pasantia">
                <input type="hidden" id="estudiante_id_crear_form" name="estudiante_id">

                <div class="form-group" id="seleccionEstudianteGroup">
                   <h3>Seleccionar Estudiante</h3>
                   <input type="text" id="estudianteSearch" class="estudiante-search" placeholder="Buscar estudiante por nombre, código o documento...">

                   <div class="estudiantes-grid" id="estudiantesGrid">
                        <?php if (empty($estudiantes_disponibles)): ?>
                            <div class="no-estudiantes">No hay estudiantes disponibles con opción de grado "pasantía".</div>
                        <?php else: ?>
                            <?php foreach ($estudiantes_disponibles as $estudiante): ?>
                                <div class="estudiante-card"
                                        data-id="<?= htmlspecialchars($estudiante['id']) ?>"
                                        data-nombre="<?= htmlspecialchars($estudiante['nombre']) ?>"
                                        data-codigo="<?= htmlspecialchars($estudiante['codigo_estudiante'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($estudiante['email']) ?>"
                                        data-documento="<?= htmlspecialchars($estudiante['documento']) ?>"
                                        data-telefono="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>"
                                        data-empresa="<?= htmlspecialchars($estudiante['nombre_empresa'] ?? '') ?>"
                                        data-ciclo="<?= htmlspecialchars($estudiante['ciclo'] ?? '') ?>"
                                        onclick="seleccionarEstudiante(<?= htmlspecialchars($estudiante['id']) ?>)">
                                    <div class="estudiante-header">
                                        <h3><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                                        <?php if (!empty($estudiante['ciclo'])): ?>
                                            <span class="ciclo-badge"><?= htmlspecialchars(ucfirst($estudiante['ciclo'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="estudiante-body">
                                        <div class="info-item">
                                            <span class="label">Código:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['codigo_estudiante'] ?? 'No asignado') ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label">Documento:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['documento']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label">Email:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['email']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label">Teléfono:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['telefono'] ?? 'No registrado') ?></span>
                                        </div>
                                        <?php if (!empty($estudiante['nombre_empresa'])): ?>
                                        <div class="info-item">
                                            <span class="label">Empresa:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['nombre_empresa']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="selected-estudiante-info" id="selectedEstudianteInfo" style="display: none;">
                    <h4>
                        Estudiante Seleccionado
                        <button type="button" class="btn-change" onclick="cambiarEstudiante()">Cambiar</button>
                    </h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Nombre:</span>
                            <span class="valor" id="infoEstudianteNombre"></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Código:</span>
                            <span class="valor" id="infoEstudianteCodigo"></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Documento:</span>
                            <span class="valor" id="infoEstudianteDocumento"></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Email:</span>
                            <span class="valor" id="infoEstudianteEmail"></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Teléfono:</span>
                            <span class="valor" id="infoEstudianteTelefono"></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Empresa:</span>
                            <span class="valor" id="infoEstudianteEmpresa"></span>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="datosPasantiaGroup" style="display: none;">
                    <h3>Datos de la Pasantía</h3>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="titulo">Título de la Pasantía *</label>
                            <input type="text" id="titulo" name="titulo" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="empresa">Empresa *</label>
                            <input type="text" id="empresa" name="empresa" required>
                        </div>
                        <div class="form-field">
                            <label for="direccion_empresa">Dirección de Empresa</label>
                            <input type="text" id="direccion_empresa" name="direccion_empresa">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="contacto_empresa">Contacto Empresa</label>
                            <input type="text" id="contacto_empresa" name="contacto_empresa">
                        </div>
                        <div class="form-field">
                            <label for="supervisor_empresa">Supervisor Empresa</label>
                            <input type="text" id="supervisor_empresa" name="supervisor_empresa">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="telefono_supervisor">Teléfono del Supervisor</label>
                            <input type="text" id="telefono_supervisor" name="telefono_supervisor">
                        </div>
                    </div>
                </div>

                <div class="form-group" id="fechasTutorGroup" style="display: none;">
                    <h3>Fechas y Tutor</h3>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                        </div>
                        <div class="form-field">
                            <label for="fecha_fin">Fecha de Fin *</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="tutor_id_crear">Tutor Asignado</label>
                            <select id="tutor_id_crear" name="tutor_id">
                                <option value="">Sin tutor asignado</option>
                                <?php foreach ($tutores as $tutor): ?>
                                    <option value="<?= htmlspecialchars($tutor['id']) ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="documentosGroup" style="display: none;">
                    <h3>Documentos</h3>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="archivo_documento">Documento de la Pasantía (PDF o Word)</label>
                            <input type="file" id="archivo_documento" name="archivo_documento" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        </div>
                    </div>
                     </div>

                <div class="form-actions" id="formActions" style="display: none;">
                    <button type="submit" class="btn-primary">Registrar Pasantía</button>
                    <button type="button" class="btn-secondary" onclick="limpiarFormulario()">Limpiar Formulario</button>
                </div>
            </form>
        </section>

        <section id="listarPasantiasSection" class="tab-content" style="display: none;">
            <div class="search-filter">
                <input type="text" id="searchPasantias" placeholder="Buscar por estudiante, empresa o título...">
                <select id="filterEstado">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aprobada">Aprobada</option>
                    <option value="rechazada">Rechazada</option>
                    <option value="en_proceso">En proceso</option>
                    <option value="finalizada">Finalizada</option>
                </select>
            </div>

            <div class="table-responsive">
                <table id="tablaPasantias" class="tabla-pasantias">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Estudiante</th>
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Tutor Asignado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pasantias)): ?>
                            <tr class="no-data-row"><td colspan="9" class="no-data">No hay pasantías registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pasantias as $pasantia): ?>
                                <tr data-id="<?= htmlspecialchars($pasantia['id']) ?>"
                                    data-estudiante="<?= strtolower(htmlspecialchars($pasantia['estudiante_nombre'] ?? '')) ?>"
                                    data-titulo="<?= strtolower(htmlspecialchars($pasantia['titulo'] ?? '')) ?>"
                                    data-empresa="<?= strtolower(htmlspecialchars($pasantia['empresa'] ?? '')) ?>"
                                    data-estado="<?= htmlspecialchars($pasantia['estado'] ?? '') ?>">
                                    <td><?= htmlspecialchars($pasantia['id']) ?></td>
                                    <td><?= htmlspecialchars($pasantia['estudiante_nombre'] ?? 'No asignado') ?></td>
                                    <td><?= htmlspecialchars($pasantia['titulo'] ?? 'Sin título') ?></td>
                                    <td><?= htmlspecialchars($pasantia['empresa'] ?? 'No especificada') ?></td>
                                    <td>
                                        <span class="badge estado-<?= htmlspecialchars($pasantia['estado'] ?? 'pendiente') ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pasantia['estado'] ?? 'pendiente'))) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida' ?></td>
                                    <td><?= !empty($pasantia['fecha_fin']) ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No establecida' ?></td>
                                    <td><?= htmlspecialchars($pasantia['tutor_nombre'] ?? 'No asignado') ?></td>
                                    <td class="acciones">
                                        <?php if (!empty($pasantia['archivo_documento'])): ?>
                                            <a href="/uploads/pasantias/<?= htmlspecialchars($pasantia['archivo_documento']) ?>" target="_blank" class="btn-documento" title="Ver documento">📄</a>
                                        <?php endif; ?>
                                        <button class="btn-ver" onclick="verPasantia(<?= htmlspecialchars($pasantia['id']) ?>)" title="Ver detalles">👁️</button>
                                        <button class="btn-editar" onclick="editarPasantia(<?= htmlspecialchars($pasantia['id']) ?>)" title="Editar">✏️</button>
                                        <button class="btn-eliminar" onclick="confirmarEliminarPasantia(<?= htmlspecialchars($pasantia['id']) ?>)" title="Eliminar">❌</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="modalVerPasantia" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerPasantia')">&times;</span>
            <h2>Detalles de la Pasantía</h2>
            <div id="detallesPasantia" class="detalles-pasantia">
                </div>
            <div class="form-actions">
                <button type="button" class="btn-editar" onclick="editarPasantiaDesdeVer()">Editar Pasantía</button>
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalVerPasantia')">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="modalEditarPasantia" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarPasantia')">&times;</span>
            <h2>Editar Pasantía</h2>
            <form id="formEditarPasantia" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_pasantia">
                <input type="hidden" name="pasantia_id" id="edit_pasantia_id">

                <div class="form-group">
                    <h3>Datos del Estudiante</h3>
                    <div class="form-row">
                        <div class="form-field">
                            <label>Nombre:</label>
                            <p id="estudiante_nombre" class="campo-solo-lectura"></p>
                        </div>
                        <div class="form-field">
                            <label>Código:</label>
                            <p id="estudiante_codigo" class="campo-solo-lectura"></p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label>Email:</label>
                            <p id="estudiante_email" class="campo-solo-lectura"></p>
                        </div>
                        <div class="form-field">
                            <label>Documento:</label>
                            <p id="estudiante_documento" class="campo-solo-lectura"></p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <h3>Datos de la Pasantía</h3>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="edit_titulo">Título *</label>
                            <input type="text" id="edit_titulo" name="titulo" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="edit_descripcion">Descripción</label>
                            <textarea id="edit_descripcion" name="descripcion" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit_empresa">Empresa *</label>
                            <input type="text" id="edit_empresa" name="empresa" required>
                        </div>
                        <div class="form-field">
                            <label for="edit_direccion_empresa">Dirección de Empresa</label>
                            <input type="text" id="edit_direccion_empresa" name="direccion_empresa">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit_contacto_empresa">Contacto Empresa</label>
                            <input type="text" id="edit_contacto_empresa" name="contacto_empresa">
                        </div>
                        <div class="form-field">
                            <label for="edit_supervisor_empresa">Supervisor Empresa</label>
                            <input type="text" id="edit_supervisor_empresa" name="supervisor_empresa">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit_telefono_supervisor">Teléfono del Supervisor</label>
                            <input type="text" id="edit_telefono_supervisor" name="telefono_supervisor">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <h3>Fechas y Estado</h3>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit_fecha_inicio">Fecha de Inicio</label>
                            <input type="date" id="edit_fecha_inicio" name="fecha_inicio">
                        </div>
                        <div class="form-field">
                            <label for="edit_fecha_fin">Fecha de Fin</label>
                            <input type="date" id="edit_fecha_fin" name="fecha_fin">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit_estado">Estado *</label>
                            <select id="edit_estado" name="estado" required>
                                <option value="pendiente">Pendiente</option>
                                <option value="aprobada">Aprobada</option>
                                <option value="rechazada">Rechazada</option>
                                <option value="en_proceso">En Proceso</option>
                                <option value="finalizada">Finalizada</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="edit_tutor_id">Tutor Asignado</label>
                            <select id="edit_tutor_id" name="tutor_id">
                                <option value="">Sin tutor asignado</option>
                                <?php foreach ($tutores as $tutor): ?>
                                    <option value="<?= htmlspecialchars($tutor['id']) ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <h3>Documentos</h3>
                    <div class="form-row">
                         <div class="form-field full-width">
                             <label>Documento Principal Actual:</label>
                             <div id="edit_documento_actual" class="documento-info">
                                  </div>
                         </div>
                    </div>
                    <div class="form-row">
                         <div class="form-field full-width">
                             <label for="edit_nuevo_archivo">Cambiar Documento Principal (PDF o Word):</label>
                             <input type="file" id="edit_nuevo_archivo" name="edit_nuevo_archivo" accept=".pdf,.doc,.docx">
                             <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                         </div>
                    </div>
                    <div class="form-row">
                         <div class="form-field">
                             <label for="delete_archivo_documento">
                                  <input type="checkbox" id="delete_archivo_documento" name="delete_archivo_documento" value="1">
                                  Eliminar documento principal existente
                             </label>
                         </div>
                    </div>
                    </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarPasantia')">Cancelar</button>
                    <button type="button" class="btn-danger" onclick="confirmarEliminarPasantiaModal()">Eliminar Pasantía</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalConfirmarEliminar" class="modal">
        <div class="modal-content modal-small">
            <span class="close" onclick="cerrarModal('modalConfirmarEliminar')">&times;</span>
            <h2>Confirmar Eliminación</h2>
            <p>¿Está seguro que desea eliminar esta pasantía? Esta acción no se puede deshacer.</p>
            <form id="formEliminarPasantia" method="POST" action="">
                <input type="hidden" name="accion" value="eliminar_pasantia">
                <input type="hidden" name="pasantia_id" id="eliminar_pasantia_id">
                <div class="form-actions">
                    <button type="submit" class="btn-danger">Eliminar</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalConfirmarEliminar')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <footer>
        
    </footer>
    <script>
        // Variables globales (inicializadas o usadas por JS)
        let pasantiaActual = null; // Para guardar los datos de la pasantía al ver/editar
        let estudianteSeleccionado = null; // Para guardar los datos del estudiante al crear
        let initialPasantiasData = []; // Para los datos de la tabla
        let initialEstudiantesData = []; // Para la selección de estudiante
        let initialTutoresData = []; // Para los selectores de tutor

        // ### INICIALIZACIÓN DE DATOS DESDE PHP ###
        // Estas líneas DEBEN estar aquí para que el JavaScript tenga los datos
        // cargados por PHP antes de que se ejecute el resto del script JS.
        <?php if (isset($pasantiasDataJSON)): ?>
        initialPasantiasData = <?php echo $pasantiasDataJSON; ?>;
        <?php else: ?>
        initialPasantiasData = []; // Inicializar como array vacío si no hay datos
        <?php endif; ?>

         <?php if (isset($estudiantesDisponiblesDataJSON)): ?>
        initialEstudiantesData = <?php echo $estudiantesDisponiblesDataJSON; ?>;
         <?php else: ?>
        initialEstudiantesData = []; // Inicializar como array vacío si no hay datos
        <?php endif; ?>

         <?php if (isset($tutoresDataJSON)): ?>
        initialTutoresData = <?php echo $tutoresDataJSON; ?>;
         <?php else: ?>
        initialTutoresData = []; // Inicializar como array vacío si no hay datos
        <?php endif; ?>
        // ### FIN INICIALIZACIÓN DE DATOS DESDE PHP ###


        // Función ucfirst (definida en JS si se necesita en el JS)
        function ucfirst(str) {
            if (typeof str !== 'string') return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

         // Función nl2br (definida en JS si se necesita en el JS)
        function nl2br(str) {
            if (typeof str !== 'string') return '';
            // Reemplazar saltos de línea con <br>, escapando cualquier HTML existente
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;')
                      .replace(/\n/g, '<br>');
        }


        // Funciones de navegación del menú lateral
        function toggleNav() {
            document.getElementById("navbar").classList.toggle("active");
            document.querySelector("main").classList.toggle("nav-active");
            document.querySelector("footer").classList.toggle("nav-active");
        }

        // Manejo de pestañas
        const crearPasantiaTab = document.getElementById('crearPasantiaTab');
        const listarPasantiasTab = document.getElementById('listarPasantiasTab');
        const crearPasantiaSection = document.getElementById('crearPasantiaSection');
        const listarPasantiasSection = document.getElementById('listarPasantiasSection');

        crearPasantiaTab.addEventListener('click', () => {
            crearPasantiaTab.classList.add('active');
            listarPasantiasTab.classList.remove('active');
            crearPasantiaSection.style.display = 'block';
            listarPasantiasSection.style.display = 'none';
        });

        listarPasantiasTab.addEventListener('click', () => {
            listarPasantiasTab.classList.add('active');
            crearPasantiaTab.classList.remove('active');
            listarPasantiasSection.style.display = 'block';
            crearPasantiaSection.style.display = 'none';
            // Al cambiar a la pestaña de lista, aplicar el filtro inicial
            filtrarPasantias();
        });

        // Búsqueda de estudiantes (Implementado en JS, opera sobre las cards generadas en PHP)
        const estudianteSearch = document.getElementById('estudianteSearch');
        let estudiantesCards = []; // Inicializar array, se llenará en DOMContentLoaded


        estudianteSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            // Seleccionar las cards actuales cada vez, por si se actualizan dinámicamente
            estudiantesCards = document.querySelectorAll('#estudiantesGrid .estudiante-card');

            estudiantesCards.forEach(card => {
                const nombre = (card.dataset.nombre || '').toLowerCase();
                const codigo = (card.dataset.codigo || '').toLowerCase();
                const documento = (card.dataset.documento || '').toLowerCase();
                const email = (card.dataset.email || '').toLowerCase();
                const empresa = (card.dataset.empresa || '').toLowerCase(); // Añadir búsqueda por empresa si es relevante aquí

                if (nombre.includes(searchTerm) ||
                    codigo.includes(searchTerm) ||
                    documento.includes(searchTerm) ||
                    email.includes(searchTerm) ||
                    empresa.includes(searchTerm) // Incluir búsqueda por empresa
                    ) {
                    card.style.display = ''; // Mostrar la tarjeta
                } else {
                    card.style.display = 'none'; // Ocultar la tarjeta
                }
            });

            // Manejar el mensaje de "No se encontraron estudiantes"
            const hayResultadosVisibles = Array.from(estudiantesCards).some(card =>
                card.style.display !== 'none'
            );

            let noEstudiantesMsg = document.querySelector('#estudiantesGrid .no-estudiantes');
            const estudiantesGrid = document.getElementById('estudiantesGrid');

            if (!hayResultadosVisibles) {
                // Si no hay resultados visibles, crear o mostrar el mensaje
                if (!noEstudiantesMsg) {
                    noEstudiantesMsg = document.createElement('div');
                    noEstudiantesMsg.className = 'no-estudiantes';
                    noEstudiantesMsg.textContent = 'No se encontraron estudiantes que coincidan con la búsqueda.';
                    estudiantesGrid.appendChild(noEstudiantesMsg);
                } else {
                    noEstudiantesMsg.style.display = 'block';
                }
            } else {
                 // Si hay resultados visibles, ocultar el mensaje si existe
                 if (noEstudiantesMsg) {
                      noEstudiantesMsg.style.display = 'none';
                 }
                 // Si la lista inicial estaba vacía y hay resultados por alguna actualización, el div original ya no existe
            }
        });


        // Selección de estudiante en el formulario de crear
        function seleccionarEstudiante(estudianteId) {
            // Buscar el estudiante en los datos iniciales
            const estudiante = initialEstudiantesData.find(est => est.id === estudianteId);

            if (!estudiante) {
                console.error("Datos de estudiante no encontrados para ID:", estudianteId);
                alert("Error al seleccionar estudiante. Intente recargar la página.");
                return;
            }

            // Guardar datos del estudiante seleccionado
            estudianteSeleccionado = estudiante; // Guardamos el objeto completo

            // Actualizar el input hidden en el formulario de CREAR
            document.getElementById('estudiante_id_crear_form').value = estudianteSeleccionado.id;

            // Mostrar la información del estudiante seleccionado
            document.getElementById('infoEstudianteNombre').textContent = estudianteSeleccionado.nombre || 'No asignado';
            document.getElementById('infoEstudianteCodigo').textContent = estudianteSeleccionado.codigo_estudiante || 'N/A';
            document.getElementById('infoEstudianteDocumento').textContent = estudianteSeleccionado.documento || 'N/A';
            document.getElementById('infoEstudianteEmail').textContent = estudianteSeleccionado.email || 'N/A';
            document.getElementById('infoEstudianteTelefono').textContent = estudianteSeleccionado.telefono || 'No registrado';
            document.getElementById('infoEstudianteEmpresa').textContent = estudianteSeleccionado.nombre_empresa || 'No registrada'; // Usar nombre_empresa si existe en los datos del estudiante

            // Mostrar la sección de información del estudiante y ocultar la selección
            document.getElementById('selectedEstudianteInfo').classList.add('visible');
            document.getElementById('selectedEstudianteInfo').style.display = 'block';
            document.getElementById('seleccionEstudianteGroup').style.display = 'none';

            // Mostrar las demás secciones del formulario de CREAR
            document.getElementById('datosPasantiaGroup').style.display = 'block';
            document.getElementById('fechasTutorGroup').style.display = 'block';
            document.getElementById('documentosGroup').style.display = 'block';
            document.getElementById('formActions').style.display = 'flex';

            // Si el estudiante tiene nombre_empresa, autocompleta el campo de empresa en el formulario de CREAR
            if (estudianteSeleccionado.nombre_empresa) {
                document.getElementById('empresa').value = estudianteSeleccionado.nombre_empresa;
            } else {
                 // Limpiar el campo de empresa si el estudiante no tiene una asignada
                 document.getElementById('empresa').value = '';
            }
             // Limpiar otros campos del formulario de crear al seleccionar estudiante (excepto empresa si vino precargada)
             document.getElementById('titulo').value = '';
             document.getElementById('descripcion').value = '';
             document.getElementById('direccion_empresa').value = '';
             document.getElementById('contacto_empresa').value = '';
             document.getElementById('supervisor_empresa').value = '';
             document.getElementById('telefono_supervisor').value = '';
             document.getElementById('fecha_inicio').value = '';
             document.getElementById('fecha_fin').value = '';
             document.getElementById('tutor_id_crear').value = ''; // Resetear selector de tutor
             document.getElementById('archivo_documento').value = ''; // Limpiar input file
        }

        function cambiarEstudiante() {
            // Limpiar estudiante seleccionado
            estudianteSeleccionado = null;
            // Limpiar el input hidden en el formulario de CREAR
            document.getElementById('estudiante_id_crear_form').value = '';

            // Ocultar la sección de información del estudiante
            document.getElementById('selectedEstudianteInfo').classList.remove('visible');
            document.getElementById('selectedEstudianteInfo').style.display = 'none';

            // Mostrar la sección de selección de estudiante
            document.getElementById('seleccionEstudianteGroup').style.display = 'block';

            // Ocultar las demás secciones del formulario de CREAR
            document.getElementById('datosPasantiaGroup').style.display = 'none';
            document.getElementById('fechasTutorGroup').style.display = 'none';
            document.getElementById('documentosGroup').style.display = 'none';
            document.getElementById('formActions').style.display = 'none';

            // Limpiar completamente los campos del formulario de crear al cambiar de estudiante
             limpiarFormularioCrearCompleto();

            // Opcional: Restablecer la búsqueda de estudiantes si se limpió
            document.getElementById('estudianteSearch').value = '';
             // Restablecer la visualización de todas las cards si no hay término de búsqueda
             estudiantesCards = document.querySelectorAll('#estudiantesGrid .estudiante-card');
             estudiantesCards.forEach(card => card.style.display = '');
             // Ocultar el mensaje de "No se encontraron estudiantes" si está visible
             let noEstudiantesMsg = document.querySelector('#estudiantesGrid .no-estudiantes');
             if (noEstudiantesMsg) {
                  noEstudiantesMsg.style.display = 'none';
             }

        }

         // Nueva función para limpiar completamente el formulario de crear
         function limpiarFormularioCrearCompleto() {
             document.getElementById('titulo').value = '';
             document.getElementById('descripcion').value = '';
             document.getElementById('empresa').value = '';
             document.getElementById('direccion_empresa').value = '';
             document.getElementById('contacto_empresa').value = '';
             document.getElementById('supervisor_empresa').value = '';
             document.getElementById('telefono_supervisor').value = '';
             document.getElementById('fecha_inicio').value = '';
             document.getElementById('fecha_fin').value = '';
             document.getElementById('tutor_id_crear').value = ''; // Resetear selector de tutor
             document.getElementById('archivo_documento').value = ''; // Limpiar input file
             // El estudiante seleccionado ya se limpió en cambiarEstudiante()
         }

         // Modificar la función limpiarFormulario para que llame a la nueva función completa
         function limpiarFormulario() {
              // Esta función ahora simplemente llamará a la limpieza completa del formulario de crear
              // para asegurar que todos los campos de la pasantía se reseteen,
              // independientemente de si el estudiante tenía empresa precargada o no.
             limpiarFormularioCrearCompleto();
         }


        // Búsqueda y filtrado de pasantías en la tabla
        const searchPasantias = document.getElementById('searchPasantias');
        const filterEstado = document.getElementById('filterEstado');

        let filasPasantias = []; // Inicializar array, se llenará en filtrarPasantias

        function filtrarPasantias() {
            // Seleccionar las filas actuales de la tabla cada vez, por si se actualizan dinámicamente
            filasPasantias = document.querySelectorAll('#tablaPasantias tbody tr');
            const searchTerm = searchPasantias.value.toLowerCase();
            const estado = filterEstado.value;

            let hayResultadosVisibles = false;
            // Seleccionar la fila original "No data" si existe en el DOM
            const noDataRowElement = document.querySelector('#tablaPasantias tbody .no-data-row');

            filasPasantias.forEach(fila => {
                 // Ignorar la fila original "No data" durante el filtro si existe
                 if (fila.classList.contains('no-data-row')) {
                      fila.style.display = 'none'; // Ocultar la fila original "No data"
                      return; // Saltar a la siguiente fila
                 }

                // Obtener los datos de los atributos data-
                const estudiante = (fila.dataset.estudiante || '').toLowerCase();
                const titulo = (fila.dataset.titulo || '').toLowerCase();
                const empresa = (fila.dataset.empresa || '').toLowerCase();
                const estadoFila = (fila.dataset.estado || '').toLowerCase();

                // Verificar si la fila coincide con el término de búsqueda y el filtro de estado
                const matchSearch = estudiante.includes(searchTerm) ||
                                    titulo.includes(searchTerm) ||
                                    empresa.includes(searchTerm); // Buscar en nombre de estudiante, título y empresa

                const matchEstado = estado === '' || estadoFila === estado;

                // Mostrar u ocultar la fila
                if (matchSearch && matchEstado) {
                    fila.style.display = ''; // Mostrar la fila
                    hayResultadosVisibles = true; // Hay al menos un resultado visible
                } else {
                    fila.style.display = 'none'; // Ocultar la fila
                }
            });

            // Manejar el mensaje "No se encontraron pasantías que coincidan con la búsqueda"
            let noResultsRow = document.querySelector('#tablaPasantias tbody tr.no-results');
            const tbody = document.querySelector('#tablaPasantias tbody');

            if (!hayResultadosVisibles) {
                // Si no hay resultados visibles, crear o mostrar el mensaje de "No se encontraron pasantías"
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.className = 'no-results'; // Añadir una clase para identificarla fácilmente
                    noResultsRow.innerHTML = '<td colspan="9" class="no-data">No se encontraron pasantías que coincidan con la búsqueda.</td>'; // colspan="9" si tienes 9 columnas
                    tbody.appendChild(noResultsRow); // Añadirla al cuerpo de la tabla
                }
                noResultsRow.style.display = ''; // Asegurarse de que el mensaje de no resultados esté visible
            } else {
                 // Si hay resultados visibles, ocultar el mensaje de no resultados si existe
                 if (noResultsRow) {
                      noResultsRow.style.display = 'none';
                 }
                 // La fila original "No data" ya se ocultó al inicio de la función si existía.
            }

             // Mostrar la fila original "No data" SOLAMENTE si la lista inicial estaba vacía
             // Y no hay término de búsqueda ni filtro de estado aplicado.
             // Si initialPasantiasData está vacío Y searchTerm está vacío Y estado está vacío,
             // y si la fila original "No data" existe, asegurarnos de que esté visible.
            if (initialPasantiasData.length === 0 && searchTerm === '' && estado === '' && noDataRowElement) {
                 noDataRowElement.style.display = '';
            }
        }


        // Funciones para modales (usan fetch API para interactuar con los endpoints PHP '?api=...')

        // Variable global para almacenar los datos de la pasantía vista/editada
        // ELIMINADA DECLARACIÓN DUPLICADA: let pasantiaActual = null;


        function verPasantia(pasantiaId) {
            // Realizar la llamada a la API para obtener detalles completos
            fetch(`?api=details&id=${pasantiaId}`)
                .then(response => {
                     // Verificar si la respuesta fue exitosa (código 200-299)
                     if (!response.ok) {
                         // Si no fue exitosa, intentar leer el cuerpo de la respuesta como JSON para obtener el error
                         return response.json().then(err => {
                              // Lanzar un error con el mensaje proporcionado por el backend o un mensaje genérico
                              throw new Error(err.error || `Error HTTP! estado: ${response.status}`);
                         }).catch(() => {
                             // Si incluso leer el JSON del error falla, lanzar un error genérico
                             throw new Error(`Error HTTP! estado: ${response.status}`);
                         });
                     }
                     // Si la respuesta fue exitosa, continuar procesando el cuerpo como JSON de datos
                    return response.json();
                })
                .then(pasantia => {
                    // Guarda la pasantía obtenida para usarla en editarDesdeVer
                    pasantiaActual = pasantia;

                    // Construir el HTML con los detalles usando los datos de la API (ya sanitizados en PHP)
                    let html = `
                        <div class="pasantia-detalle">
                            <div class="seccion">
                                <h3>Datos del Estudiante</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Nombre:</span>
                                        <span class="valor">${pasantia.estudiante_nombre || 'No asignado'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Código:</span>
                                        <span class="valor">${pasantia.codigo_estudiante || 'N/A'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Email:</span>
                                        <span class="valor">${pasantia.estudiante_email || 'N/A'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Documento:</span>
                                        <span class="valor">${pasantia.estudiante_documento || 'N/A'}</span>
                                    </div>
                                     <div class="info-item">
                                        <span class="label">Teléfono:</span>
                                        <span class="valor">${pasantia.estudiante_telefono || 'No registrado'}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="seccion">
                                <h3>Datos de la Pasantía</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Título:</span>
                                        <span class="valor">${pasantia.titulo || 'Sin título'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Empresa:</span>
                                        <span class="valor">${pasantia.empresa || 'No especificada'}</span>
                                    </div>
                                     <div class="info-item">
                                        <span class="label">Dirección:</span>
                                        <span class="valor">${pasantia.direccion_empresa || 'No especificada'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Contacto:</span>
                                        <span class="valor">${pasantia.contacto_empresa || 'No especificado'}</span>
                                    </div>
                                     <div class="info-item">
                                        <span class="label">Supervisor:</span>
                                        <span class="valor">${pasantia.supervisor_empresa || 'No especificado'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Teléfono Supervisor:</span>
                                        <span class="valor">${pasantia.telefono_supervisor || 'No especificado'}</span>
                                    </div>
                                </div>

                                <div class="descripcion">
                                    <h4>Descripción:</h4>
                                    <div class="texto-descripcion">${pasantia.descripcion ? nl2br(pasantia.descripcion) : 'Sin descripción'}</div>
                                </div>
                            </div>

                            <div class="seccion">
                                <h3>Estado y Fechas</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Estado:</span>
                                        <span class="valor">
                                            <span class="badge estado-${pasantia.estado || 'pendiente'}">
                                                ${ucfirst(pasantia.estado ? pasantia.estado.replace('_', ' ') : 'pendiente')}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Fecha de Inicio:</span>
                                        <span class="valor">${pasantia.fecha_inicio_formateada}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Fecha de Fin:</span>
                                        <span class="valor">${pasantia.fecha_fin_formateada}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Fecha de Creación:</span>
                                        <span class="valor">${pasantia.fecha_creacion_formateada}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="seccion">
                                <h3>Tutor Asignado</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Nombre:</span>
                                        <span class="valor">${pasantia.tutor_nombre || 'No asignado'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Email:</span>
                                        <span class="valor">${pasantia.tutor_email || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="seccion">
                                <h3>Documentos</h3>
                                <div class="documentos-lista">
                                    ${pasantia.archivo_documento ?
                                        `<div class="documento">
                                             <span class="label">Documento del Estudiante:</span>
                                             <a href="/uploads/pasantias/${pasantia.archivo_documento}" target="_blank" class="btn-documento">
                                                 Ver documento
                                             </a>
                                         </div>` :
                                        '<div class="documento">No hay documento del estudiante</div>'
                                    }
                                    </div>
                            </div>
                        </div>
                    `; // Fin del HTML a construir

                    document.getElementById('detallesPasantia').innerHTML = html;
                    abrirModal('modalVerPasantia');
                })
                .catch(error => {
                    // Manejar errores de la API o de la red
                    console.error('Error al cargar detalles:', error);
                    alert('Error al cargar los detalles de la pasantía: ' + error.message); // Muestra el mensaje de error específico
                });
        }


        function editarPasantia(pasantiaId) {
             // Realizar la llamada a la API para obtener detalles completos
            fetch(`?api=details&id=${pasantiaId}`)
                .then(response => {
                     // Verificar si la respuesta fue exitosa (código 200-299)
                     if (!response.ok) {
                         // Si no fue exitosa, intentar leer el cuerpo de la respuesta como JSON para obtener el error
                         return response.json().then(err => {
                              // Lanzar un error con el mensaje proporcionado por el backend o un mensaje genérico
                              throw new Error(err.error || `Error HTTP! estado: ${response.status}`);
                         }).catch(() => {
                             // Si incluso leer el JSON del error falla, lanzar un error genérico
                             throw new Error(`Error HTTP! estado: ${response.status}`);
                         });
                     }
                     // Si la respuesta fue exitosa, continuar procesando el cuerpo como JSON de datos
                    return response.json();
                })
                .then(pasantia => {
                    pasantiaActual = pasantia; // Guarda la pasantía actual

                    // Llenar el formulario de edición con los datos (ya sanitizados en PHP)
                    document.getElementById('edit_pasantia_id').value = pasantia.id;
                    document.getElementById('eliminar_pasantia_id').value = pasantia.id; // Para el modal de eliminación

                    // Datos del estudiante (solo lectura)
                    document.getElementById('estudiante_nombre').textContent = pasantia.estudiante_nombre || 'No asignado';
                    document.getElementById('estudiante_codigo').textContent = pasantia.codigo_estudiante || 'N/A';
                    document.getElementById('estudiante_email').textContent = pasantia.estudiante_email || 'N/A';
                    document.getElementById('estudiante_documento').textContent = pasantia.estudiante_documento || 'N/A';

                    // Datos de la pasantía (editables)
                    document.getElementById('edit_titulo').value = pasantia.titulo || '';
                    document.getElementById('edit_descripcion').value = pasantia.descripcion || '';
                    document.getElementById('edit_empresa').value = pasantia.empresa || '';
                    document.getElementById('edit_direccion_empresa').value = pasantia.direccion_empresa || '';
                    document.getElementById('edit_contacto_empresa').value = pasantia.contacto_empresa || '';
                    document.getElementById('edit_supervisor_empresa').value = pasantia.supervisor_empresa || '';
                    document.getElementById('edit_telefono_supervisor').value = pasantia.telefono_supervisor || '';

                    // Fechas y estado
                    // Formato AAAA-MM-DD necesario para input type="date"
                    document.getElementById('edit_fecha_inicio').value = pasantia.fecha_inicio || '';
                    document.getElementById('edit_fecha_fin').value = pasantia.fecha_fin || '';
                    document.getElementById('edit_estado').value = pasantia.estado || 'pendiente';

                    // Tutor - Buscar en los datos iniciales de tutores y seleccionar la opción
                    const tutorSelectEdit = document.getElementById('edit_tutor_id');
                     // Limpiar selecciones previas
                     tutorSelectEdit.value = '';
                    // Seleccionar el tutor correcto si existe
                    if (pasantia.tutor_id !== null && pasantia.tutor_id !== undefined) {
                         // Asegurarse de que pasantia.tutor_id sea un número para la comparación
                         const tutorIdNum = parseInt(pasantia.tutor_id);
                         if (!isNaN(tutorIdNum)) {
                              const tutorOption = Array.from(tutorSelectEdit.options).find(option => parseInt(option.value) === tutorIdNum);
                              if (tutorOption) {
                                   tutorSelectEdit.value = pasantia.tutor_id;
                              } else {
                                   console.warn("Tutor con ID", pasantia.tutor_id, "no encontrado en la lista de tutores disponibles.");
                                   // Opcional: mantener la opción vacía si el tutor no está en la lista actual
                                   tutorSelectEdit.value = '';
                              }
                         } else {
                              console.warn("ID de tutor no numérico recibido:", pasantia.tutor_id);
                              tutorSelectEdit.value = ''; // Asegurar que se seleccione la opción vacía
                         }
                    }


                    // Documentos actuales (solo visualización del documento principal)
                    const editDocumentoActualDiv = document.getElementById('edit_documento_actual');
                    const editNuevoArchivoInput = document.getElementById('edit_nuevo_archivo');
                    const deleteArchivoCheckbox = document.getElementById('delete_archivo_documento');

                    // Mostrar el enlace al documento actual o el mensaje
                    if (pasantia.archivo_documento) {
                        editDocumentoActualDiv.innerHTML = `<a href="/uploads/pasantias/${pasantia.archivo_documento}" target="_blank" class="btn-documento">Ver documento principal actual</a>`;
                         // Habilitar la opción de eliminar y subir uno nuevo si hay un archivo actual
                         deleteArchivoCheckbox.disabled = false;
                         deleteArchivoCheckbox.checked = false; // Asegurarse de que no esté marcado por defecto al abrir el modal
                         editNuevoArchivoInput.disabled = false;
                         editNuevoArchivoInput.value = ''; // Limpiar el input file
                    } else {
                        editDocumentoActualDiv.innerHTML = '<p>No hay documento principal adjunto.</p>';
                         // Deshabilitar la opción de eliminar si no hay archivo actual
                         deleteArchivoCheckbox.disabled = true;
                         deleteArchivoCheckbox.checked = false;
                         editNuevoArchivoInput.disabled = false; // Permitir subir uno nuevo incluso si no hay
                         editNuevoArchivoInput.value = ''; // Limpiar el input file
                    }

                    // Opcional: Añadir listeners para deshabilitar campos mutuamente excluyentes (si sube uno nuevo, desmarca eliminar y viceversa)
                     deleteArchivoCheckbox.onchange = function() {
                          if (this.checked) {
                               editNuevoArchivoInput.disabled = true;
                               editNuevoArchivoInput.value = ''; // Limpiar el input file si se marca eliminar
                          } else {
                               editNuevoArchivoInput.disabled = false;
                          }
                     };

                     editNuevoArchivoInput.onchange = function() {
                          if (this.value) {
                               deleteArchivoCheckbox.disabled = true;
                               deleteArchivoCheckbox.checked = false; // Desmarcar eliminar si se selecciona un nuevo archivo
                          } else {
                               // Si se limpia el input file, re-habilitar checkbox solo si había un archivo actual
                               deleteArchivoCheckbox.disabled = !pasantia.archivo_documento;
                          }
                     };
                    // ELIMINADO: Lógica para mostrar documento adicional en el modal de edición

                    abrirModal('modalEditarPasantia');
                })
                .catch(error => {
                    // Manejar errores de la API o de la red
                    console.error('Error al cargar datos de edición:', error);
                    alert('Error al cargar los datos de la pasantía para edición: ' + error.message); // Muestra el mensaje de error específico
                });
        }

        // Función para pasar del modal de ver al modal de editar
        function editarPasantiaDesdeVer() {
            cerrarModal('modalVerPasantia'); // Cerrar el modal actual de ver
            // Usar la pasantía cargada previamente (pasantiaActual)
            if (pasantiaActual && pasantiaActual.id) {
                // Llamar a editarPasantia con el ID de la pasantía actual
                editarPasantia(pasantiaActual.id);
            } else {
                console.error("No hay pasantía actual seleccionada para editar.");
                alert("Error: No se pudo identificar la pasantía a editar.");
            }
        }

        // Función para abrir el modal de confirmar eliminación desde la tabla
        function confirmarEliminarPasantia(pasantiaId) {
             // Establecer el ID de la pasantía a eliminar en el formulario del modal
            document.getElementById('eliminar_pasantia_id').value = pasantiaId;
             // Almacenar el ID en pasantiaActual por si se necesita en confirmarEliminarPasantiaModal() (aunque el form ya tiene el ID)
             pasantiaActual = { id: pasantiaId }; // Objeto simple con solo el ID
            abrirModal('modalConfirmarEliminar');
        }

        // Función para abrir el modal de confirmar eliminación desde el modal de editar
        function confirmarEliminarPasantiaModal() {
            // Usar la pasantía cargada previamente (pasantiaActual)
            if (pasantiaActual && pasantiaActual.id) {
                // Establecer el ID de la pasantía a eliminar en el formulario del modal
                document.getElementById('eliminar_pasantia_id').value = pasantiaActual.id;
                cerrarModal('modalEditarPasantia'); // Cerrar el modal de edición
                abrirModal('modalConfirmarEliminar'); // Abrir el modal de confirmación
            } else {
                console.error("No hay pasantía actual seleccionada para eliminar.");
                alert("Error: No se pudo identificar la pasantía a eliminar.");
            }
        }

        // Funciones genéricas para abrir y cerrar modales
        function abrirModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                // Evitar scroll del cuerpo mientras el modal está abierto
                document.body.style.overflow = 'hidden';
            }
        }

        function cerrarModal(modalId) {
             const modal = document.getElementById(modalId);
             if (modal) {
                  modal.style.display = 'none';
                  // Restaurar scroll del cuerpo
                  document.body.style.overflow = 'auto';
             }
        }

        // Cerrar modal al hacer click fuera del contenido
        window.onclick = function(event) {
            // Verificar si el elemento clickeado es el fondo oscuro del modal (el div con clase 'modal')
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto'; // Restaurar scroll
            }
        }

        // Validaciones del formulario de Crear Pasantía antes de enviar (cliente-side)
        document.getElementById('formCrearPasantia').addEventListener('submit', function(e) {
             const estudianteIdInput = document.getElementById('estudiante_id_crear_form');
             // Validar que se haya seleccionado un estudiante
             if (!estudianteIdInput || !estudianteIdInput.value) {
                 e.preventDefault(); // Prevenir el envío del formulario
                 alert('Debe seleccionar un estudiante para registrar la pasantía.');
                  // Opcional: cambiar a la pestaña de crear y hacer scroll si no está visible
                 crearPasantiaTab.click();
                 document.getElementById('seleccionEstudianteGroup').scrollIntoView({ behavior: 'smooth' });
                 return; // Detener la ejecución
             }

             // Validar fechas (si los campos están presentes)
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            if (fechaInicioInput && fechaFinInput) {
                 // Convertir los valores de fecha a objetos Date para comparar
                 const fechaInicio = new Date(fechaInicioInput.value);
                 const fechaFin = new Date(fechaFinInput.value);

                 // Comparar fechas. Asegurarse de que ambas tengan valor antes de comparar objetos Date.
                 if (fechaInicioInput.value && fechaFinInput.value && (fechaFin < fechaInicio)) {
                      e.preventDefault();
                      alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                 }
            } else {
                 console.warn("Inputs de fecha (inicio o fin) no encontrados en el formulario de creación.");
            }
             // Validaciones de campos requeridos adicionales si es necesario (ej. título, empresa)
             const tituloInput = document.getElementById('titulo');
             const empresaInput = document.getElementById('empresa');

             if (!tituloInput || !tituloInput.value.trim()) {
                 e.preventDefault();
                 alert('El campo "Título de la Pasantía" es obligatorio.');
                 return;
             }
             if (!empresaInput || !empresaInput.value.trim()) {
                 e.preventDefault();
                 alert('El campo "Empresa" es obligatorio.');
                 return;
             }

            // Si todas las validaciones pasan, el formulario se enviará normalmente
        });


         // Validaciones del formulario de Editar Pasantía antes de enviar (cliente-side)
         document.getElementById('formEditarPasantia').addEventListener('submit', function(e) {
             // Validar fechas (si los campos están presentes y llenos)
             const fechaInicioInput = document.getElementById('edit_fecha_inicio');
             const fechaFinInput = document.getElementById('edit_fecha_fin');

             if (fechaInicioInput && fechaFinInput) {
                  // Convertir los valores de fecha a objetos Date para comparar
                  const fechaInicio = new Date(fechaInicioInput.value);
                  const fechaFin = new Date(fechaFinInput.value);

                  // Comparar fechas solo si ambos campos tienen valor
                  if (fechaInicioInput.value && fechaFinInput.value && (fechaFin < fechaInicio)) {
                       e.preventDefault();
                       alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                  }
             } else {
                  console.warn("Inputs de fecha (inicio o fin) no encontrados en el formulario de edición.");
             }

              // Validar campos requeridos adicionales en el formulario de edición
              const editTitulo = document.getElementById('edit_titulo');
              const editEmpresa = document.getElementById('edit_empresa');
              const editEstado = document.getElementById('edit_estado'); // El estado también es requerido

              if (!editTitulo || !editTitulo.value.trim()) {
                  e.preventDefault();
                  alert('El campo "Título" es obligatorio.');
                  return;
              }
              if (!editEmpresa || !editEmpresa.value.trim()) {
                   e.preventDefault();
                   alert('El campo "Empresa" es obligatorio.');
                   return;
              }
              if (!editEstado || !editEstado.value.trim()) {
                   e.preventDefault();
                   alert('El campo "Estado" es obligatorio.');
                   return;
              }

             // Si todas las validaciones pasan, el formulario se enviará normalmente
         });


        // Código de inicialización que se ejecuta cuando el DOM está completamente cargado
        document.addEventListener('DOMContentLoaded', function() {
             // Asignar los elementos de las cards de estudiantes y filas de pasantías
             // Esto debe ocurrir DESPUÉS de que el DOM esté listo
             estudiantesCards = document.querySelectorAll('#estudiantesGrid .estudiante-card');
             filasPasantias = document.querySelectorAll('#tablaPasantias tbody tr');

             // Añadir listeners para búsqueda y filtrado de pasantías
             searchPasantias.addEventListener('input', filtrarPasantias);
             filterEstado.addEventListener('change', filtrarPasantias);

             // Aplicar el filtro inicial al cargar la página para mostrar solo los resultados que cumplen con los filtros por defecto (ninguno)
             filtrarPasantias();

             // Ocultar los mensajes de éxito o error después de un tiempo
             const mensajes = document.querySelectorAll('.mensaje');
             if (mensajes.length > 0) {
                 setTimeout(() => {
                      mensajes.forEach(msg => {
                           msg.style.opacity = '0'; // Iniciar la transición de opacidad
                           // Esperar a que la transición termine antes de ocultar el elemento
                           setTimeout(() => msg.style.display = 'none', 500); // 500ms es la duración común de una transición suave
                      });
                 }, 5000); // 5 segundos antes de que empiece la transición de opacidad
             }


             // Configurar la visualización inicial de las secciones del formulario de crear
             // Ocultar todo excepto la sección de selección de estudiante al cargar
             document.getElementById('seleccionEstudianteGroup').style.display = 'block'; // Mostrar solo esta
             document.getElementById('selectedEstudianteInfo').style.display = 'none'; // Ocultar info del seleccionado
             document.getElementById('datosPasantiaGroup').style.display = 'none'; // Ocultar datos de la pasantía
             document.getElementById('fechasTutorGroup').style.display = 'none'; // Ocultar fechas y tutor
             document.getElementById('documentosGroup').style.display = 'none'; // Ocultar documentos
             document.getElementById('formActions').style.display = 'none'; // Ocultar botones de acción

             // Asegurarse de que el campo hidden del estudiante en el formulario de crear esté vacío al cargar
             document.getElementById('crearPasantiaSection').querySelector('input[name="estudiante_id"]').value = '';

             // Opcional: Si quieres que al recargar la página se muestre la pestaña de listar por defecto
             // listarPasantiasTab.click();

        });


    </script>
</body>
</html>