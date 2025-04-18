<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Manejar solicitudes API
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // API para obtener detalles completos del seminario
    if ($_GET['api'] === 'details' && isset($_GET['id'])) {
        try {
            // Obtener detalles del seminario
            $stmt = $conexion->prepare("
                SELECT s.*, u.nombre as tutor_nombre,
                (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos
                FROM seminarios s
                LEFT JOIN usuarios u ON s.tutor_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $seminario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seminario) {
                http_response_code(404);
                echo json_encode(['error' => 'Seminario no encontrado']);
                exit;
            }

            // Obtener estudiantes inscritos
            $stmt = $conexion->prepare("
                SELECT i.*, u.nombre, u.email, u.codigo_estudiante
                FROM inscripciones_seminario i
                JOIN usuarios u ON i.estudiante_id = u.id
                WHERE i.seminario_id = ?
                ORDER BY u.nombre
            ");
            $stmt->execute([$_GET['id']]);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear fechas
            $seminario['fecha'] = date('d/m/Y', strtotime($seminario['fecha']));
            $seminario['hora'] = date('H:i', strtotime($seminario['hora']));
            $seminario['fecha_creacion'] = date('d/m/Y H:i', strtotime($seminario['fecha_creacion']));

            // Preparar respuesta
            $response = [
                'id' => $seminario['id'],
                'titulo' => $seminario['titulo'],
                'descripcion' => nl2br(htmlspecialchars($seminario['descripcion'])),
                'fecha' => $seminario['fecha'],
                'hora' => $seminario['hora'],
                'modalidad' => ucfirst($seminario['modalidad']),
                'lugar' => $seminario['lugar'],
                'cupos' => $seminario['cupos'],
                'inscritos' => $seminario['num_inscritos'],
                'tutor_nombre' => $seminario['tutor_nombre'],
                'archivo_guia' => $seminario['archivo_guia'],
                'estado' => ucfirst($seminario['estado']),
                'fecha_creacion' => $seminario['fecha_creacion'],
                'estudiantes' => array_map(function($est) {
                    return [
                        'id' => $est['estudiante_id'],
                        'nombre' => $est['nombre'],
                        'email' => $est['email'],
                        'codigo' => $est['codigo_estudiante'],
                        'estado' => ucfirst($est['estado']),
                        'asistencia' => $est['asistencia'],
                        'nota' => $est['nota']
                    ];
                }, $estudiantes)
            ];

            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los detalles del seminario']);
        }
        exit;
    }
    
    // API para obtener datos básicos del seminario para edición
    if ($_GET['api'] === 'edit' && isset($_GET['id'])) {
        try {
            $stmt = $conexion->prepare("
                SELECT s.*, u.nombre as tutor_nombre
                FROM seminarios s
                LEFT JOIN usuarios u ON s.tutor_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $seminario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seminario) {
                http_response_code(404);
                echo json_encode(['error' => 'Seminario no encontrado']);
                exit;
            }

            // Formatear fecha para el input type="date"
            $seminario['fecha'] = date('Y-m-d', strtotime($seminario['fecha']));
            // Formatear hora para el input type="time"
            $seminario['hora'] = date('H:i', strtotime($seminario['hora']));

            echo json_encode($seminario);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los datos del seminario']);
        }
        exit;
    }
    
    // API para obtener estudiantes disponibles para inscribir
    if ($_GET['api'] === 'estudiantes_disponibles' && isset($_GET['seminario_id'])) {
        try {
            // Obtener estudiantes que tienen opción de grado "seminario" y no están inscritos en este seminario
            $stmt = $conexion->prepare("
                SELECT id, nombre, email, codigo_estudiante, documento
                FROM usuarios
                WHERE rol = 'estudiante' 
                AND opcion_grado = 'seminario'
                AND estado = 'activo'
                AND id NOT IN (
                    SELECT estudiante_id 
                    FROM inscripciones_seminario 
                    WHERE seminario_id = ?
                )
                ORDER BY nombre
            ");
            $stmt->execute([$_GET['seminario_id']]);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($estudiantes);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los estudiantes disponibles']);
        }
        exit;
    }
    
    // API para inscribir estudiante
    if ($_GET['api'] === 'inscribir_estudiante' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['seminario_id']) || !isset($data['estudiante_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                exit;
            }
            
            // Verificar si hay cupos disponibles
            $stmt = $conexion->prepare("
                SELECT cupos, (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = ?) as inscritos
                FROM seminarios WHERE id = ?
            ");
            $stmt->execute([$data['seminario_id'], $data['seminario_id']]);
            $seminario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($seminario['inscritos'] >= $seminario['cupos']) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay cupos disponibles en este seminario']);
                exit;
            }
            
            // Verificar que el estudiante no esté ya inscrito
            $stmt = $conexion->prepare("
                SELECT COUNT(*) as existe FROM inscripciones_seminario 
                WHERE seminario_id = ? AND estudiante_id = ?
            ");
            $stmt->execute([$data['seminario_id'], $data['estudiante_id']]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC)['existe'];
            
            if ($existe > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'El estudiante ya está inscrito en este seminario']);
                exit;
            }
            
            // Inscribir al estudiante
            $stmt = $conexion->prepare("
                INSERT INTO inscripciones_seminario (seminario_id, estudiante_id, estado)
                VALUES (?, ?, 'inscrito')
            ");
            $stmt->execute([$data['seminario_id'], $data['estudiante_id']]);
            
            // Obtener datos del estudiante para la respuesta
            $stmt = $conexion->prepare("
                SELECT u.nombre, u.email, u.codigo_estudiante, i.estado
                FROM inscripciones_seminario i
                JOIN usuarios u ON i.estudiante_id = u.id
                WHERE i.seminario_id = ? AND i.estudiante_id = ?
            ");
            $stmt->execute([$data['seminario_id'], $data['estudiante_id']]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Estudiante inscrito correctamente',
                'estudiante' => [
                    'id' => $data['estudiante_id'],
                    'nombre' => $estudiante['nombre'],
                    'email' => $estudiante['email'],
                    'codigo' => $estudiante['codigo_estudiante'],
                    'estado' => ucfirst($estudiante['estado']),
                    'asistencia' => 0,
                    'nota' => null
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al inscribir al estudiante: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para eliminar inscripción
    if ($_GET['api'] === 'eliminar_inscripcion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['seminario_id']) || !isset($data['estudiante_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                exit;
            }
            
            // Eliminar inscripción
            $stmt = $conexion->prepare("
                DELETE FROM inscripciones_seminario 
                WHERE seminario_id = ? AND estudiante_id = ?
            ");
            $stmt->execute([$data['seminario_id'], $data['estudiante_id']]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Inscripción no encontrada']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Inscripción eliminada correctamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar la inscripción: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para actualizar estado, asistencia o nota de estudiante
    if ($_GET['api'] === 'actualizar_inscripcion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['seminario_id']) || !isset($data['estudiante_id']) || 
                (!isset($data['estado']) && !isset($data['asistencia']) && !isset($data['nota']))) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                exit;
            }
            
            // Construir la consulta según los campos a actualizar
            $campos = [];
            $valores = [];
            
            if (isset($data['estado'])) {
                $campos[] = "estado = ?";
                $valores[] = $data['estado'];
            }
            
            if (isset($data['asistencia'])) {
                $campos[] = "asistencia = ?";
                $valores[] = $data['asistencia'] ? 1 : 0;
            }
            
            if (isset($data['nota'])) {
                $campos[] = "nota = ?";
                $valores[] = $data['nota'];
            }
            
            $valores[] = $data['seminario_id'];
            $valores[] = $data['estudiante_id'];
            
            $sql = "UPDATE inscripciones_seminario SET " . implode(", ", $campos) . 
                   " WHERE seminario_id = ? AND estudiante_id = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute($valores);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Inscripción no encontrada o sin cambios']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Inscripción actualizada correctamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar la inscripción: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Si llegamos aquí, la API solicitada no existe
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no encontrado']);
    exit;
}

// Inicializar variables
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener tutores para el selector
$tutores = $conexion->query("
    SELECT id, nombre, email 
    FROM usuarios 
    WHERE rol = 'tutor' 
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener seminarios existentes
$seminarios = $conexion->query("
    SELECT s.*, u.nombre as tutor_nombre,
    (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos
    FROM seminarios s
    LEFT JOIN usuarios u ON s.tutor_id = u.id
    ORDER BY s.fecha DESC, s.hora DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_seminario') {
            // Validar datos
            if (empty($_POST['titulo']) || empty($_POST['descripcion']) || empty($_POST['fecha']) || 
                empty($_POST['hora']) || empty($_POST['modalidad']) || empty($_POST['lugar'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }

            // Procesar archivo si se ha subido
            $archivo_guia = null;
            if (isset($_FILES['archivo_guia']) && $_FILES['archivo_guia']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['archivo_guia']['name'];
                $archivo_tmp = $_FILES['archivo_guia']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/seminarios/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_guia = $archivo_nuevo_nombre;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }

            // Insertar seminario
            $stmt = $conexion->prepare("
                INSERT INTO seminarios (titulo, descripcion, fecha, hora, modalidad, lugar, cupos, 
                                      tutor_id, archivo_guia, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'],
                $_POST['fecha'],
                $_POST['hora'],
                $_POST['modalidad'],
                $_POST['lugar'],
                $_POST['cupos'] ?? 30,
                !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                $archivo_guia
            ]);
            
            $mensaje = "Seminario creado exitosamente";
            
        } elseif ($_POST['accion'] === 'actualizar_seminario') {
            if (empty($_POST['seminario_id'])) {
                throw new Exception("ID de seminario no válido");
            }

            // Procesar archivo si se ha subido uno nuevo
            $archivo_guia = null;
            $archivo_actualizado = false;
            
            if (isset($_FILES['edit_archivo_guia']) && $_FILES['edit_archivo_guia']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['edit_archivo_guia']['name'];
                $archivo_tmp = $_FILES['edit_archivo_guia']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/seminarios/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_guia = $archivo_nuevo_nombre;
                    $archivo_actualizado = true;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }

            // Actualizar seminario
            if ($archivo_actualizado) {
                $stmt = $conexion->prepare("
                    UPDATE seminarios 
                    SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, modalidad = ?, 
                        lugar = ?, cupos = ?, tutor_id = ?, archivo_guia = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $_POST['fecha'],
                    $_POST['hora'],
                    $_POST['modalidad'],
                    $_POST['lugar'],
                    $_POST['cupos'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $archivo_guia,
                    $_POST['estado'],
                    $_POST['seminario_id']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    UPDATE seminarios 
                    SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, modalidad = ?, 
                        lugar = ?, cupos = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $_POST['fecha'],
                    $_POST['hora'],
                    $_POST['modalidad'],
                    $_POST['lugar'],
                    $_POST['cupos'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['seminario_id']
                ]);
            }
            
            $mensaje = "Seminario actualizado exitosamente";
            
        } elseif ($_POST['accion'] === 'eliminar_seminario') {
            if (empty($_POST['seminario_id'])) {
                throw new Exception("ID de seminario no válido");
            }
            
            // Eliminar inscripciones
            $stmt = $conexion->prepare("DELETE FROM inscripciones_seminario WHERE seminario_id = ?");
            $stmt->execute([$_POST['seminario_id']]);
            
            // Eliminar seminario
            $stmt = $conexion->prepare("DELETE FROM seminarios WHERE id = ?");
            $stmt->execute([$_POST['seminario_id']]);
            
            $mensaje = "Seminario eliminado exitosamente";
        }
        
        // Recargar la página para mostrar los cambios
        header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Recuperar mensaje de la URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}

// Cerrar conexión
$conexion = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Seminarios - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestion_seminario.css">
   
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
        <h1>Gestión de Seminarios</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button id="crearSeminarioTab" class="active">Crear Seminario</button>
            <button id="listarSeminariosTab">Listar Seminarios</button>
        </div>
        
        <!-- Sección para crear seminarios -->
        <section id="crearSeminarioSection" class="tab-content">
            <form id="formCrearSeminario" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_seminario">
                
                <div class="form-group">
                    <h2>Información del Seminario</h2>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="titulo">Nombre o Tema del Seminario *</label>
                            <input type="text" id="titulo" name="titulo" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="descripcion">Descripción *</label>
                            <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="fecha">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" required>
                        </div>
                        <div class="form-field">
                            <label for="hora">Hora *</label>
                            <input type="time" id="hora" name="hora" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="modalidad">Modalidad *</label>
                            <select id="modalidad" name="modalidad" required>
                                <option value="">Seleccione una modalidad</option>
                                <option value="presencial">Presencial</option>
                                <option value="virtual">Virtual</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="lugar">Lugar o Enlace *</label>
                            <input type="text" id="lugar" name="lugar" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="cupos">Cupos Disponibles</label>
                            <input type="number" id="cupos" name="cupos" value="30" min="1">
                        </div>
                        <div class="form-field">
                            <label for="tutor_id">Tutor Encargado</label>
                            <select id="tutor_id" name="tutor_id">
                                <option value="">Seleccione un tutor</option>
                                <?php foreach ($tutores as $tutor): ?>
                                    <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="archivo_guia">Archivo Guía o Material (PDF o Word)</label>
                            <input type="file" id="archivo_guia" name="archivo_guia" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Crear Seminario</button>
                    <button type="reset" class="btn-secondary">Limpiar Formulario</button>
                </div>
            </form>
        </section>
        
        <!-- Sección para listar seminarios -->
        <section id="listarSeminariosSection" class="tab-content" style="display: none;">
            <div class="search-filter">
                <input type="text" id="searchSeminarios" placeholder="Buscar seminarios...">
                <select id="filterEstadoSeminario">
                    <option value="">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
                <select id="filterModalidad">
                    <option value="">Todas las modalidades</option>
                    <option value="presencial">Presencial</option>
                    <option value="virtual">Virtual</option>
                </select>
            </div>
            
            <div class="seminarios-grid">
                <?php foreach ($seminarios as $seminario): ?>
                    <div class="seminario-card" 
                         data-id="<?= $seminario['id'] ?>"
                         data-titulo="<?= strtolower(htmlspecialchars($seminario['titulo'])) ?>"
                         data-estado="<?= htmlspecialchars($seminario['estado']) ?>"
                         data-modalidad="<?= htmlspecialchars($seminario['modalidad']) ?>">
                        <div class="seminario-header estado-<?= htmlspecialchars($seminario['estado']) ?>">
                            <h3><?= htmlspecialchars($seminario['titulo']) ?></h3>
                            <span class="seminario-estado"><?= ucfirst($seminario['estado']) ?></span>
                        </div>
                        <div class="seminario-body">
                            <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($seminario['fecha'])) ?></p>
                            <p><strong>Hora:</strong> <?= date('H:i', strtotime($seminario['hora'])) ?></p>
                            <p><strong>Modalidad:</strong> <?= ucfirst($seminario['modalidad']) ?></p>
                            <p><strong>Lugar:</strong> <?= htmlspecialchars($seminario['lugar']) ?></p>
                            <p><strong>Cupos:</strong> <?= $seminario['num_inscritos'] ?>/<?= $seminario['cupos'] ?></p>
                            <p><strong>Tutor:</strong> <?= htmlspecialchars($seminario['tutor_nombre'] ?? 'No asignado') ?></p>
                            <?php if (!empty($seminario['archivo_guia'])): ?>
                                <p><strong>Material:</strong> <a href="/uploads/seminarios/<?= htmlspecialchars($seminario['archivo_guia']) ?>" target="_blank" class="archivo-link">Ver material</a></p>
                            <?php endif; ?>
                        </div>
                        <div class="seminario-footer">
                            <button class="btn-ver" onclick="verSeminario(<?= $seminario['id'] ?>)">Ver Detalles</button>
                            <button class="btn-editar" onclick="editarSeminario(<?= $seminario['id'] ?>)">Editar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($seminarios)): ?>
                    <div class="no-seminarios">
                        <p>No hay seminarios registrados. Cree un nuevo seminario en la pestaña "Crear Seminario".</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <!-- Modal para ver detalles del seminario -->
    <div id="modalVerSeminario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerSeminario')">&times;</span>
            <h2>Detalles del Seminario</h2>
            <div id="detallesSeminario"></div>
            
            <!-- Sección para gestionar estudiantes -->
            <div class="estudiantes-container">
                <div class="estudiantes-header">
                    <h3>Estudiantes Inscritos</h3>
                    <button id="btnAgregarEstudiantes" class="btn-agregar-estudiantes" onclick="mostrarEstudiantesDisponibles()">
                        <i>+</i> Agregar Estudiantes
                    </button>
                </div>
                
                <div id="estudiantesInscritos" class="estudiantes-lista">
                    <!-- Aquí se cargarán los estudiantes inscritos -->
                </div>
                
                <div id="estudiantesDisponiblesContainer" style="display: none;">
                    <h3>Estudiantes Disponibles</h3>
                    <input type="text" id="searchEstudiantes" class="search-estudiantes" placeholder="Buscar estudiantes...">
                    <div id="estudiantesDisponibles" class="estudiantes-disponibles">
                        <!-- Aquí se cargarán los estudiantes disponibles -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar seminario -->
    <div id="modalEditarSeminario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarSeminario')">&times;</span>
            <h2>Editar Seminario</h2>
            <form id="formEditarSeminario" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_seminario">
                <input type="hidden" name="seminario_id" id="edit_seminario_id">
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_titulo">Nombre o Tema del Seminario *</label>
                        <input type="text" id="edit_titulo" name="titulo" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_descripcion">Descripción *</label>
                        <textarea id="edit_descripcion" name="descripcion" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_fecha">Fecha *</label>
                        <input type="date" id="edit_fecha" name="fecha" required>
                    </div>
                    <div class="form-field">
                        <label for="edit_hora">Hora *</label>
                        <input type="time" id="edit_hora" name="hora" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_modalidad">Modalidad *</label>
                        <select id="edit_modalidad" name="modalidad" required>
                            <option value="presencial">Presencial</option>
                            <option value="virtual">Virtual</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="edit_lugar">Lugar o Enlace *</label>
                        <input type="text" id="edit_lugar" name="lugar" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_cupos">Cupos Disponibles</label>
                        <input type="number" id="edit_cupos" name="cupos" min="1">
                    </div>
                    <div class="form-field">
                        <label for="edit_tutor_id">Tutor Encargado</label>
                        <select id="edit_tutor_id" name="tutor_id">
                            <option value="">Sin tutor asignado</option>
                            <?php foreach ($tutores as $tutor): ?>
                                <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_estado">Estado del Seminario *</label>
                        <select id="edit_estado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="finalizado">Finalizado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_archivo_guia">Archivo Guía o Material (PDF o Word)</label>
                        <input type="file" id="edit_archivo_guia" name="edit_archivo_guia" accept=".pdf,.doc,.docx">
                        <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        <div id="archivo_actual" class="archivo-actual"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarSeminario')">Cancelar</button>
                    <button type="button" class="btn-danger" onclick="confirmarEliminarSeminario()">Eliminar Seminario</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para confirmar eliminación -->
    <div id="modalConfirmarEliminar" class="modal">
        <div class="modal-content modal-small">
            <span class="close" onclick="cerrarModal('modalConfirmarEliminar')">&times;</span>
            <h2>Confirmar Eliminación</h2>
            <p>¿Está seguro que desea eliminar este seminario? Esta acción no se puede deshacer.</p>
            <form id="formEliminarSeminario" method="POST" action="">
                <input type="hidden" name="accion" value="eliminar_seminario">
                <input type="hidden" name="seminario_id" id="eliminar_seminario_id">
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
        let seminarioActualId = null;
        
        // Funciones de navegación
        function toggleNav() {
            document.getElementById("navbar").classList.toggle("active");
            document.querySelector("main").classList.toggle("nav-active");
            document.querySelector("footer").classList.toggle("nav-active");
        }
        
        // Manejo de pestañas
        const crearSeminarioTab = document.getElementById('crearSeminarioTab');
        const listarSeminariosTab = document.getElementById('listarSeminariosTab');
        const crearSeminarioSection = document.getElementById('crearSeminarioSection');
        const listarSeminariosSection = document.getElementById('listarSeminariosSection');
        
        crearSeminarioTab.addEventListener('click', () => {
            crearSeminarioTab.classList.add('active');
            listarSeminariosTab.classList.remove('active');
            crearSeminarioSection.style.display = 'block';
            listarSeminariosSection.style.display = 'none';
        });
        
        listarSeminariosTab.addEventListener('click', () => {
            listarSeminariosTab.classList.add('active');
            crearSeminarioTab.classList.remove('active');
            listarSeminariosSection.style.display = 'block';
            crearSeminarioSection.style.display = 'none';
        });
        
        // Búsqueda y filtrado de seminarios
        const searchSeminarios = document.getElementById('searchSeminarios');
        const filterEstadoSeminario = document.getElementById('filterEstadoSeminario');
        const filterModalidad = document.getElementById('filterModalidad');
        const seminarioCards = document.querySelectorAll('.seminario-card');
        
        function filtrarSeminarios() {
            const searchTerm = searchSeminarios.value.toLowerCase();
            const estado = filterEstadoSeminario.value;
            const modalidad = filterModalidad.value;
            
            seminarioCards.forEach(card => {
                const titulo = card.dataset.titulo;
                const estadoCard = card.dataset.estado;
                const modalidadCard = card.dataset.modalidad;
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estado === '' || estadoCard === estado;
                const matchModalidad = modalidad === '' || modalidadCard === modalidad;
                
                card.style.display = (matchSearch && matchEstado && matchModalidad) ? 'block' : 'none';
            });
        }
        
        searchSeminarios.addEventListener('input', filtrarSeminarios);
        filterEstadoSeminario.addEventListener('change', filtrarSeminarios);
        filterModalidad.addEventListener('change', filtrarSeminarios);
        
        // Funciones para modales
        function verSeminario(seminarioId) {
            seminarioActualId = seminarioId;
            
            // Hacer una petición AJAX para obtener los detalles del seminario
            fetch(`?api=details&id=${seminarioId}`)
                .then(response => response.json())
                .then(data => {
                    // Construir el HTML con los detalles
                    let html = `
                        <div class="seminario-detalle">
                            <div class="seminario-header estado-${data.estado.toLowerCase()}">
                                <h3>${data.titulo}</h3>
                                <span class="seminario-estado">${data.estado}</span>
                            </div>
                            <div class="seminario-info">
                                <p><strong>Fecha:</strong> ${data.fecha}</p>
                                <p><strong>Hora:</strong> ${data.hora}</p>
                                <p><strong>Modalidad:</strong> ${data.modalidad}</p>
                                <p><strong>Lugar:</strong> ${data.lugar}</p>
                                <p><strong>Cupos:</strong> ${data.inscritos}/${data.cupos}</p>
                                <p><strong>Tutor:</strong> ${data.tutor_nombre || 'No asignado'}</p>
                                
                                <h4>Descripción:</h4>
                                <div class="descripcion-completa">${data.descripcion}</div>
                                
                                ${data.archivo_guia ? 
                                    `<p><strong>Material:</strong> <a href="/uploads/seminarios/${data.archivo_guia}" target="_blank" class="archivo-link">Ver material</a></p>` : 
                                    ''
                                }
                            </div>
                        </div>
                    `;
                    
                    detallesSeminario.innerHTML = html;
                    
                    // Cargar estudiantes inscritos
                    cargarEstudiantesInscritos(data.estudiantes);
                    
                    abrirModal('modalVerSeminario');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles del seminario');
                });
        }
        
        function cargarEstudiantesInscritos(estudiantes) {
            const estudiantesContainer = document.getElementById('estudiantesInscritos');
            
            if (estudiantes.length === 0) {
                estudiantesContainer.innerHTML = '<div class="no-estudiantes">No hay estudiantes inscritos en este seminario.</div>';
                return;
            }
            
            let html = '';
            estudiantes.forEach(est => {
                html += `
                    <div class="estudiante-item" data-id="${est.id}">
                        <button class="btn-eliminar" onclick="eliminarInscripcion(${est.id})">×</button>
                        <div class="estudiante-info">
                            <h4>${est.nombre} <span class="badge badge-${est.estado.toLowerCase()}">${est.estado}</span></h4>
                            <p><strong>Código:</strong> ${est.codigo || 'N/A'}</p>
                            <p><strong>Email:</strong> ${est.email}</p>
                        </div>
                        <div class="estudiante-acciones">
                            <div>
                                <label>
                                    <input type="checkbox" class="asistencia-check" ${est.asistencia ? 'checked' : ''} 
                                        onchange="actualizarAsistencia(${est.id}, this.checked)">
                                    Asistencia
                                </label>
                            </div>
                            <div>
                                <select onchange="actualizarEstado(${est.id}, this.value)">
                                    <option value="inscrito" ${est.estado.toLowerCase() === 'inscrito' ? 'selected' : ''}>Inscrito</option>
                                    <option value="aprobado" ${est.estado.toLowerCase() === 'aprobado' ? 'selected' : ''}>Aprobado</option>
                                    <option value="rechazado" ${est.estado.toLowerCase() === 'rechazado' ? 'selected' : ''}>Rechazado</option>
                                    <option value="finalizado" ${est.estado.toLowerCase() === 'finalizado' ? 'selected' : ''}>Finalizado</option>
                                </select>
                            </div>
                            <div>
                                <input type="number" class="nota-input" placeholder="Nota" min="0" max="5" step="0.1" 
                                    value="${est.nota || ''}" onchange="actualizarNota(${est.id}, this.value)">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            estudiantesContainer.innerHTML = html;
        }
        
        function mostrarEstudiantesDisponibles() {
            if (!seminarioActualId) return;
            
            const container = document.getElementById('estudiantesDisponiblesContainer');
            container.style.display = 'block';
            
            // Cargar estudiantes disponibles
            fetch(`?api=estudiantes_disponibles&seminario_id=${seminarioActualId}`)
                .then(response => response.json())
                .then(estudiantes => {
                    const estudiantesContainer = document.getElementById('estudiantesDisponibles');
                    
                    if (estudiantes.length === 0) {
                        estudiantesContainer.innerHTML = '<div class="no-estudiantes">No hay estudiantes disponibles para inscribir.</div>';
                        return;
                    }
                    
                    let html = '';
                    estudiantes.forEach(est => {
                        html += `
                            <div class="estudiante-disponible" data-id="${est.id}" data-nombre="${est.nombre.toLowerCase()}">
                                <div>
                                    <strong>${est.nombre}</strong>
                                    <br>Código: ${est.codigo_estudiante || 'N/A'}
                                    <br>Documento: ${est.documento}
                                </div>
                                <button onclick="inscribirEstudiante(${est.id})">Inscribir</button>
                            </div>
                        `;
                    });
                    
                    estudiantesContainer.innerHTML = html;
                    
                    // Configurar búsqueda de estudiantes
                    const searchEstudiantes = document.getElementById('searchEstudiantes');
                    searchEstudiantes.value = '';
                    searchEstudiantes.addEventListener('input', filtrarEstudiantesDisponibles);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los estudiantes disponibles');
                });
        }
        
        function filtrarEstudiantesDisponibles() {
            const searchTerm = document.getElementById('searchEstudiantes').value.toLowerCase();
            const estudiantes = document.querySelectorAll('.estudiante-disponible');
            
            estudiantes.forEach(est => {
                const nombre = est.dataset.nombre;
                est.style.display = nombre.includes(searchTerm) ? 'flex' : 'none';
            });
        }
        
        function inscribirEstudiante(estudianteId) {
            if (!seminarioActualId) return;
            
            fetch(`?api=inscribir_estudiante`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    seminario_id: seminarioActualId,
                    estudiante_id: estudianteId
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Actualizar la lista de estudiantes inscritos
                const estudiantesInscritos = document.getElementById('estudiantesInscritos');
                
                // Si no hay estudiantes, limpiar el mensaje
                if (estudiantesInscritos.querySelector('.no-estudiantes')) {
                    estudiantesInscritos.innerHTML = '';
                }
                
                // Agregar el nuevo estudiante
                const nuevoEstudiante = document.createElement('div');
                nuevoEstudiante.className = 'estudiante-item';
                nuevoEstudiante.dataset.id = data.estudiante.id;
                nuevoEstudiante.innerHTML = `
                    <button class="btn-eliminar" onclick="eliminarInscripcion(${data.estudiante.id})">×</button>
                    <div class="estudiante-info">
                        <h4>${data.estudiante.nombre} <span class="badge badge-inscrito">Inscrito</span></h4>
                        <p><strong>Código:</strong> ${data.estudiante.codigo || 'N/A'}</p>
                        <p><strong>Email:</strong> ${data.estudiante.email}</p>
                    </div>
                    <div class="estudiante-acciones">
                        <div>
                            <label>
                                <input type="checkbox" class="asistencia-check" onchange="actualizarAsistencia(${data.estudiante.id}, this.checked)">
                                Asistencia
                            </label>
                        </div>
                        <div>
                            <select onchange="actualizarEstado(${data.estudiante.id}, this.value)">
                                <option value="inscrito" selected>Inscrito</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="rechazado">Rechazado</option>
                                <option value="finalizado">Finalizado</option>
                            </select>
                        </div>
                        <div>
                            <input type="number" class="nota-input" placeholder="Nota" min="0" max="5" step="0.1" 
                                onchange="actualizarNota(${data.estudiante.id}, this.value)">
                        </div>
                    </div>
                `;
                
                estudiantesInscritos.appendChild(nuevoEstudiante);
                
                // Eliminar el estudiante de la lista de disponibles
                const estudianteDisponible = document.querySelector(`.estudiante-disponible[data-id="${data.estudiante.id}"]`);
                if (estudianteDisponible) {
                    estudianteDisponible.remove();
                }
                
                // Actualizar contador de inscritos en los detalles
                const seminarioDetalle = document.querySelector('.seminario-detalle');
                if (seminarioDetalle) {
                    const cuposInfo = seminarioDetalle.querySelector('p:nth-child(5)');
                    if (cuposInfo) {
                        const [inscritos, cupos] = cuposInfo.textContent.split(':')[1].trim().split('/');
                        cuposInfo.innerHTML = `<strong>Cupos:</strong> ${parseInt(inscritos) + 1}/${cupos}`;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al inscribir al estudiante');
            });
        }
        
        function eliminarInscripcion(estudianteId) {
            if (!seminarioActualId) return;
            
            if (!confirm('¿Está seguro que desea eliminar a este estudiante del seminario?')) {
                return;
            }
            
            fetch(`?api=eliminar_inscripcion`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    seminario_id: seminarioActualId,
                    estudiante_id: estudianteId
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Eliminar el estudiante de la lista
                const estudianteItem = document.querySelector(`.estudiante-item[data-id="${estudianteId}"]`);
                if (estudianteItem) {
                    estudianteItem.remove();
                }
                
                // Si no quedan estudiantes, mostrar mensaje
                const estudiantesInscritos = document.getElementById('estudiantesInscritos');
                if (estudiantesInscritos.children.length === 0) {
                    estudiantesInscritos.innerHTML = '<div class="no-estudiantes">No hay estudiantes inscritos en este seminario.</div>';
                }
                
                // Actualizar contador de inscritos en los detalles
                const seminarioDetalle = document.querySelector('.seminario-detalle');
                if (seminarioDetalle) {
                    const cuposInfo = seminarioDetalle.querySelector('p:nth-child(5)');
                    if (cuposInfo) {
                        const [inscritos, cupos] = cuposInfo.textContent.split(':')[1].trim().split('/');
                        cuposInfo.innerHTML = `<strong>Cupos:</strong> ${Math.max(0, parseInt(inscritos) - 1)}/${cupos}`;
                    }
                }
                
                // Recargar estudiantes disponibles si están visibles
                if (document.getElementById('estudiantesDisponiblesContainer').style.display !== 'none') {
                    mostrarEstudiantesDisponibles();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la inscripción');
            });
        }
        
        function actualizarEstado(estudianteId, estado) {
            actualizarInscripcion(estudianteId, { estado });
            
            // Actualizar la badge en la UI
            const estudianteItem = document.querySelector(`.estudiante-item[data-id="${estudianteId}"]`);
            if (estudianteItem) {
                const badge = estudianteItem.querySelector('.badge');
                if (badge) {
                    badge.className = `badge badge-${estado}`;
                    badge.textContent = estado.charAt(0).toUpperCase() + estado.slice(1);
                }
            }
        }
        
        function actualizarAsistencia(estudianteId, asistencia) {
            actualizarInscripcion(estudianteId, { asistencia });
        }
        
        function actualizarNota(estudianteId, nota) {
            if (nota === '') return;
            
            const notaNum = parseFloat(nota);
            if (isNaN(notaNum) || notaNum < 0 || notaNum > 5) {
                alert('La nota debe ser un número entre 0 y 5');
                return;
            }
            
            actualizarInscripcion(estudianteId, { nota: notaNum });
        }
        
        function actualizarInscripcion(estudianteId, datos) {
                if (!seminarioActualId) return;
                
                fetch(`?api=actualizar_inscripcion`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        seminario_id: seminarioActualId,
                        estudiante_id: estudianteId,
                        ...datos
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al actualizar la inscripción');
                });
        }
        
        function editarSeminario(seminarioId) {
            // Hacer una petición AJAX para obtener los datos del seminario
            fetch(`?api=edit&id=${seminarioId}`)
                .then(response => response.json())
                .then(seminario => {
                    document.getElementById('edit_seminario_id').value = seminario.id;
                    document.getElementById('eliminar_seminario_id').value = seminario.id;
                    document.getElementById('edit_titulo').value = seminario.titulo;
                    document.getElementById('edit_descripcion').value = seminario.descripcion;
                    document.getElementById('edit_fecha').value = seminario.fecha;
                    document.getElementById('edit_hora').value = seminario.hora;
                    document.getElementById('edit_modalidad').value = seminario.modalidad;
                    document.getElementById('edit_lugar').value = seminario.lugar;
                    document.getElementById('edit_cupos').value = seminario.cupos;
                    document.getElementById('edit_estado').value = seminario.estado;
                    
                    if (seminario.tutor_id) {
                        document.getElementById('edit_tutor_id').value = seminario.tutor_id;
                    }
                    
                    const archivoActualDiv = document.getElementById('archivo_actual');
                    if (seminario.archivo_guia) {
                        archivoActualDiv.innerHTML = `
                            <p>Archivo actual: <a href="/uploads/seminarios/${seminario.archivo_guia}" target="_blank">${seminario.archivo_guia}</a></p>
                            <p>Si sube un nuevo archivo, reemplazará al actual.</p>
                        `;
                    } else {
                        archivoActualDiv.innerHTML = '<p>No hay archivo adjunto actualmente.</p>';
                    }
                    
                    abrirModal('modalEditarSeminario');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del seminario');
                });
        }
        
        function confirmarEliminarSeminario() {
            cerrarModal('modalEditarSeminario');
            abrirModal('modalConfirmarEliminar');
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Si cerramos el modal de ver seminario, ocultamos también la sección de estudiantes disponibles
            if (modalId === 'modalVerSeminario') {
                document.getElementById('estudiantesDisponiblesContainer').style.display = 'none';
                seminarioActualId = null;
            }
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                // Si cerramos el modal de ver seminario, ocultamos también la sección de estudiantes disponibles
                if (event.target.id === 'modalVerSeminario') {
                    document.getElementById('estudiantesDisponiblesContainer').style.display = 'none';
                    seminarioActualId = null;
                }
            }
        }
        
        // Validación de formularios
        document.getElementById('formCrearSeminario').addEventListener('submit', function(e) {
            const fecha = new Date(document.getElementById('fecha').value);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fecha < hoy) {
                e.preventDefault();
                alert('La fecha del seminario no puede ser anterior a hoy');
            }
        });
        
        document.getElementById('formEditarSeminario').addEventListener('submit', function(e) {
            const fecha = new Date(document.getElementById('edit_fecha').value);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fecha < hoy && document.getElementById('edit_estado').value === 'activo') {
                e.preventDefault();
                alert('No se puede programar un seminario activo en una fecha pasada');
            }
        });
        
        // Actualizar campo de lugar según modalidad
        document.getElementById('modalidad').addEventListener('change', function() {
            const lugarInput = document.getElementById('lugar');
            if (this.value === 'virtual') {
                lugarInput.placeholder = 'Ingrese el enlace de la reunión';
            } else {
                lugarInput.placeholder = 'Ingrese la ubicación física';
            }
        });
        
        document.getElementById('edit_modalidad').addEventListener('change', function() {
            const lugarInput = document.getElementById('edit_lugar');
            if (this.value === 'virtual') {
                lugarInput.placeholder = 'Ingrese el enlace de la reunión';
            } else {
                lugarInput.placeholder = 'Ingrese la ubicación física';
            }
        });
        
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
