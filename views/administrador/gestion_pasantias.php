<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Habilitar reporte de errores para depuración (¡deshabilitar en producción!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para manejar valores nulos y sanitizar para HTML (o salida JSON)
function htmlSafe($str) {
    return $str !== null ? htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8') : '';
}

// Función para validar formato de fecha Y-M-D
function isValidDate($dateString) {
    $date = DateTime::createFromFormat('Y-m-d', $dateString);
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

            // Obtener detalles de la pasantía
            $stmt = $conexion->prepare("
                SELECT p.*,
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
            $stmt->execute([$pasantiaId]);
            $pasantia = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pasantia) {
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }

            // Formatear fechas y sanitizar campos para la visualización/edición
             // Sanitizar campos de texto y fechas antes de enviarlos en JSON
             $response = [
                'id' => (int)$pasantia['id'],
                'titulo' => htmlSafe($pasantia['titulo'] ?? 'Sin título'),
                'descripcion' => htmlSafe($pasantia['descripcion'] ?? ''),
                'empresa' => htmlSafe($pasantia['empresa'] ?? 'No especificada'),
                'direccion_empresa' => htmlSafe($pasantia['direccion_empresa'] ?? ''),
                'contacto_empresa' => htmlSafe($pasantia['contacto_empresa'] ?? ''),
                'supervisor_empresa' => htmlSafe($pasantia['supervisor_empresa'] ?? ''),
                'telefono_supervisor' => htmlSafe($pasantia['telefono_supervisor'] ?? ''),
                'fecha_inicio' => htmlSafe($pasantia['fecha_inicio']),
                'fecha_fin' => htmlSafe($pasantia['fecha_fin']),
                'fecha_inicio_formateada' => !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida',
                'fecha_fin_formateada' => !empty($pasantia['fecha_fin']) ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No establecida',
                'estado' => htmlSafe($pasantia['estado'] ?? 'pendiente'),
                'estado_formateado' => ucfirst(str_replace('_', ' ', htmlSafe($pasantia['estado'] ?? 'pendiente'))),
                'estudiante_id' => $pasantia['estudiante_id'] !== null ? (int)$pasantia['estudiante_id'] : null,
                'estudiante_nombre' => htmlSafe($pasantia['estudiante_nombre'] ?? 'No asignado'),
                'codigo_estudiante' => htmlSafe($pasantia['codigo_estudiante'] ?? 'N/A'),
                'estudiante_email' => htmlSafe($pasantia['estudiante_email'] ?? 'N/A'),
                'estudiante_documento' => htmlSafe($pasantia['estudiante_documento'] ?? 'N/A'),
                'estudiante_telefono' => htmlSafe($pasantia['estudiante_telefono'] ?? 'N/A'),
                'tutor_id' => $pasantia['tutor_id'] !== null ? (int)$pasantia['tutor_id'] : null,
                'tutor_nombre' => htmlSafe($pasantia['tutor_nombre'] ?? 'No asignado'),
                'tutor_email' => htmlSafe($pasantia['tutor_email'] ?? 'N/A'),
                'archivo_documento' => htmlSafe($pasantia['archivo_documento']),
                'documento_adicional' => htmlSafe($pasantia['documento_adicional']),
                'fecha_creacion' => htmlSafe($pasantia['fecha_creacion']),
                'fecha_creacion_formateada' => date('d/m/Y H:i', strtotime($pasantia['fecha_creacion']))
            ];


            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("API Error (details): " . $e->getMessage());
            echo json_encode(['error' => 'Error al obtener los detalles de la pasantía: ' . $e->getMessage()]);
        }
        exit; // Salir después de la respuesta API
    }

    // API para obtener tutores disponibles
    if ($_GET['api'] === 'tutores') {
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
            $stmt->execute();
            $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);

             // Sanitizar datos antes de enviar
             $tutores_sanitized = array_map(function($tutor) {
                  return [
                       'id' => (int)$tutor['id'], // Asegurarse que es INT
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
                    WHERE estado != 'finalizada' AND estado != 'rechazada' -- Considerar otros estados si es necesario
                )
                ORDER BY nombre
            ");
            $stmt->execute();
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

             // Sanitizar datos antes de enviar
             $estudiantes_sanitized = array_map(function($est) {
                  return [
                       'id' => (int)$est['id'], // Asegurarse que es INT
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
                WHERE id = ? AND rol = 'estudiante' AND opcion_grado = 'pasantia' -- Asegurarse que es estudiante de pasantía
            ");
            $stmt->execute([$estudianteId]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estudiante) {
                http_response_code(404);
                echo json_encode(['error' => 'Estudiante no encontrado o no elegible para pasantía']);
                exit;
            }

             // Sanitizar datos antes de enviar
             $response = [
                  'id' => (int)$estudiante['id'], // Asegurarse que es INT
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

    // API para actualizar pasantía (ahora usa transacción)
    if ($_GET['api'] === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
             $conexion->beginTransaction(); // Iniciar transacción

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                 $conexion->rollBack(); // Revertir
                exit;
            }

             $pasantiaId = filter_var($data['id'], FILTER_VALIDATE_INT);
             if ($pasantiaId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de pasantía inválido']);
                   $conexion->rollBack(); // Revertir
                  exit;
             }


            // Construir la consulta según los campos a actualizar
            $campos = [];
            $valores = [];

            // Definir campos permitidos y su tipo de sanitización/validación si es necesario
            $camposPermitidos = [
                'titulo' => 'string',
                'descripcion' => 'string',
                'empresa' => 'string',
                'direccion_empresa' => 'string',
                'contacto_empresa' => 'string',
                'supervisor_empresa' => 'string',
                'telefono_supervisor' => 'string',
                'fecha_inicio' => 'date', // Añadir validación de fecha
                'fecha_fin' => 'date', // Añadir validación de fecha
                'estado' => 'string', // Añadir validación de estado
                'tutor_id' => 'int_or_null' // Añadir validación INT o NULL
            ];

            foreach ($camposPermitidos as $campo => $tipo) {
                if (isset($data[$campo])) {
                     $valor = $data[$campo];

                     // Validar y sanitizar según el tipo
                     switch ($tipo) {
                          case 'string':
                               // La sanitización básica ya se hace en htmlSafe, pero para DB directa no es estrictamente necesaria aquí
                               $valores[] = $valor;
                               break;
                          case 'date':
                               if ($valor !== null && !empty($valor) && !isValidDate($valor)) {
                                    $conexion->rollBack();
                                    http_response_code(400);
                                    echo json_encode(['error' => "Fecha inválida para el campo {$campo}"]);
                                    exit;
                               }
                               $valores[] = empty($valor) ? null : $valor; // Permite fechas nulas/vacías
                               break;
                          case 'int_or_null':
                               $validated_val = filter_var($valor, FILTER_VALIDATE_INT);
                               if ($valor !== null && $valor !== '' && $validated_val === false) {
                                     $conexion->rollBack();
                                     http_response_code(400);
                                     echo json_encode(['error' => "Valor inválido para el campo {$campo}"]);
                                     exit;
                               }
                               $valores[] = ($valor === '' || $valor === null) ? null : $validated_val; // Permite INT o NULL
                               break;
                          // Puedes añadir más tipos de validación/sanitización si es necesario
                          default:
                               $valores[] = $valor;
                     }
                    $campos[] = "`{$campo}` = ?"; // Usar comillas inversas por si el nombre del campo es una palabra reservada
                }
            }

            if (empty($campos)) {
                 $conexion->rollBack(); // Revertir
                http_response_code(400);
                echo json_encode(['error' => 'No se proporcionaron campos válidos para actualizar']);
                exit;
            }

            $valores[] = $pasantiaId; // Agregar el ID al final para la cláusula WHERE

            $sql = "UPDATE pasantias SET " . implode(", ", $campos) . " WHERE id = ?";

            $stmt = $conexion->prepare($sql);
            if (!$stmt->execute($valores)) {
                 $conexion->rollBack(); // Revertir si falla la ejecución
                 error_log("API Error (actualizar) DB Execute Error: " . $stmt->errorInfo()[2]);
                 throw new Exception("Error al actualizar la pasantía en la base de datos.");
            }

            // Confirmar transacción
             $conexion->commit();

             // Opcional: Verificar rowCount() > 0 si quieres asegurarte de que se hizo un cambio
             // if ($stmt->rowCount() === 0) { ... error o mensaje de "sin cambios" ... }


            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía actualizada correctamente'
            ]);

        } catch (Exception $e) {
            if (isset($conexion) && $conexion->inTransaction()) { $conexion->rollBack(); } // Revertir transacción en caso de error
            http_response_code(500);
            error_log("API Error (actualizar): " . $e->getMessage());
            echo json_encode(['error' => 'Error al actualizar la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }

    // API para eliminar pasantía (ahora usa transacción)
    if ($_GET['api'] === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
             $conexion->beginTransaction(); // Iniciar transacción

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                 $conexion->rollBack(); // Revertir
                exit;
            }

            $pasantiaId = filter_var($data['id'], FILTER_VALIDATE_INT);
             if ($pasantiaId === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'ID de pasantía inválido']);
                   $conexion->rollBack(); // Revertir
                  exit;
             }

             // Opcional: Eliminar archivo asociado antes de eliminar la pasantía
             $stmt_file = $conexion->prepare("SELECT archivo_documento, documento_adicional FROM pasantias WHERE id = ? FOR UPDATE"); // Bloquear para asegurar que el archivo existe antes de eliminar
             $stmt_file->execute([$pasantiaId]);
             $archivos = $stmt_file->fetch(PDO::FETCH_ASSOC);

             if ($archivos) {
                  $files_to_delete = [];
                  if (!empty($archivos['archivo_documento'])) $files_to_delete[] = $archivos['archivo_documento'];
                  if (!empty($archivos['documento_adicional'])) $files_to_delete[] = $archivos['documento_adicional'];

                  foreach ($files_to_delete as $file) {
                      $filePath = __DIR__ . '/../../uploads/pasantias/' . $file;
                      if (file_exists($filePath)) {
                           unlink($filePath); // Elimina el archivo físico
                      } else {
                          error_log("Advertencia: Archivo de pasantía no encontrado para eliminar: " . $filePath);
                      }
                  }
             }


            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
            if (!$stmt->execute([$pasantiaId])) {
                 $conexion->rollBack(); // Revertir si falla la ejecución
                  error_log("API Error (eliminar) DB Execute Error: " . $stmt->errorInfo()[2]);
                 throw new Exception("Error al eliminar la pasantía de la base de datos.");
            }

            if ($stmt->rowCount() === 0) {
                 $conexion->rollBack(); // Revertir si no se encontró la pasantía (aunque el error 404 también aplica)
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }

             $conexion->commit(); // Confirmar transacción


            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía eliminada correctamente'
            ]);

        } catch (Exception $e) {
             if (isset($conexion) && $conexion->inTransaction()) { $conexion->rollBack(); } // Revertir transacción en caso de error
            http_response_code(500);
            error_log("API Error (eliminar): " . $e->getMessage());
            echo json_encode(['error' => 'Error al eliminar la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }

    // Si llegamos aquí, la API solicitada no existe
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint de API no encontrado']);
    exit;
}

// --- Fin Manejo de solicitudes API ---


// --- Procesar formularios (Acciones POST sin API) ---
// Estas acciones son manejadas directamente por la página (no por JS fetch a un API endpoint)
// Inicializar variables si no se han inicializado ya
$mensaje = $mensaje ?? '';
$error = $error ?? '';
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
$pasantias = $pasantiasQuery ? $pasantiasQuery->fetchAll(PDO::FETCH_ASSOC) : []; // <-- Aproximadamente la línea donde ocurre el fetch.


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        // Iniciar transacción para las acciones de formulario POST
        $conexion->beginTransaction();

        if ($_POST['accion'] === 'crear_pasantia') {
            // Validar y sanitizar datos de entrada
            $estudiante_id = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);
            $pasantia_id = filter_var($_POST['pasantia_id'] ?? 0, FILTER_VALIDATE_INT);
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


            // Validaciones obligatorias
            if ($estudiante_id !== null && $estudiante_id !== false) {
                $stmt = $conexion->prepare("
                SELECT p.*,
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
                $stmt->execute([$estudiante_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("El estudiante seleccionado no es válido o no está disponible.");
                }
            } else {
                $estudiante_id = null; // Permitir pasantías sin estudiante asignado
            }

             // Validar formatos de fecha
             if (!isValidDate($fecha_inicio) || !isValidDate($fecha_fin)) {
                 throw new Exception("Las fechas de inicio y fin deben tener un formato de fecha válido.");
             }
             if (new DateTime($fecha_fin) < new DateTime($fecha_inicio)) {
                  throw new Exception("La fecha de fin no puede ser anterior a la fecha de inicio.");
             }

             // Validar Tutor ID si se envió
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

                 // Validar tipo de archivo
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                     $conexion->rollBack(); // Revertir antes de lanzar excepción
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx).");
                }

                 // Validar tamaño del archivo (ej: 10MB)
                 $max_size = 10 * 1024 * 1024; // 10 MB
                 if ($archivo_size > $max_size) {
                      $conexion->rollBack(); // Revertir
                      throw new Exception("El tamaño del archivo excede el límite permitido (10MB).");
                 }


                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;

                if (!is_dir(dirname($ruta_destino))) {
                     // Asegurarse que el directorio de subida existe
                    mkdir(dirname($ruta_destino), 0777, true); // Permisos amplios para prueba, ajustar en prod
                }

                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_documento = $archivo_nuevo_nombre;
                } else {
                     $conexion->rollBack(); // Revertir si falla la subida
                    throw new Exception("Error al mover el archivo subido. Intente nuevamente.");
                }
            } elseif (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Manejar otros errores de subida de archivo (ej: tamaño máximo excedido por php.ini)
                 $conexion->rollBack();
                 $phpFileUploadErrors = array(
                    1 => 'El archivo subido supera la directiva upload_max_filesize en php.ini',
                    2 => 'El archivo subido supera la directiva MAX_FILE_SIZE especificada en el formulario HTML.',
                    3 => 'El archivo subido solo se subió parcialmente.',
                    4 => 'No se subió ningún archivo.', // Este código es para NO_FILE, pero ya lo manejamos arriba
                    6 => 'Falta una carpeta temporal.',
                    7 => 'No se pudo escribir el archivo en el disco.',
                    8 => 'Una extensión de PHP detuvo la carga del archivo.',
                 );
                 $error_code = $_FILES['archivo_documento']['error'];
                 $error_message = $phpFileUploadErrors[$error_code] ?? 'Error de subida desconocido';
                 throw new Exception("Error en la subida del archivo: " . $error_message);
            }


            // Insertar pasantía usando sentencia preparada
            $stmt = $conexion->prepare("
    INSERT INTO pasantias (
        estudiante_id, titulo, descripcion, empresa, direccion_empresa,
        contacto_empresa, supervisor_empresa, telefono_supervisor,
        fecha_inicio, fecha_fin, estado, tutor_id, archivo_documento
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)
");
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

if ($execute_success) {
    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Pasantía registrada exitosamente']);
} else {
    $conexion->rollBack();
    throw new Exception("Error al registrar la pasantía.");
}

            // Confirmar transacción si todo fue exitoso
            $conexion->commit();

            $mensaje = "Pasantía creada exitosamente";

        } elseif ($_POST['accion'] === 'actualizar_pasantia') {
            // Validar y sanitizar datos de entrada
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

            // Validaciones obligatorias y de formato
            if ($pasantia_id <= 0) {
                throw new Exception("ID de pasantía no válido.");
            }

             // Validar formatos de fecha si no están vacíos
             if (!empty($fecha_inicio) && !isValidDate($fecha_inicio)) {
                  throw new Exception("Formato de fecha de inicio inválido.");
             }
             if (!empty($fecha_fin) && !isValidDate($fecha_fin)) {
                  throw new Exception("Formato de fecha de fin inválido.");
             }
             if (!empty($fecha_inicio) && !empty($fecha_fin) && (new DateTime($fecha_fin) < new DateTime($fecha_inicio))) {
                  throw new Exception("La fecha de fin no puede ser anterior a la fecha de inicio.");
             }

              // Validar Tutor ID si se envió
             if ($tutor_id !== null && $tutor_id === false) {
                  throw new Exception("ID de tutor no válido.");
             }

             // Validar estado
             $estados_validos = ['pendiente', 'aprobada', 'rechazada', 'en_proceso', 'finalizada'];
             if (!in_array($estado, $estados_validos)) {
                  throw new Exception("Estado de pasantía no válido.");
             }


            // Procesar archivo adicional si se ha subido uno nuevo
            $documento_adicional = null;
            $archivo_adicional_actualizado = false;

            if (isset($_FILES['documento_adicional']) && $_FILES['documento_adicional']['error'] === UPLOAD_ERR_OK) {
                 $archivo = $_FILES['documento_adicional'];
                 $archivo_nombre = $archivo['name'];
                 $archivo_tmp = $archivo['tmp_name'];
                 $archivo_size = $archivo['size'];
                 $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));

                 // Validar tipo de archivo
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                     $conexion->rollBack(); // Revertir
                    throw new Exception("El archivo adicional debe ser PDF o Word (doc/docx).");
                }

                 // Validar tamaño del archivo (ej: 10MB)
                 $max_size = 10 * 1024 * 1024; // 10 MB
                 if ($archivo_size > $max_size) {
                      $conexion->rollBack(); // Revertir
                      throw new Exception("El tamaño del archivo adicional excede el límite permitido (10MB).");
                 }

                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;

                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0777, true);
                }

                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $documento_adicional = $archivo_nuevo_nombre;
                    $archivo_adicional_actualizado = true;

                     // Opcional: Eliminar el archivo adicional antiguo si se subió uno nuevo
                     $stmt_old_file = $conexion->prepare("SELECT documento_adicional FROM pasantias WHERE id = ?");
                     $stmt_old_file->execute([$pasantiaId]);
                     $old_file = $stmt_old_file->fetchColumn();
                     if ($old_file && file_exists(__DIR__ . '/../../uploads/pasantias/' . $old_file)) {
                          unlink(__DIR__ . '/../../uploads/pasantias/' . $old_file);
                     }

                } else {
                     $conexion->rollBack(); // Revertir
                    throw new Exception("Error al mover el archivo adicional subido. Intente nuevamente.");
                }
            } elseif (isset($_FILES['documento_adicional']) && $_FILES['documento_adicional']['error'] !== UPLOAD_ERR_NO_FILE) {
                 // Manejar otros errores de subida
                 $conexion->rollBack();
                 $phpFileUploadErrors = array( /* ... definir array ... */ ); // Reutiliza el array de arriba
                 $error_code = $_FILES['documento_adicional']['error'];
                 $error_message = $phpFileUploadErrors[$error_code] ?? 'Error de subida desconocido';
                 throw new Exception("Error en la subida del archivo adicional: " . $error_message);
            }


            // Construir la consulta de actualización dinámicamente
            $sql_update_campos = [
                 'titulo = ?', 'descripcion = ?', 'empresa = ?', 'direccion_empresa = ?',
                 'contacto_empresa = ?', 'supervisor_empresa = ?', 'telefono_supervisor = ?',
                 'fecha_inicio = ?', 'fecha_fin = ?', 'estado = ?', 'tutor_id = ?'
             ];
             $valores_update = [
                 $titulo,
                 $descripcion,
                 $empresa,
                 $direccion_empresa,
                 $contacto_empresa,
                 $supervisor_empresa,
                 $telefono_supervisor,
                 empty($fecha_inicio) ? null : $fecha_inicio, // Guardar como NULL si está vacío
                 empty($fecha_fin) ? null : $fecha_fin, // Guardar como NULL si está vacío
                 $estado,
                 $tutor_id // Puede ser null
             ];

             if ($archivo_adicional_actualizado) {
                 $sql_update_campos[] = "documento_adicional = ?";
                 $valores_update[] = $documento_adicional; // Puede ser null si falla la subida? No, ya se maneja arriba.
             }

             $sql = "UPDATE pasantias SET " . implode(", ", $sql_update_campos) . " WHERE id = ?";
             $valores_update[] = $pasantiaId; // Agregar el ID al final

             $stmt = $conexion->prepare($sql);

             if (!$stmt->execute($valores_update)) {
                  $conexion->rollBack(); // Revertir si falla la ejecución
                  error_log("DB Execute Error (actualizar_pasantia): " . $stmt->errorInfo()[2]);
                  throw new Exception("Error al actualizar la pasantía en la base de datos.");
             }

            // Confirmar transacción
            $conexion->commit();

            $mensaje = "Pasantía actualizada exitosamente";

        } elseif ($_POST['accion'] === 'eliminar_pasantia') {
             // Validar ID
             $pasantiaId = filter_var($_POST['pasantia_id'] ?? null, FILTER_VALIDATE_INT);
              if ($pasantiaId === false) {
                   throw new Exception("ID de pasantía inválido para eliminar.");
              }

              $conexion->beginTransaction(); // Iniciar transacción

             // Opcional: Eliminar archivo(s) asociado(s) antes de eliminar la pasantía
             $stmt_file = $conexion->prepare("SELECT archivo_documento, documento_adicional FROM pasantias WHERE id = ? FOR UPDATE"); // Bloquear
             $stmt_file->execute([$pasantiaId]);
             $archivos = $stmt_file->fetch(PDO::FETCH_ASSOC);

             if ($archivos) {
                  $files_to_delete = [];
                  if (!empty($archivos['archivo_documento'])) $files_to_delete[] = $archivos['archivo_documento'];
                  if (!empty($archivos['documento_adicional'])) $files_to_delete[] = $archivos['documento_adicional'];

                  foreach ($files_to_delete as $file) {
                      $filePath = __DIR__ . '/../../uploads/pasantias/' . $file;
                      if (file_exists($filePath)) {
                           unlink($filePath); // Elimina el archivo físico
                      } else {
                           error_log("Advertencia: Archivo de pasantía no encontrado para eliminar: " . $filePath);
                      }
                  }
             }


             // Eliminar pasantía usando sentencia preparada
            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
             if (!$stmt->execute([$pasantiaId])) {
                  $conexion->rollBack(); // Revertir si falla la ejecución
                   error_log("DB Execute Error (eliminar_pasantia): " . $stmt->errorInfo()[2]);
                  throw new Exception("Error al eliminar la pasantía de la base de datos.");
             }

             if ($stmt->rowCount() === 0) {
                  $conexion->rollBack(); // Revertir si no se encontró
                  throw new Exception("La pasantía con ID {$pasantiaId} no fue encontrada para eliminar.");
             }

            $conexion->commit(); // Confirmar transacción

            $mensaje = "Pasantía eliminada exitosamente";
        }

        // Recargar la página con mensaje de éxito (Redirección POST a GET)
        header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
        exit;

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = $e->getMessage();
        // Registrar error detallado
        error_log("Error en gestión de pasantías (POST): " . $e->getMessage());
        // Recargar la página con mensaje de error (Redirección POST a GET)
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit;
    }
}

// --- Fin Procesar formularios ---


// Recuperar mensaje o error de la URL si existe (después de una redirección GET)
if (isset($_GET['mensaje'])) {
    $mensaje = htmlSafe($_GET['mensaje']);
} elseif (isset($_GET['error'])) {
     $error = htmlSafe($_GET['error']);
}

// Pasar datos a JSON para usar en JavaScript (para precargar datos si es necesario, aunque la mayoría se obtiene por API)
// Considera si realmente necesitas pasar *toda* la lista de pasantias y estudiantes a JS inicialmente,
// o si solo necesitas los datos de tutores para los selectores y obtener los demás por API/filtrado.
// Para la funcionalidad de filtro y búsqueda en la tabla, pasar los datos iniciales es útil.
$pasantiasDataJSON = json_encode($pasantias);
$tutoresDataJSON = json_encode($tutores); // Pasar datos de tutores a JS
$estudiantesDisponiblesDataJSON = json_encode($estudiantes_disponibles); // Pasar datos de estudiantes disponibles a JS


// Cerrar conexión
$conexion = null;

?>

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
    <div id="logo" onclick="toggleNav()">Logo</div>
    
    <nav id="navbar">
        <div class="nav-header">
            <div id="nav-logo" onclick="toggleNav()">Logo</div>
        </div>
        <ul>
            <li><a href="/views/administrador/inicio.php" class="active">Inicio</a></li>
            <li><a href="/views/administrador/aprobacion.php">Aprobación de Usuarios</a></li>
            <li><a href="/views/administrador/usuarios.php">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="/views/administrador/gestion_seminario.php">Seminario</a></li>
                    <li><a href="/views/administrador/gestion_proyectos.php">Proyectos</a></li>
                    <li><a href="/views/administrador/gestion_pasantias.php">Pasantías</a></li>
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
        
        <!-- Sección para crear pasantías -->
        <section id="crearPasantiaSection" class="tab-content">
            <form id="formCrearPasantia" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_pasantia">
                <input type="hidden" id="estudiante_id" name="estudiante_id" class="hidden-input">
                
                <div class="form-group" id="seleccionEstudianteGroup">
       <h3>Estudiante Asignado</h3>
       <div class="form-row">
           <div class="form-field">
               <label for="estudiante_id">Estudiante *</label>
               <select id="estudiante_id" name="estudiante_id" required>
                   <option value="">Seleccione un estudiante</option>
                   <?php
                   $stmt = $conexion->query("
                       SELECT id, nombre
                       FROM usuarios
                       WHERE rol = 'estudiante' AND opcion_grado = 'pasantia' AND estado = 'activo'
                       ORDER BY nombre
                   ");
                   $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                   foreach ($estudiantes as $estudiante) {
                       echo "<option value=\"{$estudiante['id']}\">" . htmlspecialchars($estudiante['nombre']) . "</option>";
                   }
                   ?>
               </select>
           </div>
       </div>
   </div>
                    
                    <input type="text" id="estudianteSearch" class="estudiante-search" placeholder="Buscar estudiante por nombre, código o documento...">
                    
                    <div class="estudiantes-grid" id="estudiantesGrid">
                        <?php if (empty($estudiantes_disponibles)): ?>
                            <div class="no-estudiantes">No hay estudiantes disponibles con opción de grado "pasantía".</div>
                        <?php else: ?>
                            <?php foreach ($estudiantes_disponibles as $estudiante): ?>
                                <div class="estudiante-card" 
                                     data-id="<?= $estudiante['id'] ?>" 
                                     data-nombre="<?= htmlspecialchars($estudiante['nombre']) ?>" 
                                     data-codigo="<?= htmlspecialchars($estudiante['codigo_estudiante'] ?? '') ?>" 
                                     data-email="<?= htmlspecialchars($estudiante['email']) ?>" 
                                     data-documento="<?= htmlspecialchars($estudiante['documento']) ?>"
                                     data-telefono="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>"
                                     data-empresa="<?= htmlspecialchars($estudiante['nombre_empresa'] ?? '') ?>"
                                     data-ciclo="<?= htmlspecialchars($estudiante['ciclo'] ?? '') ?>">
                                    <div class="estudiante-header">
                                        <h3><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                                        <?php if (!empty($estudiante['ciclo'])): ?>
                                            <span class="ciclo-badge"><?= ucfirst($estudiante['ciclo']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="estudiante-body">
                                        <div class="estudiante-info">
                                            <span class="label">Código:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['codigo_estudiante'] ?? 'No asignado') ?></span>
                                        </div>
                                        <div class="estudiante-info">
                                            <span class="label">Documento:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['documento']) ?></span>
                                        </div>
                                        <div class="estudiante-info">
                                            <span class="label">Email:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['email']) ?></span>
                                        </div>
                                        <div class="estudiante-info">
                                            <span class="label">Teléfono:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['telefono'] ?? 'No registrado') ?></span>
                                        </div>
                                        <?php if (!empty($estudiante['nombre_empresa'])): ?>
                                        <div class="estudiante-info">
                                            <span class="label">Empresa:</span>
                                            <span class="valor"><?= htmlspecialchars($estudiante['nombre_empresa']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="estudiante-footer">
                                        <button type="button" class="estudiante-select-btn" onclick="seleccionarEstudiante(<?= $estudiante['id'] ?>)">Seleccionar</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="selected-estudiante-info" id="selectedEstudianteInfo">
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
                            <label for="tutor_id">Tutor Asignado</label>
                            <select id="tutor_id" name="tutor_id">
                                <option value="">Sin tutor asignado</option>
                                <?php foreach ($tutores as $tutor): ?>
                                    <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
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
        
        <!-- Sección para listar pasantías -->
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
                        <?php foreach ($pasantias as $pasantia): ?>
                            <tr data-id="<?= $pasantia['id'] ?>" 
                                data-estudiante="<?= strtolower(htmlspecialchars($pasantia['estudiante_nombre'] ?? '')) ?>" 
                                data-titulo="<?= strtolower(htmlspecialchars($pasantia['titulo'] ?? '')) ?>" 
                                data-empresa="<?= strtolower(htmlspecialchars($pasantia['empresa'] ?? '')) ?>" 
                                data-estado="<?= htmlspecialchars($pasantia['estado'] ?? '') ?>">
                                <td><?= $pasantia['id'] ?></td>
                                <td><?= htmlspecialchars($pasantia['estudiante_nombre'] ?? 'No asignado') ?></td>
                                <td><?= htmlspecialchars($pasantia['titulo'] ?? 'Sin título') ?></td>
                                <td><?= htmlspecialchars($pasantia['empresa'] ?? 'No especificada') ?></td>
                                <td>
                                    <span class="badge estado-<?= htmlspecialchars($pasantia['estado'] ?? 'pendiente') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $pasantia['estado'] ?? 'pendiente')) ?>
                                    </span>
                                </td>
                                <td><?= !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida' ?></td>
                                <td><?= !empty($pasantia['fecha_fin']) ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No establecida' ?></td>
                                <td><?= htmlspecialchars($pasantia['tutor_nombre'] ?? 'No asignado') ?></td>
                                <td class="acciones">
                                    <?php if (!empty($pasantia['archivo_documento'])): ?>
                                        <a href="/uploads/pasantias/<?= htmlspecialchars($pasantia['archivo_documento']) ?>" target="_blank" class="btn-documento" title="Ver documento">📄</a>
                                    <?php endif; ?>
                                    <button class="btn-ver" onclick="verPasantia(<?= $pasantia['id'] ?>)" title="Ver detalles">👁️</button>
                                    <button class="btn-editar" onclick="editarPasantia(<?= $pasantia['id'] ?>)" title="Editar">✏️</button>
                                    <button class="btn-eliminar" onclick="confirmarEliminarPasantia(<?= $pasantia['id'] ?>)" title="Eliminar">❌</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pasantias)): ?>
                            <tr>
                                <td colspan="9" class="no-data">No hay pasantías registradas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    
    <!-- Modal para ver detalles de la pasantía -->
    <div id="modalVerPasantia" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerPasantia')">&times;</span>
            <h2>Detalles de la Pasantía</h2>
            <div id="detallesPasantia" class="detalles-pasantia">
                <!-- Aquí se cargarán los detalles -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-editar" onclick="editarPasantiaDesdeVer()">Editar Pasantía</button>
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalVerPasantia')">Cerrar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar pasantía -->
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
                                    <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <h3>Documentos</h3>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label>Documento del Estudiante:</label>
                            <div id="documento_estudiante" class="documento-info"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="documento_adicional">Subir Documento Adicional (PDF o Word)</label>
                            <input type="file" id="documento_adicional" name="documento_adicional" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                            <div id="documento_adicional_actual" class="documento-info"></div>
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
    
    <!-- Modal para confirmar eliminación -->
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
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Variables globales
        let pasantiaActual = null;
        let estudianteSeleccionado = null;
        
        // Funciones de navegación
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
        });
        
        // Búsqueda de estudiantes
        const estudianteSearch = document.getElementById('estudianteSearch');
        const estudiantesCards = document.querySelectorAll('.estudiante-card');
        
        estudianteSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            estudiantesCards.forEach(card => {
                const nombre = card.dataset.nombre.toLowerCase();
                const codigo = (card.dataset.codigo || '').toLowerCase();
                const documento = card.dataset.documento.toLowerCase();
                const email = card.dataset.email.toLowerCase();
                
                if (nombre.includes(searchTerm) || 
                    codigo.includes(searchTerm) || 
                    documento.includes(searchTerm) ||
                    email.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Verificar si hay resultados visibles
            const hayResultadosVisibles = Array.from(estudiantesCards).some(card => 
                card.style.display !== 'none'
            );
            
            // Mostrar mensaje si no hay resultados
            let noEstudiantes = document.querySelector('.no-estudiantes');
            if (!hayResultadosVisibles) {
                if (!noEstudiantes) {
                    noEstudiantes = document.createElement('div');
                    noEstudiantes.className = 'no-estudiantes';
                    noEstudiantes.textContent = 'No se encontraron estudiantes que coincidan con la búsqueda.';
                    document.getElementById('estudiantesGrid').appendChild(noEstudiantes);
                } else {
                    noEstudiantes.style.display = 'block';
                }
            } else if (noEstudiantes) {
                noEstudiantes.style.display = 'none';
            }
        });
        
        // Selección de estudiante
        function seleccionarEstudiante(estudianteId) {
            // Obtener la tarjeta del estudiante
            const estudianteCard = document.querySelector(`.estudiante-card[data-id="${estudianteId}"]`);
            
            if (!estudianteCard) return;
            
            // Guardar datos del estudiante
            estudianteSeleccionado = {
                id: estudianteId,
                nombre: estudianteCard.dataset.nombre,
                codigo: estudianteCard.dataset.codigo,
                documento: estudianteCard.dataset.documento,
                email: estudianteCard.dataset.email,
                telefono: estudianteCard.dataset.telefono,
                empresa: estudianteCard.dataset.empresa
            };
            
            // Actualizar el input hidden
            document.getElementById('estudiante_id').value = estudianteId;
            
            // Mostrar la información del estudiante seleccionado
            document.getElementById('infoEstudianteNombre').textContent = estudianteSeleccionado.nombre;
            document.getElementById('infoEstudianteCodigo').textContent = estudianteSeleccionado.codigo || 'No asignado';
            document.getElementById('infoEstudianteDocumento').textContent = estudianteSeleccionado.documento;
            document.getElementById('infoEstudianteEmail').textContent = estudianteSeleccionado.email;
            document.getElementById('infoEstudianteTelefono').textContent = estudianteSeleccionado.telefono || 'No registrado';
            document.getElementById('infoEstudianteEmpresa').textContent = estudianteSeleccionado.empresa || 'No registrada';
            
            // Mostrar la sección de información del estudiante
            document.getElementById('selectedEstudianteInfo').classList.add('visible');
            
            // Ocultar la sección de selección de estudiante
            document.getElementById('seleccionEstudianteGroup').style.display = 'none';
            
            // Mostrar las demás secciones del formulario
            document.getElementById('datosPasantiaGroup').style.display = 'block';
            document.getElementById('fechasTutorGroup').style.display = 'block';
            document.getElementById('documentosGroup').style.display = 'block';
            document.getElementById('formActions').style.display = 'flex';
            
            // Si el estudiante tiene empresa, autocompletar el campo
            if (estudianteSeleccionado.empresa) {
                document.getElementById('empresa').value = estudianteSeleccionado.empresa;
            }
        }
        
        function cambiarEstudiante() {
            // Limpiar estudiante seleccionado
            estudianteSeleccionado = null;
            document.getElementById('estudiante_id').value = '';
            
            // Ocultar la sección de información del estudiante
            document.getElementById('selectedEstudianteInfo').classList.remove('visible');
            
            // Mostrar la sección de selección de estudiante
            document.getElementById('seleccionEstudianteGroup').style.display = 'block';
            
            // Ocultar las demás secciones del formulario
            document.getElementById('datosPasantiaGroup').style.display = 'none';
            document.getElementById('fechasTutorGroup').style.display = 'none';
            document.getElementById('documentosGroup').style.display = 'none';
            document.getElementById('formActions').style.display = 'none';
        }
        
        function limpiarFormulario() {
            // Mantener el estudiante seleccionado pero limpiar los demás campos
            document.getElementById('titulo').value = '';
            document.getElementById('descripcion').value = '';
            document.getElementById('empresa').value = estudianteSeleccionado?.empresa || '';
            document.getElementById('direccion_empresa').value = '';
            document.getElementById('contacto_empresa').value = '';
            document.getElementById('supervisor_empresa').value = '';
            document.getElementById('telefono_supervisor').value = '';
            document.getElementById('fecha_inicio').value = '';
            document.getElementById('fecha_fin').value = '';
            document.getElementById('tutor_id').value = '';
            document.getElementById('archivo_documento').value = '';
        }
        
        // Búsqueda y filtrado de pasantías
        const searchPasantias = document.getElementById('searchPasantias');
        const filterEstado = document.getElementById('filterEstado');
        const filasPasantias = document.querySelectorAll('#tablaPasantias tbody tr');
        
        function filtrarPasantias() {
            const searchTerm = searchPasantias.value.toLowerCase();
            const estado = filterEstado.value;
            
            filasPasantias.forEach(fila => {
                if (fila.classList.contains('no-data')) return;
                
                const estudiante = fila.dataset.estudiante || '';
                const titulo = fila.dataset.titulo || '';
                const empresa = fila.dataset.empresa || '';
                const estadoFila = fila.dataset.estado || '';
                
                const matchSearch = estudiante.includes(searchTerm) || 
                                   titulo.includes(searchTerm) || 
                                   empresa.includes(searchTerm);
                const matchEstado = estado === '' || estadoFila === estado;
                
                fila.style.display = (matchSearch && matchEstado) ? '' : 'none';
            });
            
            // Mostrar mensaje si no hay resultados
            const hayResultadosVisibles = Array.from(filasPasantias).some(fila => 
                fila.style.display !== 'none' && !fila.classList.contains('no-data')
            );
            
            let noDataRow = document.querySelector('#tablaPasantias tbody tr.no-results');
            
            if (!hayResultadosVisibles) {
                if (!noDataRow) {
                    const tbody = document.querySelector('#tablaPasantias tbody');
                    noDataRow = document.createElement('tr');
                    noDataRow.className = 'no-results';
                    noDataRow.innerHTML = '<td colspan="9" class="no-data">No se encontraron pasantías que coincidan con la búsqueda.</td>';
                    tbody.appendChild(noDataRow);
                }
                noDataRow.style.display = '';
            } else if (noDataRow) {
                noDataRow.style.display = 'none';
            }
        }
        
        searchPasantias.addEventListener('input', filtrarPasantias);
        filterEstado.addEventListener('change', filtrarPasantias);
        

        // Funciones para modales
        function verPasantia(pasantiaId) {
            // Hacer una petición AJAX para obtener los detalles de la pasantía
            fetch(`?api=details&id=${pasantiaId}`)
                .then(response => response.json())
                .then(pasantia => {
                    pasantiaActual = pasantia;
                    
                    // Construir el HTML con los detalles
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
                                    
                                    ${pasantia.documento_adicional ? 
                                        `<div class="documento">
                                            <span class="label">Documento Adicional:</span>
                                            <a href="/uploads/pasantias/${pasantia.documento_adicional}" target="_blank" class="btn-documento">
                                                Ver documento
                                            </a>
                                        </div>` : 
                                        '<div class="documento">No hay documento adicional</div>'
                                    }
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('detallesPasantia').innerHTML = html;
                    abrirModal('modalVerPasantia');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles de la pasantía');
                });
        }
        
        function editarPasantia(pasantiaId) {
            // Hacer una petición AJAX para obtener los datos de la pasantía
            fetch(`?api=details&id=${pasantiaId}`)
                .then(response => response.json())
                .then(pasantia => {
                    pasantiaActual = pasantia;
                    
                    // Llenar el formulario con los datos
                    document.getElementById('edit_pasantia_id').value = pasantia.id;
                    document.getElementById('eliminar_pasantia_id').value = pasantia.id;
                    
                    // Datos del estudiante (solo lectura)
                    document.getElementById('estudiante_nombre').textContent = pasantia.estudiante_nombre || 'No asignado';
                    document.getElementById('estudiante_codigo').textContent = pasantia.codigo_estudiante || 'N/A';
                    document.getElementById('estudiante_email').textContent = pasantia.estudiante_email || 'N/A';
                    document.getElementById('estudiante_documento').textContent = pasantia.estudiante_documento || 'N/A';
                    
                    // Datos de la pasantía
                    document.getElementById('edit_titulo').value = pasantia.titulo || '';
                    document.getElementById('edit_descripcion').value = pasantia.descripcion || '';
                    document.getElementById('edit_empresa').value = pasantia.empresa || '';
                    document.getElementById('edit_direccion_empresa').value = pasantia.direccion_empresa || '';
                    document.getElementById('edit_contacto_empresa').value = pasantia.contacto_empresa || '';
                    document.getElementById('edit_supervisor_empresa').value = pasantia.supervisor_empresa || '';
                    document.getElementById('edit_telefono_supervisor').value = pasantia.telefono_supervisor || '';
                    
                    // Fechas y estado
                    document.getElementById('edit_fecha_inicio').value = pasantia.fecha_inicio || '';
                    document.getElementById('edit_fecha_fin').value = pasantia.fecha_fin || '';
                    document.getElementById('edit_estado').value = pasantia.estado || 'pendiente';
                    
                    // Tutor
                    if (pasantia.tutor_id) {
                        document.getElementById('edit_tutor_id').value = pasantia.tutor_id;
                    } else {
                        document.getElementById('edit_tutor_id').value = '';
                    }
                    
                    // Documentos
                    const documentoEstudianteDiv = document.getElementById('documento_estudiante');
                    if (pasantia.archivo_documento) {
                        documentoEstudianteDiv.innerHTML = `
                            <a href="/uploads/pasantias/${pasantia.archivo_documento}" target="_blank" class="btn-documento">
                                Ver documento del estudiante
                            </a>
                        `;
                    } else {
                        documentoEstudianteDiv.innerHTML = '<p>No hay documento adjunto</p>';
                    }
                    
                    const documentoAdicionalDiv = document.getElementById('documento_adicional_actual');
                    if (pasantia.documento_adicional) {
                        documentoAdicionalDiv.innerHTML = `
                            <p>Documento actual: <a href="/uploads/pasantias/${pasantia.documento_adicional}" target="_blank">${pasantia.documento_adicional}</a></p>
                            <p>Si sube un nuevo documento, reemplazará al actual.</p>
                        `;
                    } else {
                        documentoAdicionalDiv.innerHTML = '<p>No hay documento adicional adjunto actualmente.</p>';
                    }
                    
                    abrirModal('modalEditarPasantia');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos de la pasantía');
                });
        }
        
        function editarPasantiaDesdeVer() {
            cerrarModal('modalVerPasantia');
            editarPasantia(pasantiaActual.id);
        }
        
        function confirmarEliminarPasantia(pasantiaId) {
            document.getElementById('eliminar_pasantia_id').value = pasantiaId;
            abrirModal('modalConfirmarEliminar');
        }
        
        function confirmarEliminarPasantiaModal() {
            cerrarModal('modalEditarPasantia');
            abrirModal('modalConfirmarEliminar');
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Validación de formularios
        document.getElementById('formCrearPasantia').addEventListener('submit', function(e) {
            const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
            const fechaFin = new Date(document.getElementById('fecha_fin').value);
            
            if (fechaFin < fechaInicio) {
                e.preventDefault();
                alert('La fecha de fin no puede ser anterior a la fecha de inicio');
            }
        });
        
        // Funciones de utilidad
        function nl2br(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/\n/g, '<br>');
        }
        
        function ucfirst(str) {
            if (typeof str !== 'string') return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar mensaje de éxito/error por 5 segundos y luego ocultarlo
            const mensajes = document.querySelectorAll('.mensaje');
            if (mensajes.length > 0) {
                setTimeout(() => {
                    mensajes.forEach(msg => {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.style.display = 'none', 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>
