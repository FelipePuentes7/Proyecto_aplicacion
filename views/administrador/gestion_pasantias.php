<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Manejar solicitudes API
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // API para obtener detalles completos de la pasantía
    if ($_GET['api'] === 'details' && isset($_GET['id'])) {
        try {
            // Obtener detalles de la pasantía
            $stmt = $conexion->prepare("
                SELECT p.*, 
                       e.nombre as estudiante_nombre, 
                       e.codigo_estudiante, 
                       e.email as estudiante_email,
                       e.documento as estudiante_documento,
                       t.nombre as tutor_nombre,
                       t.email as tutor_email
                FROM pasantias p
                LEFT JOIN usuarios e ON p.estudiante_id = e.id
                LEFT JOIN usuarios t ON p.tutor_id = t.id
                WHERE p.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $pasantia = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pasantia) {
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }

            // Formatear fechas
            if (!empty($pasantia['fecha_inicio'])) {
                $pasantia['fecha_inicio_formateada'] = date('d/m/Y', strtotime($pasantia['fecha_inicio']));
                $pasantia['fecha_inicio'] = date('Y-m-d', strtotime($pasantia['fecha_inicio']));
            } else {
                $pasantia['fecha_inicio_formateada'] = 'No establecida';
            }
            
            if (!empty($pasantia['fecha_fin'])) {
                $pasantia['fecha_fin_formateada'] = date('d/m/Y', strtotime($pasantia['fecha_fin']));
                $pasantia['fecha_fin'] = date('Y-m-d', strtotime($pasantia['fecha_fin']));
            } else {
                $pasantia['fecha_fin_formateada'] = 'No establecida';
            }
            
            $pasantia['fecha_creacion_formateada'] = date('d/m/Y H:i', strtotime($pasantia['fecha_creacion']));

            echo json_encode($pasantia);
        } catch (Exception $e) {
            http_response_code(500);
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
            $stmt->execute();
            $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($tutores);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los tutores: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para obtener estudiantes disponibles para pasantías
    if ($_GET['api'] === 'estudiantes_disponibles') {
        try {
            $stmt = $conexion->prepare("
                SELECT id, nombre, email, codigo_estudiante, documento
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
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($estudiantes);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los estudiantes disponibles: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para obtener datos de estudiante
    if ($_GET['api'] === 'estudiante_info' && isset($_GET['id'])) {
        try {
            $stmt = $conexion->prepare("
                SELECT nombre, email, codigo_estudiante, documento, telefono, nombre_empresa, ciclo
                FROM usuarios
                WHERE id = ? AND rol = 'estudiante' AND opcion_grado = 'pasantia'
            ");
            $stmt->execute([$_GET['id']]);
            $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$estudiante) {
                http_response_code(404);
                echo json_encode(['error' => 'Estudiante no encontrado']);
                exit;
            }
            
            echo json_encode($estudiante);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener la información del estudiante: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para actualizar pasantía
    if ($_GET['api'] === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                exit;
            }
            
            // Construir la consulta según los campos a actualizar
            $campos = [];
            $valores = [];
            
            $camposPermitidos = [
                'titulo', 'descripcion', 'empresa', 'direccion_empresa', 
                'contacto_empresa', 'supervisor_empresa', 'telefono_supervisor',
                'fecha_inicio', 'fecha_fin', 'estado', 'tutor_id'
            ];
            
            foreach ($camposPermitidos as $campo) {
                if (isset($data[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $data[$campo];
                }
            }
            
            if (empty($campos)) {
                http_response_code(400);
                echo json_encode(['error' => 'No se proporcionaron campos para actualizar']);
                exit;
            }
            
            $valores[] = $data['id'];
            
            $sql = "UPDATE pasantias SET " . implode(", ", $campos) . " WHERE id = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute($valores);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada o sin cambios']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía actualizada correctamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar la pasantía: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // API para eliminar pasantía
    if ($_GET['api'] === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de pasantía no proporcionado']);
                exit;
            }
            
            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Pasantía no encontrada']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Pasantía eliminada correctamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar la pasantía: ' . $e->getMessage()]);
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
    WHERE rol = 'tutor' AND estado = 'activo'
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener estudiantes disponibles para pasantías con más información
$estudiantes_disponibles = $conexion->query("
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
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener pasantías existentes
$pasantias = $conexion->query("
    SELECT p.*, 
           e.nombre as estudiante_nombre,
           t.nombre as tutor_nombre
    FROM pasantias p
    LEFT JOIN usuarios e ON p.estudiante_id = e.id
    LEFT JOIN usuarios t ON p.tutor_id = t.id
    ORDER BY p.fecha_creacion DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_pasantia') {
            // Validar datos
            if (empty($_POST['estudiante_id']) || empty($_POST['titulo']) || empty($_POST['empresa']) || 
                empty($_POST['fecha_inicio']) || empty($_POST['fecha_fin'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }
            
            // Procesar archivo si se ha subido
            $archivo_documento = null;
            if (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['archivo_documento']['name'];
                $archivo_tmp = $_FILES['archivo_documento']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_documento = $archivo_nuevo_nombre;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }
            
            // Insertar pasantía
            $stmt = $conexion->prepare("
                INSERT INTO pasantias (
                    titulo, descripcion, empresa, direccion_empresa, 
                    contacto_empresa, supervisor_empresa, telefono_supervisor,
                    fecha_inicio, fecha_fin, estado, estudiante_id, tutor_id, archivo_documento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'] ?? '',
                $_POST['empresa'],
                $_POST['direccion_empresa'] ?? '',
                $_POST['contacto_empresa'] ?? '',
                $_POST['supervisor_empresa'] ?? '',
                $_POST['telefono_supervisor'] ?? '',
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'],
                $_POST['estudiante_id'],
                !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                $archivo_documento
            ]);
            
            $mensaje = "Pasantía creada exitosamente";
            
        } elseif ($_POST['accion'] === 'actualizar_pasantia') {
            if (empty($_POST['pasantia_id'])) {
                throw new Exception("ID de pasantía no válido");
            }
            
            // Procesar archivo si se ha subido uno nuevo
            $documento_adicional = null;
            $archivo_actualizado = false;
            
            if (isset($_FILES['documento_adicional']) && $_FILES['documento_adicional']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['documento_adicional']['name'];
                $archivo_tmp = $_FILES['documento_adicional']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/pasantias/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $documento_adicional = $archivo_nuevo_nombre;
                    $archivo_actualizado = true;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }

            // Actualizar pasantía
            if ($archivo_actualizado) {
                $stmt = $conexion->prepare("
                    UPDATE pasantias 
                    SET titulo = ?, descripcion = ?, empresa = ?, direccion_empresa = ?,
                        contacto_empresa = ?, supervisor_empresa = ?, telefono_supervisor = ?,
                        fecha_inicio = ?, fecha_fin = ?, estado = ?, tutor_id = ?, documento_adicional = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'] ?? '',
                    $_POST['empresa'],
                    $_POST['direccion_empresa'] ?? '',
                    $_POST['contacto_empresa'] ?? '',
                    $_POST['supervisor_empresa'] ?? '',
                    $_POST['telefono_supervisor'] ?? '',
                    $_POST['fecha_inicio'] ?: null,
                    $_POST['fecha_fin'] ?: null,
                    $_POST['estado'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $documento_adicional,
                    $_POST['pasantia_id']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    UPDATE pasantias 
                    SET titulo = ?, descripcion = ?, empresa = ?, direccion_empresa = ?,
                        contacto_empresa = ?, supervisor_empresa = ?, telefono_supervisor = ?,
                        fecha_inicio = ?, fecha_fin = ?, estado = ?, tutor_id = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'] ?? '',
                    $_POST['empresa'],
                    $_POST['direccion_empresa'] ?? '',
                    $_POST['contacto_empresa'] ?? '',
                    $_POST['supervisor_empresa'] ?? '',
                    $_POST['telefono_supervisor'] ?? '',
                    $_POST['fecha_inicio'] ?: null,
                    $_POST['fecha_fin'] ?: null,
                    $_POST['estado'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['pasantia_id']
                ]);
            }
            
            $mensaje = "Pasantía actualizada exitosamente";
            
        } elseif ($_POST['accion'] === 'eliminar_pasantia') {
            if (empty($_POST['pasantia_id'])) {
                throw new Exception("ID de pasantía no válido");
            }
            
            // Eliminar pasantía
            $stmt = $conexion->prepare("DELETE FROM pasantias WHERE id = ?");
            $stmt->execute([$_POST['pasantia_id']]);
            
            $mensaje = "Pasantía eliminada exitosamente";
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
                    <h3>Seleccionar Estudiante *</h3>
                    
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
