<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Inicializar variables para evitar errores
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener estudiantes con opción de grado "proyecto" que NO están asignados a ningún grupo
$estudiantes = $conexion->query("
    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, u.opcion_grado, u.nombre_proyecto 
    FROM usuarios u 
    LEFT JOIN grupos_proyectos gp ON u.id = gp.estudiante_id
    WHERE u.rol = 'estudiante' AND u.opcion_grado = 'proyecto' AND gp.id IS NULL
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los estudiantes para el modal de edición
$todosEstudiantes = $conexion->query("
    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, u.opcion_grado, u.nombre_proyecto,
    (SELECT p.id FROM grupos_proyectos gp JOIN proyectos p ON gp.proyecto_id = p.id WHERE gp.estudiante_id = u.id LIMIT 1) as proyecto_asignado_id
    FROM usuarios u 
    WHERE u.rol = 'estudiante' AND u.opcion_grado = 'proyecto'
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener tutores
$tutores = $conexion->query("
    SELECT u.id, u.nombre, u.email 
    FROM usuarios u 
    WHERE u.rol = 'tutor' 
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener proyectos existentes
$proyectos = $conexion->query("
    SELECT p.*, u.nombre as tutor_nombre,
    (SELECT COUNT(*) FROM grupos_proyectos gp WHERE gp.proyecto_id = p.id) as num_estudiantes
    FROM proyectos p
    LEFT JOIN usuarios u ON p.tutor_id = u.id
    ORDER BY p.fecha_creacion DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener asignaciones de estudiantes a proyectos
$asignacionesEstudiantes = [];
$asignacionesQuery = $conexion->query("
    SELECT gp.proyecto_id, gp.estudiante_id, u.nombre as estudiante_nombre, u.email as estudiante_email
    FROM grupos_proyectos gp
    JOIN usuarios u ON gp.estudiante_id = u.id
    ORDER BY gp.proyecto_id, u.nombre
");

while ($asignacion = $asignacionesQuery->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($asignacionesEstudiantes[$asignacion['proyecto_id']])) {
        $asignacionesEstudiantes[$asignacion['proyecto_id']] = [];
    }
    $asignacionesEstudiantes[$asignacion['proyecto_id']][] = $asignacion;
}

// Obtener nombres de proyectos de la tabla usuarios para filtrado
$nombresProyectos = $conexion->query("
    SELECT DISTINCT nombre_proyecto 
    FROM usuarios 
    WHERE nombre_proyecto IS NOT NULL AND nombre_proyecto != ''
    ORDER BY nombre_proyecto
")->fetchAll(PDO::FETCH_COLUMN);

// Procesar la creación de un nuevo proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_proyecto') {
            // Validar datos
            if (empty($_POST['titulo']) || empty($_POST['descripcion'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }
            
            // Validar que se haya seleccionado al menos un estudiante
            if (!isset($_POST['estudiantes']) || count($_POST['estudiantes']) < 1) {
                throw new Exception("Debe seleccionar al menos un estudiante para el proyecto");
            }
            
            // Validar máximo 3 estudiantes
            if (count($_POST['estudiantes']) > 3) {
                throw new Exception("No puede asignar más de 3 estudiantes a un proyecto");
            }
            
            // Procesar archivo si se ha subido
            $archivo_proyecto = null;
            if (isset($_FILES['archivo_proyecto']) && $_FILES['archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['archivo_proyecto']['name'];
                $archivo_tmp = $_FILES['archivo_proyecto']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                // Validar extensión
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                // Generar nombre único para el archivo
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/proyectos/' . $archivo_nuevo_nombre;
                
                // Crear directorio si no existe
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                // Mover archivo
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_proyecto = $archivo_nuevo_nombre;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }
            
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Insertar proyecto
            $stmt = $conexion->prepare("
                INSERT INTO proyectos (titulo, descripcion, archivo_proyecto, tutor_id, estado, tipo) 
                VALUES (?, ?, ?, ?, 'propuesto', 'proyecto')
            ");
            
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'],
                $archivo_proyecto,
                !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null
            ]);
            
            $proyectoId = $conexion->lastInsertId();
            
            // Asignar estudiantes al proyecto
            foreach ($_POST['estudiantes'] as $estudianteId) {
                $stmt = $conexion->prepare("
                    INSERT INTO grupos_proyectos (proyecto_id, estudiante_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$proyectoId, $estudianteId]);
            }
            
            // Confirmar transacción
            $conexion->commit();
            $mensaje = "Proyecto creado exitosamente";
            
            // Recargar la página para mostrar el nuevo proyecto
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
            
        } elseif ($_POST['accion'] === 'actualizar_proyecto') {
            // Validar datos
            if (empty($_POST['proyecto_id']) || empty($_POST['titulo']) || empty($_POST['descripcion'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }
            
            // Validar estudiantes si se han enviado
            if (isset($_POST['edit_estudiantes'])) {
                // Validar que se haya seleccionado al menos un estudiante
                if (count($_POST['edit_estudiantes']) < 1) {
                    throw new Exception("Debe seleccionar al menos un estudiante para el proyecto");
                }
                
                // Validar máximo 3 estudiantes
                if (count($_POST['edit_estudiantes']) > 3) {
                    throw new Exception("No puede asignar más de 3 estudiantes a un proyecto");
                }
            }
            
            // Procesar archivo si se ha subido
            $archivo_proyecto = null;
            $archivo_actualizado = false;
            
            if (isset($_FILES['edit_archivo_proyecto']) && $_FILES['edit_archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['edit_archivo_proyecto']['name'];
                $archivo_tmp = $_FILES['edit_archivo_proyecto']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                // Validar extensión
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                // Generar nombre único para el archivo
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/proyectos/' . $archivo_nuevo_nombre;
                
                // Crear directorio si no existe
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                // Mover archivo
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_proyecto = $archivo_nuevo_nombre;
                    $archivo_actualizado = true;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }
            
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Actualizar proyecto
            if ($archivo_actualizado) {
                $stmt = $conexion->prepare("
                    UPDATE proyectos 
                    SET titulo = ?, descripcion = ?, archivo_proyecto = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $archivo_proyecto,
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['proyecto_id']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    UPDATE proyectos 
                    SET titulo = ?, descripcion = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['proyecto_id']
                ]);
            }
            
            // Actualizar estudiantes si se han enviado
            if (isset($_POST['edit_estudiantes'])) {
                // Obtener estudiantes actuales para liberarlos si no están en la nueva selección
                $stmt = $conexion->prepare("SELECT estudiante_id FROM grupos_proyectos WHERE proyecto_id = ?");
                $stmt->execute([$_POST['proyecto_id']]);
                $estudiantesActuales = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Eliminar asignaciones actuales
                $stmt = $conexion->prepare("DELETE FROM grupos_proyectos WHERE proyecto_id = ?");
                $stmt->execute([$_POST['proyecto_id']]);
                
                // Asignar nuevos estudiantes
                foreach ($_POST['edit_estudiantes'] as $estudianteId) {
                    $stmt = $conexion->prepare("
                        INSERT INTO grupos_proyectos (proyecto_id, estudiante_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$_POST['proyecto_id'], $estudianteId]);
                }
            }
            
            // Confirmar transacción
            $conexion->commit();
            $mensaje = "Proyecto actualizado exitosamente";
            
            // Recargar la página para mostrar los cambios
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
        } elseif ($_POST['accion'] === 'eliminar_proyecto') {
            // Validar que se haya proporcionado un ID de proyecto
            if (empty($_POST['proyecto_id'])) {
                throw new Exception("ID de proyecto no válido");
            }
            
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Eliminar relaciones con estudiantes
            $stmt = $conexion->prepare("DELETE FROM grupos_proyectos WHERE proyecto_id = ?");
            $stmt->execute([$_POST['proyecto_id']]);
            
            // Eliminar proyecto
            $stmt = $conexion->prepare("DELETE FROM proyectos WHERE id = ?");
            $stmt->execute([$_POST['proyecto_id']]);
            
            // Confirmar transacción
            $conexion->commit();
            $mensaje = "Proyecto eliminado exitosamente";
            
            // Recargar la página para mostrar los cambios
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
        }
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Recuperar mensaje de la URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}

// Obtener títulos de proyectos existentes para autocompletado
$titulosProyectos = $conexion->query("
    SELECT DISTINCT titulo FROM proyectos ORDER BY titulo
")->fetchAll(PDO::FETCH_COLUMN);

// Convertir datos a JSON para usar en JavaScript
$asignacionesJSON = json_encode($asignacionesEstudiantes);
$todosEstudiantesJSON = json_encode($todosEstudiantes);
$proyectosJSON = json_encode($proyectos);

// Cerrar conexión
$conexion = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proyectos - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestion_proyectos.css">
</head>
<body>
    <div id="logo" onclick="toggleNav()">Logo</div>
    
    <nav id="navbar">
        <div class="nav-header">
            <div id="nav-logo" onclick="toggleNav()">Logo</div>
        </div>
        <ul>
            <li><a href="#">Inicio</a></li>
            <li><a href="#">Aprobación de Usuarios</a></li>
            <li><a href="#">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#" class="active">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="#">Seminario</a></li>
                    <li><a href="#">Proyectos</a></li>
                    <li><a href="#">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="#">Reportes y Estadísticas</a></li>
            <li><a href="#">Usuario: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
            <li><a href="#">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main>
        <h1>Gestión de Proyectos</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button id="crearProyectoTab" class="active">Crear Proyecto</button>
            <button id="listarProyectosTab">Listar Proyectos</button>
        </div>
        
        <!-- Sección para crear proyectos -->
        <section id="crearProyectoSection" class="tab-content">
            <form id="formCrearProyecto" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_proyecto">
                
                <div class="form-group">
                    <h2>Información del Proyecto</h2>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="titulo">Título del Proyecto *</label>
                            <div class="autocomplete-container">
                                <input type="text" id="titulo" name="titulo" required autocomplete="off">
                                <div id="tituloSugerencias" class="autocomplete-items"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="descripcion">Descripción del Proyecto *</label>
                            <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="archivo_proyecto">Archivo del Proyecto (PDF o Word)</label>
                            <input type="file" id="archivo_proyecto" name="archivo_proyecto" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <h2>Asignación de Estudiantes</h2>
                    <p class="info-text">Seleccione de 1 a 3 estudiantes para el proyecto. El primer estudiante seleccionado será el líder del proyecto.</p>
                    
                    <div class="search-filter">
                        <input type="text" id="searchEstudiantes" placeholder="Buscar estudiantes...">
                    </div>
                    
                    <div class="search-filter">
                        <input type="text" id="searchProyectosEstudiantes" placeholder="Buscar por nombre de proyecto...">
                        <select id="filterNombreProyecto">
                            <option value="">Todos los proyectos</option>
                            <?php foreach ($nombresProyectos as $nombreProyecto): ?>
                                <option value="<?= strtolower(htmlspecialchars($nombreProyecto)) ?>">
                                    <?= htmlspecialchars($nombreProyecto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="estudiantes-container">
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <div class="estudiante-card" 
                                 data-nombre="<?= strtolower(htmlspecialchars($estudiante['nombre'])) ?>"
                                 data-opcion="<?= strtolower(htmlspecialchars($estudiante['opcion_grado'] ?? '')) ?>"
                                 data-proyecto="<?= strtolower(htmlspecialchars($estudiante['nombre_proyecto'] ?? '')) ?>">
                                <div class="estudiante-info">
                                    <h3><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                                    <p><strong>Código:</strong> <?= htmlspecialchars($estudiante['codigo_estudiante'] ?? 'N/A') ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($estudiante['email']) ?></p>
                                    <p><strong>Opción de Grado:</strong> <?= htmlspecialchars(ucfirst($estudiante['opcion_grado'] ?? 'No asignada')) ?></p>
                                    <?php if (!empty($estudiante['nombre_proyecto'])): ?>
                                        <p><strong>Proyecto:</strong> <?= htmlspecialchars($estudiante['nombre_proyecto']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="estudiante-select">
                                    <input type="checkbox" name="estudiantes[]" value="<?= $estudiante['id'] ?>" class="estudiante-checkbox">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="estudiantes-seleccionados">
                        <h3>Estudiantes Seleccionados: <span id="contadorEstudiantes">0/3</span></h3>
                        <ul id="listaEstudiantesSeleccionados"></ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <h2>Asignación de Tutor</h2>
                    
                    <div class="search-filter">
                        <input type="text" id="searchTutores" placeholder="Buscar tutores...">
                    </div>
                    
                    <div class="tutores-container">
                        <?php foreach ($tutores as $tutor): ?>
                            <div class="tutor-card" data-nombre="<?= strtolower(htmlspecialchars($tutor['nombre'])) ?>">
                                <div class="tutor-info">
                                    <h3><?= htmlspecialchars($tutor['nombre']) ?></h3>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($tutor['email']) ?></p>
                                </div>
                                <div class="tutor-select">
                                    <input type="radio" name="tutor_id" value="<?= $tutor['id'] ?>" class="tutor-radio">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Crear Proyecto</button>
                    <button type="reset" class="btn-secondary">Limpiar Formulario</button>
                </div>
            </form>
        </section>
        
        <!-- Sección para listar proyectos -->
        <section id="listarProyectosSection" class="tab-content" style="display: none;">
            <div class="search-filter">
                <input type="text" id="searchProyectos" placeholder="Buscar proyectos...">
                <select id="filterEstadoProyecto">
                    <option value="">Todos los estados</option>
                    <option value="propuesto">Propuesto</option>
                    <option value="en_revision">En Revisión</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="rechazado">Rechazado</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="finalizado">Finalizado</option>
                </select>
            </div>
            
            <div class="proyectos-grid">
                <?php foreach ($proyectos as $proyecto): ?>
                    <div class="proyecto-card" 
                         data-id="<?= $proyecto['id'] ?>"
                         data-titulo="<?= strtolower(htmlspecialchars($proyecto['titulo'])) ?>"
                         data-estado="<?= htmlspecialchars($proyecto['estado']) ?>">
                        <div class="proyecto-header estado-<?= htmlspecialchars($proyecto['estado']) ?>">
                            <h3><?= htmlspecialchars($proyecto['titulo']) ?></h3>
                            <span class="proyecto-estado"><?= ucfirst(str_replace('_', ' ', $proyecto['estado'])) ?></span>
                        </div>
                        <div class="proyecto-body">
                            <p><strong>Estudiantes:</strong> <?= $proyecto['num_estudiantes'] ?>/3</p>
                            <p><strong>Tutor:</strong> <?= htmlspecialchars($proyecto['tutor_nombre'] ?? 'No asignado') ?></p>
                            <p><strong>Fecha creación:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_creacion'])) ?></p>
                            <?php if (!empty($proyecto['archivo_proyecto'])): ?>
                                <p><strong>Archivo:</strong> <a href="/uploads/proyectos/<?= htmlspecialchars($proyecto['archivo_proyecto']) ?>" target="_blank" class="archivo-link">Ver archivo</a></p>
                            <?php endif; ?>
                            <div class="proyecto-descripcion">
                                <?= nl2br(htmlspecialchars(substr($proyecto['descripcion'], 0, 100))) ?>
                                <?= (strlen($proyecto['descripcion']) > 100) ? '...' : '' ?>
                            </div>
                        </div>
                        <div class="proyecto-footer">
                            <button class="btn-ver" onclick="verProyecto(<?= $proyecto['id'] ?>)">Ver Detalles</button>
                            <button class="btn-editar" onclick="editarProyecto(<?= $proyecto['id'] ?>)">Editar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($proyectos)): ?>
                    <div class="no-proyectos">
                        <p>No hay proyectos registrados. Cree un nuevo proyecto en la pestaña "Crear Proyecto".</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <!-- Modal para ver detalles del proyecto -->
    <div id="modalVerProyecto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerProyecto')">&times;</span>
            <h2>Detalles del Proyecto</h2>
            <div id="detallesProyecto"></div>
        </div>
    </div>
    
    <!-- Modal para editar proyecto -->
    <div id="modalEditarProyecto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarProyecto')">&times;</span>
            <h2>Editar Proyecto</h2>
            <form id="formEditarProyecto" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_proyecto">
                <input type="hidden" name="proyecto_id" id="edit_proyecto_id">
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_titulo">Título del Proyecto *</label>
                        <div class="autocomplete-container">
                            <input type="text" id="edit_titulo" name="titulo" required autocomplete="off">
                            <div id="editTituloSugerencias" class="autocomplete-items"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_estado">Estado del Proyecto *</label>
                        <select id="edit_estado" name="estado" required>
                            <option value="propuesto">Propuesto</option>
                            <option value="en_revision">En Revisión</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="finalizado">Finalizado</option>
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
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_descripcion">Descripción del Proyecto *</label>
                        <textarea id="edit_descripcion" name="descripcion" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_archivo_proyecto">Archivo del Proyecto (PDF o Word)</label>
                        <input type="file" id="edit_archivo_proyecto" name="edit_archivo_proyecto" accept=".pdf,.doc,.docx">
                        <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        <div id="archivo_actual" class="archivo-actual"></div>
                    </div>
                </div>
                
                <!-- Sección para editar estudiantes asignados -->
                <div class="form-group">
                    <h2>Estudiantes Asignados</h2>
                    <p class="info-text">Seleccione de 1 a 3 estudiantes para el proyecto. El primer estudiante seleccionado será el líder del proyecto.</p>
                    
                    <div class="estudiantes-actuales">
                        <h3>Estudiantes Actuales: <span id="contadorEstudiantesEdit">0/3</span></h3>
                        <ul id="listaEstudiantesActuales"></ul>
                    </div>
                    
                    <div class="search-filter">
                        <input type="text" id="searchEstudiantesEdit" placeholder="Buscar estudiantes...">
                    </div>
                    
                    <div class="estudiantes-container" id="estudiantesEditContainer">
                        <!-- Los estudiantes se cargarán dinámicamente aquí -->
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarProyecto')">Cancelar</button>
                    <button type="button" class="btn-danger" onclick="confirmarEliminarProyecto()">Eliminar Proyecto</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para confirmar eliminación de proyecto -->
    <div id="modalConfirmarEliminar" class="modal">
        <div class="modal-content modal-small">
            <span class="close" onclick="cerrarModal('modalConfirmarEliminar')">&times;</span>
            <h2>Confirmar Eliminación</h2>
            <p>¿Está seguro que desea eliminar este proyecto? Esta acción no se puede deshacer.</p>
            <form id="formEliminarProyecto" method="POST" action="">
                <input type="hidden" name="accion" value="eliminar_proyecto">
                <input type="hidden" name="proyecto_id" id="eliminar_proyecto_id">
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
        // Datos de proyectos y estudiantes cargados desde PHP
        const proyectos = <?= $proyectosJSON ?>;
        const asignacionesEstudiantes = <?= $asignacionesJSON ?>;
        const todosEstudiantes = <?= $todosEstudiantesJSON ?>;
        
        // Funciones de navegación
        function toggleNav() {
            document.getElementById("navbar").classList.toggle("active");
            document.querySelector("main").classList.toggle("nav-active");
            document.querySelector("footer").classList.toggle("nav-active");
        }
        
        // Manejo de pestañas
        const crearProyectoTab = document.getElementById('crearProyectoTab');
        const listarProyectosTab = document.getElementById('listarProyectosTab');
        const crearProyectoSection = document.getElementById('crearProyectoSection');
        const listarProyectosSection = document.getElementById('listarProyectosSection');
        
        crearProyectoTab.addEventListener('click', () => {
            crearProyectoTab.classList.add('active');
            listarProyectosTab.classList.remove('active');
            crearProyectoSection.style.display = 'block';
            listarProyectosSection.style.display = 'none';
        });
        
        listarProyectosTab.addEventListener('click', () => {
            listarProyectosTab.classList.add('active');
            crearProyectoTab.classList.remove('active');
            listarProyectosSection.style.display = 'block';
            crearProyectoSection.style.display = 'none';
        });
        
        // Búsqueda y filtrado de estudiantes
        const searchEstudiantes = document.getElementById('searchEstudiantes');
        const searchProyectosEstudiantes = document.getElementById('searchProyectosEstudiantes');
        const filterNombreProyecto = document.getElementById('filterNombreProyecto');
        const estudianteCards = document.querySelectorAll('.estudiante-card');
        
        searchEstudiantes.addEventListener('input', filtrarEstudiantes);
        searchProyectosEstudiantes.addEventListener('input', filtrarEstudiantes);
        filterNombreProyecto.addEventListener('change', filtrarEstudiantes);
        
        function filtrarEstudiantes() {
            const searchTerm = searchEstudiantes.value.toLowerCase();
            const searchProyectoTerm = searchProyectosEstudiantes.value.toLowerCase();
            const nombreProyecto = filterNombreProyecto.value.toLowerCase();
            
            estudianteCards.forEach(card => {
                const nombre = card.dataset.nombre;
                const proyecto = card.dataset.proyecto;
                
                const matchSearch = nombre.includes(searchTerm);
                const matchProyectoSearch = proyecto.includes(searchProyectoTerm);
                const matchProyecto = nombreProyecto === '' || proyecto === nombreProyecto;
                
                card.style.display = (matchSearch && matchProyecto && matchProyectoSearch) ? 'flex' : 'none';
            });
        }
        
        // Búsqueda de estudiantes en el modal de edición
        const searchEstudiantesEdit = document.getElementById('searchEstudiantesEdit');
        
        searchEstudiantesEdit.addEventListener('input', () => {
            const searchTerm = searchEstudiantesEdit.value.toLowerCase();
            const estudianteCardsEdit = document.querySelectorAll('#estudiantesEditContainer .estudiante-card');
            
            estudianteCardsEdit.forEach(card => {
                const nombre = card.dataset.nombre;
                card.style.display = nombre.includes(searchTerm) ? 'flex' : 'none';
            });
        });
        
        // Búsqueda de tutores
        const searchTutores = document.getElementById('searchTutores');
        const tutorCards = document.querySelectorAll('.tutor-card');
        
        searchTutores.addEventListener('input', () => {
            const searchTerm = searchTutores.value.toLowerCase();
            
            tutorCards.forEach(card => {
                const nombre = card.dataset.nombre;
                card.style.display = nombre.includes(searchTerm) ? 'flex' : 'none';
            });
        });
        
        // Búsqueda y filtrado de proyectos
        const searchProyectos = document.getElementById('searchProyectos');
        const filterEstadoProyecto = document.getElementById('filterEstadoProyecto');
        const proyectoCards = document.querySelectorAll('.proyecto-card');
        
        searchProyectos.addEventListener('input', filtrarProyectos);
        filterEstadoProyecto.addEventListener('change', filtrarProyectos);
        
        function filtrarProyectos() {
            const searchTerm = searchProyectos.value.toLowerCase();
            const estadoProyecto = filterEstadoProyecto.value;
            
            proyectoCards.forEach(card => {
                const titulo = card.dataset.titulo;
                const estado = card.dataset.estado;
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estadoProyecto === '' || estado === estadoProyecto;
                
                card.style.display = (matchSearch && matchEstado) ? 'flex' : 'none';
            });
        }
        
        // Control de selección de estudiantes
        const estudianteCheckboxes = document.querySelectorAll('.estudiante-checkbox');
        const contadorEstudiantes = document.getElementById('contadorEstudiantes');
        const listaEstudiantesSeleccionados = document.getElementById('listaEstudiantesSeleccionados');
        
        estudianteCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', actualizarEstudiantesSeleccionados);
        });
        
        function actualizarEstudiantesSeleccionados() {
            const seleccionados = document.querySelectorAll('.estudiante-checkbox:checked');
            const numSeleccionados = seleccionados.length;
            
            // Actualizar contador
            contadorEstudiantes.textContent = `${numSeleccionados}/3`;
            
            // Limitar a 3 estudiantes
            if (numSeleccionados > 3) {
                alert('No puede seleccionar más de 3 estudiantes para un proyecto');
                this.checked = false;
                actualizarEstudiantesSeleccionados();
                return;
            }
            
            // Actualizar lista de estudiantes seleccionados
            listaEstudiantesSeleccionados.innerHTML = '';
            
            seleccionados.forEach((checkbox, index) => {
                const estudianteCard = checkbox.closest('.estudiante-card');
                const nombreEstudiante = estudianteCard.querySelector('h3').textContent;
                
                const li = document.createElement('li');
                li.textContent = `${index === 0 ? '👑 ' : ''}${nombreEstudiante}${index === 0 ? ' (Líder)' : ''}`;
                listaEstudiantesSeleccionados.appendChild(li);
            });
        }
        
        // Control de selección de estudiantes en el modal de edición
        function actualizarEstudiantesSeleccionadosEdit() {
            const seleccionados = document.querySelectorAll('.estudiante-checkbox-edit:checked');
            const numSeleccionados = seleccionados.length;
            
            // Actualizar contador
            const contadorEstudiantesEdit = document.getElementById('contadorEstudiantesEdit');
            contadorEstudiantesEdit.textContent = `${numSeleccionados}/3`;
            
            // Limitar a 3 estudiantes
            if (numSeleccionados > 3) {
                alert('No puede seleccionar más de 3 estudiantes para un proyecto');
                this.checked = false;
                actualizarEstudiantesSeleccionadosEdit();
                return;
            }
            
            // Actualizar lista de estudiantes seleccionados
            const listaEstudiantesActuales = document.getElementById('listaEstudiantesActuales');
            listaEstudiantesActuales.innerHTML = '';
            
            seleccionados.forEach((checkbox, index) => {
                const estudianteCard = checkbox.closest('.estudiante-card');
                const nombreEstudiante = estudianteCard.querySelector('h3').textContent;
                
                const li = document.createElement('li');
                li.textContent = `${index === 0 ? '👑 ' : ''}${nombreEstudiante}${index === 0 ? ' (Líder)' : ''}`;
                listaEstudiantesActuales.appendChild(li);
            });
        }
        
        // Funciones para modales
        function verProyecto(proyectoId) {
            // Buscar el proyecto en los datos cargados
            const proyecto = proyectos.find(p => p.id == proyectoId);
            
            if (!proyecto) {
                alert('No se pudo encontrar información del proyecto');
                return;
            }
            
            // Obtener estudiantes asignados a este proyecto
            const estudiantes = asignacionesEstudiantes[proyectoId] || [];
            
            // Crear objeto con los datos disponibles
            const proyectoCompleto = {
                ...proyecto,
                estudiantes: estudiantes.map((est, index) => ({
                    ...est,
                    rol_en_proyecto: index === 0 ? 'lider' : 'miembro'
                })),
                avances: [],
                comentarios: []
            };
            
            // Mostrar detalles con la información disponible
            mostrarDetallesProyecto(proyectoCompleto);
        }
        
        function mostrarDetallesProyecto(proyecto) {
            const detallesProyecto = document.getElementById('detallesProyecto');
            
            // Construir HTML con los detalles del proyecto
            let html = `
                <div class="proyecto-detalle">
                    <div class="proyecto-header estado-${proyecto.estado}">
                        <h3>${proyecto.titulo}</h3>
                        <span class="proyecto-estado">${proyecto.estado.replace('_', ' ')}</span>
                    </div>
                    <div class="proyecto-info">
                        <p><strong>Fecha de creación:</strong> ${proyecto.fecha_creacion}</p>
                        <p><strong>Última actualización:</strong> ${proyecto.fecha_actualizacion || 'Sin actualizar'}</p>
                        <p><strong>Tutor asignado:</strong> ${proyecto.tutor_nombre || 'No asignado'}</p>
                        
                        ${proyecto.archivo_proyecto ? 
                            `<p><strong>Archivo del proyecto:</strong> <a href="/uploads/proyectos/${proyecto.archivo_proyecto}" target="_blank" class="archivo-link">Descargar archivo</a></p>` : 
                            '<p><strong>Archivo del proyecto:</strong> No hay archivo adjunto</p>'
                        }
                        
                        <h4>Descripción:</h4>
                        <div class="descripcion-completa">${proyecto.descripcion.replace(/\n/g, '<br>')}</div>
                        
                        <h4>Estudiantes asignados:</h4>
                        ${proyecto.estudiantes && proyecto.estudiantes.length > 0 ? 
                            `<ul class="estudiantes-lista">
                                ${proyecto.estudiantes.map((est, index) => `<li>${index === 0 ? '👑 ' : ''}${est.estudiante_nombre} (${est.estudiante_email})${index === 0 ? ' (Líder)' : ''}</li>`).join('')}
                            </ul>` : 
                            '<p>No hay estudiantes asignados</p>'
                        }
                        
                        <h4>Avances del proyecto:</h4>
                        ${proyecto.avances && proyecto.avances.length > 0 ? 
                            `<ul class="avances-lista">
                                ${proyecto.avances.map(av => `
                                    <li>
                                        <strong>${av.titulo}</strong> - ${new Date(av.fecha_registro).toLocaleDateString()}
                                        <div>${av.descripcion}</div>
                                        <div class="progreso-barra">
                                            <div class="progreso" style="width: ${av.porcentaje_avance}%"></div>
                                            <span>${av.porcentaje_avance}%</span>
                                        </div>
                                    </li>
                                `).join('')}
                            </ul>` : 
                            '<p>No hay avances registrados</p>'
                        }
                        
                        <h4>Comentarios:</h4>
                        ${proyecto.comentarios && proyecto.comentarios.length > 0 ? 
                            `<ul class="comentarios-lista">
                                ${proyecto.comentarios.map(com => `
                                    <li>
                                        <strong>${com.nombre_usuario}</strong> - ${new Date(com.fecha_comentario).toLocaleDateString()}
                                        <div>${com.comentario}</div>
                                    </li>
                                `).join('')}
                            </ul>` : 
                            '<p>No hay comentarios</p>'
                        }
                    </div>
                </div>
            `;
            
            detallesProyecto.innerHTML = html;
            abrirModal('modalVerProyecto');
        }
        
        function editarProyecto(proyectoId) {
            // Buscar el proyecto en los datos cargados
            const proyecto = proyectos.find(p => p.id == proyectoId);
            
            if (!proyecto) {
                alert('No se pudo encontrar información del proyecto');
                return;
            }
            
            // Llenar el formulario con los datos del proyecto
            document.getElementById('edit_proyecto_id').value = proyecto.id;
            document.getElementById('eliminar_proyecto_id').value = proyecto.id;
            document.getElementById('edit_titulo').value = proyecto.titulo;
            document.getElementById('edit_descripcion').value = proyecto.descripcion;
            document.getElementById('edit_estado').value = proyecto.estado;
            
            // Preseleccionar el tutor
            if (proyecto.tutor_id) {
                document.getElementById('edit_tutor_id').value = proyecto.tutor_id;
            } else {
                document.getElementById('edit_tutor_id').value = '';
            }
            
            // Mostrar información del archivo actual si existe
            const archivoActualDiv = document.getElementById('archivo_actual');
            if (proyecto.archivo_proyecto) {
                archivoActualDiv.innerHTML = `
                    <p>Archivo actual: <a href="/uploads/proyectos/${proyecto.archivo_proyecto}" target="_blank">${proyecto.archivo_proyecto}</a></p>
                    <p>Si sube un nuevo archivo, reemplazará al actual.</p>
                `;
            } else {
                archivoActualDiv.innerHTML = '<p>No hay archivo adjunto actualmente.</p>';
            }
            
            // Cargar estudiantes para edición
            cargarEstudiantesParaEdicion(proyectoId);
            
            abrirModal('modalEditarProyecto');
        }
        
        function cargarEstudiantesParaEdicion(proyectoId) {
            // Obtener estudiantes asignados a este proyecto
            const estudiantesAsignados = asignacionesEstudiantes[proyectoId] || [];
            const estudiantesAsignadosIds = estudiantesAsignados.map(est => est.estudiante_id);
            
            // Filtrar estudiantes disponibles (no asignados a otros proyectos o asignados a este proyecto)
            const estudiantesDisponibles = todosEstudiantes.filter(est => 
                !est.proyecto_asignado_id || est.proyecto_asignado_id == proyectoId
            );
            
            // Mostrar estudiantes en el contenedor
            const contenedor = document.getElementById('estudiantesEditContainer');
            contenedor.innerHTML = '';
            
            estudiantesDisponibles.forEach(estudiante => {
                const isAsignado = estudiantesAsignadosIds.includes(estudiante.id);
                
                const div = document.createElement('div');
                div.className = 'estudiante-card';
                div.dataset.id = estudiante.id;
                div.dataset.nombre = estudiante.nombre.toLowerCase();
                
                div.innerHTML = `
                    <div class="estudiante-info">
                        <h3>${estudiante.nombre}</h3>
                        <p><strong>Código:</strong> ${estudiante.codigo_estudiante || 'N/A'}</p>
                        <p><strong>Email:</strong> ${estudiante.email}</p>
                        <p><strong>Opción de Grado:</strong> ${estudiante.opcion_grado || 'No asignada'}</p>
                        ${estudiante.nombre_proyecto ? `<p><strong>Proyecto:</strong> ${estudiante.nombre_proyecto}</p>` : ''}
                    </div>
                    <div class="estudiante-select">
                        <input type="checkbox" name="edit_estudiantes[]" value="${estudiante.id}" class="estudiante-checkbox-edit" ${isAsignado ? 'checked' : ''}>
                    </div>
                `;
                
                contenedor.appendChild(div);
            });
            
            // Agregar eventos a los checkboxes
            document.querySelectorAll('.estudiante-checkbox-edit').forEach(checkbox => {
                checkbox.addEventListener('change', actualizarEstudiantesSeleccionadosEdit);
            });
            
            // Actualizar la lista de estudiantes seleccionados
            actualizarEstudiantesSeleccionadosEdit();
        }
        
        function confirmarEliminarProyecto() {
            cerrarModal('modalEditarProyecto');
            abrirModal('modalConfirmarEliminar');
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden'; // Evitar scroll en el fondo
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto'; // Restaurar scroll
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Autocompletado para el título del proyecto
        const tituloInput = document.getElementById('titulo');
        const tituloSugerencias = document.getElementById('tituloSugerencias');
        const editTituloInput = document.getElementById('edit_titulo');
        const editTituloSugerencias = document.getElementById('editTituloSugerencias');
        
        // Obtener títulos de proyectos existentes
        const titulosProyectos = <?= json_encode($titulosProyectos) ?>;
        
        // Función para mostrar sugerencias
        function mostrarSugerencias(input, sugerenciasDiv) {
            const valor = input.value.toLowerCase();
            sugerenciasDiv.innerHTML = '';
            
            if (valor.length < 2) {
                sugerenciasDiv.style.display = 'none';
                return;
            }
            
            const coincidencias = titulosProyectos.filter(titulo => 
                titulo.toLowerCase().includes(valor)
            );
            
            if (coincidencias.length === 0) {
                sugerenciasDiv.style.display = 'none';
                return;
            }
            
            coincidencias.forEach(titulo => {
                const div = document.createElement('div');
                div.innerHTML = titulo;
                div.addEventListener('click', function() {
                    input.value = titulo;
                    sugerenciasDiv.style.display = 'none';
                });
                sugerenciasDiv.appendChild(div);
            });
            
            sugerenciasDiv.style.display = 'block';
        }
        
        // Configurar autocompletado para el formulario de creación
        tituloInput.addEventListener('input', function() {
            mostrarSugerencias(tituloInput, tituloSugerencias);
        });
        
        tituloInput.addEventListener('blur', function() {
            // Pequeño retraso para permitir que el clic en la sugerencia funcione
            setTimeout(() => {
                tituloSugerencias.style.display = 'none';
            }, 200);
        });
        
        // Configurar autocompletado para el formulario de edición
        editTituloInput.addEventListener('input', function() {
            mostrarSugerencias(editTituloInput, editTituloSugerencias);
        });
        
        editTituloInput.addEventListener('blur', function() {
            setTimeout(() => {
                editTituloSugerencias.style.display = 'none';
            }, 200);
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